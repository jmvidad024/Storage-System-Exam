document.addEventListener('DOMContentLoaded', function() {
    const forgotPasswordForm = document.getElementById('forgotPasswordForm');
    const messageDiv = document.getElementById('message');

    function displayMessage(type, message, duration = 5000) {
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

    forgotPasswordForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const email = document.getElementById('email').value;

        if (!email) {
            displayMessage('error', 'Please enter your email address.');
            return;
        }

        displayMessage('loading', 'Sending reset link...', 0);

        try {
            const response = await fetch('../api/request_password_reset.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ email: email })
            });

            const result = await response.json();
            if (response.ok && result.status === 'success') {
                displayMessage('success', result.message || 'If an account with that email exists, a password reset link has been sent to your email.');
                forgotPasswordForm.reset(); // Clear the form
            } else {
                // For security, always show a generic message for both success and known error cases
                // if the email doesn't exist to prevent email enumeration.
                // The backend should handle this, but as a fallback:
                displayMessage('error', result.message || 'An error occurred. Please try again. If an account with that email exists, a password reset link has been sent.');
            }
        } catch (error) {
            console.error('Error:', error);
            displayMessage('error', 'Network error. Please try again later.');
        }
    });
});