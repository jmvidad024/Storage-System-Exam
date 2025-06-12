<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/user.php';
require_once '../models/examAttempt.php';
require_once '../controllers/AuthMiddleware.php';

$database = new Database();
$user = new User($database);
$studentExamAttempt = new StudentExamAttempt($database);

// Authenticate user and ensure role is admin or faculty
AuthMiddleware::authenticate($user);
AuthMiddleware::requireRole($user, ['admin', 'faculty']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    try {
        // Get exam_id from URL query parameter for DELETE requests
        $exam_id = $_GET['exam_id'] ?? null;

        if (!$exam_id || !is_numeric($exam_id)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Missing or invalid exam ID."]);
            exit();
        }

        // Use a transaction to ensure atomicity
        $database->getConnection()->begin_transaction();

        $delete_success = $studentExamAttempt->deleteAttemptsByExamId((int)$exam_id);

        if ($delete_success) {
            $database->getConnection()->commit();
            echo json_encode(["status" => "success", "message" => "All student attempts for this exam have been deleted."]);
        } else {
            $database->getConnection()->rollback();
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to delete exam attempts. An error occurred."]);
        }

    } catch (Exception $e) {
        $database->getConnection()->rollback();
        error_log("API DELETE exam attempts error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Server error during attempt deletion: " . $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
}
