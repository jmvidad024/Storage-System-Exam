<?php
ini_set('display_errors', 0); // Turn off error output to browser
ini_set('display_startup_errors', 0);
error_reporting(E_ALL); // Still log everything

require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/user.php';
require_once '../controllers/AuthMiddleware.php';

$database = new Database();
$user = new User($database);

// Check authentication and role for API access
AuthMiddleware::authenticate($user);
// Only Admin/Faculty can access this page
AuthMiddleware::requireRole($user, ['admin', 'faculty']);

// No direct PHP data fetching needed here, all done via JS and API
// This page primarily sets up the HTML structure for the JavaScript to populate.

$userName = $_SESSION['username'] ?? 'Guest';
$userRole = $_SESSION['role'];

if (isset($_POST['logout'])) {
    $_SESSION = array();
    session_destroy();
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses & Sections</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/manage_courses.css"> <!-- New CSS for this page -->
</head>
<body>
    <header>
        <div class="header-left">
            <h1>Inventory System</h1>
        </div>
        <div class="header-right">
            <span class="welcome-message">Welcome, <?php echo htmlspecialchars($userName); ?>! (Role: <?php echo htmlspecialchars($userRole); ?>)</span>
            <div class="dropdown">
                <button id="dropdown-button" class="dropdown-toggle">Options <span class="arrow-down">&#9660;</span></button>
                <div class="dropdown-menu">
                    <form method="post">
                        <button type="submit" name="logout" class="logout-button">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <nav class="sidebar">
        <h2>Navigation</h2>
        <ul>
            <li><a href="dashboard.php" class="nav-link">Dashboard Home</a></li>
            <li><a href="createExam.php" class="nav-link">Create Exam</a></li>
            <li><a href="getExam.php" class="nav-link">Manage Exams</a></li>
            <li><a href="manageCourses.php" class="nav-link active">Manage Courses & Sections</a></li> <!-- NEW: Added link -->
            <?php if ($userRole === 'admin'): ?>
                <li><a href="createAccount.php" class="nav-link">Create Account</a></li>
            <?php endif ?>
        </ul>
    </nav>

    <main class="content">
        <h2>Course and Section Management</h2>
        <p>This page allows you to view and manage your courses, year levels, and sections based on existing student data.</p>

        <div class="course-management-container">
            <div id="course_list_container">
                <p class="loading-indicator">Loading courses and sections...</p>
                <div class="error-message hidden"></div>
                <!-- Course and section data will be dynamically loaded here by JavaScript -->
            </div>
        </div>
    </main>

    <!-- Student List Modal (Existing, but added a placeholder for its content) -->
    <div id="studentListModal" class="modal">
        <div class="modal-content large-modal">
            <span class="close-button" id="closeStudentModalBtn">&times;</span>
            <h2 id="studentModalTitle">Students</h2>
            <div class="modal-body">
                <p id="studentModalLoading" class="loading-indicator">Loading students...</p>
                <p id="studentModalError" class="error-message hidden"></p>
                <div class="table-responsive">
                    <table class="student-table">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Actions</th> <!-- NEW Column -->
                            </tr>
                        </thead>
                        <tbody id="studentListTableBody">
                            <!-- Student data will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- NEW: Edit Student Modal Structure -->
    <div id="editStudentModal" class="modal">
        <div class="modal-content">
            <span class="close-button" id="closeEditStudentModalBtn">&times;</span>
            <h2>Edit Student Details</h2>
            <form id="editStudentForm" class="edit-form">
                <input type="hidden" id="editStudentId" name="student_id">
                <input type="hidden" id="editUserId" name="user_id"> <!-- Hidden field for user_id -->

                <div class="form-group">
                    <label for="editStudentUsername">Username</label>
                    <input type="text" id="editStudentUsername" name="username" readonly disabled> <!-- Username is readonly -->
                </div>
                <div class="form-group">
                    <label for="editStudentName">Full Name</label>
                    <input type="text" id="editStudentName" name="name" required>
                </div>
                <div class="form-group">
                    <label for="editStudentEmail">Email</label>
                    <input type="email" id="editStudentEmail" name="email" required>
                </div>
                <div class="form-group">
                    <label for="editStudentCourse">Course</label>
                    <input type="text" id="editStudentCourse" name="course" required>
                </div>
                <div class="form-group">
                    <label for="editStudentYear">Year</label>
                    <input type="number" id="editStudentYear" name="year" min="1" max="5" required>
                </div>
                <div class="form-group">
                    <label for="editStudentSection">Section</label>
                    <input type="text" id="editStudentSection" name="section" required>
                </div>
                <div id="editStudentModalMessage" class="message-area hidden"></div>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>
    </div>

    <!-- NEW: Delete Student Confirmation Modal Structure -->
    <div id="deleteStudentConfirmationModal" class="modal">
        <div class="modal-content">
            <h2>Confirm Delete Student</h2>
            <p id="deleteStudentMessage">Are you sure you want to delete this student? This action cannot be undone and will permanently remove their user account and all associated student data.</p>
            <div class="modal-buttons">
                <button id="confirmDeleteStudentBtn" class="btn btn-confirm">Delete Student</button>
                <button id="cancelDeleteStudentBtn" class="btn btn-cancel">Cancel</button>
            </div>
        </div>
    </div>

    <!-- General dashboard JS for dropdown (if not already combined) -->
    <script src="../assets/js/dashboard.js"></script>
    <script src="../assets/js/manage_courses.js"></script> <!-- New JS for this page -->
</body>
</html>