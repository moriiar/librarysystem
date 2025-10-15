<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrowing Requests</title>

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

        /* --- Requests Table Card Styles --- */

        .requests-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            width: 96%; /* Take full content width */
            overflow-x: auto; /* Allow horizontal scrolling for table on small screens */
        }

        .requests-card h3 {
            font-size: 18px;
            font-weight: 600;
            margin-top: 0;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        /* Table Styling */
        .requests-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .requests-table th, .requests-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 2px solid #f0f0f0;
        }

        .requests-table th {
            background-color: #F7FCFC;
            color: #6C6C6C;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .requests-table tbody tr:hover {
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
            color: #ff9800;
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

        .approve-btn {
            background-color: #4CAF50;
            color: white;
            margin-right: 5px;
        }
        .approve-btn:hover {
            background-color: #388E3C;
        }
        
        .reject-btn {
            background-color: #F44336;
            color: white;
        }
        .reject-btn:hover {
            background-color: #D32F2F;
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
                <li class="nav-item active"><a href="borrowing_requests.html">Borrowing Requests</a></li>
                <li class="nav-item"><a href="returning&clearance.html">Returning & Clearance</a></li>
                <li class="nav-item"><a href="penalties.html">Penalties Management</a></li>
                <li class="nav-item"><a href="borrower_status.html">Borrower Status</a></li>
            </ul>
            <div class="logout"><a href="login.html">Logout</a></div>
        </div>

        <div class="main-content">
            <div class="header">
                Welcome, <span>[Staff's Name]</span>
            </div>

            <div class="dashboard-section">
                <h2>Manage Borrowing Requests</h2>

                <div class="requests-card">
                    <h3>Pending Requests (3 Total)</h3>
                    
                    <table class="requests-table">
                        <thead>
                            <tr>
                                <th>Req. ID</th>
                                <th>Borrower</th>
                                <th>Book Title</th>
                                <th>Date Requested</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>#00101</td>
                                <td>Alice Smith</td>
                                <td>The Martian</td>
                                <td>10/01/2025</td>
                                <td><span class="status-badge status-pending">Pending</span></td>
                                <td>
                                    <button class="action-btn approve-btn">Approve</button>
                                    <button class="action-btn reject-btn">Reject</button>
                                </td>
                            </tr>
                            <tr>
                                <td>#00102</td>
                                <td>Bob Johnson</td>
                                <td>Data Structures in Java</td>
                                <td>09/30/2025</td>
                                <td><span class="status-badge status-pending">Pending</span></td>
                                <td>
                                    <button class="action-btn approve-btn">Approve</button>
                                    <button class="action-btn reject-btn">Reject</button>
                                </td>
                            </tr>
                             <tr>
                                <td>#00103</td>
                                <td>Charlie Brown</td>
                                <td>History of Art Vol. 2</td>
                                <td>09/30/2025</td>
                                <td><span class="status-badge status-pending">Pending</span></td>
                                <td>
                                    <button class="action-btn approve-btn">Approve</button>
                                    <button class="action-btn reject-btn">Reject</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>