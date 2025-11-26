<?php
// app/controllers/BookController.php

class BookController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // --- ADD BOOK LOGIC ---
    public function addBook($postData, $fileData, $userId) {
        $title = trim($postData['title'] ?? '');
        $author = trim($postData['author'] ?? '');
        $isbn = trim($postData['isbn'] ?? '');
        $price = filter_var($postData['price'] ?? 0.00, FILTER_VALIDATE_FLOAT);
        $category = trim($postData['category'] ?? '');
        $quantity = filter_var($postData['quantity'] ?? 1, FILTER_VALIDATE_INT);
        $coverImagePath = NULL;

        // Validation
        if (empty($title) || empty($isbn) || $price === false || empty($category) || $quantity === false || $quantity < 1) {
            return ['message' => "Please check your input values.", 'type' => 'error'];
        }

        // File Upload Handling
        if (isset($fileData['cover_image']) && $fileData['cover_image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = $this->handleFileUpload($fileData['cover_image'], $isbn);
            if ($uploadResult['success']) {
                $coverImagePath = $uploadResult['path'];
            } else {
                return ['message' => $uploadResult['message'], 'type' => 'error'];
            }
        }

        try {
            $this->pdo->beginTransaction();

            // Insert Book
            $sql = "INSERT INTO Book (Title, Author, ISBN, Price, CoverImagePath, Category, Status) 
                    VALUES (:title, :author, :isbn, :price, :cover_path, :category, 'Available')";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':title' => $title, ':author' => $author, ':isbn' => $isbn,
                ':price' => $price, ':category' => $category, ':cover_path' => $coverImagePath,
            ]);
            $newBookId = $this->pdo->lastInsertId();

            // Insert Copies
            if ($quantity > 0) {
                $copySql = "INSERT INTO Book_Copy (BookID, Status) VALUES (?, 'Available')";
                $copyStmt = $this->pdo->prepare($copySql);
                for ($i = 0; $i < $quantity; $i++) {
                    $copyStmt->execute([$newBookId]);
                }
            }

            // Log Action
            $this->logAction($userId, $newBookId, 'Added', "Added book '{$title}' (ISBN {$isbn}). Total copies: {$quantity}.");

            $this->pdo->commit();
            return ['message' => "Book '{$title}' added successfully! Copies: {$quantity}.", 'type' => 'success'];

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            if ($e->getCode() === '23000') {
                return ['message' => "Error: ISBN '{$isbn}' already exists.", 'type' => 'error'];
            }
            error_log("Add Book Error: " . $e->getMessage());
            return ['message' => "Database Error: Could not add book.", 'type' => 'error'];
        }
    }

    // --- UPDATE BOOK LOGIC ---
    public function updateBook($postData, $userId) {
        $bookID = filter_var($postData['book_id'] ?? null, FILTER_VALIDATE_INT);
        $title = trim($postData['title'] ?? '');
        $author = trim($postData['author'] ?? '');
        $price = filter_var($postData['price'] ?? 0.00, FILTER_VALIDATE_FLOAT);
        $category = trim($postData['category'] ?? '');
        $new_quantity = filter_var($postData['quantity'] ?? 0, FILTER_VALIDATE_INT);
        $isbn = trim($postData['isbn'] ?? ''); // Read-only in form usually, but needed for logic

        if (empty($title) || $price === false || $new_quantity < 0 || !$bookID) {
            return ['message' => "Please check all input values.", 'type' => 'error'];
        }

        try {
            $this->pdo->beginTransaction();
            
            // Get current stock data
            $current_data = $this->getBookByISBN($isbn);
            if (!$current_data) throw new Exception("Book not found for stock check.");

            $old_total = $current_data['CopiesTotal'] ?? 0;
            $old_available = $current_data['CopiesAvailable'] ?? 0;
            $currently_borrowed = $old_total - $old_available;
            $quantity_difference = $new_quantity - $old_total;

            if ($new_quantity < $currently_borrowed) {
                $this->pdo->rollBack();
                return ['message' => "Error: Cannot reduce stock below borrowed amount ({$currently_borrowed}).", 'type' => 'error'];
            }

            // Update Details
            $sql = "UPDATE Book SET Title = :title, Author = :author, Price = :price, Category = :category WHERE BookID = :book_id";
            $this->pdo->prepare($sql)->execute([
                ':title' => $title, ':author' => $author, ':price' => $price,
                ':category' => $category, ':book_id' => $bookID,
            ]);

            // Sync Copies
            $log_desc = [];
            if ($quantity_difference > 0) {
                $copySql = "INSERT INTO Book_Copy (BookID, Status) VALUES (?, 'Available')";
                $copyStmt = $this->pdo->prepare($copySql);
                for ($i = 0; $i < $quantity_difference; $i++) {
                    $copyStmt->execute([$bookID]);
                }
                $log_desc[] = "Added {$quantity_difference} copies";
            } elseif ($quantity_difference < 0) {
                $remove_count = abs($quantity_difference);
                $stmt_copies = $this->pdo->prepare("SELECT CopyID FROM Book_Copy WHERE BookID = ? AND Status = 'Available' LIMIT ?");
                $stmt_copies->bindParam(1, $bookID, PDO::PARAM_INT);
                $stmt_copies->bindParam(2, $remove_count, PDO::PARAM_INT);
                $stmt_copies->execute();
                $ids = $stmt_copies->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($ids)) {
                    $inQuery = implode(',', array_fill(0, count($ids), '?'));
                    $this->pdo->prepare("DELETE FROM Book_Copy WHERE CopyID IN ($inQuery)")->execute($ids);
                }
                $log_desc[] = "Removed {$remove_count} copies";
            }

            $logMsg = "Stock updated to {$new_quantity}. " . implode("; ", $log_desc);
            $this->logAction($userId, $bookID, 'Updated', $logMsg);

            $this->pdo->commit();
            return ['message' => "Book '{$title}' updated successfully.", 'type' => 'success'];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Update Book Error: " . $e->getMessage());
            return ['message' => "Database Error: Could not update book.", 'type' => 'error'];
        }
    }

    // --- ARCHIVE BOOK LOGIC ---
    public function archiveBook($postData, $userId) {
        $isbn = trim($postData['isbn'] ?? '');
        $reason = trim($postData['reason'] ?? 'Not specified');

        if (empty($isbn) || empty($reason)) {
            return ['message' => "Please enter ISBN and reason.", 'type' => 'error'];
        }

        try {
            $stmt = $this->pdo->prepare("SELECT BookID, Title, CopiesAvailable, CopiesTotal, Status FROM Book WHERE ISBN = ?");
            // Note: In your schema CopiesAvailable/Total aren't columns in Book, they are calculated. 
            // We must calculate them or use the logic from your view.
            // Using logic from your view (checking availability vs total via join or similar):
            
            // To check if safe to archive, we verify NO active loans exist
            $book = $this->getBookByISBN($isbn); // Re-use the getter which handles counts

            if (!$book) {
                return ['message' => "Book not found.", 'type' => 'error'];
            }
            if ($book['Status'] === 'Archived') {
                return ['message' => "Book is already archived.", 'type' => 'error'];
            }
            // Check if all copies are available (CopiesTotal == CopiesAvailable)
            // Or simply check if (CopiesTotal - CopiesAvailable) > 0 means some are borrowed
            $borrowedCount = $book['CopiesTotal'] - $book['CopiesAvailable'];
            
            if ($borrowedCount > 0) {
                return ['message' => "Error: Cannot archive. {$borrowedCount} copies are currently borrowed/reserved.", 'type' => 'error'];
            }

            // Archive
            $sql = "UPDATE Book SET Status = 'Archived' WHERE BookID = ?";
            $this->pdo->prepare($sql)->execute([$book['BookID']]);
            
            // Logic in your view also set CopiesTotal=0, but since that is calculated from Book_Copy,
            // we should technically delete or mark Book_Copy records as 'Archived' too, 
            // but for now I will follow your view's logic intent which likely relied on a column or just Status.
            // Since your schema creates copies in Book_Copy, we should probably update them to 'Lost' or similar, 
            // but your view just updated the Book table. We will stick to updating Book status.

            $this->logAction($userId, $book['BookID'], 'Archived', "Archived '{$book['Title']}'. Reason: {$reason}.");

            return ['message' => "Book '{$book['Title']}' archived successfully.", 'type' => 'success'];

        } catch (PDOException $e) {
            error_log("Archive Error: " . $e->getMessage());
            return ['message' => "Database Error.", 'type' => 'error'];
        }
    }

    // --- HELPER: GET BOOK BY ISBN ---
    public function getBookByISBN($isbn) {
        $sql = "
            SELECT 
                B.BookID, B.Title, B.Author, B.ISBN, B.Price, B.Category, B.Status,
                (SELECT COUNT(BC1.CopyID) FROM Book_Copy BC1 WHERE BC1.BookID = B.BookID) AS CopiesTotal,
                (SELECT COUNT(BC2.CopyID) FROM Book_Copy BC2 WHERE BC2.BookID = B.BookID AND BC2.Status = 'Available') AS CopiesAvailable
            FROM Book B 
            WHERE B.ISBN = ? AND B.Status != 'Archived'
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$isbn]);
        return $stmt->fetch();
    }

    // --- HELPER: FILE UPLOAD ---
    private function handleFileUpload($file, $isbn) {
        $fileName = $file['name'];
        $fileTmpPath = $file['tmp_name'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        $newFileName = $isbn . '-' . time() . '.' . $fileExtension;
        
        // Ensure this path is correct relative to the controller location
        $uploadFileDir = __DIR__ . '/../../public/covers/'; 
        $dest_path = $uploadFileDir . $newFileName;

        if (move_uploaded_file($fileTmpPath, $dest_path)) {
            return ['success' => true, 'path' => 'public/covers/' . $newFileName];
        }
        return ['success' => false, 'message' => "Error uploading file."];
    }

    // --- HELPER: LOGGING ---
    private function logAction($userId, $bookId, $action, $desc) {
        $logSql = "INSERT INTO Management_Log (UserID, BookID, ActionType, Description) 
                   VALUES (?, ?, ?, ?)";
        $this->pdo->prepare($logSql)->execute([$userId, $bookId, $action, $desc]);
    }
}
?>