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
                    $dueDate = '2025-12-11 23:59:59';

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

                    $status_message = "Book request approved.";
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

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/borrowing_requests.css">
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
                <p class="subtitle">Approve or reject book borrowing requests from students and teachers.</p>

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
                                        <td data-label="Borrower">
                                            <?php echo htmlspecialchars($request['BorrowerName']); ?>
                                            <small style="display: block; color: #999;">(<?php echo htmlspecialchars($request['BorrowerRole']); ?>)</small>
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

    <script src="<?php echo BASE_URL; ?>/public/js/main.js"></script>
</body>

</html>