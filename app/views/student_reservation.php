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
$status_message = '';
$error_type = '';

// --- Handle Cancellation (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
    $reservationID = filter_var($_POST['cancel_id'], FILTER_VALIDATE_INT);
    
    if ($reservationID) {
        try {
            $pdo->beginTransaction();
            
            // Verify ownership and status before cancelling
            $stmt_check = $pdo->prepare("SELECT ReservationID FROM Reservation WHERE ReservationID = ? AND UserID = ? AND Status = 'Active'");
            $stmt_check->execute([$reservationID, $userID]);
            
            if ($stmt_check->fetch()) {
                // Update status to Cancelled
                $update_stmt = $pdo->prepare("UPDATE Reservation SET Status = 'Cancelled' WHERE ReservationID = ?");
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
        
        // Redirect to prevent resubmission
        header("Location: student_reservation.php?msg=" . urlencode($status_message) . "&type={$error_type}");
        ob_end_flush();
        exit();
    }
}

// --- Fetch Reservations ---
$reservations = [];
try {
    // Fetch Active reservations joined with Book details
    $sql = "
        SELECT 
            R.ReservationID, R.ReservationDate, R.ExpiryDate, R.Status,
            B.Title, B.Author, B.CoverImagePath
        FROM Reservation R
        JOIN Book B ON R.BookID = B.BookID
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
    <title>Reservation System</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <style>
        /* Global Styles */
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #F7FCFC;
            color: #333;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
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
            overflow-x: hidden;
            overflow-y: auto;
            transition: width 0.5s ease;
            white-space: nowrap;
        }

        .sidebar.active {
            width: 280px;
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
            opacity: 0;
            transition: opacity 0.1s ease;
            margin-left: 10px;
        }

        .sidebar.active .logo-text { opacity: 1; }
        .text { opacity: 0; transition: opacity 0.1s ease; margin-left: 5px; }
        .sidebar.active .text { opacity: 1; }

        .nav-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .nav-item a {
            display: flex;
            align-items: center;
            font-size: 15px;
            padding: 15px 24px 15px;
            text-decoration: none;
            color: #6C6C6C;
            transition: background-color 0.2s;
        }

        .nav-item.active a {
            color: #000;
            font-weight: bold;
        }

        .nav-icon {
            font-family: 'Material Icons';
            margin-right: 20px;
            font-size: 21px;
            width: 20px;
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
        }

        /* Main Content */
        .main-content-wrapper {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-left: 32px;
            padding-right: 32px;

            margin-left: 70px;
            transition: margin-left 0.5s ease;
            width: 100%;
        }

        .main-content-wrapper.pushed {
            margin-left: 280px;
        }
        
        .main-content {
            width: 100%;
            max-width: 1200px;
            padding-top: 30px;
            min-height: 100vh;
        }

        /* Reservation UI */
        .reservation-section h2 {
            font-size: 25px;
            font-weight: 700;
            margin-bottom: 7px;
            margin-top: 0;
        }

        .reservation-section p.subtitle {
            font-size: 15px;
            color: #666;
            margin-bottom: 40px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #333;
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
            background-color: #fff;
            border-radius: 8px;
            padding: 25px 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08); /* Stronger shadow */
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #ddd; /* Lighter border */
        }

        /* Left Side: Book Info */
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
        
        /* Status Tags (Pills) */
        .status-pill {
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        .status-active {
            color: #00796B; /* Teal Dark */
            background-color: #E0F2F1; /* Teal Light */
        }
        
        .status-expired {
            color: #C62828; /* Red Dark */
            background-color: #FFCDD2; /* Red Light */
        }

        /* Right Side: Actions */
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
            color: #E5A000; /* Orange accent for the date */
            font-weight: 700;
        }

        .cancel-btn {
            background-color: #E94343; /* Primary red color for cancellation */
            color: #fff; 
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .cancel-btn:hover {
            background-color: #D63939;
        }

        /* Alerts */
        .status-box {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            width: 100%;
            max-width: 1000px;
            font-weight: 600;
        }
        .status-success { background-color: #e8f5e9; color: #388e3c; }
        .status-error { background-color: #ffcdd2; color: #d32f2f; }
        
        /* Modal Styles (Inherited and refined) */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.6); 
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
            box-shadow: 0 5px 25px rgba(0,0,0,0.4);
            text-align: center;
        }
        
        .modal-content h3 {
            margin-top: 0;
            color: #E94343;
        }

        .modal-buttons {
            margin-top: 20px;
        }
        
        .modal-buttons button {
            padding: 10px 20px;
            margin: 0 10px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .confirm-cancel-btn {
            background-color: #E94343;
            color: #fff;
        }
        
        .modal-cancel-btn {
            background-color: #ddd;
            color: #333;
        }
        
        /* Responsive */
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
                <span class="nav-icon material-icons">menu</span>
                <span class="logo-text">📚 Smart Library</span>
            </div>
            
            <ul class="nav-list">
                <li class="nav-item"><a href="student.php">
                        <span class="nav-icon material-icons">dashboard</span>
                        <span class="text">Dashboard</span>
                    </a></li>
                <li class="nav-item"><a href="student_borrow.php">
                        <span class="nav-icon material-icons">local_library</span>
                        <span class="text">Books</span>
                    </a></li>
                <li class="nav-item active"><a href="student_reservation.php">
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

                <div class="reservation-section">
                    <h2>Manage Reservations</h2>
                    <p class="subtitle">Reserve books to make a reservation or manage your current queue.</p>

                    <?php if (!empty($status_message)): ?>
                        <div class="status-box <?php echo $error_type === 'success' ? 'status-success' : 'status-error'; ?>">
                            <?php echo htmlspecialchars($status_message); ?>
                        </div>
                    <?php endif; ?>

                    <div class="section-title">
                        Your Current Reservations (<?php echo count($reservations); ?>)
                    </div>

                    <div class="reservation-list">
                        <?php if (empty($reservations)): ?>
                            <p style="color: #666; font-style: italic; padding: 20px; background: #fff; border-radius: 8px; width: 100%;">
                                You have no active reservations.
                            </p>
                        <?php else: ?>
                            <?php foreach ($reservations as $res): 
                                $expiryDateObj = new DateTime($res['ExpiryDate']);
                                $reservationDateObj = new DateTime($res['ReservationDate']);
                                $now = new DateTime();
                                
                                $expiryFormatted = $expiryDateObj->format('M d, Y');
                                $reservedFormatted = $reservationDateObj->format('M d, Y');
                                
                                // Logic for checking expiry
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
                                        <span>•</span>
                                        <span class="status-pill <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                    </div>
                                </div>
                                
                                <div class="res-actions">
                                    <div class="res-expiry">
                                        Reservation expires: <span><?php echo $expiryFormatted; ?></span>
                                    </div>
                                    
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
        </div>
    </div>

    <div id="cancelReservationModal" class="modal">
        <div class="modal-content">
            <h3>Confirm Cancellation</h3>
            <p id="modalMessage">Are you sure you want to cancel this reservation? This action cannot be undone.</p>
            <div class="modal-buttons">
                <form id="cancelForm" method="POST">
                    <input type="hidden" name="cancel_id" id="modalReservationId">
                    <button type="submit" class="confirm-cancel-btn">Yes, Cancel It</button>
                    <button type="button" class="modal-cancel-btn" onclick="closeModal('cancelReservationModal')">Keep Reservation</button>
                </form>
            </div>
        </div>
    </div>


    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar-menu');
            const mainContentWrapper = document.getElementById('main-content-wrapper'); // Fixed ID
            
            sidebar.classList.toggle('active');
            mainContentWrapper.classList.toggle('pushed'); // Fixed class toggle
            
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
                
                // Manually set classes if they aren't already set on load
                if (!sidebar.classList.contains('active')) {
                    sidebar.classList.add('active');
                }
                if (!mainContentWrapper.classList.contains('pushed')) {
                    mainContentWrapper.classList.add('pushed');
                }
            }
        });

        // --- Modal Functions ---

        function openModal(modalId) { 
            document.getElementById(modalId).style.display = 'flex'; 
        }
        
        function closeModal(modalId) { 
            document.getElementById(modalId).style.display = 'none'; 
        }

        function openCancelModal(reservationId, bookTitle) {
            const modalMessage = document.getElementById('modalMessage');
            const reservationIdInput = document.getElementById('modalReservationId');
            
            modalMessage.innerHTML = `Are you sure you want to cancel your reservation for <b>${bookTitle}</b>? This action cannot be undone.`;
            reservationIdInput.value = reservationId;
            
            openModal('cancelReservationModal');
        }

        // Close modal when clicking outside of it
        window.onclick = function (event) {
            if (event.target.classList.contains('modal')) {
                closeModal(event.target.id);
            }
        }
    </script>
</body>
</html>