<?php
// backend/register.php

// Include the database connection file
include 'db_connect.php';

// Check if the form data has been sent using POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get data from the form.
    // ** UPDATED: Changed $_POST['student_id'] to $_POST['id_number'] **
    $id_number = mysqli_real_escape_string($conn, $_POST['id_number']);
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $confirm_password = mysqli_real_escape_string($conn, $_POST['confirm_password']);

    // --- Validation ---
    if ($password !== $confirm_password) {
        echo "<script>alert('Passwords do not match!'); window.location.href='../frontend/index.html';</script>";
        exit();
    }

    // Check if the ID Number or email already exists in the database
    // ** UPDATED: Changed student_id to id_number in the WHERE clause **
    $sql_check = "SELECT * FROM users WHERE id_number = '$id_number' OR email = '$email'";
    $result_check = $conn->query($sql_check);

    if ($result_check->num_rows > 0) {
        echo "<script>alert('Error: ID Number or Email already exists.'); window.location.href='../frontend/index.html';</script>";
        exit();
    }

    // --- If validation passes, insert the new user ---
    
    // SQL query to insert the new user's data into the 'users' table.
    // ** UPDATED: Changed student_id to id_number in the column list **
    $sql_insert = "INSERT INTO users (id_number, fullname, email, password, role) VALUES ('$id_number', '$fullname', '$email', '$password', 'student')";

    if ($conn->query($sql_insert) === TRUE) {
        echo "<script>alert('Sign up successful! You can now log in.'); window.location.href='../frontend/home.php';</script>";
        exit();
    } else {
        echo "Error: " . $sql_insert . "<br>" . $conn->error;
    }
}

// Close the database connection
$conn->close();
?>