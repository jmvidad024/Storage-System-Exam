<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/user.php';
require_once '../models/examAttempt.php'; // NEW
require_once '../controllers/AuthMiddleware.php';

$database = new Database();
$user = new User($database);
$studentExamAttempt = new StudentExamAttempt($database); // NEW

// Authenticate and ensure role is student
AuthMiddleware::authenticate($user);
AuthMiddleware::requireRole($user, ['student']);

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === "GET") {
    try {
        // Get student's user ID from session
         $user_id = $user->getId();

        // Get student's details including course
        $studentDetails = $user->getStudentDetails($user_id);
        if (!$studentDetails) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Student details not found. Please ensure your student profile is complete."]);
            exit();
        }

        $student_year = $studentDetails['year'];
        $student_section = $studentDetails['section'];
        $student_course = $studentDetails['course']; // Get the student's course

        // Get all exams matching the student's year, section, AND course
        $conn = $database->getConnection();
        
        // Option 1: Exact match (if exams.course contains "Course : Major")
        $stmt = $conn->prepare("SELECT exam_id, title, instruction, year, section, code, course 
                               FROM exams 
                               WHERE year = ? AND section = ? AND course = ?");
                               
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("iss", $student_year, $student_section, $student_course);
        $stmt->execute();
        $result = $stmt->get_result();

        $exams = [];
        while ($exam = $result->fetch_assoc()) {
            $exams[] = $exam;
        }
        $stmt->close();

        // Get all exam attempts for the student, including their completion status and attempt_id
        $student_attempts = $studentExamAttempt->getStudentAttemptedExams($user_id);
        // Convert to a map for easier lookup: [exam_id => {is_completed, attempt_id}]
        $attempts_map = [];
        foreach ($student_attempts as $attempt) {
            $attempts_map[$attempt['exam_id']] = [
                'is_completed' => $attempt['is_completed'],
                'attempt_id' => $attempt['attempt_id']
            ];
        }

        // Add 'is_completed' flag and 'attempt_id' to each exam
        foreach ($exams as &$exam) { // Use & to modify the array elements directly
            $exam_id_int = (int)$exam['exam_id'];
            if (isset($attempts_map[$exam_id_int])) {
                $exam['is_completed'] = $attempts_map[$exam_id_int]['is_completed'];
                $exam['attempt_id'] = $attempts_map[$exam_id_int]['attempt_id'];
            } else {
                $exam['is_completed'] = false;
                $exam['attempt_id'] = null; // No attempt record yet
            }
        }
        unset($exam); // Break the reference of the last element

        echo json_encode([
            "status" => "success",
            "message" => "Exams retrieved successfully.",
            "exams" => $exams,
            "student_details" => [
                'year' => $student_year,
                'section' => $student_section,
                'course' => $student_course // Include course in response
            ]
        ]);

    } catch (Exception $e) {
        error_log("GET student exams error: " . $e->getMessage());
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
