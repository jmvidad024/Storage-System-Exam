<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="../assets/css/forgot_password.css"> <link rel="stylesheet" href="../assets/css/login.css"> </head>
<body>
    <div class="auth-container">
        <h2>Forgot Password</h2>
        <p>Enter your email address to receive a password reset link.</p>
        <form id="forgotPasswordForm" class="auth-form">
            <div class="form-group">
                <label for="email">Email Address:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div id="message" class="message-area hidden"></div>
            <button type="submit" class="btn btn-primary">Send Reset Link</button>
            <p class="mt-3"><a href="login.php">Back to Login</a></p>
        </form>
    </div>
    <script src="../assets/js/forgot_password.js"></script>
</body>
</html>