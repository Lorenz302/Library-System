<?php
// backend/update_request_status.php
session_start();
include 'db_connect.php';

// Security check: Ensure the user is a logged-in librarian
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'librarian') {
    die("Unauthorized access.");
}

if (!isset($_POST['action'], $_POST['request_id'], $_POST['request_type'], $_POST['book_id'])) {
    die("Invalid request. Missing required data.");
}

$action = $_POST['action'];
$request_id = (int)$_POST['request_id'];
$request_type = $_POST['request_type'];
$book_id = (int)$_POST['book_id'];
$user_id_number = $_POST['user_id_number'] ?? '';

$conn->begin_transaction();

try {
    switch ($action) {
        // --- BORROW REQUEST ACTIONS ---
        case 'Approve':
            $stmt = $conn->prepare("UPDATE borrow_requests SET borrow_status = 'Approved' WHERE borrow_id = ? AND borrow_status = 'Pending'");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            
            $stmt2 = $conn->prepare("UPDATE books SET available_copies = available_copies - 1 WHERE book_id = ? AND available_copies > 0");
            $stmt2->bind_param("i", $book_id);
            $stmt2->execute();
            break;

        case 'Reject':
            $stmt = $conn->prepare("UPDATE borrow_requests SET borrow_status = 'Rejected' WHERE borrow_id = ?");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            break;

        case 'MarkPickedUp':
            $stmt = $conn->prepare("UPDATE borrow_requests SET borrow_status = 'Borrowed' WHERE borrow_id = ?");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            break;

        case 'MarkReturned':
            $stmt = $conn->prepare("UPDATE borrow_requests SET borrow_status = 'Returned', return_date = NOW() WHERE borrow_id = ?");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            
            $stmt_next = $conn->prepare("SELECT reservation_id FROM reservation_requests WHERE book_id = ? AND reservation_status = 'Pending' ORDER BY reservation_date ASC LIMIT 1");
            $stmt_next->bind_param("i", $book_id);
            $stmt_next->execute();
            $result_next = $stmt_next->get_result();

            if ($result_next->num_rows > 0) {
                $next_reservation = $result_next->fetch_assoc();
                $next_reservation_id = $next_reservation['reservation_id'];
                
                $stmt_update_res = $conn->prepare("UPDATE reservation_requests SET reservation_status = 'Available', notified_date = NOW(), pickup_expiry_date = NOW() + INTERVAL 48 HOUR WHERE reservation_id = ?");
                $stmt_update_res->bind_param("i", $next_reservation_id);
                $stmt_update_res->execute();
            } else {
                $stmt_book = $conn->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE book_id = ?");
                $stmt_book->bind_param("i", $book_id);
                $stmt_book->execute();
            }
            break;

        // --- RESERVATION ACTIONS ---
        case 'Fulfill':
            $stmt = $conn->prepare("UPDATE reservation_requests SET reservation_status = 'Fulfilled' WHERE reservation_id = ?");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();

            $borrow_date = date('Y-m-d');
            $due_date = date('Y-m-d', strtotime('+14 days'));
            $stmt_insert = $conn->prepare("INSERT INTO borrow_requests (id_number, book_id, borrow_date, due_date, borrow_status) VALUES (?, ?, ?, ?, 'Borrowed')");
            $stmt_insert->bind_param("siss", $user_id_number, $book_id, $borrow_date, $due_date);
            $stmt_insert->execute();
            break;

        case 'CancelReservation':
            $stmt = $conn->prepare("UPDATE reservation_requests SET reservation_status = 'Cancelled' WHERE reservation_id = ?");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            break;

        // =========================================================================
        // ============================ THIS IS THE FIX ============================
        // =========================================================================
        case 'MarkAsExpired': // Corrected from 'MarkAsNoShow'
            // Step 1: Mark the current reservation as 'Expired'
            $stmt = $conn->prepare("UPDATE reservation_requests SET reservation_status = 'Expired' WHERE reservation_id = ?");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            
            // Step 2: Since this person missed their chance, immediately check for the next person in the queue
            $stmt_next2 = $conn->prepare("SELECT reservation_id FROM reservation_requests WHERE book_id = ? AND reservation_status = 'Pending' ORDER BY reservation_date ASC LIMIT 1");
            $stmt_next2->bind_param("i", $book_id);
            $stmt_next2->execute();
            $result_next2 = $stmt_next2->get_result();

            if ($result_next2->num_rows > 0) {
                // If someone else is waiting, make the book 'Available' for them
                $next_reservation2 = $result_next2->fetch_assoc();
                $next_reservation_id2 = $next_reservation2['reservation_id'];
                
                $stmt_update_res2 = $conn->prepare("UPDATE reservation_requests SET reservation_status = 'Available', notified_date = NOW(), pickup_expiry_date = NOW() + INTERVAL 48 HOUR WHERE reservation_id = ?");
                $stmt_update_res2->bind_param("i", $next_reservation_id2);
                $stmt_update_res2->execute();
            } else {
                // If NO ONE is waiting, make the book available to the public
                $stmt_book2 = $conn->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE book_id = ?");
                $stmt_book2->bind_param("i", $book_id);
                $stmt_book2->execute();
            }
            break;
        // =========================================================================

        default:
            throw new Exception("Unknown action specified.");
    }

    $conn->commit();
    $redirect_status = 'success';

} catch (Exception $e) {
    $conn->rollback();
    // For debugging: error_log($e->getMessage());
    $redirect_status = 'error';
}

// Redirect back to the manage requests page, preserving filters
$query_params = [
    'search' => $_POST['search'] ?? '',
    'type' => $_POST['type'] ?? 'all',
    'status' => $_POST['status'] ?? 'all',
    'sort' => $_POST['sort'] ?? 'DESC',
    'page' => $_POST['page'] ?? 1,
    'update' => $redirect_status
];

header("Location: ../frontend/manage_requests.php?" . http_build_query($query_params));
$conn->close();
exit();
?>