<?php
// CRITICAL: Start Output Buffering
ob_start();
session_start();

// --- Authentication and Setup ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Student') {
    header("Location: " . BASE_URL . "/views/login.php");
    ob_end_flush();
    exit();
}

require_once __DIR__ . '/../models/database.php';
require_once __DIR__ . '/../../config.php';

$student_name = $_SESSION['name'] ?? 'Student';
$userID = $_SESSION['user_id'];
$limitMax = 3; // Fixed limit for Students (Borrowed + Reserved)

// --- Inventory and Search Setup ---
$books = [];
$search_term = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? 'All');
$category_filter = trim($_GET['category'] ?? 'All');

// --- Pagination Setup ---
$books_per_page = 8;
$current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$offset = ($current_page - 1) * $books_per_page;
$total_books = 0;
$total_pages = 0;
$query_message = '';
$categories = [];

// --- Fetch User Loan & Reservation Status ---
// 1. Count currently borrowed books
$stmt_borrowed = $pdo->prepare("SELECT COUNT(BorrowID) FROM Borrow WHERE UserID = ? AND Status = 'Borrowed'");
$stmt_borrowed->execute([$userID]);
$borrowedCount = $stmt_borrowed->fetchColumn();

// 2. Count active reservations
$stmt_reserved = $pdo->prepare("SELECT COUNT(ReservationID) FROM Reservation WHERE UserID = ? AND Status = 'Active'");
$stmt_reserved->execute([$userID]);
$reservedCount = $stmt_reserved->fetchColumn();

$totalActive = $borrowedCount + $reservedCount;
$maxedOut = $totalActive >= $limitMax;

try {
    // 1. Fetch unique categories
    $categories = $pdo->query("SELECT DISTINCT Category FROM Book WHERE Category IS NOT NULL AND Category != '' ORDER BY Category ASC")->fetchAll(PDO::FETCH_COLUMN);

    // 2. Define the dynamic calculation fields
    $dynamic_fields = "
        B.BookID, B.Title, B.Author, B.ISBN, B.Price, B.CoverImagePath, B.Status, B.Category,
        (SELECT COUNT(BC1.CopyID) FROM Book_Copy BC1 WHERE BC1.BookID = B.BookID) AS CopiesTotal,
        (SELECT COUNT(BC2.CopyID) FROM Book_Copy BC2 WHERE BC2.BookID = B.BookID AND BC2.Status = 'Available') AS CopiesAvailable,
        (SELECT COUNT(R.ReservationID) FROM Reservation R WHERE R.BookID = B.BookID AND R.UserID = {$userID} AND R.Status = 'Active') AS HasActiveReservation
    ";

    // 3. Build the Base SQL Query
    $base_sql = "FROM Book B WHERE B.Status != 'Archived'";

    // Apply Filters
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

    // 4. Fetch Total Count & Calculate Pages
    $total_books = $pdo->query($count_sql)->fetchColumn();
    $total_pages = ceil($total_books / $books_per_page);

    // 5. Finalize List Query with Pagination
    $list_sql .= " ORDER BY B.Title ASC LIMIT {$books_per_page} OFFSET {$offset}";

    $stmt = $pdo->query($list_sql);
    $book_inventory = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Student Inventory Query Error: " . $e->getMessage());
    $query_message = "Database Error: Could not load the book catalog.";
}


// --- POST Logic (Reserve Action) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_id'])) {
    $bookID = filter_var($_POST['book_id'], FILTER_VALIDATE_INT);
    $bookTitle = $_POST['book_title'] ?? 'Book';

    try {
        $pdo->beginTransaction();

        // 1. Check Limit again
        $stmt_chk_b = $pdo->prepare("SELECT COUNT(*) FROM Borrow WHERE UserID = ? AND Status = 'Borrowed'");
        $stmt_chk_b->execute([$userID]);
        $curr_borrowed = $stmt_chk_b->fetchColumn();

        $stmt_chk_r = $pdo->prepare("SELECT COUNT(*) FROM Reservation WHERE UserID = ? AND Status = 'Active'");
        $stmt_chk_r->execute([$userID]);
        $curr_reserved = $stmt_chk_r->fetchColumn();

        if (($curr_borrowed + $curr_reserved) >= $limitMax) {
            $status_message = "Denied: You have reached your limit of {$limitMax} books.";
            $error_type = 'error';
            $pdo->rollBack();
        } else {
            // 2. Check duplicate reservation
            $stmt_exists = $pdo->prepare("SELECT ReservationID FROM Reservation WHERE UserID = ? AND BookID = ? AND Status = 'Active'");
            $stmt_exists->execute([$userID, $bookID]);

            if ($stmt_exists->fetch()) {
                $status_message = "Error: You already have an active reservation for '{$bookTitle}'.";
                $error_type = 'error';
                $pdo->rollBack();
            } else {
                // 3. Insert Reservation
                $expiryDate = date('Y-m-d H:i:s', strtotime('+3 days'));

                $pdo->prepare("INSERT INTO Reservation (UserID, BookID, ExpiryDate, Status) VALUES (?, ?, ?, 'Active')")
                    ->execute([$userID, $bookID, $expiryDate]);

                $status_message = "Success! A reservation has been placed. Please wait for staff approval.";
                $error_type = 'success';
                $pdo->commit();
            }
        }

        // 1. Capture the page and search term from the form
        $redirectPage = filter_input(INPUT_POST, 'page', FILTER_VALIDATE_INT) ?: 1;
        $redirectSearch = trim($_POST['search'] ?? '');

        // 2. Build the URL with these parameters
        $url = "student_borrow.php?msg=" . urlencode($status_message) . 
               "&type={$error_type}" . 
               "&page={$redirectPage}";
        
        if (!empty($redirectSearch)) {
            $url .= "&search=" . urlencode($redirectSearch);
        }

        header("Location: " . $url);
        ob_end_flush();
        exit();

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Reserve Error: " . $e->getMessage());
        $status_message = "System Error: Your request could not be processed.";
        $error_type = 'error';

        header("Location: student_borrow.php?msg=" . urlencode($status_message) . "&type={$error_type}");
        ob_end_flush();
        exit();
    }
}

// Handle Message Display
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
            width: 70px;
            padding: 30px 0;
            background-color: #fff;
            border-right: 1px solid #eee;
            box-shadow: 3px 0 9px rgba(0, 0, 0, 0.05);
            position: fixed;
            height: 100vh;
            top: 0;
            left: 0;
            z-index: 100;
            /* The sidebar itself no longer needs a width transition for the smooth effect */
            transition: width 0.5s ease;
            overflow-x: hidden;
            overflow-y: auto;
            white-space: nowrap;
        }

        .sidebar.active {
            width: 250px;
            /* Expanded Width (Toggled by JS) */
        }

        .main-content-wrapper {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-left: 32px;
            padding-right: 32px;

            /* CRITICAL: The margin-left transitions to push the content away from the sidebar */
            margin-left: 70px;
            /* Initial margin equals collapsed sidebar width */
            transition: margin-left 0.5s ease;

            width: 100%;
            /* Important for centering */
        }

        .main-content-wrapper.pushed {
            margin-left: 250px;
            /* Margin equals expanded sidebar width */
        }

        .main-content {
            width: 100%;
            /* Allows the inner content to span the full width of the wrapper */
            max-width: 1200px;
            /* Optional: Sets a max width for readability */
            padding-top: 30px;
            /* Remove left/right padding here since the wrapper handles it */
        }

        .inventory-section {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .logo {
            font-size: 19px;
            font-weight: bold;
            color: #000;
            padding: 0 23px 40px;
            display: flex;
            align-items: center;
            cursor: pointer;
        }

        .logo-text {
            /* Hide text part of logo in collapsed view */
            opacity: 0;
            transition: opacity 0.1s ease;
            margin-left: 10px;
        }

        .sidebar.active .logo-text {
            opacity: 1;
            /* Show text when sidebar is active */
        }

        .nav-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .nav-item a {
            display: flex;
            /* Use Flex for icon/text alignment */
            align-items: center;
            font-size: 15px;
            padding: 15px 24px 15px;
            text-decoration: none;
            color: #6C6C6C;
            transition: background-color 0.2s;
            white-space: nowrap;
        }

        .text {
            /* Hide text part of logo in collapsed view */
            opacity: 0;
            transition: opacity 0.1s ease;
            margin-left: 5px;
        }

        .sidebar.active .text {
            opacity: 1;
            /* Show text when sidebar is active */
        }

        .nav-item a:hover {
            background-color: #f0f0f0;
            /* Added space for the button on the right */
        }

        .nav-item.active a {
            color: #000;
            font-weight: bold;
        }

        .nav-icon {
            font-family: 'Material Icons';
            margin-right: 20px;
            /* Space between icon and text when expanded */
            font-size: 21px;
            width: 20px;
            /* Fixed width to keep icons aligned */
        }

        .logout {
            margin-top: 260px;
            cursor: pointer;
        }

        .logout a {
            display: flex;
            align-items: center;
            font-size: 15px;
            padding: 15px 24px 15px;
            color: #e94343ff;
            text-decoration: none;
            transition: background-color 0.2s;
            white-space: nowrap;
        }

        .logout a:hover {
            background-color: #f0f0f0;
        }

        /* Main Content */
        .main-content {
            flex-grow: 1;
            padding: 30px 32px;
        }

        .inventory-section h2 {
            font-size: 25px;
            font-weight: bold;
            margin-left: 10px;
            margin-bottom: 20px;
            margin-top: 20px;
            align-self: flex-start;
        }

        .inventory-section p.subtitle {
            font-size: 15px;
            color: #666;
            margin-left: 10px;
            margin-bottom: 60px;
            margin-top: -5px;
            align-self: flex-start;
        }

        .search-filters {
            display: flex;
            gap: 15px;
            margin-bottom: 60px;
            width: 100%;
            max-width: 1050px;
            flex-wrap: wrap;
        }

        .search-input-wrapper {
            position: relative;
            flex-grow: 1;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            background-color: #fff;
            display: flex;
            min-width: 300px;
        }

        .search-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px 0 0 8px;
            font-size: 16px;
            color: #333;
            border-right: none;
        }

        .search-btn-icon {
            padding: 0 18px;
            border: none;
            background-color: #00A693;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 0 8px 8px 0;
            display: flex;
            align-items: center;
        }

        /* Book Cards */
        .book-list {
            display: flex;
            gap: 23px;
            flex-wrap: wrap;
            width: 100%;
            max-width: 1200px;
            /* Increased width */
            justify-content: center;
            /* Center cards */
        }

        .book-card {
            width: 320px;
            /* Increased width */
            height: 220px;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            padding: 15px;
            box-sizing: border-box;
            transition: transform 0.2s;
        }

        .book-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
        }

        .book-cover-area {
            width: 110px;
            height: 190px;
            border-radius: 8px;
            margin-right: 15px;
            flex-shrink: 0;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-color: #F0F8F8;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #999;
            font-size: 12px;
        }

        .book-details {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            flex-grow: 1;
            padding: 5px 0;
        }

        .book-title {
            font-size: 16px;
            font-weight: 600;
            line-height: 1.3;
            margin: 5px 0 3px 0;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .book-author {
            font-size: 13px;
            color: #666;
            margin: 0;
        }

        .stock-available {
            font-weight: 700;
            color: #00A693;
        }

        .stock-low {
            font-weight: 700;
            color: #ff9800;
        }

        /* Buttons */
        .action-button {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px 15px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            width: 100%;
            min-height: 38px;
            box-sizing: border-box;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .reserve-btn {
            background-color: #00A693;
            color: #fff;
        }

        .reserve-btn:hover {
            background-color: #00897B;
        }

        .reserved-tag {
            background-color: #e0e0e0;
            color: #666;
            cursor: not-allowed;
        }

        .disabled-btn {
            background-color: #ddd;
            color: #666;
            cursor: not-allowed;
        }

        /* Pagination */
        .pagination-container {
            margin-top: 30px;
            width: 100%;
            max-width: 1000px;
            display: flex;
            justify-content: center;
        }

        .pagination {
            display: flex;
            list-style: none;
            padding: 0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .page-item {
            border-right: 1px solid #eee;
        }

        .page-item:last-child {
            border-right: none;
        }

        .page-link {
            display: block;
            padding: 10px 15px;
            background-color: #fff;
            color: #00A693;
            text-decoration: none;
            transition: background-color 0.2s;
        }

        .page-link:hover {
            background-color: #f0f8f8;
        }

        .page-item.active .page-link {
            background-color: #00A693;
            color: #fff;
        }

        .page-item.disabled .page-link {
            color: #ccc;
            pointer-events: none;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.6);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: #fff;
            margin: auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.4);
            text-align: center;
        }

        .modal-buttons button {
            padding: 10px 20px;
            margin: 0 10px;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
        }

        .confirm-btn {
            background-color: #00A693;
            color: #fff;
        }

        .cancel-btn {
            background-color: #ddd;
            color: #333;
        }

        /* Alerts */
        .status-box {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            padding: 15px 25px;
            margin-left: 10px;
            border-radius: 8px;
            width: auto;
            max-width: 350px;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            opacity: 1;
            transition: opacity 0.5s ease-out, visibility 0.5s;
        }

        .status-box.hidden {
            opacity: 0;
            visibility: hidden;
        }

        .status-success {
            background-color: #e8f5e9;
            color: #388e3c;
        }

        .status-error {
            background-color: #ffcdd2;
            color: #d32f2f;
        }
    </style>
</head>

<body>
    <div class="container">
        <div id="sidebar-menu" class="sidebar">
            <div class="logo" onclick="toggleSidebar()">
                <span class="hamburger-icon material-icons">menu</span>
                <span class="logo-text">📚 Smart Library</span>
            </div>

            <ul class="nav-list">
                <li class="nav-item"><a href="student.php">
                        <span class="nav-icon material-icons">dashboard</span>
                        <span class="text">Dashboard</span>
                    </a></li>
                <li class="nav-item active"><a href="student_borrow.php">
                        <span class="nav-icon material-icons">local_library</span>
                        <span class="text">Books</span>
                    </a></li>
                <li class="nav-item"><a href="student_reservation.php">
                        <span class="nav-icon material-icons">bookmark_add</span>
                        <span class="text">Reservations</span>
                    </a></li>
                <li class="nav-item"><a href="studentborrowed_books.php">
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
                        (Status: <?php echo $totalActive; ?>/<?php echo $limitMax; ?> slots used)
                    </p>

                    <form method="GET" action="student_borrow.php" class="search-filters" style="max-width: 100%;">
                        <div class="search-input-wrapper">
                            <input type="text" name="search" class="search-input"
                                placeholder="Search by Title or Author..."
                                value="<?php echo htmlspecialchars($search_term); ?>">
                            <button type="submit" class="search-btn-icon">
                                <span class="material-icons">search</span>
                            </button>
                        </div>
                    </form>

                    <div class="book-list">
                        <?php if (empty($book_inventory)): ?>
                            <p style="width: 100%; color: #999; text-align: center;">No books found in the catalog matching
                                your search.</p>
                        <?php else: ?>
                            <?php
                            foreach ($book_inventory as $book):
                                $isAvailable = $book['CopiesAvailable'] > 0;
                                $stockText = $isAvailable ? "{$book['CopiesAvailable']} available" : "Out of Stock";
                                $stockClass = $isAvailable ? 'stock-available' : 'stock-low';

                                // Fix for image loading (OpenLibrary vs Local)
                                $coverPath = $book['CoverImagePath'] ?? '';
                                $bgStyle = '';
                                if (!empty($coverPath)) {
                                    $url = (strpos($coverPath, 'http') === 0) ? $coverPath : BASE_URL . '/' . $coverPath;
                                    $bgStyle = "background-image: url('" . htmlspecialchars($url) . "');";
                                }

                                // Button Logic
                                $hasActiveReservation = $book['HasActiveReservation'] > 0;

                                if ($hasActiveReservation) {
                                    $buttonText = "Already Reserved";
                                    $buttonClass = "reserved-tag";
                                    $isDisabled = true;
                                } elseif ($maxedOut) {
                                    $buttonText = "Limit Reached";
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

                                        <form method="POST" action="student_borrow.php"
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

                    <!-- PAGINATION -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination-container">
                            <ul class="pagination">
                                <?php
                                $searchParam = !empty($search_term) ? '&search=' . urlencode($search_term) : '';
                                // Previous
                                $prevDisabled = ($current_page <= 1) ? 'disabled' : '';
                                echo "<li class='page-item $prevDisabled'><a class='page-link' href='?page=" . ($current_page - 1) . "$searchParam'>Previous</a></li>";

                                // Pages
                                for ($i = 1; $i <= $total_pages; $i++) {
                                    $active = ($i == $current_page) ? 'active' : '';
                                    echo "<li class='page-item $active'><a class='page-link' href='?page=$i$searchParam'>$i</a></li>";
                                }

                                // Next
                                $nextDisabled = ($current_page >= $total_pages) ? 'disabled' : '';
                                echo "<li class='page-item $nextDisabled'><a class='page-link' href='?page=" . ($current_page + 1) . "$searchParam'>Next</a></li>";
                                ?>
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

            <script>
                function openModal(modalId) { document.getElementById(modalId).style.display = 'flex'; }
                function closeModal(modalId) { document.getElementById(modalId).style.display = 'none'; }

                function openConfirmModal(formElement, bookId, bookTitle) {
                    const modalMessage = document.getElementById('modalMessage');
                    const confirmBtn = document.getElementById('modalConfirmBtn');
                    const submissionForm = document.getElementById('modalSubmissionForm');

                    modalMessage.innerHTML = `Do you want to reserve <b>${bookTitle}</b>?<br><small>This request will be sent to staff for approval.</small>`;

                    document.getElementById('modalBookId').value = bookId;
                    document.getElementById('modalBookTitle').value = bookTitle;

                    confirmBtn.onclick = function () {
                        // Save the current vertical scroll position
                        sessionStorage.setItem('scrollPosition', window.scrollY);
                        submissionForm.submit();
                    };

                    openModal('confirmActionModal');
                    return false;
                }

                window.onclick = function (event) {
                    if (event.target.classList.contains('modal')) {
                        event.target.style.display = 'none';
                    }
                }

                function toggleSidebar() {
                    const sidebar = document.getElementById('sidebar-menu');
                    const mainContent = document.getElementById('main-content-wrapper');
                    sidebar.classList.toggle('active');
                    mainContent.classList.toggle('pushed');
                    if (sidebar.classList.contains('active')) {
                        localStorage.setItem('sidebarState', 'expanded');
                    } else {
                        localStorage.setItem('sidebarState', 'collapsed');
                    }
                }

                document.addEventListener('DOMContentLoaded', () => {
                    // 1. Restore Sidebar State
                    const savedState = localStorage.getItem('sidebarState');
                    if (savedState === 'expanded') {
                        toggleSidebar();
                    }

                    // 2. Restore Scroll Position
                    const savedScroll = sessionStorage.getItem('scrollPosition');
                    if (savedScroll) {
                        window.scrollTo(0, parseInt(savedScroll));
                        sessionStorage.removeItem('scrollPosition');
                    }

                    // 3. Notification Logic & URL Cleanup
                    const notification = document.getElementById('statusNotification');
                    if (notification) {
                        // Hide message after 3 seconds
                        setTimeout(() => {
                            notification.classList.add('hidden');
                        }, 3000);

                        // REMOVE parameters from URL without refreshing
                        if (window.history.replaceState) {
                            const url = new URL(window.location);
                            url.searchParams.delete('msg');   // Remove message
                            url.searchParams.delete('type');  // Remove type
                            window.history.replaceState({}, '', url);
                        }
                    }
                });
            </script>
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

    <form id="modalSubmissionForm" method="POST" action="student_borrow.php">
        <input type="hidden" name="book_id" id="modalBookId">
        <input type="hidden" name="book_title" id="modalBookTitle">
        
        <input type="hidden" name="page" value="<?php echo $current_page; ?>">
        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
    </form>
</body>

</html>