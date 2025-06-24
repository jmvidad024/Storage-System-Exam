<?php
session_start(); // Ensure session is started at the very beginning
require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/user.php';
require_once '../controllers/AuthMiddleware.php';

$database = new Database();
$db = $database->getConnection(); // Get the mysqli connection
$user = new User($database);

// Check authentication and role
AuthMiddleware::authenticate($user);
AuthMiddleware::requireRole($user, ['admin', 'faculty']);

// Define hardcoded courses and majors (PHP mirror of JS data for initial rendering)
$coursesAndMajors = [
    'Education' => ['Science', 'Math', 'English', 'History'],
    'Engineering' => ['Civil', 'Electrical', 'Mechanical', 'Computer'],
    'Computer Science' => [], // No majors for Computer Science in this example
    'Information Technology' => [] // Add IT as it's in your dropdown
];

$is_faculty = ($user->role === 'faculty');
$faculty_assigned_course_major = null;

if ($is_faculty) {
    // If faculty, fetch their assigned course_major from faculty_details table
    $faculty_assigned_course_major = $user->getFacultyCourseMajor($user->id);
    if (!$faculty_assigned_course_major) {
        // Handle case where faculty user has no assigned course (e.g., redirect or error)
        $_SESSION['error_message'] = "Faculty account not assigned to a course. Please contact administrator.";
        header("Location: dashboard.php"); // Or wherever appropriate
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/create_exam.css?v=3">
    <title>Create Exam</title>
</head>
<body>

<header class="page-header">
    <a href="dashboard.php" class="back-button">&larr; Back to Dashboard</a>
    <h1>Create Exam</h1>
</header>

<div class="form_layout">
    <form action="" method="POST" id="question_form">
        <div class="title_box">
            <input type="text" name="title" id="title" placeholder="Enter Title">
            <input type="text" name="instruction" id="instruction" placeholder="Enter Instruction">

            <select name="year" id="year" required>
                <option value="">Select Year</option>
                <option value="1">1</option>
                <option value="2">2</option>
                <option value="3">3</option>
                <option value="4">4</option>
            </select>

            <select name="section" id="section" required>
                <option value="">Select Section</option>
                <option value="A">A</option>
                <option value="B">B</option>
                <option value="C">C</option>
            </select>

            <select name="course_display" id="course" required
                <?php if ($is_faculty): ?>
                    disabled
                <?php endif; ?>
            >
                <option value="">Select Course</option>
                <?php foreach (array_keys($coursesAndMajors) as $courseName): ?>
                    <option value="<?= htmlspecialchars($courseName) ?>"
                        <?php
                        // If faculty, pre-select their assigned course
                        if ($is_faculty) {
                            $faculty_course_only = explode(' : ', $faculty_assigned_course_major, 2)[0];
                            if ($faculty_course_only === $courseName) {
                                echo 'selected';
                            }
                        }
                        ?>>
                        <?= htmlspecialchars($courseName) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="hidden" id="course_major_db" name="course_major_db"
                    value="<?= htmlspecialchars($is_faculty ? ($faculty_assigned_course_major ?? '') : '') ?>">

            <div class="form-group" id="major-group"
                <?php
                // Determine if major group should be initially visible for faculty
                $show_major_group = false;
                if ($is_faculty && $faculty_assigned_course_major) {
                    $faculty_course_parts = explode(' : ', $faculty_assigned_course_major, 2);
                    $faculty_course_name_only = $faculty_course_parts[0];
                    if (isset($coursesAndMajors[$faculty_course_name_only]) && !empty($coursesAndMajors[$faculty_course_name_only])) {
                        $show_major_group = true;
                    }
                }
                // For admin, we want it hidden initially, the JS will show it on course selection
                echo $show_major_group ? '' : 'style="display: none;"';
                ?>
            >
                <select id="major" name="major_display"
                    <?php if ($is_faculty): ?>
                        disabled
                    <?php endif; ?>
                >
                    <option value="">Select Major</option>
                    <?php
                    // Populate majors for initial display if faculty
                    $current_course_for_majors = '';
                    $current_major_for_selection = '';
                    if ($is_faculty && $faculty_assigned_course_major) {
                        $parts = explode(' : ', $faculty_assigned_course_major, 2);
                        $current_course_for_majors = $parts[0];
                        $current_major_for_selection = $parts[1] ?? '';
                    }

                    if (isset($coursesAndMajors[$current_course_for_majors])) {
                        foreach ($coursesAndMajors[$current_course_for_majors] as $majorName) {
                            echo '<option value="' . htmlspecialchars($majorName) . '"' .
                                ($current_major_for_selection === $majorName ? ' selected' : '') .
                                '>' . htmlspecialchars($majorName) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>

            <input type="text" name="code" id="code" placeholder="Enter Exam Code" required>
            <input type="number" id="duration" name="duration" min="1" placeholder="e.g., 60" required>
        </div>

        <button type="button" id="add_button">Add Question</button>
        <button type="submit" id="submit_button">Submit</button>
    </form>
</div>

<div id="confirmationModal" class="modal">
    <div class="modal-content">
        <h2>Confirm Submission</h2>
        <p>Please review the exam details before submitting:</p>
        <div id="modal-form-summary" class="form-summary-display">
        </div>
        <div class="modal-buttons">
            <button id="confirmSubmitBtn" class="btn confirm-btn">Confirm</button>
            <button id="cancelSubmitBtn" class="btn cancel-btn">Cancel</button>
        </div>
    </div>
</div>

<script>
    // Pass PHP variables to JavaScript
    const isFaculty = <?= json_encode($is_faculty); ?>;
    const facultyAssignedCourseMajor = <?= json_encode($faculty_assigned_course_major); ?>;
    const coursesAndMajors = <?= json_encode($coursesAndMajors); ?>; // Make sure this is also defined in JS
</script>
<script src="../assets/js/create_exam.js?v=1"></script>
</body>
</html>