<?php
// utils/Mailer.php

// Adjust this path if PHPMailer is installed differently (e.g., via Composer)
// If using Composer, it's typically: require_once __DIR__ . '/../vendor/autoload.php';
// For direct includes, you might need:
require_once '../env_loader.php';
require '../vendor/autoload.php'; // Adjust path if not using Composer


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private $mail;

    public function __construct() {
        $this->mail = new PHPMailer(true); // true enables exceptions
        // Server settings
        // $this->mail->SMTPDebug = SMTP::DEBUG_SERVER; // Enable verbose debug output (for testing)
        $this->mail->isSMTP();
        $this->mail->Host       = $_ENV['SMTP_HOST']; // Your SMTP host (e.g., smtp.gmail.com)
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = $_ENV['SMTP_USERNAME']; // Your SMTP username (e.g., your_email@example.com)
        $this->mail->Password   = $_ENV['SMTP_PASSWORD']; // Your SMTP password
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Use ENCRYPTION_SMTPS for port 465, ENCRYPTION_STARTTLS for 587
        $this->mail->Port       = $_ENV['SMTP_PORT']; // TCP port to connect to

        // Recipients
        $this->mail->setFrom('no-reply@yourdomain.com', 'Your Exam System');
        $this->mail->isHTML(true); // Set email format to HTML
    }

    /**
     * Sends a verification email to the user.
     *
     * @param string $recipientEmail The email address of the recipient.
     * @param string $recipientName The name of the recipient.
     * @param string $verificationToken The unique token for verification.
     * @param string $username The username (student ID) for login reminder.
     * @return bool True on success, false on failure.
     */
    public function sendVerificationEmail(string $recipientEmail, string $recipientName, string $verificationToken, string $username): bool {
        try {
            $this->mail->clearAddresses(); // Clear all addresses to ensure fresh send
            $this->mail->addAddress($recipientEmail, $recipientName);

            $verificationLink ='https://puma-topical-noticeably.ngrok-free.app/view/verify_email.php?token=' . urlencode($verificationToken);

            $this->mail->Subject = 'Account Verification for Your Exam System';
            $this->mail->Body    = "
                <p>Dear {$recipientName},</p>
                <p>Your account for the Exam System has been approved!</p>
                <p>Your username is: <strong>{$username}</strong></p>
                <p>Please click the link below to verify your email address and activate your account:</p>
                <p><a href='{$verificationLink}'>Verify Your Account</a></p>
                <p>If you did not register for this account, please ignore this email.</p>
                <p>Thank you,</p>
                <p>The Exam System Team</p>
            ";
            $this->mail->AltBody = "Dear {$recipientName},\n\nYour account for the Exam System has been approved!\nYour username is: {$username}\n\nPlease visit the following link to verify your email address and activate your account:\n{$verificationLink}\n\nIf you did not register for this account, please ignore this email.\n\nThank you,\nThe Exam System Team";

            $this->mail->send();
            error_log("Verification email sent to: " . $recipientEmail);
            return true;
        } catch (Exception $e) {
    error_log("PHPMailer Exception: " . $e->getMessage());
    error_log("PHPMailer ErrorInfo: " . $this->mail->ErrorInfo);
    return false;
}
    }

    // Add other email sending methods here if needed (e.g., password reset, notifications)
}
