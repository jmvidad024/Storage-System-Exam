<?php
session_start(); // Ensure session is started at the very beginning
ini_set('display_errors', 0); // Turn off error output to browser for production
ini_set('display_startup_errors', 0);
error_reporting(E_ALL); // Still log everything

require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/user.php';
require_once '../controllers/AuthMiddleware.php';
require_once '../models/examAttempt.php'; // Include StudentExamAttempt model

$database = new Database();
$user = new User($database);
$studentExamAttempt = new StudentExamAttempt($database);

// Check authentication and role
AuthMiddleware::authenticate($user);

// Get the user's role from the session
$userRole = $_SESSION['role'] ?? '';
$userName = $_SESSION['name'] ?? 'Guest'; // Use $_SESSION['name'] for display
$userId = $user->getId();

// Variables for student-specific data
$student_year = null;
$student_section = null;
$student_course = null;

// Variables for faculty-specific data
$faculty_assigned_course_major = null;
$total_students_in_faculty_course = 0;
$facultyCourse = null;
$facultyMajor = null;
$faculty_assigned_subject = null;
$faculty_assigned_year = null;
$faculty_assigned_section = null;


if ($userRole === 'student') {
    $studentDetails = $user->getStudentDetails($userId);
    if ($studentDetails) {
        $student_year = $studentDetails['year'];
        $student_section = $studentDetails['section'];
        $student_course = $studentDetails['course']; // This will be the combined string e.g., "Education : Science"
    }
} elseif ($userRole === 'faculty') {
    $faculty_assigned_course_major = $user->getFacultyCourseMajor($userId);
    $faculty_details = $user->getFullUserProfile($userId); // Assuming this gets subject, year, section
    $faculty_assigned_subject = $faculty_details['subject'] ?? 'N/A';
    $faculty_assigned_year = $faculty_details['year'] ?? 'N/A';
    $faculty_assigned_section = $faculty_details['section'] ?? 'N/A';

    if ($faculty_assigned_course_major) {
        $parts = explode(' : ', $faculty_assigned_course_major, 2);
        $facultyCourse = $parts[0];
        $facultyMajor = $parts[1] ?? null; // Major might not exist if course has no majors

        // Now, count students for this course/major combination
        $total_students_in_faculty_course = $user->countStudentsByCourseMajor($facultyCourse, $facultyMajor, $faculty_assigned_year, $faculty_assigned_section);
    } else {
        // Handle case where faculty user has no assigned course
        error_log("Faculty user ID {$userId} has no assigned course_major.");
        // We will display a message on the dashboard directly in the HTML
    }
}

$totalStudents = 0;
if ($userRole === 'admin') {
    $totalStudents = $user->countStudents();
}


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
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/dashboard_student_exams.css">
    <link rel="stylesheet" href="../assets/css/dashboard_faculty.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <title>Dashboard - Exam System</title>
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <a href="dashboard.php" class="logo">Exam System</a>
        </div>
        <div class="header-right">
            <span class="welcome-message">Welcome, <?php echo htmlspecialchars($userName); ?>
            <?php if($userRole === 'faculty'): ?>
                | Year-Section: <?php echo htmlspecialchars($faculty_assigned_year . "-" . $faculty_assigned_section); ?>
                <?php if ($faculty_assigned_subject && $faculty_assigned_subject !== 'N/A'): ?>
                    | Subject: <?php echo htmlspecialchars($faculty_assigned_subject); ?>
                <?php endif; ?>
            <?php elseif($userRole === 'student'): ?>
                | Student ID: <?php echo htmlspecialchars($_SESSION['username']); ?>
            <?php endif; ?>
            </span>
            <form action="" method="post" class="logout-form">
                <button type="submit" name="logout" id="logout-button" class="btn btn-logout">Logout</button>
            </form>
        </div>
    </header>

    <nav class="sidebar">
        <h2 class="sidebar-title">Navigation</h2>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php" class="nav-link active"><i class="icon-home"></i> Dashboard Home</a></li>
            <?php if ($userRole === 'admin' || $userRole === 'faculty'): ?>
                <li><a href="createExam.php" class="nav-link"><i class="icon-create-exam"></i> Create Exam</a></li>
                <li><a href="getExam.php" class="nav-link"><i class="icon-manage-exams"></i> Manage Exams</a></li>
            <?php endif; ?>
            <?php if ($userRole === 'admin'): ?>
                <li><a href="manageCourses.php" class="nav-link"><i class="icon-manage-courses"></i> Manage Courses & Sections</a></li>
                <li><a href="createAccount.php" class="nav-link"><i class="icon-create-account"></i> Create Account</a></li>
                <li><a href="pending_registration.php" class="nav-link"><i class="icon-pending-account"></i> Pending Accounts</a></li>
            <?php endif ?>
        </ul>
    </nav>

    <main class="content-area">
        <?php if ($userRole === 'admin'): ?>
            <section class="admin-panel dashboard-section">
                <h3 class="section-title">Admin Overview</h3>
                <p class="section-description">Welcome, Administrator! You have full access to all system features. Monitor the system's vital statistics.</p>
                <div class="dashboard-metrics-grid">
                    <div class="dashboard-metric-card">
                        <h4 class="metric-title">Total Registered Students</h4>
                        <p class="metric-value"><?php echo htmlspecialchars($totalStudents); ?></p>
                    </div>
                    </div>
            </section>
        <?php elseif ($userRole === 'faculty'): ?>
            <section class="faculty-panel dashboard-section">
                <h3 class="section-title">Faculty Dashboard</h3>
                <p class="section-description">Welcome, Faculty Member! Here you can manage your courses and exams, and view student progress.</p>

                <?php if ($faculty_assigned_course_major): ?>
                    <div class="dashboard-metrics-grid">
                        <div class="dashboard-metric-card">
                            <h4 class="metric-title">Assigned Course & Major</h4>
                            <p class="metric-value"><?php echo htmlspecialchars($faculty_assigned_course_major); ?></p>
                        </div>
                        <div class="dashboard-metric-card">
                            <h4 class="metric-title">Total Students in Assigned Class</h4>
                            <p class="metric-value"><?php echo htmlspecialchars($total_students_in_faculty_course); ?></p>
                        </div>
                    </div>

                    <div class="faculty-section-card">
                        <h4 class="card-title">My Exams</h4>
                        <p class="card-description">View and manage exams you have created or are assigned to.</p>
                        <div id="faculty_exam_list" class="exam-list-container">
                        </div>
                    </div>

                <?php else: ?>
                    <div class="info-message error-message">
                        Your faculty account is not yet assigned to a course, year, and section. Please contact the administrator to get assigned to a class to view relevant data.
                    </div>
                <?php endif; ?>
            </section>
        <?php elseif ($userRole === 'student'): ?>
            <section class="student-panel dashboard-section">
                <h3 class="section-title">Student Dashboard</h3>
                <p class="section-description">Access your assigned exams and track your progress.</p>

                <div class="student-info-card">
                    <h4>Your Class Information:</h4>
                    <p><strong>Year:</strong> <?php echo htmlspecialchars($student_year); ?></p>
                    <p><strong>Section:</strong> <?php echo htmlspecialchars($student_section); ?></p>
                    <p><strong>Course & Major:</strong> <?php echo htmlspecialchars($student_course); ?></p>
                </div>

                <div class="student-section-card">
                    <h4 class="card-title">Available Exams for You</h4>
                    <p class="card-description">Here are the exams currently available based on your class assignment.</p>
                    <div id="exam_list_container" class="exam-list-container"
                         data-user-id="<?php echo htmlspecialchars($userId); ?>"
                         data-student-year="<?php echo htmlspecialchars($student_year); ?>"
                         data-student-section="<?php echo htmlspecialchars($student_section); ?>"
                         data-student-course-major="<?php echo htmlspecialchars($student_course); ?>">
                        <div class="exam-loading-error">Loading exams...</div>
                        <div class="exam-grid">
                            </div>
                    </div>
                </div>
            </section>
        <?php else: ?>
            <section class="user-panel dashboard-section">
                <h3 class="section-title">User Information</h3>
                <p class="section-description">Welcome to your personal dashboard. Explore your inventory and other features.</p>
                <p>No specific role assigned or recognized. Please contact support if this is incorrect.</p>
            </section>
        <?php endif; ?>
    </main>

    <script src="../assets/js/dashboard.js"></script>
    <script src="../assets/js/dashboard_faculty.js"></script>
    <script src="../assets/js/dashboard_student.js"></script>
</body>
</html>