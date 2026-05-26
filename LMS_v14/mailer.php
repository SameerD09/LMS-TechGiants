<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'vendor/autoload.php';

function sendOTPEmail($toEmail, $otp) {
    $mail = new PHPMailer(true);
    try {
        // Log debug output to PHP error log for troubleshooting
        $mail->SMTPDebug   = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer [$level]: $str");
        };

        $mail->isSMTP();
        $mail->Host       = 'sandbox.smtp.mailtrap.io';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'c9088b9ce0f403';
        $mail->Password   = '9ed6d53be2fd3f';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 10; // fail fast instead of hanging

        $mail->setFrom('noreply@lms.com', 'LMS System');
        $mail->addAddress($toEmail);

        $mail->Subject = 'Your Password Reset OTP';
        $mail->Body    =
            "Hello,\n\n" .
            "Your OTP code for password reset is: $otp\n\n" .
            "This code expires in 10 minutes.\n\n" .
            "If you did not request this, please ignore this email.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer error: " . $mail->ErrorInfo);
        return false;
    }
}
