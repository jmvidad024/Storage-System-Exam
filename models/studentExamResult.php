<?php

class StudentExamResult {
    private $conn;
    private $table_name = "student_exam_results";

    public function __construct(Database $db) {
        $this->conn = $db->getConnection();
    }

    /**
     * Creates a new exam result entry.
     *
     * @param int $attempt_id The ID of the student_exam_attempt.
     * @param float $score The score obtained by the student.
     * @param float $max_score The maximum possible score for the exam.
     * @return int|false The ID of the new result entry on success, false on failure.
     */
    public function createResult($attempt_id, $score, $max_score) {
        $query = "INSERT INTO " . $this->table_name . " (attempt_id, score, max_score) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: (" . $this->conn->errno . ") " . $this->conn->error);
        }
        $stmt->bind_param("idd", $attempt_id, $score, $max_score); // i=int, d=double/float

        if ($stmt->execute()) {
            $new_id = $stmt->insert_id;
            $stmt->close();
            return $new_id;
        } else {
            error_log("Failed to create exam result: " . $stmt->error);
            $stmt->close();
            return false;
        }
    }

    /**
     * Retrieves an exam result by attempt ID.
     *
     * @param int $attempt_id The ID of the student_exam_attempt.
     * @return array|false The result data as an associative array, or false if not found.
     */
    public function getResultByAttemptId($attempt_id) {
        $query = "SELECT result_id, attempt_id, score, max_score, submitted_at FROM " . $this->table_name . " WHERE attempt_id = ? LIMIT 1";
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
     * Update the score for an existing exam result.
     *
     * @param int $result_id The ID of the result record.
     * @param float $score The new score.
     * @return bool True on success, false on failure.
     */
    public function updateScore($result_id, $score) {
        $query = "UPDATE " . $this->table_name . " SET score = ? WHERE result_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: (" . $this->conn->errno . ") " . $this->conn->error);
        }
        $stmt->bind_param("di", $score, $result_id);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            error_log("Failed to update exam result score: " . $stmt->error);
            $stmt->close();
            return false;
        }
    }
}
