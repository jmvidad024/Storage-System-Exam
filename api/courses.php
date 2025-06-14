<?php
ini_set('display_errors', 0); // Turn off error output to browser
ini_set('display_startup_errors', 0);
error_reporting(E_ALL); // Still log everything

require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/user.php'; // Assuming User model is needed for AuthMiddleware
require_once '../controllers/AuthMiddleware.php';

$database = new Database();
$user = new User($database);
$conn = $database->getConnection(); // Get the database connection

header('Content-Type: application/json');

// Authenticate and authorize access to this API
AuthMiddleware::authenticate($user);
AuthMiddleware::requireRole($user, ['admin', 'faculty']);

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        // Query to get distinct courses, years, sections and count of students in each
        $query = "
            SELECT
                s.course AS course_name,
                s.year AS year_level,
                s.section AS section_name,
                COUNT(s.student_id) AS student_count
            FROM
                students s
            GROUP BY
                s.course, s.year, s.section
            ORDER BY
                s.course ASC, s.year ASC, s.section ASC
        ";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("Courses API (GET) prepare failed: (" . $conn->errno . ") " . $conn->error);
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Database query preparation failed."]);
            exit();
        }
        
        if (!$stmt->execute()) {
            error_log("Courses API (GET) execute failed: (" . $stmt->errno . ") " . $stmt->error);
            $stmt->close();
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Database query execution failed."]);
            exit();
        }
        
        $result = $stmt->get_result();
        
        $grouped_data = []; // Temporary structure for grouping

        while ($row = $result->fetch_assoc()) {
            $course_name = $row['course_name'];
            $year_level = $row['year_level'];
            $section_name = $row['section_name'];
            $student_count = (int)$row['student_count']; // Cast to int

            // Group by course_name -> year_level -> section_name
            if (!isset($grouped_data[$course_name])) {
                $grouped_data[$course_name] = [];
            }
            if (!isset($grouped_data[$course_name][$year_level])) {
                $grouped_data[$course_name][$year_level] = [];
            }
            $grouped_data[$course_name][$year_level][] = [
                'section_name' => $section_name,
                'student_count' => $student_count
            ];
        }
        $stmt->close();

        // Reformat the grouped data into a cleaner array structure for the frontend
        $courses_data_for_frontend = [];
        foreach ($grouped_data as $course_name => $years_data) {
            $course_entry = [
                'course_name' => $course_name,
                'years' => []
            ];
            foreach ($years_data as $year_level => $sections_list) {
                // Sort sections by name (e.g., A, B, C)
                usort($sections_list, function($a, $b) {
                    return strcmp($a['section_name'], $b['section_name']);
                });

                $course_entry['years'][] = [
                    'year_level' => $year_level,
                    'sections' => $sections_list
                ];
            }
            // Sort years numerically
            usort($course_entry['years'], function($a, $b) {
                return (int)$a['year_level'] - (int)$b['year_level'];
            });

            $courses_data_for_frontend[] = $course_entry;
        }

        echo json_encode(["status" => "success", "data" => $courses_data_for_frontend]);
        exit();

    } catch (Exception $e) {
        error_log("Courses API error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Server error fetching courses: " . $e->getMessage()]);
        exit();
    }
} else {
    // Handle unsupported methods
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed."]);
    exit();
}
