<?php
ini_set('display_errors', 0); // Turn off error output to browser
ini_set('display_startup_errors', 0);
error_reporting(E_ALL); // Still log everything

require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/user.php'; // Needed for user data
require_once '../controllers/AuthMiddleware.php'; // For authentication and role checks

$database = new Database();
$user = new User($database); // Initialize User model
$conn = $database->getConnection(); // Get the database connection

header('Content-Type: application/json');

// Authenticate and authorize access to this API
AuthMiddleware::authenticate($user);
AuthMiddleware::requireRole($user, ['admin', 'faculty']); // Only admin/faculty can view student lists

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $course = $_GET['course'] ?? null;
        $year = $_GET['year'] ?? null;
        $section = $_GET['section'] ?? null;

        if (empty($course) || empty($year) || empty($section)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Missing required parameters: course, year, or section."]);
            exit();
        }

        $query = "
            SELECT
                s.id,              -- Primary key of the students table
                s.user_id,          -- user_id is also important for edit operation
                s.student_id,      -- Real-life student ID
                s.course AS course_name, -- Aliased for consistency with frontend expectations
                s.year,    -- Aliased for consistency with frontend expectations
                s.section, -- Aliased for consistency with frontend expectations
                u.username,
                u.name,
                u.email
            FROM
                students s
            JOIN
                users u ON s.user_id = u.id
            WHERE
                s.course = ? AND s.year = ? AND s.section = ?
            ORDER BY
                u.name ASC, u.username ASC
        ";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("Students by Section API (GET) prepare failed: (" . $conn->errno . ") " . $conn->error);
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Database query preparation failed."]);
            exit();
        }
        
        $stmt->bind_param("sis", $course, $year, $section); // s for string, i for integer (year), s for string
        
        if (!$stmt->execute()) {
            error_log("Students by Section API (GET) execute failed: (" . $stmt->errno . ") " . $stmt->error);
            $stmt->close();
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Database query execution failed."]);
            exit();
        }
        
        $result = $stmt->get_result();
        $students = [];

        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
        $stmt->close();

        echo json_encode(["status" => "success", "students" => $students]);
        exit();

    } catch (Exception $e) {
        error_log("Students by Section API error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Server error fetching students: " . $e->getMessage()]);
        exit();
    }
} else {
    // Handle unsupported methods
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed."]);
    exit();
}
