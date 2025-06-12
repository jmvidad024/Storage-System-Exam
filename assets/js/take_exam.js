document.addEventListener('DOMContentLoaded', async function() {
            const examDataContainer = document.getElementById('exam_data_container');
            const examId = examDataContainer.dataset.examId;
            const userId = examDataContainer.dataset.userId;
            const attemptId = examDataContainer.dataset.attemptId; // NEW: Get attemptId

            const examTitleElem = document.getElementById('exam_title');
            const examInstructionElem = document.getElementById('exam_instruction');
            const examDetailsElem = document.getElementById('exam_details');
            const examQuestionsDiv = document.getElementById('exam_questions');
            const submitExamBtn = document.getElementById('submit_exam_btn');
            const examMessageArea = document.getElementById('exam_message_area');
            const examForm = document.getElementById('exam_form');

            if (!examId || !userId || !attemptId) { // NEW: Check for attemptId
                displayMessage('error', 'Missing exam, user, or attempt ID. Cannot load exam.');
                return;
            }

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

                const answers = {};
                const formElements = examForm.elements;
                for (let i = 0; i < formElements.length; i++) {
                    const element = formElements[i];
                    if (element.name && element.name.startsWith('question_')) {
                        const questionId = element.name.replace('question_', '');
                        if (element.type === 'radio') {
                            if (element.checked) {
                                answers[questionId] = element.value;
                            }
                        } else {
                            answers[questionId] = element.value.trim();
                        }
                    }
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
                            attempt_id: attemptId, // Pass attempt ID
                            answers: answers
                        })
                    });

                    const data = await response.json();

                    if (response.ok && data.status === 'success') {
                        displayMessage('success', data.message || 'Exam submitted successfully!');
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
                }
            });

            // --- Message Display Helper (no changes needed) ---
            function displayMessage(type, message) {
                examMessageArea.style.display = 'block';
                examMessageArea.className = 'message-area'; // Reset classes
                if (type === 'success') {
                    examMessageArea.classList.add('success');
                } else if (type === 'error') {
                    examMessageArea.classList.add('error');
                } else if (type === 'loading') {
                    examMessageArea.classList.add('loading'); // Custom class for loading message
                }
                examMessageArea.innerHTML = `<p>${message}</p>`;
            }

            // Initial fetch of exam details
            fetchExamDetails();
        });