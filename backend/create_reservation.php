<?php
// backend/create_reservation.php
session_start();
include 'db_connect.php';

// Security check: Ensure the user is logged in as a student.
if (!isset($_SESSION['id_number']) || $_SESSION['role'] !== 'student') {
    // Optional: Redirect with an error message.
    header("Location: ../frontend/index.html");
    exit();
}

// Check if the form was submitted via POST and book_id is set
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['book_id'])) {
    
    $book_id = (int)$_POST['book_id'];
    $id_number = $_SESSION['id_number'];

    // --- Validation and Logic ---

    // 1. Check if the book is actually out of stock
    $book_check_sql = "SELECT available_copies FROM books WHERE book_id = ? LIMIT 1";
    $stmt_book = $conn->prepare($book_check_sql);
    $stmt_book->bind_param("i", $book_id);
    $stmt_book->execute();
    $book_result = $stmt_book->get_result();
    
    if ($book_result->num_rows === 0) {
        header("Location: ../frontend/home.php?reservation_status=error_book_not_found");
        exit();
    }
    
    $book = $book_result->fetch_assoc();
    if ($book['available_copies'] > 0) {
        // The book became available while the user was on the page. They should borrow it instead.
        header("Location: ../frontend/home.php?reservation_status=error_book_is_available");
        exit();
    }
    $stmt_book->close();

    // 2. Check if the user already has a pending or active reservation for this book
    $reserve_check_sql = "SELECT reservation_id FROM reservation_requests WHERE book_id = ? AND id_number = ? AND reservation_status IN ('Pending', 'Available')";
    $stmt_check = $conn->prepare($reserve_check_sql);
    $stmt_check->bind_param("is", $book_id, $id_number);
    $stmt_check->execute();
    $stmt_check->store_result();
    
    if ($stmt_check->num_rows > 0) {
        // User already has a reservation.
        header("Location: ../frontend/home.php?reservation_status=error_already_reserved");
        exit();
    }
    $stmt_check->close();

    // --- If all checks pass, create the reservation ---
    
    $sql_insert = "INSERT INTO reservation_requests (book_id, id_number) VALUES (?, ?)";
    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bind_param("is", $book_id, $id_number);

    if ($stmt_insert->execute()) {
        header("Location: ../frontend/home.php?reservation_status=success");
    } else {
        header("Location: ../frontend/home.php?reservation_status=error_db");
    }
    
    $stmt_insert->close();

} else {
    // Redirect if not a POST request
    header("Location: ../frontend/home.php");
}

$conn->close();
exit();
?>