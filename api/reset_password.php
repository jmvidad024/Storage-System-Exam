<?php
session_start();
ini_set('display_errors', 0); // Turn off error output to browser
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/user.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection(); // <-- Get the actual mysqli connection object here

// Pass the mysqli connection object ($db) to your User model
$user = new User($database); // Correct: User expects the mysqli connection

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->token) || empty($data->token) || !isset($data->new_password) || empty($data->new_password)) {
    echo json_encode(['status' => 'error', 'message' => 'Token and new password are required.']);
    exit();
}

$token = $data->token;
$newPassword = $data->new_password;

// Password policy validation (example)
if (strlen($newPassword) < 8) {
    echo json_encode(['status' => 'error', 'message' => 'New password must be at least 8 characters long.']);
    exit();
}
// Add more complex password validation (e.g., requiring numbers, symbols, etc.) here if needed.

try {
    // Start transaction for mysqli
    $db->begin_transaction(); // Correct for mysqli

    // 1. Find and validate the token
    // Corrected for mysqli: use '?' placeholder and bind_param, get_result
    $stmt = $db->prepare("SELECT user_id, expires_at FROM password_reset_tokens WHERE token = ?");
    if (!$stmt) {
        throw new Exception("Prepare statement failed for token validation: " . $db->error);
    }
    $stmt->bind_param('s', $token); // 's' for string
    $stmt->execute();
    $result = $stmt->get_result();
    $tokenData = $result->fetch_assoc();
    $stmt->close(); // Close statement

    if (!$tokenData) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid or already used reset token.']);
        $db->rollback(); // Correct for mysqli
        exit();
    }

    $expiresAt = new DateTime($tokenData['expires_at']);
    $currentTime = new DateTime();

    if ($currentTime > $expiresAt) {
        echo json_encode(['status' => 'error', 'message' => 'Expired reset token. Please request a new one.']);
        $db->rollback(); // Correct for mysqli
        exit();
    }

    $userId = $tokenData['user_id'];

    // 2. Hash the new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // 3. Update the user's password
    $user->id = $userId; // Set user ID to identify which user to update
    if (!$user->updatePassword($hashedPassword)) { // Ensure this method uses mysqli syntax internally
        echo json_encode(['status' => 'error', 'message' => 'Failed to update password in database.']);
        $db->rollback(); // Correct for mysqli
        exit();
    }

    // 4. Invalidate (delete) the token to prevent reuse
    // Corrected for mysqli: use '?' placeholder and bind_param
    $stmt = $db->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
    if (!$stmt) {
        throw new Exception("Prepare statement failed for token deletion: " . $db->error);
    }
    $stmt->bind_param('s', $token); // 's' for string
    $stmt->execute();
    $stmt->close(); // Close statement

    $db->commit(); // Correct for mysqli
    echo json_encode(['status' => 'success', 'message' => 'Password has been reset successfully.']);

} catch (Exception $e) { // Catch general Exception now for prepare errors, etc.
    // Check if a transaction is active before trying to rollback
    // This isn't strictly necessary with begin_transaction/commit/rollback, but can prevent
    // errors if an exception occurs before begin_transaction().
    if ($db instanceof mysqli && $db->ping()) { // Ensure $db is still a valid mysqli connection
        $db->rollback(); // Correct for mysqli
    }
    error_log("Database error during password reset: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'An internal server error occurred during password reset.']);
}

// Close the mysqli connection
if ($db instanceof mysqli) { // Check if it's a mysqli object before closing
    $db->close();
}
?>