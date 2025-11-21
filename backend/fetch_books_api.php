<?php
// backend/fetch_books_api.php
// =======================================================================================
// WHAT THIS FILE DOES:
// This is an API Endpoint. It outputs Book data in JSON format.
// It is called by the JavaScript in 'manage_books.php' every few seconds to:
// 1. Update availability counts (Real-Time).
// 2. Handle live searching without page reloads.
// 3. Handle category filtering.
// =======================================================================================

session_start();
include 'db_connect.php';

// Set Header
header('Content-Type: application/json');

// Security
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'librarian') {
    echo json_encode([]); // Return empty array if unauthorized
    exit;
}

// --- GET PARAMETERS ---
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? 'all';

// --- BUILD QUERY ---
$sql = "SELECT * FROM books WHERE 1=1"; // 1=1 allows appending AND conditions easily
$params = [];
$types = "";

// Search Logic
if (!empty($search)) {
    $sql .= " AND (book_name LIKE ? OR author LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

// Filter Logic
if ($category !== 'all') {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

// Ordering
$sql .= " ORDER BY book_name ASC";

// --- EXECUTE ---
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$books = [];
while ($row = $result->fetch_assoc()) {
    // Add a logic to determine status text/color
    $row['status_class'] = ($row['available_copies'] > 0) ? 'status-available' : 'status-out';
    $row['status_text'] = ($row['available_copies'] > 0) ? 'Available' : 'Out of Stock';
    $books[] = $row;
}

echo json_encode($books);
$conn->close();
?>