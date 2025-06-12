<?php
// Include necessary files
require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/user.php';
require_once '../controllers/AuthMiddleware.php';
require_once '../models/student.php'; // NEW: Include the Student model

// Initialize database, user model, and student model
$database = new Database();
$user = new User($database);
$studentModel = new Student($database); // NEW: Initialize Student model

AuthMiddleware::authenticate($user);
AuthMiddleware::requireRole($user, ['admin']);

$message = ''; // To store success or error messages

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize common input fields
    $username = htmlspecialchars(trim($_POST['username'] ?? ''));
    $name = htmlspecialchars(trim($_POST['name'] ?? ''));
    $password = $_POST['password'] ?? '';
    $email = htmlspecialchars(trim($_POST['email'] ?? ''));
    $role = htmlspecialchars(trim($_POST['role'] ?? ''));

    // Collect student-specific inputs if role is student
    $student_id = '';
    $course = '';
    $year = '';
    $section = '';

    if ($role === 'student') {
        $student_id = htmlspecialchars(trim($_POST['student_id'] ?? ''));
        $course = htmlspecialchars(trim($_POST['course'] ?? ''));
        $year = htmlspecialchars(trim($_POST['year'] ?? ''));
        $section = htmlspecialchars(trim($_POST['section'] ?? ''));
    }

    // --- Server-side Validation ---
    $errors = [];

    if (empty($username)) $errors[] = "Username is required.";
    if (empty($name)) $errors[] = "Full Name is required.";
    if (empty($password)) $errors[] = "Password is required.";
    if (empty($email)) $errors[] = "Email is required.";
    if (empty($role)) $errors[] = "Role is required.";

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters long.";
    if (!in_array($role, ['admin', 'faculty', 'student'])) $errors[] = "Invalid role selected.";

    if ($role === 'student') {
        if (empty($student_id)) $errors[] = "Student ID is required for students.";
        if (empty($course)) $errors[] = "Course is required for students.";
        if (empty($year)) $errors[] = "Year is required for students.";
        if (!is_numeric($year) || $year < 1 || $year > 4) $errors[] = "Invalid Year selected for students.";
        if (empty($section)) $errors[] = "Section is required for students.";
    }

    if (empty($errors)) {
        // Check if username or email already exists in users table
        if ($user->findByUsername($username)) {
            $errors[] = "Username already taken. Please choose another.";
        }
        if ($user->findByEmail($email)) {
            $errors[] = "Email already registered. Please use a different email or log in.";
        }
        // If role is student, check if student_id already exists in students table
        // You'll need a findByStudentId method in Student.php if you want this check
        /*
        if ($role === 'student' && $studentModel->findByStudentId($student_id)) {
            $errors[] = "Student ID already registered. Please verify or use a different ID.";
        }
        */
    }

    if (!empty($errors)) {
        $message = '<p class="error">' . implode('<br>', $errors) . '</p>';
    } else {
        // All validation passed, attempt to create user
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Create user in the 'users' table
        $new_user_id = $user->create($username, $name, $hashed_password, $email, $role);

        if ($new_user_id) {
            $registration_successful = true;

            // If the role is student, create a record in the 'students' table as well
            if ($role === 'student') {
                if (!$studentModel->create($new_user_id, $student_id, $course, $year, $section)) {
                    // If student record creation fails, log error and potentially revert user creation
                    // For now, we'll just show a message, but in a real app, you might want to rollback or handle this more robustly.
                    $registration_successful = false;
                    $message = '<p class="error">Account created, but failed to create student profile. Please contact support.</p>';
                }
            }

            if ($registration_successful) {
                $message = '<p class="success">Account created successfully! You can now <a href="login.php">log in</a>.</p>';
                // Clear form data on success (optional, uncomment if desired)
                $_POST = [];
            }
        } else {
            $message = '<p class="error">Error creating account. Please try again later.</p>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Account</title>
    <link rel="stylesheet" href="../assets/css/create_account.css">
</head>
<body>
    <header class="page-header">
        <a href="login.php" class="back-button">&larr; Back to Dashboard</a>
        <h1>Create New Account</h1>
    </header>

    <div class="container">
        <div class="registration-form-section">
            <?php if (!empty($message)): ?>
                <div class="message-area">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="register-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Choose a unique username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
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

                <!-- NEW: Student-specific fields, initially hidden -->
                <div id="student_fields" class="student-fields-group">
                    <h3>Student Details</h3>
                    <div class="form-group">
                        <label for="student_id">Student ID</label>
                        <input type="text" id="student_id" name="student_id" placeholder="Your unique student ID" value="<?php echo htmlspecialchars($_POST['student_id'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="course">Course</label>
                        <input type="text" id="course" name="course" placeholder="e.g., BS Computer Science" value="<?php echo htmlspecialchars($_POST['course'] ?? ''); ?>">
                    </div>
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
                        <input type="text" id="section" name="section" placeholder="e.g., A, B, C" value="<?php echo htmlspecialchars($_POST['section'] ?? ''); ?>">
                    </div>
                </div>

                <button type="submit" class="btn btn-register">Register Account</button>
            </form>
        </div>
    </div>

    <script src="/Storage-System/assets/js/create_account.js"></script>
</body>
</html>