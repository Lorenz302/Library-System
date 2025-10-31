<?php
session_start();
include 'db_connect.php';

// 1. SECURITY & DATA GATHERING
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'librarian' || $_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../frontend/index.html");
    exit;
}

// Get form data for the action
$action = $_POST['action'] ?? '';
$user_id = (int)($_POST['user_id'] ?? 0);

// Prevent a librarian from acting on themselves
if ($user_id === (int)$_SESSION['user_id']) {
    die("Error: You cannot perform this action on your own account.");
}

// Get form data for preserving state on redirect
$redirect_params = [
    'search' => $_POST['search'] ?? '',
    'role' => $_POST['role'] ?? 'all',
    'status' => $_POST['status'] ?? 'all',
    'page' => $_POST['page'] ?? 1
];
$redirect_url = "../frontend/manage_users.php?" . http_build_query($redirect_params);

// 2. PROCESS THE ACTION
if ($user_id > 0 && !empty($action)) {
    $sql = "";
    $new_value = "";

    switch ($action) {
        case 'activate':
            // <<< FIXED: Changed 'id' to 'user_id' in WHERE clause
            $sql = "UPDATE users SET status = ? WHERE user_id = ?";
            $new_value = 'Active';
            break;
        case 'deactivate':
            // <<< FIXED: Changed 'id' to 'user_id' in WHERE clause
            $sql = "UPDATE users SET status = ? WHERE user_id = ?";
            $new_value = 'Inactive';
            break;
        case 'promote':
            // <<< FIXED: Changed 'id' to 'user_id' in WHERE clause
            $sql = "UPDATE users SET role = ? WHERE user_id = ?";
            $new_value = 'librarian';
            break;
        case 'demote':
            // <<< FIXED: Changed 'id' to 'user_id' in WHERE clause
            $sql = "UPDATE users SET role = ? WHERE user_id = ?";
            $new_value = 'student';
            break;
        default:
            // Invalid action, redirect with an error
            header("Location: " . $redirect_url . "&op_status=error&msg=Invalid_action");
            exit;
    }

    try {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $new_value, $user_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            header("Location: " . $redirect_url . "&op_status=success");
        } else {
            throw new Exception("The user status was already up-to-date.");
        }
    } catch (Exception $e) {
        header("Location: " . $redirect_url . "&op_status=error&msg=" . urlencode($e->getMessage()));
    }

} else {
    // Redirect if user_id or action is missing
    header("Location: " . $redirect_url . "&op_status=error&msg=Missing_parameters");
}

$conn->close();
exit;
?>