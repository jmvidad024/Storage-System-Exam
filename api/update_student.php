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

    $errors = [];

    if (!isset($data['student_id'])) $errors[] = "student_id is not set.";
    else if (!is_numeric($data['student_id'])) $errors[] = "student_id is not numeric.";

    if (!isset($data['user_id'])) $errors[] = "user_id is not set.";
    else if (!is_numeric($data['user_id'])) $errors[] = "user_id is not numeric.";

    if (!isset($data['name'])) $errors[] = "name is not set.";
    else if (empty($data['name'])) $errors[] = "name is empty.";

    if (!isset($data['email'])) $errors[] = "email is not set.";
    else if (empty($data['email'])) $errors[] = "email is empty.";
    else if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = "email is invalid.";

    if (!isset($data['course'])) $errors[] = "course is not set.";
    else if (empty($data['course'])) $errors[] = "course is empty.";

    if (!isset($data['year'])) $errors[] = "year is not set.";
    else if (!is_numeric($data['year'])) $errors[] = "year is not numeric."; // '1' is numeric

    if (!isset($data['section'])) $errors[] = "section is not set.";
    else if (empty($data['section'])) $errors[] = "section is empty.";

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid or missing required data for update.", "details" => $errors, "received_data" => $data]);
        exit();
    }

    // REMOVE THIS LINE: echo json_encode(['error'=>$errors]);

    $student_pk_id = (int)$data['student_id'];
    $user_id = (int)$data['user_id'];
    $name = htmlspecialchars(trim($data['name']));
    $email = htmlspecialchars(trim($data['email']));
    $course = htmlspecialchars(trim($data['course']));
    $year = (int)$data['year'];
    $section = htmlspecialchars(trim($data['section']));

    $conn->begin_transaction(); // Start transaction


    try {
        if (!$user_model->updateNameAndEmail($user_id, $name, $email)) {
            error_log("fail");
            throw new Exception("Failed to update user details.");
        }

        // 2. Update student details (course, year, section) using the Student model
        // Assuming Student model has an update method that updates a student by student_pk_id
        if (!$student_model->update($student_pk_id, $course, $year, $section)) {
            error_log("here");
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