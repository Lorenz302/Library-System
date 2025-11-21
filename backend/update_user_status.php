<?php
// backend/update_user_status.php

session_start();
include 'db_connect.php';
require_once 'send_status_email.php';

// 1. SECURITY CHECK: Allow both Admin and Librarian
$allowed_roles = ['admin', 'librarian'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles) || $_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../frontend/index.html");
    exit;
}

$current_user_role = $_SESSION['role'];
$action = $_POST['action'] ?? '';
$target_user_id = (int)($_POST['user_id'] ?? 0);

// Prevent self-action
if ($target_user_id === (int)$_SESSION['user_id']) {
    die("Error: You cannot perform this action on your own account.");
}

// Redirect URL logic
$redirect_params = [
    'search' => $_POST['search'] ?? '',
    'role' => $_POST['role'] ?? 'all',
    'status' => $_POST['status'] ?? 'all',
    'page' => $_POST['page'] ?? 1
];
$redirect_url = "../frontend/manage_users.php?" . http_build_query($redirect_params);

if ($target_user_id > 0 && !empty($action)) {
    
    // Fetch target user data to check THEIR role and get email
    $user_query = "SELECT fullname, email, role FROM users WHERE user_id = $target_user_id";
    $user_result = $conn->query($user_query);
    $target_user = $user_result->fetch_assoc();

    if (!$target_user) {
        header("Location: " . $redirect_url . "&op_status=error&msg=User_not_found");
        exit;
    }

    // ==================================================================
    // ==================== PERMISSION GATES (FIXED) ====================
    // ==================================================================

    // Rule 1: Librarians can ONLY manage 'student' accounts.
    // They cannot touch 'admin' OR other 'librarian' accounts.
    if ($current_user_role === 'librarian') {
        if ($target_user['role'] === 'admin' || $target_user['role'] === 'librarian') {
            die("Error: Librarians can only manage Students. You cannot modify this account.");
        }
    }

    // Rule 2: Only Admins can Promote or Demote (Already correct, but keeping for safety)
    if (($action === 'promote' || $action === 'demote') && $current_user_role !== 'admin') {
        die("Error: Only Administrators can change user roles.");
    }

    // ==================================================================

    // --- PROCESS ACTION ---
    $sql = "";
    $new_value = "";
    $email_action_type = "";

    switch ($action) {
        case 'activate':
            $sql = "UPDATE users SET status = ? WHERE user_id = ?";
            $new_value = 'Active';
            $email_action_type = 'activate';
            break;
        case 'ban':
            $sql = "UPDATE users SET status = ? WHERE user_id = ?";
            $new_value = 'Banned'; 
            $email_action_type = 'ban';
            break;
        case 'promote':
            $sql = "UPDATE users SET role = ? WHERE user_id = ?";
            $new_value = 'librarian'; 
            $email_action_type = 'promote';
            break;
        case 'demote':
            $sql = "UPDATE users SET role = ? WHERE user_id = ?";
            $new_value = 'student';
            $email_action_type = 'demote';
            break;
        default:
            header("Location: " . $redirect_url . "&op_status=error&msg=Invalid_action");
            exit;
    }

    try {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $new_value, $target_user_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            // Send Email Notification
            sendStatusEmail($target_user['email'], $target_user['fullname'], $email_action_type);
            header("Location: " . $redirect_url . "&op_status=success");
        } else {
            header("Location: " . $redirect_url . "&op_status=warning&msg=No_changes_made");
        }
    } catch (Exception $e) {
        header("Location: " . $redirect_url . "&op_status=error&msg=" . urlencode($e->getMessage()));
    }

} else {
    header("Location: " . $redirect_url . "&op_status=error&msg=Missing_parameters");
}

$conn->close();
exit;
?>