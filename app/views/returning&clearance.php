<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Returning & Clearance</title>

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
            display: flex;
            flex-direction: column;
            gap: 20px; /* Gap between cards */
        }
        
        .dashboard-section h2 {
            font-size: 25px;
            font-weight: bold;
            margin-bottom: 20px;
            margin-top: -7px;
        }
        
        /* Two-column layout for forms/info */
        .info-cards {
            display: flex;
            gap: 30px;
            width: 100%;
            max-width: 1000px; /* Limit width for aesthetics */
        }
        
        .card {
            flex: 1;
            background-color: #fff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .card-header {
            font-size: 19px;
            font-weight: 600;
            color: #000;
            margin-bottom: 25px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        /* Form Group and Input Styles (Reused theme) */
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
            font-size: 16px;
            outline: none;
            transition: border-color 0.3s;
        }

        .form-input:focus {
            border-color: #00bcd4;
        }

        /* Action Button */
        .action-button {
            width: 100%;
            background-color: #00a89d;
            color: white;
            padding: 14px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
            transition: background-color 0.3s;
        }

        .action-button:hover {
            background-color: #00897b;
        }
        
        .clearance-button {
            background-color: #4CAF50; /* Green for success/clearance */
        }
        
        .clearance-button:hover {
            background-color: #388E3C;
        }
        
        .returned {
            color: #4CAF50;
            font-weight: 600;
        }
        
        /* Detail List */
        .detail-list {
            list-style: none;
            padding: 0;
        }
        
        .detail-list li {
            padding: 8px 0;
            border-bottom: 1px dashed #eee;
            font-size: 16px;
        }
        
        .detail-list li:last-child {
            border-bottom: none;
        }
        
        .detail-list strong {
            display: inline-block;
            width: 120px;
            color: #6C6C6C;
            font-weight: bold;
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
                <li class="nav-item active"><a href="returning&clearance.html">Returning & Clearance</a></li>
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
                <h2>Book Returns and Clearance</h2>

                <div class="info-cards">
                    
                    <div class="card">
                        <div class="card-header">
                            1. Scan Book & Search Record
                        </div>
                        <form onsubmit="return false;"> 
                            <div class="form-group">
                                <label for="book_identifier" class="form-label">Book ID / ISBN / Barcode</label>
                                <input type="text" id="book_identifier" name="book_identifier" class="form-input" placeholder="e.g., 9781234567890" required>
                            </div>
                            <button type="submit" class="action-button">Search</button>
                        </form>
                        
                        <hr style="margin: 30px 0 20px; border: 0; border-top: 1px solid #eee;">
                        
                        <div class="card-header" style="border-bottom: none; margin-bottom: 10px;">
                            Borrower Details
                        </div>
                        
                        <ul class="detail-list">
                            <li><strong>Borrower:</strong> Teacher</li>
                            <li><strong>Name:</strong> Alice Smith</li>
                            <li><strong>Book Title:</strong> The Martian</li>
                            <li><strong>Due Date:</strong> Dec. 11, 2025</li>
                            <li><strong>Return Date:</strong> Oct. 1, 2025</li>
                            <li><strong>Status:</strong> <span class="returned">Returned</span></li>
                        </ul>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            2. Clearance and Penalty Assessment
                        </div>
                        
                        <form action="#" method="POST">
                            
                            <div class="form-group">
                                <label for="condition" class="form-label">Book Condition Upon Return</label>
                                <select id="condition" name="condition" class="form-input" required>
                                    <option value="good" selected>Good / No Damage</option>
                                    <option value="minor_damage">Minor Damage</option>
                                    <option value="major_damage">Major Damage (Requires Fee)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="penalty" class="form-label">Calculated Penalty Fee</label>
                                <input type="text" id="penalty" name="penalty" class="form-input overdue" value="₱0.00" readonly>
                            </div>
                            
                            <div class="form-group">
                                <label for="payment_status" class="form-label">Penalty Payment Status</label>
                                <select id="payment_status" name="payment_status" class="form-input" required>
                                    <option value="pending" class="overdue" selected>Pending Payment</option>
                                    <option value="paid">Paid</option>
                                    <option value="cleared">Cleared</option>
                                </select>
                                <small style="display: block; margin-top: 5px; color: #666;">Clearance can only be issued once penalties are handled.</small>
                            </div>

                            <div class="form-group" style="margin-top: 40px;">
                                <button type="submit" class="action-button clearance-button">
                                    Finalize Return & Issue Clearance
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</body>
</html>