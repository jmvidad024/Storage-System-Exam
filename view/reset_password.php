<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/login.css">
    <link rel="stylesheet" href="../assets/css/reset_password.css"> </head>
<body>
    <div class="auth-container">
        <h2>Reset Your Password</h2>
        <div id="initialMessage" class="message-area loading">Verifying token...</div>
        <form id="resetPasswordForm" class="auth-form hidden">
            <input type="hidden" id="resetToken" name="token">

            <div class="form-group">
                <label for="newPassword">New Password:</label>
                <input type="password" id="newPassword" name="new_password" required>
            </div>
            <div class="form-group">
                <label for="confirmPassword">Confirm New Password:</label>
                <input type="password" id="confirmPassword" name="confirm_password" required>
            </div>
            <div id="message" class="message-area hidden"></div>
            <button type="submit" class="btn btn-primary">Reset Password</button>
            <p class="mt-3"><a href="login.php">Back to Login</a></p>
        </form>
    </div>
    <script src="../assets/js/reset_password.js"></script>
</body>
</html>