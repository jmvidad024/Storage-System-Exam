<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/user.php';
require_once '../models/examModel.php'; // NEW: Include ExamModel
require_once '../controllers/AuthMiddleware.php';

$database = new Database();
$user = new User($database);
$examModel = new ExamModel($database); // NEW: Initialize ExamModel

// Authenticate user
AuthMiddleware::authenticate($user);
// Optionally, you might want to restrict this to students, or only if student_exam_attempt created.
// For now, let's allow any authenticated user to fetch an exam if they have the ID.
// AuthMiddleware::requireRole($user, ['student']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $exam_id = $_GET['exam_id'] ?? null;

        if (!$exam_id || !is_numeric($exam_id)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Missing or invalid exam ID."]);
            exit();
        }

        $exam = $examModel->getExamById((int)$exam_id);

        if (!$exam) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Exam not found."]);
            exit();
        }

        // --- IMPORTANT: REMOVE ANSWERS BEFORE SENDING TO CLIENT ---
        if (isset($exam['questions'])) {
            foreach ($exam['questions'] as &$question) {
                unset($question['answer']); // Remove the correct answer for student view
            }
            unset($question); // Break the reference
        }
        // --- END IMPORTANT ---

        echo json_encode([
            "status" => "success",
            "message" => "Exam details retrieved successfully.",
            "exam" => $exam
        ]);

    } catch (Exception $e) {
        error_log("API GET exam_details error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Server error. Please try again later."
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
}
