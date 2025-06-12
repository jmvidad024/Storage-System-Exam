<?php

class Student {
    private $conn;
    private $table_name = "students"; // Your students table name

    public $id;
    public $user_id;
    public $student_id;
    public $course;
    public $year;
    public $section;
    public $created_at;

    public function __construct(Database $db) {
        $this->conn = $db->getConnection(); // Get the mysqli connection
    }

    public function create($user_id, $student_id, $course, $year, $section) {
        $query = "INSERT INTO " . $this->table_name . "
                  SET
                      user_id = ?,
                      student_id = ?,
                      course = ?,
                      year = ?,
                      section = ?";

        $stmt = $this->conn->prepare($query);

        // Sanitize data (optional, but good practice)
        $student_id = htmlspecialchars(strip_tags($student_id));
        $course = htmlspecialchars(strip_tags($course));
        $section = htmlspecialchars(strip_tags($section));
        // Year is an integer, no need for htmlspecialchars/strip_tags

        // Bind values
        // 'isssi' specifies types: integer, string, string, string, integer
        $stmt->bind_param("issis", $user_id, $student_id, $course, $year, $section);

        // Execute query
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        }

        // For debugging:
        // error_log("Error creating student record: " . $stmt->error);
        $stmt->close();
        return false;
    }

    // You might add methods like findByUserId, update, delete etc. here later.
}