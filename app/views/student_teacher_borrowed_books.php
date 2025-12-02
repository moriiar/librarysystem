<?php
// CRITICAL: Start Output Buffering
ob_start();
session_start();

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../models/database.php';

// --- Authentication Check ---
// Allow both 'Student' and 'Teacher' roles
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Student', 'Teacher'])) {
    header("Location: " . BASE_URL . "/views/login.php");
    ob_end_flush();
    exit();
}

$userID = $_SESSION['user_id'];
$borrowedBooks = [];

try {
    $sql = "
        SELECT 
            BK.Title, 
            BK.Author,
            BO.BorrowDate, 
            BO.DueDate
        FROM borrowing_record BO
        JOIN book_copy BC ON BO.CopyID = BC.CopyID
        JOIN book BK ON BC.BookID = BK.BookID
        WHERE BO.UserID = ? AND BO.Status = 'Borrowed'
        ORDER BY BO.DueDate ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userID]);
    $borrowedBooks = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch Borrowed Books Error: " . $e->getMessage());
}
?>
<?php ob_end_flush(); ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrowed Books</title>

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

        .borrowed-section {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .borrowed-section h2 {
            font-size: 25px;
            font-weight: bold;
            margin-left: 10px;
            margin-bottom: 20px;
            margin-top: 20px;
            align-self: flex-start;
        }

        .borrowed-section p.subtitle {
            font-size: 15px;
            color: #666;
            margin-left: 10px;
            margin-bottom: 40px;
            margin-top: -5px;
            align-self: flex-start;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            margin-left: 10px;
            margin-bottom: 20px;
            color: #333;
            align-self: flex-start;
        }

        .borrowed-list-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            max-width: 1000px;
        }

        .borrowed-list-table th,
        .borrowed-list-table td {
            padding: 20px 25px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .borrowed-list-table th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 14px;
            color: #6C6C6C;
            text-transform: uppercase;
        }

        .borrowed-list-table tbody tr:hover {
            background: #fafafa;
        }

        .book-title {
            font-weight: 600;
            color: #333;
            display: block;
        }

        .book-author {
            font-size: 13px;
            color: #888;
            margin-top: 4px;
        }

        .date-text {
            font-weight: 500;
        }

        .due-date-text {
            color: #E5A000;
            font-weight: 600;
        }

        .overdue-text {
            color: #d32f2f;
            font-weight: 700;
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
                <li class="nav-item"><a href="student_teacher_borrow.php">
                        <span class="nav-icon material-icons">local_library</span>
                        <span class="text">Books</span>
                    </a></li>
                <li class="nav-item"><a href="student_teacher_reservation.php">
                        <span class="nav-icon material-icons">bookmark_add</span>
                        <span class="text">Reservations</span>
                    </a></li>
                <li class="nav-item active"><a href="student_teacher_borrowed_books.php">
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
                <div class="borrowed-section">
                    <h2>Your Borrowed Books</h2>
                    <p class="subtitle">Manage the status and due dates of your active books.</p>

                    <!-- Dynamic Title based on role -->
                    <div class="section-title">
                        Active Books
                        (<?php echo count($borrowedBooks); ?><?php echo ($_SESSION['role'] === 'Student') ? '/3' : ''; ?>)
                    </div>

                    <table class="borrowed-list-table">
                        <thead>
                            <tr>
                                <th style="width: 50%;">Book Title</th>
                                <th style="width: 25%;">Borrow Date</th>
                                <th style="width: 25%;">Due Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($borrowedBooks)): ?>
                                <tr>
                                    <td colspan="3"
                                        style="color: #666; font-style: italic; width: 100%; text-align: center;">
                                        You have no books currently borrowed.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php
                                foreach ($borrowedBooks as $book):
                                    $borrowDate = new DateTime($book['BorrowDate']);
                                    $dueDate = new DateTime($book['DueDate']);
                                    $today = new DateTime();
                                    $isOverdue = $today > $dueDate;
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="book-title"><?php echo htmlspecialchars($book['Title']); ?></span>
                                            <span class="book-author">By:
                                                <?php echo htmlspecialchars($book['Author']); ?></span>
                                        </td>
                                        <td class="date-text"><?php echo $borrowDate->format('M d, Y'); ?></td>
                                        <td class="<?php echo $isOverdue ? 'overdue-text' : 'due-date-text'; ?>">
                                            <?php echo $dueDate->format('M d, Y'); ?>
                                            <?php if ($isOverdue)
                                                echo " (Overdue)"; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar-menu');
            // TARGET THE WRAPPER, NOT THE INNER CONTENT
            const mainContentWrapper = document.getElementById('main-content-wrapper');

            sidebar.classList.toggle('active');
            mainContentWrapper.classList.toggle('pushed');

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
                const mainContentWrapper = document.getElementById('main-content-wrapper');

                sidebar.classList.add('active');
                mainContentWrapper.classList.add('pushed');
            }
        });
    </script>
</body>

</html>