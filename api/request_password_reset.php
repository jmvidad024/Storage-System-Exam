<?php
session_start();
ini_set('display_errors', 0); // Turn off error output to browser
ini_set('display_startup_errors', 0);
error_reporting(E_ALL); // Still log everything

require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/user.php';

// PHPMailer includes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../vendor/autoload.php'; // Adjust path if not using Composer

header('Content-Type: application/json');

$database = new Database(); // Instance of your Database wrapper
$db = $database->getConnection(); // GET THE ACTUAL DATABASE CONNECTION OBJECT HERE!

// Now pass the actual database connection object ($db) to your User model
$user = new User($database); // Correct: User expects the PDO/mysqli connection

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->email) || empty($data->email)) {
    echo json_encode(['status' => 'error', 'message' => 'Email is required.']);
    exit();
}

$email = $data->email;

// 1. Find user by email
$user->email = $email;
$foundUser = $user->findByEmail(); // Ensure findByEmail() uses '?' placeholders and bind_param now

if (!$foundUser) {
    // For security, always return a generic success message
    // to prevent email enumeration attacks.
    echo json_encode(['status' => 'success', 'message' => 'If an account with that email exists, a password reset link has been sent.']);
    exit();
}

$userId = $foundUser['id'];

// 2. Generate a unique, secure token
$token = bin2hex(random_bytes(32)); // 64-character hex string
$expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token valid for 1 hour

// 3. Store the token in the database
try {
    // Start transaction for atomicity
    $db->begin_transaction(); // Use begin_transaction() for mysqli

    // Delete any existing tokens for this user
    // Corrected for mysqli: use '?' placeholder and bind_param
    $stmt = $db->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
    if (!$stmt) {
        throw new Exception("Prepare statement failed: " . $db->error);
    }
    $stmt->bind_param('i', $userId); // 'i' for integer
    $stmt->execute();
    $stmt->close(); // Close statement for next prepare

    // Insert the new token
    // Corrected for mysqli: use '?' placeholders and bind_param
    $stmt = $db->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Prepare statement failed: " . $db->error);
    }
    $stmt->bind_param('iss', $userId, $token, $expiresAt); // 'i' for integer, 's' for string, 's' for string
    $stmt->execute();
    $stmt->close(); // Close statement

    $db->commit(); // Commit transaction

} catch (Exception $e) { // Catch general Exception now for prepare errors
    $db->rollback(); // Rollback transaction on error
    // Log the error but still return a generic success message
    error_log("Database error during token storage: " . $e->getMessage());
    echo json_encode(['status' => 'success', 'message' => 'If an account with that email exists, a password reset link has been sent.']);
    exit();
}

// 4. Send email
$mail = new PHPMailer(true); // true enables exceptions
try {
    //Server settings
    $mail->isSMTP();
    $mail->Host       = $_ENV['SMTP_HOST']; // Your SMTP host (e.g., smtp.gmail.com)
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['SMTP_USERNAME']; // Your SMTP username (e.g., your_email@example.com)
    $mail->Password   = $_ENV['SMTP_PASSWORD']; // Your SMTP password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Use ENCRYPTION_SMTPS for port 465, ENCRYPTION_STARTTLS for 587
    $mail->Port       = $_ENV['SMTP_PORT']; // TCP port to connect to

    //Recipients
    $mail->setFrom('admin@yourapp.com', 'Inventory System'); // Sender email and name
    $mail->addAddress($email, $foundUser['name']); // User's email and name

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Password Reset Request';
    $resetLink = 'https://puma-topical-noticeably.ngrok-free.app/view/reset_password.php?token=' . $token; // IMPORTANT: Adjust this URL to your actual domain
    $mail->Body    = 'Hello ' . htmlspecialchars($foundUser['name']) . ',<br><br>'
                   . 'You have requested to reset your password for your Inventory System account.<br>'
                   . 'Please click on the following link to reset your password: <a href="' . $resetLink . '">' . $resetLink . '</a><br><br>'
                   . 'This link will expire in 1 hour.<br>'
                   . 'If you did not request a password reset, please ignore this email.<br><br>'
                   . 'Regards,<br>Inventory System Team';
    $mail->AltBody = 'Hello ' . htmlspecialchars($foundUser['name']) . ',\n\n'
                   . 'You have requested to reset your password for your Inventory System account.\n'
                   . 'Please copy and paste the following link into your browser to reset your password: ' . $resetLink . '\n\n'
                   . 'This link will expire in 1 hour.\n'
                   . 'If you did not request a password reset, please ignore this email.\n\n'
                   . 'Regards,\nInventory System Team';

    $mail->send();
    echo json_encode(['status' => 'success', 'message' => 'If an account with that email exists, a password reset link has been sent.']);

} catch (Exception $e) {
    // Log the error but return a generic success message
    error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
    echo json_encode(['status' => 'success', 'message' => 'If an account with that email exists, a password reset link has been sent.']);
}

// Close the mysqli connection
if ($db instanceof mysqli) { // Check if it's a mysqli object before closing
    $db->close();
}
?>