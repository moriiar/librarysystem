<?php
session_start();

// Redirect if user is not logged in or is not a Librarian
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Librarian') {
    header("Location: " . BASE_URL . "/views/login.php");
    exit();
}

require_once __DIR__ . '/../models/database.php';
require_once __DIR__ . '/../../config.php';

// --- Initialize Dashboard Variables ---
$librarian_name = $_SESSION['name'] ?? 'Librarian';
$totalBooks = 'N/A';
$copiesAvailable = 'N/A';

try {
    // 1. Get total distinct books
    $stmt1 = $pdo->query("SELECT COUNT(BookID) AS total FROM Book WHERE Status != 'Archived'");
    $stats1 = $stmt1->fetch();
    $totalBooks = $stats1['total'];

    // 2. Get total copies available
    $stmt2 = $pdo->query("SELECT SUM(CopiesAvailable) AS available FROM Book WHERE Status != 'Archived'");
    $stats2 = $stmt2->fetch();
    $copiesAvailable = $stats2['available'] ?? 0;

} catch (PDOException $e) {
    // Handle error gracefully
    error_log("Dashboard Stats Error: " . $e->getMessage());
    $error_message = "Could not load book statistics.";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Librarian's Dashboard</title>

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
            padding: 30px 0;
            background-color: #fff;
            border-right: 1px solid #eee;
            box-shadow: 3px 0 9px rgba(0, 0, 0, 0.05);
            position: relative; /* Back in document flow */
            height: 100%; /* Ensure it matches height of container content */
            min-height: 100vh; /* Minimum screen height */
            flex-shrink: 0;
            overflow-x: hidden;
            overflow-y: auto;
            transition: width 0.3s ease; /* Smooth toggle animation */
            white-space: nowrap;
        }

        .sidebar.active {
            width: 250px;
            /* Expanded Width (Toggled by JS) */
        }

        .main-content {
            flex-grow: 1;
            padding: 30px 32px;
            /* CRITICAL: Use transition for smooth push/pull effect on content */
            transition: margin-left 0.3s ease; 
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
            padding: 30px 32px;
        }

        /* Header/Welcome Message */
        .header {
            text-align: right;
            padding-bottom: 20px;
            font-size: 16px;
            color: #666;
        }

        .header span {
            font-weight: bold;
            color: #333;
        }

        /* Dashboard Section */
        .dashboard-section h2 {
            font-size: 25px;
            font-weight: bold;
            margin-bottom: 20px;
            margin-top: -7px;
        }

        /* Action Cards */
        .action-cards {
            display: flex;
            gap: 30px;
            margin-bottom: 25px;
        }

        .card {
            flex: 1;
            max-width: 218px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            min-height: 80px;
            /* To match the image's card height */
            display: flex;
        }

        /* Card Link Style (The clickable area) */
        .card-link {
            display: flex;
            align-items: center;
            /* Vertically center content */
            justify-content: center;
            /* Horizontally center content */
            text-decoration: none;
            color: #333;
            font-weight: 550;
            font-size: 16px;
            /* Slightly larger text */
            padding: 25px;
            flex-grow: 2;
            /* Makes the link fill the card */
            transition: background-color 0.2s, box-shadow 0.2s;
        }

        /* Hover Effect for Clickable Card */
        .card-link:hover {
            background-color: #F0F8F8;
            /* Light teal hover color */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .overview-section {
            max-width: 960px;
            /* Use max-width for responsiveness */
        }

        .overview-section h3 {
            font-size: 18px;
            font-weight: 600;
            margin-top: 1px;
            margin-bottom: 10px;
        }

        /* Overview Card Style */
        .overview-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            min-height: 250px;
            margin-bottom: 20px;
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

        <div class="main-content">
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
                        <h3>Overview</h3>
                        <p>Total Book Titles: <b><?php echo $totalBooks; ?></b></p>
                        <p>Total Copies Available: <b><?php echo $copiesAvailable; ?></b></p>
                    </div>
                </div>

            </div>

            <script>
                function toggleSidebar() {
                        const sidebar = document.getElementById('sidebar-menu');
                        sidebar.classList.toggle('active');

                        // Optional: Store state in local storage to remember setting across page reloads
                        if (sidebar.classList.contains('active')) {
                            localStorage.setItem('sidebarState', 'expanded');
                        } else {
                            localStorage.setItem('sidebarState', 'collapsed');
                        }
                    }

                    // Optional: Re-apply state on page load if using localStorage
                    document.addEventListener('DOMContentLoaded', () => {
                    const savedState = localStorage.getItem('sidebarState');
                    const sidebar = document.getElementById('sidebar-menu');
                        if (savedState === 'expanded') {
                            sidebar.classList.add('active');
                        }
                    });

            </script>
        </div>
    </div>

</body>

</html>