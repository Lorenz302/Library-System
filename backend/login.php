<?php
// backend/login.php

// Start a session to store user data after login
session_start();

// Include the database connection file
include 'db_connect.php';

// Check if the form data has been sent using POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get data from the form
    $student_id = mysqli_real_escape_string($conn, $_POST['student_id']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    // SQL query to find a user with matching student_id, email, and password
    $sql = "SELECT * FROM users WHERE student_id = '$student_id' AND email = '$email' AND password = '$password'";
    $result = $conn->query($sql);

    // Check if exactly one user was found
    if ($result->num_rows == 1) {
        // Login successful
        // Store user's student_id in the session for future use
        $_SESSION['student_id'] = $student_id;
        
        // Redirect the user to the home page
        header("Location: ../frontend/home.html");
        exit();
    } else {
        // Login failed, show an error and send them back to the index page
        echo "<script>alert('Invalid Student ID, Email, or Password.'); window.location.href='../frontend/index.html';</script>";
        exit();
    }
}

// Close the database connection
$conn->close();
?>