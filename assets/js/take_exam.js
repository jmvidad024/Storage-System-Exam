document.addEventListener('DOMContentLoaded', async function() {
    const examDataContainer = document.getElementById('exam_data_container');
    const examId = examDataContainer.dataset.examId;
    const userId = examDataContainer.dataset.userId;
    const attemptId = examDataContainer.dataset.attemptId; // Get attemptId

    const examTitleElem = document.getElementById('exam_title');
    const examInstructionElem = document.getElementById('exam_instruction');
    const examDetailsElem = document.getElementById('exam_details');
    const examQuestionsDiv = document.getElementById('exam_questions');
    const submitExamBtn = document.getElementById('submit_exam_btn');
    const examMessageArea = document.getElementById('exam_message_area');
    const examForm = document.getElementById('exam_form');

    // State variable to track if the exam has already been submitted (manual or auto)
    let examSubmitted = false;
    let tabSwitchCount = 0;
    const maxTabSwitchesAllowed = 0; // Set to 0 for immediate submission and 0 score

    if (!examId || !userId || !attemptId) {
        displayMessage('error', 'Missing exam, user, or attempt ID. Cannot load exam.');
        return;
    }

    // --- Tab/Window Focus Tracking ---
    function handleVisibilityChange() {
        if (examSubmitted) return; // Do nothing if exam is already submitted

        if (document.hidden) {
            // User has switched tabs, minimized the window, or switched to another application
            tabSwitchCount++;
            console.warn(`Window hidden. Tab switch count: ${tabSwitchCount}`);

            if (tabSwitchCount > maxTabSwitchesAllowed) {
                // Ensure this only triggers once
                if (!examSubmitted) {
                    alert("You have switched away from the exam window too many times. Your exam will now be submitted and graded as zero.");
                    sendFormData(true); // Pass true to indicate it's an auto-submission
                    examSubmitted = true; // Mark exam as submitted
                    // Optionally disable further interaction here
                    examForm.style.pointerEvents = 'none'; // Disable form interaction
                    submitExamBtn.disabled = true;
                    examQuestionsDiv.innerHTML = '<h1>Exam Submitted Due to Tab Switching. Score: 0</h1><p>Please contact your instructor for further details.</p>';
                }
            } else if (maxTabSwitchesAllowed > 0) { // Only show warning if more than 0 allowed
                displayMessage('warning', `Warning: Switching away from the exam window is not allowed. You have ${maxTabSwitchesAllowed - tabSwitchCount} more attempt(s) before your exam is automatically submitted and scored zero.`);
            }
        } else {
            // User has returned to the exam window
            console.log("Window is now visible.");
            // Optionally clear warnings if the user returns before exceeding limit
            if (examMessageArea.classList.contains('warning')) {
                examMessageArea.style.display = 'none';
            }
        }
    }

    // This event is also useful but `visibilitychange` is generally more precise for 'tab out' scenarios
    function handleBlur() {
        if (examSubmitted) return;
        console.log("Window lost focus (blur event).");
        // We'll primarily rely on `visibilitychange` for the actual penalty,
        // but this logs when the window loses focus for any reason.
    }

    function handleFocus() {
        if (examSubmitted) return;
        console.log("Window gained focus (focus event).");
    }

    // Add event listeners for visibility and focus changes
    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('blur', handleBlur);
    window.addEventListener('focus', handleFocus);


    // --- Fetch Exam Details ---
    async function fetchExamDetails() {
        examQuestionsDiv.innerHTML = '<p class="loading-message">Fetching questions...</p>';
        examMessageArea.style.display = 'none';

        try {
            const response = await fetch(`../api/exam_details.php?exam_id=${examId}`);
            const data = await response.json();

            if (response.ok && data.status === 'success' && data.exam) {
                const exam = data.exam;
                examTitleElem.textContent = exam.title;
                examInstructionElem.textContent = `Instructions: ${exam.instruction}`;
                examDetailsElem.textContent = `Code: ${exam.code} | Year: ${exam.year} | Section: ${exam.section}`;

                renderQuestions(exam.questions);
                submitExamBtn.style.display = 'block'; // Show submit button
            } else {
                throw new Error(data.message || `Failed to load exam: HTTP Status ${response.status}`);
            }
        } catch (error) {
            console.error('Error fetching exam details:', error);
            displayMessage('error', `Error loading exam: ${error.message}. Please try again.`);
            examTitleElem.textContent = "Error Loading Exam";
            examInstructionElem.textContent = "";
            examDetailsElem.textContent = "";
            examQuestionsDiv.innerHTML = ''; // Clear loading message
            submitExamBtn.style.display = 'none'; // Hide submit button on error
        }
    }

    // --- Render Questions (no changes needed here from previous version) ---
    function renderQuestions(questions) {
        examQuestionsDiv.innerHTML = ''; // Clear loading message

        if (questions.length === 0) {
            examQuestionsDiv.innerHTML = '<p class="no-questions-message">No questions found for this exam.</p>';
            submitExamBtn.style.display = 'none'; // No questions, no submit
            return;
        }

        questions.forEach((q, index) => {
            const questionBlock = document.createElement('div');
            questionBlock.classList.add('question-block');
            questionBlock.dataset.questionId = q.question_id;

            let choicesHtml = '';
            if (q.choices && q.choices.length > 0) {
                // Assuming multiple choice for now (radio buttons)
                choicesHtml = q.choices.map(c => `
                    <div class="choice-item">
                        <input type="radio" id="q${q.question_id}_c${c.choice_id}" name="question_${q.question_id}" value="${c.choice_text}" required>
                        <label for="q${q.question_id}_c${c.choice_id}">${c.choice_text}</label>
                    </div>
                `).join('');
            } else {
                // If no choices, assume text input (e.g., fill-in-the-blank)
                choicesHtml = `
                    <div class="choice-item">
                        <input type="text" name="question_${q.question_id}" placeholder="Your answer" required>
                    </div>
                `;
            }

            questionBlock.innerHTML = `
                <h3>Question ${index + 1}:</h3>
                <p class="question-text">${q.question_text}</p>
                <div class="choices-container">
                    ${choicesHtml}
                </div>
            `;
            examQuestionsDiv.appendChild(questionBlock);
        });
    }

    // --- Handle Form Submission (Updated for new API and Redirect) ---
    examForm.addEventListener('submit', async function(event) {
        event.preventDefault();

        if (examSubmitted) {
            displayMessage('info', 'Exam already submitted.');
            return;
        }

        // Basic client-side validation for all required fields
        const requiredInputs = examForm.querySelectorAll('[required]');
        let allFieldsFilled = true;
        requiredInputs.forEach(input => {
            if (input.type === 'radio') {
                const groupName = input.name;
                const anyCheckedInGroup = examForm.querySelector(`input[name="${groupName}"]:checked`);
                if (!anyCheckedInGroup) {
                    allFieldsFilled = false;
                }
            } else if (!input.value.trim()) {
                allFieldsFilled = false;
            }
        });

        if (!allFieldsFilled) {
            displayMessage('error', 'Please answer all questions before submitting.');
            return;
        }

        submitExamBtn.disabled = true;
        submitExamBtn.textContent = 'Submitting...';
        displayMessage('loading', 'Submitting your exam for grading...');

        sendFormData(false); // Pass false for a manual submission
    });

    // --- Message Display Helper (added a 'warning' type) ---
    function displayMessage(type, message) {
        examMessageArea.style.display = 'block';
        examMessageArea.className = 'message-area'; // Reset classes
        if (type === 'success') {
            examMessageArea.classList.add('success');
        } else if (type === 'error') {
            examMessageArea.classList.add('error');
        } else if (type === 'loading') {
            examMessageArea.classList.add('loading'); // Custom class for loading message
        } else if (type === 'warning') { // New warning type
            examMessageArea.classList.add('warning');
        }
        examMessageArea.innerHTML = `<p>${message}</p>`;
    }

    // Function to send form data (modified to handle auto-submit)
    // Function to send form data (modified to handle auto-submit)
async function sendFormData(isAutoSubmit = false) {
    if (examSubmitted) return; // Prevent multiple submissions

    let answers = {}; // Initialize answers object

    if (isAutoSubmit) {
        // If it's an auto-submit due to a tab switch,
        // send an empty answers object to ensure a score of 0
        // and prevent recording any partially filled answers.
        answers = {}; // Explicitly empty
        console.log("Auto-submitting: sending empty answers payload.");
    } else {
        // Normal manual submission: gather all answers
        const questionContainers = document.querySelectorAll('.question-block');
        questionContainers.forEach(qContainer => {
            const questionId = qContainer.dataset.questionId;
            const radioButtons = qContainer.querySelectorAll(`input[name="question_${questionId}"][type="radio"]`);
            if (radioButtons.length > 0) {
                const selected = qContainer.querySelector(`input[name="question_${questionId}"]:checked`);
                if (selected) {
                    answers[questionId] = selected.value;
                } else {
                    answers[questionId] = ""; // Record unanswered MCQs as empty
                }
            } else {
                const textInput = qContainer.querySelector(`input[name="question_${questionId}"][type="text"]`);
                if (textInput) {
                    answers[questionId] = textInput.value.trim();
                } else {
                    answers[questionId] = ""; // Record unanswered text as empty
                }
            }
        });
        console.log("Manual submission: sending gathered answers payload.");
    }

    try {
        const response = await fetch('../api/submit_exam.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                exam_id: examId,
                user_id: userId,
                attempt_id: attemptId,
                answers: answers, // This will be empty if isAutoSubmit is true
                is_auto_submit: isAutoSubmit // Send the flag to the server
            })
        });

        const data = await response.json();

        if (response.ok && data.status === 'success') {
            examSubmitted = true; // Mark as submitted
            if (isAutoSubmit) {
                displayMessage('error', 'Exam automatically submitted. Your score is 0 due to switching away from the exam window.');
            } else {
                displayMessage('success', data.message || 'Exam submitted successfully!');
            }
            // Redirect to the new results page, passing the attempt_id
            setTimeout(() => {
                window.location.href = `viewExamResult.php?attempt_id=${attemptId}`;
            }, 2000);
        } else {
            throw new Error(data.message || `Submission failed: HTTP Status ${response.status}`);
        }
    } catch (error) {
        console.error('Error submitting exam:', error);
        displayMessage('error', `Failed to submit exam: ${error.message}. Please try again.`);
        submitExamBtn.disabled = false;
        submitExamBtn.textContent = 'Submit Exam';
        examSubmitted = false; // Reset if submission failed, might allow another attempt
    }
}

    // Initial fetch of exam details
    fetchExamDetails();
});