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

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/student_teacher_borrowed_books.css">
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

    <script src="<?php echo BASE_URL; ?>/public/js/main.js"></script>
</body>

</html>