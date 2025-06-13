<?php
require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/user.php';
require_once '../controllers/AuthMiddleware.php';

$database = new Database();
$user = new User($database);

// Check authentication and role
AuthMiddleware::authenticate($user);
AuthMiddleware::requireRole($user, ['admin', 'faculty']);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/create_exam.css">
    <title>Create Exam</title>
</head>
<body>
    
<header class="page-header">
        <a href="dashboard.php" class="back-button">&larr; Back to Dashboard</a>
        <h1>Create New Exam</h1>
</header>

<div class="form_layout">
<form action="" method="POST" id="question_form">
        <div class="title_box">
        <input type="text" name="title" id="title" placeholder="Enter Title">
        <input type="text" name="instruction" id="instruction" placeholder="Enter Instruction">

        <select name="year" id="year">
            <option value="">Select Year</option>
            <option value="1">1</option>
            <option value="2">2</option>
            <option value="3">3</option>
            <option value="4">4</option>
        </select>

        <select name="section" id="section">
            <option value="">Select Section</option>
            <option value="A">A</option>
            <option value="B">B</option>
            <option value="C">C</option>
        </select>

        <input type="text" name="code" id="code" placeholder="Enter Exam Code">
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

<script src="../assets/js/create_exam.js"></script>
</body>
</html>