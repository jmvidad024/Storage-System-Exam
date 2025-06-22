document.addEventListener('DOMContentLoaded', function() {
    const resetPasswordForm = document.getElementById('resetPasswordForm');
    const resetTokenInput = document.getElementById('resetToken');
    const newPasswordInput = document.getElementById('newPassword');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    const messageDiv = document.getElementById('message');
    const initialMessageDiv = document.getElementById('initialMessage');

    // Get token from URL
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');

    function displayInitialMessage(type, message) {
        initialMessageDiv.textContent = message;
        initialMessageDiv.className = `message-area ${type}`;
        initialMessageDiv.classList.remove('hidden');
    }

    function displayFormMessage(type, message, duration = 5000) {
        messageDiv.textContent = message;
        messageDiv.className = `message-area ${type}`;
        messageDiv.classList.remove('hidden');

        if (duration > 0) {
            setTimeout(() => {
                messageDiv.classList.add('hidden');
                messageDiv.textContent = '';
            }, duration);
        }
    }

    // Initial token validation
    async function validateToken() {
        if (!token) {
            displayInitialMessage('error', 'Password reset token is missing.');
            return;
        }

        resetTokenInput.value = token; // Set the hidden input

        try {
            const response = await fetch(`../api/validate_reset_token.php?token=${encodeURIComponent(token)}`);
            const result = await response.json();

            if (response.ok && result.status === 'valid') {
                initialMessageDiv.classList.add('hidden');
                resetPasswordForm.classList.remove('hidden'); // Show the form
            } else {
                displayInitialMessage('error', result.message || 'Invalid or expired password reset link. Please request a new one.');
            }
        } catch (error) {
            console.error('Error validating token:', error);
            displayInitialMessage('error', 'Network error during token validation. Please try again later.');
        }
    }

    validateToken(); // Call immediately on page load

    resetPasswordForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const newPassword = newPasswordInput.value;
        const confirmPassword = confirmPasswordInput.value;

        if (newPassword !== confirmPassword) {
            displayFormMessage('error', 'Passwords do not match.');
            return;
        }

        if (newPassword.length < 8) { // Example password policy
            displayFormMessage('error', 'Password must be at least 8 characters long.');
            return;
        }

        displayFormMessage('loading', 'Resetting password...', 0);

        try {
            const response = await fetch('../api/reset_password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    token: token,
                    new_password: newPassword
                })
            });

            const result = await response.json();

            if (response.ok && result.status === 'success') {
                displayFormMessage('success', result.message || 'Your password has been reset successfully! Redirecting to login...', 3000);
                setTimeout(() => {
                    window.location.href = 'login.php'; // Redirect to login
                }, 3000);
            } else {
                displayFormMessage('error', result.message || 'Failed to reset password. Please try again.');
            }
        } catch (error) {
            console.error('Error resetting password:', error);
            displayFormMessage('error', 'Network error during password reset. Please try again later.');
        }
    });
});