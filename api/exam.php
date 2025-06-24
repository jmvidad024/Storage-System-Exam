<?php
ini_set('display_errors', 0); // Turn off error output to browser
ini_set('display_startup_errors', 0);
error_reporting(E_ALL); // Still log everything

require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/user.php'; // For User and getFacultyCourseMajor
require_once '../models/examModel.php'; // Include ExamModel for consistent database interaction
require_once '../models/examAttempt.php'; // Include StudentExamAttempt model
require_once '../controllers/AuthMiddleware.php';

$database = new Database();
$user = new User($database);
$examModel = new ExamModel($database); // Initialize ExamModel
$studentExamAttempt = new StudentExamAttempt($database); // Initialize StudentExamAttempt model

// Check authentication and role for API access
AuthMiddleware::authenticate($user);
// Only Admin/Faculty can perform these actions (GET, POST, PUT, DELETE)
AuthMiddleware::requireRole($user, ['admin', 'faculty']);

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// Get the current user's role and ID after authentication
$loggedInUserRole = $user->getRole();
$loggedInUserId = $user->getId();
$facultyCourse = null;

// If the logged-in user is a faculty member, retrieve their assigned course.
// This course will be used for filtering exams.
if ($loggedInUserRole === 'faculty') {
    $facultyCourse = $user->getFacultyCourseMajor($loggedInUserId);
    // If a faculty member is not assigned a course, they cannot view/manage any exams.
    if (!$facultyCourse) {
        http_response_code(403); // Forbidden
        echo json_encode(['status' => 'error', 'message' => 'Your faculty account is not assigned to a course. Please contact the administrator.']);
        exit();
    }
}

switch ($method) {
    case "GET":
        try {
            $code_filter = $_GET['code'] ?? null;
            $exam_id_filter = $_GET['exam_id'] ?? null;

            if ($exam_id_filter) {
                // Scenario 1: Fetch a specific exam by ID
                $exam = $examModel->getExamById((int)$exam_id_filter);

                // If faculty, perform an additional check to ensure the exam belongs to their course
                if ($loggedInUserRole === 'faculty' && $exam && $exam['course'] !== $facultyCourse) {
                    http_response_code(403); // Forbidden
                    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: This exam does not belong to your course.']);
                    exit();
                }

                if ($exam) {
                    echo json_encode(['status' => 'success', 'exam' => $exam]);
                    exit();
                } else {
                    http_response_code(404);
                    echo json_encode(['status' => 'error', 'message' => 'Exam not found by ID.']);
                    exit();
                }
            } elseif ($code_filter) {
                // Scenario 2: Fetch a specific exam by code
                $exam = $examModel->getExamByCode($code_filter);

                // If faculty, perform an additional check to ensure the exam belongs to their course
                if ($loggedInUserRole === 'faculty' && $exam && $exam['course'] !== $facultyCourse) {
                    http_response_code(403); // Forbidden
                    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: This exam does not belong to your course.']);
                    exit();
                }

                if ($exam) {
                    echo json_encode(['status' => 'success', 'exam' => $exam]);
                    exit();
                } else {
                    http_response_code(404);
                    echo json_encode(['status' => 'error', 'message' => 'Exam not found by code.']);
                    exit();
                }
            } else {
                // Scenario 3: Fetch all exams (or filtered by faculty's course)
                $all_exams = [];
                if ($loggedInUserRole === 'admin') {
                    // Admin gets all exams
                    $all_exams = $examModel->getAllExams();
                } elseif ($loggedInUserRole === 'faculty') {
                    // Faculty gets only exams for their assigned course
                    $all_exams = $examModel->getExamsByCourse($facultyCourse);
                }

                echo json_encode(['status' => 'success', 'exams' => $all_exams]);
                exit();
            }

        } catch (Exception $e) {
            error_log("GET exam error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Server error. Please try again later. " . $e->getMessage()
            ]);
            exit();
        }
        break;

    case "POST": // Create Exam
        try {
            $conn = $database->getConnection();
            $rawData = file_get_contents("php://input");
            $data = json_decode($rawData, true);

            // Access control: Faculty can only create exams for their assigned course
            if ($loggedInUserRole === 'faculty') {
                $examCourseFromRequest = $data['course'] ?? null;
                if ($examCourseFromRequest !== $facultyCourse) {
                    http_response_code(403); // Forbidden
                    echo json_encode(["status" => "error", "message" => "Unauthorized: You can only create exams for your assigned course."]);
                    exit();
                }
            }

            // Validate required fields, including 'duration_minutes'
            if (
                !$data ||
                !isset($data['title']) ||
                !isset($data['instruction']) ||
                !isset($data['year']) ||
                !isset($data['section']) ||
                !isset($data['code']) ||
                !isset($data['course']) ||
                !isset($data['duration_minutes']) ||
                !is_numeric($data['duration_minutes'])
            ) {
                http_response_code(400); // Bad request for missing fields
                echo json_encode(["status" => "error", "message" => "Missing required fields or invalid duration for new exam."]);
                exit();
            }

            $duration_minutes = (int)$data['duration_minutes'];

            $conn->begin_transaction();

            $stmt = $conn->prepare("INSERT INTO exams (title, instruction, year, section, code, course, duration_minutes) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                error_log("Prepare statement failed for exam: " . $conn->error);
                throw new Exception("Prepare statement failed for exam: " . $conn->error);
            }
            $stmt->bind_param("ssssssi", $data['title'], $data['instruction'], $data['year'], $data['section'], $data['code'], $data['course'], $duration_minutes);
            if (!$stmt->execute()) {
                error_log("Execute failed for exam: " . $stmt->error);
                throw new Exception("Execute failed for exam: " . $stmt->error);
            }
            $exam_id = $stmt->insert_id;
            $stmt->close();

            // Insert questions and choices
            if (isset($data['questions']) && is_array($data['questions'])) {
                foreach ($data['questions'] as $q_data) {
                    $question_text = $q_data['question_text'] ?? '';
                    $answer = $q_data['answer'] ?? '';

                    $stmt_q = $conn->prepare("INSERT INTO questions (exam_id, question_text, answer) VALUES (?, ?, ?)");
                    if (!$stmt_q) { throw new Exception("Prepare statement failed for question: " . $conn->error); }
                    $stmt_q->bind_param("iss", $exam_id, $question_text, $answer);
                    if (!$stmt_q->execute()) { throw new Exception("Execute failed for question (ExamID: " . $exam_id . "): " . $stmt_q->error); }
                    $question_id = $stmt_q->insert_id;
                    $stmt_q->close();

                    $choices = $q_data['choices'] ?? [];
                    if (!empty($choices)) {
                        $stmt_c = $conn->prepare("INSERT INTO choices (question_id, choice_text) VALUES (?, ?)");
                        if (!$stmt_c) { throw new Exception("Prepare statement failed for choices: " . $conn->error); }
                        $stmt_c->bind_param("is", $bound_question_id, $bound_choice_text);

                        foreach ($choices as $choice_data) {
                            $bound_choice_text = $choice_data['choice_text'] ?? '';
                            $bound_question_id = $question_id;
                            if (!$stmt_c->execute()) { throw new Exception("Execute failed for choice (QID: " . $question_id . ", Choice: " . $bound_choice_text . "): " . $stmt_c->error); }
                        }
                        $stmt_c->close();
                    }
                }
            }

            $conn->commit();
            echo json_encode(["status" => "success", "message" => "Exam inserted successfully", "exam_id" => $exam_id]);
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            error_log("API POST error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Server error during exam creation: " . $e->getMessage()]);
            exit();
        }
        break;

    case "PUT": // Update Exam
        try {
            $conn = $database->getConnection();
            $rawData = file_get_contents("php://input");
            $data = json_decode($rawData, true);

            $exam_id = $data['exam_id'] ?? null;
            $duration_minutes = $data['duration_minutes'] ?? null;
            $newCourse = $data['course'] ?? null; // The course received in the update request

            if (!$exam_id || !is_numeric($exam_id) || $duration_minutes === null || !is_numeric($duration_minutes)) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "Missing or invalid exam ID or duration for update."]);
                exit();
            }

            // Access control: Faculty can only update exams for their assigned course
            if ($loggedInUserRole === 'faculty') {
                $existing_exam = $examModel->getExamById((int)$exam_id);
                if (!$existing_exam) {
                    http_response_code(404);
                    echo json_encode(['status' => 'error', 'message' => 'Exam not found for authorization check.']);
                    exit();
                }
                // Check if the faculty is assigned to the original course of the exam
                if ($existing_exam['course'] !== $facultyCourse) {
                    http_response_code(403); // Forbidden
                    echo json_encode(["status" => "error", "message" => "Unauthorized: You can only update exams from your assigned course."]);
                    exit();
                }
                // Additionally, if the request attempts to change the course, ensure it's still their course
                if ($newCourse !== null && $newCourse !== $facultyCourse) {
                    http_response_code(403); // Forbidden
                    echo json_encode(["status" => "error", "message" => "Unauthorized: You cannot change an exam's course to one you are not assigned to."]);
                    exit();
                }
            }

            $conn->begin_transaction();

            $update_main = $examModel->updateExamMain(
                (int)$exam_id,
                $data['title'] ?? '',
                $data['instruction'] ?? '',
                $data['year'] ?? '',
                $data['section'] ?? '',
                $data['code'] ?? '',
                $data['course'], // Use the course from the request, already validated if faculty
                null, // Major is combined in $data['course']
                (int)$duration_minutes
            );
            if (!$update_main) {
                throw new Exception("Failed to update main exam details.");
            }

            // Handle Questions (Update, Create, Delete)
            $submitted_questions = $data['questions'] ?? [];
            $existing_question_ids = $examModel->getQuestionIdsForExam((int)$exam_id);
            $processed_question_ids = [];

            foreach ($submitted_questions as $q_data) {
                $q_id = $q_data['question_id'] ?? null;
                $question_text = $q_data['question_text'] ?? '';
                $answer = $q_data['answer'] ?? '';

                if ($q_id && in_array($q_id, $existing_question_ids)) {
                    $examModel->updateQuestion((int)$q_id, $question_text, $answer);
                    $processed_question_ids[] = (int)$q_id;
                } else {
                    $new_q_id = $examModel->createQuestion((int)$exam_id, $question_text, $answer);
                    if ($new_q_id) {
                        $q_id = $new_q_id;
                        $processed_question_ids[] = (int)$new_q_id;
                    } else {
                        throw new Exception("Failed to create new question.");
                    }
                }

                $submitted_choices = $q_data['choices'] ?? [];
                $existing_choice_ids = $examModel->getChoiceIdsForQuestion((int)$q_id);
                $processed_choice_ids = [];

                foreach ($submitted_choices as $c_data) {
                    $c_id = $c_data['choice_id'] ?? null;
                    $choice_text = $c_data['choice_text'] ?? '';

                    if ($c_id && in_array($c_id, $existing_choice_ids)) {
                        $examModel->updateChoice((int)$c_id, $choice_text);
                        $processed_choice_ids[] = (int)$c_id;
                    } else {
                        $new_c_id = $examModel->createChoice((int)$q_id, $choice_text);
                        if ($new_c_id) {
                           $processed_choice_ids[] = (int)$new_c_id;
                        } else {
                            throw new Exception("Failed to create new choice.");
                        }
                    }
                }

                $choices_to_delete = array_diff($existing_choice_ids, $processed_choice_ids);
                foreach ($choices_to_delete as $id_to_delete) {
                    $examModel->deleteChoice((int)$id_to_delete);
                }
            }

            $questions_to_delete = array_diff($existing_question_ids, $processed_question_ids);
            foreach ($questions_to_delete as $id_to_delete) {
                $examModel->deleteQuestion((int)$id_to_delete);
            }

            $conn->commit();
            echo json_encode(["status" => "success", "message" => "Exam updated successfully!"]);
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            error_log("PUT exam error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Server error during update: " . $e->getMessage()
            ]);
            exit();
        }
        break;

    case "DELETE":
        try {
            $exam_id = $_GET['exam_id'] ?? null;

            if (!$exam_id || !is_numeric($exam_id)) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "Missing or invalid exam ID for deletion."]);
                exit();
            }

            // Access control: Faculty can only delete exams from their assigned course
            if ($loggedInUserRole === 'faculty') {
                $existing_exam = $examModel->getExamById((int)$exam_id);
                if (!$existing_exam) {
                    http_response_code(404);
                    echo json_encode(['status' => 'error', 'message' => 'Exam not found for authorization check.']);
                    exit();
                }
                if ($existing_exam['course'] !== $facultyCourse) {
                    http_response_code(403); // Forbidden
                    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: You can only delete exams from your assigned course.']);
                    exit();
                }
            }

            // Use the StudentExamAttempt model to delete attempts first
            // Note: This model is expected to handle the actual DB deletion of attempts.
            if (!$studentExamAttempt->deleteAttemptsForExam((int)$exam_id)) {
                // Log and potentially return an error, but allow exam deletion to proceed if attempts cannot be deleted
                // depending on your business rules. For critical data, you might throw an exception here.
                error_log("Warning: Failed to delete student attempts for exam ID: " . $exam_id);
                // Optionally, you might choose to halt here: throw new Exception("Failed to delete associated student attempts.");
            }

            // Use the ExamModel to delete the exam (which should also handle questions and choices)
            if ($examModel->deleteExam((int)$exam_id)) {
                echo json_encode(["status" => "success", "message" => "Exam and all related data deleted successfully."]);
                exit();
            } else {
                http_response_code(500); // 500 for general deletion failure, 404 implies it was not found for deletion
                echo json_encode(["status" => "error", "message" => "Failed to delete exam. It might not exist or a database error occurred."]);
                exit();
            }

        } catch (Exception $e) {
            error_log("DELETE exam error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Server error during deletion: " . $e->getMessage()
            ]);
            exit();
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Method not allowed"]);
        exit();
        break;
}
