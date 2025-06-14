<?php
class User {
    private $table_name = "users"; // Your students table name

    private $id;
    private $username;
    private $name; 
    private $email;
    private $role;
    private $isAuthenticated = false;
    private $db;

    public function __construct(Database $database) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $this->db = $database;
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

    public function getStudentDetails($user_id) {
        $conn = $this->db->getConnection();
        $query = "SELECT student_id, course, year, section FROM students WHERE user_id = ? LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $studentData = $result->fetch_assoc();
        $stmt->close();
        return $studentData;
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

    public function findByEmail($email) {
        $conn = $this->db->getConnection(); // Get the mysqli connection object
        $query = "SELECT id, username, name, email, role, password_hash FROM users WHERE email = ? LIMIT 1";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
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

    public function updateNameAndEmail($id, $name, $email) {
        $query = "UPDATE " . $this->table_name . " SET name = ?, email = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("User Model updateNameAndEmail - Prepare failed: " . $this->conn->error);
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