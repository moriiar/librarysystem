<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrower Status</title>

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
            max-width: 900px;
        }
        
        .dashboard-section h2 {
            font-size: 25px;
            font-weight: bold;
            margin-bottom: 20px;
            margin-top: -7px;
        }

        /* --- Status Card Styles --- */

        .status-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            width: 106%; 
            margin-bottom: 25px;
        }
        
        .card-header {
            font-size: 20px;
            font-weight: 600;
            color: #000;
            margin-bottom: 30px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        /* Search Form */
        .search-form {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .form-input {
            flex-grow: 1;
            padding: 12px;
            border: 2px solid #ccc;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 16px;
        }
        
        .search-button {
            background-color: #00a89d;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            font-size: 17px;
            transition: background-color 0.2s;
        }
        
        .search-button:hover {
            background-color: #00897b;
        }
        
        /* Borrower Information Area */
        .borrower-info {
            padding: 20px 0;
            border-top: 2px dashed #eee;
        }
        
        .info-title {
            font-size: 19px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
        }
        
        .info-list {
            list-style: none;
            padding: 0;
            display: flex;
            flex-wrap: wrap;
            gap: 15px 30px;
        }
        
        .info-list li {
            width: 45%;
            font-size: 15px;
        }
        
        .info-list strong {
            display: block;
            font-weight: 500;
            color: #6C6C6C;
        }
        
        .status-clear {
            color: #4CAF50; /* Green */
            font-weight: 700;
            font-size: 1.1em;
        }
        
        .status-hold {
            color: #d32f2f; /* Red */
            font-weight: 700;
            font-size: 1.1em;
        }
        
        /* Currently Borrowed Table */
        .borrowed-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 15px;
        }

        .borrowed-table th, .borrowed-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        .borrowed-table th {
            background-color: #F7FCFC;
            color: #6C6C6C;
            font-weight: 600;
            text-transform: uppercase;
        }

        .overdue-row td {
            background-color: #ffcdd2; /* Light red background for overdue rows */
        }
        
        .overdue-text {
            color: #d32f2f;
            font-weight: 600;
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
                <li class="nav-item"><a href="penalties.html">Penalties Management</a></li>
                <li class="nav-item active"><a href="borrower_status.html">Borrower Status</a></li>
            </ul>
            <div class="logout"><a href="login.html">Logout</a></div>
        </div>

        <div class="main-content">
            <div class="header">
                Welcome, <span>[Staff's Name]</span>
            </div>

            <div class="dashboard-section">
                <h2>Borrower Status Lookup</h2>

                <div class="status-card">
                    <div class="card-header">
                        Search Borrower Record
                    </div>
                    
                    <form class="search-form" onsubmit="return false;">
                        <input type="text" class="form-input" placeholder="Enter Borrower Name..." required>
                        <button type="submit" class="search-button">Lookup Status</button>
                    </form>
                    
                    <div class="borrower-info">
                        <div class="info-title">Borrower: Bob Johnson</div>
                        
                        <ul class="info-list">
                            <li>
                                <strong>Role:</strong> Student
                            </li>
                            <li>
                                <strong>Clearance Status:</strong> <span class="status-hold">On Hold (Pending Fees)</span>
                            </li>
                            <li>
                                <strong>Active Loans:</strong> 2
                            </li>
                            <li>
                                <strong>Overdue Books:</strong> 1 (<span class="overdue-text">Requires Full Price Payment</span>)
                            </li>
                            <li>
                                <strong>Pending Fees:</strong> ₱950.00 (Book Fee)
                            </li>
                            <li>
                                <strong>Borrowing Limit:</strong> 3 Books
                            </li>
                        </ul>
                    </div>

                    <div class="info-title" style="margin-top: 30px;">Currently Borrowed Books</div>
                    <table class="borrowed-table">
                        <thead>
                            <tr>
                                <th>Book Title</th>
                                <th>ISBN</th>
                                <th>Date Borrowed</th>
                                <th>Due Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="overdue-row">
                                <td>Physics in Motion</td>
                                <td>9780123683712</td>
                                <td>Sept. 29, 2025</td>
                                <td>Dec. 19, 2025</td>
                                <td><span class="overdue-text">8 Days Overdue</span></td>
                            </tr>
                            <tr>
                                <td>Literary Theory</td>
                                <td>9782345729106</td>
                                <td>Sept. 27, 2025</td>
                                <td>Dec. 11, 2025</td>
                                <td>On Time</td>
                            </tr>
                            <tr>
                                <td>The Art of Data Analysis</td>
                                <td>9786789537280</td>
                                <td>Sept. 23, 2025</td>
                                <td>Dec. 9, 2025</td>
                                <td>On Time</td>
                            </tr>
                        </tbody>
                    </table>

                </div>
            </div>
        </div>
    </div>
</body>
</html>