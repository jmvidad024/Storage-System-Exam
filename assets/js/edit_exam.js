document.addEventListener('DOMContentLoaded', function() {
const loggedInUserRole = window.loggedInUserRole || '';
    const loggedInUserSubject = window.loggedInUserSubject || '';

    const examEditForm = document.getElementById('exam_edit_form');
    const examIdHidden = document.getElementById('exam_id_hidden');
    const titleInput = document.getElementById('title');
    const instructionInput = document.getElementById('instruction');
    const yearSelect = document.getElementById('year');
    const sectionSelect = document.getElementById('section');
    const codeInput = document.getElementById('code');
    const durationInput = document.getElementById('duration_minutes'); // NEW: Reference to duration input
    const questionsContainer = document.getElementById('questions_container');
    const addQuestionBtn = document.getElementById('add_question_btn');
    const saveExamBtn = document.getElementById('save_exam_btn');
    const courseInput = document.getElementById('course'); // This refers to the course SELECT element
    const subjectInput = document.getElementById('subject');

    const courseSelect = document.getElementById('course');
    const majorGroup = document.getElementById('major-group');
    const majorSelect = document.getElementById('major');
    const courseMajorDb = document.getElementById('course_major_db');


    if (loggedInUserRole === 'faculty') {
        yearSelect.setAttribute('disabled', true);
        yearSelect.title = "You cannot change this subject.";

        sectionSelect.setAttribute('disabled', true);
        sectionSelect.title = "You cannot change this subject.";

        subjectInput.setAttribute('disabled', true);
        subjectInput.title = "You cannot change this subject.";

        courseSelect.setAttribute('disabled', true);
        courseSelect.title = "You cannot change this course.";

        majorSelect.setAttribute('disabled', true);
        majorSelect.title = "You cannot change this major.";
    }

    // Define which courses have majors
    const majorsByCourse = {
        'Education': ['Science', 'Math', 'English', 'History'],
        // Add other courses with majors if needed
    };

    // Handle course selection changes
    courseSelect.addEventListener('change', function() {
        const selectedCourse = this.value;
        
        // Hide major group if no course or course doesn't have majors
        if (!selectedCourse || !majorsByCourse[selectedCourse]) {
            majorGroup.style.display = 'none';
            majorSelect.innerHTML = '<option value="">Select Major</option>'; // Clear major options
            courseMajorDb.value = selectedCourse; // Store just the course
            return;
        }
        
        // Show major group and populate options
        majorGroup.style.display = 'block';
        majorSelect.innerHTML = '<option value="">Select Major</option>';
        
        majorsByCourse[selectedCourse].forEach(function(major) {
            const option = document.createElement('option');
            option.value = major;
            option.textContent = major;
            majorSelect.appendChild(option);
        });
        // If there was a preselected major, try to set it
        // This logic correctly handles pre-selection *after* options are added
        if (majorSelect.dataset.preselectedMajor) {
            majorSelect.value = majorSelect.dataset.preselectedMajor;
            // Clear preselected major after attempting to set it
            delete majorSelect.dataset.preselectedMajor;
        }
        // Manually trigger change to ensure hidden field is updated if major was preselected
        majorSelect.dispatchEvent(new Event('change'));
    });

    // Update hidden field when major is selected
    majorSelect.addEventListener('change', function() {
        const selectedCourse = courseSelect.value;
        const selectedMajor = this.value;
        
        if (selectedMajor) {
            courseMajorDb.value = `${selectedCourse} : ${selectedMajor}`;
        } else {
            courseMajorDb.value = selectedCourse;
        }
    });

    // Elements for delete attempts modal (modal is now triggered by successful save)
    const deleteAttemptsConfirmationModal = document.getElementById('deleteAttemptsConfirmationModal');
    const confirmDeleteAttemptsBtn = document.getElementById('confirmDeleteAttemptsBtn');
    const cancelDeleteAttemptsBtn = document.getElementById('cancelDeleteAttemptsBtn');

    const messageArea = document.getElementById('action_message_area') || document.createElement('div');
    if (!document.getElementById('action_message_area')) { 
        messageArea.id = 'action_message_area';
        messageArea.classList.add('message-area', 'hidden');
        document.querySelector('.container').appendChild(messageArea);
    }
    
    // Get initial data from the data attribute (ensure it's correctly passed in PHP)
    const examInitialDataElement = document.getElementById('exam_initial_data');
    let initialExamData = null;
    if (examInitialDataElement && examInitialDataElement.dataset.initialExamData) {
        try {
            initialExamData = JSON.parse(examInitialDataElement.dataset.initialExamData);
        } catch (e) {
            console.error("Error parsing initial exam data:", e);
            displayMessage('error', 'Failed to load initial exam data. Please check the console for details.');
        }
    }

    let questionCounter = 0; // To keep track of new questions

    // Helper to display messages
    function displayMessage(type, message) {
        messageArea.classList.remove('hidden', 'success', 'error', 'loading'); // Clear all state classes
        messageArea.textContent = message;
        if (type === 'success') {
            messageArea.classList.add('success');
        } else if (type === 'error') {
            messageArea.classList.add('error');
        } else if (type === 'loading') { // Added for explicit loading state
            messageArea.classList.add('loading');
        }
        messageArea.style.display = 'block';
        setTimeout(() => {
            messageArea.style.display = 'none';
        }, 5000);
    }

    // Function to add a new question block
    function addQuestionBlock(question = {}) {
        questionCounter++;
        const qId = question.question_id || `new_${questionCounter}`; // Use existing ID or a temp ID for new ones
        const questionText = question.question_text || '';
        const answer = question.answer || '';
        const choices = question.choices || [];

        const questionBlock = document.createElement('div');
        questionBlock.classList.add('question-block');
        questionBlock.dataset.questionId = qId; // Store actual or temp ID
        questionBlock.innerHTML = `
            <button type="button" class="remove-question-btn" data-question-id="${qId}">Remove Question</button>
            <div class="form-group">
                <label for="q_${qId}_text">Question Text</label>
                <input type="text" id="q_${qId}_text" name="question_${qId}_text" placeholder="Enter Question" value="${questionText}" required>
            </div>
            <div class="form-group">
                <label for="q_${qId}_answer">Correct Answer</label>
                <input type="text" id="q_${qId}_answer" name="question_${qId}_answer" placeholder="Enter Right Answer" value="${answer}" required>
            </div>
            <div class="choices-section" id="q_${qId}_choices">
                <h4>Choices</h4>
                <!-- Choices will be appended here -->
            </div>
            <button type="button" class="add-option-btn" data-question-id="${qId}">Add Option</button>
        `;
        questionsContainer.appendChild(questionBlock);

        const choicesSection = questionBlock.querySelector(`#q_${qId}_choices`);
        const addOptionBtn = questionBlock.querySelector(`.add-option-btn`);

        // Function to add a new choice input
        function addChoiceInput(choice = {}) {
            const cId = choice.choice_id || `new_c_${qId}_${choicesSection.children.length}`; // Make new choice ID unique to its question
            const choiceText = choice.choice_text || '';

            const choiceItem = document.createElement('div');
            choiceItem.classList.add('choice-item');
            choiceItem.dataset.choiceId = cId;
            choiceItem.innerHTML = `
                <input type="text" name="choice_${qId}_${cId}_text" placeholder="Enter Choice" value="${choiceText}" required>
                <button type="button" class="remove-choice-btn" data-choice-id="${cId}">Remove</button>
            `;
            choicesSection.appendChild(choiceItem);

            // Add event listener for removing choice
            choiceItem.querySelector('.remove-choice-btn').addEventListener('click', function() {
                choiceItem.remove();
            });
        }

        // Add existing choices
        choices.forEach(addChoiceInput);

        // Event listener for "Add Option" button
        addOptionBtn.addEventListener('click', () => addChoiceInput());

        // Event listener for "Remove Question" button
        questionBlock.querySelector('.remove-question-btn').addEventListener('click', function() {
            questionBlock.remove();
        });
    }

    // --- Populate form if initialExamData exists (editing mode) ---
    if (initialExamData) {
        examIdHidden.value = initialExamData.exam_id;
        titleInput.value = initialExamData.title;
        instructionInput.value = initialExamData.instruction;
        yearSelect.value = initialExamData.year;
        sectionSelect.value = initialExamData.section;
        codeInput.value = initialExamData.code;
        subjectInput.value = initialExamData.subject;
        durationInput.value = initialExamData.duration_minutes; // Set duration value

        const fullCourseMajor = initialExamData.course || '';
        const [coursePart, majorPart] = fullCourseMajor.split(':').map(part => part.trim());

        courseInput.value = coursePart;
        courseMajorDb.value = fullCourseMajor;

        // Store majorPart in a dataset attribute before dispatching the change event
        // This allows the 'change' event listener on courseSelect to pick it up later.
        if (majorPart) {
            majorSelect.dataset.preselectedMajor = majorPart;
        }

        // Trigger initial course change event to correctly set up major dropdown based on loaded data
        courseSelect.dispatchEvent(new Event('change'));

        if (initialExamData.questions && initialExamData.questions.length > 0) {
            initialExamData.questions.forEach(q => addQuestionBlock(q));
        }
    } else {
        let errorMessage = 'Exam not found or invalid ID. Cannot edit.';
        if (!examIdHidden.value) {
            errorMessage += ' No exam ID was passed to the page URL.';
        } else {
            errorMessage += ` Exam ID '${examIdHidden.value}' might not exist in the database or there was a server error loading data.`;
        }
        displayMessage('error', errorMessage);
        saveExamBtn.disabled = true;
        addQuestionBtn.disabled = true;
    }

    // --- Add Question Button Listener ---
    addQuestionBtn.addEventListener('click', () => addQuestionBlock());

    // --- Form Submission (PUT request) ---
    examEditForm.addEventListener('submit', async function(event) {
        event.preventDefault();

        saveExamBtn.disabled = true;
        saveExamBtn.textContent = 'Saving...';
        displayMessage('loading', 'Saving changes...');

        const examId = examIdHidden.value;
        const updatedExamData = {
            exam_id: parseInt(examId),
            title: titleInput.value.trim(),
            instruction: instructionInput.value.trim(),
            year: yearSelect.value,
            section: sectionSelect.value,
            code: codeInput.value.trim(),
            course: courseMajorDb.value.trim(),
            subject: subjectInput.value.trim(),
            duration_minutes: parseInt(durationInput.value), // Include duration
            questions: []
        };

        // Collect questions and choices
        document.querySelectorAll('.question-block').forEach(qBlock => {
            const questionTextElement = qBlock.querySelector('input[name^="question_"][name$="_text"]');
            const answerElement = qBlock.querySelector('input[name^="question_"][name$="_answer"]');

            if (!questionTextElement || !answerElement || questionTextElement.value.trim() === '') {
                return;
            }

            const qId = qBlock.dataset.questionId.startsWith('new_') ? null : parseInt(qBlock.dataset.questionId);
            const questionText = questionTextElement.value.trim();
            const answer = answerElement.value.trim();

            const choices = [];
            qBlock.querySelectorAll('.choice-item').forEach(cItem => {
                const choiceTextElement = cItem.querySelector('input[type="text"]');
                if (choiceTextElement && choiceTextElement.value.trim() !== '') {
                    const cId = cItem.dataset.choiceId.startsWith('new_c_') ? null : parseInt(cItem.dataset.choiceId);
                    const choiceText = choiceTextElement.value.trim();
                    choices.push({ choice_id: cId, choice_text: choiceText });
                }
            });

            updatedExamData.questions.push({
                question_id: qId,
                question_text: questionText,
                answer: answer,
                choices: choices
            });
        });

        try {
            const response = await fetch(`../api/exam.php?exam_id=${examId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(updatedExamData)
            });

            const data = await response.json();

            if (response.ok && data.status === 'success') {
                displayMessage('success', data.message || 'Exam updated successfully!');
                saveExamBtn.disabled = false;
                saveExamBtn.textContent = 'Save Exam Changes';

                // Show confirmation modal after successful save
                deleteAttemptsConfirmationModal.classList.add('show'); 

            } else {
                throw new Error(data.message || `Update failed: HTTP Status ${response.status}`);
            }
        } catch (error) {
            console.error('Error updating exam:', error);
            displayMessage('error', `Failed to update exam: ${error.message}.`);
            saveExamBtn.disabled = false;
            saveExamBtn.textContent = 'Save Exam Changes';
        }
    });

    confirmDeleteAttemptsBtn.addEventListener('click', async () => {
        deleteAttemptsConfirmationModal.classList.remove('show');
        const examId = examIdHidden.value;
        if (!examId) {
            displayMessage('error', 'Exam ID missing for attempt deletion.');
            return;
        }

        displayMessage('loading', 'Deleting all attempts for this exam...');

        try {
            const response = await fetch(`../api/delete_exam_attempt.php?exam_id=${examId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            const data = await response.json();

            if (response.ok && data.status === 'success') {
                displayMessage('success', data.message || 'All attempts successfully deleted.');
                setTimeout(() => {
                    window.location.href = `getExam.php?message=attempts_deleted_for_exam`;
                }, 2000);
            } else {
                throw new Error(data.message || `Deletion failed: HTTP Status ${response.status}`);
            }
        } catch (error) {
            console.error('Error deleting attempts:', error);
            displayMessage('error', `Failed to delete attempts: ${error.message}.`);
        }
    });

    cancelDeleteAttemptsBtn.addEventListener('click', () => {
        deleteAttemptsConfirmationModal.classList.remove('show');
        setTimeout(() => {
            window.location.href = `getExam.php?message=exam_updated_attempts_kept`;
        }, 1000);
    });

    // Close modals if clicked outside
    window.addEventListener('click', function(event) {
        if (event.target === deleteAttemptsConfirmationModal) {
            deleteAttemptsConfirmationModal.classList.remove('show');
            setTimeout(() => {
                window.location.href = `getExam.php?message=exam_updated_modal_dismissed`;
            }, 1000);
        }
    });
});
