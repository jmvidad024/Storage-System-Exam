<?php
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
AuthMiddleware::requireRole($user, ['student']); // Only students view their own results

$attempt_id = $_GET['attempt_id'] ?? null;
$user_id = $user->getId();

$exam_title = "Exam Results";
$exam_code = "";
$exam_details = "";
$score_display = "";
$questions_data = []; // To store full question details for review

if (!$attempt_id || !is_numeric($attempt_id)) {
    header("Location: dashboard.php?error=invalid_attempt_id");
    exit();
}

try {
    // 1. Get attempt details to verify ownership and get exam_id
    $attempt = $studentExamAttempt->getAttemptDetailsById((int)$attempt_id); // Assuming getAttemptDetailsById is added to StudentExamAttempt
    if (!$attempt || $attempt['user_id'] != $user_id) {
        throw new Exception("Attempt not found or you don't have permission to view these results.");
    }

    $exam_id = $attempt['exam_id'];

    // 2. Get main exam details
    $exam = $examModel->getExamById((int)$exam_id);
    if (!$exam) {
        throw new Exception("Associated exam not found.");
    }
    $exam_title = $exam['title'];
    $exam_code = $exam['code'];
    $exam_details = "Year: " . $exam['year'] . " | Section: " . $exam['section'];

    // 3. Get exam results summary
    $results = $studentExamResult->getResultByAttemptId((int)$attempt_id);
    if ($results) {
        $score_display = "Your Score: " . htmlspecialchars($results['score']) . " / " . htmlspecialchars($results['max_score']) . " (" . round(($results['score'] / $results['max_score']) * 100, 2) . "%)";
    } else {
        $score_display = "Score not yet available.";
    }

    // 4. Get student's individual answers and compare with correct answers
    $student_answers = $studentAnswer->getAnswersByAttemptId((int)$attempt_id);
    $correct_answers_map = $examModel->getCorrectAnswersForExam((int)$exam_id);

    // Combine question details, student answers, and correct answers for display
    foreach ($exam['questions'] as $q_data) {
        $question_id = $q_data['question_id'];
        $student_ans = 'Not answered';
        $is_correct = false;
        $score_earned = 0;

        foreach ($student_answers as $s_ans_data) {
            if ($s_ans_data['question_id'] == $question_id) {
                $student_ans = htmlspecialchars($s_ans_data['submitted_answer']);
                $is_correct = (bool)$s_ans_data['is_correct'];
                $score_earned = (float)$s_ans_data['score_earned'];
                break;
            }
        }

        $questions_data[] = [
            'question_text' => htmlspecialchars($q_data['question_text']),
            'choices' => $q_data['choices'], // Choices include choice_id, choice_text
            'correct_answer' => htmlspecialchars($q_data['answer']), // This is the correct answer
            'student_answer' => $student_ans,
            'is_correct' => $is_correct,
            'score_earned' => $score_earned
        ];
    }

} catch (Exception $e) {
    // Log the error and display a generic message to the user
    error_log("Error in viewExamResults.php: " . $e->getMessage());
    $exam_title = "Error Loading Results";
    $score_display = "An error occurred while loading your results. Please try again later.";
    $questions_data = []; // Clear questions on error
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $exam_title; ?> - Results</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css"> <!-- Reusing some basic styles -->
    <link rel="stylesheet" href="../assets/css/view_exam_result.css"> <!-- NEW: Specific results page styles -->
</head>
<body>
    <header class="page-header">
        <a href="dashboard.php" class="back-button">&larr; Back to Dashboard</a>
        <h1>Exam Results</h1>
    </header>

    <div class="container">
        <div class="results-summary-section">
            <h2 id="exam_results_title"><?php echo $exam_title; ?></h2>
            <p class="exam-code">Exam Code: <?php echo htmlspecialchars($exam_code); ?></p>
            <p class="exam-info"><?php echo htmlspecialchars($exam_details); ?></p>
            <h3 class="score-display"><?php echo $score_display; ?></h3>
        </div>

        <div class="questions-review-section">
            <h3>Question Review</h3>
            <?php if (!empty($questions_data)): ?>
                <?php foreach ($questions_data as $index => $q): ?>
                    <div class="question-review-block <?php echo $q['is_correct'] ? 'correct-answer' : 'incorrect-answer'; ?>">
                        <h4>Question <?php echo $index + 1; ?>: <?php echo $q['question_text']; ?></h4>
                        <div class="choices-display">
                            <?php if (!empty($q['choices'])): ?>
                                <ul>
                                    <?php foreach ($q['choices'] as $choice): ?>
                                        <li class="<?php echo (strtolower($choice['choice_text']) === strtolower($q['student_answer']) && !$q['is_correct']) ? 'student-selected-incorrect' : ''; ?>
                                                   <?php echo (strtolower($choice['choice_text']) === strtolower($q['correct_answer'])) ? 'correct-choice' : ''; ?>">
                                            <?php echo htmlspecialchars($choice['choice_text']); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p>No choices provided for this question.</p>
                            <?php endif; ?>
                        </div>
                        <p class="your-answer">Your Answer: <span><?php echo $q['student_answer']; ?></span></p>
                        <p class="correct-answer-display">Correct Answer: <span><?php echo $q['correct_answer']; ?></span></p>
                        <p class="question-score">Score: <?php echo htmlspecialchars($q['score_earned']); ?> / 1</p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-questions-message">No questions to review or an error occurred.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
