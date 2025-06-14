document.addEventListener('DOMContentLoaded', async function() {
    const get_exam_form = document.getElementById("get_exam");
    const examCodeInput = document.getElementById("exam_code_input");
    const singleExamResultDiv = document.getElementById("single_exam_result"); // Changed ID for clarity
    const loadingIndicator = document.getElementById("loading_indicator");
    const allExamsTableContainer = document.getElementById("all_exams_table_container"); // New container for the table
    const allExamsHeading = document.getElementById('all_exams_heading'); // NEW: Reference to the H2 heading

    const deleteConfirmationModal = document.getElementById('deleteConfirmationModal');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
    const actionMessageArea = document.getElementById('action_message_area');

    let examIdToDelete = null; // To store the exam_id when delete is clicked

    // Helper function to display messages
    function displayActionMessage(type, message) {
        actionMessageArea.classList.remove('hidden', 'success', 'error', 'loading');
        actionMessageArea.textContent = message;
        if (type === 'success') {
            actionMessageArea.classList.add('success');
        } else if (type === 'error') {
            actionMessageArea.classList.add('error');
        } else if (type === 'loading') { // Added loading type
            actionMessageArea.classList.add('loading');
        }
        actionMessageArea.style.display = 'block';
        setTimeout(() => {
            actionMessageArea.style.display = 'none';
            actionMessageArea.textContent = '';
        }, 5000);
    }

    // Function to render the single exam details (from search by code)
    function renderSingleExamDetails(examData) {
        singleExamResultDiv.innerHTML = ''; // Clear previous content
        singleExamResultDiv.classList.remove('placeholder-text'); // Remove placeholder class if present

        let questionsHtml = '';
        if (examData.questions && examData.questions.length > 0) {
            questionsHtml = examData.questions.map((q, i) => `
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

        singleExamResultDiv.innerHTML = `
            <h2>${examData.title || 'N/A'} (ID: ${examData.exam_id})</h2>
            <p><strong>Instruction:</strong> ${examData.instruction || 'No instructions provided.'}</p>
            <p><strong>Year:</strong> ${examData.year || 'N/A'} | <strong>Section:</strong> ${examData.section || 'N/A'} | <strong>Code:</strong> ${examData.code || 'N/A'}</p>
            <hr>
            <h3>Questions:</h3>
            ${questionsHtml}
            <div class="exam-actions">
                <a href="editExam.php?exam_id=${examData.exam_id}" class="btn btn-edit">Edit Exam</a>
                <button class="btn btn-delete delete-exam-btn" data-exam-id="${examData.exam_id}">Delete Exam</button>
                <button class="btn btn-secondary" id="clear_search_btn">Clear Search</button> <!-- NEW BUTTON -->
            </div>
        `;

        // Add event listeners to the action buttons for the single exam view
        singleExamResultDiv.querySelector('.delete-exam-btn').addEventListener('click', function() {
            examIdToDelete = this.dataset.examId;
            deleteConfirmationModal.classList.add('show');
        });

        // NEW: Add event listener for Clear Search button
        const clearSearchBtn = singleExamResultDiv.querySelector('#clear_search_btn');
        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', () => {
                singleExamResultDiv.innerHTML = '<p class="placeholder-text">Enter an exam code above to view its details, or see the list below.</p>';
                singleExamResultDiv.classList.add('placeholder-text'); // Re-add placeholder class
                allExamsTableContainer.style.display = 'block'; // Show all exams table
                if (allExamsHeading) allExamsHeading.style.display = 'block'; // Show heading
                examCodeInput.value = ''; // Clear search input
                // No need to re-fetch all exams here, as it's already done on initial load and after deletion.
            });
        }
    }

    // Function to fetch and render all exams in a table
    async function fetchAllExamsAndRenderTable() {
        allExamsTableContainer.innerHTML = '<p class="loading-indicator">Loading all exams...</p>'; // Show loading
        allExamsTableContainer.style.display = 'block'; // Ensure visible when fetching all
        if (allExamsHeading) allExamsHeading.style.display = 'block'; // Ensure heading visible

        try {
            const response = await fetch('../api/exam.php'); // No parameters, so it fetches all exams
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || `HTTP error! Status: ${response.status}`);
            }
            const data = await response.json();

            if (data.status === 'success' && data.exams) {
                if (data.exams.length > 0) {
                    let tableHtml = `
                        <div class="table-responsive">
                            <table class="exam-table">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Instructions</th>
                                        <th>Year</th>
                                        <th>Section</th>
                                        <th>Code</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;
                    data.exams.forEach(exam => {
                        tableHtml += `
                            <tr data-exam-id="${exam.exam_id}">
                                <td data-label="Title">${exam.title}</td>
                                <td data-label="Instructions">${exam.instruction}</td>
                                <td data-label="Year">${exam.year}</td>
                                <td data-label="Section">${exam.section}</td>
                                <td data-label="Code">${exam.code}</td>
                                <td class="actions" data-label="Actions">
                                    <a href="editExam.php?exam_id=${exam.exam_id}" class="btn btn-action btn-edit">Edit</a>
                                    <button class="btn btn-action btn-delete delete-exam-btn" data-exam-id="${exam.exam_id}">Delete</button>
                                </td>
                            </tr>
                        `;
                    });
                    tableHtml += `
                                </tbody>
                            </table>
                        </div>
                    `;
                    allExamsTableContainer.innerHTML = tableHtml;

                    // Attach event listeners to the new delete buttons in the table
                    allExamsTableContainer.querySelectorAll('.delete-exam-btn').forEach(button => {
                        button.addEventListener('click', function() {
                            examIdToDelete = this.dataset.examId;
                            deleteConfirmationModal.classList.add('show');
                        });
                    });
                } else {
                    allExamsTableContainer.innerHTML = '<p class="no-exams-message">No exams created yet. Click "Create New Exam" to get started!</p>';
                }
            } else {
                displayActionMessage('error', data.message || 'Failed to load exams.');
                allExamsTableContainer.innerHTML = '<p class="error-message">Error loading exams.</p>';
            }
        } catch (error) {
            console.error("Error fetching all exams:", error);
            displayActionMessage('error', `Failed to load exams: ${error.message}.`);
            allExamsTableContainer.innerHTML = '<p class="error-message">Could not load exams. Please try again later.</p>';
        }
    }

    // Event listener for "Find Exam by Code" form
    get_exam_form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const code = examCodeInput.value.trim();

        if (!code) {
            singleExamResultDiv.innerHTML = '<p class="error-message">Please enter an exam code.</p>';
            // Ensure table is visible if search input is empty
            allExamsTableContainer.style.display = 'block';
            if (allExamsHeading) allExamsHeading.style.display = 'block';
            return;
        }

        loadingIndicator.style.display = 'block';
        singleExamResultDiv.innerHTML = ''; // Clear previous content
        actionMessageArea.style.display = 'none'; // Hide any previous action messages

        try {
            const response = await fetch(`../api/exam.php?code=${encodeURIComponent(code)}`);
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || `HTTP error! Status: ${response.status}`);
            }
            const data = await response.json();

            loadingIndicator.style.display = 'none';

            if (data.status === "error" || !data.exam) {
                // If search fails, ensure the full table is visible again
                singleExamResultDiv.innerHTML = `<p class="error-message">${data.message || "Exam not found."}</p>`;
                allExamsTableContainer.style.display = 'block';
                if (allExamsHeading) allExamsHeading.style.display = 'block';
                return;
            }

            // Render details of the found exam
            renderSingleExamDetails(data.exam);

            // NEW: Hide the "All Existing Exams" section when a single exam is found
            allExamsTableContainer.style.display = 'none';
            if (allExamsHeading) allExamsHeading.style.display = 'none';

        } catch (err) {
            loadingIndicator.style.display = 'none';
            console.error("Fetch error for single exam:", err);
            singleExamResultDiv.innerHTML = `<p class="error-message">${err.message || "Error loading exam details. Please try again."}</p>`;
            // Ensure table is visible on fetch error
            allExamsTableContainer.style.display = 'block';
            if (allExamsHeading) allExamsHeading.style.display = 'block';
        }
    });

    // Delete Confirmation Modal Event Listeners
    confirmDeleteBtn.addEventListener('click', async () => {
        deleteConfirmationModal.classList.remove('show'); // Hide modal
        if (!examIdToDelete) {
            displayActionMessage('error', 'No exam selected for deletion.');
            return;
        }

        displayActionMessage('loading', 'Deleting exam...'); // Show loading message

        try {
            const response = await fetch(`../api/exam.php?exam_id=${examIdToDelete}`, { // Correct API endpoint
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            const data = await response.json();

            if (response.ok && data.status === 'success') {
                displayActionMessage('success', data.message || 'Exam deleted successfully!');
                
                // Clear single exam result if the deleted exam was displayed there
                singleExamResultDiv.innerHTML = '<p class="placeholder-text">Enter an exam code above to view its details, or see the list below.</p>';
                singleExamResultDiv.classList.add('placeholder-text'); // Re-add placeholder class
                
                // Ensure the full table is visible again after deletion
                await fetchAllExamsAndRenderTable(); // This will also make them visible
                
                examIdToDelete = null; // Clear stored ID

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
        examIdToDelete = null; // Clear the ID if cancelled
    });

    // Close modal if clicked outside
    window.addEventListener('click', function(event) {
        if (event.target === deleteConfirmationModal) {
            deleteConfirmationModal.classList.remove('show');
            examIdToDelete = null; // Clear the ID if clicked outside
        }
    });

    // Initial load: Fetch and render all exams table
    fetchAllExamsAndRenderTable();

    // Check if there are messages from PHP on initial load
    const phpMessageArea = document.querySelector('.exam-management-section .message-area');
    if (phpMessageArea && phpMessageArea.textContent.trim() !== '') {
        // A message was rendered by PHP, display it via JS function for consistent styling/timeout
        const type = phpMessageArea.classList.contains('success') ? 'success' : 'error';
        const message = phpMessageArea.textContent.trim();
        phpMessageArea.style.display = 'none'; // Hide the PHP-rendered one
        displayActionMessage(type, message);
    }
});
