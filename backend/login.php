<?php
// backend/login.php

session_start();
include 'db_connect.php';

// Include the mailer script
require_once 'send_otp_email.php'; 

header('Content-Type: application/json');

$response = [
    'success' => false,
    'otp_required' => false,
    'message' => 'An error occurred.',
    'user_id' => null,
    'redirect' => ''
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_number = mysqli_real_escape_string($conn, $_POST['id_number']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    $sql = "SELECT * FROM users WHERE id_number = '$id_number' AND email = '$email' AND password = '$password'";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();

        if ($user['status'] === 'Active') {
            // --- LOGIN CREDENTIALS VALID ---

            // 1. Generate OTP
            $otp = rand(100000, 999999);
            $expiry_time = date('Y-m-d H:i:s', strtotime('+5 minutes'));

            // 2. Update DB
            $update_sql = "UPDATE users SET otp_code = '$otp', otp_expiry = '$expiry_time' WHERE user_id = {$user['user_id']}";
            
            if ($conn->query($update_sql) === TRUE) {
                // 3. SEND EMAIL
                $emailSent = sendOtpEmail($user['email'], $otp);

                if ($emailSent) {
                    $response['success'] = true;
                    $response['otp_required'] = true;
                    $response['message'] = "OTP sent to " . $user['email'];
                    $response['user_id'] = $user['user_id'];
                } else {
                    $response['success'] = false;
                    $response['message'] = 'Failed to send OTP email. Please check your internet connection or contact admin.';
                }

            } else {
                $response['message'] = 'Database error: ' . $conn->error;
            }
            
        } else {
            $response['message'] = 'Your account is inactive.';
        }
    } else {
        $response['message'] = 'Invalid ID Number, Email, or Password.';
    }
}

$conn->close();
echo json_encode($response);
exit();
?>