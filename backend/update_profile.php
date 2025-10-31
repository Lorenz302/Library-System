<?php
// backend/update_profile.php
session_start();
include 'db_connect.php';

// Security check: Ensure the user is logged in.
if (!isset($_SESSION['id_number'])) {
    // Redirect to login page if not logged in
    header("Location: ../frontend/index.html");
    exit();
}

// Check if the form was submitted via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get the user's ID from the session (the source of truth)
    $id_number = $_SESSION['id_number'];

    // Get the new data from the form
    $fullname = $_POST['fullname'];
    $program_and_year = $_POST['program_and_year'];

    // Basic validation: ensure fields are not empty
    if (empty($fullname) || empty($program_and_year)) {
        // Redirect back with an error message (optional)
        header("Location: ../frontend/home.php?profile_update=error_empty");
        exit();
    }

    // --- Use a Prepared Statement to prevent SQL Injection ---
    
    // 1. Prepare the SQL query
    $sql_update = "UPDATE users SET fullname = ?, program_and_year = ? WHERE id_number = ?";
    $stmt = $conn->prepare($sql_update);

    // 2. Bind the parameters
    // 'ss' for two strings (fullname, program_and_year), 's' for the id_number
    $stmt->bind_param("sss", $fullname, $program_and_year, $id_number);

    // 3. Execute the statement
    if ($stmt->execute()) {
        // --- IMPORTANT: Update the session variable with the new name ---
        $_SESSION['fullname'] = $fullname;

        // Redirect back to home on success
        header("Location: ../frontend/home.php?profile_update=success");
    } else {
        // Redirect back with a generic error if the update fails
        header("Location: ../frontend/home.php?profile_update=error_db");
    }

    // 4. Close the statement
    $stmt->close();

} else {
    // If not a POST request, just redirect to home
    header("Location: ../frontend/home.php");
}

// Close the database connection
$conn->close();
exit();
?>