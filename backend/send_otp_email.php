<?php
// backend/send_otp_email.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// =================================================================
// FIX: The folder name in your screenshot is "PHPMailer-6.10.0"
// We must match that folder name exactly in these lines.
// =================================================================
require 'PHPMailer-6.10.0/src/Exception.php';
require 'PHPMailer-6.10.0/src/PHPMailer.php';
require 'PHPMailer-6.10.0/src/SMTP.php';

function sendOtpEmail($recipientEmail, $otp) {
    $mail = new PHPMailer(true);

    try {
        // =========================================================
        // SERVER SETTINGS (GMAIL SMTP)
        // =========================================================
        $mail->isSMTP();                                            
        $mail->Host       = 'smtp.gmail.com';                     
        $mail->SMTPAuth   = true;                                   
        
        // YOUR CREDENTIALS
        $mail->Username   = 'paxelfdsev@gmail.com'; 
        $mail->Password   = 'ibzn pvjk qkmo ezua'; // Your App Password
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         
        $mail->Port       = 587;                                    

        // =========================================================
        // RECIPIENTS
        // =========================================================
        $mail->setFrom('paxelfdsev@gmail.com', 'Bataan Heroes Library');
        $mail->addAddress($recipientEmail);     

        // =========================================================
        // EMAIL CONTENT DESIGN
        // =========================================================
        $mail->isHTML(true);                                  
        $mail->Subject = 'Your Library Login Code';
        
        // This variable holds the HTML design
        $email_body = "
        <div style='font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 20px; background-color: #ffffff; border: 1px solid #e0e0e0; border-radius: 8px;'>
            
            <!-- Header -->
            <div style='text-align: center; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; margin-bottom: 20px;'>
                <h2 style='color: #333; margin: 0;'>Bataan Heroes College</h2>
                <p style='color: #777; font-size: 14px; margin: 5px 0 0 0;'>Library Management System</p>
            </div>

            <!-- Content -->
            <div style='text-align: center;'>
                <p style='font-size: 16px; color: #555; margin-bottom: 20px;'>
                    Please use the following One-Time Password (OTP) to complete your login.
                </p>

                <!-- The Big Highlighted Number -->
                <div style='background-color: #f0f8f0; border: 2px dashed #4CAF50; padding: 15px; display: inline-block; border-radius: 10px; margin-bottom: 20px;'>
                    <span style='font-size: 32px; font-weight: bold; color: #4CAF50; letter-spacing: 5px; display: block;'>
                        {$otp}
                    </span>
                </div>

                <p style='font-size: 14px; color: #888;'>
                    This code is valid for <strong>5 minutes</strong>. <br>
                    If you did not request this, please ignore this email.
                </p>
            </div>

            <!-- Footer -->
            <div style='margin-top: 30px; font-size: 12px; color: #aaa; text-align: center; border-top: 1px solid #eee; padding-top: 10px;'>
                &copy; " . date("Y") . " Bataan Heroes Memorial College Library
            </div>
        </div>
        ";

        $mail->Body = $email_body;
        $mail->AltBody = "Your Library OTP is: $otp";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // If there is an error, this will help you see it in the browser Network tab response
        // error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>