<?php

class ExamModel {
    private $conn;
    private $exams_table = "exams";
    private $questions_table = "questions";
    private $choices_table = "choices";
    private $student_exam_attempts_table = "student_exam_attempts"; // Needed for CASCADE reference

    public function __construct(Database $db) {
        $this->conn = $db->getConnection();
    }

    /**
     * Fetches a full exam by its ID, including all questions and their choices.
     * Includes the correct 'answer' for admin/faculty viewing.
     *
     * @param int $exam_id The ID of the exam to fetch.
     * @return array|false The exam data with nested questions and choices, or false if not found.
     */
    public function getExamById($exam_id) {
        // Fetch main exam details
        $query = "SELECT exam_id, title, instruction, year, section, code FROM " . $this->exams_table . " WHERE exam_id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: (" . $this->conn->errno . ") " . $this->conn->error);
        }
        $stmt->bind_param("i", $exam_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $exam = $result->fetch_assoc();
        $stmt->close();

        if (!$exam) {
            return false; // Exam not found
        }

        // Fetch questions for the exam
        $questions_query = "SELECT question_id, question_text, answer FROM " . $this->questions_table . " WHERE exam_id = ?";
        $stmt_q = $this->conn->prepare($questions_query);
        if (!$stmt_q) {
            throw new Exception("Prepare failed: (" . $this->conn->errno . ") " . $this->conn->error);
        }
        $stmt_q->bind_param("i", $exam_id);
        $stmt_q->execute();
        $questions_result = $stmt_q->get_result();

        $questions = [];
        while ($question = $questions_result->fetch_assoc()) {
            // Fetch choices for each question
            $choices_query = "SELECT choice_id, choice_text FROM " . $this->choices_table . " WHERE question_id = ?";
            $stmt_c = $this->conn->prepare($choices_query);
            if (!$stmt_c) {
                throw new Exception("Prepare failed: (" . $this->conn->errno . ") " . $this->conn->error);
            }
            $stmt_c->bind_param("i", $question['question_id']);
            $stmt_c->execute();
            $choices_result = $stmt_c->get_result();

            $choices = [];
            while ($choice = $choices_result->fetch_assoc()) {
                $choices[] = $choice; // Store choice_id as well
            }
            $stmt_c->close();

            $question['choices'] = $choices;
            $questions[] = $question;
        }
        $stmt_q->close();

        $exam['questions'] = $questions;

        return $exam;
    }

    /**
     * Retrieves only the question IDs and their correct answers for a given exam.
     * Useful for grading without exposing all exam details.
     *
     * @param int $exam_id The ID of the exam.
     * @return array An associative array of question_id => correct_answer.
     */
    public function getCorrectAnswersForExam($exam_id) {
        $query = "SELECT question_id, answer FROM " . $this->questions_table . " WHERE exam_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: (" . $this->conn->errno . ") " . $this->conn->error);
        }
        $stmt->bind_param("i", $exam_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $correct_answers = [];
        while ($row = $result->fetch_assoc()) {
            $correct_answers[$row['question_id']] = $row['answer'];
        }
        $stmt->close();
        return $correct_answers;
    }


    /**
     * Updates the main details of an exam.
     *
     * @param int $exam_id
     * @param string $title
     * @param string $instruction
     * @param int $year
     * @param string $section
     * @param string $code
     * @return bool True on success, false on failure.
     */
    public function updateExamMain($exam_id, $title, $instruction, $year, $section, $code) {
        $query = "UPDATE " . $this->exams_table . "
                  SET title = ?, instruction = ?, year = ?, section = ?, code = ?
                  WHERE exam_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) { throw new Exception("Prepare failed: " . $this->conn->error); }
        $stmt->bind_param("ssissi", $title, $instruction, $year, $section, $code, $exam_id);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        }
        $stmt->close();
        return false;
    }

    /**
     * Creates a new question for an exam.
     *
     * @param int $exam_id
     * @param string $question_text
     * @param string $answer
     * @return int|false The new question_id on success, false on failure.
     */
    public function createQuestion($exam_id, $question_text, $answer) {
        $query = "INSERT INTO " . $this->questions_table . " (exam_id, question_text, answer) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) { throw new Exception("Prepare failed: " . $this->conn->error); }
        $stmt->bind_param("iss", $exam_id, $question_text, $answer);
        if ($stmt->execute()) {
            $new_id = $stmt->insert_id;
            $stmt->close();
            return $new_id;
        }
        $stmt->close();
        return false;
    }

    /**
     * Updates an existing question.
     *
     * @param int $question_id
     * @param string $question_text
     * @param string $answer
     * @return bool True on success, false on failure.
     */
    public function updateQuestion($question_id, $question_text, $answer) {
        $query = "UPDATE " . $this->questions_table . " SET question_text = ?, answer = ? WHERE question_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) { throw new Exception("Prepare failed: " . $this->conn->error); }
        $stmt->bind_param("ssi", $question_text, $answer, $question_id);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        }
        $stmt->close();
        return false;
    }

    /**
     * Deletes a question and its associated choices (if cascades are set up).
     *
     * @param int $question_id
     * @return bool True on success, false on failure.
     */
    public function deleteQuestion($question_id) {
        // Assuming ON DELETE CASCADE from questions to choices, otherwise delete choices explicitly here
        $query = "DELETE FROM " . $this->questions_table . " WHERE question_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) { throw new Exception("Prepare failed: " . $this->conn->error); }
        $stmt->bind_param("i", $question_id);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        }
        $stmt->close();
        return false;
    }

    /**
     * Creates a new choice for a question.
     *
     * @param int $question_id
     * @param string $choice_text
     * @return int|false The new choice_id on success, false on failure.
     */
    public function createChoice($question_id, $choice_text) {
        $query = "INSERT INTO " . $this->choices_table . " (question_id, choice_text) VALUES (?, ?)";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) { throw new Exception("Prepare failed: " . $this->conn->error); }
        $stmt->bind_param("is", $question_id, $choice_text);
        if ($stmt->execute()) {
            $new_id = $stmt->insert_id;
            $stmt->close();
            return $new_id;
        }
        $stmt->close();
        return false;
    }

    /**
     * Updates an existing choice.
     *
     * @param int $choice_id
     * @param string $choice_text
     * @return bool True on success, false on failure.
     */
    public function updateChoice($choice_id, $choice_text) {
        $query = "UPDATE " . $this->choices_table . " SET choice_text = ? WHERE choice_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) { throw new Exception("Prepare failed: " . $this->conn->error); }
        $stmt->bind_param("si", $choice_text, $choice_id);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        }
        $stmt->close();
        return false;
    }

    /**
     * Deletes a choice.
     *
     * @param int $choice_id
     * @return bool True on success, false on failure.
     */
    public function deleteChoice($choice_id) {
        $query = "DELETE FROM " . $this->choices_table . " WHERE choice_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) { throw new Exception("Prepare failed: " . $this->conn->error); }
        $stmt->bind_param("i", $choice_id);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        }
        $stmt->close();
        return false;
    }

    /**
     * Gets an array of question IDs for a specific exam.
     * Used for identifying questions to delete during exam update.
     *
     * @param int $exam_id
     * @return array An array of question IDs.
     */
    public function getQuestionIdsForExam($exam_id) {
        $query = "SELECT question_id FROM " . $this->questions_table . " WHERE exam_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) { throw new Exception("Prepare failed: " . $this->conn->error); }
        $stmt->bind_param("i", $exam_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $ids = [];
        while ($row = $result->fetch_assoc()) {
            $ids[] = (int)$row['question_id'];
        }
        $stmt->close();
        return $ids;
    }

    /**
     * Gets an array of choice IDs for a specific question.
     * Used for identifying choices to delete during question update.
     *
     * @param int $question_id
     * @return array An array of choice IDs.
     */
    public function getChoiceIdsForQuestion($question_id) {
        $query = "SELECT choice_id FROM " . $this->choices_table . " WHERE question_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) { throw new Exception("Prepare failed: " . $this->conn->error); }
        $stmt->bind_param("i", $question_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $ids = [];
        while ($row = $result->fetch_assoc()) {
            $ids[] = (int)$row['choice_id'];
        }
        $stmt->close();
        return $ids;
    }
}
