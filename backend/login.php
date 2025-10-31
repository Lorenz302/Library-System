<?php
// backend/login.php

session_start();
include 'db_connect.php';

// Set the header to return JSON
header('Content-Type: application/json');

// Initialize the response array
$response = [
    'success' => false,
    'message' => 'An error occurred.',
    'redirect' => ''
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Using mysqli_real_escape_string for security since we are building a raw SQL string.
    $id_number = mysqli_real_escape_string($conn, $_POST['id_number']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    // This query finds a user where all three plain-text fields match.
    $sql = "SELECT * FROM users WHERE id_number = '$id_number' AND email = '$email' AND password = '$password'";
    $result = $conn->query($sql);

    // Check if exactly one user was found with these credentials
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();

        // =========================================================================
        // ============================ THIS IS THE FIX ============================
        // =========================================================================
        // After finding the user, check if their account status is 'Active'.
        if ($user['status'] === 'Active') {
            // --- LOGIN SUCCESSFUL ---

            // Destroy the old session and create a new, clean one.
            session_regenerate_id(true);

            // Set the session variables for the logged-in user.
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['id_number'] = $user['id_number'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role'] = $user['role'];
            
            // Prepare the success response
            $response['success'] = true;
            $response['message'] = 'Login successful!';

            // Set the redirect URL based on the user's role
            if ($user['role'] == 'librarian') {
                $response['redirect'] = '../frontend/admin_dashboard.php';
            } else {
                $response['redirect'] = '../frontend/home.php';
            }
        } else {
            // --- ACCOUNT IS INACTIVE ---
            // The credentials are correct, but the account is disabled. Deny login.
            $response['success'] = false;
            $response['message'] = 'Your account is inactive. Please contact the librarian for assistance.';
        }
        // =========================================================================
        // ========================== END OF THE FIX ===============================
        // =========================================================================

    } else {
        // If num_rows is 0, the credentials are wrong.
        $response['success'] = false;
        $response['message'] = 'Invalid ID Number, Email, or Password.';
    }
}

$conn->close();

// Send the JSON response back to the JavaScript
echo json_encode($response);
exit();
?>
