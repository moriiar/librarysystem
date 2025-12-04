# 📚 Smart Library Web System

![Library System Illustration](public/src/image.png)

> **Project Title:** Smart Library Web System for Multi-User Management

A comprehensive, role-based web application designed to manage library operations efficiently. This system facilitates borrowing, returning, reserving, and managing books with specific rules and responsibilities for Students, Teachers, Librarians, and Staff.

---

## 🚀 Project Overview

The objective of this project is to streamline library transactions and inventory management through a user-friendly web interface. It enforces role-specific rules, such as borrowing limits for students and clearance requirements for all users.

### 🔑 Key Features

* **Secure Authentication:** Role-based login system protecting access to specific dashboards. 
* **Dynamic Inventory:** Real-time tracking of book stock, availability, and active reservations. 
* **Circulation Management:** Complete module for borrowing and returning books. 
* **Reservation System:** Online booking system for Students and Teachers. 
* **Penalty Automation:** Automatic calculation of fines for overdue or damaged items.

---

## 👥 User Roles & Responsibilities

The system supports four distinct user roles, each with unique permissions:

### 🎓 1. Student 
* **Borrowing Limit:** Can borrow up to **3 books** per semester. 
* **Reservations:** Ability to reserve books online. 
* **Clearance:** Must return all books to be cleared; otherwise, liable for the book price. 

### 🍎 2. Teacher 
* **Borrowing Limit:** **Unlimited** book borrowing privileges. 
* **Reservations:** Ability to reserve books online. 
* **Clearance:** Mandatory return of all items at the end of the semester. 

### 📖 3. Librarian 
* **Inventory Control:** Add new books, update details, and archive old records. 
* **Metadata Management:** Manage book categories, authors, and cover images. 

### 🛡️ 4. Staff 
* **Operations:** Facilitates the actual borrowing and returning process at the desk. 
* **Oversight:** View borrower status, check for overdue items, and manage penalties.

---

## 🛠️ Technology Stack

* **Backend:** Vanilla PHP (MVC Structure)
* **Database:** MySQL (Relational Database)
* **Frontend:** HTML5, CSS3 (Poppins Typography), JavaScript (ES6)
* **APIs:** Open Library API (for fetching book covers)

---

## 📂 Folder Structure

```text
librarysystem/
├── app/
│   ├── controllers/      # Business logic (Book, Staff, Librarian controllers)
│   ├── models/           # Database connection & helpers
│   └── views/            # User Interface files (Dashboards, Login, etc.)
├── public/
│   ├── css/              # Custom stylesheets
│   ├── js/               # Client-side scripts & modal logic
│   └── src/              # Images and assets
├── config.php            # Database configuration settings
├── database_schema       # SQL file for database setup
├── fetch_book_covers.php # Script to auto-fetch book covers
├── hash_generator.php    # Utility for password hashing
└── index.php             # Application entry point
