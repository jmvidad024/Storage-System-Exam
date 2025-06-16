document.addEventListener('DOMContentLoaded', async function() {
    // Dropdown logic (assuming it's in dashboard.js or integrated elsewhere, but keeping here for completeness)
    const dropdownButton = document.getElementById('dropdown-button');
    const dropdownMenu = document.querySelector('.dropdown-menu');

    if (dropdownButton && dropdownMenu) {
        dropdownButton.addEventListener('click', function() {
            dropdownMenu.classList.toggle('show');
        });

        window.addEventListener('click', function(event) {
            if (!event.target.matches('.dropdown-toggle') && !event.target.closest('.dropdown-menu')) {
                if (dropdownMenu.classList.contains('show')) {
                    dropdownMenu.classList.remove('show');
                }
            }
        });
    }

    const courseListContainer = document.getElementById('course_list_container');
    const loadingIndicator = courseListContainer.querySelector('.loading-indicator');
    const errorMessageDiv = courseListContainer.querySelector('.error-message');

    // References for the student list modal
    const studentListModal = document.getElementById('studentListModal');
    const closeStudentModalBtn = document.getElementById('closeStudentModalBtn');
    const studentListTableBody = document.getElementById('studentListTableBody');
    const studentModalLoading = document.getElementById('studentModalLoading');
    const studentModalError = document.getElementById('studentModalError');
    const studentModalTitle = document.getElementById('studentModalTitle');

    // References for the delete student confirmation modal
    const deleteStudentConfirmationModal = document.getElementById('deleteStudentConfirmationModal');
    const confirmDeleteStudentBtn = document.getElementById('confirmDeleteStudentBtn');
    const cancelDeleteStudentBtn = document.getElementById('cancelDeleteStudentBtn');
    const deleteStudentMessage = document.getElementById('deleteStudentMessage');
    let studentIdToDelete = null; // Store ID of student (PK 'id' from students table) to be deleted
    let currentSectionData = { course: null, year: null, section: null }; // To refresh current section after delete

    // References for the edit student modal
    const editStudentModal = document.getElementById('editStudentModal');
    const closeEditStudentModalBtn = document.getElementById('closeEditStudentModalBtn');
    const editStudentForm = document.getElementById('editStudentForm');
    const editStudentIdInput = document.getElementById('editStudentId'); // This will store the PK 'id' of students table
    const editUserIdInput = document.getElementById('editUserId'); // Hidden field for user_id
    const editStudentUsernameInput = document.getElementById('editStudentUsername'); // Username cannot be edited
    const editStudentNameInput = document.getElementById('editStudentName');
    const editStudentEmailInput = document.getElementById('editStudentEmail');
    const editStudentCourseInput = document.getElementById('editStudentCourse');
    const editStudentYearInput = document.getElementById('editStudentYear');
    const editStudentSectionInput = document.getElementById('editStudentSection');
    const editStudentModalMessage = document.getElementById('editStudentModalMessage');


    // Helper function to display messages (reusing from getExam.js principles)
    function displayActionMessage(targetElement, type, message, duration = 5000) {
        targetElement.classList.remove('hidden', 'success', 'error', 'loading');
        targetElement.textContent = message;
        if (type === 'success') {
            targetElement.classList.add('success');
        } else if (type === 'error') {
            targetElement.classList.add('error');
        } else if (type === 'loading') {
            targetElement.classList.add('loading');
        }
        targetElement.style.display = 'block';
        setTimeout(() => {
            targetElement.style.display = 'none';
            targetElement.textContent = '';
        }, duration);
    }

    // Function to fetch and render the main course hierarchy
    async function fetchAndRenderCourses() {
        loadingIndicator.style.display = 'block';
        errorMessageDiv.style.display = 'none';
        errorMessageDiv.textContent = '';
        courseListContainer.querySelector('.course-list')?.remove(); 

        try {
            const response = await fetch('../api/courses.php');
            const data = await response.json();

            loadingIndicator.style.display = 'none';

            if (response.ok && data.status === 'success' && data.data) {
                renderCourses(data.data);
            } else {
                throw new Error(data.message || 'Failed to fetch course data.');
            }
        } catch (error) {
            console.error('Error fetching courses:', error);
            loadingIndicator.style.display = 'none';
            errorMessageDiv.style.display = 'block';
            errorMessageDiv.textContent = `Error loading courses: ${error.message}`;
        }
    }

    // Function to render the course hierarchy UI
    function renderCourses(courses) {
        if (courses.length === 0) {
            courseListContainer.innerHTML = '<p class="no-data-message">No course, year, or section data found from registered students.</p>';
            return;
        }

        const courseList = document.createElement('div');
        courseList.classList.add('course-list');

        courses.forEach(course => {
            const courseBlock = document.createElement('div');
            courseBlock.classList.add('course-block');

            const courseHeader = document.createElement('div');
            courseHeader.classList.add('course-header');
            courseHeader.innerHTML = `<h3>${course.course_name}</h3><span class="toggle-icon">&#9660;</span>`; // Down arrow
            courseBlock.appendChild(courseHeader);

            const yearsContainer = document.createElement('div');
            yearsContainer.classList.add('years-container', 'hidden'); // Hidden by default
            courseBlock.appendChild(yearsContainer);

            courseHeader.addEventListener('click', () => {
                yearsContainer.classList.toggle('hidden');
                courseHeader.querySelector('.toggle-icon').innerHTML = yearsContainer.classList.contains('hidden') ? '&#9660;' : '&#9650;'; // Toggle arrow
            });

            if (course.years && course.years.length > 0) {
                course.years.forEach(year => {
                    const yearBlock = document.createElement('div');
                    yearBlock.classList.add('year-block');

                    const yearHeader = document.createElement('div');
                    yearHeader.classList.add('year-header');
                    yearHeader.innerHTML = `<h4>Year ${year.year_level}</h4><span class="toggle-icon">&#9660;</span>`; // Down arrow
                    yearBlock.appendChild(yearHeader);

                    const sectionsContainer = document.createElement('div');
                    sectionsContainer.classList.add('sections-container', 'hidden'); // Hidden by default
                    yearBlock.appendChild(sectionsContainer);

                    yearHeader.addEventListener('click', () => {
                        sectionsContainer.classList.toggle('hidden');
                        yearHeader.querySelector('.toggle-icon').innerHTML = sectionsContainer.classList.contains('hidden') ? '&#9660;' : '&#9650;'; // Toggle arrow
                    });

                    if (year.sections && year.sections.length > 0) {
                        const sectionList = document.createElement('ul');
                        sectionList.classList.add('section-list');
                        year.sections.forEach(section => {
                            const sectionItem = document.createElement('li');
                            sectionItem.dataset.courseName = course.course_name;
                            sectionItem.dataset.yearLevel = year.year_level;
                            sectionItem.dataset.sectionName = section.section_name;
                            sectionItem.innerHTML = `Section ${section.section_name} <span class="student-count">${section.student_count} students</span>`;
                            
                            sectionItem.style.cursor = 'pointer';
                            sectionItem.classList.add('clickable-section');
                            sectionItem.addEventListener('click', () => {

                                currentSectionData = {
                                    course: course.course_name,
                                    year: year.year_level,
                                    section: section.section_name
                                };
                                showStudentsForSection(
                                    sectionItem.dataset.courseName,
                                    sectionItem.dataset.yearLevel,
                                    sectionItem.dataset.sectionName
                                );
                            });
                            
                            sectionList.appendChild(sectionItem);
                        });
                        sectionsContainer.appendChild(sectionList);
                    } else {
                        sectionsContainer.innerHTML = '<p class="no-data-message">No sections with students for this year.</p>';
                    }
                    yearsContainer.appendChild(yearBlock);
                });
            } else {
                yearsContainer.innerHTML = '<p class="no-data-message">No years with students for this course.</p>';
            }
            courseList.appendChild(courseBlock);
        });

        courseListContainer.appendChild(courseList);
    }

    // Fetches and displays students for a given section in a modal
    async function showStudentsForSection(courseName, yearLevel, sectionName) {
        studentListModal.classList.add('show'); // Show the modal
        studentModalLoading.style.display = 'block'; // Show loading
        studentModalError.style.display = 'none'; // Hide error
        studentListTableBody.innerHTML = ''; // Clear previous student list
        
        studentModalTitle.textContent = `Students in ${courseName}, Year ${yearLevel}, Section ${sectionName}`;

        try {
            const response = await fetch(`../api/students_by_section.php?course=${encodeURIComponent(courseName)}&year=${encodeURIComponent(yearLevel)}&section=${encodeURIComponent(sectionName)}`);
            const data = await response.json();
            studentModalLoading.style.display = 'none'; // Hide loading

            if (response.ok && data.status === 'success' && data.students) {
                if (data.students.length > 0) {
                    data.students.forEach(student => {
                        console.log(student.year_level);
                        const row = document.createElement('tr');
                        // Use student.id (PK from students table) for data-student-id
                        row.innerHTML = `
                            <td data-label="Student ID">${student.student_id || 'N/A'}</td>
                            <td data-label="Username">${student.username || 'N/A'}</td>
                            <td data-label="Full Name">${student.name || 'N/A'}</td>
                            <td data-label="Email">${student.email || 'N/A'}</td>
                            <td data-label="Actions" class="actions">
                                <button class="btn btn-action btn-edit edit-student-btn"
                                    data-student-id="${student.id}"
                                    data-user-id="${student.user_id}"
                                    data-username="${student.username}"
                                    data-name="${student.name}"
                                    data-email="${student.email}"
                                    data-course="${student.course_name}"   data-year="${student.year_level}"      data-section="${student.section_name}">
                                    Edit</button>
                                    <button class="btn btn-action btn-delete delete-student-btn"
                                    data-student-id="${student.id}"
                                    data-username="${student.username}">Delete</button>
                            </td>
                        `;
                        studentListTableBody.appendChild(row);
                    });
                    
                    // Attach event listeners to newly created buttons
                    studentListTableBody.querySelectorAll('.edit-student-btn').forEach(button => {
                        button.addEventListener('click', function() {
                            openEditStudentModal(this.dataset);
                        });
                    });

                    studentListTableBody.querySelectorAll('.delete-student-btn').forEach(button => {
                        button.addEventListener('click', function() {
                            confirmDeleteStudent(this.dataset.studentId, this.dataset.username);
                        });
                    });

                } else {
                    studentListTableBody.innerHTML = '<tr><td colspan="5" class="no-students-message">No students found for this section.</td></tr>';
                }
            } else {
                throw new Error(data.message || 'Failed to fetch student data.');
            }
        } catch (error) {
            console.error('Error fetching students by section:', error);
            studentModalLoading.style.display = 'none';
            studentModalError.style.display = 'block';
            studentModalError.textContent = `Error loading students: ${error.message}`;
            studentListTableBody.innerHTML = '<tr><td colspan="5" class="error-message">Failed to load students.</td></tr>';
        }
    }

    // NEW FUNCTION: Opens the edit student modal and populates form
    function openEditStudentModal(studentData) {
        console.log(studentData);
        
        // Close student list modal first
        studentListModal.classList.remove('show'); 

        // Check if elements are found before attempting to set value
        if (editStudentIdInput) editStudentIdInput.value = studentData.studentId;
        if (editUserIdInput) editUserIdInput.value = studentData.userId;
        if (editStudentUsernameInput) editStudentUsernameInput.value = studentData.username;
        if (editStudentNameInput) editStudentNameInput.value = studentData.name;
        if (editStudentEmailInput) editStudentEmailInput.value = studentData.email;
        if (editStudentCourseInput) editStudentCourseInput.value = studentData.course;
        if (editStudentYearInput) editStudentYearInput.value = studentData.year;
        if (editStudentSectionInput) editStudentSectionInput.value = studentData.section;

        editStudentModalMessage.style.display = 'none'; // Hide any previous messages
        editStudentModal.classList.add('show');
    }

    // NEW FUNCTION: Handles the edit student form submission
    editStudentForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(editStudentForm);
        const studentData = Object.fromEntries(formData.entries());

        // Basic validation (can be expanded)
        if (!studentData.name || !studentData.email || !studentData.course || !studentData.year || !studentData.section) {
            displayActionMessage(editStudentModalMessage, 'error', 'All fields are required.', 3000);
            return;
        }

        displayActionMessage(editStudentModalMessage, 'loading', 'Saving changes...', 0); // Indefinite loading

        try {
            const response = await fetch('../api/update_student.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(studentData)
            });
            const result = await response.json();

            if (response.ok && result.status === 'success') {
                displayActionMessage(editStudentModalMessage, 'success', result.message || 'Student updated successfully!', 3000);
                editStudentModal.classList.remove('show'); // Hide modal
                
                // Refresh the list for the current section
                showStudentsForSection(currentSectionData.course, currentSectionData.year, currentSectionData.section);
                // Also refresh the main course list to update student counts in case of section/course change
                fetchAndRenderCourses();

            } else {
                throw new Error(result.message || 'Failed to update student.');
            }
        } catch (error) {
            console.error('Error updating student:', error);
            displayActionMessage(editStudentModalMessage, 'error', `Update failed: ${error.message}`, 5000);
        }
    });

    // NEW FUNCTION: Confirms student deletion
    function confirmDeleteStudent(studentId, username) {
        studentIdToDelete = studentId; // This is now the PK 'id' from students table
        deleteStudentMessage.textContent = `Are you sure you want to delete the student "${username}"? This action cannot be undone.`;
        deleteStudentConfirmationModal.classList.add('show');
    }

    // NEW FUNCTION: Executes student deletion
    confirmDeleteStudentBtn.addEventListener('click', async () => {
        deleteStudentConfirmationModal.classList.remove('show'); // Hide modal
        if (!studentIdToDelete) {
            displayActionMessage(studentModalError, 'error', 'No student selected for deletion.', 3000);
            return;
        }

        displayActionMessage(studentModalError, 'loading', 'Deleting student...', 0); // Indefinite loading
        studentListModal.classList.remove('show'); // Hide the student list modal temporarily

        try {
            // Send the PK 'id' of the student record to the API
            const response = await fetch(`../api/delete_student.php?student_id=${studentIdToDelete}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            const result = await response.json();

            if (response.ok && result.status === 'success') {
                displayActionMessage(errorMessageDiv, 'success', result.message || 'Student deleted successfully!', 5000); // Use main page message area
                studentIdToDelete = null; // Clear stored ID
                
                // Re-open and refresh the student list for the current section
                showStudentsForSection(currentSectionData.course, currentSectionData.year, currentSectionData.section);
                // Also refresh the main course list to update student counts
                fetchAndRenderCourses();
            } else {
                throw new Error(result.message || `Deletion failed: HTTP Status ${response.status}`);
            }
        } catch (error) {
            console.error('Delete student error:', error);
            displayActionMessage(errorMessageDiv, 'error', `Failed to delete student: ${error.message}`, 5000);
        }
    });

    // NEW: Close edit student modal event listener
    closeEditStudentModalBtn.addEventListener('click', () => {
        editStudentModal.classList.remove('show');
    });

    // NEW: Cancel delete student modal event listener
    cancelDeleteStudentBtn.addEventListener('click', () => {
        deleteStudentConfirmationModal.classList.remove('show');
        studentIdToDelete = null; // Clear the ID if cancelled
        studentListModal.classList.add('show'); // Re-show the student list modal
    });

    // Close student list modal event listener
    closeStudentModalBtn.addEventListener('click', () => {
        studentListModal.classList.remove('show');
    });

    // Close any modal if clicked outside
    window.addEventListener('click', function(event) {
        if (event.target === studentListModal) {
            studentListModal.classList.remove('show');
        }
        if (event.target === deleteStudentConfirmationModal) {
            deleteStudentConfirmationModal.classList.remove('show');
            studentIdToDelete = null; // Clear the ID if clicked outside
            studentListModal.classList.add('show'); // Re-show the student list modal
        }
        if (event.target === editStudentModal) {
            editStudentModal.classList.remove('show');
        }
    });

    // Initial fetch and render when the page loads
    fetchAndRenderCourses();
});
