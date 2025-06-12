const get_exam_form = document.getElementById("get_exam");
const examCodeInput = document.getElementById("exam_code_input");
const resultDiv = document.getElementById("exam_result");
const loadingIndicator = document.getElementById("loading_indicator");

// NEW: Elements for delete confirmation modal and messages
const deleteConfirmationModal = document.getElementById('deleteConfirmationModal');
const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
const actionMessageArea = document.getElementById('action_message_area');

let currentExamId = null; // To store the exam_id when delete is clicked

// Helper function to display messages
function displayActionMessage(type, message) {
    actionMessageArea.classList.remove('hidden', 'success', 'error'); // Reset classes
    actionMessageArea.textContent = message;
    if (type === 'success') {
        actionMessageArea.classList.add('success');
    } else if (type === 'error') {
        actionMessageArea.classList.add('error');
    }
    actionMessageArea.style.display = 'block';
    setTimeout(() => {
        actionMessageArea.style.display = 'none'; // Hide message after a few seconds
    }, 5000);
}

get_exam_form.addEventListener('submit', (e) => {
    e.preventDefault();

    const code = examCodeInput.value.trim();

    if (!code) {
        resultDiv.innerHTML = '<p class="error-message">Please enter an exam code.</p>';
        return;
    }

    loadingIndicator.style.display = 'block';
    resultDiv.innerHTML = ''; // Clear previous content
    actionMessageArea.style.display = 'none'; // Hide any previous action messages

    fetch(`/Storage-System/api/exam.php?code=${encodeURIComponent(code)}`)
    .then(res => {
        if (!res.ok) {
            return res.json().then(errorData => {
                throw new Error(errorData.message || `HTTP error! Status: ${res.status}`);
            }).catch(() => {
                throw new Error(`HTTP error! Status: ${res.status}`);
            });
        }
        return res.json();
    })
    .then(data => {
        loadingIndicator.style.display = 'none';

        if (data.status === "error") {
            throw new Error(data.message || "Unknown error from server.");
        }

        // Store the exam_id globally for delete operations
        currentExamId = data.exam_id; 

        // Render result to the page
        console.log("Exam data:", data);
        if (resultDiv) {
            let questionsHtml = '';
            if (data.questions && data.questions.length > 0) {
                questionsHtml = data.questions.map((q, i) => `
                    <div class="question-block">
                        <h3>Question ${i + 1}: ${q.question_text || 'No question text'}</h3>
                        ${q.choices && q.choices.length > 0 ? `
                            <ul>
                                ${q.choices.map(c => `<li>${c.choice_text}</li>`).join('')}
                            </ul>` : '<p>No choices provided.</p>'}
                        <p class="answer"><strong>Correct Answer:</strong> ${q.answer || 'N/A'}</p>
                    </div>
                `).join('');
            } else {
                questionsHtml = '<p class="placeholder-text">No questions found for this exam.</p>';
            }

            resultDiv.innerHTML = `
                <h2>${data.title || 'N/A'}</h2>
                <p><strong>Instruction:</strong> ${data.instruction || 'No instructions provided.'}</p>
                <p><strong>Year:</strong> ${data.year || 'N/A'} | <strong>Section:</strong> ${data.section || 'N/A'}</p>
                <hr>
                ${questionsHtml}
                <div class="exam-actions">
                    <button id="edit_exam_btn" class="btn btn-edit">Edit Exam</button>
                    <button id="delete_exam_btn" class="btn btn-delete">Delete Exam</button>
                </div>
            `;

            // NEW: Add event listeners to the action buttons
            document.getElementById('edit_exam_btn').addEventListener('click', () => {
                window.location.href = `editExam.php?exam_id=${currentExamId}`;
            });

            document.getElementById('delete_exam_btn').addEventListener('click', () => {
                deleteConfirmationModal.classList.add('show'); // Show modal
            });
        }
    })
    .catch(err => {
        loadingIndicator.style.display = 'none';
        console.error("Fetch error:", err);
        if (resultDiv) {
            resultDiv.innerHTML = `<p class="error-message">${err.message || "Error loading exam details. Please try again."}</p>`;
        }
    });
});

// NEW: Delete Confirmation Modal Event Listeners
confirmDeleteBtn.addEventListener('click', async () => {
    deleteConfirmationModal.classList.remove('show'); // Hide modal
    if (!currentExamId) {
        displayActionMessage('error', 'No exam selected for deletion.');
        return;
    }

    displayActionMessage('loading', 'Deleting exam...'); // Show loading message

    try {
        const response = await fetch(`/Storage-System/api/exam.php?exam_id=${currentExamId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            }
            // For DELETE, body is often empty or contains only ID, but can contain JSON
            // body: JSON.stringify({ exam_id: currentExamId }) // If your API expects it in body
        });
        const data = await response.json();

        if (response.ok && data.status === 'success') {
            displayActionMessage('success', data.message || 'Exam deleted successfully!');
            resultDiv.innerHTML = '<p class="placeholder-text">Exam deleted. Enter another exam code to view details.</p>'; // Clear content
            currentExamId = null; // Clear stored ID
        } else {
            throw new Error(data.message || `Deletion failed: HTTP Status ${response.status}`);
        }
    } catch (error) {
        console.error('Delete error:', error);
        displayActionMessage('error', `Failed to delete exam: ${error.message}.`);
    }
});

cancelDeleteBtn.addEventListener('click', () => {
    deleteConfirmationModal.classList.remove('show'); // Hide modal
});

// Close modal if clicked outside (optional, but good UX)
window.addEventListener('click', function(event) {
    if (event.target === deleteConfirmationModal) {
        deleteConfirmationModal.classList.remove('show');
    }
});
