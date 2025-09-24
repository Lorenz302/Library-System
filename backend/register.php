<?php
// backend/register.php

// Include the database connection file
include 'db_connect.php';

// Check if the form data has been sent using POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get data from the form. Use mysqli_real_escape_string for basic security.
    $student_id = mysqli_real_escape_string($conn, $_POST['student_id']);
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $confirm_password = mysqli_real_escape_string($conn, $_POST['confirm_password']);

    // --- Validation ---
    
    // Check if passwords match
    if ($password !== $confirm_password) {
        // If they don't match, redirect back to the index page with an error message
        echo "<script>alert('Passwords do not match!'); window.location.href='../frontend/index.html';</script>";
        exit();
    }

    // Check if the student ID or email already exists in the database
    $sql_check = "SELECT * FROM users WHERE student_id = '$student_id' OR email = '$email'";
    $result_check = $conn->query($sql_check);

    if ($result_check->num_rows > 0) {
        // If user exists, redirect back with an error message
        echo "<script>alert('Error: Student ID or Email already exists.'); window.location.href='../frontend/index.html';</script>";
        exit();
    }

    // --- If validation passes, insert the new user ---
    
    // SQL query to insert the new user's data into the 'users' table
    $sql_insert = "INSERT INTO users (student_id, fullname, email, password) VALUES ('$student_id', '$fullname', '$email', '$password')";

    if ($conn->query($sql_insert) === TRUE) {
        // If registration is successful, show a success message and redirect to the home page
        echo "<script>alert('Sign up successful! You can now log in.'); window.location.href='../frontend/home.html';</script>";
        exit();
    } else {
        // If there was an error with the query, show the error
        echo "Error: " . $sql_insert . "<br>" . $conn->error;
    }
}

// Close the database connection
$conn->close();
?>