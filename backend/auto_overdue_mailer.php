<?php
// backend/auto_overdue_mailer.php
// This script runs every 10 seconds via the Admin Dashboard.

session_start();
include 'db_connect.php';
require_once 'send_request_email.php'; 

// Security: Only Admins/Librarians can trigger this
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'librarian' && $_SESSION['role'] !== 'admin')) {
    exit;
}

$today = date('Y-m-d');
$report = ['overdue_sent' => 0, 'expired_updated' => 0];

// ==================================================================================
// JOB 1: NOTIFY OVERDUE BORROWS (Already Implemented)
// ==================================================================================
$sql_overdue = "
    SELECT br.borrow_id, br.due_date, u.email, u.fullname, b.book_name 
    FROM borrow_requests br
    JOIN users u ON br.id_number = u.id_number
    JOIN books b ON br.book_id = b.book_id
    WHERE br.borrow_status = 'Borrowed' 
    AND br.due_date < ? 
    AND br.is_overdue_notified = 0
";

$stmt = $conn->prepare($sql_overdue);
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $sent = sendRequestStatusEmail($row['email'], $row['fullname'], $row['book_name'], 'Overdue');
    if ($sent) {
        $conn->query("UPDATE borrow_requests SET is_overdue_notified = 1 WHERE borrow_id = " . $row['borrow_id']);
        $report['overdue_sent']++;
    }
}
$stmt->close();


// ==================================================================================
// JOB 2: EXPIRE UNCLAIMED APPROVALS (The New Feature)
// Logic: If status is 'Approved' AND 'due_date' (or a pickup expiry date) has passed
// Note: Since 'Approved' requests usually share the 'due_date' field as the "Return Date if borrowed",
//       we assume that if the *Due Date* passes and it's still just "Approved" (not "Borrowed"), 
//       they never picked it up.
// ==================================================================================

$sql_expired = "
    SELECT br.borrow_id, br.book_id, u.email, u.fullname, b.book_name
    FROM borrow_requests br
    JOIN users u ON br.id_number = u.id_number
    JOIN books b ON br.book_id = b.book_id
    WHERE br.borrow_status = 'Approved'
    AND br.due_date < ? 
";

$stmt_exp = $conn->prepare($sql_expired);
$stmt_exp->bind_param("s", $today);
$stmt_exp->execute();
$result_exp = $stmt_exp->get_result();

while ($row = $result_exp->fetch_assoc()) {
    // 1. Mark as 'Expired'
    $conn->query("UPDATE borrow_requests SET borrow_status = 'Expired' WHERE borrow_id = " . $row['borrow_id']);

    // 2. RETURN QUANTITY TO STOCK
    $conn->query("UPDATE books SET available_copies = available_copies + 1 WHERE book_id = " . $row['book_id']);

    // 3. (Optional) Send Email Notification that it was cancelled
    sendRequestStatusEmail($row['email'], $row['fullname'], $row['book_name'], 'MarkAsExpired');

    $report['expired_updated']++;
}
$stmt_exp->close();

echo json_encode(['status' => 'success', 'report' => $report]);
$conn->close();
?>