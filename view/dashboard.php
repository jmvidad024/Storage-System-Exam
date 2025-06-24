<?php
session_start(); // Ensure session is started at the very beginning
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
$userName = $_SESSION['username'] ?? 'Guest';
$userId = $user->getId();

// Variables for student-specific data
$student_year = null;
$student_section = null;
$student_course = null;

// Variables for faculty-specific data
$faculty_assigned_course_major = null;
$total_students_in_faculty_course = 0;

if ($userRole === 'student') {
    $studentDetails = $user->getStudentDetails($userId);
    if ($studentDetails) {
        $student_year = $studentDetails['year'];
        $student_section = $studentDetails['section'];
        $student_course = $studentDetails['course']; // This will be the combined string e.g., "Education : Science"
    }
} elseif ($userRole === 'faculty') {
    $faculty_assigned_course_major = $user->getFacultyCourseMajor($userId);
    if ($faculty_assigned_course_major) {
        $parts = explode(' : ', $faculty_assigned_course_major, 2);
        $facultyCourse = $parts[0];
        $facultyMajor = $parts[1] ?? null; // Major might not exist if course has no majors

        // Now, count students for this course/major combination
        $total_students_in_faculty_course = $user->countStudentsByCourseMajor($facultyCourse, $facultyMajor);
    } else {
        // Handle case where faculty user has no assigned course
        error_log("Faculty user ID {$userId} has no assigned course_major.");
        $_SESSION['error_message'] = "Your faculty account is not assigned to a course. Please contact the administrator.";
        // Optionally redirect or show a specific message on the dashboard.
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
    <title>Dashboard</title>
    <style>
        /* Add some basic styling for the new faculty metric box */
        .dashboard-metric {
            background-color: #f0f8ff; /* Light blue background */
            border: 1px solid #cceeff; /* Light blue border */
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .dashboard-metric h4 {
            color: #333;
            margin-top: 0;
            margin-bottom: 10px;
        }
        .dashboard-metric .metric-value {
            font-size: 2em;
            font-weight: bold;
            color: #007bff; /* Primary blue color */
            text-align: center;
        }
    </style>
</head>
<body>
    <header>
        <div class="header-left">
            <h1>Inventory System</h1>
        </div>
        <div class="header-right">
            <span class="welcome-message">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</span>
            <form action="" method="post">
                <button type="submit" name="logout" id="logout-button">Logout</button>
            </form>
        </div>
    </header>

    <nav class="sidebar">
        <h2>Navigation</h2>
        <ul>
            <li><a href="#" class="nav-link active">Dashboard Home</a></li>
            <?php if ($userRole === 'admin' || $userRole === 'faculty'): ?>
                <li><a href="createExam.php" class="nav-link">Create Exam</a></li>
                <li><a href="getExam.php" class="nav-link">Manage Exams</a></li>
                <li><a href="manageCourses.php" class="nav-link">Manage Courses & Sections</a></li>
            <?php endif; ?>
            <?php if ($userRole === 'admin'): ?>
                <li><a href="createAccount.php" class="nav-link">Create Account</a></li>
            <?php endif ?>
        </ul>
    </nav>

    <main class="content">
        <h2>Your Dashboard</h2>
        <p>This is the main content area of your dashboard. You can add various widgets, summaries, or quick actions here.</p>

        <?php if ($userRole === 'admin'): ?>
        <div class="admin-panel">
            <h3>Admin Tools</h3>
            <p>Welcome, Administrator! You have full access to all system features.</p>
            <div class="dashboard-metric">
                <h4>Total Registered Students:</h4>
                <p class="metric-value"><?php echo htmlspecialchars($totalStudents); ?></p>
            </div>
        </div>
        <?php elseif ($userRole === 'faculty'): ?>
            <div class="faculty-panel">
                <h3>Faculty Tools</h3>
                <p>Welcome, Faculty Member! Here you can manage your courses and exams.</p>
                <?php if ($faculty_assigned_course_major): ?>
                    <div class="dashboard-metric">
                        <h4>Students in Your Assigned Course:</h4>
                        <p class="metric-value">
                            <?php echo htmlspecialchars($total_students_in_faculty_course); ?>
                        </p>
                        <p class="metric-label">
                            for <?php echo htmlspecialchars($faculty_assigned_course_major); ?>
                        </p>
                    </div>
                <?php else: ?>
                    <p class="info-message">
                        Your faculty account is not yet assigned to a course. Please contact the administrator.
                    </p>
                <?php endif; ?>
            </div>
        <?php elseif ($userRole === 'student'): ?>
            <div class="student-panel">
                <h3>Available Exams for You</h3>
                <p>Here are the exams currently available for:
                    <br>Year <?php echo htmlspecialchars($student_year); ?> Section <?php echo htmlspecialchars($student_section); ?>-<?php echo htmlspecialchars($student_course); ?>.</p>

                <div id="exam_list_container"
                     data-user-id="<?php echo htmlspecialchars($userId); ?>"
                     data-student-year="<?php echo htmlspecialchars($student_year); ?>"
                     data-student-section="<?php echo htmlspecialchars($student_section); ?>"
                     data-student-course-major="<?php echo htmlspecialchars($student_course); ?>"> <div class="exam-loading-error">Loading exams...</div>
                    <div class="exam-grid">
                        </div>
                </div>
            </div>
        <?php else: ?>
            <div class="user-panel">
                <h3>User Information</h3>
                <p>Welcome to your personal dashboard. Explore your inventory and other features.</p>
            </div>
        <?php endif; ?>
    </main>

    <script src="../assets/js/dashboard.js"></script>
    <script src="../assets/js/dashboard_student.js"></script>
</body>
</html>