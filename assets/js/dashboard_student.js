document.addEventListener('DOMContentLoaded', function() {
    // --- Student Exam Loading Logic ---
    const examListContainer = document.getElementById('exam_list_container');

    if (examListContainer) { // Only run this if the student panel is visible
        const userId = examListContainer.dataset.userId;
        const studentYear = examListContainer.dataset.studentYear;
        const studentSection = examListContainer.dataset.studentSection;
        const examGrid = examListContainer.querySelector('.exam-grid');
        const loadingErrorDiv = examListContainer.querySelector('.exam-loading-error');

        if (userId && studentYear && studentSection) {
            fetchExamsForStudent(userId, studentYear, studentSection);
        } else {
            loadingErrorDiv.classList.add('error');
            loadingErrorDiv.textContent = "Could not load student details (Year/Section missing). Please ensure your profile is complete.";
        }

        async function fetchExamsForStudent(userId, year, section) {
            loadingErrorDiv.textContent = "Loading exams...";
            loadingErrorDiv.classList.remove('error'); // Clear previous error state
            loadingErrorDiv.style.display = 'block'; // Ensure loading message is visible
            examGrid.innerHTML = ''; // Clear any previous content

            try {
                const response = await fetch(`../api/student_exam.php?year=${year}&section=${section}&user_id=${userId}`);
                const data = await response.json();

                if (response.ok && data.status === 'success' && data.exams) {
                    loadingErrorDiv.style.display = 'none'; // Hide loading message if successful
                    
                    if (data.exams.length > 0) {
                        data.exams.forEach(exam => {
                            const examCard = document.createElement('div');
                            examCard.classList.add('exam-card');

                            let actionButtonHtml = '';
                            if (exam.is_completed) {
                                // NEW: Link to view results for completed exams
                                // exam.attempt_id should be returned by student_exams.php now
                                actionButtonHtml = `<a href="viewExamResult.php?attempt_id=${exam.attempt_id}" class="btn-take-exam btn-view-results">View Results</a>`;
                            } else {
                                actionButtonHtml = `<a href="takeExam.php?exam_id=${exam.exam_id}" class="btn-take-exam">Take Exam</a>`;
                            }

                            examCard.innerHTML = `
                                <h3>${exam.title || 'Untitled Exam'}</h3>
                                <p>${exam.instruction || 'No instructions provided.'}</p>
                                <p class="details">Code: ${exam.code || 'N/A'} | Year: ${exam.year || 'N/A'} | Section: ${exam.section || 'N/A'}</p>
                                ${actionButtonHtml}
                            `;
                            examGrid.appendChild(examCard);
                        });
                    } else {
                        loadingErrorDiv.style.display = 'block';
                        loadingErrorDiv.textContent = "No exams currently available for your year and section.";
                        loadingErrorDiv.classList.remove('error'); // Not an error, just no exams
                    }
                } else {
                    throw new Error(data.message || `Failed to load exams: HTTP Status ${response.status}`);
                }
            } catch (error) {
                console.error('Error fetching exams:', error);
                loadingErrorDiv.style.display = 'block';
                loadingErrorDiv.classList.add('error');
                loadingErrorDiv.textContent = `Error loading exams: ${error.message}. Please try again later.`;
                examGrid.innerHTML = ''; // Clear any partial content
            }
        }
    }
});
