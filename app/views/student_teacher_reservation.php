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

        .reservation-section {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .reservation-section h2 {
            font-size: 25px;
            font-weight: bold;
            margin-left: 10px;
            margin-bottom: 20px;
            margin-top: 20px;
            align-self: flex-start;
        }

        .reservation-section p.subtitle {
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

        /* Card Styles */
        .reservation-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
            width: 100%;
            max-width: 1000px;
        }

        .reservation-card {
            background: #fff;
            border-radius: 8px;
            padding: 25px 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #ddd;
        }

        .res-book-info {
            display: flex;
            flex-direction: column;
        }

        .res-title {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .res-author {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
        }

        .res-meta-details {
            font-size: 13px;
            color: #888;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .status-pill {
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
        }

        .status-active {
            color: #00796B;
            background: #E0F2F1;
        }

        .status-expired {
            color: #C62828;
            background: #FFCDD2;
        }

        .res-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .res-expiry {
            font-size: 14px;
            color: #555;
            font-weight: 500;
        }

        .res-expiry span {
            color: #E5A000;
            font-weight: 700;
        }

        .cancel-btn {
            background: #E94343;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }

        .cancel-btn:hover {
            background: #D63939;
        }

        /* Modals & Alerts */
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
            transition: 0.5s ease;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
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
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
        }

        .confirm-cancel-btn {
            background: #E94343;
            color: #fff;
        }

        .modal-cancel-btn {
            background: #ddd;
            color: #333;
        }

        @media (max-width: 768px) {
            .reservation-card {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }

            .res-actions {
                width: 100%;
                justify-content: space-between;
            }
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

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar-menu');
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

        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function openCancelModal(reservationId, bookTitle) {
            document.getElementById('modalMessage').innerHTML = `Are you sure you want to cancel your reservation for <b>${bookTitle}</b>?`;
            document.getElementById('modalReservationId').value = reservationId;
            openModal('cancelReservationModal');
        }
        window.onclick = function (event) {
            if (event.target.classList.contains('modal')) closeModal(event.target.id);
        }
    </script>
</body>

</html>