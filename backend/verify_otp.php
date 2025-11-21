<?php
// backend/verify_otp.php

session_start();
include 'db_connect.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => 'An error occurred during OTP verification.',
    'redirect' => ''
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $otp_input = isset($_POST['otp_code']) ? mysqli_real_escape_string($conn, $_POST['otp_code']) : '';

    if ($user_id > 0 && !empty($otp_input)) {
        // 1. Fetch user data
        $sql = "SELECT * FROM users WHERE user_id = $user_id AND otp_code = '$otp_input'";
        $result = $conn->query($sql);

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            $current_time = date('Y-m-d H:i:s');
            
            // 2. Check Expiry
            if ($user['otp_expiry'] > $current_time) {
                
                // 3. Clear OTP
                $clear_otp_sql = "UPDATE users SET otp_code = NULL, otp_expiry = NULL WHERE user_id = $user_id";
                $conn->query($clear_otp_sql); 

                // 4. Set Session
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['id_number'] = $user['id_number'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['role'] = $user['role']; // This now stores 'admin', 'librarian', or 'student'
                
                $response['success'] = true;
                $response['message'] = 'Login successful!';

                // ============================================================
                // FIX: Check for BOTH 'librarian' AND 'admin'
                // ============================================================
                if ($user['role'] === 'librarian' || $user['role'] === 'admin') {
                    $response['redirect'] = '../frontend/admin_dashboard.php';
                } else {
                    $response['redirect'] = '../frontend/home.php';
                }
                // ============================================================

            } else {
                $response['message'] = 'The One-Time Password has expired.';
                $conn->query("UPDATE users SET otp_code = NULL, otp_expiry = NULL WHERE user_id = $user_id");
            }
        } else {
            $response['message'] = 'Invalid One-Time Password.';
        }
    } else {
        $response['message'] = 'Missing User ID or OTP code.';
    }
}

$conn->close();
echo json_encode($response);
exit();
?>