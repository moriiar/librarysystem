<?php
session_start();

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Librarian') {
    header("Location: " . BASE_URL . "/views/login.php");
    exit();
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../models/database.php';
// Include the new controller
require_once __DIR__ . '/../controllers/LibrarianController.php';

$librarian_name = $_SESSION['name'] ?? 'Librarian';

// Instantiate Controller and fetch data
$controller = new LibrarianController($pdo);
$dashboardData = $controller->getDashboardData();

// Extract data to variables for the View to use
$totalBooks = $dashboardData['totalBooks'];
$copiesAvailable = $dashboardData['copiesAvailable'];
$recentActivity = $dashboardData['recentActivity'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Librarian's Dashboard</title>

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
                <li class="nav-item active"><a href="librarian.php">
                        <span class="nav-icon material-icons">dashboard</span>
                        <span class="text">Dashboard</span>
                    </a></li>
                <li class="nav-item"><a href="book_inventory.php">
                        <span class="nav-icon material-icons">inventory_2</span>
                        <span class="text">Book Inventory</span>
                    </a></li>
                <li class="nav-item"><a href="add_book.php">
                        <span class="nav-icon material-icons">add_box</span>
                        <span class="text">Add New Book</span>
                    </a></li>
                <li class="nav-item"><a href="update_book.php">
                        <span class="nav-icon material-icons">edit</span>
                        <span class="text">Update Book</span>
                    </a></li>
                <li class="nav-item"><a href="archive_book.php">
                        <span class="nav-icon material-icons">archive</span>
                        <span class="text">Archive Book</span>
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
                Welcome, <span><?php echo htmlspecialchars($_SESSION['name'] ?? '[Librarian]'); ?></span>
            </div>

            <div class="dashboard-section">
                <h2>Librarian's Dashboard</h2>

                <div class="action-cards">
                    <div class="card">
                        <a href="book_inventory.php" class="card-link">Book Inventory</a>
                    </div>
                    <div class="card">
                        <a href="add_book.php" class="card-link">Add Book</a>
                    </div>
                    <div class="card">
                        <a href="update_book.php" class="card-link">Update Book</a>
                    </div>
                    <div class="card">
                        <a href="archive_book.php" class="card-link">Archive Book</a>
                    </div>
                </div>

                <div class="overview-section">
                    <div class="overview-card">
                        <h3>Library Overview</h3>

                        <div class="stats-grid">
                            <div class="stat-box">
                                <h4><?php echo $totalBooks; ?></h4>
                                <p>Total Books</p>
                            </div>
                            <div class="stat-box">
                                <h4><?php echo $copiesAvailable; ?></h4>
                                <p>Copies Available</p>
                            </div>
                        </div>

                        <h3>Recent Book Management Activity</h3>

                        <table class="activity-table">
                            <thead>
                                <tr>
                                    <th style="width: 120px;">Time</th>
                                    <th>Action</th>
                                    <th>Book Title</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recentActivity)): ?>
                                    <?php foreach ($recentActivity as $activity):
                                        // Determine class for visual feedback based on the action type
                                        $actionClass = '';
                                        if ($activity['ActionType'] === 'Added') {
                                            $actionClass = 'activity-type-added';
                                        } elseif ($activity['ActionType'] === 'Updated') {
                                            $actionClass = 'activity-type-updated';
                                        } elseif ($activity['ActionType'] === 'Archived') {
                                            $actionClass = 'activity-type-archived';
                                        }

                                        $timeFormatted = (new DateTime($activity['Timestamp']))->format('m-d-Y h:i:s'); // Full timestamp for precision
                                        ?>
                                        <tr>
                                            <td><?php echo $timeFormatted; ?></td>
                                            <td><span
                                                    class="<?php echo $actionClass; ?>"><?php echo htmlspecialchars($activity['ActionType']); ?></span>
                                            </td>
                                            <td>
                                                "<?php echo htmlspecialchars($activity['Title']); ?>"
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" style="text-align: center; color: #999;">No recent management
                                            activity recorded.</td>
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