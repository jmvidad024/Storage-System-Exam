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
// If your models use $database internally to call $database->getConnection() every time,
// that's okay, but passing the direct $conn is often more explicit and slightly more efficient.
$user = new User($database);
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
        $is_auto_submit_proctor_violation = $data['is_auto_submit'] ?? false; // This is the old 'is_auto_submit' for proctoring
        $is_time_out = $data['is_time_out'] ?? false; // NEW: Flag for time-out submission

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
        // Assuming getAttemptDetails needs user_id and exam_id, and returns an array with 'id' and 'is_completed'
        $attempt_details = $studentExamAttempt->getAttemptDetails($user_id, (int)$exam_id);
        if (!$attempt_details) {
            throw new Exception("Exam attempt record not found for user_id: {$user_id}, exam_id: {$exam_id}. Please try taking the exam again.");
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
        // Assuming getCorrectAnswersForExam returns an associative array: [question_id => correct_answer_text, ...]
        $correct_answers_map = $examModel->getCorrectAnswersForExam((int)$exam_id);

        $total_score = 0.00;
        $max_possible_score = 0.00;
        $processed_answers = []; // Store answers with correctness/score for later saving

        // Calculate max_possible_score first
        foreach ($correct_answers_map as $q_id => $correct_answer) {
            // Assuming each question is worth 1 point for simplicity, or fetch actual points if available in $correct_answers_map
            $max_possible_score += 1.00; // Each question counts as 1 point max
        }

        // Determine score and process answers based on flags
        if ($is_auto_submit_proctor_violation) {
            $total_score = 0.00; // Force score to 0 for proctoring violations
            // For auditing, save all questions with empty/incorrect answers
            foreach ($correct_answers_map as $q_id => $correct_answer) {
                $processed_answers[] = [
                    'question_id' => (int)$q_id,
                    'submitted_answer' => '', // No answer recorded (or could be last known answer)
                    'is_correct' => false,
                    'score_earned' => 0.00
                ];
            }
            error_log("Proctoring violation auto-submission triggered for attempt_id: {$attempt_id}. Score forced to 0.");

        } else {
            // Normal grading process for manual submission or time-out auto-submission
            foreach ($correct_answers_map as $q_id => $correct_answer) {
                $student_answer = $submitted_answers_raw[$q_id] ?? ''; // Get student's answer for this question
                $s_answer_normalized = trim(strtolower($student_answer));
                $correct_answer_normalized = trim(strtolower($correct_answer));

                // Check if correct answer is not empty to avoid matching empty student answers to empty correct answers
                $is_correct = ($s_answer_normalized === $correct_answer_normalized && $correct_answer_normalized !== '');
                $score_earned = $is_correct ? 1.00 : 0.00; // Assuming 1 point per correct answer

                $total_score += $score_earned;

                $processed_answers[] = [
                    'question_id' => (int)$q_id,
                    'submitted_answer' => $student_answer, // Store original answer
                    'is_correct' => $is_correct,
                    'score_earned' => $score_earned
                ];
            }
            if ($is_time_out) {
                error_log("Time-out auto-submission triggered for attempt_id: {$attempt_id}. Graded normally.");
            } else {
                error_log("Manual submission received for attempt_id: {$attempt_id}. Graded normally.");
            }
        }

        // 3. Create new entry in student_exam_results (PARENT record)
        // Assuming createResult accepts attempt_id, total_score, max_score
        $result_created = $studentExamResult->createResult($attempt_id, $total_score, $max_possible_score);
        if (!$result_created) {
            throw new Exception("Failed to save exam results summary for attempt_id: {$attempt_id}.");
        }

        // 4. Save Individual Answers (CHILD records)
        // This loop will save the answers processed above, which will be empty/incorrect if proctor auto-submitted
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
        // Assuming markAttemptCompleted accepts user_id, exam_id, score, and an auto_submitted_flag
        $mark_attempt_completed_success = $studentExamAttempt->markAttemptCompleted(
            $user_id,
            (int)$exam_id,
            $total_score, // Pass the calculated score (0 if proctoring violation, actual score otherwise)
            $is_auto_submit_proctor_violation // This flag indicates if it was a proctoring auto-submit
        );
        if (!$mark_attempt_completed_success) {
            throw new Exception("Failed to mark exam attempt as completed for attempt_id: {$attempt_id}.");
        }

        $conn->commit(); // Commit transaction on success

        $response_message = "Exam submitted and graded successfully!";
        if ($is_auto_submit_proctor_violation) {
            $response_message = "Exam automatically submitted with score 0 due to proctoring violation.";
        } else if ($is_time_out) {
            $response_message = "Time ran out! Your exam has been submitted for grading.";
        }

        echo json_encode([
            "status" => "success",
            "message" => $response_message,
            "score" => $total_score,
            "max_score" => $max_possible_score,
            "attempt_id" => $attempt_id,
            "is_auto_submit_proctor_violation" => $is_auto_submit_proctor_violation, // Confirm the flag sent back
            "is_time_out" => $is_time_out // Confirm the flag sent back
        ]);

    } catch (Exception $e) {
        // Only rollback if the transaction was successfully started
        if (isset($conn) && $conn instanceof mysqli && $conn->real_query("SELECT 1") && !$conn->autocommit(true)) {
            $conn->rollback(); // Rollback on error
            $conn->autocommit(true); // Re-enable autocommit
        }
        error_log("API POST submit_exam error for user_id: {$user_id}, exam_id: {$exam_id}: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Server error during exam submission: " . $e->getMessage()
        ]);
    } finally {
        // Ensure connection is closed if it was opened
        if (isset($conn) && $conn instanceof mysqli) {
             // Reset autocommit if it was manually turned off
             if (!$conn->autocommit(true)) { // Check if autocommit is off
                 $conn->autocommit(true);
             }
             $conn->close();
        }
    }
} else {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
}
?>