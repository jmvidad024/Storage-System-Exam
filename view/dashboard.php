<?php
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

if ($userRole === 'student') {
    $studentDetails = $user->getStudentDetails($userId);
    if ($studentDetails) {
        $student_year = $studentDetails['year'];
        $student_section = $studentDetails['section'];
    }
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
</head>
<body>
    <header>
        <div class="header-left">
            <h1>Inventory System</h1>
        </div>
        <div class="header-right">
            <span class="welcome-message">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</span>
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
            </div>
        <?php elseif ($userRole === 'faculty'): ?>
            <div class="faculty-panel">
                <h3>Faculty Tools</h3>
                <p>Welcome, Faculty Member! Here you can manage your courses and exams.</p>
            </div>
        <?php elseif ($userRole === 'student'): ?>
            <!-- Student-specific panel to display exams -->
            <div class="student-panel">
                <h3>Available Exams for You</h3>
                <p>Here are the exams currently available for Year <?php echo htmlspecialchars($student_year); ?> Section <?php echo htmlspecialchars($student_section); ?>.</p>
                
                <!-- Data attributes to pass student info to JS -->
                <div id="exam_list_container" 
                     data-user-id="<?php echo htmlspecialchars($userId); ?>"
                     data-student-year="<?php echo htmlspecialchars($student_year); ?>"
                     data-student-section="<?php echo htmlspecialchars($student_section); ?>">
                    <div class="exam-loading-error">Loading exams...</div>
                    <div class="exam-grid">
                        <!-- Exams will be loaded here by JavaScript -->
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

    <!-- Original dashboard.js for general dropdown and other common JS -->
    <script src="../assets/js/dashboard.js"></script>
    <!-- NEW: Link to separate JavaScript for student exam loading logic -->
    <script src="../assets/js/dashboard_student.js"></script>
</body>
</html>
