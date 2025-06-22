<?php
require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/user.php';
require_once '../models/examModel.php';
require_once '../controllers/AuthMiddleware.php';

$database = new Database();
$user = new User($database);
$examModel = new ExamModel($database);

// Authenticate and ensure role is admin or faculty
AuthMiddleware::authenticate($user);
AuthMiddleware::requireRole($user, ['admin', 'faculty']);

$exam_id = $_GET['exam_id'] ?? null;
$exam_data_json = 'null'; // Initialize as 'null' string for JS

if ($exam_id && is_numeric($exam_id)) {
    $exam_data = $examModel->getExamById((int)$exam_id);
    if ($exam_data) {
        // Encode the full exam data to JSON
        $exam_data_json = json_encode($exam_data);
    }
}

// Message handling for feedback after save/delete attempts
$message = '';
$message_type = '';
if (isset($_GET['status']) && isset($_GET['message'])) {
    $message_type = htmlspecialchars($_GET['status']);
    $message = htmlspecialchars($_GET['message']);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/create_exam.css"> <!-- Reusing some styles -->
    <link rel="stylesheet" href="../assets/css/edit_exam.css"> <!-- Specific edit styles -->
    <title>Edit Exam</title>
</head>
<body>
    <header class="page-header">
        <a href="getExam.php" class="back-button">&larr; Back to Manage Exams</a>
        <h1>Edit Exam</h1>
    </header>

    <div class="container">
        <div class="form-section">
            <?php if (!empty($message)): ?>
                <div class="message-area <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" id="exam_edit_form">
                <input type="hidden" name="exam_id" id="exam_id_hidden" value="<?php echo htmlspecialchars($exam_id); ?>">

                <div class="title_box">
                    <label for="title">Exam Title</label>
                    <input type="text" name="title" id="title" placeholder="Enter Title" required>
                    
                    <label for="instruction">Exam Instructions</label>
                    <input type="text" name="instruction" id="instruction" placeholder="Enter Instruction" required>

                    <div class="form-row">
                        <div class="form-group-inline">
                            <label for="year">Year</label>
                            <select name="year" id="year" required>
                                <option value="">Select Year</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                            </select>
                        </div>
                        <div class="form-group-inline">
                            <label for="section">Section</label>
                            <select name="section" id="section" required>
                                <option value="">Select Section</option>
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                            </select>
                        </div>
                        <div class="form-group-inline">
                            <label for="course">Course</label>
                            <select name="course" id="course">
                                <option value="">Select Course</option>
                                <option value="Computer Science">Computer Science</option>
                                <option value="Information Technology">Information Technology</option>
                                <option value="Education">Education</option>
                                <option value="Engineering">Engineering</option>
                            </select>

                            <!-- Add this hidden field to store the combined course:major value -->
                            <input type="hidden" id="course_major_db" name="course_major_db">

                            <!-- Add the majors dropdown (initially hidden) -->
                            <div class="form-group-inline" id="major-group" style="display: none;">
                                <label for="major">Major</label>
                                <select id="major" name="major">
                                    <option value="">Select Major</option>
                                    <!-- JavaScript will populate this -->
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <label for="code">Exam Code</label>
                    <input type="text" name="code" id="code" placeholder="Enter Exam Code" required>
                </div>

                <div id="questions_container">
                    <!-- Existing questions will be loaded here by JS -->
                </div>

                <button type="button" id="add_question_btn" class="btn btn-add">Add New Question</button>
                <button type="submit" id="save_exam_btn" class="btn btn-submit">Save Exam Changes</button>

                <!-- Removed: Button to delete all attempts for this exam is now handled by JS after save -->
            </form>
        </div>
    </div>

    <!-- NEW: Confirmation Modal for Deleting Attempts (still needed, but triggered by JS) -->
    <div id="deleteAttemptsConfirmationModal" class="modal">
        <div class="modal-content">
            <h2>Confirm Deletion of All Attempts</h2>
            <p>Exam changes have been saved. Would you like to also delete ALL student attempts, results, and answers for this exam? This action cannot be undone.</p>
            <div class="modal-buttons">
                <button id="confirmDeleteAttemptsBtn" class="btn btn-confirm">Delete All Attempts</button>
                <button id="cancelDeleteAttemptsBtn" class="btn btn-cancel">Keep Attempts</button>
            </div>
        </div>
    </div>
    
    <!-- Message Area for actions (re-using from getExam.php, ensure it's in editExam.php HTML as well) -->
    <div id="action_message_area" class="message-area hidden"></div>

    <!-- NEW: This div passes the PHP exam data to JavaScript -->
    <div id="exam_initial_data" data-initial-exam-data='<?php echo $exam_data_json; ?>'></div>

    <!-- JavaScript to handle dynamic form and submission -->
    <script src="../assets/js/edit_exam.js"></script>
</body>
</html>
