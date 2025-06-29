<?php
session_start();
ini_set('display_errors', 0); // Turn off error output to browser
ini_set('display_startup_errors', 0);
error_reporting(E_ALL); // Still log everything

require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/user.php';

$database = new Database();
$user = new User($database);

$message = '';
$message_type = ''; // 'error' or 'success'

// If a user is already logged in, redirect them to the dashboard
if ($user->isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

// Handle login attempt
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $message = "Please enter both username and password.";
        $message_type = 'error';
    } else {
        $loginResult = $user->login($username, $password);

        if ($loginResult === true) {
            // Login successful, redirect to dashboard
            header('Location: dashboard.php');
            exit();
        } elseif ($loginResult === 'not_verified') {
            // Account not verified
            $message = "Your account is not yet verified. Please check your email for a verification link.";
            $message_type = 'error';
        } else {
            // Login failed (incorrect credentials)
            $message = "Invalid username or password.";
            $message_type = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Your App</title>
    <link rel="stylesheet" href="../assets/css/login.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Exam System</h1>
            <p>Please log in to your account to continue.</p>
        </div>

        <form action="" method="POST" class="login-form">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Enter your username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>
            <button type="submit">Log In</button>
        </form>

        <?php if (!empty($message)): ?>
            <div id="login_message_area" class="message-area <?php echo $message_type; ?>" style="display: block;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="link-area">
            <p>Forgot Password? <a href="forgot_password.php">Click here</a></p>
            <p>Don't have an account? <a href="register.php">Register here</a></p>
        </div>
    </div>
</body>
</html>