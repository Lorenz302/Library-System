<?php
// backend/get_dashboard_data.php

session_start();
include 'db_connect.php';

// Set header to JSON
header('Content-Type: application/json');

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'librarian') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// --- 1. GET COUNTS ---

// Pending Requests
$sql_pending = "SELECT 
    (SELECT COUNT(*) FROM borrow_requests WHERE borrow_status = 'Pending') + 
    (SELECT COUNT(*) FROM reservation_requests WHERE reservation_status = 'Pending') 
    AS total";
$pending_count = $conn->query($sql_pending)->fetch_assoc()['total'];

// Total Books
$sql_books = "SELECT COUNT(*) as count FROM books";
$total_books = $conn->query($sql_books)->fetch_assoc()['count'];

// Books on Loan (Total Copies - Available Copies)
$sql_loan = "SELECT SUM(total_copies) - SUM(available_copies) as count FROM books";
$loan_result = $conn->query($sql_loan)->fetch_assoc()['count'];
$books_on_loan = $loan_result ?? 0;

// Total Users
$sql_users = "SELECT COUNT(*) as count FROM users";
$total_users = $conn->query($sql_users)->fetch_assoc()['count'];


// --- 2. GET RECENT ACTIVITY FEED (The "Useful" Feature) ---
// We combine Borrow and Reservation requests, sort by date, and take the top 5.
$sql_activity = "
    SELECT user_name, book_title, request_type, request_date, status FROM (
        (SELECT 
            u.fullname AS user_name, 
            b.book_name AS book_title, 
            'Borrow' AS request_type, 
            br.borrow_date AS request_date,
            br.borrow_status AS status
         FROM borrow_requests br
         JOIN users u ON br.id_number = u.id_number
         JOIN books b ON br.book_id = b.book_id
         ORDER BY br.borrow_date DESC LIMIT 5)
        UNION ALL
        (SELECT 
            u.fullname AS user_name, 
            b.book_name AS book_title, 
            'Reservation' AS request_type, 
            rr.reservation_date AS request_date,
            rr.reservation_status AS status
         FROM reservation_requests rr
         JOIN users u ON rr.id_number = u.id_number
         JOIN books b ON rr.book_id = b.book_id
         ORDER BY rr.reservation_date DESC LIMIT 5)
    ) AS combined_activity
    ORDER BY request_date DESC LIMIT 5
";

$activity_result = $conn->query($sql_activity);
$recent_activities = [];

while($row = $activity_result->fetch_assoc()) {
    // Add a human-readable time (e.g., "2 hours ago" or just the date)
    $row['formatted_date'] = date('M d, h:i A', strtotime($row['request_date']));
    $recent_activities[] = $row;
}

// --- 3. OUTPUT JSON ---
echo json_encode([
    'stats' => [
        'pending_requests' => $pending_count,
        'total_books' => $total_books,
        'books_on_loan' => $books_on_loan,
        'total_users' => $total_users
    ],
    'recent_activity' => $recent_activities
]);

$conn->close();
exit;
?>