<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive a Book</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">

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

        /* Sidebar Navigation */
        .sidebar {
            width: 250px;
            padding: 30px 0;
            background-color: #fff;
            border-right: 1px solid #eee;
            box-shadow: 2px 0 5px rgba(0,0,0,0.05);
        }

        .logo {
            font-size: 16px;
            font-weight: bold;
            color: #000;
            padding: 0 30px 40px;
        }
        
        .nav-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .nav-item a {
            font-size: 15px;
            display: block;
            padding: 15px 30px;
            text-decoration: none;
            color: #6C6C6C;
            transition: background-color 0.2s;
        }

        .nav-item a:hover {
            background-color: #f0f0f0;
        }

        .nav-item.active a {
            color: #000;
            font-weight: bold;
        }
        
        .logout {
            margin-top: 50px;
            cursor: pointer;
        }

        .logout a {
            display: block;
            padding: 15px 30px;
            color: #6C6C6C;
            text-decoration: none;
            transition: background-color 0.2s;
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
            margin-top: 30px;
            background-color: #fff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            max-width: 650px; 
            width: 100%; 
        }
        
        .warning-text {
            color: #d32f2f; /* Red color for warning */
            font-weight: 600;
            margin-bottom: 25px;
            padding: 15px;
            border: 2px solid #ef9a9a;
            background-color: #ffcdd2;
            border-radius: 4px;
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

        .form-input, .form-select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ccc;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 17px;
            outline: none;
            transition: border-color 0.3s;
        }

        .form-input:focus, .form-select:focus {
            border-color: #00bcd4;
        }

        .form-select {
             color: #666;
        }
        .form-select option:not([disabled]):not(:first-child) {
            color: #333;
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

        /* Specific style for Archive/Delete button (making it red) */
        .archive-button {
            background-color: #d32f2f; /* Red */
        }
        
        .archive-button:hover {
            background-color: #b71c1c; /* Darker red on hover */
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
        <div class="sidebar">
            <div class="logo">
                📚 Smart Library
            </div>
            <ul class="nav-list">
                <li class="nav-item"><a href="librarian.php">Dashboard</a></li>
                <li class="nav-item"><a href="book_inventory.php">Book Inventory</a></li>
                <li class="nav-item"><a href="add_book.php">Add New Book</a></li>
                <li class="nav-item"><a href="update_book.php">Update Book</a></li>
                <li class="nav-item active"><a href="archive_book.php">Archive Book</a></li>
            </ul>
            <div class="logout"><a href="login.php">Logout</a></div>
        </div>

        <div class="main-content">
            
            <div class="dashboard-section">
                <h2>Archive a Book</h2>

                <div class="form-card">
                    <div class="warning-text">
                        Warning: Archiving a book removes it from the search catalog. Only proceed if the book is permanently lost, damaged, or retired.
                    </div>

                    <form action="book_inventory.php" method="POST">
                        
                        <div class="form-group">
                            <label for="isbn" class="form-label">Book ISBN</label>
                            <input type="text" id="isbn" name="isbn" class="form-input" placeholder="Enter ISBN to archive" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="reason" class="form-label">Reason for Archiving</label>
                            <select id="reason" name="reason" class="form-select" required>
                                <option value="" disabled selected>Select a reason</option>
                                <option value="lost">Lost</option>
                                <option value="damaged">Damaged/Unrepairable</option>
                                <option value="retired">Retired/Outdated Edition</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="button-group">
                            <button type="submit" class="action-button archive-button">Archive Book Permanently</button>
                            
                            <button type="button" class="action-button cancel-button" onclick="window.location.href='librarian.php'">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>