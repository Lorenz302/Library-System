<?php
// backend/fetch_my_requests_api.php
session_start();
include 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['id_number'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['id_number'];
$response = [
    'on_loan' => [],
    'pickup' => [],
    'pending' => []
];

// 1. Fetch ON LOAN
$sql_borrowed = "SELECT b.book_name, b.book_description, b.book_image_path, br.due_date
                 FROM borrow_requests br
                 JOIN books b ON br.book_id = b.book_id
                 WHERE br.id_number = ? AND br.borrow_status = 'Borrowed'
                 ORDER BY br.due_date ASC";
$stmt = $conn->prepare($sql_borrowed);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) {
    $row['formatted_due_date'] = date('F j, Y', strtotime($row['due_date']));
    $response['on_loan'][] = $row;
}
$stmt->close();

// 2. Fetch READY FOR PICKUP
// (Approved Borrows)
$sql_approved = "SELECT br.borrow_id, 'borrow' as type, b.book_name, b.book_description, b.book_image_path, NULL as pickup_expiry
                 FROM borrow_requests br
                 JOIN books b ON br.book_id = b.book_id
                 WHERE br.id_number = ? AND br.borrow_status = 'Approved'";
$stmt = $conn->prepare($sql_approved);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) {
    $row['status_msg'] = "Approved! Please pick up within 3 days.";
    $response['pickup'][] = $row;
}
$stmt->close();

// (Available Reservations)
$sql_avail = "SELECT rr.reservation_id as borrow_id, 'reservation' as type, b.book_name, b.book_description, b.book_image_path, rr.pickup_expiry_date as pickup_expiry
              FROM reservation_requests rr
              JOIN books b ON rr.book_id = b.book_id
              WHERE rr.id_number = ? AND rr.reservation_status = 'Available'";
$stmt = $conn->prepare($sql_avail);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) {
    $expiry = date('F j, Y, g:i a', strtotime($row['pickup_expiry']));
    $row['status_msg'] = "Available! Pick up by: $expiry";
    $response['pickup'][] = $row;
}
$stmt->close();

// 3. Fetch PENDING
// (Pending Borrows)
$sql_p_bor = "SELECT br.borrow_id, 'borrow' as type, b.book_name, b.book_description, b.book_image_path
              FROM borrow_requests br
              JOIN books b ON br.book_id = b.book_id
              WHERE br.id_number = ? AND br.borrow_status = 'Pending'";
$stmt = $conn->prepare($sql_p_bor);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) {
    $row['status_msg'] = "Awaiting Librarian Approval";
    $response['pending'][] = $row;
}
$stmt->close();

// (Pending Reservations - Need Queue Position)
$sql_p_res = "SELECT rr.reservation_id as borrow_id, 'reservation' as type, b.book_name, b.book_description, b.book_image_path, rr.book_id, rr.reservation_date
              FROM reservation_requests rr
              JOIN books b ON rr.book_id = b.book_id
              WHERE rr.id_number = ? AND rr.reservation_status = 'Pending'
              ORDER BY rr.reservation_date ASC";
$stmt = $conn->prepare($sql_p_res);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) {
    // Calculate Queue Position
    // Logic: Count how many pending reservations for this book exist with a date <= mine
    $q_sql = "SELECT COUNT(*) as pos FROM reservation_requests WHERE book_id = ? AND reservation_status = 'Pending' AND reservation_date <= ?";
    $stmt_q = $conn->prepare($q_sql);
    $stmt_q->bind_param("is", $row['book_id'], $row['reservation_date']);
    $stmt_q->execute();
    $pos = $stmt_q->get_result()->fetch_assoc()['pos'];
    $stmt_q->close();

    $row['status_msg'] = "You are #$pos in the queue.";
    $response['pending'][] = $row;
}
$stmt->close();

echo json_encode($response);
$conn->close();
?>