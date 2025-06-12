<?php
ini_set('display_errors', 0); // Turn off error output to browser
ini_set('display_startup_errors', 0);
error_reporting(E_ALL); // Still log everything

// Assuming env_loader, database, user, and AuthMiddleware are in place
require_once '../env_loader.php';
require_once '../assets/config/database.php';
require_once '../models/user.php';
require_once '../controllers/AuthMiddleware.php';

$database = new Database();
$user = new User($database);

// Only admin and faculty can manage/delete exams
AuthMiddleware::authenticate($user);
AuthMiddleware::requireRole($user, ['admin', 'faculty']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/get_exam.css">
    <title>Manage Exams</title>
    <style>
        /* Add these styles to your get_exam.css or here temporarily */
        .exam-actions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: right;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }
        .btn-edit, .btn-delete {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .btn-edit {
            background-color: #007bff; /* Blue */
            color: white;
        }
        .btn-edit:hover {
            background-color: #0056b3;
            transform: translateY(-1px);
        }
        .btn-delete {
            background-color: #dc3545; /* Red */
            color: white;
        }
        .btn-delete:hover {
            background-color: #c82333;
            transform: translateY(-1px);
        }

        /* Modal Styles (can be reused from createExam.php if you have a shared modal.css) */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 2000; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.6); /* Black w/ opacity */
            display: flex;
            justify-content: center;
            align-items: center;
            opacity: 0; /* Start hidden for fade-in effect */
            visibility: hidden; /* Hide completely */
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .modal.show {
            opacity: 1;
            visibility: visible;
            display: flex; /* Now it's flex, so justify/align center applies */
        }
        .modal-content {
            background-color: #ffffff;
            margin: auto;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            width: 90%;
            max-width: 500px;
            text-align: center;
            animation: fadeIn 0.3s ease-out;
        }
        .modal-content h2 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.8rem;
        }
        .modal-content p {
            margin-bottom: 25px;
            font-size: 1.1rem;
            color: #555;
        }
        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        .modal-buttons .btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .modal-buttons .btn-confirm {
            background-color: #28a745; /* Green */
            color: white;
        }
        .modal-buttons .btn-confirm:hover {
            background-color: #218838;
        }
        .modal-buttons .btn-cancel {
            background-color: #6c757d; /* Grey */
            color: white;
        }
        .modal-buttons .btn-cancel:hover {
            background-color: #5a6268;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        /* Message area for success/error (re-use from create_exam or get_exam CSS) */
        .message-area {
            margin-top: 20px;
            padding: 15px;
            border-radius: 8px;
            font-size: 1.05rem;
            font-weight: bold;
            text-align: center;
            display: block; /* Show messages dynamically */
        }
        .message-area.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message-area.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .message-area.hidden { /* To explicitly hide message */
            display: none;
        }
    </style>
</head>
<body>
    <header class="page-header">
        <a href="dashboard.php" class="back-button">&larr; Back to Dashboard</a>
        <h1>Manage Exams</h1>
    </header>

    <div class="container">
        <div class="search-section">
            <h2>Find Exam by Code</h2>
            <form action="" method="GET" id="get_exam" class="exam-search-form">
                <input type="text" name="code" id="exam_code_input" placeholder="Enter Exam Code (e.g., CS101-Midterm)" required>
                <button type="submit" class="btn btn-search">Get Exam</button>
            </form>
            <div id="loading_indicator" class="loading-indicator">Loading...</div>
        </div>

        <div id="exam_result" class="exam-result-section">
            <p class="placeholder-text">Enter an exam code above and click "Get Exam" to view details.</p>
        </div>

        <!-- NEW: Confirmation Modal for Deletion -->
        <div id="deleteConfirmationModal" class="modal">
            <div class="modal-content">
                <h2>Confirm Deletion</h2>
                <p>Are you sure you want to delete this exam and all its associated questions and choices? This action cannot be undone.</p>
                <div class="modal-buttons">
                    <button id="confirmDeleteBtn" class="btn btn-confirm">Delete Exam</button>
                    <button id="cancelDeleteBtn" class="btn btn-cancel">Cancel</button>
                </div>
            </div>
        </div>
        
        <div id="action_message_area" class="message-area hidden"></div>

    </div>

    <script src="../assets/js/get_exam.js"></script>
</body>
</html>
