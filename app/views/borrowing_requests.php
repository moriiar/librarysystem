<?php
// CRITICAL: Start Output Buffering
ob_start();
session_start();

// Authentication check: Must be Staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Staff') {
    header("Location: " . BASE_URL . "/views/login.php");
    ob_end_flush();
    exit();
}

require_once __DIR__ . '/../models/database.php';
require_once __DIR__ . '/../../config.php';

$staff_name = $_SESSION['name'] ?? 'Staff';
$pending_requests = [];
$status_message = '';
$error_type = '';
$staffID = $_SESSION['user_id']; // Staff member processing the request

// --- FUNCTION TO EXECUTE APPROVAL/REJECTION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $reservationID = filter_var($_POST['reservation_id'] ?? null, FILTER_VALIDATE_INT);
    $bookID = filter_var($_POST['book_id'] ?? null, FILTER_VALIDATE_INT);
    $action = $_POST['action'];

    if ($reservationID && $bookID) {
        try {
            $pdo->beginTransaction();

            if ($action === 'Approve') {
                // 1. Find an AVAILABLE COPY of the book to assign
                // Note: The system assumes the copy count was checked before reservation, 
                // but we lock one specific physical copy now.
                $stmt_find_copy = $pdo->prepare("SELECT CopyID FROM book_copy WHERE BookID = ? AND Status = 'Available' LIMIT 1 FOR UPDATE");
                $stmt_find_copy->execute([$bookID]);
                $assignedCopyID = $stmt_find_copy->fetchColumn();

                if ($assignedCopyID) {
                    $borrowDate = date('Y-m-d H:i:s');
                    $dueDate = date('Y-m-d H:i:s', strtotime('+7 days')); // Example: 7 Day Loan

                    // 2. Create the Borrowing Record (Move from Reservation to Loan)
                    $stmt_borrow = $pdo->prepare("INSERT INTO borrowing_record (UserID, CopyID, BookID, BorrowDate, DueDate, Status, ProcessedBy) 
                                                  SELECT UserID, ?, BookID, ?, ?, 'Borrowed', ? FROM reservation WHERE ReservationID = ?");
                    $stmt_borrow->execute([$assignedCopyID, $borrowDate, $dueDate, $staffID, $reservationID]);
                    $newBorrowID = $pdo->lastInsertId();

                    // 3. Mark the Physical Copy as Borrowed
                    $pdo->prepare("UPDATE book_copy SET Status = 'Borrowed' WHERE CopyID = ?")
                        ->execute([$assignedCopyID]);

                    // 4. Mark Reservation as Fulfilled
                    $pdo->prepare("UPDATE reservation SET Status = 'Fulfilled', FulfilledBy = ? WHERE ReservationID = ?")
                        ->execute([$newBorrowID, $reservationID]);

                    $status_message = "Request Approved. Copy #{$assignedCopyID} assigned.";
                    $error_type = 'success';

                } else {
                    $status_message = "Error: No physical copies are currently available to fulfill this request.";
                    $error_type = 'error';
                }

            } elseif ($action === 'Reject') {
                // 1. Mark Reservation as Cancelled
                $pdo->prepare("UPDATE reservation SET Status = 'Cancelled' WHERE ReservationID = ?")
                    ->execute([$reservationID]);

                $status_message = "Request #{$reservationID} REJECTED.";
                $error_type = 'error';
            }

            $pdo->commit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Borrow Action Error: " . $e->getMessage());
            $status_message = "Transaction failed: " . $e->getMessage();
            $error_type = 'error';
        }

        // Redirect to clear POST data and show message
        header("Location: borrowing_requests.php?msg=" . urlencode($status_message) . "&type={$error_type}");
        ob_end_flush();
        exit();
    }
}

// --- 3. Fetch Pending Requests (From Reservation Table) ---
try {
    // UPDATED: Querying 'reservation' table instead of 'borrowing_record'
    $sql = "
        SELECT 
            R.ReservationID, R.BookID, R.ReservationDate,
            BK.Title, BK.ISBN, 
            U.UserID, U.Name AS BorrowerName, U.Role AS BorrowerRole,
            (SELECT COUNT(BC.CopyID) FROM book_copy BC WHERE BC.BookID = R.BookID AND BC.Status = 'Available') AS CopiesAvailable
        FROM reservation R
        JOIN book BK ON R.BookID = BK.BookID
        JOIN users U ON R.UserID = U.UserID
        WHERE R.Status = 'Active'
        ORDER BY R.ReservationDate ASC
    ";

    $stmt = $pdo->query($sql);
    $pending_requests = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Borrow Requests Fetch Error: " . $e->getMessage());
    $status_message = "Database Error: Could not load pending requests.";
    $error_type = 'error';
}

// Handle Message Display on GET Request (after redirect)
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
    <title>Borrowing Requests</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">

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

        /* Layout Container */
        .container {
            display: flex;
            min-height: 100vh;
        }

        /* --- Collapsible Sidebar (Fixed Anchor) --- */
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
            flex-shrink: 0;
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
            white-space: nowrap;
        }

        .logo-text {
            opacity: 0;
            transition: opacity 0.1s ease;
            margin-left: 10px;
        }

        .sidebar.active .logo-text {
            opacity: 1;
        }

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
            white-space: nowrap;
        }

        .text {
            opacity: 0;
            transition: opacity 0.1s ease;
            margin-left: 5px;
        }

        .sidebar.active .text {
            opacity: 1;
        }

        .nav-item a:hover {
            background-color: #f0f0f0;
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
            white-space: nowrap;
        }

        .logout a:hover {
            background-color: #f0f0f0;
        }

        /* Main Content Area */
        .main-content {
            flex-grow: 1;
            padding: 30px 42px;
            min-height: 80vh;
            margin-left: 70px;
            transition: margin-left 0.5s ease;
        }

        .main-content.pushed {
            margin-left: 280px;
        }

        /* Borrow Requests Section */
        .borrowreq-section {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .borrowreq-section h2 {
            font-size: 25px;
            font-weight: bold;
            margin-bottom: 40px;
            margin-top: 30px;
            align-self: self-start;
        }

        /* --- Requests Table Card Styles --- */
        .requests-card {
            background-color: #fff;
            border-radius: 11px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            width: 90%;
            max-width: 1100px;
            overflow-x: auto;
        }

        .requests-card h3 {
            font-size: 18px;
            font-weight: 600;
            margin-top: 0;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        /* Status/Error Box */
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
            background-color: #e8f5e9;
            color: #388e3c;
        }

        .status-error {
            background-color: #ffcdd2;
            color: #d32f2f;
        }

        .hidden {
            opacity: 0;
            visibility: hidden;
            transition: 0.5s;
        }

        /* --- Table Styling (Functional & Responsive) --- */
        .requests-table {
            width: 100%;
            min-width: 850px;
            border-collapse: collapse;
            font-size: 14px;
        }

        .requests-table th,
        .requests-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }

        .requests-table th {
            background-color: #F7FCFC;
            color: #6C6C6C;
            font-weight: 600;
            text-transform: uppercase;
        }

        .requests-table tbody tr:hover {
            background-color: #FAFAFA;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 13px;
        }

        .status-pending {
            background-color: #fff3e0;
            color: #ff9800;
        }

        /* Action Buttons */
        .action-btn {
            padding: 7px 11px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.2s;
            font-size: 14px;
        }

        .approve-btn {
            background-color: #00A693;
            color: white;
            margin-right: 5px;
        }

        .approve-btn:hover {
            background-color: #00897B;
        }

        .reject-btn {
            background-color: #F44336;
            color: white;
        }

        .reject-btn:hover {
            background-color: #D32F2F;
        }

        /* Mobile/Responsive Styles */
        @media screen and (max-width: 768px) {
            .requests-card {
                padding: 15px;
            }

            /* Hide table header on small screens */
            .requests-table thead {
                display: none;
            }

            /* Make table body act like stacked cards */
            .requests-table,
            .requests-table tbody,
            .requests-table tr,
            .requests-table td {
                display: block;
                width: 100%;
            }

            /* Style rows as blocks */
            .requests-table tr {
                margin-bottom: 15px;
                border: 1px solid #eee;
                border-radius: 8px;
            }

            /* Style cells (td) as full-width elements */
            .requests-table td {
                text-align: right;
                padding-left: 50%;
                /* Give space for the pseudo-label */
                position: relative;
                border-bottom: 1px dashed #eee;
            }

            /* Create labels for mobile view using the column headers */
            .requests-table td:before {
                content: attr(data-label);
                position: absolute;
                left: 10px;
                font-weight: 600;
                color: #6C6C6C;
            }

            /* Adjust action button grouping */
            .requests-table td:last-child {
                text-align: center;
                padding-left: 15px;
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
                <li class="nav-item"><a href="staff.php">
                        <span class="nav-icon material-icons">dashboard</span>
                        <span class="text">Dashboard</span>
                    </a></li>
                <li class="nav-item active"><a href="borrowing_requests.php">
                        <span class="nav-icon material-icons">rule</span>
                        <span class="text">Borrowing Requests</span>
                    </a></li>
                <li class="nav-item"><a href="returning&clearance.php">
                        <span class="nav-icon material-icons">assignment_turned_in</span>
                        <span class="text">Returns & Clearance</span>
                    </a></li>
                <li class="nav-item"><a href="penalties.php">
                        <span class="nav-icon material-icons">monetization_on</span>
                        <span class="text">Penalties Management</span>
                    </a></li>
                <li class="nav-item"><a href="borrower_status.php">
                        <span class="nav-icon material-icons">person_search</span>
                        <span class="text">Borrower Status</span>
                    </a></li>
            </ul>
            <ul class="logout nav-list">
                <li class="nav-item"><a href="login.php">
                        <span class="nav-icon material-icons">logout</span>
                        <span class="text">Logout</span>
                    </a></li>
            </ul>
        </div>

        <div id="main-content-area" class="main-content">

            <div class="borrowreq-section">
                <h2>Manage Borrowing Requests</h2>

                <div class="requests-card">
                    <h3>Pending Requests (<?php echo count($pending_requests); ?>)</h3>

                    <?php if (empty($pending_requests)): ?>
                        <p style="text-align: center; color: #6C6C6C; padding: 30px;">
                            🎉 No pending requests.
                        </p>
                    <?php else: ?>
                        <table class="requests-table">
                            <thead>
                                <tr>
                                    <th>Res. ID</th>
                                    <th>Borrower</th>
                                    <th>Book Title (ISBN)</th>
                                    <th>Date Requested</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_requests as $request): ?>
                                    <tr>
                                        <td data-label="Request ID">#<?php echo htmlspecialchars($request['ReservationID']); ?>
                                        </td>
                                        <td data-label="Borrower">
                                            <?php echo htmlspecialchars($request['BorrowerName']); ?>
                                        </td>
                                        <td data-label="Book Details">
                                            <?php echo htmlspecialchars($request['Title']); ?>
                                            <small style="display: block; color: #999;">(ISBN:
                                                <?php echo htmlspecialchars($request['ISBN']); ?>)</small>
                                            <small style="display: block; color: #00A693;">Available:
                                                <?php echo $request['CopiesAvailable']; ?></small>
                                        </td>
                                        <td data-label="Date Requested">
                                            <?php echo (new DateTime($request['ReservationDate']))->format('M d, Y'); ?>
                                        </td>
                                        <td data-label="Status"><span class="status-badge status-pending">Pending</span></td>
                                        <td data-label="Actions">
                                            <form method="POST" style="display: inline-block;">
                                                <input type="hidden" name="reservation_id"
                                                    value="<?php echo htmlspecialchars($request['ReservationID']); ?>">
                                                <input type="hidden" name="book_id"
                                                    value="<?php echo htmlspecialchars($request['BookID']); ?>">
                                                <button type="submit" name="action" value="Approve"
                                                    class="action-btn approve-btn">Approve</button>
                                                <button type="submit" name="action" value="Reject"
                                                    class="action-btn reject-btn">Reject</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar-menu');
            const mainContent = document.getElementById('main-content-area');

            sidebar.classList.toggle('active');
            mainContent.classList.toggle('pushed');

            // Store state in local storage
            if (sidebar.classList.contains('active')) {
                localStorage.setItem('sidebarState', 'expanded');
            } else {
                localStorage.setItem('sidebarState', 'collapsed');
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const savedState = localStorage.getItem('sidebarState');
            const sidebar = document.getElementById('sidebar-menu');
            const mainContent = document.getElementById('main-content-area');
            const notification = document.getElementById('statusNotification');

            // Apply saved state only if it exists
            if (savedState === 'expanded') {
                sidebar.classList.add('active');
                mainContent.classList.add('pushed');
            }

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
    </script>
</body>

</html>