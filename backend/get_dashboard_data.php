<?php
// backend/get_dashboard_data.php

// Include the main database connection file
include 'db_connect.php';

// --- 1. Get Pending Requests ---
// This is the sum of pending borrow requests AND pending reservation requests.

// Get pending borrow requests
$sql_pending_borrows = "SELECT COUNT(*) as count FROM borrow_requests WHERE borrow_status = 'Pending'";
$result_pending_borrows = $conn->query($sql_pending_borrows);
$pending_borrows = $result_pending_borrows->fetch_assoc()['count'];

// Get pending reservation requests
$sql_pending_reservations = "SELECT COUNT(*) as count FROM reservation_requests WHERE reservation_status = 'Pending'";
$result_pending_reservations = $conn->query($sql_pending_reservations);
$pending_reservations = $result_pending_reservations->fetch_assoc()['count'];

// Add them together for the total
$pending_requests_count = $pending_borrows + $pending_reservations;


// --- 2. Get Total Books ---
// This is the total number of unique book titles in the library.
$sql_total_books = "SELECT COUNT(*) as count FROM books";
$result_total_books = $conn->query($sql_total_books);
$total_books_count = $result_total_books->fetch_assoc()['count'];


// --- 3. Get Books on Loan ---
// This is calculated by finding the difference between total copies and available copies.
$sql_books_on_loan = "SELECT SUM(total_copies) - SUM(available_copies) as count FROM books";
$result_books_on_loan = $conn->query($sql_books_on_loan);
// The result can be NULL if there are no books, so we use '?? 0' to default to 0.
$books_on_loan_count = $result_books_on_loan->fetch_assoc()['count'] ?? 0;


// --- 4. Get Total Users ---
// This counts all registered users (students and librarians).
$sql_total_users = "SELECT COUNT(*) as count FROM users";
$result_total_users = $conn->query($sql_total_users);
$total_users_count = $result_total_users->fetch_assoc()['count'];

// Note: We don't close the connection here ($conn->close()) because the main dashboard
// script might still need it. The connection will close automatically when the script ends.

?>