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
    } else {
        $loginResult = $user->login($username, $password);

        if ($loginResult === true) {
            // Login successful, redirect to dashboard
            header('Location: dashboard.php');
            exit();
        } elseif ($loginResult === 'not_verified') {
            // Account not verified
            $message = "Your account is not yet verified. Please check your email for a verification link.";
        } else {
            // Login failed (incorrect credentials)
            $message = "Invalid username or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="../assets/css/login.css"> <!-- General styles -->
    <style>
        /* Basic login form styling */
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
        }
        .login-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .login-container h1 {
            color: #333;
            margin-bottom: 25px;
            font-size: 2em;
        }
        .login-container .form-group {
            margin-bottom: 15px;
            text-align: left;
        }
        .login-container label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: bold;
        }
        .login-container input[type="text"],
        .login-container input[type="password"] {
            width: calc(100% - 20px); /* Adjust for padding */
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 1em;
        }
        .login-container button {
            background-color: #007bff;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.1em;
            width: 100%;
            margin-top: 20px;
            transition: background-color 0.3s ease;
        }
        .login-container button:hover {
            background-color: #0056b3;
        }
        .message-area {
            margin-top: 20px;
            padding: 10px;
            border-radius: 5px;
            font-weight: bold;
            background-color: #f8d7da; /* Error background */
            color: #721c24; /* Error text color */
            border: 1px solid #f5c6cb;
            display: <?php echo !empty($message) ? 'block' : 'none'; ?>; /* Show if message exists */
        }
        .register-link {
            margin-top: 20px;
            font-size: 0.9em;
            color: #555;
        }
        .register-link a {
            color: #007bff;
            text-decoration: none;
            font-weight: bold;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Login</h1>
        <form action="" method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Login</button>
        </form>
        <div id="login_message_area" class="message-area">
            <?php echo $message; ?>
        </div>
        <p class="register-link">Don't have an account? <a href="register.php">Register here</a></p>
    </div>
</body>
</html>
