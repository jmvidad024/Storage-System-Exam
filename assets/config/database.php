<?php
require_once "../env_loader.php"; // Load your .env file

class Database {
    private $host;
    private $username;
    private $password;
    private $dbname;
    private $conn;
    
    public function __construct() {
        $this->host = $_ENV['DB_HOST'];
        $this->username = $_ENV['DB_USER'];
        $this->password = $_ENV['DB_PASSWORD'];
        $this->dbname = $_ENV['DB_NAME'];
        
        $this->connect();
    }
    
    private function connect() {
        $this->conn = new mysqli(
            $this->host,
            $this->username,
            $this->password,
            $this->dbname
        );
        
        // Check connection
        if ($this->conn->connect_error) {
            error_log("Connection failed: " . $this->conn->connect_error);
            throw new Exception("Database connection failed");
        }
        
        // Set charset - THIS IS CRUCIAL!
        if (!$this->conn->set_charset('utf8mb4')) {
            error_log("Error loading charset utf8mb4: " . $this->conn->error);
            throw new Exception("Error setting charset");
        }
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    public function closeConnection() {
        if ($this->conn) {
            $this->conn->close();
            echo "Successfully closed connection";
        }
    }
    
    public function query($sql, $params = []) {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->conn->error);
        }
        
        if (!empty($params)) {
            $types = str_repeat('s', count($params)); // all params as strings by default
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        return $stmt;
    }
    // Prevent cloning and unserialization
    public function __clone() {}
    public function __wakeup() {}
}