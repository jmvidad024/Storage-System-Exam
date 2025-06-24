<?php
// admin/pending_registrations.php - Admin interface for approving/rejecting student registrations

session_start();
ini_set('display_errors', 0); // Turn off error output to browser for production
ini_set('display_startup_errors', 0);
error_reporting(E_ALL); // Still log everything

require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/user.php';
require_once '../models/pendingUser.php';
require_once '../controllers/AuthMiddleware.php';

$database = new Database();
$user = new User($database);
$pendingUser = new PendingUser($database);

// Authenticate and ensure user is an Admin
AuthMiddleware::authenticate($user);
AuthMiddleware::requireRole($user, ['admin']);

$message = '';
$message_type = '';

// Check for messages from approval/rejection actions (e.g., redirect from API)
if (isset($_GET['status']) && isset($_GET['message'])) {
    $message_type = htmlspecialchars($_GET['status']);
    $message = htmlspecialchars($_GET['message']);
}

// Fetch all pending users for display
$pending_users = $pendingUser->getAllPendingUsers();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Pending Registrations</title>
    <link rel="stylesheet" href="../assets/css/style.css"> <!-- General styles -->
    <link rel="stylesheet" href="../assets/css/admin.css"> <!-- Admin specific styles -->
    <style>
        /* Basic styling for the pending registrations table */
        .pending-users-container {
            padding: 20px;
            max-width: 1200px;
            margin: 20px auto;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .pending-users-container h1 {
            text-align: center;
            color: #333;
            margin-bottom: 25px;
        }
        .message-area {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: bold;
            display: none; /* Hidden by default, shown by JS or PHP */
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
        .pending-users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .pending-users-table th, .pending-users-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }
        .pending-users-table th {
            background-color: #f2f2f2;
            font-weight: bold;
            color: #555;
        }
        .pending-users-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .pending-users-table tr:hover {
            background-color: #f1f1f1;
        }
        .action-buttons button {
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
            font-size: 0.9em;
            transition: background-color 0.2s ease;
        }
        .action-buttons .approve-btn {
            background-color: #28a745;
            color: white;
        }
        .action-buttons .approve-btn:hover {
            background-color: #218838;
        }
        .action-buttons .reject-btn {
            background-color: #dc3545;
            color: white;
        }
        .action-buttons .reject-btn:hover {
            background-color: #c82333;
        }
        .no-pending-message {
            text-align: center;
            margin-top: 30px;
            font-size: 1.1em;
            color: #777;
        }
    </style>
</head>
<body>
    <header class="page-header">
        <a href="dashboard.php" class="back-button">&larr; Back to Dashboard</a>
        <h1>Manage Pending Registrations</h1>
    </header>

    <div class="pending-users-container">
        <div id="action_message_area" class="message-area <?php echo $message_type; ?>" 
             style="<?php echo !empty($message) ? 'display: block;' : 'display: none;'; ?>">
            <?php echo $message; ?>
        </div>

        <?php if (!empty($pending_users)): ?>
            <table class="pending-users-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student ID (Username)</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Course</th>
                        <th>Year</th>
                        <th>Section</th>
                        <th>Requested On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_users as $p_user): ?>
                        <tr data-pending-user-id="<?= htmlspecialchars($p_user['id']) ?>">
                            <td><?= htmlspecialchars($p_user['id']) ?></td>
                            <td><?= htmlspecialchars($p_user['username']) ?></td>
                            <td><?= htmlspecialchars($p_user['name']) ?></td>
                            <td><?= htmlspecialchars($p_user['email']) ?></td>
                            <td><?= htmlspecialchars($p_user['course']) ?></td>
                            <td><?= htmlspecialchars($p_user['year']) ?></td>
                            <td><?= htmlspecialchars($p_user['section']) ?></td>
                            <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($p_user['created_at']))) ?></td>
                            <td class="action-buttons">
                                <button class="approve-btn" data-id="<?= htmlspecialchars($p_user['id']) ?>">Approve</button>
                                <button class="reject-btn" data-id="<?= htmlspecialchars($p_user['id']) ?>">Reject</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-pending-message">No pending student registrations at this time.</p>
        <?php endif; ?>
    </div>

    <script src="../assets/js/pending_registrations.js"></script>
</body>
</html>
