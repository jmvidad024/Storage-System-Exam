<?php
require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/user.php';
require_once '../models/examAttempt.php'; // Needed to check initial attempt status
require_once '../controllers/AuthMiddleware.php';


$database = new Database();
$user = new User($database);
$studentExamAttempt = new StudentExamAttempt($database);

AuthMiddleware::authenticate($user);
AuthMiddleware::requireRole($user, ['student']);

$exam_id = $_GET['exam_id'] ?? null;
$user_id = $user->getId();

// Validate exam_id
if (!$exam_id || !is_numeric($exam_id)) {
    header("Location: dashboard.php?error=invalid_exam_id");
    exit();
}

// Get or create the attempt record.
// This will return the existing attempt_id if it exists, or create a new one.
$attempt_id = $studentExamAttempt->createAttempt($user_id, (int)$exam_id, false); 

if (!$attempt_id) {
    // This indicates a critical error where attempt couldn't be created/retrieved
    header("Location: dashboard.php?error=failed_to_start_exam");
    exit();
}

// Check if the student has already completed this exam (based on the attempt record)
$attempt_details = $studentExamAttempt->getAttemptDetails($user_id, (int)$exam_id);
if ($attempt_details && $attempt_details['is_completed']) {
    // If already completed, redirect to the view results page for this attempt
    header("Location: viewExamResult.php?attempt_id=" . $attempt_details['id'] . "&message=exam_already_completed");
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Exam</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css"> <!-- Basic layout styles -->
    <link rel="stylesheet" href="../assets/css/take_exam.css"> <!-- Specific exam page styles -->
</head>
<body>
    <header class="page-header">
        <a href="dashboard.php" class="back-button">&larr; Back to Dashboard</a>
        <h1>Take Exam</h1>
    </header>

    <div class="container">
        <div class="exam-content-section">
            <div id="exam_header">
                <h2 id="exam_title">Loading Exam...</h2>
                <p id="exam_instruction"></p>
                <p id="exam_details"></p>
            </div>

            <form id="exam_form" class="exam-form">
                <div id="exam_questions">
                    <p class="loading-message">Fetching questions...</p>
                    <!-- Questions will be dynamically loaded here -->
                </div>
                <button type="submit" id="submit_exam_btn" class="btn btn-submit" style="display: none;">Submit Exam</button>
            </form>

            <div id="exam_message_area" class="message-area" style="display: none;"></div>
        </div>
    </div>

    <!-- Data attributes to pass essential info to JS -->
    <div id="exam_data_container" 
         data-exam-id="<?php echo htmlspecialchars($exam_id); ?>"
         data-user-id="<?php echo htmlspecialchars($user_id); ?>"
         data-attempt-id="<?php echo htmlspecialchars($attempt_id); ?>"></div> <!-- NEW: Pass attempt_id -->

    <script src="../assets/js/take_exam.js">
        
    </script>
</body>
</html>
