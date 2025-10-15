<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrow & Reserve Book</title>

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
            width: 409px;
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

        /* Inventory Section */
        .inventory-section h2 {
            font-size: 25px;
            font-weight: 700;
            margin-bottom: 7px;
            margin-top: 0;
        }

        .inventory-section p.subtitle {
            font-size: 15px;
            color: #666;
            margin-bottom: 30px;
        }

        .book-list {
            display: flex;
            gap: 23px;
            flex-wrap: wrap;
        }

        .book-card {
            width: 320px;
            height: 210px;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            padding: 10px;
            box-sizing: border-box;
        }

        .book-cover-area {
            background-color: #F0F8F8;
            width: 110px;
            height: 190px;
            border-radius: 8px;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .book-details {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            flex-grow: 1;
            padding: 5px 0;
            padding-left: 5px;
        }

        .book-title {
            font-size: 16px;
            font-weight: 600;
            line-height: 1.3;
            margin: 10px 0 3px 0;
        }

        .book-author {
            font-size: 14px;
            color: #666;
            margin: 0;
        }

        .book-status-info {
            font-size: 14px;
            color: #444;
            font-weight: 500;
            margin-bottom: 15px;
            margin-top: 10px;
        }

        .book-status-info span {
            font-weight: 700;
            color: #00A693;
        }

        /* Action Buttons/Status Tags */
        .action-button {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px 15px;
            border-radius: 8px;
            font-weight: 700;
            text-decoration: none;
            border: none;
            width: 100px;
            min-height: 40px;
            box-sizing: border-box;
            align-self: flex-end;
            font-size: 15px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .borrow-button {
            background-color: #00A693;
            color: #fff;
        }

        .reserve-button {
            background-color: #FFC107;
            color: #333;
        }

        /* Disabled States */
        .borrow-button:disabled,
        .reserve-button:disabled {
            background-color: #ddd;
            color: #666;
            cursor: not-allowed;
        }

        .reserved-tag {
            background-color: #E0E0E0;
            color: #444;
            cursor: default;
        }

        .borrowed-tag {
            background-color: #F8D7DA;
            color: #721C24;
            cursor: default;
        }

        /* --- MODAL STYLES (Borrow & Warning) --- */
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
            max-width: 400px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            text-align: center;
        }

        .modal-content h3 {
            font-size: 20px;
            margin-top: 0;
            color: #00A693;
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

        #confirmBorrowBtn,
        #confirmReserveBtn {
            background-color: #00A693;
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
                <li class="nav-item active"><a href="student_borrow.html">Books</a></li>
                <li class="nav-item" id="reservations-nav"><a href="student_reservation.html">Reservations (<span
                            id="sidebarReservationCount">0</span>)</a></li>
                <li class="nav-item" id="borrowed-books-nav"><a href="studentborrowed_books.html">Borrowed Books (<span
                            id="sidebarBorrowedCount">0</span>)</a>
                </li>
            </ul>
            <div class="logout"><a href="login.html">Logout</a></div>
        </div>

        <div class="main-content">
            <div class="header">
                Welcome, <span>[Student's Name]</span>
            </div>

            <div class="inventory-section">
                <h2>Borrow & Reserve Book</h2>
                <p class="subtitle">Select an available book to borrow or an unavailable one to reserve.</p>

                <div class="book-list" id="bookInventoryList">
                    <div class="book-card" data-book-id="101" data-title="Introduction to Programming"
                        data-author="J. Doe" data-copies="12">
                        <div class="book-cover-area"></div>
                        <div class="book-details">
                            <div>
                                <div class="book-title">Introduction to Programming</div>
                                <div class="book-author">By: J. Doe</div>
                            </div>
                            <div class="book-status-info">
                                Stock: <span class="stock-count">12 copies</span> available
                            </div>
                            <button class="action-button borrow-button" onclick="handleBookAction(this)">Borrow</button>
                        </div>
                    </div>

                    <div class="book-card" data-book-id="102" data-title="The Great Gatsby"
                        data-author="F. Scott Fitzgerald" data-copies="1">
                        <div class="book-cover-area"></div>
                        <div class="book-details">
                            <div>
                                <div class="book-title">The Great Gatsby</div>
                                <div class="book-author">By: F. Scott Fitzgerald</div>
                            </div>
                            <div class="book-status-info">
                                Stock: <span class="stock-count">1 copy</span> available
                            </div>
                            <button class="action-button borrow-button" onclick="handleBookAction(this)">Borrow</button>
                        </div>
                    </div>

                    <div class="book-card" data-book-id="103" data-title="Data Structures" data-author="A. Smith"
                        data-copies="0">
                        <div class="book-cover-area"></div>
                        <div class="book-details">
                            <div>
                                <div class="book-title">Data Structures</div>
                                <div class="book-author">By: A. Smith</div>
                            </div>
                            <div class="book-status-info">
                                Stock: <span class="stock-count" style="color: #E5A000;">0 copies</span> available
                            </div>
                            <button class="action-button reserve-button"
                                onclick="handleBookAction(this)">Reserve</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="borrowModal" class="modal">
        <div class="modal-content">
            <h3 id="modalTitle">Confirm Borrow</h3>
            <p id="modalMessage"></p>
            <p style="font-weight: 600;">Due Date: <span id="dueDateText"></span></p>
            <div class="modal-buttons">
                <button id="confirmBorrowBtn">Confirm Borrow</button>
                <button class="close-modal" onclick="closeModal('borrowModal')">Cancel</button>
            </div>
        </div>
    </div>

    <div id="reserveModal" class="modal">
        <div class="modal-content">
            <h3 style="color: #FFC107;">Confirm Reservation</h3>
            <p id="reserveModalMessage"></p>
            <p style="font-weight: 600;">Reservation expires: <span id="reserveExpiresText"></span></p>
            <div class="modal-buttons">
                <button id="confirmReserveBtn">Confirm Reservation</button>
                <button class="close-modal" onclick="closeModal('reserveModal')">Cancel</button>
            </div>
        </div>
    </div>

    <div id="warningModal" class="modal">
        <div class="modal-content">
            <h3 style="color: #E5A000;">Borrow Limit Reached</h3>
            <p>You have reached your maximum borrowing limit (3 books). Please return a book before borrowing another.
            </p>
            <div class="modal-buttons">
                <button class="close-modal" onclick="closeModal('warningModal')">OK</button>
            </div>
        </div>
    </div>


    <script>
        // --- Configuration ---
        const BORROW_LIMIT = 3;
        const SEMESTER_END_DATE = new Date('December 11, 2025');
        const RESERVATION_DAYS = 7;

        let selectedBook = null; // Holds data of the book selected for action

        // Dynamic Book Data State (simulates database/API)
        // We use this local variable and sync it with localStorage
        let bookInventory = [];

        // --- Data Persistence and Retrieval (Shared) ---

        function getBookInventory() {
            // Load from localStorage or use the default structure if empty
            const savedInventory = localStorage.getItem('bookInventoryState');
            if (savedInventory) {
                return JSON.parse(savedInventory);
            }

            // Default Inventory (only run if no state is saved)
            return [
                { id: 101, title: "Introduction to Programming", author: "J. Doe", copies: 12 },
                { id: 102, title: "The Great Gatsby", author: "F. Scott Fitzgerald", copies: 1 },
                { id: 103, title: "Data Structures", author: "A. Smith", copies: 0 },
                { id: 104, title: "Advanced Calculus", author: "B. Taylor", copies: 5 },
                { id: 105, title: "History of World Wars", author: "C. Roberts", copies: 2 }
            ];
        }

        function saveBookInventory(inventory) {
            localStorage.setItem('bookInventoryState', JSON.stringify(inventory));
            bookInventory = inventory; // Update the local variable
        }

        function getReservations() {
            try {
                const data = localStorage.getItem('studentReservations');
                return data ? JSON.parse(data) : [];
            } catch (e) {
                console.error("Error reading reservations:", e);
                return [];
            }
        }

        function saveReservations(reservationsArray) {
            localStorage.setItem('studentReservations', JSON.stringify(reservationsArray));
        }

        function getLoanList() {
            try {
                const data = localStorage.getItem('studentLoanList');
                const loans = data ? JSON.parse(data) : [];
                return loans.map(loan => ({
                    ...loan,
                    // Re-instantiate Date objects
                    borrowDate: loan.borrowDate ? new Date(loan.borrowDate) : null,
                    dueDate: loan.dueDate ? new Date(loan.dueDate) : null,
                }));
            } catch (e) {
                console.error("Error reading loan list:", e);
                return [];
            }
        }

        function saveLoanList(loans) {
            // Convert Date objects to ISO strings for storage
            const serializableLoans = loans.map(loan => ({
                ...loan,
                borrowDate: loan.borrowDate ? loan.borrowDate.toISOString() : null,
                dueDate: loan.dueDate ? loan.dueDate.toISOString() : null,
            }));
            localStorage.setItem('studentLoanList', JSON.stringify(serializableLoans));
            // Update the simple count for the sidebar based on active 'borrowed' loans
            localStorage.setItem('studentBorrowedCount', loans.filter(l => l.status === 'borrowed').length);
        }

        function getBorrowedCount() {
            return getLoanList().filter(l => l.status === 'borrowed').length;
        }

        // --- Utility Functions ---
        function formatDueDate(date) {
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        function calculateReservationExpiry() {
            const expiryDate = new Date();
            expiryDate.setDate(expiryDate.getDate() + RESERVATION_DAYS);
            return expiryDate;
        }

        function updateSidebarCounts() {
            const reservations = getReservations();
            const borrowedCount = getBorrowedCount();
            document.getElementById('sidebarBorrowedCount').textContent = borrowedCount;
            document.getElementById('sidebarReservationCount').textContent = reservations.length;
        }

        function openModal(modalId) { document.getElementById(modalId).style.display = 'flex'; }
        function closeModal(modalId) { document.getElementById(modalId).style.display = 'none'; }


        // --- UI Rendering Logic ---

        function renderBookInventory() {
            const container = document.getElementById('bookInventoryList');
            container.innerHTML = ''; // Clear static HTML

            const loans = getLoanList();
            const reservations = getReservations();

            bookInventory.forEach(book => {
                const isBorrowed = loans.some(l => l.bookId === book.id && l.status === 'borrowed');
                const isReserved = reservations.some(r => r.id === book.id);

                let buttonHTML;
                let stockText;
                let stockColor = '#00A693';

                if (isBorrowed) {
                    buttonHTML = `<div class="action-button borrowed-tag">Borrowed</div>`;
                    stockText = 'N/A';
                    stockColor = '#721C24';
                } else if (isReserved) {
                    buttonHTML = `<div class="action-button reserved-tag">Reserved</div>`;
                    stockText = 'N/A';
                    stockColor = '#E5A000';
                } else if (book.copies > 0) {
                    buttonHTML = `<button class="action-button borrow-button" onclick="handleBookAction(this)">Borrow</button>`;
                    stockText = `${book.copies} ${book.copies > 1 ? 'copies' : 'copy'}`;
                    stockColor = '#00A693';
                } else {
                    buttonHTML = `<button class="action-button reserve-button" onclick="handleBookAction(this)">Reserve</button>`;
                    stockText = `0 copies`;
                    stockColor = '#E5A000';
                }

                const card = document.createElement('div');
                card.className = 'book-card';
                card.dataset.bookId = book.id;
                card.dataset.title = book.title;
                card.dataset.author = book.author;
                card.dataset.copies = book.copies;

                card.innerHTML = `
                    <div class="book-cover-area"></div>
                    <div class="book-details">
                        <div>
                            <div class="book-title">${book.title}</div>
                            <div class="book-author">By: ${book.author}</div>
                        </div>
                        <div class="book-status-info">
                            Stock: <span class="stock-count" style="color: ${stockColor};">${stockText}</span> available
                        </div>
                        ${buttonHTML}
                    </div>
                `;
                container.appendChild(card);
            });
        }


        // --- Borrowing/Reservation Workflow ---

        function handleBookAction(buttonElement) {
            const card = buttonElement.closest('.book-card');
            const action = buttonElement.textContent.trim(); // "Borrow" or "Reserve"

            selectedBook = {
                id: parseInt(card.dataset.bookId),
                title: card.dataset.title,
                author: card.dataset.author,
                copies: parseInt(card.dataset.copies),
                cardElement: card,
                buttonElement: buttonElement
            };

            // Check if user has already borrowed this specific book
            if (getLoanList().some(l => l.bookId === selectedBook.id && l.status === 'borrowed')) {
                // This state should be caught by renderBookInventory, but is a safe check
                alert(`Error: You currently have a loan for "${selectedBook.title}".`);
                return;
            }

            if (action === 'Borrow') {
                const currentBorrowedBooks = getBorrowedCount(); // Use the corrected function
                if (currentBorrowedBooks >= BORROW_LIMIT) {
                    openModal('warningModal');
                    return;
                }
                openBorrowModal(selectedBook.title);

            } else if (action === 'Reserve') {
                const reservations = getReservations();
                if (reservations.some(r => r.id === selectedBook.id)) {
                    // This state should be caught by renderBookInventory
                    alert(`Error: You already have a reservation for "${selectedBook.title}".`);
                    return;
                }
                openReserveModal(selectedBook.title);
            }
        }

        // --- Borrow Modal Logic ---
        function openBorrowModal(bookTitle) {
            const dueDateString = formatDueDate(SEMESTER_END_DATE);
            document.getElementById('modalMessage').innerHTML =
                `Do you want to borrow <b>${bookTitle}</b>?`;
            document.getElementById('dueDateText').textContent = dueDateString;
            openModal('borrowModal');
        }

        function confirmBorrow() {
            if (!selectedBook) return;

            // 1. Update Inventory State (CRITICAL FIX)
            const bookToUpdate = bookInventory.find(b => b.id === selectedBook.id);
            if (bookToUpdate && bookToUpdate.copies > 0) {
                bookToUpdate.copies -= 1;
                saveBookInventory(bookInventory);
            } else {
                alert("Error: Cannot borrow. Book out of stock.");
                closeModal('borrowModal');
                return;
            }

            // 2. Update Loan List State
            let loans = getLoanList();
            const newLoan = {
                loanId: 'L' + Date.now(), // Unique loan ID
                bookId: selectedBook.id,
                title: selectedBook.title,
                author: selectedBook.author,
                borrowDate: new Date(), // Today
                dueDate: SEMESTER_END_DATE,
                status: 'borrowed'
            };
            loans.push(newLoan);
            saveLoanList(loans);

            // 3. Update UI (Card & Sidebar)
            renderBookInventory(); // Re-render all cards to reflect new stock/status
            updateSidebarCounts();

            alert(`SUCCESS: "${selectedBook.title}" borrowed. Due Date: ${formatDueDate(SEMESTER_END_DATE)}`);
            closeModal('borrowModal');
            selectedBook = null;
        }

        // --- Reserve Modal Logic ---
        function openReserveModal(bookTitle) {
            const expiryDate = calculateReservationExpiry();
            document.getElementById('reserveModalMessage').innerHTML =
                `You will reserve <b>${bookTitle}</b>. <br>Reservation expires in 7 days on <b>${formatDueDate(expiryDate)}</b>.`;
            document.getElementById('reserveExpiresText').textContent = formatDueDate(expiryDate);
            openModal('reserveModal');
        }

        function confirmReservation() {
            if (!selectedBook) return;

            const expiryDate = calculateReservationExpiry();
            let reservations = getReservations();

            // 1. Add to reservation state and save
            reservations.push({
                id: selectedBook.id,
                title: selectedBook.title,
                author: selectedBook.author,
                expiration: expiryDate.toISOString() // Store as ISO string for local storage
            });
            saveReservations(reservations);

            // 2. Update UI (Card & Sidebar)
            renderBookInventory(); // Re-render to show "Reserved" tag
            updateSidebarCounts();

            alert(`You have successfully reserved "${selectedBook.title}". View it in the Reservations tab.`);

            closeModal('reserveModal');
            selectedBook = null;
        }

        // --- Initialization ---
        document.addEventListener('DOMContentLoaded', () => {
            // 1. Load the book inventory state or use default
            bookInventory = getBookInventory();

            // 2. Render UI
            renderBookInventory();
            updateSidebarCounts();
        });

        // Attach event listeners to the confirm buttons
        document.getElementById('confirmBorrowBtn').addEventListener('click', confirmBorrow);
        document.getElementById('confirmReserveBtn').addEventListener('click', confirmReservation);

        window.onclick = function (event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>

</html>