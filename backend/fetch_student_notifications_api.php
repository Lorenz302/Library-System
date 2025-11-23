<?php
// backend/fetch_book_status_api.php
session_start();
include 'db_connect.php';
header('Content-Type: application/json');

// Security Check
if (!isset($_SESSION['id_number'])) {
    echo json_encode([]);
    exit;
}

$user_id = $_SESSION['id_number'];

// 1. Get user's active interactions (Book IDs they are currently borrowing or reserving)
$user_status_map = [];

// Check Borrows (Pending or Approved means they can't borrow again yet)
$sql_borrow = "SELECT book_id FROM borrow_requests WHERE id_number = ? AND borrow_status IN ('Pending', 'Approved')";
$stmt_b = $conn->prepare($sql_borrow);
$stmt_b->bind_param("s", $user_id);
$stmt_b->execute();
$res_b = $stmt_b->get_result();
while ($row = $res_b->fetch_assoc()) {
    $user_status_map[$row['book_id']] = 'borrowed';
}
$stmt_b->close();

// Check Reservations (Pending or Available)
$sql_reserve = "SELECT book_id FROM reservation_requests WHERE id_number = ? AND reservation_status IN ('Pending', 'Available')";
$stmt_r = $conn->prepare($sql_reserve);
$stmt_r->bind_param("s", $user_id);
$stmt_r->execute();
$res_r = $stmt_r->get_result();
while ($row = $res_r->fetch_assoc()) {
    $user_status_map[$row['book_id']] = 'reserved';
}
$stmt_r->close();

// 2. Fetch Book Quantities
$sql = "SELECT book_id, available_copies FROM books";
$result = $conn->query($sql);

$books = [];
while ($row = $result->fetch_assoc()) {
    $id = $row['book_id'];
    
    // Determine the specific status for this user
    $user_status = 'none';
    if (isset($user_status_map[$id])) {
        $user_status = $user_status_map[$id];
    }

    $books[] = [
        'id' => $id,
        'qty' => (int)$row['available_copies'],
        'user_status' => $user_status // 'none', 'borrowed', or 'reserved'
    ];
}

echo json_encode($books);
$conn->close();
?>