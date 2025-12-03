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

$userID = $_SESSION['user_id'];
$status_message = '';
$error_type = '';

// --- Handle Cancellation (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
    $reservationID = filter_var($_POST['cancel_id'], FILTER_VALIDATE_INT);

    if ($reservationID) {
        try {
            $pdo->beginTransaction();

            // Verify ownership and status
            $stmt_check = $pdo->prepare("SELECT ReservationID FROM reservation WHERE ReservationID = ? AND UserID = ? AND Status = 'Active'");
            $stmt_check->execute([$reservationID, $userID]);

            if ($stmt_check->fetch()) {
                $update_stmt = $pdo->prepare("UPDATE reservation SET Status = 'Cancelled' WHERE ReservationID = ?");
                $update_stmt->execute([$reservationID]);

                $status_message = "Reservation cancelled successfully.";
                $error_type = 'success';
                $pdo->commit();
            } else {
                $status_message = "Error: Reservation not found or already processed.";
                $error_type = 'error';
                $pdo->rollBack();
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Cancel Reservation Error: " . $e->getMessage());
            $status_message = "System Error: Could not cancel reservation.";
            $error_type = 'error';
        }

        header("Location: student_teacher_reservation.php?msg=" . urlencode($status_message) . "&type={$error_type}");
        ob_end_flush();
        exit();
    }
}

// --- Fetch Reservations ---
$reservations = [];
try {
    $sql = "
        SELECT 
            R.ReservationID, 
            R.ReservationDate, 
            R.ExpiryDate, 
            R.Status,
            B.Title, B.Author, B.CoverImagePath
        FROM reservation R
        JOIN book B ON R.BookID = B.BookID
        WHERE R.UserID = ? AND R.Status = 'Active'
        ORDER BY R.ExpiryDate ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userID]);
    $reservations = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch Reservations Error: " . $e->getMessage());
    $status_message = "Error loading reservations.";
    $error_type = 'error';
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
    <title>Reservations</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">

    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/styles.css">
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
                <li class="nav-item active"><a href="student_teacher_reservation.php">
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
                <div class="reservation-section">
                    <h2>Reservations</h2>
                    <p class="subtitle">Reserve books to make a reservation or manage your current queue.</p>

                    <div class="section-title">
                        Your Current Reservations (<?php echo count($reservations); ?>)
                    </div>

                    <div class="reservation-list">
                        <?php if (empty($reservations)): ?>
                            <p style="color: #666; font-style: italic; width: 100%; text-align: center;">
                                You have no active reservations.
                            </p>
                        <?php else: ?>
                            <?php foreach ($reservations as $res):
                                $expiryDateObj = new DateTime($res['ExpiryDate']);
                                $reservationDateObj = new DateTime($res['ReservationDate']);
                                $now = new DateTime();

                                $expiryFormatted = $expiryDateObj->format('M d, Y');
                                $reservedFormatted = $reservationDateObj->format('M d, Y');

                                $isExpired = $expiryDateObj < $now;
                                $statusClass = $isExpired ? 'status-expired' : 'status-active';
                                $statusText = $isExpired ? 'EXPIRED' : 'Active';
                                ?>
                                <div class="reservation-card">
                                    <div class="res-book-info">
                                        <div class="res-title"><?php echo htmlspecialchars($res['Title']); ?></div>
                                        <div class="res-author">By: <?php echo htmlspecialchars($res['Author']); ?></div>
                                        <div class="res-meta-details">
                                            <span>Reserved on: <?php echo $reservedFormatted; ?></span>
                                        </div>
                                    </div>

                                    <div class="res-actions">
                                        <button type="button" class="cancel-btn"
                                            onclick="openCancelModal(<?php echo $res['ReservationID']; ?>, '<?php echo htmlspecialchars(addslashes($res['Title'])); ?>')"
                                            <?php echo $isExpired ? 'disabled' : ''; ?>>
                                            <?php echo $isExpired ? 'Expired' : 'Cancel Reservation'; ?>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
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

    <div id="cancelReservationModal" class="modal">
        <div class="modal-content">
            <h3>Confirm Cancellation</h3>
            <p id="modalMessage">Are you sure you want to cancel this reservation?</p>
            <div class="modal-buttons">
                <form id="cancelForm" method="POST">
                    <input type="hidden" name="cancel_id" id="modalReservationId">
                    <button type="submit" class="confirm-cancel-btn">Yes, Cancel It</button>
                    <button type="button" class="modal-cancel-btn" onclick="closeModal('cancelReservationModal')">Keep
                        Reservation</button>
                </form>
            </div>
        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>/public/js/main.js"></script>
</body>

</html>