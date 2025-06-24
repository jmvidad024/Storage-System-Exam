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
    <link rel="stylesheet" href="../assets/css/style.css"> <!-- Assuming a general style.css -->
    <link rel="stylesheet" href="../assets/css/create_exam.css"> <!-- Reusing some form styles -->
    <style>
        /* Basic styling for the registration form */
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f7f6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }
        .register-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            text-align: center;
        }
        .register-container h1 {
            color: #333;
            margin-bottom: 25px;
            font-size: 2em;
        }
        .register-container .form-group {
            margin-bottom: 15px;
            text-align: left;
        }
        .register-container label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: bold;
        }
        .register-container input[type="text"],
        .register-container input[type="email"],
        .register-container input[type="password"],
        .register-container select {
            width: calc(100% - 20px); /* Adjust for padding */
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 1em;
        }
        .register-container .btn {
            background-color: #007bff;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.1em;
            width: 100%;
            margin-top: 20px;
            transition: background-color 0.3s ease;
        }
        .register-container .btn:hover {
            background-color: #0056b3;
        }
        .register-container .message-area {
            margin-top: 20px;
            padding: 10px;
            border-radius: 5px;
            font-weight: bold;
        }
        .register-container .message-area.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .register-container .message-area.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .register-container .login-link {
            margin-top: 20px;
            font-size: 0.9em;
            color: #555;
        }
        .register-container .login-link a {
            color: #007bff;
            text-decoration: none;
            font-weight: bold;
        }
        .register-container .login-link a:hover {
            text-decoration: underline;
        }
        /* Adjustments for major dropdown visibility */
        #major-group {
            margin-top: 15px;
        }
    </style>
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
