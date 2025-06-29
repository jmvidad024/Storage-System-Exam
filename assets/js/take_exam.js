document.addEventListener('DOMContentLoaded', async function() {
    const examDataContainer = document.getElementById('exam_data_container');
    const examId = examDataContainer.dataset.examId;
    const userId = examDataContainer.dataset.userId;
    const attemptId = examDataContainer.dataset.attemptId;
    const examDurationMinutes = parseInt(examDataContainer.dataset.durationMinutes, 10);

    const examTitleElem = document.getElementById('exam_title');
    const examInstructionElem = document.getElementById('exam_instruction');
    const examDetailsElem = document.getElementById('exam_details');
    const examQuestionsDiv = document.getElementById('exam_questions');
    const submitExamBtn = document.getElementById('submit_exam_btn');
    const examMessageArea = document.getElementById('exam_message_area');
    const examForm = document.getElementById('exam_form');
    const examTimerElem = document.getElementById('exam_timer');

    const isMobile = /Android|iPhone|iPad|iPod|Windows Phone/i.test(navigator.userAgent);

    let examSubmitted = false;
    let tabSwitchCount = 0;
    const maxTabSwitchesAllowed = 0; // Set to 0 for immediate submission and 0 score

    let timeRemainingSeconds;
    let timerInterval;

    if (!examId || !userId || !attemptId || isNaN(examDurationMinutes)) {
        displayMessage('error', 'Missing exam, user, attempt ID, or invalid exam duration. Cannot load exam.');
        return;
    }
    //SCREEN SIZE
    const MIN_DESKTOP_WIDTH = screen.width * 0.8;
    const MIN_DESKTOP_HEIGHT = screen.height * 0.8;

    if (!isMobile) {
        const currentWidth = window.innerWidth;
        const currentHeight = window.innerHeight;

        if (currentWidth < MIN_DESKTOP_WIDTH || currentHeight < MIN_DESKTOP_HEIGHT) {
            displayMessage('error', `Your window size (${currentWidth}x${currentHeight}) is too small. Please maximize your browser window or increase its size to at least ${MIN_DESKTOP_WIDTH}x${MIN_DESKTOP_HEIGHT} to start the exam.`);
            examQuestionsDiv.innerHTML = '<p class="error-message">Please adjust your window size to proceed.</p>';
            submitExamBtn.style.display = 'none';
            examForm.style.pointerEvents = 'none';
            return;
        }
        requestFullscreenMode();
    } else {
        console.log("Mobile device detected. Skipping desktop-specific checks like initial resize and forced fullscreen.");
    }

    // --- Timer Functions ---
    function formatTime(seconds) {
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;
        return `${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
    }

    function updateTimerDisplay() {
        if (examTimerElem) {
            examTimerElem.textContent = `Time Remaining: ${formatTime(timeRemainingSeconds)}`;
            if (timeRemainingSeconds <= 60) {
                examTimerElem.style.color = '#dc3545';
                examTimerElem.style.animation = 'blink 1s infinite';
            } else {
                examTimerElem.style.color = '#d9534f';
                examTimerElem.style.animation = 'none';
            }
        }
    }

    function startTimer() {
        if (examSubmitted) return;

        timeRemainingSeconds = examDurationMinutes * 60;
        examTimerElem.style.display = 'block';

        updateTimerDisplay();

        timerInterval = setInterval(() => {
            timeRemainingSeconds--;
            updateTimerDisplay();

            if (timeRemainingSeconds <= 0) {
                clearInterval(timerInterval);
                if (!examSubmitted) {
                    alert("Time's up! Your exam will now be submitted.");
                    // *** KEY CHANGE HERE ***
                    // Pass 'true' for is_auto_submit (proctoring violation) only for cheating.
                    // For time-out, we want it to be graded normally, so pass 'false' for is_proctoring_violation.
                    sendFormData(false, true); // sendFormData(isProctoringViolation, isTimeOut)
                    examSubmitted = true;
                    examForm.style.pointerEvents = 'none';
                    submitExamBtn.disabled = true;
                    examQuestionsDiv.innerHTML = '<h1>Exam Auto-Submitted Due to Time Limit.</h1><p>Redirecting to results...</p>';
                }
            }
        }, 1000);
    }

    // --- Proctoring Functions (handleVisibilityChange, handleBlur, handleFocus, handleResize, handleFullscreenChange, requestFullscreenMode) ---
    function handleVisibilityChange() {
        if (examSubmitted) return;

        if (document.hidden) {
            tabSwitchCount++;
            console.warn(`Window hidden. Tab switch count: ${tabSwitchCount}`);

            if (tabSwitchCount > maxTabSwitchesAllowed) {
                if (!examSubmitted) {
                    alert("You have switched away from the exam window too many times. Your exam will now be submitted and graded as zero.");
                    // This is a proctoring violation, so send true for is_auto_submit
                    sendFormData(true); // isProctoringViolation = true
                    examSubmitted = true;
                    examForm.style.pointerEvents = 'none';
                    submitExamBtn.disabled = true;
                    examQuestionsDiv.innerHTML = '<h1>Exam Submitted Due to Tab Switching. Score: 0</h1><p>Please contact your instructor for further details.</p>';
                }
            } else if (maxTabSwitchesAllowed > 0) {
                displayMessage('warning', `Warning: Switching away from the exam window is not allowed. You have ${maxTabSwitchesAllowed - tabSwitchCount} more attempt(s) before your exam is automatically submitted and scored zero.`);
            }
        } else {
            console.log("Window is now visible.");
            if (examMessageArea.classList.contains('warning') && tabSwitchCount <= maxTabSwitchesAllowed) {
                examMessageArea.style.display = 'none';
            }
        }
    }

    function handleBlur() {
        if (examSubmitted) return;
        console.log("Window lost focus (blur event).");
    }

    function handleFocus() {
        if (examSubmitted) return;
        console.log("Window gained focus (focus event).");
    }

    function handleResize() {
        if (examSubmitted || isMobile) return;

        const width = window.innerWidth;
        const height = window.innerHeight;

        if (width < MIN_DESKTOP_WIDTH || height < MIN_DESKTOP_HEIGHT) {
            alert("Window resized too small! Split-screen or minimizing is not allowed. Auto-submitting.");
            // This is a proctoring violation, so send true for is_auto_submit
            sendFormData(true); // isProctoringViolation = true
            examSubmitted = true;
            examForm.style.pointerEvents = 'none';
            submitExamBtn.disabled = true;
            examQuestionsDiv.innerHTML = '<h1>Exam Auto-Submitted Due to Resizing.</h1><p>Please contact your instructor for further details.</p>';
        }
    }

    function handleFullscreenChange() {
        if (examSubmitted || isMobile) return;

        if (!document.fullscreenElement) {
            alert("You exited fullscreen mode! Auto-submitting the exam.");
            // This is a proctoring violation, so send true for is_auto_submit
            sendFormData(true); // isProctoringViolation = true
            examSubmitted = true;
            examForm.style.pointerEvents = 'none';
            submitExamBtn.disabled = true;
            examQuestionsDiv.innerHTML = '<h1>Exam Auto-Submitted Due to Fullscreen Exit.</h1><p>Please contact your instructor for further details.</p>';
        }
    }

    function requestFullscreenMode() {
        const elem = document.documentElement;
        if (elem.requestFullscreen) {
            elem.requestFullscreen().catch(() => {
                console.warn("Fullscreen denied by user.");
                displayMessage('warning', 'Warning: Please enter fullscreen mode for the exam. Exiting fullscreen will result in auto-submission.');
            });
        }
    }

    if (!examQuestionsDiv.innerHTML.includes('Please adjust your window size')) {
        document.addEventListener('visibilitychange', handleVisibilityChange);
        window.addEventListener('blur', handleBlur);
        window.addEventListener('focus', handleFocus);
        window.addEventListener('resize', handleResize);
        document.addEventListener('fullscreenchange', handleFullscreenChange);
    }

    // --- Fetch Exam Details ---
    async function fetchExamDetails() {
        if (examQuestionsDiv.innerHTML.includes('Please adjust your window size')) {
            return;
        }

        examQuestionsDiv.innerHTML = '<p class="loading-message">Fetching questions...</p>';
        examMessageArea.style.display = 'none';

        try {
            const response = await fetch(`../api/exam_details.php?exam_id=${examId}`);
            const data = await response.json();

            if (response.ok && data.status === 'success' && data.exam) {
                const exam = data.exam;
                renderQuestions(exam.questions);
                submitExamBtn.style.display = 'block';

                if (examDurationMinutes > 0) {
                    startTimer();
                } else {
                    console.log("Exam duration is 0 or not set. Timer will not run.");
                }

            } else {
                throw new Error(data.message || `Failed to load exam: HTTP Status ${response.status}`);
            }
        } catch (error) {
            console.error('Error fetching exam details:', error);
            displayMessage('error', `Error loading exam questions: ${error.message}. Please try again.`);
            examQuestionsDiv.innerHTML = '<p class="error-message">Failed to load exam questions.</p>';
            submitExamBtn.style.display = 'none';
            examForm.style.pointerEvents = 'none';
        }
    }

    // --- Render Questions ---
    function renderQuestions(questions) {
        examQuestionsDiv.innerHTML = '';

        if (questions.length === 0) {
            examQuestionsDiv.innerHTML = '<p class="no-questions-message">No questions found for this exam.</p>';
            submitExamBtn.style.display = 'none';
            return;
        }

        questions.forEach((q, index) => {
            const questionBlock = document.createElement('div');
            questionBlock.classList.add('question-block');
            questionBlock.dataset.questionId = q.question_id;

            let choicesHtml = '';
            if (q.choices && q.choices.length > 0) {
                choicesHtml = q.choices.map(c => `
                    <div class="choice-item">
                        <input type="radio" id="q${q.question_id}_c${c.choice_id}" name="question_${q.question_id}" value="${c.choice_text}" required>
                        <label for="q${q.question_id}_c${c.choice_id}">${c.choice_text}</label>
                    </div>
                `).join('');
            } else {
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

    // --- Handle Form Submission ---
    examForm.addEventListener('submit', async function(event) {
        event.preventDefault();

        if (examSubmitted) {
            displayMessage('info', 'Exam already submitted.');
            return;
        }

        clearInterval(timerInterval); // Stop the timer immediately on manual submission attempt

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
            // Re-enable timer if submission is blocked by validation
            if (examDurationMinutes > 0 && timeRemainingSeconds > 0) {
                timerInterval = setInterval(updateTimerDisplay, 1000); // Corrected function name
            }
            return;
        }

        submitExamBtn.disabled = true;
        submitExamBtn.textContent = 'Submitting...';
        displayMessage('loading', 'Submitting your exam for grading...');

        sendFormData(false); // Manual submission: not a proctoring violation
    });

    // --- Message Display Helper ---
    function displayMessage(type, message) {
        examMessageArea.style.display = 'block';
        examMessageArea.className = 'message-area';
        if (type === 'success') {
            examMessageArea.classList.add('success');
        } else if (type === 'error') {
            examMessageArea.classList.add('error');
        } else if (type === 'loading') {
            examMessageArea.classList.add('loading');
        } else if (type === 'warning') {
            examMessageArea.classList.add('warning');
        }
        examMessageArea.innerHTML = `<p>${message}</p>`;
    }

    // Function to send form data (modified to clear timer on success/failure)
    // Parameter 'isProctoringViolation' means it's an auto-submit due to cheating.
    // Parameter 'isTimeOut' means it's an auto-submit because time ran out (not cheating).
    async function sendFormData(isProctoringViolation = false, isTimeOut = false) {
        if (examSubmitted) return;

        clearInterval(timerInterval);
        if (examTimerElem) examTimerElem.style.animation = 'none';

        let answers = {};

        // If it's a proctoring violation (cheating), or if it's a timeout,
        // we'll send the answers collected so far.
        // The backend should handle the scoring logic.
        const questionContainers = document.querySelectorAll('.question-block');
        questionContainers.forEach(qContainer => {
            const questionId = qContainer.dataset.questionId;
            const radioButtons = qContainer.querySelectorAll(`input[name="question_${questionId}"][type="radio"]`);
            if (radioButtons.length > 0) {
                const selected = qContainer.querySelector(`input[name="question_${questionId}"]:checked`);
                if (selected) {
                    answers[questionId] = selected.value;
                } else {
                    answers[questionId] = ""; // Include unanswered questions for consistency
                }
            } else {
                const textInput = qContainer.querySelector(`input[name="question_${questionId}"][type="text"]`);
                if (textInput) {
                    answers[questionId] = textInput.value.trim();
                } else {
                    answers[questionId] = ""; // Include unanswered questions for consistency
                }
            }
        });

        if (isProctoringViolation) {
            console.log("Proctoring violation: sending gathered answers payload for 0 score.");
        } else if (isTimeOut) {
            console.log("Time-out auto-submit: sending gathered answers payload for normal grading.");
        } else {
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
                    answers: answers,
                    is_auto_submit: isProctoringViolation, // This flag specifically for proctoring violations
                    is_time_out: isTimeOut // NEW: Add a separate flag for time-out
                })
            });

            const data = await response.json();

            if (response.ok && data.status === 'success') {
                examSubmitted = true;
                if (isProctoringViolation) {
                    displayMessage('error', 'Exam automatically submitted. Your score is 0 due to a proctoring violation.');
                } else if (isTimeOut) {
                    displayMessage('success', data.message || 'Time ran out! Your exam has been submitted for grading.');
                } else {
                    displayMessage('success', data.message || 'Exam submitted successfully!');
                }
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
            // If submission fails, restart timer if it was running and time remains
            // Only restart if not already a time-out auto-submit (to avoid infinite loops)
            if (examDurationMinutes > 0 && timeRemainingSeconds > 0 && !isTimeOut) {
                timerInterval = setInterval(updateTimerDisplay, 1000); // Corrected function name
            }
        }
    }

    // Initial fetch of exam details (moved after initial checks)
    fetchExamDetails();
});