<?php
ini_set('display_errors', 0); // Turn off error output to browser
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);       // Still log everything


require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/User.php';

session_start();
$database = new Database();
$user = new User($database);

// Redirect if already logged in
if ($user->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($user->login($_POST['username'], $_POST['password'])) {
        // Redirect to intended page or dashboard
        $redirect = $_SESSION['redirect_url'] ?? 'dashboard.php';
        unset($_SESSION['redirect_url']);
        header("Location: $redirect");
        exit;
    } else {
        $error = "Invalid username or password";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Storage System</title>
    <style>
        .error-message {
            color: var(--error-color);
            font-size: 14px;
            margin-top: 5px;
            text-align: center;
            display: <?php echo isset($error) ? 'block' : 'none'; ?>;
        }
    </style>
    <link rel="stylesheet" href="/Storage-System/assets/css/login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Welcome Back</h1>
            <p>Sign in to access your storage system</p>
        </div>
        
        <form class="login-form" action="" method="POST" id="login_form">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" placeholder="Enter your username" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" placeholder="Enter your password" required>
            </div>
            
            <div class="error-message">
                <?php echo isset($error) ? $error : ''; ?>
            </div>
            
            <button type="submit">Login</button>
        </form>
    </div>
    
    <script src="/Storage-System/assets/js/login.js"></script>
</body>
</html>