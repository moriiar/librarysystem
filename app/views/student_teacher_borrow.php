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
            /* Initial Collapsed Width */
            padding: 30px 0;
            background-color: #fff;
            border-right: 1px solid #eee;
            box-shadow: 3px 0 9px rgba(0, 0, 0, 0.05);

            /* CRITICAL FIX: Anchor the sidebar to the viewport */
            position: fixed;
            height: 100vh;
            /* Full height */
            top: 0;
            left: 0;
            z-index: 100;
            /* Stays above content */

            flex-shrink: 0;
            overflow-x: hidden;
            overflow-y: auto;
            transition: width 0.5s ease;
            /* Smooth toggle animation */
            white-space: nowrap;
        }

        .sidebar.active {
            width: 250px;
            /* Expanded Width (Toggled by JS) */
        }

        .logo {
            font-size: 19px;
            font-weight: bold;
            color: #000;
            padding: 0 23px 40px;
            display: flex;
            align-items: center;
            cursor: pointer;
            /* Indicate it's clickable */
            white-space: nowrap;
            /* Prevents logo text wrap/break */
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
            margin-top: 310px;
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

        /* Main Content Area */
        .main-content-wrapper {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0 32px;
            margin-left: 70px;
            transition: margin-left 0.5s ease;
            width: 100%;
        }

        .main-content-wrapper.pushed {
            margin-left: 250px;
        }

        .main-content {
            width: 100%;
            max-width: 1200px;
            padding-top: 30px;
        }

        .inventory-section {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
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

        /* Styling for ALL Select/Input fields */
        .search-input,
        .filter-select {
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            color: #333;
            transition: border-color 0.2s;
            box-sizing: border-box;
            height: 48px;
            /* Ensures all inputs/selects are the same height */
        }

        /* Separate styling for the filter selects */
        .filter-select {
            max-width: 200px;
            flex-grow: 0;
            /* Prevents stretching */
            padding-right: 35px;
            /* Space for the dropdown arrow */

            /* Apply box shadow styling to filter selects too */
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            background-color: #fff;
        }

        /* Focus styling for all filter elements */
        .search-input:focus,
        .filter-select:focus {
            outline: none;
        }

        /* New Style for the Search Icon Button (Replaces the text button) */
        .search-btn-icon {
            /* Positioned visually next to the input field */
            padding: 0 18px;
            border: none;
            background-color: #57e4d4ff;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 0 8px 8px 0;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
        }

        .search-btn-icon:hover {
            background-color: #4bd0c0ff;
        }

        .search-input {
            width: 100%;
            padding: 12px 35px 12px 18px;
            border: 2px solid #ddd;
            border-right: none;
            border-radius: 8px 0 0 8px;
            padding-right: 35px;
            font-size: 16px;
            background-color: #fff;
            color: #333;
            transition: border-color 0.3s;
        }

        .search-input:focus {
            border-color: #57e4d4ff;
            outline: none;
        }

        .clear-btn {
            position: absolute;
            top: 50%;
            right: 9%;
            transform: translateY(-50%);
            height: 100%;
            width: 35px;
            background: none;
            border: none;
            color: #999;
            font-size: 28px;
            font-weight: 600px;
            cursor: pointer;
            display:
                <?php echo empty($search_term) ? 'none' : 'block'; ?>
            ;
            padding: 0;
            line-height: 1;
            outline: none;
            transition: color 0.2s;
            z-index: 10;
        }

        .clear-btn:hover {
            color: #777;
        }

        /* Book Cards */
        .book-list {
            display: flex;
            gap: 25px;
            flex-wrap: wrap;
            width: 100%;
            justify-content: center;
        }

        .book-card {
            width: 320px;
            height: 220px;
            background: #fff;
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
            background-color: #F0F8F8;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 12px;
            background-size: cover;
            background-position: center;
            flex-shrink: 0;
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
            margin: 5px 0 3px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
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
            border: none;
            width: 100%;
            min-height: 38px;
            cursor: pointer;
            transition: 0.2s;
            font-size: 14px;
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
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }

        .page-link {
            display: block;
            padding: 10px 15px;
            background: #fff;
            color: #00A693;
            text-decoration: none;
            border-right: 1px solid #eee;
        }

        .page-link:hover {
            background: #f0f8f8;
        }

        .page-item.active .page-link {
            background: #00A693;
            color: #fff;
        }

        .page-item.disabled .page-link {
            color: #ccc;
            pointer-events: none;
        }

        /* Modals & Alerts */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 400px;
            text-align: center;
        }

        .modal-buttons button {
            padding: 10px 20px;
            margin: 0 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
        }

        .confirm-btn {
            background: #00A693;
            color: #fff;
        }

        .cancel-btn {
            background: #ddd;
            color: #333;
        }

        .status-box {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            padding: 15px 25px;
            border-radius: 8px;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .status-success {
            background: #e8f5e9;
            color: #388e3c;
        }

        .status-error {
            background: #ffcdd2;
            color: #d32f2f;
        }

        .hidden {
            opacity: 0;
            visibility: hidden;
            transition: 0.5s;
        }

        /* --- PAGINATION STYLES --- */
        .pagination-controls {
            margin-top: 10px;
            margin-bottom: 10px;
            display: flex;
            justify-content: flex-end;
            width: 100%;
        }

        .pagination {
            display: flex;
            padding-left: 0;
            list-style: none;
            border-radius: 0.50rem;
        }

        .page-item:first-child .page-link {
            border-top-left-radius: 0.50rem;
            border-bottom-left-radius: 0.50rem;
        }

        .page-item:last-child .page-link {
            border-top-right-radius: 0.50rem;
            border-bottom-right-radius: 0.50rem;
        }

        .page-link {
            display: block;
            padding: 0.5rem 0.75rem;
            color: #4aa0fdff;
            background-color: #fff;
            border: 1px solid #dee2e6;
            text-decoration: none;
            transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out;
        }

        .page-item:not(:first-child) .page-link {
            margin-left: -1px;
            /* Overlap borders slightly */
        }

        .page-link:hover {
            z-index: 2;
            color: #007bffff;
            background-color: #e9ecef;
            border-color: #dee2e6;
        }

        /* Active State */
        .page-item.active .page-link {
            z-index: 3;
            color: #fff;
            background-color: #34cfbcff;
            border-color: #34cfbcff;
        }

        /* Disabled State */
        .page-item.disabled .page-link {
            color: #6c757d;
            pointer-events: none;
            background-color: #eeebebff;
            border-color: #d3d2d2ff;
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

                            <button type="button" class="clear-btn" onclick="window.location.href='student_teacher_borrow.php';"
                                title="Clear Search" <?php echo empty($search_term) ? 'style="display: none;"' : ''; ?>>
                                &times;
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
                            <p style="width: 100%; color: #999; text-align: center;">No books found in the catalog matching your search.</p>
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

            <script>
                function openModal(modalId) {
                    document.getElementById(modalId).style.display = 'flex';
                }

                function closeModal(modalId) {
                    document.getElementById(modalId).style.display = 'none';
                }

                function openConfirmModal(formElement, bookId, bookTitle) {
                    const modalMessage = document.getElementById('modalMessage');
                    const confirmBtn = document.getElementById('modalConfirmBtn');
                    const submissionForm = document.getElementById('modalSubmissionForm');

                    modalMessage.innerHTML = `Do you want to reserve <b>${bookTitle}</b>?<br><small>This request will be sent to staff for approval.</small>`;

                    document.getElementById('modalBookId').value = bookId;
                    document.getElementById('modalBookTitle').value = bookTitle;

                    confirmBtn.onclick = function () {
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
                    const savedState = localStorage.getItem('sidebarState');
                    if (savedState === 'expanded') {
                        const sidebar = document.getElementById('sidebar-menu');
                        const mainContent = document.getElementById('main-content-wrapper');
                        sidebar.classList.add('active');
                        mainContent.classList.add('pushed');
                    }
                    const savedScroll = sessionStorage.getItem('scrollPosition');
                    if (savedScroll) {
                        window.scrollTo(0, parseInt(savedScroll));
                        sessionStorage.removeItem('scrollPosition');
                    }
                    const notification = document.getElementById('statusNotification');
                    if (notification) {
                        setTimeout(() => {
                            notification.classList.add('hidden');
                        }, 3000);
                        if (window.history.replaceState) {
                            const url = new URL(window.location);
                            url.searchParams.delete('msg');
                            url.searchParams.delete('type');
                            window.history.replaceState({}, '', url);
                        }
                    }
                });

                const searchInput = document.getElementById('search-input-field');
                const clearBtn = document.querySelector('.clear-btn');

                // Show/hide the 'X' button dynamically as the user types
                if (searchInput && clearBtn) {
                    searchInput.addEventListener('input', function () {
                        if (this.value.length > 0) {
                            clearBtn.style.display = 'block';
                        } else {
                            clearBtn.style.display = 'none';
                        }
                    });
                }
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

    <form id="modalSubmissionForm" method="POST" action="student_teacher_borrow.php">
        <input type="hidden" name="book_id" id="modalBookId">
        <input type="hidden" name="book_title" id="modalBookTitle">
        <input type="hidden" name="page" value="<?php echo $current_page; ?>">
        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
    </form>
</body>

</html>