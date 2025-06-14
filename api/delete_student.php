<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/user.php';
require_once '../models/student.php'; // NEW: Include the Student model
require_once '../controllers/AuthMiddleware.php';

$database = new Database();
$user_model = new User($database); // Use a distinct variable name for the User model instance
$student_model = new Student($database); // NEW: Initialize Student model
$conn = $database->getConnection(); // Still needed for transaction management

header('Content-Type: application/json');

AuthMiddleware::authenticate($user_model); // Pass the User model instance
AuthMiddleware::requireRole($user_model, ['admin', 'faculty']); // Only admin/faculty can delete students

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'DELETE') {
    $student_id_pk = $_GET['student_id'] ?? null; // Renamed to clarify it's the primary key 'id' from students table
    

    if (!$student_id_pk || !is_numeric($student_id_pk)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Missing or invalid student ID (primary key) for deletion."]);
        exit();
    }

    $conn->begin_transaction(); // Start transaction

    try {
        // 1. Get the user_id associated with the student_id (PK) using the Student model
        $user_id_to_delete = $student_model->getUserIdByStudentId((int)$student_id_pk);
        if (!$user_id_to_delete) {
            throw new Exception("Student record not found or associated user_id missing for ID: {$student_id_pk}.");
        }
        
        // 2. Delete from students table using the Student model
        if (!$student_model->delete((int)$student_id_pk)) {
            throw new Exception("Failed to delete student record with ID: {$student_id_pk}.");
        }

        // 3. Delete from users table using the User model
        if (!$user_model->delete($user_id_to_delete)) { // Assuming User model has a delete method by ID
            throw new Exception("Failed to delete associated user account with ID: {$user_id_to_delete}.");
        }

        $conn->commit();
        echo json_encode(["status" => "success", "message" => "Student and associated user account deleted successfully."]);
        exit();

    } catch (Exception $e) {
        $conn->rollback(); // Rollback on any error
        error_log("Delete student API error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Server error during student deletion: " . $e->getMessage()
        ]);
        exit();
    }
} else {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed."]);
    exit();
}
