<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Handle Penalties</title>

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
        .dashboard-section {
            width: 100%;
        }
        
        .dashboard-section h2 {
            font-size: 25px;
            font-weight: bold;
            margin-bottom: 20px;
            margin-top: -7px;
        }

        /* --- Penalties Card Styles --- */

        .penalties-card {
            margin-top: 30px;
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            width: 96%; 
            overflow-x: auto; 
            max-width: 1100px;
        }
        
        .search-form {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
            margin-top: 15px;
        }
        
        .form-input, .form-select {
            padding: 10px;
            border: 2px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 15px;
        }

        .form-select {
            width: 170px;
            color: #666;
        }

        .form-select option:not([disabled]):not(:first-child) {
            color: #333;
        }
        
        .search-input {
            flex-grow: 1;
            max-width: 450px;
        }
        
        .search-button {
            background-color: #00a89d;
            color: white;
            padding: 11px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 16px;
        }
        
        .search-button:hover {
            background-color: #00897b;
        }

        /* Table Styling */
        .penalties-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 15px;
            margin-bottom: 15px;
        }

        .penalties-table th, .penalties-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        .penalties-table th {
            background-color: #F7FCFC;
            color: #6C6C6C;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .penalties-table tbody tr:hover {
            background-color: #FAFAFA;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 15px;
            font-weight: 500;
            font-size: 14px;
        }

        .status-pending {
            background-color: #fff3e0;
            color: #ff9800; /* Amber */
        }
        
        .status-paid {
            background-color: #e8f5e9;
            color: #4CAF50; /* Green */
        }
        
        .status-cleard {
            background-color: #fce4ec;
            color: #e91e63; /* Pink/Reddish */
        }
        
        .status-replacement {
            color: #d32f2f;
            font-weight: 600;
        }

        /* Action Buttons */
        .action-btn {
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.2s;
            font-size: 15px;
        }

        .collect-btn {
            background-color: #00bcd4;
            color: white;
            margin-right: 5px;
        }
        .collect-btn:hover {
            background-color: #0097a7;
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
                <li class="nav-item"><a href="staff.html">Dashboard</a></li>
                <li class="nav-item"><a href="borrowing_requests.html">Borrowing Requests</a></li>
                <li class="nav-item"><a href="returning&clearance.html">Returning & Clearance</a></li>
                <li class="nav-item active"><a href="penalties.html">Penalties Management</a></li>
                <li class="nav-item"><a href="borrower_status.html">Borrower Status</a></li>
            </ul>
            <div class="logout"><a href="login.html">Logout</a></div>
        </div>

        <div class="main-content">
            <div class="header">
                Welcome, <span>[Staff's Name]</span>
            </div>

            <div class="dashboard-section">
                <h2>Handle Book Penalties</h2>

                <div class="penalties-card">
                    <form class="search-form" onsubmit="return false;">
                        <input type="text" class="search-input form-input" placeholder="Search by Borrower or Book...">
                        <select class="form-select">
                            <option disabled selected>Filter by Status</option>
                            <option>Pending</option>
                            <option>Paid</option>
                        </select>
                        <button type="submit" class="search-button">Search</button>
                    </form>
                    
                    <table class="penalties-table">
                        <thead>
                            <tr>
                                <th>Borrower</th>
                                <th>Book Title / ISBN</th>
                                <th>Amount Due</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Bob Johnson (Student)</td>
                                <td>Physics in Motion</td>
                                <td class="status-replacement">₱950.00</td>
                                <td><span class="status-badge status-pending">Pending</span></td>
                                <td>
                                    <button class="action-btn collect-btn">Collect Fee</button>
                                </td>
                            </tr>
                            <tr>
                                <td>Dr. Lee (Teacher)</td>
                                <td>Advanced Statistics</td>
                                <td class="status-replacement">₱1,500.00</td>
                                <td><span class="status-badge status-pending">Pending</span></td>
                                <td>
                                    <button class="action-btn collect-btn">Collect Fee</button>
                                </td>
                            </tr>
                            <tr>
                                <td>Eve Adams (Student)</td>
                                <td>Basic Chemistry</td>
                                <td>₱750.00</td>
                                <td><span class="status-badge status-paid">Paid</span></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td>David Chen (Teacher)</td>
                                <td>Literary Theory</td>
                                <td>₱1,200.00</td>
                                <td><span class="status-badge status-paid">Paid</span></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>