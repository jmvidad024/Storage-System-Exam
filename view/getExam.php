<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/user.php';
require_once '../controllers/AuthMiddleware.php';

$database = new Database();
$user = new User($database);

AuthMiddleware::authenticate($user);
AuthMiddleware::requireRole($user, ['admin', 'faculty']);

// Message handling from query parameters (kept for initial load feedback)
$message = '';
$message_type = '';
if (isset($_GET['message'])) {
    if ($_GET['message'] === 'exam_created_success') {
        $message = 'Exam created successfully!';
        $message_type = 'success';
    } elseif ($_GET['message'] === 'exam_updated_success') {
        $message = 'Exam updated successfully!';
        $message_type = 'success';
    } elseif ($_GET['message'] === 'exam_deleted_success') {
        $message = 'Exam deleted successfully!';
        $message_type = 'success';
    } elseif ($_GET['message'] === 'exam_not_found') {
        $message = 'Exam not found.';
        $message_type = 'error';
    } elseif ($_GET['message'] === 'attempts_deleted_for_exam') {
        $message = 'Exam updated and all previous student attempts deleted!';
        $message_type = 'success';
    } elseif ($_GET['message'] === 'exam_updated_attempts_kept') {
        $message = 'Exam updated, student attempts were kept.';
        $message_type = 'success';
    } elseif ($_GET['message'] === 'exam_updated_modal_dismissed') {
        $message = 'Exam updated. Delete attempts confirmation dismissed.';
        $message_type = 'success';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Exams</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=1">
    <link rel="stylesheet" href="../assets/css/get_exam.css">
</head>
<body>
    <header class="page-header">
        <a href="dashboard.php" class="back-button">&larr; Back to Dashboard</a>
        <h1>Manage Exams</h1>
    </header>

    <div class="container">
        <div class="exam-management-section">
            <?php if (!empty($message)): ?>
                <div class="message-area <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="top-controls">
                <h2>Find Exam by Code</h2>
                <form action="" method="GET" id="get_exam" class="exam-search-form">
                    <input type="text" name="code" id="exam_code_input" placeholder="Enter Exam Code (e.g., CS101-Midterm)">
                    <button type="submit" class="btn btn-search">Get Exam</button>
                </form>
                <div id="loading_indicator" class="loading-indicator hidden">Loading...</div>
            </div>

            <div id="single_exam_result" class="exam-result-section">
                <p class="placeholder-text">Enter an exam code above to view its details, or see the list below.</p>
            </div>

            <hr class="section-divider">

            <h2 id="all_exams_heading">All Existing Exams</h2>
            <div class="button-group">
                <a href="createExam.php" class="btn btn-create">Create New Exam</a>
            </div>

            <div id="all_exams_table_container" class="table-container">
                <p class="loading-indicator">Loading all exams...</p>
                </div>

            <div id="deleteConfirmationModal" class="modal">
                <div class="modal-content">
                    <h2>Confirm Delete Exam</h2>
                    <p>Are you sure you want to delete this exam? This action cannot be undone and will also delete all associated questions, choices, and student attempts/results.</p>
                    <div class="modal-buttons">
                        <button id="confirmDeleteBtn" class="btn btn-confirm">Delete Exam</button>
                        <button id="cancelDeleteBtn" class="btn btn-cancel">Cancel</button>
                    </div>
                </div>
            </div>

            <div id="action_message_area" class="message-area hidden"></div>

        </div>
    </div>

    <script src="../assets/js/get_exam.js"></script>
</body>
</html>
