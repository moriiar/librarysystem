<?php
// CRITICAL: Start Output Buffering
ob_start();
session_start();

// --- Authentication Check ---
// Allow both 'Student' and 'Teacher' roles
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Student', 'Teacher'])) {
    header("Location: " . BASE_URL . "/views/login.php");
    ob_end_flush();
    exit();
}

require_once __DIR__ . '/../models/database.php';
require_once __DIR__ . '/../../config.php';

$user_name = $_SESSION['name'] ?? 'User';
$userID = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// --- Role-Based Limit ---
// Students: 3 books
// Teachers: 9999 books (effectively unlimited)
$limitMax = ($user_role === 'Student') ? 3 : 9999;

// --- Inventory and Search Setup ---
$books = [];
$search_term = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? 'All');
$category_filter = trim($_GET['category'] ?? 'All');

// --- Pagination Setup ---
$books_per_page = 9;
$current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$offset = ($current_page - 1) * $books_per_page;
$total_books = 0;
$total_pages = 0;
$query_message = '';
$categories = [];

// --- Fetch User Loan & Reservation Status ---
$stmt_borrowed = $pdo->prepare("SELECT COUNT(BorrowID) FROM borrowing_record WHERE UserID = ? AND Status = 'Borrowed'");
$stmt_borrowed->execute([$userID]);
$borrowedCount = $stmt_borrowed->fetchColumn();

$stmt_reserved = $pdo->prepare("SELECT COUNT(ReservationID) FROM reservation WHERE UserID = ? AND Status = 'Active'");
$stmt_reserved->execute([$userID]);
$reservedCount = $stmt_reserved->fetchColumn();

$totalActive = $borrowedCount + $reservedCount;
$maxedOut = $totalActive >= $limitMax;

try {
    // 1. Fetch unique categories
    $categories = $pdo->query("SELECT DISTINCT Category FROM book WHERE Category IS NOT NULL AND Category != '' ORDER BY Category ASC")->fetchAll(PDO::FETCH_COLUMN);

    // 2. Define the dynamic calculation fields
    $dynamic_fields = "
        B.BookID, B.Title, B.Author, B.ISBN, B.Price, B.CoverImagePath, B.Status, B.Category,
        (SELECT COUNT(BC1.CopyID) FROM book_copy BC1 WHERE BC1.BookID = B.BookID) AS CopiesTotal,
        (SELECT COUNT(BC2.CopyID) FROM book_copy BC2 WHERE BC2.BookID = B.BookID AND BC2.Status = 'Available') AS CopiesAvailable,
        (SELECT COUNT(R.ReservationID) FROM reservation R WHERE R.BookID = B.BookID AND R.Status = 'Active') AS TotalActiveReservations,
        (SELECT COUNT(R2.ReservationID) FROM reservation R2 WHERE R2.BookID = B.BookID AND R2.UserID = {$userID} AND R2.Status = 'Active') AS UserHasActiveReservation
    ";

    // 3. Build the Base SQL Query
    $base_sql = "FROM book B WHERE B.Status != 'Archived'";

    if ($status_filter !== 'All') {
        $safe_status = $pdo->quote($status_filter);
        $base_sql .= " AND B.Status = {$safe_status}";
    }

    if ($category_filter !== 'All') {
        $safe_category = $pdo->quote($category_filter);
        $base_sql .= " AND B.Category = {$safe_category}";
    }

    $count_sql = "SELECT COUNT(B.BookID) " . $base_sql;
    $list_sql = "SELECT {$dynamic_fields} " . $base_sql;

    $is_search = !empty($search_term);

    if ($is_search) {
        $search_clause = " AND (B.Title LIKE :search OR B.Author LIKE :search OR B.ISBN LIKE :search)";
        $safe_search = $pdo->quote('%' . $search_term . '%');
        $count_sql .= str_replace(':search', $safe_search, $search_clause);
        $list_sql .= str_replace(':search', $safe_search, $search_clause);
        $query_message = "Showing results for: '" . htmlspecialchars($search_term) . "'";
    }

    $total_books = $pdo->query($count_sql)->fetchColumn();
    $total_pages = ceil($total_books / $books_per_page);
    $list_sql .= " ORDER BY B.Title ASC LIMIT {$books_per_page} OFFSET {$offset}";

    $stmt = $pdo->query($list_sql);
    $book_inventory = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Inventory Query Error: " . $e->getMessage());
    $query_message = "Database Error: Could not load the book catalog.";
}

// --- POST Logic (Reserve Action) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_id'])) {
    $bookID = filter_var($_POST['book_id'], FILTER_VALIDATE_INT);
    $bookTitle = $_POST['book_title'] ?? 'Book';

    try {
        $pdo->beginTransaction();

        // 1. Check User Limit
        if ($maxedOut) {
            $msg = ($user_role === 'Teacher')
                ? "You have reached the system maximum."
                : "Denied: You have reached your limit of 3 books.";
            throw new Exception($msg);
        }

        // 2. Check Duplicate Reservation
        $stmt_exists = $pdo->prepare("SELECT ReservationID FROM reservation WHERE UserID = ? AND BookID = ? AND Status = 'Active'");
        $stmt_exists->execute([$userID, $bookID]);
        if ($stmt_exists->fetch()) {
            throw new Exception("Error: You already have an active reservation for '{$bookTitle}'.");
        }

        // 3. Check Availability logic
        $stmt_avail = $pdo->prepare("SELECT COUNT(CopyID) FROM book_copy WHERE BookID = ? AND Status = 'Available'");
        $stmt_avail->execute([$bookID]);
        $physicallyAvailable = $stmt_avail->fetchColumn();

        $stmt_active_res = $pdo->prepare("SELECT COUNT(ReservationID) FROM reservation WHERE BookID = ? AND Status = 'Active'");
        $stmt_active_res->execute([$bookID]);
        $existingReservations = $stmt_active_res->fetchColumn();

        if ($physicallyAvailable > $existingReservations) {
            $expiryDate = date('Y-m-d H:i:s', strtotime('+3 days'));
            $stmt_insert = $pdo->prepare("INSERT INTO reservation (UserID, BookID, ExpiryDate, Status) VALUES (?, ?, ?, 'Active')");
            $stmt_insert->execute([$userID, $bookID, $expiryDate]);

            $status_message = "Success! Reservation placed. Please wait for staff approval.";
            $error_type = 'success';
            $pdo->commit();
        } else {
            throw new Exception("Error: No copies available. All available copies are currently reserved by others.");
        }

        $redirectPage = filter_input(INPUT_POST, 'page', FILTER_VALIDATE_INT) ?: 1;
        $redirectSearch = trim($_POST['search'] ?? '');
        $url = "student_teacher_borrow.php?msg=" . urlencode($status_message) . "&type={$error_type}&page={$redirectPage}";
        if (!empty($redirectSearch))
            $url .= "&search=" . urlencode($redirectSearch);

        header("Location: " . $url);
        ob_end_flush();
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $status_message = $e->getMessage();
        $error_type = 'error';

        $redirectPage = filter_input(INPUT_POST, 'page', FILTER_VALIDATE_INT) ?: 1;
        header("Location: student_teacher_borrow.php?msg=" . urlencode($status_message) . "&type={$error_type}&page={$redirectPage}");
        ob_end_flush();
        exit();
    }
}

if (isset($_GET['msg'])) {
    $status_message = htmlspecialchars($_GET['msg']);
    $error_type = htmlspecialchars($_GET['type'] ?? 'success');
}
?>
<?php ob_end_flush(); ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse and Reserve Books</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">

    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/student_teacher_borrow.css">
</head>

<body>
    <div class="container">
        <div id="sidebar-menu" class="sidebar">
            <div class="logo" onclick="toggleSidebar()">
                <span class="hamburger-icon material-icons">menu</span>
                <span class="logo-text">📚 Smart Library</span>
            </div>

            <ul class="nav-list">
                <li class="nav-item"><a href="student_teacher.php">
                        <span class="nav-icon material-icons">dashboard</span>
                        <span class="text">Dashboard</span>
                    </a></li>
                <li class="nav-item active"><a href="student_teacher_borrow.php">
                        <span class="nav-icon material-icons">local_library</span>
                        <span class="text">Books</span>
                    </a></li>
                <li class="nav-item"><a href="student_teacher_reservation.php">
                        <span class="nav-icon material-icons">bookmark_add</span>
                        <span class="text">Reservations</span>
                    </a></li>
                <li class="nav-item"><a href="student_teacher_borrowed_books.php">
                        <span class="nav-icon material-icons">menu_book</span>
                        <span class="text">Borrowed Books</span>
                    </a>
                </li>
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
                    <h2>Browse and Reserve Books</h2>
                    <p class="subtitle">
                        Search the catalog and reserve books.
                    </p>

                    <form method="GET" action="student_teacher_borrow.php" class="search-filters">
                        <div class="search-input-wrapper">
                            <input type="text" name="search" id="search-input-field" class="search-input"
                                placeholder="Search by Title or Author..."
                                value="<?php echo htmlspecialchars($search_term); ?>">

                            <button type="button" class="clear-btn" onclick="window.location.href='book_inventory.php';"
                                title="Clear Search"
                                style="display: <?php echo empty($search_term) ? 'none' : 'block'; ?>;"> &times;
                            </button>

                            <button type="submit" name="submit_search" class="search-btn-icon" title="Search">
                                <span class="material-icons">search</span>
                            </button>
                        </div>

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

                    <div class="book-list">
                        <?php if (empty($book_inventory)): ?>
                            <p style="width: 100%; color: #999; text-align: center;">No books found in the catalog matching
                                your search.</p>
                        <?php else: ?>
                            <?php
                            foreach ($book_inventory as $book):
                                $availCopies = (int) $book['CopiesAvailable'];
                                $reservationsForBook = (int) $book['TotalActiveReservations'];
                                $effectiveAvailability = $availCopies - $reservationsForBook;
                                $isAvailable = $effectiveAvailability > 0;

                                $stockText = $isAvailable ? "{$effectiveAvailability} available" : "Out of Stock";
                                $stockClass = $isAvailable ? 'stock-available' : 'stock-low';

                                $coverPath = $book['CoverImagePath'] ?? '';
                                $bgStyle = '';
                                if (!empty($coverPath)) {
                                    $url = (strpos($coverPath, 'http') === 0) ? $coverPath : BASE_URL . '/' . $coverPath;
                                    $bgStyle = "background-image: url('" . htmlspecialchars($url) . "');";
                                }

                                $userHasActive = $book['UserHasActiveReservation'] > 0;

                                if ($userHasActive) {
                                    $buttonText = "Already Reserved";
                                    $buttonClass = "reserved-tag";
                                    $isDisabled = true;
                                } elseif ($maxedOut) {
                                    // Teachers usually won't hit this, but logic handles it
                                    $buttonText = "Limit Reached";
                                    $buttonClass = "disabled-btn";
                                    $isDisabled = true;
                                } elseif (!$isAvailable) {
                                    $buttonText = "Reserved by Others";
                                    $buttonClass = "disabled-btn";
                                    $isDisabled = true;
                                } else {
                                    $buttonText = "Reserve Book";
                                    $buttonClass = "reserve-btn";
                                    $isDisabled = false;
                                }
                                ?>
                                <div class="book-card">
                                    <div class="book-cover-area" style="<?php echo $bgStyle; ?>">
                                        <?php if (empty($coverPath))
                                            echo "No Cover"; ?>
                                    </div>

                                    <div class="book-details">
                                        <div>
                                            <div class="book-title" title="<?php echo htmlspecialchars($book['Title']); ?>">
                                                <?php echo htmlspecialchars($book['Title']); ?>
                                            </div>
                                            <div class="book-author">By: <?php echo htmlspecialchars($book['Author']); ?></div>
                                        </div>

                                        <div class="book-status-info">
                                            Stock: <span class="<?php echo $stockClass; ?>"><?php echo $stockText; ?></span>
                                        </div>

                                        <form method="POST" action="student_teacher_borrow.php"
                                            onsubmit="return openConfirmModal(this, '<?php echo $book['BookID']; ?>', '<?php echo htmlspecialchars(addslashes($book['Title'])); ?>')">
                                            <input type="hidden" name="book_id" value="<?php echo $book['BookID']; ?>">
                                            <input type="hidden" name="book_title"
                                                value="<?php echo htmlspecialchars($book['Title']); ?>">

                                            <button class="action-button <?php echo $buttonClass; ?>" type="submit" <?php echo $isDisabled ? 'disabled' : ''; ?>>
                                                <?php echo $buttonText; ?>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
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

            <?php if (!empty($status_message)): ?>
                <div id="statusNotification"
                    class="status-box <?php echo $error_type === 'success' ? 'status-success' : 'status-error'; ?>">
                    <?php echo htmlspecialchars($status_message); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="confirmActionModal" class="modal">
        <div class="modal-content">
            <h3 id="modalTitle">Confirm Reservation</h3>
            <p id="modalMessage"></p>
            <div class="modal-buttons">
                <button id="modalConfirmBtn" class="confirm-btn">Confirm</button>
                <button type="button" class="cancel-btn" onclick="closeModal('confirmActionModal')">Cancel</button>
            </div>
        </div>
    </div>

    <form id="modalSubmissionForm" method="POST" action="student_teacher_borrow.php">
        <input type="hidden" name="book_id" id="modalBookId">
        <input type="hidden" name="book_title" id="modalBookTitle">
        <input type="hidden" name="page" value="<?php echo $current_page; ?>">
        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
    </form>

    <script src="<?php echo BASE_URL; ?>/public/js/main.js"></script>
    <script src="<?php echo BASE_URL; ?>/public/js/student_teacher_borrow.js"></script>
</body>

</html>