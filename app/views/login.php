<?php
session_start();

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../models/database.php';

$error_message = '';

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Sanitize and collect inputs
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = "Please enter both your username and password.";
    } else {
        try {
            // 2. Prepare the query: Fetch user by ONLY Username
            $stmt = $pdo->prepare("SELECT UserID, Name, Role, PasswordHash FROM Users WHERE Username = ?");
            $stmt->execute([$username]);
            $user_data = $stmt->fetch();

            if ($user_data) {
                // 3. Verify the submitted password against the stored hash
                if (password_verify($password, $user_data['PasswordHash'])) {

                    // Authentication successful!
                    $_SESSION['user_id'] = $user_data['UserID'];
                    $_SESSION['username'] = $username;
                    $_SESSION['name'] = $user_data['Name'];
                    $_SESSION['role'] = $user_data['Role']; // Role determined by the database

                    // 4. Determine redirection based on the authenticated role
                    $role = $_SESSION['role'];
                    $dashboard_url = '';

                    switch ($role) {
                        case 'Librarian':
                            $dashboard_url = '/views/librarian.php';
                            break;
                        case 'Staff':
                            $dashboard_url = '/views/staff.php';
                            break;
                        case 'Student':
                            $dashboard_url = '/views/student_teacher.php';
                            break;
                        case 'Teacher':
                            $dashboard_url = '/views/student_teacher.php';
                            break;
                        default:
                            $dashboard_url = '/views/login.php';
                            break;
                    }

                    // Perform the final redirection using BASE_URL
                    header("Location: " . BASE_URL . $dashboard_url);
                    exit();

                } else {
                    $error_message = "Invalid password.";
                }
            } else {
                $error_message = "User not found with the provided username.";
            }

        } catch (PDOException $e) {
            error_log("Login PDO Error: " . $e->getMessage());
            $error_message = "An internal error occurred. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Library Web System Login</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">

    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/login.css">
</head>

<body>
    <div class="page-canvas">
        <div class="info-panel">
            <h1><span class="logo-icon">📚</span>Smart Library<br>Web System</h1>
            <img class="system-image" src="/librarysystem/public/src/image.png" alt="Library System Illustration">
        </div>

        <div class="login-panel">
            <h2 class="welcome-header">Welcome!</h2>
            <p class="login-prompt">Login with your account</p>

            <?php
            // Display error message if authentication failed
            if (!empty($error_message)) {
                echo '<div class="error-message">' . htmlspecialchars($error_message) . '</div>';
            }
            ?>

            <form method="POST" action="login.php">
                <div class="form-group">
                    <span class="material-icons form-icon">person</span>
                    <input type="text" id="username" name="username" class="form-input" placeholder="Username" required>
                </div>

                <div class="form-group">
                    <span class="material-icons form-icon">lock</span>
                    <input type="password" id="password" name="password" class="form-input" placeholder="Password"
                        required>
                </div>

                <button type="submit" class="login-button">Login</button>
            </form>
        </div>
    </div>
</body>

</html>