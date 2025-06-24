<?php
// models/PendingUser.php

require_once 'student.php'; 
require_once 'user.php'; 

class PendingUser {
    private $conn;
    private $table_name = "pending_users";
    private $db_instance;

    public function __construct(Database $db) {
        $this->db_instance = $db;
        $this->conn = $db->getConnection();
    }

    /**
     * Creates a new pending user entry in the database.
     * For students, the username will be their student_id.
     *
     * @param string $username The user's chosen username (student_id for students).
     * @param string $name The user's full name.
     * @param string $hashed_password The hashed password for the user.
     * @param string $email The user's email address.
     * @param string $role The role (e.g., 'student').
     * @param string|null $course The combined course string (e.g., "Education : Science").
     * @param int|null $year The student's year (only if role is student).
     * @param string|null $section The student's section (only if role is student).
     * @return int|false The ID of the newly created pending user, or false on failure.
     */
    public function createPendingUser($username, $name, $hashed_password, $email, $role, $course = null, $year = null, $section = null) {
        $query = "INSERT INTO " . $this->table_name . " (username, name, password_hash, email, role, course, year, section) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            error_log("PendingUser::createPendingUser - Prepare failed: " . $this->conn->error);
            return false;
        }

        // Bind parameters: ssssss (username, name, password, email, role, course)
        // Add 'is' for year and section if they are integers and strings respectively
        $stmt->bind_param("ssssssis", $username, $name, $hashed_password, $email, $role, $course, $year, $section);

        if ($stmt->execute()) {
            $new_id = $this->conn->insert_id;
            $stmt->close();
            return $new_id;
        } else {
            error_log("PendingUser::createPendingUser - Execute failed: " . $stmt->error);
            $stmt->close();
            return false;
        }
    }

    /**
     * Retrieves a pending user by their ID.
     *
     * @param int $id The ID of the pending user.
     * @return array|false The pending user's data as an associative array, or false if not found.
     */
    public function getPendingUserById(int $id) {
        $query = "SELECT id, username, name, email, password_hash, role, course, year, section, created_at FROM " . $this->table_name . " WHERE id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("PendingUser::getPendingUserById - Prepare failed: " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        $stmt->close();
        return $user_data;
    }

    /**
     * Finds a pending user by username.
     * @param string $username
     * @return array|false User data if found, false otherwise.
     */
    public function findByUsername(string $username) {
        $query = "SELECT id, username FROM " . $this->table_name . " WHERE username = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("PendingUser::findByUsername - Prepare failed: " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        $stmt->close();
        return $user_data;
    }

    /**
     * Finds a pending user by email.
     * @param string $email
     * @return array|false User data if found, false otherwise.
     */
    public function findByEmail(string $email) {
        $query = "SELECT id, email FROM " . $this->table_name . " WHERE email = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("PendingUser::findByEmail - Prepare failed: " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        $stmt->close();
        return $user_data;
    }

    /**
     * Retrieves all pending user applications.
     *
     * @return array An array of all pending user records.
     */
    public function getAllPendingUsers(): array {
        $query = "SELECT id, username, name, email, role, course, year, section, created_at FROM " . $this->table_name . " ORDER BY created_at ASC";
        $result = $this->conn->query($query);
        if ($result) {
            return $result->fetch_all(MYSQLI_ASSOC);
        } else {
            error_log("PendingUser::getAllPendingUsers - Query failed: " . $this->conn->error);
            return [];
        }
    }

    /**
     * Deletes a pending user record.
     *
     * @param int $id The ID of the pending user to delete.
     * @return bool True on success, false on failure.
     */
    public function deletePendingUser(int $id): bool {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("PendingUser::deletePendingUser - Prepare failed: " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            error_log("PendingUser::deletePendingUser - Execute failed: " . $stmt->error);
            $stmt->close();
            return false;
        }
    }

    /**
     * Approves a pending user by migrating their data to the 'users' table
     * and then deleting them from 'pending_users'.
     * Generates a verification token for the new user.
     *
     * @param int $pending_user_id The ID of the pending user to approve.
     * @param User $userModel An instance of the User model to create the actual user.
     * @return string|false The generated verification token on success, false on failure.
     */
    public function approvePendingUser(int $pending_user_id, User $userModel): string|false {
        $this->conn->begin_transaction();
        try {
            // 1. Get pending user details
            $pending_user_data = $this->getPendingUserById($pending_user_id);
            if (!$pending_user_data) {
                throw new Exception("Pending user not found for approval.");
            }

            // Generate a unique verification token
            $verification_token = bin2hex(random_bytes(32));

            // 2. Create the actual user in the 'users' table
            // The 'username' for students is their student_id
            $new_user_id = $userModel->create(
                $pending_user_data['username'], // This is student_id for students
                $pending_user_data['name'],
                $pending_user_data['password_hash'],
                $pending_user_data['email'],
                $pending_user_data['role'],
                $verification_token, // Pass the token
                0 // is_verified = 0 (unverified)
            );

            if (!$new_user_id) {
                throw new Exception("Failed to create user in 'users' table.");
            }

            // 3. If role is student, create student_details entry
            if ($pending_user_data['role'] === 'student') {
                $studentModel = new Student($this->db_instance); 
                
                // Reuse User model's splitCourseMajor method to handle combined course string
                $userUtil = new User($this->db_instance); // Temporary User instance for utility method
                $course_major_parts = $userUtil->splitCourseMajor($pending_user_data['course']);
                $course_name = $course_major_parts['course'];
                $major_name = $course_major_parts['major'];

                // Pass all relevant student details from pending_users to Student::create
                if (!$studentModel->create(
                    $new_user_id,
                    $pending_user_data['username'], // student_id
                    $course_name,
                    $pending_user_data['year'],
                    $pending_user_data['section'],
                    $major_name // Major can be null if not provided
                )) { 
                    error_log("Warning: Failed to create student details for user_id $new_user_id (student_id: {$pending_user_data['username']}). This may be expected if student details are optional or handled elsewhere.");
                }
            }
            // Add similar logic for 'faculty' role if you have specific faculty_details to migrate

            // 4. Delete from pending_users table
            if (!$this->deletePendingUser($pending_user_id)) {
                throw new Exception("Failed to delete pending user record after approval.");
            }

            $this->conn->commit();
            return $verification_token; // Return the token so it can be used to send the email
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("PendingUser::approvePendingUser - Approval failed: " . $e->getMessage());
            return false;
        }
    }
}
