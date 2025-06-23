<?php
// submit_exam.php

ini_set('display_errors', 0); // Keep this at 0 for production API endpoints
ini_set('display_startup_errors', 0);
error_reporting(E_ALL); // Log all errors for debugging

require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/user.php';
require_once '../models/examAttempt.php';
require_once '../models/examModel.php';
require_once '../models/studentExamResult.php';
require_once '../models/studentAnswer.php';
require_once '../controllers/AuthMiddleware.php';

$database = new Database();
$conn = $database->getConnection(); // Get the MySQLi connection here

// Check if connection is successful. If not, exit early.
if ($conn === null || $conn->connect_error) {
    error_log("Failed to get database connection in submit_exam.php: " . ($conn ? $conn->connect_error : 'Connection object is null'));
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection error. Please try again later."]);
    exit();
}

// Instantiate models with the *active* connection object
// Ensure your models use $conn, not $database, for their operations
$user = new User($database); // Assuming User model can take the Database object or just $conn
$studentExamAttempt = new StudentExamAttempt($database);
$examModel = new ExamModel($database);
$studentExamResult = new StudentExamResult($database);
$studentAnswer = new StudentAnswer($database);

// Authenticate and authorize role
AuthMiddleware::authenticate($user);
AuthMiddleware::requireRole($user, ['student']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $rawData = file_get_contents("php://input");
        $data = json_decode($rawData, true);

        $exam_id = $data['exam_id'] ?? null;
        $submitted_answers_raw = $data['answers'] ?? []; // Raw answers from client
        $is_auto_submit = $data['is_auto_submit'] ?? false; // Flag from frontend

        // Ensure the authenticated user is the one submitting (security check)
        $user_id = $user->getId();
        if (!$user_id) {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "User not identified."]);
            exit();
        }

        if (!$exam_id || !is_numeric($exam_id)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid exam ID provided."]);
            exit();
        }

        $conn->begin_transaction(); // Start transaction for atomicity (MySQLi)

        // 1. Get the attempt_id for this student and exam
        $attempt_details = $studentExamAttempt->getAttemptDetails($user_id, (int)$exam_id);
        if (!$attempt_details) {
            throw new Exception("Exam attempt record not found. Please try taking the exam again.");
        }
        $attempt_id = $attempt_details['id'];

        // Prevent resubmission if already completed
        if ($attempt_details['is_completed']) {
            $conn->rollback(); // No changes made, rollback transaction
            http_response_code(409); // Conflict
            echo json_encode(["status" => "error", "message" => "This exam has already been completed."]);
            exit();
        }

        // 2. Fetch correct answers for the exam and calculate total score/max score
        $correct_answers_map = $examModel->getCorrectAnswersForExam((int)$exam_id);

        $total_score = 0;
        $max_possible_score = 0;
        $processed_answers = []; // Store answers with correctness/score for later saving

        // Determine score and process answers based on auto-submit flag
        if ($is_auto_submit) {
            $total_score = 0.00; // Force score to 0 for auto-submitted exams
            // Populate processed_answers with empty/incorrect entries for all questions
            // to maintain a record for auditing, linking all questions to the attempt.
            foreach ($correct_answers_map as $q_id => $correct_answer) {
                $processed_answers[] = [
                    'question_id' => (int)$q_id,
                    'submitted_answer' => '', // No answer recorded
                    'is_correct' => false,
                    'score_earned' => 0.00
                ];
                $max_possible_score += 1; // Each question still counts towards max score
            }
            // Log the auto-submission event
            error_log("Auto-submission triggered for attempt_id: {$attempt_id}. Score set to 0.");

        } else {
            // Normal grading process for manual submission
            foreach ($correct_answers_map as $q_id => $correct_answer) {
                 $max_possible_score += 1; // Each question counts as 1 point max

                $student_answer = $submitted_answers_raw[$q_id] ?? ''; // Get student's answer for this question
                $s_answer_normalized = trim(strtolower($student_answer));
                $correct_answer_normalized = trim(strtolower($correct_answer));

                $is_correct = ($s_answer_normalized === $correct_answer_normalized && $correct_answer_normalized !== '');
                $score_earned = $is_correct ? 1.00 : 0.00;

                $total_score += $score_earned;

                $processed_answers[] = [
                    'question_id' => (int)$q_id,
                    'submitted_answer' => $student_answer, // Store original answer
                    'is_correct' => $is_correct,
                    'score_earned' => $score_earned
                ];
            }
        }

        // 3. Create new entry in student_exam_results (PARENT record)
        $result_created = $studentExamResult->createResult($attempt_id, $total_score, $max_possible_score);
        if (!$result_created) {
            throw new Exception("Failed to save exam results summary.");
        }

        // 4. Save Individual Answers (CHILD records)
        // This loop will save the answers processed above, which will be empty/incorrect if auto-submitted
        foreach ($processed_answers as $ans_data) {
            $answer_saved = $studentAnswer->createAnswer(
                $attempt_id,
                $ans_data['question_id'],
                $ans_data['submitted_answer'],
                $ans_data['is_correct'],
                $ans_data['score_earned']
            );
            if (!$answer_saved) {
                throw new Exception("Failed to save answer for question ID: " . $ans_data['question_id']);
            }
        }

        // 5. Update student_exam_attempts to mark as completed and set auto_submitted flag
        $mark_attempt_completed_success = $studentExamAttempt->markAttemptCompleted(
            $user_id,
            (int)$exam_id,
            $total_score, // Pass the calculated score
            $is_auto_submit // Pass the auto_submit flag
        );
        if (!$mark_attempt_completed_success) {
            throw new Exception("Failed to mark exam attempt as completed.");
        }

        $conn->commit(); // Commit transaction on success

        echo json_encode([
            "status" => "success",
            "message" => "Exam submitted and graded successfully!",
            "score" => $total_score,
            "max_score" => $max_possible_score,
            "attempt_id" => $attempt_id,
            "is_auto_submit" => $is_auto_submit
        ]);

    } catch (Exception $e) {
        // Only rollback if the transaction was successfully started
        if (isset($conn) && $conn instanceof mysqli && !$conn->autocommit(true)) { // Check if autocommit is off
             $conn->rollback(); // Rollback on error
             $conn->autocommit(true); // Re-enable autocommit
        }
        error_log("API POST submit_exam error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Server error during exam submission: " . $e->getMessage()
        ]);
    } finally {
        // Ensure connection is closed if it was opened
        if (isset($conn) && $conn instanceof mysqli) {
             // Reset autocommit if it was manually turned off
             if (!$conn->autocommit(true)) {
                 $conn->autocommit(true);
             }
             $conn->close();
        }
    }
} else {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
}