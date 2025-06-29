document.addEventListener('DOMContentLoaded', function() {
    // --- Student Exam Loading Logic ---
    const examListContainer = document.getElementById('exam_list_container');

    if (examListContainer) { // Only run this if the student panel is visible
        const userId = examListContainer.dataset.userId;
        const studentYear = examListContainer.dataset.studentYear;
        const studentSection = examListContainer.dataset.studentSection;
        const studentCourseMajor = examListContainer.dataset.studentCourseMajor; // Use this data attribute

        const examGrid = examListContainer.querySelector('.exam-grid');
        const loadingErrorDiv = examListContainer.querySelector('.exam-loading-error');

        // Check if essential data attributes are present
        if (userId && studentYear && studentSection && studentCourseMajor) {
            fetchExamsForStudent(userId, studentYear, studentSection, studentCourseMajor);
        } else {
            loadingErrorDiv.classList.add('error');
            loadingErrorDiv.textContent = "Could not load student details (Year/Section/Course/User ID missing). Please ensure your profile is complete.";
        }

        async function fetchExamsForStudent(userId, year, section, courseMajor) {
            loadingErrorDiv.textContent = "Loading exams...";
            loadingErrorDiv.classList.remove('error'); // Clear previous error state
            loadingErrorDiv.classList.remove('info-message'); // Clear any previous info message state
            loadingErrorDiv.style.display = 'block'; // Ensure loading message is visible
            examGrid.innerHTML = ''; // Clear any previous content

            try {
                // Pass courseMajor to the API if your backend needs it to filter exams
                const response = await fetch(`../api/student_exam.php?year=${year}&section=${section}&course_major=${encodeURIComponent(courseMajor)}&user_id=${userId}`);
                const data = await response.json();

                if (response.ok && data.status === 'success' && data.exams) {
                    if (data.exams.length > 0) {
                        loadingErrorDiv.style.display = 'none'; // Hide loading message if successful exams are found

                        data.exams.forEach(exam => {
                            const examCard = document.createElement('div');
                            examCard.classList.add('exam-card'); // **Crucial: Add the 'exam-card' class**

                            let actionButtonHtml = '';
                            if (exam.is_completed) {
                                // Use 'status-completed' and 'btn-view-results' for styling completed exams
                                actionButtonHtml = `
                                    <a href="viewExamResult.php?attempt_id=${exam.attempt_id}" class="status-completed btn-view-results">
                                        View Results
                                    </a>`;
                            } else {
                                // Use 'btn-take-exam' for styling available exams
                                actionButtonHtml = `
                                    <a href="takeExam.php?exam_id=${exam.exam_id}" class="btn-take-exam">
                                        Take Exam
                                    </a>`;
                            }

                            // Ensure all fields are present or default to 'N/A'
                            const examTitle = exam.title || 'Untitled Exam';
                            const examInstruction = exam.instruction || 'No instructions provided.';
                            const examCode = exam.code || 'N/A';
                            const examYear = exam.year || 'N/A';
                            const examSection = exam.section || 'N/A';
                            const examSubject = exam.subject || 'N/A'; // Assuming subject is available

                            examCard.innerHTML = `
                                <h3>${examTitle}</h3>
                                <p>${examInstruction}</p>
                                <p class="details">
                                    Code: ${examCode} | Year: ${examYear} | Section: ${examSection} | Subject: ${examSubject}
                                </p>
                                ${actionButtonHtml}
                            `;
                            examGrid.appendChild(examCard);
                        });
                    } else {
                        // If no exams are available, show an info message
                        loadingErrorDiv.style.display = 'block';
                        loadingErrorDiv.textContent = "No exams currently available for your class. Please check back later!";
                        loadingErrorDiv.classList.remove('error'); // It's not an error, just no data
                        loadingErrorDiv.classList.add('info-message'); // Style as an info message
                    }
                } else {
                    // Handle API errors or unsuccessful status
                    throw new Error(data.message || `Failed to load exams: HTTP Status ${response.status}`);
                }
            } catch (error) {
                console.error('Error fetching exams:', error);
                loadingErrorDiv.style.display = 'block';
                loadingErrorDiv.classList.add('error'); // Style as an error message
                loadingErrorDiv.classList.remove('info-message'); // Remove info if it was there
                loadingErrorDiv.textContent = `Error loading exams: ${error.message}. Please try again later.`;
                examGrid.innerHTML = ''; // Clear any partial content on error
            }
        }
    }
});