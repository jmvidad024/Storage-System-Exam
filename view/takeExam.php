<?php
require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/user.php';
require_once '../models/examModel.php'; // Include ExamModel to get exam duration
require_once '../models/examAttempt.php'; // Needed to check initial attempt status
require_once '../controllers/AuthMiddleware.php';


$database = new Database();
$user = new User($database);
$examModel = new ExamModel($database); // Initialize ExamModel
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

// Get the exam details, including duration
$exam_details_from_db = $examModel->getExamById((int)$exam_id);

if (!$exam_details_from_db) {
    header("Location: dashboard.php?error=exam_not_found");
    exit();
}

$exam_duration_minutes = $exam_details_from_db['duration_minutes'] ?? 0; // Get duration

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

// Store the fetched exam title, instruction, etc., for initial display
$exam_title = htmlspecialchars($exam_details_from_db['title'] ?? 'N/A');
$exam_instruction = htmlspecialchars($exam_details_from_db['instruction'] ?? 'N/A');
$exam_code = htmlspecialchars($exam_details_from_db['code'] ?? 'N/A');
$exam_year = htmlspecialchars($exam_details_from_db['year'] ?? 'N/A');
$exam_section = htmlspecialchars($exam_details_from_db['section'] ?? 'N/A');

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Exam</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css"> <!-- Basic layout styles -->
    <link rel="stylesheet" href="../assets/css/take_exam.css"> <!-- Specific exam page styles -->
    <style>
        /* Basic styling for the timer, adjust as needed */
        #exam_timer {
            font-size: 1.8em;
            font-weight: bold;
            color: #d9534f; /* Red color for urgency */
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
            border: 2px solid #d9534f;
            border-radius: 8px;
            background-color: #fdd;
            display: none; /* Hidden by default, shown by JS when timer starts */
        }
    </style>
</head>
<body>
    <header class="page-header">
        <a href="dashboard.php" class="back-button">&larr; Back to Dashboard</a>
        <h1>Take Exam</h1>
    </header>

    <div class="container">
        <div class="exam-content-section">
            <div id="exam_header">
                <h2 id="exam_title"><?php echo $exam_title; ?></h2>
                <p id="exam_instruction">Instructions: <?php echo $exam_instruction; ?></p>
                <p id="exam_details">Code: <?php echo $exam_code; ?> | Year: <?php echo $exam_year; ?> | Section: <?php echo $exam_section; ?></p>
                <div id="exam_timer" style="display: none;"></div> <!-- Timer display element -->
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
         data-attempt-id="<?php echo htmlspecialchars($attempt_id); ?>"
         data-duration-minutes="<?php echo htmlspecialchars($exam_duration_minutes); ?>"> <!-- NEW: Pass duration -->
    </div>

    <script src="../assets/js/take_exam.js"></script>
</body>
</html>