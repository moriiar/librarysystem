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

// --- Dynamic Limit Logic ---
// Students get 3, Teachers get "Unlimited"
$borrowedbookLimit = ($user_role === 'Teacher') ? 'Unlimited' : 3;

$borrowedCount = 0;
$reservationCount = 0;
$clearanceStatus = 'Cleared';
$hasOverdue = false;

try {
    // 1. Get Count of Currently Borrowed Books
    $stmt1 = $pdo->prepare("SELECT COUNT(BorrowID) FROM borrowing_record WHERE UserID = ? AND Status = 'Borrowed'");
    $stmt1->execute([$userID]);
    $borrowedCount = $stmt1->fetchColumn();

    // 2. Get Count of Active Reservations
    $stmt2 = $pdo->prepare("SELECT COUNT(ReservationID) FROM reservation WHERE UserID = ? AND Status = 'Active'");
    $stmt2->execute([$userID]);
    $reservationCount = $stmt2->fetchColumn();

    // 3. Check for Overdue Books and Pending Penalties
    $stmt3 = $pdo->prepare("
        SELECT 
            COUNT(BO.BorrowID) AS OverdueCount,
            (SELECT SUM(AmountDue) FROM penalty WHERE UserID = ? AND Status = 'Pending') AS PendingFees
        FROM borrowing_record BO
        WHERE BO.UserID = ? AND BO.Status = 'Borrowed' AND BO.DueDate < NOW()
    ");
    $stmt3->execute([$userID, $userID]);
    $liabilities = $stmt3->fetch();

    if (($liabilities['OverdueCount'] ?? 0) > 0 || ($liabilities['PendingFees'] ?? 0) > 0.00) {
        $clearanceStatus = 'On Hold';
        $hasOverdue = ($liabilities['OverdueCount'] ?? 0) > 0;
    }
} catch (PDOException $e) {
    error_log("Dashboard Error: " . $e->getMessage());
}
?>
<?php ob_end_flush(); ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user_role); ?>'s Dashboard</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">

    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/style.css">
</head>

<body>
    <div class="container">
        <div id="sidebar-menu" class="sidebar">
            <div class="logo" onclick="toggleSidebar()">
                <span class="hamburger-icon material-icons">menu</span>
                <span class="logo-text">📚 Smart Library</span>
            </div>

            <ul class="nav-list">
                <li class="nav-item active"><a href="student_teacher.php">
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

        <div id="main-content-area" class="main-content">
            <div class="header">
                Welcome, <span><?php echo htmlspecialchars($user_name); ?></span>
            </div>

            <div class="dashboard-section">
                <!-- Role-Aware Title -->
                <h2><?php echo htmlspecialchars($user_role); ?>'s Dashboard</h2>

                <!-- Role-Aware Limit Text -->
                <div class="borrow-limit">
                    Borrow Limit: <?php echo $borrowedbookLimit; ?>
                    <?php echo ($user_role === 'Student') ? 'books per semester' : 'books'; ?>
                </div>

                <div class="metric-cards">
                    <div class="metric-box">
                        <?php
                        // Only flag as 'bad' if it's a student over the limit
                        $isOverLimit = ($user_role === 'Student' && $borrowedCount >= 3);
                        $iconColor = $isOverLimit ? 'stat-bad' : 'stat-good';
                        $textColor = $isOverLimit ? 'stat-bad' : 'stat-good';
                        ?>
                        <span class="material-icons metric-icon <?php echo $iconColor; ?>">menu_book</span>
                        <h4 class="<?php echo $textColor; ?>"><?php echo $borrowedCount; ?></h4>
                        <p>Active Borrowed Books</p>
                    </div>

                    <div class="metric-box">
                        <span class="material-icons metric-icon stat-<?php echo $reservationCount > 0 ? 'warn' : 'good'; ?>">bookmark</span>
                        <h4 class="<?php echo $reservationCount > 0 ? 'stat-warn' : 'stat-good'; ?>">
                            <?php echo $reservationCount; ?>
                        </h4>
                        <p>Active Reservations</p>
                    </div>

                    <div class="metric-box">
                        <span class="material-icons metric-icon stat-<?php echo $clearanceStatus === 'On Hold' ? 'bad' : 'good'; ?>">
                            <?php echo $clearanceStatus === 'On Hold' ? 'warning' : 'check_circle'; ?>
                        </span>
                        <h4 class="<?php echo $clearanceStatus === 'On Hold' ? 'stat-bad' : 'stat-good'; ?>">
                            <?php echo $clearanceStatus; ?>
                        </h4>
                        <p>Clearance Status</p>
                    </div>
                </div>

                <div class="metric-cards" style="margin-top: 0; margin-bottom: 30px;">
                    <div class="action-link-box">
                        <a href="student_teacher_borrow.php">
                            <span class="material-icons" style="margin-right: 10px;">search</span>
                            Reserve Books
                        </a>
                    </div>
                    <div class="action-link-box">
                        <a href="student_teacher_borrowed_books.php">
                            <span class="material-icons" style="margin-right: 10px;">assignment_return</span>
                            View Borrowed Books
                        </a>
                    </div>
                </div>

                <?php if ($hasOverdue || $clearanceStatus === 'On Hold'): ?>
                    <div class="reminder">
                        ⚠️ **Action Required:** Your clearance is on hold due to overdue items or pending fees. Please settle your liability.
                    </div>
                <?php endif; ?>

                <div class="reminder" style="color: #6C6C6C; background-color: #f0f0f0; border: 2px solid #ddd;">
                    Reminder: You must return all borrowed books at semester end. Unreturned books will be charged at full price.
                </div>

            </div>
        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>/public/js/main.js"></script>
</body>

</html>