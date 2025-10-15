<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrowed Books</title>

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

        /* Borrowed Section */
        .borrowed-section h2 {
            font-size: 25px;
            font-weight: 700;
            margin-bottom: 7px;
            margin-top: 0;
        }

        .borrowed-section p.subtitle {
            font-size: 15px;
            color: #666;
            margin-bottom: 30px;
        }

        /* List Table Styling */
        .borrowed-list-table {
            width: 100%;
            border-collapse: collapse;
            background-color: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            max-width: 1000px;
        }

        .borrowed-list-table th,
        .borrowed-list-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .borrowed-list-table th {
            background-color: #f8f8f8;
            font-weight: 600;
            font-size: 14px;
            color: #555;
        }

        .borrowed-list-table td {
            font-size: 15px;
        }

        /* Status Tags */
        .status-tag {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: 600;
            font-size: 13px;
            text-transform: capitalize;
        }

        .status-borrowed {
            background-color: #E0F7FA;
            color: #00A693;
        }

        .status-returned {
            background-color: #E6FFE6;
            color: #388E3C;
        }

        .status-overdue {
            background-color: #F8D7DA;
            color: #CC0000;
        }

        .status-penalty {
            background-color: #FFF3E0;
            color: #E5A000;
        }

        /* Action Button */
        .return-btn {
            background-color: #00A693;
            color: #fff;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.2s;
        }

        .return-btn:hover {
            background-color: #008779;
        }

        .return-btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }


        /* --- MODAL STYLES (Penalty) --- */
        .modal {
            display: none;
            position: fixed;
            z-index: 100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: #fff;
            margin: auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 450px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            text-align: center;
        }

        .modal-content h3 {
            font-size: 20px;
            margin-top: 0;
            color: #CC0000;
        }

        .modal-content p {
            font-size: 15px;
            color: #666;
            margin-bottom: 20px;
        }

        .modal-buttons button {
            padding: 10px 20px;
            margin: 0 10px;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        #confirmPenaltyBtn {
            background-color: #CC0000;
            color: #fff;
        }

        .close-modal {
            background-color: #ddd;
            color: #333;
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
                <li class="nav-item"><a href="student.html">Dashboard</a></li>
                <li class="nav-item"><a href="student_borrow.html">Books</a></li>
                <li class="nav-item" id="reservations-nav"><a href="student_reservation.html">Reservations (<span
                            id="sidebarReservationCount">0</span>)</a></li>
                <li class="nav-item active" id="borrowed-books-nav"><a href="studentborrowed_books.html">Borrowed Books
                        (<span id="sidebarBorrowedCount">0</span>)</a></li>
            </ul>
            <div class="logout"><a href="login.html">Logout</a></div>
        </div>

        <div class="main-content">
            <div class="header">
                Welcome, <span>[Student's Name]</span>
            </div>

            <div class="borrowed-section">
                <h2>Your Borrowed Books</h2>
                <p class="subtitle">Manage the status and due dates of your active loans.</p>

                <h3>Active Loans (<span id="borrowedCount">0</span> / 3)</h3>

                <table class="borrowed-list-table">
                    <thead>
                        <tr>
                            <th style="width: 40%;">Title</th>
                            <th style="width: 15%;">Borrow Date</th>
                            <th style="width: 15%;">Due Date</th>
                            <th style="width: 15%;">Status</th>
                            <th style="width: 15%;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="loanTableBody">
                        <tr id="noBorrowedRow">
                            <td colspan="5" style="text-align: center; color: #666; font-style: italic;">
                                You have no books currently borrowed.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="penaltyModal" class="modal">
        <div class="modal-content">
            <h3>Overdue Book Penalty</h3>
            <p id="penaltyModalMessage">
                This book is overdue. As a student, you must pay the full book price of
                <strong id="penaltyAmountText">₱5,000.00</strong> before your clearance can be granted.
            </p>
            <p style="color: #CC0000; font-weight: 600;">You must clear this penalty to complete the return.</p>
            <div class="modal-buttons">
                <button id="confirmPenaltyBtn">Pay Penalty & Return</button>
                <button class="close-modal" onclick="closeModal('penaltyModal')">Close</button>
            </div>
        </div>
    </div>


    <script>
        // --- Configuration ---
        const BORROW_LIMIT = 3;
        const SEMESTER_END_DATE = new Date('December 11, 2025'); // Fixed standard due date
        const FULL_BOOK_PRICE = 5000; // Simulated full book price for penalty

        // --- Data Persistence and Retrieval (Shared) ---
        function getReservations() {
            try {
                const data = localStorage.getItem('studentReservations');
                return data ? JSON.parse(data) : [];
            } catch (e) {
                return [];
            }
        }

        function getLoanList() {
            try {
                const data = localStorage.getItem('studentLoanList');
                // Ensure dates are converted back from string to Date object
                const loans = data ? JSON.parse(data) : [];
                return loans.map(loan => ({
                    ...loan,
                    borrowDate: new Date(loan.borrowDate),
                    dueDate: new Date(loan.dueDate),
                }));
            } catch (e) {
                return [];
            }
        }

        function saveLoanList(loans) {
            // Convert Date objects to ISO strings for storage
            const serializableLoans = loans.map(loan => ({
                ...loan,
                borrowDate: loan.borrowDate.toISOString(),
                dueDate: loan.dueDate.toISOString(),
            }));
            localStorage.setItem('studentLoanList', JSON.stringify(serializableLoans));
            // Also update the simple count for other pages
            localStorage.setItem('studentBorrowedCount', loans.filter(l => l.status === 'borrowed').length);
        }

        function getBorrowedCount() {
            return getLoanList().filter(l => l.status === 'borrowed').length;
        }

        // --- Utility Functions ---

        function formatDisplayDate(date) {
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }

        function isOverdue(dueDate) {
            // Check if the due date is in the past
            // Using a tolerance of 1 day to account for time differences if needed, 
            // but comparing the date parts is safer:
            const today = new Date();
            today.setHours(0, 0, 0, 0); // Normalize to start of day

            const normalizedDueDate = new Date(dueDate);
            normalizedDueDate.setHours(0, 0, 0, 0);

            return normalizedDueDate.getTime() < today.getTime();
        }

        // --- Core Logic ---

        function renderBorrowedBooks() {
            const loans = getLoanList();
            const borrowedCount = getBorrowedCount();
            const tableBody = document.getElementById('loanTableBody');

            // Update counts in the header and sidebar
            document.getElementById('borrowedCount').textContent = `${borrowedCount}`;
            document.getElementById('sidebarBorrowedCount').textContent = borrowedCount;

            tableBody.innerHTML = ''; // Clear existing table rows

            if (loans.length === 0) {
                tableBody.innerHTML = `
                    <tr id="noBorrowedRow">
                        <td colspan="5" style="text-align: center; color: #666; font-style: italic;">
                            You have no books currently borrowed or returned.
                        </td>
                    </tr>
                `;
                return;
            }

            loans.forEach(loan => {
                const isCurrentOverdue = isOverdue(loan.dueDate) && loan.status === 'borrowed';
                let statusClass = `status-${loan.status}`;
                let statusText = loan.status;

                if (isCurrentOverdue) {
                    statusClass = 'status-overdue';
                    statusText = 'Overdue';
                } else if (loan.status === 'returned' && loan.isOverdueAtReturn) {
                    statusClass = 'status-penalty';
                    statusText = 'Returned (Penalty Paid)';
                }

                const row = tableBody.insertRow();
                row.innerHTML = `
                    <td>${loan.title}</td>
                    <td>${formatDisplayDate(loan.borrowDate)}</td>
                    <td><span style="color: ${isCurrentOverdue ? '#CC0000' : '#333'}">${formatDisplayDate(loan.dueDate)}</span></td>
                    <td><span class="status-tag ${statusClass}">${statusText}</span></td>
                    <td>
                        <button class="return-btn" 
                                onclick="handleReturn('${loan.loanId}')" 
                                ${loan.status !== 'borrowed' ? 'disabled' : ''}>
                            Return
                        </button>
                    </td>
                `;
            });
        }

        // --- Modal Control Functions ---
        function openModal(modalId) { document.getElementById(modalId).style.display = 'flex'; }
        function closeModal(modalId) { document.getElementById(modalId).style.display = 'none'; }

        // --- Return Workflow ---

        function handleReturn(loanId) {
            let loans = getLoanList();
            const loan = loans.find(l => l.loanId === loanId);
            if (!loan) return;

            const overdue = isOverdue(loan.dueDate);

            if (overdue) {
                // Book is overdue, show penalty modal
                document.getElementById('penaltyAmountText').textContent = `₱${FULL_BOOK_PRICE.toLocaleString('en-PH')}`;

                // Set up the confirmation button to finalize the return after 'payment'
                document.getElementById('confirmPenaltyBtn').onclick = () => {
                    finalizeReturn(loanId, true);
                    closeModal('penaltyModal');
                };
                openModal('penaltyModal');

            } else {
                // Book is returned on time
                if (confirm(`Confirm return of "${loan.title}"?`)) {
                    finalizeReturn(loanId, false);
                }
            }
        }

        function finalizeReturn(loanId, paidPenalty) {
            let loans = getLoanList();
            const loanIndex = loans.findIndex(l => l.loanId === loanId);

            if (loanIndex > -1) {
                // 1. Update Loan Record
                loans[loanIndex].status = 'returned';
                loans[loanIndex].returnDate = new Date().toISOString();
                loans[loanIndex].isOverdueAtReturn = paidPenalty;

                // 2. Save State
                saveLoanList(loans);

                // 3. Update UI
                renderBorrowedBooks();

                let message = paidPenalty ?
                    `"${loans[loanIndex].title}" returned with penalty paid (₱${FULL_BOOK_PRICE.toLocaleString('en-PH')}).` :
                    `"${loans[loanIndex].title}" returned successfully and on time.`;

                alert(`SUCCESS: ${message}`);
            }
        }

        // --- Sidebar Synchronization ---
        function updateSidebar() {
            const reservations = getReservations();
            document.getElementById('sidebarReservationCount').textContent = reservations.length;
            document.getElementById('sidebarBorrowedCount').textContent = getBorrowedCount();
        }

        // --- Initialization ---
        document.addEventListener('DOMContentLoaded', () => {
            updateSidebar();
            renderBorrowedBooks();
        });

        window.onclick = function (event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>

</html>