<?php
session_start();

// Redirect if user is not logged in or is not Staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Staff') {
    header("Location: " . BASE_URL . "/views/login.php");
    exit();
}

require_once __DIR__ . '/../models/database.php';
require_once __DIR__ . '/../../config.php';

// Include the new controller
require_once __DIR__ . '/../controllers/StaffController.php';

// --- Initialize Dashboard Variables ---
$staff_name = $_SESSION['name'] ?? 'Staff';
$error_message = '';

// Instantiate Controller and fetch data
$controller = new StaffController($pdo);
$dashboardData = $controller->getDashboardData();

// Extract data to variables for the View to use
$pendingRequests = $dashboardData['pendingRequests'];
$outstandingPenalties = $dashboardData['outstandingPenalties']; 
$recentActivity = $dashboardData['recentActivity'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff's Dashboard</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">

    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/staff.css">
</head>

<body>
    <div class="container">
        <div id="sidebar-menu" class="sidebar">
            <div class="logo" onclick="toggleSidebar()">
                <span class="hamburger-icon material-icons">menu</span>
                <span class="logo-text">📚 Smart Library</span>
            </div>

            <ul class="nav-list">
                <li class="nav-item active"><a href="staff.php">
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
            <div class="header">
                Welcome, <span><?php echo htmlspecialchars($staff_name); ?></span>
            </div>

            <div class="dashboard-section">
                <h2>Staff's Dashboard</h2>

                <div class="action-cards">
                    <div class="card">
                        <a href="borrowing_requests.php" class="card-link">Manage Requests</a>
                    </div>
                    <div class="card">
                        <a href="returning&clearance.php" class="card-link">Process Returns</a>
                    </div>
                    <div class="card">
                        <a href="penalties.php" class="card-link">Handle Penalties</a>
                    </div>
                    <div class="card">
                        <a href="borrower_status.php" class="card-link">Borrower Lookup</a>
                    </div>
                </div>

                <div class="overview-section">
                    <div class="overview-card">
                        <h3>Operational Overview</h3>

                        <div class="stats-grid">
                            <div class="stat-box">
                                <h4><?php echo $pendingRequests; ?></h4>
                                <p>Pending Borrow Requests</p>
                            </div>
                            <div class="stat-box">
                                <h4><?php echo $outstandingPenalties; ?></h4>
                                <p>Pending Penalties</p>
                            </div>
                        </div>

                        <h3>Recent Loan Activity</h3>

                        <table class="activity-table">
                            <thead>
                                <tr>
                                    <th style="width: 120px;">Time</th>
                                    <th>Action</th>
                                    <th>Book / Borrower</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recentActivity)): ?>
                                    <?php foreach ($recentActivity as $activity):
                                        $actionClass = strtolower($activity['ActionType']) === 'borrowed' ? 'activity-type-borrowed' : 'activity-type-returned';
                                        $actionText = htmlspecialchars($activity['ActionType']);
                                        $timeFormatted = (new DateTime($activity['ActionTimestamp']))->format('m-d-Y h:i:s');
                                        ?>
                                        <tr>
                                            <td><?php echo $timeFormatted; ?></td>
                                            <td><span
                                                    class="<?php echo $actionClass; ?>"><?php echo $actionText; ?></span>
                                            </td>
                                            <td>
                                                "<?php echo htmlspecialchars($activity['Title']); ?>"
                                                by <?php echo htmlspecialchars($activity['UserName']); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" style="text-align: center; color: #999;">No recent loan activity
                                            recorded.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
    
    <script src="<?php echo BASE_URL; ?>/public/js/main.js"></script>
</body>

</html>