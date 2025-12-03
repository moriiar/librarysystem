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
$current_book = null;

$controller = new BookController($pdo);

// 1. Handle Update Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $controller->updateBook($_POST, $_SESSION['user_id']);
    $status_message = $result['message'];
    $error_type = $result['type'];

    // Maintain the form state
    $lookup_isbn = $_POST['isbn'] ?? '';
    if (!empty($lookup_isbn)) {
        $current_book = $controller->getBookByISBN($lookup_isbn);
    }
}

// 2. Handle Initial Lookup (GET)
if (isset($_GET['isbn'])) {
    $lookup_isbn = trim($_GET['isbn']);
    $current_book = $controller->getBookByISBN($lookup_isbn);

    if (!$current_book) {
        $status_message = "Error: Book with ISBN '{$lookup_isbn}' not found or archived.";
        $error_type = 'error';
    }
}
?>
<?php ob_end_flush(); ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Book</title>

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
                <li class="nav-item active"><a href="update_book.php">
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

            <div class="updatebook-section">
                <h2>Book Management</h2>

                <div class="form-card">
                    <div class="form-header-title">
                        <span class="material-icons form-icon">edit_square</span>
                        Update Book Details
                    </div>

                    <?php if (!$current_book && empty($_POST['isbn'])): ?>
                        <form action="update_book.php" method="GET">
                            <p style="color: #666; margin-bottom: 15px;">Enter the ISBN of the book you want to update:</p>
                            <div class="form-group">
                                <div class="form-input-icon-wrapper">
                                    <span class="material-icons form-input-icon">search</span>
                                    <input type="text" name="isbn" class="form-input"
                                        placeholder="Enter ISBN (e.g., 978-0123456789)" required>
                                </div>
                            </div>
                            <button type="submit" class="action-button" style="width: 100%;">Search Book</button>
                        </form>

                    <?php else: ?>
                        <p style="color: #666; margin-bottom: 15px;">Editing:
                            **<?php echo htmlspecialchars($current_book['Title'] ?? 'Book Not Found'); ?>** (ISBN:
                            <?php echo htmlspecialchars($current_book['ISBN'] ?? ''); ?>)
                        </p>

                        <form action="update_book.php" method="POST">
                            <input type="hidden" name="book_id"
                                value="<?php echo htmlspecialchars($current_book['BookID'] ?? ''); ?>">
                            <input type="hidden" name="isbn"
                                value="<?php echo htmlspecialchars($current_book['ISBN'] ?? $_POST['isbn'] ?? ''); ?>">

                            <div class="form-group">
                                <label for="isbn_display" class="form-label">ISBN (Read-Only)</label>
                                <div class="form-input-icon-wrapper">
                                    <span class="material-icons form-input-icon">vpn_key_off</span>
                                    <input type="text" id="isbn_display" class="form-input" readonly
                                        style="background-color: #f0f0f0;"
                                        value="<?php echo htmlspecialchars($current_book['ISBN'] ?? $_POST['isbn'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="title" class="form-label">Title</label>
                                <div class="form-input-icon-wrapper">
                                    <span class="material-icons form-input-icon">title</span>
                                    <input type="text" id="title" name="title" class="form-input" required
                                        value="<?php echo htmlspecialchars($current_book['Title'] ?? $_POST['title'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="author" class="form-label">Author</label>
                                <div class="form-input-icon-wrapper">
                                    <span class="material-icons form-input-icon">person</span>
                                    <input type="text" id="author" name="author" class="form-input" required
                                        value="<?php echo htmlspecialchars($current_book['Author'] ?? $_POST['author'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="price" class="form-label">Price</label>
                                <div class="form-input-icon-wrapper">
                                    <span class="material-icons form-input-icon">payments</span>
                                    <input type="number" id="price" name="price" class="form-input" min="0.01" step="0.01"
                                        required
                                        value="<?php echo htmlspecialchars($current_book['Price'] ?? $_POST['price'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="category" class="form-label">Category</label>
                                <div class="form-input-icon-wrapper">
                                    <span class="material-icons form-input-icon">category</span>
                                    <input type="text" id="category" name="category" class="form-input" required
                                        value="<?php echo htmlspecialchars($current_book['Category'] ?? $_POST['category'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="quantity" class="form-label">Total Copies</label>
                                <div class="form-input-icon-wrapper">
                                    <span class="material-icons form-input-icon">inventory</span>
                                    <input type="number" id="quantity" name="quantity" class="form-input" min="0" required
                                        value="<?php echo htmlspecialchars($current_book['CopiesTotal'] ?? $_POST['quantity'] ?? ''); ?>">
                                </div>
                                <small style="color: #666; font-size: 13px;">Currently Available:
                                    **<?php echo $current_book['CopiesAvailable'] ?? 'N/A'; ?>**</small>
                            </div>

                            <div class="button-group">
                                <button type="submit" class="action-button">Update Book</button>
                                <button type="button" class="action-button cancel-button"
                                    onclick="window.location.href='update_book.php'">Cancel</button>
                            </div>
                        </form>
                    <?php endif; ?>
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