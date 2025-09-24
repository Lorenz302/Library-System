<?php
// backend/logout.php

// Always start the session first.
session_start();

// Unset all of the session variables.
$_SESSION = array();

// Finally, destroy the session.
session_destroy();

// Redirect the user to the login page (index.html) after logging out.
header("location: ../frontend/index.html");
exit;
?>