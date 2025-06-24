document.addEventListener('DOMContentLoaded', function() {
    const actionMessageArea = document.getElementById('action_message_area');
    const pendingUsersTableBody = document.querySelector('.pending-users-table tbody'); // Select the tbody

    // Helper function to display messages dynamically
    function displayMessage(type, message) {
        actionMessageArea.classList.remove('hidden', 'success', 'error');
        actionMessageArea.textContent = message;
        if (type === 'success') {
            actionMessageArea.classList.add('success');
        } else if (type === 'error') {
            actionMessageArea.classList.add('error');
        }
        actionMessageArea.style.display = 'block';
        setTimeout(() => {
            actionMessageArea.style.display = 'none';
            actionMessageArea.textContent = '';
        }, 5000);
    }

    // Check if there's an initial message from PHP (e.g., after redirect)
    if (actionMessageArea.textContent.trim() !== '') {
        actionMessageArea.style.display = 'block';
        setTimeout(() => {
            actionMessageArea.style.display = 'none';
            actionMessageArea.textContent = '';
            // Clear URL parameters after message is shown to avoid re-showing on refresh
            const url = new URL(window.location.href);
            url.searchParams.delete('status');
            url.searchParams.delete('message');
            window.history.replaceState({}, document.title, url.toString());
        }, 5000);
    }

    // Add event listener to the table body to handle clicks on dynamically added buttons
    if (pendingUsersTableBody) {
        pendingUsersTableBody.addEventListener('click', async function(event) {
            const target = event.target;

            if (target.classList.contains('approve-btn') || target.classList.contains('reject-btn')) {
                const pendingUserId = target.dataset.id;
                const action = target.classList.contains('approve-btn') ? 'approve' : 'reject';

                if (!pendingUserId) {
                    displayMessage('error', 'Error: Missing pending user ID.');
                    return;
                }

                // Disable buttons to prevent multiple clicks
                target.disabled = true;
                const row = target.closest('tr');
                if (row) {
                    row.querySelectorAll('button').forEach(btn => btn.disabled = true);
                }

                displayMessage('success', `${action === 'approve' ? 'Approving' : 'Rejecting'} request...`);

                try {
                    const response = await fetch('../api/admin_approval.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ id: pendingUserId, action: action })
                    });

                    const responseData = await response.json();

                    if (response.ok && responseData.status === 'success') {
                        displayMessage('success', responseData.message);
                        // Remove the row from the table on success
                        if (row) {
                            row.remove();
                        }
                        // Check if the table is now empty and display appropriate message
                        if (pendingUsersTableBody.children.length === 0) {
                            const container = document.querySelector('.pending-users-container');
                            if (container) {
                                container.innerHTML += '<p class="no-pending-message">No pending student registrations at this time.</p>';
                            }
                        }
                    } else {
                        throw new Error(responseData.message || `Action failed: HTTP Status ${response.status}`);
                    }
                } catch (error) {
                    console.error('Error during admin action:', error);
                    displayMessage('error', `Failed to ${action} registration: ${error.message}.`);
                    // Re-enable buttons on error
                    if (row) {
                        row.querySelectorAll('button').forEach(btn => btn.disabled = false);
                    }
                }
            }
        });
    }
});
