<?php
session_start();
include 'db_connect.php';

// Ensure user is logged in
if (!isset($_SESSION["id_number"])) {
    // Return a forbidden error if accessed directly without session
    http_response_code(403);
    exit;
}

// =========================================================================
// ========================== THE REDIRECT LOGIC ===========================
// =========================================================================
// Determine where to redirect the user after the action is complete.
// Use the HTTP_REFERER if it's available, otherwise default to home.php.
if (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) {
    $redirect_url = $_SERVER['HTTP_REFERER'];
} else {
    // Fallback in case the referer is not sent by the browser
    $redirect_url = '../frontend/home.php';
}
// =========================================================================


// Ensure book_id is provided
if (isset($_POST['book_id'])) {
    $id_number = $_SESSION['id_number'];
    $book_id = (int)$_POST['book_id'];

    // Check if the book is already in the user's favorites
    $check_sql = "SELECT favorite_id FROM user_favorites WHERE id_number = ? AND book_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    // CORRECTED: Bind id_number as a string ('s') for safety, book_id as integer ('i')
    $check_stmt->bind_param("si", $id_number, $book_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        // --- IT EXISTS, SO REMOVE IT ---
        $delete_sql = "DELETE FROM user_favorites WHERE id_number = ? AND book_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        // CORRECTED: Bind as string and integer
        $delete_stmt->bind_param("si", $id_number, $book_id);
        $delete_stmt->execute();
    } else {
        // --- IT DOESN'T EXIST, SO ADD IT ---
        $insert_sql = "INSERT INTO user_favorites (id_number, book_id) VALUES (?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        // CORRECTED: Bind as string and integer
        $insert_stmt->bind_param("si", $id_number, $book_id);
        $insert_stmt->execute();
    }

    // --- CRITICAL FIX: Redirect to the dynamic URL determined at the start ---
    header("Location: " . $redirect_url);
    exit;

} else {
    // Redirect if book_id is not set
    header("Location: " . $redirect_url);
    exit;
}
?>