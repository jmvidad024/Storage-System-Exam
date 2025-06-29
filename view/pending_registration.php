<?php
session_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/user.php';
require_once '../models/pendingUser.php';
require_once '../controllers/AuthMiddleware.php';

$database = new Database();
$user = new User($database);
$pendingUser = new PendingUser($database);

// Middleware: only allow admins
AuthMiddleware::authenticate($user);
AuthMiddleware::requireRole($user, ['admin']);

$message = '';
$message_type = '';

if (isset($_GET['status'], $_GET['message'])) {
    $message_type = htmlspecialchars($_GET['status'], ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars($_GET['message'], ENT_QUOTES, 'UTF-8');
}

$pending_users = $pendingUser->getAllPendingUsers();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Pending Registrations</title>
    <link rel="stylesheet" href="../assets/css/pending_registration.css">
</head>
<body>
    <header class="page-header">
        <a href="dashboard.php" class="back-button" aria-label="Back to Dashboard">&larr; Back to Dashboard</a>
        <h1>Manage Pending Registrations</h1>
    </header>

    <div class="pending-users-container">
        <?php if (!empty($message)): ?>
            <div id="action_message_area" class="message-area <?= $message_type ?>" style="display: block;">
                <?= $message ?>
            </div>
        <?php endif; ?>

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
                        <tr data-pending-user-id="<?= htmlspecialchars($p_user['id'], ENT_QUOTES, 'UTF-8') ?>">
                            <td><?= htmlspecialchars($p_user['id'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($p_user['username'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($p_user['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($p_user['email'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($p_user['course'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($p_user['year'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($p_user['section'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($p_user['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="action-buttons">
                                <button class="approve-btn" data-id="<?= htmlspecialchars($p_user['id'], ENT_QUOTES, 'UTF-8') ?>">Approve</button>
                                <button class="reject-btn" data-id="<?= htmlspecialchars($p_user['id'], ENT_QUOTES, 'UTF-8') ?>">Reject</button>
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
