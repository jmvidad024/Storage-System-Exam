<?php

ini_set('display_errors', 0); // Turn off error output to browser
ini_set('display_startup_errors', 0);
error_reporting(E_ALL); // Still log everything
require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/user.php';
require_once '../models/examModel.php'; // Include ExamModel for consistent database interaction
require_once '../controllers/AuthMiddleware.php';

$database = new Database();
$user = new User($database);
$examModel = new ExamModel($database); // Initialize ExamModel

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
            $exam_id_filter = $_GET['exam_id'] ?? null; // Allow fetching by ID too

            $exam = false;
            if ($code_filter) {
                // If fetching by code, first get the exam_id
                $conn = $database->getConnection();
                $stmt = $conn->prepare("SELECT exam_id FROM exams WHERE code = ? LIMIT 1");
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param("s", $code_filter);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                if ($row) {
                    $exam_id_filter = $row['exam_id'];
                }
                $stmt->close();
            }

            if (!$exam_id_filter) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Missing required parameter: code or exam_id"
                ]);
                break;
            }

            // Use ExamModel to get full exam details including answers for admin/faculty
            $exam = $examModel->getExamById((int)$exam_id_filter);
            
            if (!$exam) {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "Exam not found"]);
                break;
            }
            
            echo json_encode($exam); // This includes answers for admin/faculty view

        } catch (Exception $e) {
            error_log("GET exam error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Server error. Please try again later."
            ]);
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
                !isset($data['code'])
            ) {
                throw new Exception("Missing required fields for new exam: title, instruction, year, section, or code");
            }

            // Insert exam
            $stmt = $conn->prepare("INSERT INTO exams (title, instruction, year, section, code) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt) {
                error_log("Prepare statement failed for exam: " . $conn->error);
                throw new Exception("Prepare statement failed for exam: " . $conn->error);
            }
            $stmt->bind_param("sssss", $data['title'], $data['instruction'], $data['year'], $data['section'], $data['code']);
            if (!$stmt->execute()) {
                error_log("Execute failed for exam: " . $stmt->error);
                throw new Exception("Execute failed for exam: " . $stmt->error);
            }
            $exam_id = $stmt->insert_id;
            $stmt->close();

            // Insert questions and choices
            $index = 1;
            while (isset($data["question_$index"])) {
                $question_text = $data["question_$index"] ?? '';
                $answer = $data["answer_$index"] ?? '';

                $stmt_q = $conn->prepare("INSERT INTO questions (exam_id, question_text, answer) VALUES (?, ?, ?)");
                if (!$stmt_q) {
                    error_log("Prepare statement failed for question: " . $conn->error);
                    throw new Exception("Prepare statement failed for question: " . $conn->error);
                }
                $stmt_q->bind_param("iss", $exam_id, $question_text, $answer);
                if (!$stmt_q->execute()) {
                    error_log("Execute failed for question (ID: " . $exam_id . "): " . $stmt_q->error);
                    throw new Exception("Execute failed for question: " . $stmt_q->error);
                }
                $question_id = $stmt_q->insert_id;
                $stmt_q->close();

                $choices = $data["choices_{$index}"] ?? [];

                if (!empty($choices)) {
                    $stmt_c = $conn->prepare("INSERT INTO choices (question_id, choice_text) VALUES (?, ?)");
                    if (!$stmt_c) {
                        error_log("Prepare statement failed for choices: " . $conn->error);
                        throw new Exception("Prepare statement failed for choices: " . $conn->error);
                    }
                    $stmt_c->bind_param("is", $bound_question_id, $bound_choice_text);
                    
                    foreach ($choices as $choice) {
                        $bound_choice_text = $choice;
                        $bound_question_id = $question_id;
                        if (!$stmt_c->execute()) {
                            error_log("Execute failed for choice (QID: " . $question_id . ", Choice: " . $choice . "): " . $stmt_c->error);
                            throw new Exception("Execute failed for choice: " . $stmt_c->error);
                        }
                    }
                    $stmt_c->close();
                }
                $index++;
            }

            echo json_encode(["status" => "success", "message" => "Exam inserted successfully"]);
        } catch (Exception $e) {
            error_log("API POST error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
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
                break;
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
                $data['code'] ?? ''
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

        } catch (Exception $e) {
            $conn->rollback(); // Rollback on error
            error_log("PUT exam error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Server error during update: " . $e->getMessage()
            ]);
        }
        break;

    case "DELETE":
        try {
            $exam_id = $_GET['exam_id'] ?? null;

            if (!$exam_id || !is_numeric($exam_id)) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "Missing or invalid exam ID for deletion."]);
                break;
            }

            $conn = $database->getConnection();
            $conn->begin_transaction();

            // Manual deletion order if cascades aren't fully relied upon:
            // 1. Delete student exam attempts related to this exam
            $stmt_attempts = $conn->prepare("DELETE FROM student_exam_attempts WHERE exam_id = ?");
            if (!$stmt_attempts) { throw new Exception("Prepare failed for deleting attempts: " . $conn->error); }
            $stmt_attempts->bind_param("i", $exam_id);
            $stmt_attempts->execute();
            $stmt_attempts->close();

            // 2. Delete choices and questions for this exam
            // Fetch all question_ids for this exam first
            $stmt_get_q_ids = $conn->prepare("SELECT question_id FROM questions WHERE exam_id = ?");
            if (!$stmt_get_q_ids) { throw new Exception("Prepare failed for getting question IDs: " . $conn->error); }
            $stmt_get_q_ids->bind_param("i", $exam_id);
            $stmt_get_q_ids->execute();
            $q_ids_result = $stmt_get_q_ids->get_result();
            $question_ids_to_delete = [];
            while ($row = $q_ids_result->fetch_assoc()) {
                $question_ids_to_delete[] = $row['question_id'];
            }
            $stmt_get_q_ids->close();

            // If there are questions, delete their choices first
            if (!empty($question_ids_to_delete)) {
                $placeholders = implode(',', array_fill(0, count($question_ids_to_delete), '?'));
                $types = str_repeat('i', count($question_ids_to_delete));

                $stmt_c_del = $conn->prepare("DELETE FROM choices WHERE question_id IN ($placeholders)");
                if (!$stmt_c_del) { throw new Exception("Prepare failed for deleting choices: " . $conn->error); }
                $stmt_c_del->bind_param($types, ...$question_ids_to_delete);
                $stmt_c_del->execute();
                $stmt_c_del->close();

                // Then delete the questions themselves
                $stmt_q_del = $conn->prepare("DELETE FROM questions WHERE exam_id = ?");
                if (!$stmt_q_del) { throw new Exception("Prepare failed for deleting questions: " . $conn->error); }
                $stmt_q_del->bind_param("i", $exam_id);
                $stmt_q_del->execute();
                $stmt_q_del->close();
            }

            // 3. Delete the main exam record
            $stmt = $conn->prepare("DELETE FROM exams WHERE exam_id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed for deleting exam: " . $conn->error);
            }
            $stmt->bind_param("i", $exam_id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $conn->commit();
                echo json_encode(["status" => "success", "message" => "Exam and all related data deleted successfully."]);
            } else {
                $conn->rollback();
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "Exam not found or already deleted."]);
            }
            $stmt->close();

        } catch (Exception $e) {
            $conn->rollback();
            error_log("DELETE exam error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Server error during deletion: " . $e->getMessage()
            ]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Method not allowed"]);
        break;
}
