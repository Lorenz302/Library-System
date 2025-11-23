<?php
// backend/borrow_book.php
session_start();
// Turn off error display to prevent HTML from breaking JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

include 'db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Initialize response
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if (!isset($_SESSION['id_number']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized or invalid request.']);
    exit;
}

$id_number = $_SESSION['id_number'];
$book_id = (int)$_POST['book_id'];
$return_date = $_POST['return_date']; 
$borrow_date = date('Y-m-d');

// 1. Check if copies are available
$check_sql = "SELECT available_copies, book_name FROM books WHERE book_id = ?";
$stmt_check = $conn->prepare($check_sql);
$stmt_check->bind_param("i", $book_id);
$stmt_check->execute();
$res = $stmt_check->get_result();
$book = $res->fetch_assoc();
$stmt_check->close();

if ($book && $book['available_copies'] > 0) {
    
    // 2. Fetch User Details
    $u_sql = "SELECT fullname, email, program_and_year FROM users WHERE id_number = ?";
    $u_stmt = $conn->prepare($u_sql);
    $u_stmt->bind_param("s", $id_number);
    $u_stmt->execute();
    $u_res = $u_stmt->get_result();
    $u_data = $u_res->fetch_assoc();
    $u_stmt->close();

    if (!$u_data) {
        echo json_encode(['success' => false, 'message' => 'User details not found.']);
        exit;
    }

    // Map Data
    $fullname = trim($u_data['fullname']);
    $parts = explode(' ', $fullname, 2); 
    $firstname = $parts[0];
    $lastname = isset($parts[1]) ? $parts[1] : ''; 

    $gsuit_account = $u_data['email'];
    $program = $u_data['program_and_year'];

    $conn->begin_transaction();

    try {
        // 3. Insert Request
        $sql_insert = "INSERT INTO borrow_requests 
            (id_number, firstname, lastname, gsuit_account, program_and_year, book_id, borrow_date, due_date, borrow_status, is_overdue_notified) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 0)";
        
        $stmt_insert = $conn->prepare($sql_insert);
        if (!$stmt_insert) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        // ============================================================
        // FIX IS HERE: "sssssiss" (8 characters for 8 variables)
        // ============================================================
        $stmt_insert->bind_param("sssssiss", $id_number, $firstname, $lastname, $gsuit_account, $program, $book_id, $borrow_date, $return_date);
        
        if (!$stmt_insert->execute()) {
            throw new Exception("Execute failed: " . $stmt_insert->error);
        }
        $stmt_insert->close();

        // 4. Decrease Quantity
        $sql_update = "UPDATE books SET available_copies = available_copies - 1 WHERE book_id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("i", $book_id);
        $stmt_update->execute();
        $stmt_update->close();

        $conn->commit();
        
        $response['success'] = true;
        $response['message'] = 'Request submitted successfully!';

    } catch (Exception $e) {
        $conn->rollback();
        $response['success'] = false;
        $response['message'] = 'Database Error: ' . $e->getMessage();
    }
} else {
    $response['success'] = false;
    $response['message'] = 'This book is currently out of stock.';
}

$conn->close();
echo json_encode($response);
exit;
?>