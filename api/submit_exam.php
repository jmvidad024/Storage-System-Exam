<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/user.php';
require_once '../models/examAttempt.php';
require_once '../models/examModel.php';         
require_once '../models/studentExamResult.php'; 
require_once '../models/studentAnswer.php';     
require_once '../controllers/AuthMiddleware.php';

$database = new Database();
$user = new User($database);
$studentExamAttempt = new StudentExamAttempt($database);
$examModel = new ExamModel($database);             
$studentExamResult = new StudentExamResult($database); 
$studentAnswer = new StudentAnswer($database);     

AuthMiddleware::authenticate($user);
AuthMiddleware::requireRole($user, ['student']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $rawData = file_get_contents("php://input");
        $data = json_decode($rawData, true);

        $exam_id = $data['exam_id'] ?? null;
        $submitted_answers_raw = $data['answers'] ?? []; // Raw answers from client

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

        $conn = $database->getConnection();
        $conn->begin_transaction(); // Start transaction for atomicity

        // 1. Get the attempt_id for this student and exam
        // (This should exist because takeExam.php creates it when the exam is started)
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

        // Process submitted answers to determine correctness and calculate score
        // This loop now *only* calculates score, it does not save answers yet.
        $processed_answers = []; // Store answers with correctness/score for later saving
        foreach ($submitted_answers_raw as $question_id => $student_answer) {
            $q_id = (int)$question_id;
            $s_answer = trim(strtolower($student_answer)); // Normalize for comparison

            $correct_answer = trim(strtolower($correct_answers_map[$q_id] ?? ''));

            $is_correct = ($s_answer === $correct_answer && $correct_answer !== '');
            $score_earned = $is_correct ? 1.00 : 0.00; // 1 point for correct, 0 for incorrect

            if (isset($correct_answers_map[$q_id])) { // Only count if question exists in correct answers
                $max_possible_score += 1; // Each question counts as 1 point max
            }
            $total_score += $score_earned;

            $processed_answers[] = [
                'question_id' => $q_id,
                'submitted_answer' => $student_answer, // Keep original casing for storage
                'is_correct' => $is_correct,
                'score_earned' => $score_earned
            ];
        }

        // 3. Create new entry in student_exam_results (PARENT record)
        $result_created = $studentExamResult->createResult($attempt_id, $total_score, $max_possible_score);
        if (!$result_created) {
            throw new Exception("Failed to save exam results summary.");
        }
        // $result_id is not directly needed for student_answers as they link to attempt_id

        // 4. Save Individual Answers (CHILD records, now that parent exists)
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

        // 5. Update student_exam_attempts to mark as completed
        $mark_attempt_completed_success = $studentExamAttempt->markAttemptCompleted($user_id, (int)$exam_id);
        if (!$mark_attempt_completed_success) {
            throw new Exception("Failed to mark exam attempt as completed.");
        }

        $conn->commit(); // Commit transaction on success

        echo json_encode([
            "status" => "success",
            "message" => "Exam submitted and graded successfully!",
            "score" => $total_score,
            "max_score" => $max_possible_score,
            "attempt_id" => $attempt_id 
        ]);

    } catch (Exception $e) {
        $conn->rollback(); // Rollback on error
        error_log("API POST submit_exam error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Server error during exam submission: " . $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
}
