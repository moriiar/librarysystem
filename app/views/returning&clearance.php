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
$staffID = $_SESSION['user_id'];

// Messages
$status_message = '';
$error_type = '';

// State Variables
$user_search = trim($_GET['user_search'] ?? '');
$selected_borrow_id = filter_input(INPUT_GET, 'select_loan', FILTER_VALIDATE_INT);

$user_details = null;
$active_loans = [];
$selected_loan = null;

// ===========================================
// 1. HANDLE POST (FINALIZE TRANSACTION)
// ===========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'finalize_transaction') {

    $borrowID = filter_var($_POST['borrow_id'] ?? null, FILTER_VALIDATE_INT);
    $copyID = filter_var($_POST['copy_id'] ?? null, FILTER_VALIDATE_INT);
    $userID = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    $processType = $_POST['process_type'] ?? 'return'; // 'return' or 'lost'

    $penaltyAmount = filter_var($_POST['penalty_amount'] ?? 0.00, FILTER_VALIDATE_FLOAT);
    $paymentStatus = $_POST['payment_status'] ?? 'Pending';
    $condition = $_POST['condition'] ?? 'Good';

    if ($borrowID && $copyID && $userID) {
        try {
            $pdo->beginTransaction();
            $timestamp = date('Y-m-d H:i:s');

            if ($processType === 'return') {
                // --- PROCESS RETURN ---

                // 1. Update Borrow Record
                $stmt = $pdo->prepare("UPDATE borrowing_record SET Status = 'Returned', ReturnDate = ?, ProcessedBy = ? WHERE BorrowID = ?");
                $stmt->execute([$timestamp, $staffID, $borrowID]);

                // 2. Update Copy Status
                // If condition is 'Major Damage', mark as Damaged, else Available
                $newStatus = ($condition === 'Major Damage') ? 'Damaged' : 'Available';
                $pdo->prepare("UPDATE book_copy SET Status = ? WHERE CopyID = ?")->execute([$newStatus, $copyID]);

                $successMsg = "Book returned successfully.";

            } elseif ($processType === 'lost') {
                // --- PROCESS LOST ---

                // 1. Update Borrow Record
                $stmt = $pdo->prepare("UPDATE borrowing_record SET Status = 'Lost', ReturnDate = NULL, ProcessedBy = ? WHERE BorrowID = ?");
                $stmt->execute([$staffID, $borrowID]);

                // 2. Update Copy Status
                $pdo->prepare("UPDATE book_copy SET Status = 'Lost' WHERE CopyID = ?")->execute([$copyID]);

                $successMsg = "Book marked as LOST.";
            }

            // 3. Handle Penalty (Common for both)
            if ($penaltyAmount > 0) {
                // Insert Penalty
                $stmt = $pdo->prepare("INSERT INTO penalty (BorrowID, UserID, AmountDue, Status, IssuedDate) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$borrowID, $userID, $penaltyAmount, ($paymentStatus === 'Paid' ? 'Paid' : 'Pending'), $timestamp]);

                // Optional: Insert into Payment table if Paid immediately
                if ($paymentStatus === 'Paid') {
                    $penaltyID = $pdo->lastInsertId();
                    $pdo->prepare("INSERT INTO payment (UserID, PenaltyID, Amount, Status, PaymentDate) VALUES (?, ?, ?, 'Completed', ?)")
                        ->execute([$userID, $penaltyID, $penaltyAmount, $timestamp]);
                }

                $successMsg .= " Penalty of ₱" . number_format($penaltyAmount, 2) . " recorded ($paymentStatus).";
            }

            $pdo->commit();

            // Redirect to same user search to refresh list
            $redirectUrl = "returning&clearance.php?msg=" . urlencode($successMsg) . "&type=success&user_search=" . urlencode($_POST['redirect_search']);
            header("Location: " . $redirectUrl);
            ob_end_flush();
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            $status_message = "Error processing transaction: " . $e->getMessage();
            $error_type = 'error';
        }
    }
}

// ===========================================
// 2. SEARCH LOGIC (GET)
// ===========================================
if (!empty($user_search)) {
    try {
        // A. Find User
        $stmtUser = $pdo->prepare("SELECT UserID, Name, Role, Email FROM users WHERE Name LIKE ? OR UserID = ? LIMIT 1");
        $stmtUser->execute(['%' . $user_search . '%', $user_search]);
        $user_details = $stmtUser->fetch();

        if ($user_details) {
            // B. Find Active Loans for User
            // Status 'Borrowed' or 'Overdue' (assuming Overdue is a valid enum or handled by logic)
            // Note: Your schema uses 'Borrowed', 'Returned', 'Overdue', 'Lost'. 
            // We fetch 'Borrowed' and 'Overdue' to show active items.
            $sqlLoans = "
                SELECT 
                    BO.BorrowID, BO.CopyID, BO.BorrowDate, BO.DueDate, BO.Status,
                    BK.Title, BK.ISBN, BK.Price
                FROM borrowing_record BO
                JOIN book_copy BC ON BO.CopyID = BC.CopyID
                JOIN book BK ON BC.BookID = BK.BookID
                WHERE BO.UserID = ? AND BO.Status IN ('Borrowed', 'Overdue')
                ORDER BY BO.DueDate ASC
            ";
            $stmtLoans = $pdo->prepare($sqlLoans);
            $stmtLoans->execute([$user_details['UserID']]);
            $active_loans = $stmtLoans->fetchAll();

            // C. If a specific loan was selected, grab it from the array
            if ($selected_borrow_id) {
                foreach ($active_loans as $loan) {
                    if ($loan['BorrowID'] == $selected_borrow_id) {
                        $selected_loan = $loan;
                        break;
                    }
                }
            }
        } else {
            $status_message = "User not found.";
            $error_type = 'error';
        }
    } catch (PDOException $e) {
        $status_message = "Database Error: " . $e->getMessage();
        $error_type = 'error';
    }
}

// Handle URL Messages
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
    <title>Returning & Clearance</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">

    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/styles.css">
</head>

<body>
    <div class="container">
        <!-- Sidebar -->
        <div id="sidebar-menu" class="sidebar">
            <div class="logo" onclick="toggleSidebar()">
                <span class="hamburger-icon material-icons">menu</span>
                <span class="logo-text">📚 Smart Library</span>
            </div>
            <ul class="nav-list">
                <li class="nav-item"><a href="staff.php"><span class="nav-icon material-icons">dashboard</span><span
                            class="text">Dashboard</span></a></li>
                <li class="nav-item"><a href="borrowing_requests.php"><span
                            class="nav-icon material-icons">rule</span><span class="text">Borrowing Requests</span></a>
                </li>
                <li class="nav-item active"><a href="returning&clearance.php"><span
                            class="nav-icon material-icons">assignment_turned_in</span><span class="text">Returns &
                            Clearance</span></a></li>
                <li class="nav-item"><a href="penalties.php"><span
                            class="nav-icon material-icons">monetization_on</span><span class="text">Penalties
                            Management</span></a></li>
                <li class="nav-item"><a href="borrower_status.php"><span
                            class="nav-icon material-icons">person_search</span><span class="text">Borrower
                            Status</span></a></li>
            </ul>
            <div class="logout"><a href="login.php"><span class="nav-icon material-icons">logout</span><span
                        class="text">Logout</span></a></div>
        </div>

        <!-- Main Content -->
        <div id="main-content-area" class="main-content">
            <h2>Returns & Clearance</h2>
            <p class="subtitle">Process book returns and manage borrower clearances efficiently.</p>

            <div class="process-container">
                <!-- LEFT PANEL: SEARCH & LIST -->
                <div class="left-panel">
                    <div class="card">
                        <div class="card-header">
                            <span class="material-icons">person_search</span> 1. Find Borrower
                        </div>
                        <form method="GET" class="search-group">
                            <input type="text" name="user_search" class="search-input"
                                placeholder="Enter Student Name or ID..."
                                value="<?php echo htmlspecialchars($user_search); ?>" required>
                            <button type="submit" class="search-btn">Search</button>
                        </form>
                    </div>

                    <?php if ($user_details): ?>
                        <div class="user-meta">
                            <div>
                                <h3><?php echo htmlspecialchars($user_details['Name']); ?></h3>
                                <p>ID: <?php echo htmlspecialchars($user_details['UserID']); ?> | Email:
                                    <?php echo htmlspecialchars($user_details['Email']); ?>
                                </p>
                            </div>
                            <span class="role-badge"><?php echo htmlspecialchars($user_details['Role']); ?></span>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <span class="material-icons">list_alt</span> Active Books
                                (<?php echo count($active_loans); ?>)
                            </div>
                            <?php if (count($active_loans) > 0): ?>
                                <table class="loans-table">
                                    <thead>
                                        <tr>
                                            <th>Book Title</th>
                                            <th>Due Date</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($active_loans as $loan):
                                            $isOverdue = new DateTime() > new DateTime($loan['DueDate']);
                                            $rowClass = ($selected_borrow_id == $loan['BorrowID']) ? 'selected-row' : '';
                                            ?>
                                            <tr class="<?php echo $rowClass; ?>">
                                                <td>
                                                    <?php echo htmlspecialchars($loan['Title']); ?><br>
                                                    <small
                                                        style="color:#999;"><?php echo htmlspecialchars($loan['ISBN']); ?></small>
                                                </td>
                                                <td class="<?php echo $isOverdue ? 'status-overdue' : ''; ?>">
                                                    <?php echo (new DateTime($loan['DueDate']))->format('M d, Y'); ?>
                                                </td>
                                                <td>
                                                    <?php echo $isOverdue ? 'Overdue' : 'Borrowed'; ?>
                                                </td>
                                                <td>
                                                    <a href="?user_search=<?php echo urlencode($user_search); ?>&select_loan=<?php echo $loan['BorrowID']; ?>"
                                                        class="select-btn">
                                                        Select
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p style="color: #999; text-align:center;">This user has no active borrowed books.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- RIGHT PANEL: FINALIZE -->
                <div class="right-panel">
                    <div class="card" style="min-height: 400px;">
                        <div class="card-header">
                            <span class="material-icons">verified</span> 2. Finalize Clearance
                        </div>

                        <?php if ($selected_loan):
                            $dueDate = new DateTime($selected_loan['DueDate']);
                            $today = new DateTime();
                            $isOverdue = $today > $dueDate;
                            $bookPrice = (float) $selected_loan['Price'];

                            // Penalty Logic: If overdue, full price, else 0
                            $initialPenalty = $isOverdue ? $bookPrice : 0.00;
                            ?>
                            <div style="margin-bottom: 20px;">
                                <strong>Selected Book:</strong><br>
                                <span
                                    style="font-size: 1.2em; color: #00A693;"><?php echo htmlspecialchars($selected_loan['Title']); ?></span>
                                <br><small>ISBN: <?php echo htmlspecialchars($selected_loan['ISBN']); ?></small>
                            </div>

                            <form method="POST" class="finalize-form" id="clearanceForm">
                                <input type="hidden" name="action" value="finalize_transaction">
                                <input type="hidden" name="redirect_search"
                                    value="<?php echo htmlspecialchars($user_search); ?>">
                                <input type="hidden" name="borrow_id" value="<?php echo $selected_loan['BorrowID']; ?>">
                                <input type="hidden" name="copy_id" value="<?php echo $selected_loan['CopyID']; ?>">
                                <input type="hidden" name="user_id" value="<?php echo $user_details['UserID']; ?>">

                                <!-- Hidden fields to store raw prices for JS calculation -->
                                <input type="hidden" id="raw_book_price" value="<?php echo $bookPrice; ?>">
                                <input type="hidden" id="raw_initial_penalty" value="<?php echo $initialPenalty; ?>">

                                <div class="form-group">
                                    <label>Processing Type</label>
                                    <select name="process_type" id="processType" onchange="updateFormLogic()">
                                        <option value="return">Return Book</option>
                                        <option value="lost">Mark as Lost</option>
                                    </select>
                                </div>

                                <div class="form-group" id="conditionGroup">
                                    <label>Book Condition</label>
                                    <select name="condition" id="conditionSelect" onchange="updateFormLogic()">
                                        <option value="Good">Good / Normal Wear</option>
                                        <option value="Minor Damage">Minor Damage</option>
                                        <option value="Major Damage">Major Damage (Requires Replacement)</option>
                                    </select>
                                </div>

                                <div class="fee-display active" id="feeBox">
                                    Calculated Fee: ₱<span
                                        id="displayFee"><?php echo number_format($initialPenalty, 2); ?></span>
                                    <input type="hidden" name="penalty_amount" id="inputPenalty"
                                        value="<?php echo $initialPenalty; ?>">
                                </div>

                                <div class="form-group" id="paymentGroup"
                                    style="<?php echo $initialPenalty > 0 ? 'display:block' : 'display:none'; ?>">
                                    <label>Payment Status</label>
                                    <select name="payment_status">
                                        <option value="Pending">Pending (Add to Account)</option>
                                        <option value="Paid">Paid Now (Cash/GCash)</option>
                                    </select>
                                </div>

                                <button type="submit" id="submitBtn" class="submit-btn btn-return">Confirm Return</button>
                            </form>

                        <?php else: ?>
                            <div style="text-align: center; color: #999; margin-top: 100px;">
                                <span class="material-icons" style="font-size: 48px; color: #eee;">library_books</span><br>
                                Select a book from the list<br>to process return or report lost.
                            </div>
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

    <script src="<?php echo BASE_URL; ?>/public/js/main.js"></script>
</body>

</html>