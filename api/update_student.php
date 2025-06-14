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
$user_model = new User($database); // Renamed to avoid conflict with $user used by AuthMiddleware
$student_model = new Student($database); // NEW: Initialize Student model
$conn = $database->getConnection();

header('Content-Type: application/json');

AuthMiddleware::authenticate($user_model); // Pass the User model instance
AuthMiddleware::requireRole($user_model, ['admin', 'faculty']); // Only admin/faculty can update students

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'PUT') {
    $rawData = file_get_contents("php://input");
    $data = json_decode($rawData, true);

    echo json_encode(["data"=>$data]);
    // Validate required fields
    if (
        !isset($data['id']) || !is_numeric($data['id']) || // This student_id is the PK 'id' of students table
        !isset($data['user_id']) || !is_numeric($data['user_id']) ||
        !isset($data['name']) || empty($data['name']) ||
        !isset($data['email']) || empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL) ||
        !isset($data['course']) || empty($data['course']) ||
        !isset($data['year']) || !is_numeric($data['year']) ||
        !isset($data['section']) || empty($data['section'])
    ) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid or missing required data for update."]);
        exit();
    }

    $student_pk_id = (int)$data['student_id']; // Primary key 'id' from students table
    $user_id = (int)$data['user_id'];
    $name = htmlspecialchars(trim($data['name']));
    $email = htmlspecialchars(trim($data['email']));
    $course = htmlspecialchars(trim($data['course']));
    $year = (int)$data['year'];
    $section = htmlspecialchars(trim($data['section']));

    $conn->begin_transaction(); // Start transaction

    try {
        // 1. Update user details (name, email) using the User model
        if (!$user_model->updateNameAndEmail($user_id, $name, $email)) { // Assuming User model has this method
             throw new Exception("Failed to update user details.");
        }

        // 2. Update student details (course, year, section) using the Student model
        if (!$student_model->update($student_pk_id, $course, $year, $section)) {
            throw new Exception("Failed to update student record.");
        }

        $conn->commit();
        echo json_encode(["status" => "success", "message" => "Student details updated successfully."]);
        exit();

    } catch (Exception $e) {
        $conn->rollback(); // Rollback on any error
        error_log("Update student API error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Server error during student update: " . $e->getMessage()
        ]);
        exit();
    }
} else {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed."]);
    exit();
}
