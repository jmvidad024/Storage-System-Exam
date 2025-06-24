<?php
session_start();
ini_set('display_errors', 0); // Turn off error output to browser
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/user.php';
require_once '../controllers/AuthMiddleware.php';
require_once '../models/student.php';
require_once '../utils/Mailer.php'; // Include the Mailer utility

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Initialize models
$user = new User($database);
$studentModel = new Student($database);
$mailer = new Mailer(); // Initialize Mailer

// Define hardcoded courses and majors (PHP mirror of JS data)
$coursesAndMajors = [
    'Education' => ['Science', 'Math', 'English', 'History'],
    'Engineering' => ['Civil', 'Electrical', 'Mechanical', 'Computer'],
    'Computer Science' => []
];

// Authenticate and authorize (only Admin can create accounts)
AuthMiddleware::authenticate($user);
AuthMiddleware::requireRole($user, ['admin']);

$message = '';
$registration_successful = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize common input fields
    $username_input = htmlspecialchars(trim($_POST['username'] ?? '')); // This is the form's username field
    $name = htmlspecialchars(trim($_POST['name'] ?? ''));
    $password = $_POST['password'] ?? '';
    $email = htmlspecialchars(trim($_POST['email'] ?? ''));
    $role = htmlspecialchars(trim($_POST['role'] ?? ''));

    // Role-specific inputs
    $student_id = htmlspecialchars(trim($_POST['student_id'] ?? '')); // Student ID will become username for students
    $course_major = htmlspecialchars(trim($_POST['course_major_db'] ?? '')); // Combined course & major
    $year = htmlspecialchars(trim($_POST['year'] ?? ''));
    $section = htmlspecialchars(trim($_POST['section'] ?? ''));
    $faculty_course_data = htmlspecialchars(trim($_POST['faculty_course_data'] ?? '')); // For faculty

    $errors = [];
    $username_for_db = $username_input; // Default username for DB is from the form's username input

    // Adjust username and validate role-specific fields
    if ($role === 'student') {
        $username_for_db = $student_id; // Student ID becomes the username for students
        if (empty($student_id)) $errors[] = "Student ID is required for students.";
        if (empty($course_major)) $errors[] = "Course is required for students.";
        if (empty($year)) $errors[] = "Year is required for students.";
        if (!is_numeric($year) || $year < 1 || $year > 4) $errors[] = "Invalid Year (1-4) selected for students.";
        if (empty($section)) $errors[] = "Section is required for students.";

        // Parse student course and major from the combined string for validation
        $course_parts = explode(' : ', $course_major, 2);
        $student_course_name = $course_parts[0];
        $student_major = $course_parts[1] ?? null;

        if (!array_key_exists($student_course_name, $coursesAndMajors)) {
            $errors[] = "Invalid student course selected.";
        } elseif ($student_major && !in_array($student_major, $coursesAndMajors[$student_course_name])) {
            $errors[] = "Invalid major for the selected student course.";
        }

    } elseif ($role === 'faculty') {
        if (empty($faculty_course_data)) $errors[] = "Course is required for faculty.";

        $faculty_parts = explode(' : ', $faculty_course_data, 2);
        $faculty_course_name = $faculty_parts[0];
        $faculty_major = $faculty_parts[1] ?? null;

        if (!empty($faculty_course_name) && !array_key_exists($faculty_course_name, $coursesAndMajors)) {
            $errors[] = "Invalid faculty course selected from the list.";
        } elseif ($faculty_major && !in_array($faculty_major, $coursesAndMajors[$faculty_course_name])) {
            $errors[] = "Invalid major selected for the chosen faculty course.";
        }
    }

    // --- Server-side Validation (Common Fields) ---
    if (empty($username_for_db)) $errors[] = "Username is required."; // Use the actual username for DB
    if (empty($name)) $errors[] = "Full Name is required.";
    if (empty($password)) $errors[] = "Password is required.";
    if (empty($email)) $errors[] = "Email is required.";
    if (empty($role)) $errors[] = "Role is required.";

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters long.";
    if (!in_array($role, ['admin', 'faculty', 'student'])) $errors[] = "Invalid role selected.";

    // Check for existing username/email/student_id BEFORE attempting database operations
    if (empty($errors)) {
        if ($user->findByUsername($username_for_db)) {
            $errors[] = "Username/Student ID '{$username_for_db}' already exists. Please choose another.";
        }
        if ($user->findByEmail($email)) {
            $errors[] = "Email '{$email}' already registered. Please use a different email.";
        }
        if ($role === 'student' && $studentModel->findByStudentId($student_id)) {
            $errors[] = "Student ID '{$student_id}' is already registered. Please verify or use a different ID.";
        }
    }

    // If there are validation errors, display them. Otherwise, proceed with registration.
    if (!empty($errors)) {
        $message = '<p class="error">' . implode('<br>', $errors) . '</p>';
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $verification_token = bin2hex(random_bytes(32)); // Generate token for email verification
        $is_verified_status = 0; // Default to unverified

        // For Admin/Faculty roles created by Admin, you might want them to be auto-verified
        // Uncomment the following lines if Admin/Faculty accounts should be active immediately.
        /*
        if ($role === 'admin' || $role === 'faculty') {
            $is_verified_status = 1;
            $verification_token = null; // No token needed if auto-verified
        }
        */

        // --- Start Database Transaction ---
        $db->begin_transaction();

        try {
            // Create user in the 'users' table, passing the verification token and initial verified status
            $new_user_id = $user->create(
                $username_for_db, // Use the appropriate username (student_id or direct input)
                $name,
                $hashed_password,
                $email,
                $role,
                $verification_token,
                $is_verified_status
            );

            if (!$new_user_id) {
                throw new Exception('Failed to create user account.');
            }

            // If the role is student, create a record in the 'students' table as well
            if ($role === 'student') {
                $course_parts = explode(' : ', $course_major, 2);
                $student_course_name = $course_parts[0];
                $student_major = $course_parts[1] ?? null;

                if (!$studentModel->create($new_user_id, $student_id, $student_course_name, $year, $section, $student_major)) {
                    throw new Exception('Failed to create student profile.');
                }
            }
            // If the role is faculty, create a record in the 'faculty_details' table
            elseif ($role === 'faculty') {
                if (!$user->createFacultyDetails($new_user_id, $faculty_course_data)) {
                    throw new Exception('Failed to create faculty profile.');
                }
            }

            // Attempt to send verification email (only if account is not auto-verified)
            if ($is_verified_status === 0) {
                if ($mailer->sendVerificationEmail($email, $name, $verification_token, $username_for_db)) {
                    $message = '<p class="success">Account created successfully! A verification email has been sent to ' . htmlspecialchars($email) . '. The user must verify their email before logging in.</p>';
                    $registration_successful = true;
                } else {
                    // Log the email sending failure but still consider the account created
                    error_log("Failed to send verification email for user ID: {$new_user_id}. Account created, but verification email might not have been received.");
                    $message = '<p class="warning">Account created successfully, but failed to send verification email to ' . htmlspecialchars($email) . '. Please inform the user to contact support for verification.</p>';
                    $registration_successful = true; // Still true, just with a warning
                }
            } else {
                 // Account was auto-verified
                 $message = '<p class="success">Account created and automatically verified successfully! User can now log in.</p>';
                 $registration_successful = true;
            }

            $db->commit();
            $_POST = []; // Clear form data on success for fresh form

        } catch (Exception $e) {
            $db->rollback();
            error_log("Account creation failed: " . $e->getMessage());
            $message = '<p class="error">Error creating account: ' . $e->getMessage() . '</p>';
        }
    }
}

// Close DB connection at the end of script execution
if ($db instanceof mysqli) {
    $db->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Account</title>
    <link rel="stylesheet" href="../assets/css/create_account.css">
    <style>
        /* Add some basic styles for the warning message */
        .message-area.warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
    </style>
</head>
<body>
    <header class="page-header">
        <a href="dashboard.php" class="back-button">&larr; Back to Dashboard</a>
        <h1>Create New Account</h1>
    </header>

    <div class="container">
        <div class="registration-form-section">
            <?php if (!empty($message)): ?>
                <div class="message-area <?php echo $registration_successful ? ($mailer->sendVerificationEmail($email, $name, $verification_token, $username_for_db) ? 'success' : 'warning') : 'error'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="register-form">
                <div class="form-group" id="username_group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Choose a unique username" required 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" placeholder="Your full name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="your.email@example.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Minimum 6 characters" required>
                </div>

                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" required>
                        <option value="">Select Role</option>
                        <option value="admin" <?php echo (($_POST['role'] ?? '') === 'admin') ? 'selected' : ''; ?>>Admin</option>
                        <option value="faculty" <?php echo (($_POST['role'] ?? '') === 'faculty') ? 'selected' : ''; ?>>Faculty</option>
                        <option value="student" <?php echo (($_POST['role'] ?? '') === 'student') ? 'selected' : ''; ?>>Student</option>
                    </select>
                </div>

                <div id="faculty_fields" class="faculty-fields-group" style="display: none;">
                    <h3>Faculty Details</h3>
                    <div class="form-group">
                        <label for="faculty_course">Course</label>
                        <select id="faculty_course" name="faculty_course_select_display">
                            <option value="">Select Course</option>
                            <?php foreach ($coursesAndMajors as $courseName => $majors): ?>
                                <option value="<?= htmlspecialchars($courseName) ?>"
                                    <?php
                                    $current_faculty_data = $_POST['faculty_course_data'] ?? '';
                                    $selected_faculty_course_only = explode(' : ', $current_faculty_data, 2)[0];
                                    if ($selected_faculty_course_only === $courseName) {
                                        echo 'selected';
                                    }
                                    ?>>
                                    <?= htmlspecialchars($courseName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" id="faculty_major_group" style="display: none;">
                        <label for="faculty_major">Major</label>
                        <select id="faculty_major" name="faculty_major_select_display">
                            <option value="">Select Major</option>
                        </select>
                    </div>

                    <input type="hidden" id="faculty_course_data" name="faculty_course_data"
                           value="<?php echo htmlspecialchars($_POST['faculty_course_data'] ?? ''); ?>">
                </div>

                <div id="student_fields" class="student-fields-group" style="display: none;">
                    <h3>Student Details</h3>
                    <div class="form-group">
                        <label for="student_id">Student ID</label>
                        <input type="text" id="student_id" name="student_id" placeholder="Your unique student ID" value="<?php echo htmlspecialchars($_POST['student_id'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="course">Course</label>
                        <select id="course" name="course">
                            <option value="">Select Course</option>
                            <?php foreach (array_keys($coursesAndMajors) as $courseName): ?>
                                <option value="<?php echo htmlspecialchars($courseName); ?>"
                                    <?php
                                    $current_student_data = $_POST['course_major_db'] ?? '';
                                    $selected_student_course_only = explode(' : ', $current_student_data, 2)[0];
                                    if ($selected_student_course_only === $courseName) {
                                        echo 'selected';
                                    }
                                    ?>>
                                    <?php echo htmlspecialchars($courseName); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" id="major-group" style="display: none;">
                        <label for="major">Major</label>
                        <select id="major" name="major">
                            <option value="">Select Major</option>
                        </select>
                    </div>

                    <input type="hidden" id="course_major_db" name="course_major_db"
                           value="<?php echo htmlspecialchars($_POST['course_major_db'] ?? ''); ?>">
                    <div class="form-group">
                        <label for="year">Year</label>
                        <select id="year" name="year">
                            <option value="">Select Year</option>
                            <option value="1" <?php echo (($_POST['year'] ?? '') == '1') ? 'selected' : ''; ?>>1st Year</option>
                            <option value="2" <?php echo (($_POST['year'] ?? '') == '2') ? 'selected' : ''; ?>>2nd Year</option>
                            <option value="3" <?php echo (($_POST['year'] ?? '') == '3') ? 'selected' : ''; ?>>3rd Year</option>
                            <option value="4" <?php echo (($_POST['year'] ?? '') == '4') ? 'selected' : ''; ?>>4th Year</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="section">Section</label>
                        <select id="section" name="section">
                            <option value="">Select Section</option>
                            <option value="A" <?php echo (($_POST['section'] ?? '') == 'A') ? 'selected' : ''; ?>>A</option>
                            <option value="B" <?php echo (($_POST['section'] ?? '') == 'B') ? 'selected' : ''; ?>>B</option>
                            <option value="C" <?php echo (($_POST['section'] ?? '') == 'C') ? 'selected' : ''; ?>>C</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-register">Register Account</button>
            </form>
        </div>
    </div>

    <script>
        // Pass the PHP-defined courses and majors data to JavaScript
        const majorsByCourseData = <?php echo json_encode($coursesAndMajors); ?>;
    </script>
    <script src="../assets/js/create_account.js"></script>
</body>
</html>
