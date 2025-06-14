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
        if (!is_numeric($exam_id) || $exam_id <= 0) {
            error_log("ExamModel->getExamById: Invalid exam_id provided: " . var_export($exam_id, true));
            return false;
        }

        // Fetch main exam details
        $query = "SELECT exam_id, title, instruction, year, section, code FROM " . $this->exams_table . " WHERE exam_id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("ExamModel->getExamById: Main exam query prepare failed: (" . $this->conn->errno . ") " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("i", $exam_id);
        if (!$stmt->execute()) {
            error_log("ExamModel->getExamById: Main exam query execute failed: (" . $stmt->errno . ") " . $stmt->error);
            $stmt->close();
            return false;
        }
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
            error_log("ExamModel->getExamById: Questions query prepare failed: (" . $this->conn->errno . ") " . $this->conn->error);
            // Return main exam data even if questions fail, to avoid breaking the API completely
            $exam['questions'] = []; 
            return $exam; 
        }
        $stmt_q->bind_param("i", $exam_id);
        if (!$stmt_q->execute()) {
            error_log("ExamModel->getExamById: Questions query execute failed: (" . $stmt_q->errno . ") " . $stmt_q->error);
            $stmt_q->close();
            $exam['questions'] = [];
            return $exam;
        }
        $questions_result = $stmt_q->get_result();

        $questions = [];
        while ($question = $questions_result->fetch_assoc()) {
            // Ensure question_id is valid before fetching choices
            if (!isset($question['question_id']) || !is_numeric($question['question_id'])) {
                error_log("ExamModel->getExamById: Question fetched without valid question_id, skipping choices for: " . var_export($question, true));
                $question['choices'] = []; // Ensure choices array is set
                $questions[] = $question;
                continue; // Skip this problematic question and move to the next
            }

            // Fetch choices for each question
            $choices_query = "SELECT choice_id, choice_text FROM " . $this->choices_table . " WHERE question_id = ?";
            $stmt_c = $this->conn->prepare($choices_query);
            if (!$stmt_c) {
                error_log("ExamModel->getExamById: Choices query prepare failed for QID " . $question['question_id'] . ": (" . $this->conn->errno . ") " . $this->conn->error);
                $question['choices'] = []; // Add empty choices array
                $questions[] = $question;
                continue; // Move to next question
            }
            $q_id_int = (int)$question['question_id']; // Cast to int for binding
            $stmt_c->bind_param("i", $q_id_int);
            if (!$stmt_c->execute()) {
                error_log("ExamModel->getExamById: Choices query execute failed for QID " . $question['question_id'] . ": (" . $stmt_c->errno . ") " . $stmt_c->error);
                $stmt_c->close();
                $question['choices'] = [];
                $questions[] = $question;
                continue;
            }
            $choices_result = $stmt_c->get_result();

            $choices = [];
            while ($choice = $choices_result->fetch_assoc()) {
                $choices[] = $choice;
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
        if (!is_numeric($exam_id) || $exam_id <= 0) {
            error_log("ExamModel->getCorrectAnswersForExam: Invalid exam_id provided: " . var_export($exam_id, true));
            return [];
        }
        $query = "SELECT question_id, answer FROM " . $this->questions_table . " WHERE exam_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("ExamModel->getCorrectAnswersForExam: Prepare failed: (" . $this->conn->errno . ") " . $this->conn->error);
            return [];
        }
        $stmt->bind_param("i", $exam_id);
        if (!$stmt->execute()) {
            error_log("ExamModel->getCorrectAnswersForExam: Execute failed: (" . $stmt->errno . ") " . $stmt->error);
            $stmt->close();
            return [];
        }
        $result = $stmt->get_result();

        $correct_answers = [];
        while ($row = $result->fetch_assoc()) {
            $correct_answers[$row['question_id']] = $row['answer'];
        }
        $stmt->close();
        return $correct_answers;
    }

    /**
     * Fetches a full exam by its Code, including all questions and their choices.
     * This method directly calls getExamById internally for full details.
     *
     * @param string $code The code of the exam to fetch.
     * @return array|false The exam data with nested questions and choices, or false if not found.
     */
    public function getExamByCode($code) {
        if (empty($code)) {
            error_log("ExamModel->getExamByCode: Empty code provided.");
            return false;
        }

        // Fetch main exam details by code to get the exam_id
        $query = "SELECT exam_id FROM " . $this->exams_table . " WHERE code = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("ExamModel->getExamByCode: Main exam query prepare failed: (" . $this->conn->errno . ") " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("s", $code);
        if (!$stmt->execute()) {
            error_log("ExamModel->getExamByCode: Main exam query execute failed for code '{$code}': (" . $stmt->errno . ") " . $stmt->error);
            $stmt->close();
            return false;
        }
        $result = $stmt->get_result();
        $exam_data = $result->fetch_assoc();
        $stmt->close();

        if (!$exam_data || !isset($exam_data['exam_id'])) {
            return false; // Exam not found or ID missing
        }

        // Now, reuse getExamById to get full details (questions, choices)
        return $this->getExamById((int)$exam_data['exam_id']);
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
        if (!$stmt) { error_log("ExamModel->updateExamMain: Prepare failed: " . $this->conn->error); throw new Exception("Prepare failed: " . $this->conn->error); }
        $stmt->bind_param("ssissi", $title, $instruction, $year, $section, $code, $exam_id);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        }
        error_log("ExamModel->updateExamMain: Execute failed: " . $stmt->error);
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
        if (!$stmt) { error_log("ExamModel->createQuestion: Prepare failed: " . $this->conn->error); throw new Exception("Prepare failed: " . $this->conn->error); }
        $stmt->bind_param("iss", $exam_id, $question_text, $answer);
        if ($stmt->execute()) {
            $new_id = $stmt->insert_id;
            $stmt->close();
            return $new_id;
        }
        error_log("ExamModel->createQuestion: Execute failed: " . $stmt->error);
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
        if (!$stmt) { error_log("ExamModel->updateQuestion: Prepare failed: " . $this->conn->error); throw new Exception("Prepare failed: " . $this->conn->error); }
        $stmt->bind_param("ssi", $question_text, $answer, $question_id);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        }
        error_log("ExamModel->updateQuestion: Execute failed: " . $stmt->error);
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
        if (!$stmt) { error_log("ExamModel->deleteQuestion: Prepare failed: " . $this->conn->error); throw new Exception("Prepare failed: " . $this->conn->error); }
        $stmt->bind_param("i", $question_id);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        }
        error_log("ExamModel->deleteQuestion: Execute failed: " . $stmt->error);
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
        if (!$stmt) { error_log("ExamModel->createChoice: Prepare failed: " . $this->conn->error); throw new Exception("Prepare failed: " . $this->conn->error); }
        $stmt->bind_param("is", $question_id, $choice_text);
        if ($stmt->execute()) {
            $new_id = $stmt->insert_id;
            $stmt->close();
            return $new_id;
        }
        error_log("ExamModel->createChoice: Execute failed: " . $stmt->error);
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
        if (!$stmt) { error_log("ExamModel->updateChoice: Prepare failed: " . $this->conn->error); throw new Exception("Prepare failed: " . $this->conn->error); }
        $stmt->bind_param("si", $choice_text, $choice_id);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        }
        error_log("ExamModel->updateChoice: Execute failed: " . $stmt->error);
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
        if (!$stmt) { error_log("ExamModel->deleteChoice: Prepare failed: " . $this->conn->error); throw new Exception("Prepare failed: " . $this->conn->error); }
        $stmt->bind_param("i", $choice_id);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        }
        error_log("ExamModel->deleteChoice: Execute failed: " . $stmt->error);
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
        if (!is_numeric($exam_id) || $exam_id <= 0) {
            error_log("ExamModel->getQuestionIdsForExam: Invalid exam_id provided: " . var_export($exam_id, true));
            return [];
        }
        $query = "SELECT question_id FROM " . $this->questions_table . " WHERE exam_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) { error_log("ExamModel->getQuestionIdsForExam: Prepare failed: " . $this->conn->error); throw new Exception("Prepare failed: " . $this->conn->error); }
        $stmt->bind_param("i", $exam_id);
        if (!$stmt->execute()) {
            error_log("ExamModel->getQuestionIdsForExam: Execute failed: " . $stmt->error);
            $stmt->close();
            return [];
        }
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
        if (!is_numeric($question_id) || $question_id <= 0) {
            error_log("ExamModel->getChoiceIdsForQuestion: Invalid question_id provided: " . var_export($question_id, true));
            return [];
        }
        $query = "SELECT choice_id FROM " . $this->choices_table . " WHERE question_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) { error_log("ExamModel->getChoiceIdsForQuestion: Prepare failed: " . $this->conn->error); throw new Exception("Prepare failed: " . $this->conn->error); }
        $stmt->bind_param("i", $question_id);
        if (!$stmt->execute()) {
            error_log("ExamModel->getChoiceIdsForQuestion: Execute failed: " . $stmt->error);
            $stmt->close();
            return [];
        }
        $result = $stmt->get_result();
        $ids = [];
        while ($row = $result->fetch_assoc()) {
            $ids[] = (int)$row['choice_id'];
        }
        $stmt->close();
        return $ids;
    }

    /**
     * Fetches all exams from the database.
     *
     * @return array An array of all exam records, or an empty array if none found.
     */
    public function getAllExams() {
        $query = "SELECT exam_id, title, instruction, year, section, code FROM " . $this->exams_table . " ORDER BY title ASC";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("ExamModel->getAllExams: Prepare failed: (" . $this->conn->errno . ") " . $this->conn->error);
            return [];
        }
        if (!$stmt->execute()) {
            error_log("ExamModel->getAllExams: Execute failed: (" . $stmt->errno . ") " . $stmt->error);
            $stmt->close();
            return [];
        }
        $result = $stmt->get_result();

        $exams = [];
        while ($row = $result->fetch_assoc()) {
            $exams[] = $row;
        }
        $stmt->close();
        return $exams;
    }

    /**
     * Deletes an exam and its associated questions and choices.
     * This method assumes proper foreign key constraints with ON DELETE CASCADE
     * are set up in the database for questions (referencing exams) and choices (referencing questions).
     * If not, you'd need to manually delete from those tables first within this method.
     *
     * @param int $exam_id The ID of the exam to delete.
     * @return bool True on success, false on failure.
     */
    public function deleteExam($exam_id) {
        if (!is_numeric($exam_id) || $exam_id <= 0) {
            error_log("ExamModel->deleteExam: Invalid exam_id provided for deletion: " . var_export($exam_id, true));
            return false;
        }

        // Deleting the exam record. Questions and Choices should cascade delete if your DB is set up.
        // The API already handles student_exam_attempts separately.
        $query = "DELETE FROM " . $this->exams_table . " WHERE exam_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("ExamModel->deleteExam: Prepare failed: (" . $this->conn->errno . ") " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("i", $exam_id);
        $success = $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $error = $stmt->error;
        $stmt->close();

        if ($success && $affected_rows > 0) {
            return true;
        } elseif ($affected_rows === 0) {
            error_log("ExamModel->deleteExam: No exam found with ID: " . $exam_id);
            return false; // Exam not found
        } else {
            error_log("ExamModel->deleteExam: Execute failed for ID " . $exam_id . ": " . $error);
            return false; // Deletion failed for other reasons
        }
    }
}
