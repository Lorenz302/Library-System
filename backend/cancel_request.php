<?php
// backend/cancel_request.php
session_start();
include 'db_connect.php';

// Security check: Ensure the user is logged in.
if (!isset($_SESSION['id_number'])) {
    header("Location: ../frontend/index.html");
    exit();
}

// Check if the required data was sent via POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['request_id'], $_POST['request_type'])) {

    $request_id = (int)$_POST['request_id'];
    $request_type = $_POST['request_type'];
    $id_number = $_SESSION['id_number'];

    $conn->begin_transaction(); // Use a transaction for safety

    try {
        if ($request_type === 'borrow') {
            // --- LOGIC FOR CANCELLING A BORROW REQUEST ---

            // First, find the request to ensure it belongs to the user and get its details
            $stmt_find = $conn->prepare("SELECT book_id, borrow_status FROM borrow_requests WHERE borrow_id = ? AND id_number = ?");
            $stmt_find->bind_param("is", $request_id, $id_number);
            $stmt_find->execute();
            $result = $stmt_find->get_result();
            
            if ($result->num_rows > 0) {
                $request = $result->fetch_assoc();
                $book_id_to_update = $request['book_id'];
                $status = $request['borrow_status'];

                // The user can only cancel 'Pending' or 'Approved' requests
                if ($status === 'Pending' || $status === 'Approved') {
                    // Delete the request from the borrow_requests table
                    $stmt_delete = $conn->prepare("DELETE FROM borrow_requests WHERE borrow_id = ?");
                    $stmt_delete->bind_param("i", $request_id);
                    $stmt_delete->execute();
                    
                    // IMPORTANT: If the request was already 'Approved', a book copy was being held.
                    // We must return it to circulation.
                    if ($status === 'Approved') {
                        $stmt_book = $conn->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE book_id = ?");
                        $stmt_book->bind_param("i", $book_id_to_update);
                        $stmt_book->execute();
                    }
                }
            }
            $stmt_find->close();

        } elseif ($request_type === 'reservation') {
            // --- LOGIC FOR CANCELLING A RESERVATION ---
            
            // Find the reservation to get its details
            $stmt_find = $conn->prepare("SELECT book_id, reservation_status FROM reservation_requests WHERE reservation_id = ? AND id_number = ?");
            $stmt_find->bind_param("is", $request_id, $id_number);
            $stmt_find->execute();
            $result = $stmt_find->get_result();

            if ($result->num_rows > 0) {
                $request = $result->fetch_assoc();
                $book_id_to_update = $request['book_id'];
                $status = $request['reservation_status'];

                // Delete the user's reservation
                $stmt_delete = $conn->prepare("DELETE FROM reservation_requests WHERE reservation_id = ?");
                $stmt_delete->bind_param("i", $request_id);
                $stmt_delete->execute();

                // If the reservation status was 'Available', a book was on hold for this user.
                // We must now process the queue for that book again.
                if ($status === 'Available') {
                    // Check for the next person in the queue for this specific book
                    $stmt_next = $conn->prepare("SELECT reservation_id FROM reservation_requests WHERE book_id = ? AND reservation_status = 'Pending' ORDER BY reservation_date ASC LIMIT 1");
                    $stmt_next->bind_param("i", $book_id_to_update);
                    $stmt_next->execute();
                    $result_next = $stmt_next->get_result();

                    if ($result_next->num_rows > 0) {
                        // If another user is waiting, make the book available for them.
                        $next_reservation = $result_next->fetch_assoc();
                        $stmt_update_res = $conn->prepare("UPDATE reservation_requests SET reservation_status = 'Available', notified_date = NOW(), pickup_expiry_date = NOW() + INTERVAL 48 HOUR WHERE reservation_id = ?");
                        $stmt_update_res->bind_param("i", $next_reservation['reservation_id']);
                        $stmt_update_res->execute();
                    } else {
                        // If no one else is waiting, return the book to public circulation.
                        $stmt_book = $conn->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE book_id = ?");
                        $stmt_book->bind_param("i", $book_id_to_update);
                        $stmt_book->execute();
                    }
                }
            }
            $stmt_find->close();
        }

        $conn->commit(); // Finalize the changes
        header("Location: ../frontend/reservation-section.php?cancel=success");
        exit();

    } catch (Exception $e) {
        $conn->rollback(); // Undo all changes if an error occurred
        // You can log the error for debugging: error_log($e->getMessage());
        header("Location: ../frontend/reservation-section.php?cancel=error");
        exit();
    }

} else {
    // If data is missing, just redirect
    header("Location: ../frontend/reservation-section.php");
    exit();
}

$conn->close();
?>