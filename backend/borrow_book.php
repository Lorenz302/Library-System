<?php
// backend/borrow_book.php
session_start();
include 'db_connect.php';

// Check if user is logged in and is a student
if (!isset($_SESSION["id_number"]) || $_SESSION['role'] !== 'student') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $book_id = (int)$_POST['book_id'];
    $id_number = $_SESSION['id_number'];
    $due_date = $_POST['return_date']; 
    $borrow_date = date('Y-m-d');

    // --- FETCH ALL NECESSARY USER DETAILS ---
    $user_sql = "SELECT fullname, email, program_and_year FROM users WHERE id_number = ? LIMIT 1";
    $stmt_user = $conn->prepare($user_sql);
    $stmt_user->bind_param("s", $id_number);
    $stmt_user->execute();
    $user_result = $stmt_user->get_result();
    
    if ($user_result->num_rows > 0) {
        $user = $user_result->fetch_assoc();
        
        $name_parts = explode(' ', $user['fullname'], 2);
        $firstname = $name_parts[0];
        $lastname = isset($name_parts[1]) ? $name_parts[1] : '';

        $gsuit_account = $user['email'];
        $program_and_year = $user['program_and_year'];

    } else {
        header("Location: ../frontend/home.php?borrow_status=error&msg=" . urlencode("User details not found."));
        exit;
    }
    $stmt_user->close();

    // Start a transaction to ensure data integrity
    $conn->begin_transaction();

    try {
        // 1. Check if the book has available copies and lock the row for update
        $check_sql = "SELECT available_copies FROM books WHERE book_id = ? AND available_copies > 0 FOR UPDATE";
        $stmt_check = $conn->prepare($check_sql);
        $stmt_check->bind_param("i", $book_id);
        $stmt_check->execute();
        
        if ($stmt_check->get_result()->num_rows > 0) {
            // 2. Insert the borrow request with all the fetched user details
            $insert_sql = "INSERT INTO borrow_requests 
                           (id_number, firstname, lastname, gsuit_account, program_and_year, book_id, borrow_date, due_date) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($insert_sql);
            $stmt_insert->bind_param("sssssiss", $id_number, $firstname, $lastname, $gsuit_account, $program_and_year, $book_id, $borrow_date, $due_date);
            $stmt_insert->execute();

            $conn->commit();
            header("Location: ../frontend/home.php?borrow_status=success");

        } else {
            throw new Exception("No available copies of this book to borrow.");
        }

    } catch (Exception $e) {
        $conn->rollback();
        // =========================================================================
        // ============================ THIS IS THE FIX ============================
        // =========================================================================
        // Changed the incorrect period (.) to an arrow (->) to correctly call the method
        header("Location: ../frontend/home.php?borrow_status=error&msg=" . urlencode($e->getMessage()));
        // =========================================================================
    } finally {
        if (isset($stmt_check)) $stmt_check->close();
        if (isset($stmt_insert)) $stmt_insert->close();
        $conn->close();
    }
    
    exit;
}
?>