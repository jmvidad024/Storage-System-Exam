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

    public function splitCourseMajor($combined_course) {
        if (strpos($combined_course, ':') !== false) {
            $parts = explode(':', $combined_course, 2);
            return [
                'course' => trim($parts[0]),
                'major' => trim($parts[1])
            ];
        }
        return [
            'course' => trim($combined_course),
            'major' => null
        ];
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

        $query = "SELECT exam_id, title, instruction, year, section, code, course, duration_minutes FROM " . $this->exams_table . " WHERE exam_id = ? LIMIT 1";
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

        if (isset($exam['course'])) {
            $exam['course_details'] = $this->splitCourseMajor($exam['course']);
        }

        // Fetch questions for the exam
        $questions_query = "SELECT question_id, question_text, answer FROM " . $this->questions_table . " WHERE exam_id = ?";
        $stmt_q = $this->conn->prepare($questions_query);
        if (!$stmt_q) {
            error_log("ExamModel->getExamById: Questions query prepare failed: (" . $this->conn->errno . ") " . $this->conn->error);
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
            if (!isset($question['question_id']) || !is_numeric($question['question_id'])) {
                error_log("ExamModel->getExamById: Question fetched without valid question_id, skipping choices for: " . var_export($question, true));
                $question['choices'] = [];
                $questions[] = $question;
                continue;
            }

            $choices_query = "SELECT choice_id, choice_text FROM " . $this->choices_table . " WHERE question_id = ?";
            $stmt_c = $this->conn->prepare($choices_query);
            if (!$stmt_c) {
                error_log("ExamModel->getExamById: Choices query prepare failed for QID " . $question['question_id'] . ": (" . $this->conn->errno . ") " . $this->conn->error);
                $question['choices'] = [];
                $questions[] = $question;
                continue;
            }
            $q_id_int = (int)$question['question_id'];
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

        $query = "SELECT exam_id FROM " . $this->exams_table . " WHERE code = ? LIMIT 1"; // Only fetch exam_id
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

        // Now, reuse getExamById to get full details (questions, choices, and duration)
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
     * @param string $course
     * @param string|null $major
     * @param int $duration_minutes // NEW PARAMETER
     * @return bool True on success, false on failure.
     */
    public function updateExamMain($exam_id, $title, $instruction, $year, $section, $code, $course, $major = null, $duration_minutes) {
        $full_course = $course;
        if (!empty($major)) {
            $full_course = $course . ' : ' . $major;
        }

        $query = "UPDATE " . $this->exams_table . "
                    SET title = ?,
                        instruction = ?,
                        year = ?,
                        section = ?,
                        code = ?,
                        course = ?,
                        duration_minutes = ?
                    WHERE exam_id = ?";

        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("ExamModel->updateExamMain: Prepare failed: " . $this->conn->error);
            throw new Exception("Prepare failed: " . $this->conn->error);
        }

        $stmt->bind_param("ssisssii",
            $title,
            $instruction,
            $year,
            $section,
            $code,
            $full_course,
            $duration_minutes,
            $exam_id
        );

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
        $query = "SELECT exam_id, title, instruction, year, section, code, course, duration_minutes FROM " . $this->exams_table . " ORDER BY title ASC";
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
     * Fetches exams associated with a specific course.
     *
     * @param string $course The combined course string (e.g., "BSIT : Web Development").
     * @return array An array of exam records for the given course, or an empty array if none found.
     */
    public function getExamsByCourse($course) {
        if (empty($course)) {
            error_log("ExamModel->getExamsByCourse: Empty course provided.");
            return [];
        }
        $query = "SELECT exam_id, title, instruction, year, section, code, course, duration_minutes FROM " . $this->exams_table . " WHERE course = ? ORDER BY title ASC";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("ExamModel->getExamsByCourse: Prepare failed: (" . $this->conn->errno . ") " . $this->conn->error);
            return [];
        }
        $stmt->bind_param("s", $course);
        if (!$stmt->execute()) {
            error_log("ExamModel->getExamsByCourse: Execute failed: (" . $stmt->errno . ") " . $stmt->error);
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
     * student_exam_attempts deletion is handled in the API layer or by another cascading FK.
     *
     * @param int $exam_id The ID of the exam to delete.
     * @return bool True on success, false on failure.
     */
    public function deleteExam($exam_id) {
        if (!is_numeric($exam_id) || $exam_id <= 0) {
            error_log("ExamModel->deleteExam: Invalid exam_id provided for deletion: " . var_export($exam_id, true));
            return false;
        }

        // It's safer to ensure all dependent data is removed.
        // Even if you have CASCADE set up, explicitly managing it within a transaction
        // ensures consistency and better error handling, especially for student_exam_attempts.
        // Since the API now explicitly deletes student_attempts, we focus on exam, questions, choices.
        $this->conn->begin_transaction();
        try {
            // Get all question_ids related to this exam
            $question_ids = [];
            $stmt = $this->conn->prepare("SELECT question_id FROM " . $this->questions_table . " WHERE exam_id = ?");
            if (!$stmt) throw new Exception("Prepare select questions failed: " . $this->conn->error);
            $stmt->bind_param("i", $exam_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while($row = $result->fetch_assoc()) {
                $question_ids[] = $row['question_id'];
            }
            $stmt->close();

            // Delete choices related to these questions
            if (!empty($question_ids)) {
                $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
                $stmt = $this->conn->prepare("DELETE FROM " . $this->choices_table . " WHERE question_id IN ($placeholders)");
                if (!$stmt) throw new Exception("Prepare delete choices failed: " . $this->conn->error);
                $types = str_repeat('i', count($question_ids));
                $stmt->bind_param($types, ...$question_ids);
                if (!$stmt->execute()) throw new Exception("Execute delete choices failed: " . $stmt->error);
                $stmt->close();
            }

            // Delete questions for the exam
            $stmt = $this->conn->prepare("DELETE FROM " . $this->questions_table . " WHERE exam_id = ?");
            if (!$stmt) throw new Exception("Prepare delete questions failed: " . $this->conn->error);
            $stmt->bind_param("i", $exam_id);
            if (!$stmt->execute()) throw new Exception("Execute delete questions failed: " . $stmt->error);
            $stmt->close();

            // Finally, delete the exam itself
            $query = "DELETE FROM " . $this->exams_table . " WHERE exam_id = ?";
            $stmt = $this->conn->prepare($query);
            if (!$stmt) throw new Exception("Prepare delete exam failed: " . $this->conn->error);
            $stmt->bind_param("i", $exam_id);
            if (!$stmt->execute()) throw new Exception("Execute delete exam failed: " . $stmt->error);
            $affected_rows = $stmt->affected_rows;
            $stmt->close();

            if ($affected_rows > 0) {
                $this->conn->commit();
                return true;
            } else {
                $this->conn->rollback(); // No exam found to delete
                error_log("ExamModel->deleteExam: No exam found with ID: " . $exam_id);
                return false;
            }
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Error deleting exam (ID: $exam_id) in ExamModel: " . $e->getMessage());
            return false;
        }
    }
}