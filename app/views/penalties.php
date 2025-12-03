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
$penalties = [];
$status_message = '';
$error_type = '';
$staffID = $_SESSION['user_id'];

// Search and Filter variables
$search_term = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? 'All'); // Default filter to All

// --- FUNCTION TO HANDLE COLLECT/WAIVE ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['penalty_id'])) {
    $penaltyID = filter_var($_POST['penalty_id'] ?? null, FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';

    if ($penaltyID) {
        try {
            $pdo->beginTransaction();

            if ($action === 'Collect') {
                // 1. Update Penalty Status to Paid and set PaidDate
                $pdo->prepare("UPDATE penalty SET Status = 'Paid', PaidDate = CURRENT_TIMESTAMP() WHERE PenaltyID = ? AND Status = 'Pending'")
                    ->execute([$penaltyID]);

                // 2. Insert a record into the Payment table
                $pdo->prepare("INSERT INTO payment (UserID, PenaltyID, Amount, Status) 
                               VALUES (?, ?, (SELECT AmountDue FROM penalty WHERE PenaltyID = ?), 'Completed')")
                    ->execute([$staffID, $penaltyID, $penaltyID]);

                $status_message = "Penalty #{$penaltyID} collected successfully and marked PAID.";
                $error_type = 'success';

            } elseif ($action === 'Waive') {
                // 1. Update Penalty Status to Waived
                $pdo->prepare("UPDATE penalty SET Status = 'Waived', PaidDate = CURRENT_TIMESTAMP() WHERE PenaltyID = ? AND Status = 'Pending'")
                    ->execute([$penaltyID]);

                $status_message = "Penalty #{$penaltyID} waived and closed.";
                $error_type = 'error'; // Use error/warning color for closure of liability
            }

            $pdo->commit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Penalty Action Error: " . $e->getMessage());
            $status_message = "Transaction failed. Database Error.";
            $error_type = 'error';
        }

        // Redirect to clear POST data and show message, preserving search/filter
        $params = '?msg=' . urlencode($status_message) . '&type=' . $error_type . '&status=' . urlencode($status_filter) . '&search=' . urlencode($search_term);
        header("Location: penalties.php" . $params);
        ob_end_flush();
        exit();
    }
}

// --- 2. FETCH PENALTIES (Dynamic Query) ---
try {
    $sql = "
        SELECT 
            P.PenaltyID, P.AmountDue, P.Status, P.IssuedDate,
            U.Name AS BorrowerName, U.Role AS BorrowerRole,
            BK.Title, BK.ISBN
        FROM penalty P
        JOIN users U ON P.UserID = U.UserID
        JOIN borrowing_record BO ON P.BorrowID = BO.BorrowID
        JOIN book_copy BCPY ON BO.CopyID = BCPY.CopyID 
        JOIN book BK ON BCPY.BookID = BK.BookID
        WHERE 1=1
    ";

    $params = [];
    $is_search = !empty($search_term);

    // Apply Status Filter
    if ($status_filter !== 'All') {
        $sql .= " AND P.Status = :status";
        $params[':status'] = $status_filter;
    }

    // Apply Search Filter
    if ($is_search) {
        // Use prepared statements securely for the search term
        $sql .= " AND (U.Name LIKE :search OR BK.Title LIKE :search OR BK.ISBN LIKE :search)";
        $params[':search'] = '%' . $search_term . '%';
    }

    $sql .= " ORDER BY P.IssuedDate DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $penalties = $stmt->fetchAll();

    // Handle message after redirect
    if (isset($_GET['msg'])) {
        $status_message = htmlspecialchars($_GET['msg']);
        $error_type = htmlspecialchars($_GET['type'] ?? 'success');
    }

} catch (PDOException $e) {
    error_log("Penalties Fetch Error: " . $e->getMessage());
    $status_message = "Database Error: Could not load penalty list.";
    $error_type = 'error';
}
?>
<?php ob_end_flush(); ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penalties Management</title>

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
                <li class="nav-item"><a href="staff.php">
                        <span class="nav-icon material-icons">dashboard</span>
                        <span class="text">Dashboard</span>
                    </a></li>
                <li class="nav-item"><a href="borrowing_requests.php">
                        <span class="nav-icon material-icons">rule</span>
                        <span class="text">Borrowing Requests</span>
                    </a></li>
                <li class="nav-item"><a href="returning&clearance.php">
                        <span class="nav-icon material-icons">assignment_turned_in</span>
                        <span class="text">Returns & Clearance</span>
                    </a></li>
                <li class="nav-item active"><a href="penalties.php">
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

            <div class="penalties-section">
                <h2>Handle Book Penalties</h2>
                <p class="subtitle">Track and manage outstanding penalties and fees.</p>

                <div class="penalties-card">
                    <form method="GET" action="penalties.php" class="search-form">

                        <div class="search-input-wrapper">
                            <input type="text" name="search" class="search-input form-input"
                                placeholder="Search by Borrower, Book Title, or ISBN..."
                                value="<?php echo htmlspecialchars($search_term); ?>">

                            <button type="submit" class="search-button" title="Search">
                                <span class="material-icons">search</span>
                            </button>
                        </div>

                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="All" <?php echo $status_filter === 'All' ? 'selected' : ''; ?>>Filter: All Statuses</option>
                            <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending
                            </option>
                            <option value="Paid" <?php echo $status_filter === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="Waived" <?php echo $status_filter === 'Waived' ? 'selected' : ''; ?>>Waived
                            </option>
                        </select>
                    </form>

                    <table class="penalties-table">
                        <thead>
                            <tr>
                                <th>Borrower</th>
                                <th>Book Title (ISBN)</th>
                                <th>Amount Due</th>
                                <th>Issued Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($penalties)): ?>
                                <?php foreach ($penalties as $penalty):
                                    $statusClass = strtolower($penalty['Status']);
                                    $isPending = $penalty['Status'] === 'Pending';
                                    ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($penalty['BorrowerName']); ?>
                                            <small
                                                style="display: block; color: #999;">(<?php echo htmlspecialchars($penalty['BorrowerRole']); ?>)</small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($penalty['Title']); ?>
                                            <small style="display: block; color: #999;">(ISBN:
                                                <?php echo htmlspecialchars($penalty['ISBN']); ?>)</small>
                                        </td>
                                        <td class="status-replacement">₱<?php echo number_format($penalty['AmountDue'], 2); ?>
                                        </td>
                                        <td><?php echo (new DateTime($penalty['IssuedDate']))->format('M d, Y'); ?></td>
                                        <td><span
                                                class="status-badge status-<?php echo $statusClass; ?>"><?php echo htmlspecialchars($penalty['Status']); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($isPending): ?>
                                                <form method="POST" style="display: inline-block;">
                                                    <input type="hidden" name="penalty_id"
                                                        value="<?php echo htmlspecialchars($penalty['PenaltyID']); ?>">
                                                    <button type="submit" name="action" value="Collect"
                                                        class="action-btn collect-btn">Collect Fee</button>
                                                    <button type="submit" name="action" value="Waive"
                                                        class="action-btn waive-btn">Waive</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: #999; padding: 20px;">No penalties found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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