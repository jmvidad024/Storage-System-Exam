<?php
// register.php (Frontend page for student registration)

// No authentication required for this page
require_once '../env_loader.php'; // For base paths, etc.
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration</title>
    <link rel="stylesheet" href="../assets/css/register.css"> <!-- Assuming a general style.css -->    
</head>
<body>
    <div class="register-container">
        <h1>Student Registration</h1>
        <form id="student_register_form">
            <div class="form-group">
                <label for="student_id">Student ID</label>
                <input type="text" id="student_id" name="student_id" placeholder="Enter your Student ID" required>
                <!-- Hidden username field, populated by JS from student_id -->
                <input type="hidden" id="username" name="username"> 
            </div>

            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <div class="form-group">
                <label for="course">Course</label>
                <select name="course" id="course" required>
                    <option value="">Select Course</option>
                    <option value="Computer Science">Computer Science</option>
                    <option value="Information Technology">Information Technology</option>
                    <option value="Education">Education</option>
                    <option value="Engineering">Engineering</option>
                </select>
                <!-- This hidden field will store the combined course:major value -->
                <input type="hidden" id="course_major_db" name="course_major_db">
            </div>

            <!-- Major dropdown (initially hidden) -->
            <div class="form-group" id="major-group" style="display: none;">
                <label for="major">Major</label>
                <select id="major" name="major">
                    <option value="">Select Major</option>
                    <!-- Populated by JavaScript -->
                </select>
            </div>

            <div class="form-group">
                <label for="year">Year</label>
                <select id="year" name="year" required>
                    <option value="">Select Year</option>
                    <option value="1">1st Year</option>
                    <option value="2">2nd Year</option>
                    <option value="3">3rd Year</option>
                    <option value="4">4th Year</option>
                </select>
            </div>
            <div class="form-group">
                <label for="section">Section</label>
                <select id="section" name="section" required>
                    <option value="">Select Section</option>
                    <option value="A">A</option>
                    <option value="B">B</option>
                    <option value="C">C</option>
                </select>
            </div>

            <button type="submit" class="btn">Register</button>
        </form>
        <div id="registration_message_area" class="message-area hidden"></div>
        <p class="login-link">Already have an account? <a href="login.php">Login here</a></p>
    </div>

    <script src="../assets/js/register.js"></script>
</body>
</html>
