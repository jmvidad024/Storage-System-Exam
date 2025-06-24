<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/user.php'; // Assuming User model is needed for AuthMiddleware
require_once '../controllers/AuthMiddleware.php';
require_once '../models/student.php'; // Assuming Student model needed for student_details table

$database = new Database();
$user = new User($database);
$conn = $database->getConnection(); // Get the database connection

header('Content-Type: application/json');

// Authenticate and authorize access to this API
AuthMiddleware::authenticate($user);
AuthMiddleware::requireRole($user, ['admin', 'faculty']); // Ensure only these roles access

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $userRole = $user->getRole(); // Get the authenticated user's role
        $userId = $user->getId();     // Get the authenticated user's ID

        $query = "
            SELECT
                sd.course AS course_name,
                sd.year AS year_level,
                sd.section AS section_name,
                COUNT(sd.student_id) AS student_count
            FROM
                students sd
            JOIN
                users u ON sd.user_id = u.id
            WHERE
                u.role = 'student'
        ";

        $params = [];
        $types = "";

        if ($userRole === 'faculty') {
            // If faculty, filter by their assigned course_major
            $facultyAssignedCourseMajor = $user->getFacultyCourseMajor($userId);

            if (!$facultyAssignedCourseMajor) {
                // If faculty is not assigned a course, return empty data or an error specific to this.
                // For now, returning empty data is safer.
                echo json_encode(["status" => "success", "data" => []]);
                exit();
            }

            // Append WHERE clause for faculty's assigned course
            // Assuming 'student_details.course' column stores the combined "Course : Major" string
            $query .= " AND sd.course = ?";
            $params[] = $facultyAssignedCourseMajor;
            $types .= "s";
        }
        // Admins see all, so no additional WHERE clause for them

        $query .= " GROUP BY sd.course, sd.year, sd.section
                    ORDER BY sd.course ASC, sd.year ASC, sd.section ASC";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("Courses API (GET) prepare failed: (" . $conn->errno . ") " . $conn->error);
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Database query preparation failed."]);
            exit();
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            error_log("Courses API (GET) execute failed: (" . $stmt->errno . ") " . $stmt->error);
            $stmt->close();
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Database query execution failed."]);
            exit();
        }

        $result = $stmt->get_result();
        
        $grouped_data = [];
        while ($row = $result->fetch_assoc()) {
            $course_name = $row['course_name'];
            $year_level = $row['year_level'];
            $section_name = $row['section_name'];
            $student_count = (int)$row['student_count'];

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

        $courses_data_for_frontend = [];
        foreach ($grouped_data as $course_name => $years_data) {
            $course_entry = [
                'course_name' => $course_name,
                'years' => []
            ];
            foreach ($years_data as $year_level => $sections_list) {
                usort($sections_list, function($a, $b) {
                    return strcmp($a['section_name'], $b['section_name']);
                });

                $course_entry['years'][] = [
                    'year_level' => $year_level,
                    'sections' => $sections_list
                ];
            }
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
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed."]);
    exit();
}