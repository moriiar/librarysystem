<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Book</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">

    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <style>
        /* Global Styles */
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #F7FCFC; /* Requested background color */
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
            /* Initial Collapsed Width */
            padding: 30px 0;
            background-color: #fff;
            border-right: 1px solid #eee;
            box-shadow: 3px 0 9px rgba(0, 0, 0, 0.05);
            flex-shrink: 0;
            overflow-x: hidden;
            overflow-y: auto;
            transition: width 0.5s ease;
            /* Smooth expansion animation */
            white-space: nowrap;
        }

        .sidebar.active {
            width: 250px;
            /* Expanded Width (Toggled by JS) */
        }

        .logo {
            font-size: 19px;
            font-weight: bold;
            color: #000;
            padding: 0 23px 40px;
            display: flex;
            align-items: center;
            cursor: pointer;
            /* Indicate it's clickable */
            white-space: nowrap;
            /* Prevents logo text wrap/break */
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

        /* Dashboard Section */
        .dashboard-section {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center; 
        }
        
        .dashboard-section h2 {
            font-size: 25px;
            font-weight: bold;
            margin-bottom: 20px;
            margin-top: 20px;
            width: 100%;
            text-align: left;
        }


        /* --- Book Form Card Styles --- */

        .form-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            max-width: 650px; 
            width: 100%; 
        }

        /* Form Group and Input Styles */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-size: 16px;
            color: #333;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ccc;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 17px;
            outline: none;
            transition: border-color 0.3s;
        }

        .form-input:focus {
            border-color: #00bcd4;
        }

        /* Button Styling */
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .action-button {
            flex: 1;
            background-color: #00a89d;
            color: white;
            padding: 14px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            font-weight: 600;
            transition: background-color 0.3s;
        }

        .action-button:hover {
            background-color: #00897b;
        }
        
        .cancel-button {
            background-color: #e0e0e0;
            color: #333;
        }

        .cancel-button:hover {
            background-color: #ccc;
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

        <div class="main-content">
            
            <div class="dashboard-section">
                <h2>Update Existing Book Details</h2>

                <div class="form-card">
                    <form action="book_inventory.php" method="POST">
                        <div class="form-group">
                            <label for="isbn" class="form-label">ISBN</label>
                            <input type="text" id="isbn" name="isbn" class="form-input" placeholder="978-0123456789" required>
                        </div>

                        <div class="form-group">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" id="title" name="title" class="form-input" placeholder="The Art of Data Analysis" required>
                        </div>

                        <div class="form-group">
                            <label for="author" class="form-label">Author</label>
                            <input type="text" id="author" name="author" class="form-input" placeholder="J. Doe" required>
                        </div>

                        <div class="form-group">
                            <label for="price" class="form-label">Price</label>
                            <input type="number" id="price" name="price" class="form-input" placeholder="e.g., ₱200.00" min="0.01" step="0.01" required>
                        </div>

                        <div class="form-group">
                            <label for="quantity" class="form-label">Quantity</label>
                            <input type="number" id="quantity" name="quantity" class="form-input" placeholder="15" min="0" required>
                        </div>

                        <div class="button-group">
                            <button type="submit" class="action-button">Update Book</button>
                            
                            <button type="button" class="action-button cancel-button" onclick="window.location.href='book_inventory.php'">Cancel</button>
                        </div>
                    </form>
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