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

    // Pass POST data, FILES data, and User ID to the controller
    $result = $controller->addBook($_POST, $_FILES, $_SESSION['user_id']);

    $status_message = $result['message'];
    $error_type = $result['type'];

    if ($error_type === 'success') {
        $_POST = array(); // Clear form on success
    }
}
?>
<?php ob_end_flush(); ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add a New Book</title>

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
                <li class="nav-item"><a href="librarian.php">
                        <span class="nav-icon material-icons">dashboard</span>
                        <span class="text">Dashboard</span>
                    </a></li>
                <li class="nav-item"><a href="book_inventory.php">
                        <span class="nav-icon material-icons">inventory_2</span>
                        <span class="text">Book Inventory</span>
                    </a></li>
                <li class="nav-item active"><a href="add_book.php">
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

            <div class="addbook-section">
                <h2>Book Management</h2>

                <div class="form-card">
                    <div class="form-header-title">
                        <span class="material-icons form-icon">book</span>
                        Add New Book
                    </div>

                    <form action="add_book.php" method="POST" enctype="multipart/form-data">

                        <div class="form-group">
                            <label for="isbn" class="form-label">ISBN</label>
                            <div class="form-input-icon-wrapper">
                                <span class="material-icons form-input-icon">vpn_key</span>
                                <input type="text" id="isbn" name="isbn" class="form-input"
                                    placeholder="e.g., 978-0123456789" required
                                    value="<?php echo htmlspecialchars($_POST['isbn'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="title" class="form-label">Title</label>
                            <div class="form-input-icon-wrapper">
                                <span class="material-icons form-input-icon">title</span>
                                <input type="text" id="title" name="title" class="form-input" required
                                    value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="author" class="form-label">Author</label>
                            <div class="form-input-icon-wrapper">
                                <span class="material-icons form-input-icon">person</span>
                                <input type="text" id="author" name="author" class="form-input" required
                                    value="<?php echo htmlspecialchars($_POST['author'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="price" class="form-label">Price</label>
                            <div class="form-input-icon-wrapper">
                                <span class="material-icons form-input-icon">payments</span>
                                <input type="number" id="price" name="price" class="form-input" min="0.01" step="0.01"
                                    required value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="category" class="form-label">Category</label>
                            <div class="form-input-icon-wrapper">
                                <span class="material-icons form-input-icon">category</span>
                                <input type="text" id="category" name="category" class="form-input" required
                                    value="<?php echo htmlspecialchars($_POST['category'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="quantity" class="form-label">Quantity</label>
                            <div class="form-input-icon-wrapper">
                                <span class="material-icons form-input-icon">inventory</span>
                                <input type="number" id="quantity" name="quantity" class="form-input" min="1" required
                                    value="<?php echo htmlspecialchars($_POST['quantity'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="button-group">
                            <button type="submit" class="action-button">Add Book</button>
                            <button type="button" class="action-button cancel-button"
                                onclick="window.location.href='add_book.php'">Cancel</button>
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