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
$status_filter = trim($_GET['status'] ?? 'All');
$category_filter = trim($_GET['category'] ?? 'All');

// --- Pagination Setup ---
$books_per_page = 4;
$current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$offset = ($current_page - 1) * $books_per_page;
$total_books = 0;
$total_pages = 0;
$query_message = '';

$categories = [];

try {
    // 1. Fetch unique categories for the filter dropdown
    $categories = $pdo->query("SELECT DISTINCT Category FROM Book WHERE Category IS NOT NULL AND Category != '' ORDER BY Category ASC")->fetchAll(PDO::FETCH_COLUMN);

    // 2. Define the dynamic calculation fields
    $dynamic_fields = "
        B.BookID, B.Title, B.Author, B.ISBN, B.Price, B.CoverImagePath, B.Status, B.Category,
        -- Calculate CopiesTotal dynamically from Book_Copy table:
        (SELECT COUNT(BC1.CopyID) FROM Book_Copy BC1 WHERE BC1.BookID = B.BookID) AS CopiesTotal,
        -- Calculate CopiesAvailable dynamically from Book_Copy table:
        (SELECT COUNT(BC2.CopyID) FROM Book_Copy BC2 WHERE BC2.BookID = B.BookID AND BC2.Status = 'Available') AS CopiesAvailable
    ";

    // 3. Build the Base SQL Query for COUNT and LISTING
    $base_sql = "FROM Book B WHERE B.Status != 'Archived'"; // Use alias B for the Book table

    // Apply Status Filter
    if ($status_filter !== 'All') {
        $safe_status = $pdo->quote($status_filter);
        $base_sql .= " AND Status = {$safe_status}";
    }

    // Apply Category Filter
    if ($category_filter !== 'All') {
        $safe_category = $pdo->quote($category_filter);
        $base_sql .= " AND B.Category = {$safe_category}";
    }

    $count_sql = "SELECT COUNT(B.BookID) " . $base_sql;
    $list_sql = "SELECT {$dynamic_fields} " . $base_sql;

    $is_search = !empty($search_term);

    if ($is_search) {
        // --- Apply Search to both COUNT and LIST queries ---
        $search_clause = " AND (B.Title LIKE :search OR B.Author LIKE :search OR B.ISBN LIKE :search)";
        $safe_search = $pdo->quote('%' . $search_term . '%');

        $count_sql .= str_replace(':search', $safe_search, $search_clause);
        $list_sql .= str_replace(':search', $safe_search, $search_clause);

        $query_message = "Showing results for: '" . htmlspecialchars($search_term) . "'";
    }

    // 4. Fetch Total Count
    $total_books = $pdo->query($count_sql)->fetchColumn();
    $total_pages = ceil($total_books / $books_per_page);

    // 5. Finalize List Query with Pagination and Order
    $list_sql .= " ORDER BY B.Title ASC LIMIT {$books_per_page} OFFSET {$offset}";

    // 6. Execute the final list query
    $stmt = $pdo->query($list_sql);
    $books = $stmt->fetchAll();

    // We must re-run the final list query, injecting the search term again if needed
    if ($is_search) {
        $list_sql = str_replace(':search', $safe_search, $list_sql);
    }

    // Ensure current page is within valid range
    if ($current_page > $total_pages && $total_pages > 0) {
        $params = "?page={$total_pages}";
        if ($is_search)
            $params .= "&search=" . urlencode($search_term);
        if ($status_filter !== 'All')
            $params .= "&status=" . urlencode($status_filter);

        header("Location: book_inventory.php" . $params);
        exit();
    }

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

    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/book_inventory.css">
</head>

<body>
    <div class="container">
        <div id="sidebar-menu" class="sidebar">
            <div class="logo" onclick="toggleSidebar()">
                <span class="hamburger-icon material-icons">menu</span>
                <span class="logo-text">📚 Smart Library</span>
            </div>

            <ul class="nav-list">
                <li class="nav-item"><a href="librarian.php">
                        <span class="nav-icon material-icons">dashboard</span>
                        <span class="text">Dashboard</span>
                    </a></li>
                <li class="nav-item active"><a href="book_inventory.php">
                        <span class="nav-icon material-icons">inventory_2</span>
                        <span class="text">Book Inventory</span>
                    </a></li>
                <li class="nav-item"><a href="add_book.php">
                        <span class="nav-icon material-icons">add_box</span>
                        <span class="text">Add New Book</span>
                    </a></li>
                <li class="nav-item"><a href="update_book.php">
                        <span class="nav-icon material-icons">edit</span>
                        <span class="text">Update Book</span>
                    </a></li>
                <li class="nav-item"><a href="archive_book.php">
                        <span class="nav-icon material-icons">archive</span>
                        <span class="text">Archive Book</span>
                    </a></li>
            </ul>
            <ul class="logout nav-list">
                <li class="nav-item"><a href="login.php">
                        <span class="nav-icon material-icons">logout</span>
                        <span class="text">Logout</span>
                    </a></li>
            </ul>
        </div>

        <div id="main-content-wrapper" class="main-content-wrapper">
            <div class="main-content">

                <div class="inventory-section">
                    <h2>Manage Book Inventory</h2>
                    <p class="subtitle">Search, filter, and manage books. Click the book cards to update book details.
                    </p>

                    <form method="GET" action="book_inventory.php" class="search-filters">
                        <div class="search-input-wrapper">
                            <input type="text" name="search" id="search-input-field" class="search-input"
                                placeholder="Search by Title, Author, ISBN"
                                value="<?php echo htmlspecialchars($search_term); ?>">

                            <button type="button" class="clear-btn" onclick="window.location.href='book_inventory.php';"
                                title="Clear Search"
                                style="display: <?php echo empty($search_term) ? 'none' : 'block'; ?>;"> &times;
                            </button>

                            <button type="submit" name="submit_search" class="search-btn-icon" title="Search">
                                <span class="material-icons">search</span>
                            </button>
                        </div>

                        <select name="status" onchange="this.form.submit()" class="filter-select">
                            <option value="All" <?php echo $status_filter === 'All' ? 'selected' : ''; ?>>All Statuses
                            </option>
                            <option value="Available" <?php echo $status_filter === 'Available' ? 'selected' : ''; ?>>
                                Available</option>
                            <option value="Reserved" <?php echo $status_filter === 'Reserved' ? 'selected' : ''; ?>>
                                Reserved</option>
                            <option value="Borrowed" <?php echo $status_filter === 'Borrowed' ? 'selected' : ''; ?>>
                                Borrowed</option>
                        </select>

                        <select name="category" onchange="this.form.submit()" class="filter-select">
                            <option value="All" <?php echo $category_filter === 'All' ? 'selected' : ''; ?>>All Categories
                            </option>
                            <?php
                            // This loop now uses the dynamically fetched list from the database
                            foreach ($categories as $catName): ?>
                                <option value="<?php echo htmlspecialchars($catName); ?>" <?php echo $category_filter === $catName ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($catName); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <?php
                    if (!empty($query_message)): ?>
                        <p
                            style="font-size: 15px; font-weight: 600; color: #00A693; margin-top: -20px; margin-bottom: 30px;">
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
                            $statusTagClass = strtolower($book['Status']) . '-tag';

                            $coverImagePath = $book['CoverImagePath'] ?? null;
                            $coverStyle = '';
                            $fallbackText = '';

                            if (!empty($coverImagePath)) {
                                if (strpos($coverImagePath, 'http') === 0) {
                                    $imageURL = htmlspecialchars($coverImagePath);
                                } else {
                                    $imageURL = BASE_URL . '/' . htmlspecialchars($coverImagePath);
                                }
                                $coverStyle = "
                    background-image: url('$imageURL'); 
                    background-size: cover; 
                    background-position: center; 
                    background-repeat: no-repeat;
                    background-color: transparent;
                ";
                            } else {
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
                            ?>

                            <!-- WRAP THE CARD IN A LINK to update_book.php -->
                            <a href="update_book.php?isbn=<?php echo htmlspecialchars($book['ISBN']); ?>"
                                class="book-card-link">
                                <div class="book-card">
                                    <div class="book-cover-area" style="<?php echo $coverStyle; ?>">
                                        <?php echo $fallbackText; ?>
                                    </div>

                                    <div class="book-details">
                                        <div>
                                            <div class="book-title"><?php echo htmlspecialchars($book['Title']); ?></div>
                                            <div class="book-author">By: <?php echo htmlspecialchars($book['Author']); ?>
                                            </div>
                                        </div>

                                        <div class="stock-info-block">
                                            <div class="book-status">
                                                Stock: <span
                                                    class="<?php echo $stockClass; ?>"><?php echo $copiesAvailable; ?>
                                                    copies</span> available
                                            </div>
                                            <div class="book-total-stock">
                                                Total Copies: <?php echo $book['CopiesTotal']; ?>
                                            </div>
                                            <small style="display: block; color: #aaa; margin-top: 5px;">(ISBN:
                                                <?php echo $book['ISBN']; ?>)</small>
                                        </div>
                                        <!-- The button is still visible but the whole card is clickable -->
                                        <div class="action-button <?php echo $statusTagClass; ?>">
                                            <?php echo htmlspecialchars($book['Status']); ?>
                                        </div>
                                    </div>
                                </div>
                            </a>

                        <?php endforeach; ?>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <div class="pagination-controls">
                            <ul class="pagination">
                                <?php
                                $searchParam = $search_term ? "&search=" . urlencode($search_term) : '';

                                // Previous Page Link
                                $prevPage = max(1, $current_page - 1);
                                $prevDisabled = ($current_page == 1) ? 'disabled' : '';
                                ?>
                                <li class="page-item <?php echo $prevDisabled; ?>">
                                    <a href="?page=<?php echo $prevPage . $searchParam; ?>" class="page-link">
                                        Previous
                                    </a>
                                </li>

                                <?php
                                // Loop for page numbers
                                for ($i = 1; $i <= $total_pages; $i++):
                                    $activeClass = ($i == $current_page) ? 'active' : '';
                                    ?>
                                    <li class="page-item <?php echo $activeClass; ?>">
                                        <a href="?page=<?php echo $i . $searchParam; ?>" class="page-link">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php
                                // Next Page Link
                                $nextPage = min($total_pages, $current_page + 1);
                                $nextDisabled = ($current_page == $total_pages) ? 'disabled' : '';
                                ?>
                                <li class="page-item <?php echo $nextDisabled; ?>">
                                    <a href="?page=<?php echo $nextPage . $searchParam; ?>" class="page-link">
                                        Next
                                    </a>
                                </li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    </div>
    </div>

    <script src="<?php echo BASE_URL; ?>/public/js/main.js"></script>
</body>

</html>