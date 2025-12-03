<?php
ob_start();
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Librarian') {
    header("Location: " . BASE_URL . "/views/login.php");
    ob_end_flush();
    exit();
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../models/database.php';
// Include the new controller
require_once __DIR__ . '/../controllers/BookController.php';

$status_message = '';
$error_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new BookController($pdo);

    $result = $controller->archiveBook($_POST, $_SESSION['user_id']);

    $status_message = $result['message'];
    $error_type = $result['type'];
}
?>
<?php ob_end_flush(); ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive a Book</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">

    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/archive_book.css">
</head>

<body>
    <div class="container">
        <div id="sidebar-menu" class="sidebar">
            <div class="logo" onclick="toggleSidebar()">
                <span class="hamburger-icon material-icons">menu</span>
                <span class="logo-text">📚 Smart Library</span>
            </div>

            <ul class="nav-list">
                <li class="nav-item"><a href="librarian.php">
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
                <li class="nav-item active"><a href="archive_book.php">
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

            <div class="archivebook-section">
                <h2>Book Management</h2>

                <div class="form-card">
                    <div class="form-header-title">
                        <span class="material-icons form-icon" style="color: #d32f2f;">delete</span>
                        Archive Book Permanently
                    </div>

                    <div class="warning-text">
                        Warning: Archiving a book removes it from the search catalog. This action cannot be easily
                        undone.
                    </div>

                    <form action="archive_book.php" method="POST">

                        <div class="form-group">
                            <label for="isbn" class="form-label">Book ISBN</label>
                            <div class="form-input-icon-wrapper">
                                <span class="material-icons form-input-icon">vpn_key</span>
                                <input type="text" id="isbn" name="isbn" class="form-input"
                                    placeholder="Enter ISBN to archive" required
                                    value="<?php echo htmlspecialchars($_POST['isbn'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="reason" class="form-label">Reason for Archiving</label>
                            <div class="form-input-icon-wrapper">
                                <span class="material-icons form-input-icon">info</span>
                                <select id="reason" name="reason" class="form-select" required>
                                    <option value="" disabled selected>Select a reason</option>
                                    <option value="lost">Lost</option>
                                    <option value="damaged">Damaged/Unrepairable</option>
                                    <option value="retired">Retired/Outdated Edition</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="button-group">
                            <button type="submit" class="action-button archive-button">Archive Book Permanently</button>

                            <button type="button" class="action-button cancel-button"
                                onclick="window.location.href='archive_book.php'">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (!empty($status_message)): ?>
                <div id="statusNotification"
                    class="status-box <?php echo ($error_type === 'success' ? 'status-success' : 'status-error'); ?>">
                    <?php echo htmlspecialchars($status_message); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>/public/js/main.js"></script>
</body>

</html>