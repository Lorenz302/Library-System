<?php
// backend/send_request_email.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-6.10.0/src/Exception.php';
require 'PHPMailer-6.10.0/src/PHPMailer.php';
require 'PHPMailer-6.10.0/src/SMTP.php';

function sendRequestStatusEmail($recipientEmail, $recipientName, $bookTitle, $actionType) {
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

        $mail->setFrom('paxelfdsev@gmail.com', 'Bataan Heroes Library');
        $mail->addAddress($recipientEmail, $recipientName);     

        // --- CONTENT LOGIC ---
        $subject = "";
        $headerTitle = "";
        $messageBody = "";
        $color = "#333";

        switch ($actionType) {
            case 'Approve':
                $subject = "Book Request Approved";
                $headerTitle = "Ready for Pickup";
                $messageBody = "Good news! Your request for <strong>$bookTitle</strong> has been approved. Please proceed to the library to pick up your book.";
                $color = "#2ecc71"; // Green
                break;
            case 'Reject':
                $subject = "Book Request Update";
                $headerTitle = "Request Declined";
                $messageBody = "We are sorry, but your request for <strong>$bookTitle</strong> could not be processed at this time. Please contact the librarian for more details.";
                $color = "#e74c3c"; // Red
                break;
            case 'Available': // For Reservations
                $subject = "Reserved Book Available";
                $headerTitle = "Book Now Available";
                $messageBody = "The book <strong>$bookTitle</strong> you reserved is now available! You have 48 hours to claim it before it passes to the next person.";
                $color = "#27ae60"; // Dark Green
                break;
            case 'MarkReturned':
                $subject = "Book Returned";
                $headerTitle = "Return Successful";
                $messageBody = "We have successfully received the book <strong>$bookTitle</strong>. Thank you for returning it on time.";
                $color = "#3498db"; // Blue
                break;
            case 'MarkAsExpired':
                $subject = "Reservation Expired";
                $headerTitle = "Reservation Cancelled";
                $messageBody = "Your reservation for <strong>$bookTitle</strong> has expired because it was not picked up in time.";
                $color = "#95a5a6"; // Grey
                break;
        }

        // --- HTML TEMPLATE ---
        $mail->isHTML(true);                                  
        $mail->Subject = $subject;
        
        $htmlContent = "
        <div style='font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; border: 1px solid #ddd; border-radius: 8px; overflow: hidden;'>
            <div style='background-color: $color; color: white; padding: 20px; text-align: center;'>
                <h2 style='margin: 0;'>$headerTitle</h2>
            </div>
            <div style='padding: 20px; background-color: #fff; color: #333; line-height: 1.6;'>
                <p>Hi $recipientName,</p>
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