<?php
// api/admin_approval.php - Handles admin approval/rejection of pending users

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/user.php';
require_once '../models/pendingUser.php';
require_once '../controllers/AuthMiddleware.php';
require_once '../utils/Mailer.php'; // Include the new Mailer utility

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed. Only POST is supported."]);
    exit();
}

$database = new Database();
$user = new User($database);
$pendingUser = new PendingUser($database);
$mailer = new Mailer(); // Initialize the Mailer

// Authenticate and ensure user is an Admin
AuthMiddleware::authenticate($user);
AuthMiddleware::requireRole($user, ['admin']);

$rawData = file_get_contents("php://input");
$data = json_decode($rawData, true);

$pending_user_id = isset($data['id']) ? $data['id'] : null;
$action = isset($data['action']) ? $data['action'] : null;

$pendingUserData = $pendingUser->getPendingUserById((int)$pending_user_id);
if (!$pendingUserData) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Pending user not found."]);
    exit();
}

try {
    if ($action === 'approve') {
    $pendingUserData = $pendingUser->getPendingUserById((int)$pending_user_id);
    if (!$pendingUserData) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Pending user not found."]);
        exit();
    }

    $verification_token = $pendingUser->approvePendingUser((int)$pending_user_id, $user);

    if ($verification_token) {
        $approved_user_info = $user->findByUsername($pendingUserData['username']);

        if ($approved_user_info && $mailer->sendVerificationEmail(
            $approved_user_info['email'],
            $approved_user_info['name'],
            $verification_token,
            $approved_user_info['username']
        )) {
            http_response_code(200);
            echo json_encode(["status" => "success", "message" => "Student registration approved. Verification email sent."]);
        } else {
            error_log("Failed to send verification email for user ID: Account is approved, but verification email might not have been received.");
            http_response_code(200);
            echo json_encode(["status" => "warning", "message" => "Student registration approved, but failed to send verification email. Admin to follow up."]);
        }
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to approve registration. Database error or user not found during approval."]);
    }
}
 elseif ($action === 'reject') {
        if ($pendingUser->deletePendingUser((int)$pending_user_id)) {
            http_response_code(200);
            echo json_encode(["status" => "success", "message" => "Student registration rejected and removed."]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to reject registration. User not found or database error."]);
        }
    }
} catch (Exception $e) {
    error_log("Admin approval API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Server error during approval process: " . $e->getMessage()]);
}
exit();
