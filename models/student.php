<?php

class Student {
    private $conn;
    private $table_name = "students"; // Your students table name

    public $id; // Primary key of the students table
    public $user_id; // Foreign key to the users table
    public $student_id; // The actual student ID number/string
    public $course;
    public $year;
    public $section;
    public $created_at;

    public function __construct(Database $db) {
        $this->conn = $db->getConnection(); // Get the mysqli connection
    }

    /**
     * Creates a new student record.
     *
     * @param int $user_id The ID of the associated user.
     * @param string $student_id The unique student ID (e.g., a student number).
     * @param string $course The course name.
     * @param int $year The year level.
     * @param string $section The section name.
     * @return bool True on success, false on failure.
     */
    public function create($user_id, $student_id, $course, $year, $section) {
        $query = "INSERT INTO " . $this->table_name . "
                  SET
                      user_id = ?,
                      student_id = ?,
                      course = ?,
                      year = ?,
                      section = ?";

        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            error_log("Student Model Create - Prepare failed: (" . $this->conn->errno . ") " . $this->conn->error);
            return false;
        }

        // Sanitize data
        $student_id = htmlspecialchars(strip_tags($student_id));
        $course = htmlspecialchars(strip_tags($course));
        $section = htmlspecialchars(strip_tags($section));
        // Year is an integer, no need for htmlspecialchars/strip_tags

        // Bind values: 'issis' specifies types: integer (user_id), string (student_id), string (course), integer (year), string (section)
        $stmt->bind_param("issis", $user_id, $student_id, $course, $year, $section);

        if ($stmt->execute()) {
            $stmt->close();
            return true;
        }

        error_log("Student Model Create - Execute failed: (" . $stmt->errno . ") " . $stmt->error);
        $stmt->close();
        return false;
    }

    // Add this method to your Student class
/**
 * Gets student details by user_id
 * 
 * @param int $user_id The user ID
 * @return array|false Student data if found, false otherwise
 */
public function getByUserId($user_id) {
    $query = "SELECT id, user_id, student_id, course, year, section, created_at
              FROM " . $this->table_name . "
              WHERE user_id = ? LIMIT 1";

    $stmt = $this->conn->prepare($query);
    if (!$stmt) {
        error_log("Student Model getByUserId - Prepare failed: (" . $this->conn->errno . ") " . $this->conn->error);
        return false;
    }

    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $student_data = $result->fetch_assoc();
        $stmt->close();
        return $student_data;
    }

    error_log("Student Model getByUserId - Execute failed: (" . $stmt->errno . ") " . $stmt->error);
    $stmt->close();
    return false;
}

    /**
     * Finds a student by their unique student_id and returns their details.
     *
     * @param string $student_id The unique student ID.
     * @return array|false Student data if found, false otherwise.
     */
    public function findByStudentId($student_id) {
        $query = "SELECT id, user_id, student_id, course, year, section, created_at
                  FROM " . $this->table_name . "
                  WHERE student_id = ? LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("Student Model findByStudentId - Prepare failed: (" . $this->conn->errno . ") " . $this->conn->error);
            return false;
        }

        $stmt->bind_param("s", $student_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $student_data = $result->fetch_assoc();
            $stmt->close();
            return $student_data;
        }

        error_log("Student Model findByStudentId - Execute failed: (" . $stmt->errno . ") " . $stmt->error);
        $stmt->close();
        return false;
    }

    /**
     * Gets the user_id associated with a specific student_id.
     *
     * @param int $student_id The internal ID (primary key) of the student record.
     * @return int|false The user_id if found, false otherwise.
     */
    public function getUserIdByStudentId($student_id) {
        $query = "SELECT user_id FROM " . $this->table_name . " WHERE id = ? LIMIT 1"; // Assuming 'id' is the primary key of the students table
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("Student Model getUserIdByStudentId - Prepare failed: (" . $this->conn->errno . ") " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("i", $student_id); // 'i' for integer type of student_id (primary key)
        if (!$stmt->execute()) {
            error_log("Student Model getUserIdByStudentId - Execute failed: (" . $stmt->errno . ") " . $stmt->error);
            $stmt->close();
            return false;
        }
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return $row ? (int)$row['user_id'] : false;
    }


    /**
     * Updates an existing student record.
     * Note: This method updates the course, year, and section only for the student.
     * User details (name, email) are updated via the User model.
     *
     * @param int $id The internal ID (primary key) of the student record to update.
     * @param string $course The new course name.
     * @param int $year The new year level.
     * @param string $section The new section name.
     * @return bool True on success, false on failure.
     */
    public function update($id, $course, $year, $section) {
        $query = "UPDATE " . $this->table_name . "
                  SET
                      course = ?,
                      year = ?,
                      section = ?
                  WHERE id = ?"; // 'id' is the primary key for the students table

        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            error_log("Student Model Update - Prepare failed: (" . $this->conn->errno . ") " . $this->conn->error);
            return false;
        }

        // Sanitize data
        $course = htmlspecialchars(strip_tags($course));
        $section = htmlspecialchars(strip_tags($section));

        // Bind values: 'sisi' specifies types: string (course), integer (year), string (section), integer (id)
        $stmt->bind_param("sisi", $course, $year, $section, $id);

        if ($stmt->execute()) {
            $stmt->close();
            return true;
        }

        error_log("Student Model Update - Execute failed: (" . $stmt->errno . ") " . $stmt->error);
        $stmt->close();
        return false;
    }

    /**
     * Deletes a student record by its internal ID (primary key).
     * This method only deletes the student record itself. The calling API (delete_student.php)
     * is responsible for deleting the associated user record to maintain data integrity.
     *
     * @param int $id The internal ID (primary key) of the student record to delete.
     * @return bool True on success, false on failure.
     */
    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";

        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            error_log("Student Model Delete - Prepare failed: (" . $this->conn->errno . ") " . $this->conn->error);
            return false;
        }

        $stmt->bind_param("i", $id); // 'i' for integer type of id

        if ($stmt->execute()) {
            $stmt->close();
            return true;
        }

        error_log("Student Model Delete - Execute failed: (" . $stmt->errno . ") " . $stmt->error);
        $stmt->close();
        return false;
    }
}
