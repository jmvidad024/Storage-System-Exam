<?php
ini_set('display_errors', 0); // Turn off error output to browser
ini_set('display_startup_errors', 0);
error_reporting(E_ALL); // Still log everything

require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/user.php';
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

switch ($method) {
    case "GET":
        try {
            $code_filter = $_GET['code'] ?? null;
            $exam_id_filter = $_GET['exam_id'] ?? null;

            if ($exam_id_filter) {
                // Fetch by exam_id
                $exam = $examModel->getExamById((int)$exam_id_filter);
                if ($exam) {
                    echo json_encode(['status' => 'success', 'exam' => $exam]);
                    exit(); // Crucial: terminate script after sending response
                } else {
                    http_response_code(404);
                    echo json_encode(['status' => 'error', 'message' => 'Exam not found by ID.']);
                    exit(); // Crucial: terminate script
                }
            } elseif ($code_filter) {
                // Fetch by code
                // THIS IS THE CRITICAL FIX: Use getExamByCode instead of getExamById
                $exam = $examModel->getExamByCode($code_filter);
                if ($exam) {
                    echo json_encode(['status' => 'success', 'exam' => $exam]);
                    exit(); // Crucial: terminate script
                } else {
                    http_response_code(404);
                    echo json_encode(['status' => 'error', 'message' => 'Exam not found by code.']);
                    exit(); // Crucial: terminate script
                }
            } else {
                // If no specific exam_id or code is provided, return all exams
                $all_exams = $examModel->getAllExams();
                echo json_encode(['status' => 'success', 'exams' => $all_exams]);
                exit(); // Crucial: terminate script
            }

        } catch (Exception $e) {
            error_log("GET exam error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Server error. Please try again later. " . $e->getMessage() // Added message for debug
            ]);
            exit(); // Crucial: terminate script
        }
        break;
    
    case "POST": // Create Exam
        try {
            $conn = $database->getConnection();

            $rawData = file_get_contents("php://input");
            $data = json_decode($rawData, true);

            // Validate required fields
            if (
                !$data ||
                !isset($data['title']) ||
                !isset($data['instruction']) ||
                !isset($data['year']) ||
                !isset($data['section']) ||
                !isset($data['code']) ||
                !isset($data['course'])
            ) {
                http_response_code(400); // Bad request for missing fields
                echo json_encode(["status" => "error", "message" => "Missing required fields for new exam: title, instruction, year, section, or code"]);
                exit(); // Added exit
            }

            // Start transaction for POST (good practice)
            $conn->begin_transaction();

            // Insert exam
            $stmt = $conn->prepare("INSERT INTO exams (title, instruction, year, section, code, course) VALUES (?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                error_log("Prepare statement failed for exam: " . $conn->error);
                throw new Exception("Prepare statement failed for exam: " . $conn->error);
            }
            $stmt->bind_param("ssssss", $data['title'], $data['instruction'], $data['year'], $data['section'], $data['code'], $data['course']);
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
                    if (!$stmt_q) {
                        error_log("Prepare statement failed for question: " . $conn->error);
                        throw new Exception("Prepare statement failed for question: " . $conn->error);
                    }
                    $stmt_q->bind_param("iss", $exam_id, $question_text, $answer);
                    if (!$stmt_q->execute()) {
                        error_log("Execute failed for question (ExamID: " . $exam_id . "): " . $stmt_q->error);
                        throw new Exception("Execute failed for question: " . $stmt_q->error);
                    }
                    $question_id = $stmt_q->insert_id;
                    $stmt_q->close();

                    $choices = $q_data['choices'] ?? [];
                    if (!empty($choices)) {
                        $stmt_c = $conn->prepare("INSERT INTO choices (question_id, choice_text) VALUES (?, ?)");
                        if (!$stmt_c) {
                            error_log("Prepare statement failed for choices: " . $conn->error);
                            throw new Exception("Prepare statement failed for choices: " . $conn->error);
                        }
                        $stmt_c->bind_param("is", $bound_question_id, $bound_choice_text);
                        
                        foreach ($choices as $choice_data) {
                            $bound_choice_text = $choice_data['choice_text'] ?? '';
                            $bound_question_id = $question_id;
                            if (!$stmt_c->execute()) {
                                error_log("Execute failed for choice (QID: " . $question_id . ", Choice: " . $bound_choice_text . "): " . $stmt_c->error);
                                throw new Exception("Execute failed for choice: " . $stmt_c->error);
                            }
                        }
                        $stmt_c->close();
                    }
                }
            }


            $conn->commit(); // Commit transaction on success
            echo json_encode(["status" => "success", "message" => "Exam inserted successfully", "exam_id" => $exam_id]);
            exit(); // Added exit
        } catch (Exception $e) {
            $conn->rollback(); // Rollback on error
            error_log("API POST error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Server error during exam creation: " . $e->getMessage()]);
            exit(); // Added exit
        }
        break;

    case "PUT": // Update Exam
        try {
            $conn = $database->getConnection();
            $rawData = file_get_contents("php://input");
            $data = json_decode($rawData, true);

            $exam_id = $data['exam_id'] ?? null;

            if (!$exam_id || !is_numeric($exam_id)) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "Missing or invalid exam ID for update."]);
                exit(); // Added exit
            }

            // Start transaction
            $conn->begin_transaction();

            // 1. Update main exam details
            $update_main = $examModel->updateExamMain(
                (int)$exam_id,
                $data['title'] ?? '',
                $data['instruction'] ?? '',
                $data['year'] ?? '',
                $data['section'] ?? '',
                $data['code'] ?? '',
                $data['course']
            );
            if (!$update_main) {
                throw new Exception("Failed to update main exam details.");
            }

            // 2. Handle Questions (Update, Create, Delete)
            $submitted_questions = $data['questions'] ?? [];
            $existing_question_ids = $examModel->getQuestionIdsForExam((int)$exam_id);
            $processed_question_ids = [];

            foreach ($submitted_questions as $q_data) {
                $q_id = $q_data['question_id'] ?? null;
                $question_text = $q_data['question_text'] ?? '';
                $answer = $q_data['answer'] ?? '';

                if ($q_id && in_array($q_id, $existing_question_ids)) {
                    // Existing question: Update
                    $examModel->updateQuestion((int)$q_id, $question_text, $answer);
                    $processed_question_ids[] = (int)$q_id;
                } else {
                    // New question: Create
                    $new_q_id = $examModel->createQuestion((int)$exam_id, $question_text, $answer);
                    if ($new_q_id) {
                        $q_id = $new_q_id; // Use new ID for choices processing
                        $processed_question_ids[] = (int)$new_q_id;
                    } else {
                        throw new Exception("Failed to create new question.");
                    }
                }

                // Handle Choices for the current question
                $submitted_choices = $q_data['choices'] ?? [];
                $existing_choice_ids = $examModel->getChoiceIdsForQuestion((int)$q_id);
                $processed_choice_ids = [];

                foreach ($submitted_choices as $c_data) {
                    $c_id = $c_data['choice_id'] ?? null;
                    $choice_text = $c_data['choice_text'] ?? '';

                    if ($c_id && in_array($c_id, $existing_choice_ids)) {
                        // Existing choice: Update
                        $examModel->updateChoice((int)$c_id, $choice_text);
                        $processed_choice_ids[] = (int)$c_id;
                    } else {
                        // New choice: Create
                        $new_c_id = $examModel->createChoice((int)$q_id, $choice_text);
                        if ($new_c_id) {
                           $processed_choice_ids[] = (int)$new_c_id;
                        } else {
                            throw new Exception("Failed to create new choice.");
                        }
                    }
                }

                // Delete choices that were in DB but not in submitted data for this question
                $choices_to_delete = array_diff($existing_choice_ids, $processed_choice_ids);
                foreach ($choices_to_delete as $id_to_delete) {
                    $examModel->deleteChoice((int)$id_to_delete);
                }
            }

            // Delete questions that were in DB but not in submitted data for this exam
            $questions_to_delete = array_diff($existing_question_ids, $processed_question_ids);
            foreach ($questions_to_delete as $id_to_delete) {
                $examModel->deleteQuestion((int)$id_to_delete);
            }

            $conn->commit(); // Commit transaction on success
            echo json_encode(["status" => "success", "message" => "Exam updated successfully!"]);
            exit(); // Added exit

        } catch (Exception $e) {
            $conn->rollback(); // Rollback on error
            error_log("PUT exam error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Server error during update: " . $e->getMessage()
            ]);
            exit(); // Added exit
        }
        break;

    case "DELETE":
        try {
            $exam_id = $_GET['exam_id'] ?? null;

            if (!$exam_id || !is_numeric($exam_id)) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "Missing or invalid exam ID for deletion."]);
                exit(); // Added exit
            }

            // Use the StudentExamAttempt model to delete attempts first
            $studentExamAttempt->deleteAttemptsForExam((int)$exam_id);

            // Use the ExamModel to delete the exam (which should cascade to questions and choices if configured)
            // If not configured, deleteExam method in ExamModel should handle questions and choices explicitly.
            if ($examModel->deleteExam((int)$exam_id)) {
                echo json_encode(["status" => "success", "message" => "Exam and all related data deleted successfully."]);
                exit(); // Added exit
            } else {
                http_response_code(404); // Or 500 if deletion logic in model failed for other reasons
                echo json_encode(["status" => "error", "message" => "Exam not found or failed to delete."]);
                exit(); // Added exit
            }

        } catch (Exception $e) {
            error_log("DELETE exam error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Server error during deletion: " . $e->getMessage()
            ]);
            exit(); // Added exit
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Method not allowed"]);
        exit(); // Added exit
        break;
}
