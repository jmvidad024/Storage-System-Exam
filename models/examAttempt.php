<?php

class StudentExamAttempt {
    private $conn;
    private $table_name = "student_exam_attempts";

    public function __construct(Database $db) {
        $this->conn = $db->getConnection();
    }

    /**
     * Records a student's attempt on an exam.
     * If an attempt already exists for this user and exam, it returns its ID.
     *
     * @param int $user_id The ID of the student (from the users table).
     * @param int $exam_id The ID of the exam.
     * @param bool $is_completed Whether the attempt is completed (default false).
     * @return int|false The ID of the existing or new attempt on success, false on failure.
     */
    public function createAttempt($user_id, $exam_id, $is_completed = false) {
        // First, check if an attempt for this user and exam already exists.
        $existing_attempt = $this->getAttemptDetails($user_id, $exam_id);
        
        if ($existing_attempt) {
            // If an attempt already exists, return its ID instead of creating a duplicate.
            return $existing_attempt['id']; 
        }

        $query = "INSERT INTO " . $this->table_name . " (user_id, exam_id, is_completed) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($query);

        // Convert boolean to integer for MySQL tinyint(1)
        $is_completed_int = $is_completed ? 1 : 0; 
        $stmt->bind_param("iii", $user_id, $exam_id, $is_completed_int);

        if ($stmt->execute()) {
            $new_id = $stmt->insert_id;
            $stmt->close();
            return $new_id; // Return the new attempt ID
        } else {
            error_log("Failed to create exam attempt: " . $stmt->error); // Log error for debugging
            $stmt->close();
            return false;
        }
    }

    /**
     * Retrieves details of a specific exam attempt by user and exam ID.
     *
     * @param int $user_id The ID of the student.
     * @param int $exam_id The ID of the exam.
     * @return array|false The attempt data (id, user_id, exam_id, is_completed) if found, false otherwise.
     */
    public function getAttemptDetails($user_id, $exam_id) {
        $query = "SELECT id, user_id, exam_id, is_completed FROM " . $this->table_name . " WHERE user_id = ? AND exam_id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: (" . $this->conn->errno . ") " . $this->conn->error);
        }
        $stmt->bind_param("ii", $user_id, $exam_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        return $data;
    }

    /**
     * Retrieves details of a specific exam attempt by its unique ID.
     * This is useful for `viewExamResults.php`.
     *
     * @param int $attempt_id The unique ID of the exam attempt record.
     * @return array|false The attempt data (id, user_id, exam_id, is_completed) if found, false otherwise.
     */
    public function getAttemptDetailsById($attempt_id) {
        $query = "SELECT id, user_id, exam_id, is_completed FROM " . $this->table_name . " WHERE id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: (" . $this->conn->errno . ") " . $this->conn->error);
        }
        $stmt->bind_param("i", $attempt_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        return $data;
    }


    /**
     * Checks if a student has already attempted a specific exam.
     * (This just checks for existence of an attempt record).
     *
     * @param int $user_id The ID of the student.
     * @param int $exam_id The ID of the exam.
     * @return bool True if an attempt exists, false otherwise.
     */
    public function hasStudentAttempted($user_id, $exam_id) {
        $attempt = $this->getAttemptDetails($user_id, $exam_id);
        return $attempt !== false;
    }

    /**
     * Marks an existing exam attempt as completed.
     *
     * @param int $user_id The ID of the student.
     * @param int $exam_id The ID of the exam.
     * @return bool True on success, false on failure.
     */
    public function markAttemptCompleted($user_id, $exam_id) {
        $query = "UPDATE " . $this->table_name . " SET is_completed = TRUE WHERE user_id = ? AND exam_id = ? AND is_completed = FALSE";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: (" . $this->conn->errno . ") " . $this->conn->error);
        }
        $stmt->bind_param("ii", $user_id, $exam_id);

        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            error_log("Failed to mark exam attempt completed: " . $stmt->error);
            $stmt->close();
            return false;
        }
    }

    /**
     * Get all exam attempts for a specific student, including completion status and attempt ID.
     *
     * @param int $user_id The ID of the student.
     * @return array An array of attempt data (exam_id, is_completed, attempt_id).
     */
    public function getStudentAttemptedExams($user_id) {
        $query = "SELECT id, exam_id, is_completed FROM " . $this->table_name . " WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: (" . $this->conn->errno . ") " . $this->conn->error);
        }
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $attempted_exams = [];
        while ($row = $result->fetch_assoc()) {
            $attempted_exams[] = [
                'exam_id' => (int) $row['exam_id'],
                'is_completed' => (bool) $row['is_completed'], // Cast to boolean
                'attempt_id' => (int) $row['id'] // Ensure attempt_id is returned
            ];
        }
        $stmt->close();
        return $attempted_exams;
    }

    /**
     * Deletes all exam attempts associated with a given exam ID.
     * Due to ON DELETE CASCADE, this should also delete related records in
     * student_exam_results and student_answers.
     *
     * @param int $exam_id The ID of the exam for which to delete attempts.
     * @return bool True on success, false on failure.
     */
    public function deleteAttemptsByExamId($exam_id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE exam_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed for deleteAttemptsByExamId: " . $this->conn->error);
        }
        $stmt->bind_param("i", $exam_id);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            error_log("Failed to delete exam attempts by exam ID: " . $stmt->error);
            $stmt->close();
            return false;
        }
    }
}
