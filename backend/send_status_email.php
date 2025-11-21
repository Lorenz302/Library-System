<?php
// backend/send_status_email.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Adjust path to match your PHPMailer folder name
require 'PHPMailer-6.10.0/src/Exception.php';
require 'PHPMailer-6.10.0/src/PHPMailer.php';
require 'PHPMailer-6.10.0/src/SMTP.php';

function sendStatusEmail($recipientEmail, $recipientName, $actionType) {
    $mail = new PHPMailer(true);

    try {
        // --- SERVER SETTINGS ---
        $mail->isSMTP();                                            
        $mail->Host       = 'smtp.gmail.com';                     
        $mail->SMTPAuth   = true;                                   
        $mail->Username   = 'paxelfdsev@gmail.com'; 
        $mail->Password   = 'ibzn pvjk qkmo ezua'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         
        $mail->Port       = 587;                                    

        $mail->setFrom('paxelfdsev@gmail.com', 'Bataan Heroes Library Admin');
        $mail->addAddress($recipientEmail, $recipientName);     

        // --- EMAIL CONTENT LOGIC ---
        $subject = "";
        $messageHeader = "";
        $messageBody = "";
        $color = "#333";

        switch ($actionType) {
            case 'promote':
                $subject = "Congratulations! Role Updated to Librarian";
                $messageHeader = "Role Promotion";
                $messageBody = "We are pleased to inform you that your account has been promoted. You now have <strong>Librarian</strong> access to the system.";
                $color = "#4CAF50"; // Green
                break;
            case 'demote':
                $subject = "Account Role Update";
                $messageHeader = "Role Changed";
                $messageBody = "Your account role has been updated to <strong>Student</strong>.";
                $color = "#FF9800"; // Orange
                break;
            case 'ban':
                $subject = "Account Suspended";
                $messageHeader = "Account Banned";
                $messageBody = "Your access to the Library System has been <strong>suspended</strong>. Please contact the administration if you believe this is a mistake.";
                $color = "#f44336"; // Red
                break;
            case 'activate':
                $subject = "Account Reactivated";
                $messageHeader = "Welcome Back";
                $messageBody = "Your account suspension has been lifted. You may now log in and access library resources.";
                $color = "#2196F3"; // Blue
                break;
        }

        // --- HTML TEMPLATE ---
        $mail->isHTML(true);                                  
        $mail->Subject = $subject;
        
        $htmlContent = "
        <div style='font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; border: 1px solid #ddd; border-radius: 8px; overflow: hidden;'>
            <div style='background-color: $color; color: white; padding: 20px; text-align: center;'>
                <h2 style='margin: 0;'>$messageHeader</h2>
            </div>
            <div style='padding: 20px; background-color: #fff; color: #333; line-height: 1.6;'>
                <p>Dear $recipientName,</p>
                <p>$messageBody</p>
                <br>
                <p style='font-size: 12px; color: #999;'>Bataan Heroes Memorial College Library System</p>
            </div>
        </div>
        ";

        $mail->Body = $htmlContent;
        $mail->AltBody = strip_tags($htmlContent);

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>