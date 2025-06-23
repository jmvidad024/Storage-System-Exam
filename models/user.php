<?php

require_once 'student.php';

class User {
    private $table_name = "users"; // Your students table name
    private $conn;
    public $id;
    private $username;
    private $name; 
    public $email;
    public $role;
    private $password_hash;
    private $isAuthenticated = false;
    private $db;

    public function __construct(Database $database) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $this->db = $database;
    $this->conn = $database->getConnection(); // <---- ADD THIS LINE
    $this->loadFromSession();
}

    public function login($username, $password) {
        // Use your existing database connection
        $conn = $this->db->getConnection(); // Get the mysqli connection object
        $stmt = $conn->prepare(
            "SELECT id, username, name, email, role, password_hash FROM users WHERE username = ?"
        );
        
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $userData = $result->fetch_assoc();
        
        if ($userData && password_verify($password, $userData['password_hash'])) {
            $this->id = $userData['id'];
            $this->username = $userData['username'];
            $this->name = $userData['name']; // Load name into property
            $this->email = $userData['email']; // Load email into property
            $this->role = $userData['role'];
            $this->isAuthenticated = true;
            
            $this->saveToSession();
            return true;
        }
        
        return false;
    }

 public function countStudents() {
        $query = "SELECT COUNT(*) as student_count FROM " . $this->table_name . " WHERE role = 'student'";
        
        // Using object-oriented MySQLi
        $result = $this->conn->query($query); 
        
        if ($result) {
            $row = $result->fetch_assoc(); // Use fetch_assoc() for associative array
            $result->free(); // Free the result set
            return $row['student_count'];
        } else {
            // Handle error, e.g., log it or throw an exception
            error_log("Error counting students: " . $this->conn->error);
            return 0; // Return 0 or handle error appropriately
        }
    }

    // In your User class, update or add this method:
public function getStudentDetails($user_id) {
    $student = new Student($this->db);
    return $student->getByUserId($user_id);
}

    public function logout() {
        $this->id = null;
        $this->username = null;
        $this->name = null; // Clear name
        $this->email = null; // Clear email
        $this->role = null;
        $this->isAuthenticated = false;
        
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

    // New: Method to get user ID
    public function getId() {
        return $this->id;
    }

    private function loadFromSession() {
        if (isset($_SESSION['user_id'])) {
            $this->id = $_SESSION['user_id'];
            $this->username = $_SESSION['username'];
            $this->role = $_SESSION['role'];
            $this->isAuthenticated = true;
        }
    }

    private function saveToSession() {
        $_SESSION['user_id'] = $this->id;
        $_SESSION['username'] = $this->username;
        $_SESSION['name'] = $this->name;
        $_SESSION['role'] = $this->role;
        $_SESSION['logged_in'] = true;
    }

    
    public function create($username, $name, $hashed_password, $email, $role) {
        $conn = $this->db->getConnection(); // Get the mysqli connection object

        // Query to insert record - ensure 'password_hash' column name matches your DB
        $query = "INSERT INTO users (username, name, password_hash, email, role) VALUES (?, ?, ?, ?, ?)";

        // Prepare statement
        $stmt = $conn->prepare($query);

        // Bind values
        // 'sssss' specifies the types of the parameters: string, string, string, string, string
        $stmt->bind_param("sssss", $username, $name, $hashed_password, $email, $role);

        // Execute query
        if ($stmt->execute()) {
            $new_user_id = $conn->insert_id; // THIS IS THE IMPORTANT PART
            $stmt->close();
            return $new_user_id; // Return the integer ID
        } 

        // For debugging:
        // error_log("Error creating user: " . $stmt->error);
        $stmt->close();
        return false;
    }

    public function getFacultyCourseMajor($user_id) {
        $query = "SELECT course FROM faculty_details WHERE user_id = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['course']; // Returns "Course : Major" or "Course"
        }
        return null;
    }

    public function findByUsername($username) {
        $conn = $this->db->getConnection(); // Get the mysqli connection object
        $query = "SELECT id, username, name, email, role, password_hash FROM users WHERE username = ? LIMIT 1";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $userData = $result->fetch_assoc();
            $stmt->close();
            return $userData;
        }
        
        $stmt->close();
        return false;
    }

    public function findByEmail() {
        $query = "SELECT id, username, name, email, role, password_hash
                  FROM " . $this->table_name . "
                  WHERE email = ?
                  LIMIT 0,1";

        // Line 168: This is where the error occurred
        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            // Add error logging for prepare failures
            error_log("MySQLi Prepare Error: " . $this->conn->error);
            return false;
        }

        // Bind the parameter (s for string)
        $this->email = htmlspecialchars(strip_tags($this->email)); // Sanitize email
        $stmt->bind_param('s', $this->email);

        $stmt->execute();
        $result = $stmt->get_result(); // Get the result set

        $row = $result->fetch_assoc();

        if ($row) {
            $this->id = $row['id'];
            $this->username = $row['username'];
            $this->name = $row['name'];
            $this->email = $row['email'];
            $this->role = $row['role'];
            $this->password_hash = $row['password_hash'];
            return $row;
        }
        return false;
    }


    // New method to update a user's password
    public function updatePassword($newHashedPassword) {
        $query = "UPDATE " . $this->table_name . "
                  SET password_hash = ?          -- Changed to positional placeholder
                  WHERE id = ?";            

        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            // It's good practice to log prepare errors
            error_log("MySQLi Prepare Error (updatePassword): " . $this->conn->error);
            return false;
        }

        // Sanitize password (already hashed, so strip_tags is fine)
        // Note: For ID, ensure it's an integer if it's an INT in DB.
        // For password, it's a string.
        $newHashedPassword_sanitized = htmlspecialchars(strip_tags($newHashedPassword));
        $id_sanitized = htmlspecialchars(strip_tags($this->id)); // Ensure this is safe if ID is coming from user input.
                                                              // For internal use like this, if $this->id is set from DB, it's generally safe.

        // Use bind_param for mysqli: 's' for string (password), 'i' for integer (id)
        $stmt->bind_param('si', $newHashedPassword_sanitized, $id_sanitized);

        if ($stmt->execute()) {
            $stmt->close(); // Close the statement after execution
            return true;
        } else {
            // Log execution errors
            error_log("MySQLi Execute Error (updatePassword): " . $stmt->error);
            $stmt->close(); // Close the statement even on error
            return false;
        }
    }

    public function createFacultyDetails($userId, $course = null) {
        $query = "INSERT INTO faculty_details (user_id, course)
                  VALUES (?, ?)";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("Faculty Details Prepare Error: " . $this->conn->error);
            return false;
        }

        // Sanitize the course name if it's coming from user input
        $course_sanitized = htmlspecialchars(strip_tags($course));

        // 'is' => i for user_id (integer), s for course (string)
        $stmt->bind_param('is', $userId, $course_sanitized);

        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            error_log("Faculty Details Execute Error: " . $stmt->error);
            $stmt->close();
            return false;
        }
    }

    // Method to get full user profile including faculty-specific details if applicable
    public function getFullUserProfile($userId) {
        $query = "SELECT u.id, u.username, u.name, u.email, u.role,
                         fd.course -- Only fetch 'course' from faculty_details
                  FROM " . $this->table_name . " u
                  LEFT JOIN faculty_details fd ON u.id = fd.user_id
                  WHERE u.id = ? LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("Get Full User Profile Prepare Error: " . $this->conn->error);
            return false;
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return $row;
    }

    // You might also want a method to update faculty details
    public function updateFacultyCourse($userId, $newCourse) {
        $query = "UPDATE faculty_details
                  SET course = ?, updated_at = CURRENT_TIMESTAMP
                  WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("Update Faculty Course Prepare Error: " . $this->conn->error);
            return false;
        }
        $newCourse_sanitized = htmlspecialchars(strip_tags($newCourse));
        $stmt->bind_param('si', $newCourse_sanitized, $userId);

        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            error_log("Update Faculty Course Execute Error: " . $stmt->error);
            $stmt->close();
            return false;
        }
    }

    public function updateNameAndEmail($id, $name, $email) {
        $query = "UPDATE " . $this->table_name . " SET name = ?, email = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("User Model updateNameAndEmail - Prepare failed: " . $this->conn->error);
            echo(['error'=>"here"]);
            return false;
        }
        $stmt->bind_param("ssi", $name, $email, $id);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        }
        error_log("User Model updateNameAndEmail - Execute failed: " . $stmt->error);
        $stmt->close();
        return false;
    }

    public function delete($id) {
        $conn = $this->db->getConnection(); // Get the mysqli connection object for this method
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $conn->prepare($query); // Use local $conn here
        if (!$stmt) {
            error_log("User Model Delete - Prepare failed: " . $conn->error); // Log error from $conn
            return false;
        }

        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        }
        error_log("User Model Delete - Execute failed: " . $stmt->error);
        $stmt->close();
        return false;
    }
}
?>