<?php
// backend/db_connect.php

$servername = "localhost";
$username = "root"; // Default username for XAMPP
$password = "";     // Default password for XAMPP is empty
$dbname = "jasper"; // The database name from your screenshot

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
  // Stop the script and show an error if the connection fails.
  die("Connection failed: " . $conn->connect_error);
}
?>