document.addEventListener('DOMContentLoaded', async function() {
    // --- Dropdown logic (unchanged) ---
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

    const userRoleElement = document.getElementById('userRole');
    let currentUserRole = userRoleElement ? userRoleElement.value : 'guest';
    console.log("Initial User Role from PHP:", currentUserRole); // For debugging

    // NEW: Get faculty's assigned course/major from hidden input
    const facultyAssignedCourseMajorElement = document.getElementById('facultyAssignedCourseMajor');
    let facultyAssignedCourseMajor = facultyAssignedCourseMajorElement ? facultyAssignedCourseMajorElement.value : '';
    console.log("Faculty Assigned Course/Major from PHP:", facultyAssignedCourseMajor); // For debugging

    // --- References for the edit student modal and related elements ---
    const editStudentModal = document.getElementById('editStudentModal');
    const closeEditStudentModalBtn = document.getElementById('closeEditStudentModalBtn');
    const editStudentForm = document.getElementById('editStudentForm');
    const editStudentIdInput = document.getElementById('editStudentId');
    const editUserIdInput = document.getElementById('editUserId');
    const editStudentUsernameInput = document.getElementById('editStudentUsername');
    const editStudentNameInput = document.getElementById('editStudentName');
    const editStudentEmailInput = document.getElementById('editStudentEmail');
    const editStudentCourseInput = document.getElementById('editStudentCourse');
    const editStudentYearInput = document.getElementById('editStudentYear');
    const editStudentSectionInput = document.getElementById('editStudentSection');
    const editStudentModalMessage = document.getElementById('editStudentModalMessage');

    const editStudentMajorGroup = document.getElementById('editStudentMajorGroup');
    const editStudentMajorInput = document.getElementById('editStudentMajor');
    const editStudentCourseMajorHidden = document.getElementById('editStudentCourseMajor');

    const majorsByCourse = {
        'Education': ['Science', 'Math', 'English', 'History'],
        'Engineering': ['Electrical', 'Mechanical', 'Civil'],
        'Computer Science': [],
        'Information Technology': [], // Ensure this matches your PHP side
    };

    // --- Event listeners for Course and Major selection in Edit Student Modal ---
    editStudentCourseInput.addEventListener('change', () => {
        const selectedCourse = editStudentCourseInput.value;
        const majors = majorsByCourse[selectedCourse];

        if (majors && majors.length > 0) { // Check for majors array and if it has elements
            editStudentMajorGroup.style.display = 'block';
            editStudentMajorInput.innerHTML = '<option value="">Select Major</option>';
            majors.forEach(major => {
                const option = document.createElement('option');
                option.value = major;
                option.textContent = major;
                editStudentMajorInput.appendChild(option);
            });
            // If there's a preselected major stored, attempt to set it
            if (editStudentMajorInput.dataset.preselectedMajor) {
                editStudentMajorInput.value = editStudentMajorInput.dataset.preselectedMajor;
                delete editStudentMajorInput.dataset.preselectedMajor; // Clear it after use
            }
            editStudentMajorInput.dispatchEvent(new Event('change')); // Trigger major change for hidden field
        } else {
            editStudentMajorGroup.style.display = 'none';
            editStudentMajorInput.innerHTML = ''; // Clear major options
            editStudentCourseMajorHidden.value = selectedCourse; // Only course if no major
        }
    });

    editStudentMajorInput.addEventListener('change', () => {
        const course = editStudentCourseInput.value;
        const major = editStudentMajorInput.value;
        editStudentCourseMajorHidden.value = major ? `${course} : ${major}` : course;
    });

    // --- Main Course List Display ---
    const courseListContainer = document.getElementById('course_list_container');
    const loadingIndicator = courseListContainer.querySelector('.loading-indicator');
    const errorMessageDiv = courseListContainer.querySelector('.error-message');

    // --- Student List Modal Elements ---
    const studentListModal = document.getElementById('studentListModal');
    const closeStudentModalBtn = document.getElementById('closeStudentModalBtn');
    const studentListTableBody = document.getElementById('studentListTableBody');
    const studentModalLoading = document.getElementById('studentModalLoading');
    const studentModalError = document.getElementById('studentModalError');
    const studentModalTitle = document.getElementById('studentModalTitle');

    // --- Delete Student Confirmation Modal Elements ---
    const deleteStudentConfirmationModal = document.getElementById('deleteStudentConfirmationModal');
    const confirmDeleteStudentBtn = document.getElementById('confirmDeleteStudentBtn');
    const cancelDeleteStudentBtn = document.getElementById('cancelDeleteStudentBtn');
    const deleteStudentMessage = document.getElementById('deleteStudentMessage');
    let studentIdToDelete = null;
    let currentSectionData = { course: null, year: null, section: null };

    // Helper function to display messages
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
        // Only proceed if not in the faculty unassigned state
        if (currentUserRole === 'faculty' && !facultyAssignedCourseMajor) {
            loadingIndicator.style.display = 'none';
            errorMessageDiv.style.display = 'block';
            errorMessageDiv.textContent = 'Your faculty account is not assigned to a course. Please contact the administrator.';
            return;
        }

        loadingIndicator.style.display = 'block';
        errorMessageDiv.style.display = 'none';
        errorMessageDiv.textContent = '';
        courseListContainer.querySelector('.course-list')?.remove();

        try {
            // No need to pass faculty_assigned_course_major as GET param
            // The API will figure it out from the authenticated session
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
            courseListContainer.innerHTML = '<p class="no-data-message">No course, year, or section data found for your access level.</p>';
            return;
        }

        const courseList = document.createElement('div');
        courseList.classList.add('course-list');

        courses.forEach(course => {
            const courseBlock = document.createElement('div');
            courseBlock.classList.add('course-block');

            const courseHeader = document.createElement('div');
            courseHeader.classList.add('course-header');
            courseHeader.innerHTML = `<h3>${course.course_name}</h3><span class="toggle-icon">&#9660;</span>`;
            courseBlock.appendChild(courseHeader);

            const yearsContainer = document.createElement('div');
            yearsContainer.classList.add('years-container', 'hidden');
            courseBlock.appendChild(yearsContainer);

            courseHeader.addEventListener('click', () => {
                yearsContainer.classList.toggle('hidden');
                courseHeader.querySelector('.toggle-icon').innerHTML = yearsContainer.classList.contains('hidden') ? '&#9660;' : '&#9650;';
            });

            if (course.years && course.years.length > 0) {
                course.years.forEach(year => {
                    const yearBlock = document.createElement('div');
                    yearBlock.classList.add('year-block');

                    const yearHeader = document.createElement('div');
                    yearHeader.classList.add('year-header');
                    yearHeader.innerHTML = `<h4>Year ${year.year_level}</h4><span class="toggle-icon">&#9660;</span>`;
                    yearBlock.appendChild(yearHeader);

                    const sectionsContainer = document.createElement('div');
                    sectionsContainer.classList.add('sections-container', 'hidden');
                    yearBlock.appendChild(sectionsContainer);

                    yearHeader.addEventListener('click', () => {
                        sectionsContainer.classList.toggle('hidden');
                        yearHeader.querySelector('.toggle-icon').innerHTML = sectionsContainer.classList.contains('hidden') ? '&#9660;' : '&#9650;';
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
        studentListModal.classList.add('show');
        studentModalLoading.style.display = 'block';
        studentModalError.style.display = 'none';
        studentListTableBody.innerHTML = '';

        studentModalTitle.textContent = `Students in ${courseName}, Year ${yearLevel}, Section ${sectionName}`;

        try {
            // We need to pass the course, year, and section to the API
            const response = await fetch(`../api/students_by_section.php?course=${encodeURIComponent(courseName)}&year=${encodeURIComponent(yearLevel)}&section=${encodeURIComponent(sectionName)}`);
            const data = await response.json();
            studentModalLoading.style.display = 'none';

            if (response.ok && data.status === 'success' && data.students) {
                // Determine if 'Actions' column should be shown based on user role
                const showActionsColumn = currentUserRole === 'admin'; // Only admin can edit/delete

                const studentListTableHead = studentListModal.querySelector('.student-table thead tr');
                studentListTableHead.innerHTML = `
                    <th>Student ID</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    ${showActionsColumn ? '<th>Actions</th>' : ''}
                `;

                if (data.students.length > 0) {
                    data.students.forEach(student => {
                        const row = document.createElement('tr');
                        // Split course_major from student.course_major into course and major for dropdown pre-filling
                        const studentCourseCombined = student.course_name || '';
                        let [studentCourse, studentMajor] = ['', ''];
                        if (studentCourseCombined.includes(' : ')) {
                            const parts = studentCourseCombined.split(' : ', 2);
                            studentCourse = parts[0];
                            studentMajor = parts[1];
                        } else {
                            studentCourse = studentCourseCombined;
                        }

                        row.innerHTML = `
                            <td data-label="Student ID">${student.student_id || 'N/A'}</td>
                            <td data-label="Username">${student.username || 'N/A'}</td>
                            <td data-label="Full Name">${student.name || 'N/A'}</td>
                            <td data-label="Email">${student.email || 'N/A'}</td>
                            ${showActionsColumn ? `
                            <td data-label="Actions" class="actions">
                                <button class="btn btn-action btn-edit edit-student-btn"
                                    data-student-id="${student.id}"
                                    data-user-id="${student.user_id || ''}"
                                    data-username="${student.username || ''}"
                                    data-name="${student.name || ''}"
                                    data-email="${student.email || ''}"
                                    data-course="${studentCourse}"
                                    data-major="${studentMajor}"
                                    data-year="${student.year_level || ''}"
                                    data-section="${student.section_name || ''}">
                                    Edit</button>
                                <button class="btn btn-action btn-delete delete-student-btn"
                                    data-student-id="${student.id}"
                                    data-username="${student.username || ''}">Delete</button>
                            </td>
                            ` : ''}
                        `;
                        studentListTableBody.appendChild(row);
                    });

                    if (showActionsColumn) {
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
                    }

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
        if (currentUserRole === 'faculty') {
            console.warn("Faculty members cannot edit student data.");
            return;
        }

        // Close student list modal first
        studentListModal.classList.remove('show');

        if (editStudentIdInput) editStudentIdInput.value = studentData.studentId;
        if (editUserIdInput) editUserIdInput.value = studentData.userId;
        if (editStudentUsernameInput) editStudentUsernameInput.value = studentData.username;
        if (editStudentNameInput) editStudentNameInput.value = studentData.name;
        if (editStudentEmailInput) editStudentEmailInput.value = studentData.email;
        if (editStudentYearInput) editStudentYearInput.value = studentData.year;
        if (editStudentSectionInput) editStudentSectionInput.value = studentData.section;

        if (editStudentCourseInput && studentData.course) {
            console.log("Attempting to set course:", studentData.course);
        editStudentCourseInput.value = studentData.course;
        console.log("Course after attempt:", editStudentCourseInput.value); // Check what it actually became
        // ... (rest of your function)
            // Store major in a dataset attribute before dispatching change
            if (studentData.major) { // Only set preselectedMajor if a major exists
                editStudentMajorInput.dataset.preselectedMajor = studentData.major;
            } else {
                delete editStudentMajorInput.dataset.preselectedMajor; // Ensure it's cleared if no major
            }
            editStudentCourseInput.dispatchEvent(new Event('change')); // Trigger major dropdown population
        } else {
            // Handle case where no course data is available or clear existing selections
            if (editStudentCourseInput) editStudentCourseInput.value = '';
            editStudentMajorGroup.style.display = 'none';
            editStudentMajorInput.innerHTML = '<option value="">Select Major</option>';
            delete editStudentMajorInput.dataset.preselectedMajor;
            editStudentCourseMajorHidden.value = '';
        }

        editStudentModalMessage.style.display = 'none';
        editStudentModal.classList.add('show');
    }

    // NEW FUNCTION: Handles the edit student form submission
    editStudentForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(editStudentForm);
        // Correctly set the combined course value based on selections
        formData.set('course', editStudentCourseMajorHidden.value);
        // Remove individual major and major_display fields if they exist
        formData.delete('major_display'); // Make sure this matches name attribute if it exists
        formData.delete('major'); // Assuming the hidden field 'course_combined' is the source of truth

        const studentData = Object.fromEntries(formData.entries());

        displayActionMessage(editStudentModalMessage, 'loading', 'Saving changes...');

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
                displayActionMessage(editStudentModalMessage, 'success', result.message || 'Student updated successfully!');
                setTimeout(() => {
                    editStudentModal.classList.remove('show');
                    // Refresh the student list in the modal after update
                    // Use the stored currentSectionData to re-fetch
                    if (currentSectionData.course && currentSectionData.year && currentSectionData.section) {
                        showStudentsForSection(currentSectionData.course, currentSectionData.year, currentSectionData.section);
                    } else {
                        // If for some reason current section data is missing, re-render all courses
                        fetchAndRenderCourses();
                    }
                }, 1500);
            } else {
                throw new Error(result.message || 'Failed to update student.');
            }
        } catch (error) {
            console.error('Error updating student:', error);
            displayActionMessage(editStudentModalMessage, 'error', `Error: ${error.message}`);
        }
    });

    closeStudentModalBtn.addEventListener('click', () => {
        studentListModal.classList.remove('show');
    });

    // Optional: Close student list modal if clicking outside content
    studentListModal.addEventListener('click', function(event) {
        if (event.target === studentListModal) {
            studentListModal.classList.remove('show');
        }
    });

    // Event listener for closing the edit student modal
    closeEditStudentModalBtn.addEventListener('click', () => {
        editStudentModal.classList.remove('show');
    });

    editStudentModal.addEventListener('click', function(event) {
        if (event.target === editStudentModal) {
            editStudentModal.classList.remove('show');
        }
    });


    // NEW FUNCTION: Confirm delete student
    function confirmDeleteStudent(studentId, username) {
        if (currentUserRole === 'faculty') {
            console.warn("Faculty members cannot delete student data.");
            return;
        }
        studentIdToDelete = studentId; // Store the ID globally
        deleteStudentMessage.innerHTML = `Are you sure you want to delete student <strong>${username}</strong> (ID: ${studentId})? This action cannot be undone and will permanently remove their user account and all associated student data.`;
        deleteStudentConfirmationModal.classList.add('show');
    }

    // Handle delete confirmation
    confirmDeleteStudentBtn.addEventListener('click', async () => {
        if (studentIdToDelete) {
            displayActionMessage(deleteStudentConfirmationModal.querySelector('.modal-content'), 'loading', 'Deleting student...');
            try {
                const response = await fetch(`../api/delete_student.php`, { // Assuming a delete_student.php API
                    method: 'POST', // Or DELETE, based on your API design
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ student_id: studentIdToDelete })
                });

                const result = await response.json();

                if (response.ok && result.status === 'success') {
                    displayActionMessage(deleteStudentConfirmationModal.querySelector('.modal-content'), 'success', result.message || 'Student deleted successfully!');
                    setTimeout(() => {
                        deleteStudentConfirmationModal.classList.remove('show');
                        // Refresh the student list in the modal after deletion
                        if (currentSectionData.course && currentSectionData.year && currentSectionData.section) {
                            showStudentsForSection(currentSectionData.course, currentSectionData.year, currentSectionData.section);
                        } else {
                            fetchAndRenderCourses(); // Fallback to refresh all
                        }
                    }, 1500);
                } else {
                    throw new Error(result.message || 'Failed to delete student.');
                }
            } catch (error) {
                console.error('Error deleting student:', error);
                displayActionMessage(deleteStudentConfirmationModal.querySelector('.modal-content'), 'error', `Error: ${error.message}`);
            } finally {
                studentIdToDelete = null; // Clear the stored ID
            }
        }
    });

    cancelDeleteStudentBtn.addEventListener('click', () => {
        deleteStudentConfirmationModal.classList.remove('show');
        studentIdToDelete = null; // Clear the stored ID
    });

    // Close delete modal if clicking outside content
    deleteStudentConfirmationModal.addEventListener('click', function(event) {
        if (event.target === deleteStudentConfirmationModal) {
            deleteStudentConfirmationModal.classList.remove('show');
            studentIdToDelete = null;
        }
    });


    // Initial fetch and render of courses when the page loads
    fetchAndRenderCourses();
});