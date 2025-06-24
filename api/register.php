<?php
// api/register.php - Handles new student registration requests

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/user.php'; 
require_once '../models/pendingUser.php'; 
require_once '../models/student.php'; // Required for student ID check

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed. Only POST is supported."]);
    exit();
}

$database = new Database();
$pendingUser = new PendingUser($database);
$userModel = new User($database); 
$studentModel = new Student($database);

$rawData = file_get_contents("php://input");
$data = json_decode($rawData, true);

// Input validation (now includes student_id, year, section for students)
if (
    !isset($data['student_id']) || empty(trim($data['student_id'])) || // Student ID is now the identifier
    !isset($data['name']) || empty(trim($data['name'])) ||
    !isset($data['email']) || empty(trim($data['email'])) ||
    !isset($data['password']) || empty($data['password']) ||
    !isset($data['confirm_password']) || empty($data['confirm_password']) ||
    !isset($data['course_major_db']) || empty(trim($data['course_major_db'])) ||
    !isset($data['year']) || empty(trim($data['year'])) ||
    !isset($data['section']) || empty(trim($data['section']))
) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "All fields (Student ID, Full Name, Email, Password, Confirm Password, Course, Year, Section) are required."]);
    exit();
}

$student_id = trim($data['student_id']);
$username_for_user_table = $student_id; // Student ID is the username
$name = trim($data['name']);
$email = filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL);
$password = $data['password'];
$confirm_password = $data['confirm_password'];
$course_major_db = trim($data['course_major_db']); // This holds the "Course : Major" string
$year = (int)trim($data['year']);
$section = trim($data['section']);


// Basic validation for email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid email format."]);
    exit();
}

// Password mismatch check
if ($password !== $confirm_password) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Passwords do not match."]);
    exit();
}

// Password strength (optional, but recommended)
if (strlen($password) < 6) { 
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Password must be at least 6 characters long."]);
    exit();
}

// Additional validation for student-specific fields
if ($year < 1 || $year > 4) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid Year. Please select between 1st and 4th year."]);
    exit();
}
// Assuming 'A', 'B', 'C' are valid sections. Adjust if more are possible.
if (!in_array($section, ['A', 'B', 'C'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid Section. Please select A, B, or C."]);
    exit();
}

// Check for existing student_id (as username) in 'users' or 'pending_users'
if ($userModel->findByUsername($username_for_user_table)) {
    http_response_code(409); // Conflict
    echo json_encode(["status" => "error", "message" => "Student ID '{$username_for_user_table}' is already registered as an active user. Please try logging in or use a different Student ID."]);
    exit();
}
if ($pendingUser->findByUsername($username_for_user_table)) { 
    http_response_code(409); // Conflict
    echo json_encode(["status" => "error", "message" => "Student ID '{$username_for_user_table}' is already awaiting approval. Please wait for an administrator to review your previous request."]);
    exit();
}
// Check for student_id in the 'students' table specifically
if ($studentModel->findByStudentId($student_id)) {
   http_response_code(409); // Conflict
   echo json_encode(["status" => "error", "message" => "Student ID '{$student_id}' is already registered. Please verify or use a different ID."]);
   exit();
}

// Check if email already exists in 'users' or 'pending_users'
if ($userModel->findByEmail($email)) {
    http_response_code(409); // Conflict
    echo json_encode(["status" => "error", "message" => "Email '{$email}' is already registered in active users. Please use a different email or login."]);
    exit();
}
if ($pendingUser->findByEmail($email)) { 
    http_response_code(409); // Conflict
    echo json_encode(["status" => "error", "message" => "Email '{$email}' is already awaiting approval. Please wait for an administrator to review your previous request."]);
    exit();
}

// Hash the password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    // Create pending user with 'student' role.
    // username will be student_id.
    // Course holds the combined string. Year and Section are now also passed to pending_users.
    $new_pending_user_id = $pendingUser->createPendingUser(
        $username_for_user_table, // This is the student_id
        $name,
        $hashed_password,
        $email,
        'student', // Role is hardcoded as 'student' for this registration
        $course_major_db,
        $year,
        $section
    );

    if ($new_pending_user_id) {
        http_response_code(201); // Created
        echo json_encode([
            "status" => "success",
            "message" => "Registration successful! Your account (Student ID: {$student_id}) is pending admin approval.",
            "pending_user_id" => $new_pending_user_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to process registration. Please try again."]);
    }
} catch (Exception $e) {
    error_log("Student registration error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Server error during registration: " . $e->getMessage()]);
}
