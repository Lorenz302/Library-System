<?php
// backend/update_request_status.php
session_start();
include 'db_connect.php';
require_once 'send_request_email.php'; // Include mailer

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'librarian') {
    die("Unauthorized access.");
}

if (!isset($_POST['action'], $_POST['request_id'], $_POST['request_type'], $_POST['book_id'])) {
    die("Invalid request.");
}

$action = $_POST['action'];
$request_id = (int)$_POST['request_id'];
$request_type = $_POST['request_type'];
$book_id = (int)$_POST['book_id'];
$user_id_number = $_POST['user_id_number'] ?? '';

// 1. Fetch User Details for Emailing BEFORE updating
// We need the email and name of the person who made the request
$user_sql = "SELECT fullname, email FROM users WHERE id_number = ?";
$stmt_user = $conn->prepare($user_sql);
$stmt_user->bind_param("s", $user_id_number);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

// Fetch Book Name for Email
$book_sql = "SELECT book_name FROM books WHERE book_id = ?";
$stmt_book = $conn->prepare($book_sql);
$stmt_book->bind_param("i", $book_id);
$stmt_book->execute();
$book_data = $stmt_book->get_result()->fetch_assoc();
$stmt_book->close();

$recipientEmail = $user_data['email'] ?? '';
$recipientName = $user_data['fullname'] ?? 'Student';
$bookTitle = $book_data['book_name'] ?? 'Book';

$conn->begin_transaction();

try {
    $emailAction = ""; // To store which email template to use

    switch ($action) {
        case 'Approve':
            $stmt = $conn->prepare("UPDATE borrow_requests SET borrow_status = 'Approved' WHERE borrow_id = ? AND borrow_status = 'Pending'");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            
            $stmt2 = $conn->prepare("UPDATE books SET available_copies = available_copies - 1 WHERE book_id = ? AND available_copies > 0");
            $stmt2->bind_param("i", $book_id);
            $stmt2->execute();
            
            $emailAction = "Approve";
            break;

        case 'Reject':
            // Find status first
            $stmt_find = $conn->prepare("SELECT borrow_status FROM borrow_requests WHERE borrow_id = ?");
            $stmt_find->bind_param("i", $request_id);
            $stmt_find->execute();
            $req = $stmt_find->get_result()->fetch_assoc();
            $stmt_find->close();

            $stmt_update = $conn->prepare("UPDATE borrow_requests SET borrow_status = 'Rejected', due_date = NULL, return_date = NULL WHERE borrow_id = ?");
            $stmt_update->bind_param("i", $request_id);
            $stmt_update->execute();

            // Return copy if it was previously approved
            if ($req && $req['borrow_status'] === 'Approved') {
                $stmt_book = $conn->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE book_id = ?");
                $stmt_book->bind_param("i", $book_id);
                $stmt_book->execute();
            }
            $emailAction = "Reject";
            break;

        case 'MarkPickedUp':
            $stmt = $conn->prepare("UPDATE borrow_requests SET borrow_status = 'Borrowed' WHERE borrow_id = ?");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            // No email typically needed for pickup as they are physically there, but can add if desired
            break;

        case 'MarkReturned':
            $stmt = $conn->prepare("UPDATE borrow_requests SET borrow_status = 'Returned', return_date = NOW() WHERE borrow_id = ?");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            
            // Send "Returned" email to the borrower
            sendRequestStatusEmail($recipientEmail, $recipientName, $bookTitle, 'MarkReturned');

            // CHECK RESERVATIONS
            $stmt_next = $conn->prepare("SELECT reservation_id, id_number FROM reservation_requests WHERE book_id = ? AND reservation_status = 'Pending' ORDER BY reservation_date ASC LIMIT 1");
            $stmt_next->bind_param("i", $book_id);
            $stmt_next->execute();
            $res_next = $stmt_next->get_result();

            if ($res_next->num_rows > 0) {
                // Notify the NEXT person in line
                $next_res = $res_next->fetch_assoc();
                
                // Update status
                $stmt_upd = $conn->prepare("UPDATE reservation_requests SET reservation_status = 'Available', notified_date = NOW(), pickup_expiry_date = NOW() + INTERVAL 48 HOUR WHERE reservation_id = ?");
                $stmt_upd->bind_param("i", $next_res['reservation_id']);
                $stmt_upd->execute();

                // GET NEXT PERSON'S EMAIL
                $next_user_sql = "SELECT fullname, email FROM users WHERE id_number = ?";
                $stmt_nu = $conn->prepare($next_user_sql);
                $stmt_nu->bind_param("s", $next_res['id_number']);
                $stmt_nu->execute();
                $next_user = $stmt_nu->get_result()->fetch_assoc();
                
                // Send Email to the Reserver
                if ($next_user) {
                    sendRequestStatusEmail($next_user['email'], $next_user['fullname'], $bookTitle, 'Available');
                }

            } else {
                // No reservations, return copy to stock
                $stmt_bk = $conn->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE book_id = ?");
                $stmt_bk->bind_param("i", $book_id);
                $stmt_bk->execute();
            }
            break;

        case 'Fulfill': // Reservation -> Borrow
            $stmt = $conn->prepare("UPDATE reservation_requests SET reservation_status = 'Fulfilled' WHERE reservation_id = ?");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();

            $borrow_date = date('Y-m-d');
            $due_date = date('Y-m-d', strtotime('+14 days'));
            $stmt_ins = $conn->prepare("INSERT INTO borrow_requests (id_number, book_id, borrow_date, due_date, borrow_status) VALUES (?, ?, ?, ?, 'Borrowed')");
            $stmt_ins->bind_param("siss", $user_id_number, $book_id, $borrow_date, $due_date);
            $stmt_ins->execute();
            break;

        case 'CancelReservation':
            $stmt = $conn->prepare("UPDATE reservation_requests SET reservation_status = 'Cancelled' WHERE reservation_id = ?");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $emailAction = "Reject"; // Reuse reject template for cancellation
            break;

        case 'MarkAsExpired':
            $stmt = $conn->prepare("UPDATE reservation_requests SET reservation_status = 'Expired' WHERE reservation_id = ?");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            
            $emailAction = "MarkAsExpired";

            // Check next in line
            $stmt_next2 = $conn->prepare("SELECT reservation_id, id_number FROM reservation_requests WHERE book_id = ? AND reservation_status = 'Pending' ORDER BY reservation_date ASC LIMIT 1");
            $stmt_next2->bind_param("i", $book_id);
            $stmt_next2->execute();
            $res_next2 = $stmt_next2->get_result();

            if ($res_next2->num_rows > 0) {
                $next_res2 = $res_next2->fetch_assoc();
                $stmt_upd2 = $conn->prepare("UPDATE reservation_requests SET reservation_status = 'Available', notified_date = NOW(), pickup_expiry_date = NOW() + INTERVAL 48 HOUR WHERE reservation_id = ?");
                $stmt_upd2->bind_param("i", $next_res2['reservation_id']);
                $stmt_upd2->execute();
                
                // Notify next person
                $next_user_sql2 = "SELECT fullname, email FROM users WHERE id_number = ?";
                $stmt_nu2 = $conn->prepare($next_user_sql2);
                $stmt_nu2->bind_param("s", $next_res2['id_number']);
                $stmt_nu2->execute();
                $next_user2 = $stmt_nu2->get_result()->fetch_assoc();
                if($next_user2) {
                    sendRequestStatusEmail($next_user2['email'], $next_user2['fullname'], $bookTitle, 'Available');
                }
            } else {
                $stmt_bk2 = $conn->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE book_id = ?");
                $stmt_bk2->bind_param("i", $book_id);
                $stmt_bk2->execute();
            }
            break;
    }

    $conn->commit();

    // Send Email if an action was set
    if (!empty($emailAction) && !empty($recipientEmail)) {
        sendRequestStatusEmail($recipientEmail, $recipientName, $bookTitle, $emailAction);
    }

    $status = 'success';
} catch (Exception $e) {
    $conn->rollback();
    $status = 'error';
}

// Redirect preserving params
$query = $_POST;
unset($query['action'], $query['request_id'], $query['request_type'], $query['book_id'], $query['user_id_number']);
$query['update'] = $status;
header("Location: ../frontend/manage_requests.php?" . http_build_query($query));
exit;
?>