<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student's Dashboard</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">

    <style>
        /* Global Styles */
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #F7FCFC;
            /* Requested background color */
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
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.05);
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
        .dashboard-section h2 {
            font-size: 25px;
            font-weight: bold;
            margin-bottom: 7px;
            margin-top: -7px;
        }

        .borrow-limit {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 30px;
        }

        /* Action Cards Container */
        .action-cards {
            display: flex;
            gap: 30px;
            margin-bottom: 25px;
        }

        .card {
            flex: 1;
            max-width: 250px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            min-height: 80px;
            display: flex;
        }

        /* Card Link Style */
        .card-link {
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: #333;
            font-weight: 550;
            font-size: 16px;
            padding: 25px;
            flex-grow: 2;
            transition: background-color 0.2s, box-shadow 0.2s;
        }

        /* Hover Effect for Clickable Card */
        .card-link:hover {
            background-color: #F0F8F8;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .overview-section {
            max-width: 960px;
        }

        .overview-section h3 {
            font-size: 18px;
            font-weight: 600;
            margin-top: 1px;
            margin-bottom: 10px;
        }

        /* Overview Card Style */
        .overview-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            min-height: 250px;
            margin-bottom: 20px;
        }

        .reminder {
            font-size: 0.9em;
            color: #666;
            padding-top: 20px;
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
                <li class="nav-item active"><a href="student.html">Dashboard</a></li>
                <li class="nav-item"><a href="student_borrow.html">Books</a></li>
                <li class="nav-item" id="reservations-nav"><a href="student_reservation.html">Reservations (<span id="sidebarReservationCount">0</span>)</a></li>
                <li class="nav-item" id="borrowed-books-nav"><a href="studentborrowed_books.html">Borrowed Books (<span id="sidebarBorrowedCount">0</span>)</a></li>
            </ul>
            <div class="logout"><a href="login.html">Logout</a></div>
        </div>

        <div class="main-content">
            <div class="header">
                Welcome, <span>[Student's Name]</span>
            </div>

            <div class="dashboard-section">
                <h2>Student's Dashboard</h2>
                <div class="borrow-limit">Borrow limit: 3 books per semester</div>

                <div class="action-cards">
                    <div class="card">
                        <a href="student_borrow.html" class="card-link">Borrow Book</a>
                    </div>
                    <div class="card">
                        <a href="student_borrow.html" class="card-link">Reserve Book</a>
                    </div>
                    <div class="card">
                        <a href="studentborrowed_books.html" class="card-link">Return Book</a>
                    </div>
                </div>

                <div class="overview-section">
                    <div class="overview-card">
                        <h3>Overview</h3>
                        <p id="dashboardOverviewText">
                            You currently have <strong id="overviewBorrowedCount">0</strong> books borrowed and <strong id="overviewReservationCount">0</strong> active reservations.
                            <br>
                        </p>
                    </div>

                    <div class="reminder">
                        Reminder: You must return all borrowed books at semester end. Unreturned books will be charged
                        at full price.
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        // --- Shared Data Retrieval Logic ---

        function getReservations() {
            try {
                const data = localStorage.getItem('studentReservations');
                return data ? JSON.parse(data) : [];
            } catch (e) {
                return [];
            }
        }

        function getBorrowedCount() {
            // Retrieve the count saved by student_borrow.html
            return parseInt(localStorage.getItem('studentBorrowedCount') || '0');
        }

        // --- UI Update Function ---

        function updateDashboardCounts() {
            const reservations = getReservations();
            const borrowedCount = getBorrowedCount();
            const reservationCount = reservations.length;

            // 1. Update Sidebar Counts
            document.getElementById('sidebarReservationCount').textContent = reservationCount;
            document.getElementById('sidebarBorrowedCount').textContent = borrowedCount;
            
            // 2. Update Dashboard Overview Text
            document.getElementById('overviewBorrowedCount').textContent = borrowedCount;
            document.getElementById('overviewReservationCount').textContent = reservationCount;
        }


        // Initialize the UI on load
        document.addEventListener('DOMContentLoaded', () => {
            updateDashboardCounts();
        });
    </script>
</body>

</html>