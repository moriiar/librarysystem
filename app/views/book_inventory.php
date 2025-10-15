<?php
session_start();

// --- Authentication and Setup ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Librarian') {
    header("Location: " . BASE_URL . "/views/login.php");
    exit();
}

require_once __DIR__ . '/../models/database.php';
require_once __DIR__ . '/../../config.php';

$librarian_name = $_SESSION['name'] ?? 'Librarian';
$books = [];
$search_term = trim($_GET['search'] ?? '');
$query_message = '';

try {
    // Ensure CoverImagePath is selected so it's available for display
    $sql = "SELECT BookID, Title, Author, ISBN, CopiesTotal, CopiesAvailable, Status, CoverImagePath 
            FROM Book 
            WHERE Status != 'Archived'";

    $is_search = !empty($search_term);

    if ($is_search) {
        // --- INSECURE BUT WORKING PATH ---
        // Use pdo->quote() to manually sanitize the search term and inject it directly into the SQL string.
        $safe_search = $pdo->quote('%' . $search_term . '%');
        $sql .= " AND (Title LIKE $safe_search OR Author LIKE $safe_search OR ISBN LIKE $safe_search)";
        $query_message = "Showing results for: '" . htmlspecialchars($search_term) . "'";
    }

    $sql .= " ORDER BY Title ASC";

    // --- Execute the Query using ONLY pdo->query() ---
    $stmt = $pdo->query($sql);
    $books = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Inventory Query Error: " . $e->getMessage());
    $query_message = "Database Error! Could not load inventory.";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Book Inventory</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">

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

        .sidebar {
            width: 410px;
            padding: 30px 0;
            background-color: #fff;
            border-right: 1px solid #eee;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.05);
        }

        .logo {
            font-size: 16px;
            font-weight: bold;
            color: #000;
            padding: 0 30px 40px;
        }

        .nav-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .nav-item a {
            font-size: 15px;
            display: block;
            padding: 15px 30px;
            text-decoration: none;
            color: #6C6C6C;
            transition: background-color 0.2s;
        }

        .nav-item a:hover {
            background-color: #f0f0f0;
        }

        .nav-item.active a {
            color: #000;
            font-weight: bold;
        }

        .logout {
            margin-top: 50px;
            cursor: pointer;
        }

        .logout a {
            display: block;
            padding: 15px 30px;
            color: #6C6C6C;
            text-decoration: none;
            transition: background-color 0.2s;
        }

        .logout a:hover {
            background-color: #f0f0f0;
        }

        /* Main Content Area */
        .main-content {
            flex-grow: 1;
            padding: 30px 32px;
        }

        .inventory-section h2 {
            font-size: 25px;
            font-weight: bold;
            margin-bottom: 20px;
            margin-top: 20px;
        }

        .inventory-section p.subtitle {
            font-size: 15px;
            color: #666;
            margin-bottom: 30px;
        }

        .search-filters {
            display: flex;
            gap: 15px;
            margin-bottom: 40px;
            width: 100%;
            max-width: 1800px;
            /* Constraints for form size */
        }

        .search-input {
            max-width: 65%;
            flex-grow: 1;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            background-color: #fff;
            color: #666;
        }

        /* Book Cards Container */
        .book-list {
            display: flex;
            gap: 23px;
            flex-wrap: wrap;
            /* FIX: Ensure container stretches to accommodate cards */
            width: 100%;
        }

        /* FIX: Increased width/height to fit text better */
        .book-card {
            width: 400px;
            /* Increased from 320px */
            height: 250px;
            /* Increased from 210px */
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            padding: 15px;
            /* Increased padding */
            box-sizing: border-box;
        }

        .book-cover-area {
            background-color: #F0F8F8;
            width: 120px;
            height: 220px;
            border-radius: 8px;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .book-details {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            flex-grow: 1;
            padding: 5px 0;
            padding-left: 5px;
        }

        /* Book Title and Author */
        .book-title {
            font-size: 17px;
            /* Slightly larger font */
            font-weight: 600;
            line-height: 1.3;
            margin: 5px 0 3px 0;
            /* FIX: Ensure long titles wrap */
            word-wrap: break-word;
        }

        .book-author {
            font-size: 14px;
            color: #666;
            margin: 0;
        }

        /* Book Status and Stock Info */
        .book-status {
            font-size: 14px;
            color: #444;
            font-weight: 500;
            margin-bottom: 10px;
            /* Reduced margin */
            margin-top: 10px;
        }

        .book-status span {
            font-weight: 700;
        }

        .book-status .available-stock {
            color: #00A693;
        }

        /* Green/Teal for available stock */
        .book-status .low-stock {
            color: #E5A000;
        }

        /* Amber/Orange for low/zero stock */


        /* Action Buttons/Status Tags */
        .action-button {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px 15px;
            border-radius: 8px;
            font-weight: 700;
            cursor: default;
            text-decoration: none;
            width: 100px;
            min-height: 40px;
            box-sizing: border-box;
            align-self: flex-end;
            /* FIX: Align to the start of the detail column */
            font-size: 15px;
            border: none;
        }

        .search-btn {
            padding: 12px 20px;
            border-radius: 8px;
            border: none;
            background-color: #00A693;
            color: white;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .search-btn:hover {
            background-color: #00897B;
        }

        /* --- Specific Status Tag Colors --- */

        /* 1. AVAILABLE */
        .available-tag {
            background-color: #00A693;
            color: #fff;
        }

        /* 2. RESERVED */
        .reserved-tag {
            background-color: #E0E0E0;
            color: #444;
        }

        /* 3. BORROWED */
        .borrowed-tag {
            background-color: #F8D7DA;
            /* Light red/pinkish */
            color: #721C24;
            /* Dark red text */
        }

        /* 4. ARCHIVED */
        .archived-tag {
            background-color: #E6E6E6;
            /* Slightly darker gray than reserved */
            color: #777;
            font-weight: 500;
            /* Less emphasis */
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="sidebar">
            <div class="logo">
                📚 Smart Library
            </div>
            <ul class="nav-list">
                <li class="nav-item"><a href="librarian.php">Dashboard</a></li>
                <li class="nav-item active"><a href="book_inventory.php">Book Inventory</a></li>
                <li class="nav-item"><a href="add_book.php">Add New Book</a></li>
                <li class="nav-item"><a href="update_book.php">Update Book</a></li>
                <li class="nav-item"><a href="archive_book.php">Archive Book</a></li>
            </ul>
            <div class="logout"><a href="login.php">Logout</a></div>
        </div>

        <div class="main-content">

            <div class="inventory-section">
                <h2>Manage Book Inventory</h2>
                <p class="subtitle">Search, filter, and manage books.
                </p>

                <form method="GET" action="book_inventory.php" class="search-filters">
                    <input type="text" name="search" class="search-input" placeholder="Search by Title, Author, ISBN"
                        value="<?php echo htmlspecialchars($search_term); ?>">
                    <button type="submit" name="submit_search" class="search-btn">Search</button>
                </form>

                <?php
                if (!empty($query_message)): ?>
                    <p style="font-size: 15px; font-weight: 600; color: #00A693; margin-top: -20px; margin-bottom: 30px;">
                        <?php echo htmlspecialchars($query_message); ?>
                    </p>
                <?php endif; ?>

                <div class="book-list">
                    <?php if (empty($books)): ?>
                        <p style="width: 100%;">No books found matching your criteria.</p>
                    <?php endif; ?>

                    <?php foreach ($books as $book):
                        $copiesAvailable = (int) $book['CopiesAvailable'];
                        $stockClass = ($copiesAvailable > 0) ? 'available-stock' : 'low-stock';
                        // Map the database status to a CSS class
                        $statusTagClass = strtolower($book['Status']) . '-tag';
                    ?>

                        <div class="book-card">

                            <?php
                            // --- PHP LOGIC BLOCK (REVISED FOR OPEN LIBRARY INTEGRATION) ---
                            $coverImagePath = $book['CoverImagePath'] ?? null;
                            $coverStyle = '';
                            $fallbackText = '';

                            if (!empty($coverImagePath)) {
                                // 1. Determine the correct URL for the image
                                if (strpos($coverImagePath, 'http') === 0) {
                                    // If it starts with 'http', it's the full Open Library URL
                                    $imageURL = htmlspecialchars($coverImagePath);
                                } else {
                                    // Otherwise, it's a local path, prepend BASE_URL 
                                    $imageURL = BASE_URL . '/' . htmlspecialchars($coverImagePath);
                                }

                                // 2. Apply CSS background using the determined URL
                                $coverStyle = "
                                    background-image: url('$imageURL'); 
                                    background-size: cover; 
                                    background-position: center; 
                                    background-repeat: no-repeat;
                                    background-color: transparent;
                                ";
                            } else {
                                // Fallback style for "No Cover"
                                $coverStyle = "
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                font-size: 12px;
                                color: #999;
                                text-align: center;
                                ";
                                $fallbackText = 'No Cover';
                            }
                            // --- END REVISED PHP LOGIC BLOCK ---
                            ?>

                            <div class="book-cover-area" style="<?php echo $coverStyle; ?>">
                                <?php echo $fallbackText; ?>
                            </div>

                            <div class="book-details">
                                <div>
                                    <div class="book-title"><?php echo htmlspecialchars($book['Title']); ?></div>
                                    <div class="book-author">By: <?php echo htmlspecialchars($book['Author']); ?></div>
                                </div>

                                <div class="book-status">
                                    Stock: <span class="<?php echo $stockClass; ?>"><?php echo $copiesAvailable; ?>
                                        copies</span> available
                                    <small style="display: block; color: #aaa; margin-top: 5px;">(ISBN:
                                        <?php echo $book['ISBN']; ?>)</small>
                                </div>

                                <div class="action-button <?php echo $statusTagClass; ?>">
                                    <?php echo htmlspecialchars($book['Status']); ?>
                                </div>
                            </div>
                        </div>

                    <?php endforeach; ?>

                </div>
            </div>
        </div>
    </div>
</body>

</html>