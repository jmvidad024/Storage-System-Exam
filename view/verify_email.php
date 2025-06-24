<?php
// verify_email.php - Handles email verification links

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/user.php';

$database = new Database();
$userModel = new User($database);

$token = $_GET['token'] ?? null;
$message = '';
$message_type = 'error';

if (empty($token)) {
    $message = "Invalid verification link. Token is missing.";
} else {
    try {
        if ($userModel->verifyUserByToken($token)) {
            $message_type = 'success';
            $message = "Your email has been successfully verified! You can now log in.";
        } else {
            $message = "Invalid or expired verification token, or your account is already verified.";
        }
    } catch (Exception $e) {
        error_log("Email verification error: " . $e->getMessage());
        $message = "An error occurred during verification. Please try again later.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f7f6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
            text-align: center;
        }
        .verification-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        .verification-container h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 2em;
        }
        .message-area {
            padding: 15px;
            border-radius: 5px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .message-area.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message-area.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .login-link {
            display: block;
            margin-top: 20px;
            color: #007bff;
            text-decoration: none;
            font-weight: bold;
        }
        .login-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <h1>Email Verification</h1>
        <div class="message-area <?php echo $message_type; ?>">
            <?php echo $message; ?>
        </div>
        <a href="login.php" class="login-link">Go to Login Page</a>
    </div>
</body>
</html>
