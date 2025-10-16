<?php
session_start();

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Librarian') {
    header("Location: " . BASE_URL . "/views/login.php");
    exit();
}

require_once __DIR__ . '/../models/database.php';
require_once __DIR__ . '/../../config.php';

$status_message = '';
$error_type = ''; // success or error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $isbn = trim($_POST['isbn'] ?? '');
    $price = filter_var($_POST['price'] ?? 0.00, FILTER_VALIDATE_FLOAT);
    $quantity = filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT);

    // Basic validation
    if (empty($title) || empty($isbn) || $price === false || $quantity === false || $quantity < 1) {
        $status_message = "Please check your input values for Title, ISBN, Price, and Quantity.";
        $error_type = 'error';
    }
    // --- File Upload Handling ---
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['cover_image']['tmp_name'];
        $fileName = $_FILES['cover_image']['name'];
        $fileSize = $_FILES['cover_image']['size'];
        $fileType = $_FILES['cover_image']['type'];
        
        // Sanitize file name to prevent directory traversal attacks
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        
        // Create a unique, safe filename (e.g., ISBN-timestamp.ext)
        $newFileName = $isbn . '-' . time() . '.' . $fileExtension;
        
        // Define the destination path
        $uploadFileDir = __DIR__ . '/../../public/covers/'; // Adjust path to public/covers
        $dest_path = $uploadFileDir . $newFileName;

        if (move_uploaded_file($fileTmpPath, $dest_path)) {
            // Save the relative path to the database
            $coverImagePath = 'public/covers/' . $newFileName; 
        } else {
            $status_message = "Error uploading file. Check folder permissions.";
            $error_type = 'error';
            // Stop processing if the upload failed
        }
    } else {
        // --- Database Insertion Update ---
        if ($error_type !== 'error') { // Only proceed if no upload error occurred
            try {
                $sql = "INSERT INTO Book (Title, Author, ISBN, Price, CopiesTotal, CopiesAvailable, Status, CoverImagePath) VALUES (:title, :author, :isbn, :price, :total, :available, 'Available', :cover_path)";
            
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':title' => $title,
                    ':author' => $author,
                    ':isbn' => $isbn,
                    ':price' => $price,
                    ':total' => $quantity,
                    ':available' => $quantity, // CopiesAvailable starts equal to CopiesTotal
                    ':cover_path' => $coverImagePath, // NEW: Bind the path here
                ]);

                $status_message = "Book '{$title}' added successfully! Total copies: {$quantity}.";
                $error_type = 'success';
                $_POST = array();

            } catch (PDOException $e) {
                // Check for duplicate ISBN error
                if ($e->getCode() === '23000') {
                    $status_message = "Error: The ISBN '{$isbn}' already exists in the catalog.";
                } else {
                    // For debugging, use the detailed message, but log it and show a generic message in production
                    error_log("Add Book Error: " . $e->getMessage());
                    $status_message = "Database Error: Could not add the book.";
                }
                $error_type = 'error';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add a New Book</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">

    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <style>
        /* Global Styles */
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #F7FCFC;
            /* Requested background color */
            color: #333;
        }

        /* Layout Container */
        .container {
            display: flex;
            min-height: 100vh;
        }

        /* --- Collapsible sidebar --- */
        .sidebar {
            width: 70px; /* Initial Collapsed Width */
            padding: 30px 0;
            background-color: #fff;
            border-right: 1px solid #eee;
            box-shadow: 3px 0 9px rgba(0, 0, 0, 0.05);
            flex-shrink: 0;
            overflow: hidden; /* Hides content that exceeds width */
            transition: width 0.5s ease; /* Smooth expansion animation */
        }

        .sidebar:hover {
            width: 250px; /* Expanded Width */
        }

        .logo {
            font-size: 19px;
            font-weight: bold;
            color: #000;
            padding: 0 30px 40px;
            white-space: nowrap; /* Prevents logo text wrap/break */
        }

        .logo-text {
            /* Hide text part of logo in collapsed view */
            opacity: 0; 
            transition: opacity 0.3s ease 0.1s;
        }
        
        .sidebar:hover .logo-text {
            opacity: 1;
        }

        .nav-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .nav-item a {
            display: flex; /* Use Flex for icon/text alignment */
            align-items: center;
            font-size: 15px;
            padding: 15px 30px;
            text-decoration: none;
            color: #6C6C6C;
            transition: background-color 0.2s;
            white-space: nowrap;
        }

        .nav-item a:hover {
            background-color: #f0f0f0;
        }

        .nav-item.active a {
            color: #000;
            font-weight: bold;
        }

        .nav-icon {
            font-family: 'Material Icons';
            margin-right: 20px; /* Space between icon and text when expanded */
            font-size: 20px;
            width: 20px; /* Fixed width to keep icons aligned */
        }

        .logout {
            margin-top: 220px;
            cursor: pointer;
        }

        .logout a {
            display: flex;
            align-items: center;
            font-size: 15px;
            padding: 15px 30px;
            color: #e94343ff;
            text-decoration: none;
            transition: background-color 0.2s;
            white-space: nowrap;
        }

        .logout a:hover {
            background-color: #f0f0f0;
        }

        /* Main Content Area */
        .main-content {
            flex-grow: 1;
            padding: 30px 32px;
        }

        /* Dashboard Section */
        .dashboard-section {
            width: 100%;
            /* Ensure the section uses full width available to main-content */
            display: flex;
            flex-direction: column;
            align-items: center;
            /* Center the form title and card */
        }

        .dashboard-section h2 {
            font-size: 25px;
            font-weight: bold;
            margin-bottom: 20px;
            margin-top: 20px;
            width: 100%;
            /* Ensure H2 spans width for consistency, despite center alignment */
            text-align: left;
            /* Keep the section title left-aligned */
        }


        /* --- New Book Form Card Styles --- */

        .form-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            max-width: 650px;
            width: 100%;
        }

        /* Form Group and Input Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 16px;
            color: #333;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ccc;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 17px;
            outline: none;
            transition: border-color 0.3s;
        }

        .form-input:focus {
            border-color: #00bcd4;
        }

        /* Button Styling */
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .action-button {
            flex: 1;
            background-color: #00a89d;
            color: white;
            padding: 14px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            font-weight: 600;
            transition: background-color 0.3s;
        }

        .action-button:hover {
            background-color: #00897b;
        }

        .cancel-button {
            background-color: #e0e0e0;
            color: #333;
        }

        .cancel-button:hover {
            background-color: #ccc;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="sidebar">
            <div class="logo">
                <span class="nav-icon">📚</span>
                <span class="logo-text">Smart Library</span>
            </div>
            <ul class="nav-list">
                <li class="nav-item"><a href="librarian.php">
                    <span class="nav-icon material-icons">dashboard</span>
                    Dashboard
                </a></li>
                <li class="nav-item"><a href="book_inventory.php">
                    <span class="nav-icon material-icons">inventory_2</span>
                    Book Inventory
                </a></li>
                <li class="nav-item active"><a href="add_book.php">
                    <span class="nav-icon material-icons">add_box</span>
                    Add New Book
                </a></li>
                <li class="nav-item"><a href="update_book.php">
                    <span class="nav-icon material-icons">edit</span>
                    Update Book
                </a></li>
                <li class="nav-item"><a href="archive_book.php">
                    <span class="nav-icon material-icons">archive</span>
                    Archive Book
                </a></li>
            </ul>
            <ul class="logout nav-list">
                <li class="nav-item"><a href="login.php">
                    <span class="nav-icon material-icons">logout</span>
                    Logout
                </a></li>
            </ul>
        </div>

        <div class="main-content">

            <div class="dashboard-section">
                <h2>Add a New Book</h2>

                <?php if (!empty($status_message)): ?>
                    <div
                        style="padding: 15px; margin-bottom: 20px; border-radius: 5px; width: 100%; max-width: 650px; background-color: <?php echo ($error_type === 'success' ? '#e8f5e9' : '#ffcdd2'); ?>; color: <?php echo ($error_type === 'success' ? '#388e3c' : '#d32f2f'); ?>;">
                        <?php echo htmlspecialchars($status_message); ?>
                    </div>
                <?php endif; ?>

                <div class="form-card">
                    <form action="add_book.php" method="POST" enctype="multipart/form-data">

                        <div class="form-group">
                            <label for="isbn" class="form-label">ISBN</label>
                            <input type="text" id="isbn" name="isbn" class="form-input"
                                placeholder="e.g., 978-0123456789" required
                                value="<?php echo htmlspecialchars($_POST['isbn'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" id="title" name="title" class="form-input" 
                            required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="author" class="form-label">Author</label>
                            <input type="text" id="author" name="author" class="form-input"
                                required value="<?php echo htmlspecialchars($_POST['author'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="price" class="form-label">Price</label>
                            <input type="number" id="price" name="price" class="form-input"
                                min="0.01" step="0.01" required
                                value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="quantity" class="form-label">Quantity</label>
                            <input type="number" id="quantity" name="quantity" class="form-input"
                                min="1" required
                                value="<?php echo htmlspecialchars($_POST['quantity'] ?? ''); ?>">
                        </div>

                        <div class="button-group">
                            <button type="submit" class="action-button">Add Book</button>
                            <button type="button" class="action-button cancel-button"
                                onclick="window.location.href='librarian.php'">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>

</html>