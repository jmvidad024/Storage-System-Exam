<?php

class StudentAnswer {
    private $conn;
    private $table_name = "student_answers";

    public function __construct(Database $db) {
        $this->conn = $db->getConnection();
    }

    /**
     * Saves a student's answer for a specific question.
     *
     * @param int $attempt_id The ID of the student_exam_attempt.
     * @param int $question_id The ID of the question.
     * @param string $submitted_answer The answer provided by the student.
     * @param bool $is_correct Whether the answer is correct.
     * @param float $score_earned The points earned for this answer.
     * @return int|false The ID of the new answer entry on success, false on failure.
     */
    public function createAnswer($attempt_id, $question_id, $submitted_answer, $is_correct, $score_earned = 0.00) {
        $query = "INSERT INTO " . $this->table_name . " (attempt_id, question_id, submitted_answer, is_correct, score_earned) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: (" . $this->conn->errno . ") " . $this->conn->error);
        }
        // i=int, i=int, s=string, i=int (for boolean true/false), d=double/float
        $is_correct_int = $is_correct ? 1 : 0; // Convert boolean to int for MySQL tinyint(1)
        $stmt->bind_param("iisid", $attempt_id, $question_id, $submitted_answer, $is_correct_int, $score_earned);

        if ($stmt->execute()) {
            $new_id = $stmt->insert_id;
            $stmt->close();
            return $new_id;
        } else {
            error_log("Failed to create student answer: " . $stmt->error);
            $stmt->close();
            return false;
        }
    }

    /**
     * Retrieves all answers for a specific exam attempt.
     *
     * @param int $attempt_id The ID of the student_exam_attempt.
     * @return array An array of answer data.
     */
    public function getAnswersByAttemptId($attempt_id) {
        $query = "SELECT answer_id, attempt_id, question_id, submitted_answer, is_correct, score_earned FROM " . $this->table_name . " WHERE attempt_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: (" . $this->conn->errno . ") " . $this->conn->error);
        }
        $stmt->bind_param("i", $attempt_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $answers = [];
        while ($row = $result->fetch_assoc()) {
            $answers[] = $row;
        }
        $stmt->close();
        return $answers;
    }
}
