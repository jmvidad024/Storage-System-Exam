<?php

require_once 'student.php'; 

class User {
    private $table_name = "users";
    private $conn;
    private $db;

    public $id;
    private $username;
    private $name; 
    public $email;
    public $role;
    private $password_hash;
    private $isAuthenticated = false;
    private $isVerified = false; // New property

    public function __construct(Database $database) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $this->db = $database;
        $this->conn = $database->getConnection();
        $this->loadFromSession();
    }

    public function login($username, $password) {
        $stmt = $this->conn->prepare(
            "SELECT id, username, name, email, role, password_hash, is_verified FROM users WHERE username = ?"
        );
        
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $userData = $result->fetch_assoc();
        $stmt->close();

        if ($userData && password_verify($password, $userData['password_hash'])) {
            // Check if account is verified
            if ($userData['is_verified'] == 0) {
                // Account not verified
                return 'not_verified'; 
            }

            $this->id = $userData['id'];
            $this->username = $userData['username'];
            $this->name = $userData['name'];
            $this->email = $userData['email'];
            $this->role = $userData['role'];
            $this->isVerified = (bool)$userData['is_verified']; // Set new property
            $this->isAuthenticated = true;
            
            $this->saveToSession();
            return true;
        }
        
        return false;
    }

    public function countStudents() {
        $query = "SELECT COUNT(*) as student_count FROM " . $this->table_name . " WHERE role = 'student' AND is_verified = 1";
        $result = $this->conn->query($query); 
        if ($result) {
            $row = $result->fetch_assoc();
            $result->free();
            return $row['student_count'];
        } else {
            error_log("Error counting all students: " . $this->conn->error);
            return 0;
        }
    }

    public function getStudentDetails($user_id) {
        $student = new Student($this->db);
        return $student->getByUserId($user_id);
    }

    public function countStudentsByCourseMajor($course, $major = null) {
        $course_major_filter = $course;
        if (!empty($major)) {
            $course_major_filter .= ' : ' . $major;
        }

        $query = "SELECT COUNT(sd.user_id) as total_students
                  FROM students sd
                  JOIN users u ON sd.user_id = u.id
                  WHERE u.role = 'student' AND sd.course = ?"; 

        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("User::countStudentsByCourseMajor - Prepare failed: " . $this->conn->error);
            return 0;
        }

        $stmt->bind_param("s", $course_major_filter);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return $row['total_students'] ?? 0;
    }

    public function logout() {
        $this->id = null;
        $this->username = null;
        $this->name = null;
        $this->email = null;
        $this->role = null;
        $this->isAuthenticated = false;
        $this->isVerified = false;
        
        session_unset();
        session_destroy();
    }

    public function isLoggedIn() {
        return $this->isAuthenticated;
    }

    public function hasRole($requiredRole) {
        return $this->isAuthenticated && $this->role === $requiredRole;
    }

    public function getUsername() {
        return $this->username;
    }

    public function getRole() {
        return $this->role;
    }

    public function getName() {
        return $this->name;
    }

    public function getEmail() {
        return $this->email;
    }

    public function getId() {
        return $this->id;
    }

    public function getIsVerified() {
        return $this->isVerified;
    }

    private function loadFromSession() {
        if (isset($_SESSION['user_id'])) {
            $this->id = $_SESSION['user_id'];
            $this->username = $_SESSION['username'];
            $this->name = $_SESSION['name'] ?? null;
            $this->email = $_SESSION['email'] ?? null;
            $this->role = $_SESSION['role'];
            $this->isVerified = $_SESSION['is_verified'] ?? false; // Load verification status
            $this->isAuthenticated = true;
        }
    }

    private function saveToSession() {
        $_SESSION['user_id'] = $this->id;
        $_SESSION['username'] = $this->username;
        $_SESSION['name'] = $this->name;
        $_SESSION['email'] = $this->email;
        $_SESSION['role'] = $this->role;
        $_SESSION['is_verified'] = $this->isVerified; // Save verification status
        $_SESSION['logged_in'] = true;
    }

    /**
     * Creates a new user account.
     *
     * @param string $username
     * @param string $name
     * @param string $hashed_password
     * @param string $email
     * @param string $role
     * @param string|null $verification_token (Optional) Token for email verification.
     * @param int $is_verified (Optional) 0 for unverified, 1 for verified. Defaults to 0.
     * @return int|false New user ID on success, false on failure.
     */
    public function create($username, $name, $hashed_password, $email, $role, $verification_token = null, $is_verified = 0) {
        $query = "INSERT INTO users (username, name, password_hash, email, role, verification_token, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            error_log("User::create - Prepare failed: " . $this->conn->error);
            return false;
        }

        // Sanitize token before binding
        $verification_token = htmlspecialchars(strip_tags($verification_token));

        $stmt->bind_param("ssssssi", $username, $name, $hashed_password, $email, $role, $verification_token, $is_verified);

        if ($stmt->execute()) {
            $new_user_id = $this->conn->insert_id;
            $stmt->close();
            return $new_user_id;
        } 
        error_log("User::create - Execute failed: " . $stmt->error);
        $stmt->close();
        return false;
    }

    /**
     * Verifies a user by their verification token.
     * @param string $token The verification token.
     * @return bool True on success, false if token is invalid or user not found.
     */
    public function verifyUserByToken(string $token): bool {
        $query = "UPDATE " . $this->table_name . " SET is_verified = 1, verification_token = NULL WHERE verification_token = ? AND is_verified = 0";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("User::verifyUserByToken - Prepare failed: " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $affected_rows > 0;
    }


    public function getFacultyCourseMajor($user_id) {
        $query = "SELECT course FROM faculty_details WHERE user_id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("User::getFacultyCourseMajor - Prepare Error: " . $this->conn->error);
            return null;
        }
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['course'];
        }
        return null;
    }

    public function getFacultySubject($user_id){
        $query = "SELECT subject FROM faculty_details WHERE user_id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("User::getFacultySubject - Prepare Error: " . $this->conn->error);
            return null;
        }
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['subject'];
        }
        return null;
    }

    public function getFacultyYear($user_id){
        $query = "SELECT year FROM faculty_details WHERE user_id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("User::getFacultyYear - Prepare Error: " . $this->conn->error);
            return null;
        }
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['year'];
        }
        return null;
    }

    public function getFacultySection($user_id){
        $query = "SELECT section FROM faculty_details WHERE user_id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("User::getFacultySection - Prepare Error: " . $this->conn->error);
            return null;
        }
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['section'];
        }
        return null;
    }

    public function findByUsername($username) {
        $query = "SELECT id, username, name, email, role, password_hash, is_verified FROM users WHERE username = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
             error_log("User::findByUsername - Prepare Error: " . $this->conn->error);
             return false;
        }
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        if ($result->num_rows > 0) {
            $userData = $result->fetch_assoc();
            return $userData;
        }
        
        return false;
    }

    public function findByEmail($email) {
        $query = "SELECT id, username, name, email, role, password_hash, is_verified FROM " . $this->table_name . " WHERE email = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("User::findByEmail - MySQLi Prepare Error: " . $this->conn->error);
            return false;
        }
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        $row = $result->fetch_assoc();
        return $row;
    }

    public function updatePassword($newHashedPassword) {
        $query = "UPDATE " . $this->table_name . " SET password_hash = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("User::updatePassword - MySQLi Prepare Error: " . $this->conn->error);
            return false;
        }
        $newHashedPassword_sanitized = htmlspecialchars(strip_tags($newHashedPassword));
        $stmt->bind_param('si', $newHashedPassword_sanitized, (int)$this->id);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            error_log("User::updatePassword - MySQLi Execute Error: " . $stmt->error);
            $stmt->close();
            return false;
        }
    }

    public function createFacultyDetails($userId, $course, $subject) {
        $query = "INSERT INTO faculty_details (user_id, course, subject) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("User::createFacultyDetails - Prepare Error: " . $this->conn->error);
            return false;
        }
        $course_sanitized = htmlspecialchars(strip_tags($course));
        $subject_sanitized = htmlspecialchars(strip_tags($subject));
        $stmt->bind_param('iss', $userId, $course_sanitized, $subject_sanitized);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            error_log("User::createFacultyDetails - Execute Error: " . $stmt->error);
            $stmt->close();
            return false;
        }
    }

    public function getFullUserProfile($userId) {
        $query = "SELECT u.id, u.username, u.name, u.email, u.role, u.is_verified,
                                    fd.course, fd.subject, fd.section, fd.year
                             FROM " . $this->table_name . " u
                             LEFT JOIN faculty_details fd ON u.id = fd.user_id
                             WHERE u.id = ? LIMIT 1";

        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("User::getFullUserProfile - Prepare Error: " . $this->conn->error);
            return false;
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return $row;
    }

    public function updateFacultyCourse($userId, $newCourse, $newSubject) {
        $query = "UPDATE faculty_details SET course = ?, subject = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("User::updateFacultyCourse - Prepare Error: " . $this->conn->error);
            return false;
        }
        $newCourse_sanitized = htmlspecialchars(strip_tags($newCourse));
        $newSubject_sanitized = htmlspecialchars(strip_tags($newSubject));
        $stmt->bind_param('ssi', $newCourse_sanitized, $newSubject_sanitized, $userId);

        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            error_log("User::updateFacultyCourse - Execute Error: " . $stmt->error);
            $stmt->close();
            return false;
        }
    }

    public function updateNameAndEmail($id, $name, $email) {
        $query = "UPDATE " . $this->table_name . " SET name = ?, email = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("User::updateNameAndEmail - Prepare failed: " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("ssi", $name, $email, $id);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        }
        error_log("User::updateNameAndEmail - Execute failed: " . $stmt->error);
        $stmt->close();
        return false;
    }

    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("User::delete - Prepare failed: " . $this->conn->error);
            return false;
        }

        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        }
        error_log("User::delete - Execute failed: " . $stmt->error);
        $stmt->close();
        return false;
    }

    public function splitCourseMajor($combined_course) {
        if (strpos($combined_course, ':') !== false) {
            $parts = explode(':', $combined_course, 2);
            return [
                'course' => trim($parts[0]),
                'major' => trim($parts[1])
            ];
        }
        return [
            'course' => trim($combined_course),
            'major' => null
        ];
    }
}
