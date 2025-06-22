document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role');
    const studentFieldsDiv = document.getElementById('student_fields');
    const facultyFieldsDiv = document.getElementById('faculty_fields');

    // Student specific elements
    const courseSelect = document.getElementById('course');
    const majorGroup = document.getElementById('major-group');
    const majorSelect = document.getElementById('major');
    const courseMajorDb = document.getElementById('course_major_db'); // Hidden input for student data

    // Faculty specific elements
    const facultyCourseSelect = document.getElementById('faculty_course'); // The visible select
    const facultyMajorGroup = document.getElementById('faculty_major_group');
    const facultyMajorSelect = document.getElementById('faculty_major');
    const facultyCourseData = document.getElementById('faculty_course_data'); // Hidden input for faculty data

    // `majorsByCourseData` is a global variable defined in createAccount.php
    // It contains all courses and their associated majors.

    // Store initial POST values to re-populate fields on form error
    const initialRole = roleSelect.value;

    const initialStudentCourseData = courseMajorDb.value; // Combined string from PHP for students
    let initialStudentCourseName = '';
    let initialStudentMajorName = '';
    if (initialStudentCourseData) {
        const parts = initialStudentCourseData.split(' : ', 2);
        initialStudentCourseName = parts[0];
        initialStudentMajorName = parts[1] || '';
    }

    const initialFacultyCourseData = facultyCourseData.value; // Combined string from PHP for faculty
    let initialFacultyCourseName = '';
    let initialFacultyMajorName = '';
    if (initialFacultyCourseData) {
        const parts = initialFacultyCourseData.split(' : ', 2);
        initialFacultyCourseName = parts[0];
        initialFacultyMajorName = parts[1] || '';
    }

    /**
     * Toggles visibility and required status of role-specific fields.
     */
    function toggleRoleSpecificFields() {
        const selectedRole = roleSelect.value;

        // Hide all extra fields and remove required attributes by default
        studentFieldsDiv.style.display = 'none';
        facultyFieldsDiv.style.display = 'none';

        // Clear input values in hidden sections to prevent accidental submission or validation issues
        studentFieldsDiv.querySelectorAll('input:not(#course_major_db), select').forEach(input => {
            input.value = ''; // Clear value
            input.removeAttribute('required');
        });
        facultyFieldsDiv.querySelectorAll('input:not(#faculty_course_data), select').forEach(input => {
            input.value = ''; // Clear value
            input.removeAttribute('required');
        });

        // Hide and reset all major fields
        majorGroup.style.display = 'none';
        majorSelect.removeAttribute('required');
        majorSelect.innerHTML = '<option value="">Select Major</option>'; // Clear student major options

        facultyMajorGroup.style.display = 'none';
        facultyMajorSelect.removeAttribute('required');
        facultyMajorSelect.innerHTML = '<option value="">Select Major</option>'; // Clear faculty major options

        if (selectedRole === 'student') {
            studentFieldsDiv.style.display = 'block';
            studentFieldsDiv.querySelectorAll('input:not(#course_major_db), select').forEach(input => {
                input.setAttribute('required', 'required');
            });
            // Re-select student course and major if coming from a POST request
            courseSelect.value = initialStudentCourseName; // Set the visible course select
            updateCourseAndMajorFields(
                courseSelect,
                majorSelect,
                majorGroup,
                courseMajorDb,
                initialStudentCourseName,
                initialStudentMajorName
            );

        } else if (selectedRole === 'faculty') {
            facultyFieldsDiv.style.display = 'block';
            facultyCourseSelect.setAttribute('required', 'required'); // Make faculty course required

            // Re-select faculty course and major if coming from a POST request
            facultyCourseSelect.value = initialFacultyCourseName; // Set the visible course select
            updateCourseAndMajorFields(
                facultyCourseSelect,
                facultyMajorSelect,
                facultyMajorGroup,
                facultyCourseData,
                initialFacultyCourseName,
                initialFacultyMajorName
            );
        }
    }

    /**
     * General function to update major dropdown based on selected course for both students and faculty.
     * @param {HTMLElement} courseDropdown - The course select element (e.g., courseSelect or facultyCourseSelect).
     * @param {HTMLElement} majorDropdown - The major select element (e.g., majorSelect or facultyMajorSelect).
     * @param {HTMLElement} majorGroupDiv - The div containing the major dropdown (e.g., majorGroup or facultyMajorGroup).
     * @param {HTMLElement} hiddenDataInput - The hidden input to store combined data (e.g., courseMajorDb or facultyCourseData).
     * @param {string} preselectedCourseName - The course name from initial POST data.
     * @param {string} preselectedMajorName - The major name from initial POST data.
     */
    function updateCourseAndMajorFields(
        courseDropdown,
        majorDropdown,
        majorGroupDiv,
        hiddenDataInput,
        preselectedCourseName = null,
        preselectedMajorName = null
    ) {
        const selectedCourse = courseDropdown.value;
        const majors = majorsByCourseData[selectedCourse] || [];

        // Reset major dropdown
        majorDropdown.innerHTML = '<option value="">Select Major</option>';
        majorGroupDiv.style.display = 'none';
        majorDropdown.removeAttribute('required');

        // Set the hidden input based on the current course selection (before major is picked)
        hiddenDataInput.value = selectedCourse;

        if (majors.length > 0) {
            majors.forEach(major => {
                const option = document.createElement('option');
                option.value = major;
                option.textContent = major;
                majorDropdown.appendChild(option);
            });
            majorGroupDiv.style.display = 'block';
            majorDropdown.setAttribute('required', 'required');

            // Attempt to re-select major if preselected values exist and match
            if (preselectedMajorName && majors.includes(preselectedMajorName)) {
                majorDropdown.value = preselectedMajorName;
                hiddenDataInput.value = `${selectedCourse} : ${preselectedMajorName}`;
            }
        }
        // Always ensure the hidden field is correctly set after updates
        updateCombinedHiddenField(courseDropdown, majorDropdown, hiddenDataInput);
    }

    /**
     * Updates the hidden combined field (e.g., course_major_db or faculty_course_data).
     */
    function updateCombinedHiddenField(courseDropdown, majorDropdown, hiddenDataInput) {
        const selectedCourse = courseDropdown.value;
        const selectedMajor = majorDropdown.value;
        if (selectedMajor) {
            hiddenDataInput.value = `${selectedCourse} : ${selectedMajor}`;
        } else {
            hiddenDataInput.value = selectedCourse;
        }
    }

    // --- Event Listeners ---
    roleSelect.addEventListener('change', toggleRoleSpecificFields);

    // Student specific dropdown listeners
    courseSelect.addEventListener('change', () => {
        // When student course changes, update student major dropdown and hidden field
        updateCourseAndMajorFields(courseSelect, majorSelect, majorGroup, courseMajorDb);
    });
    majorSelect.addEventListener('change', () => {
        // When student major changes, update hidden field
        updateCombinedHiddenField(courseSelect, majorSelect, courseMajorDb);
    });

    // Faculty specific dropdown listeners
    facultyCourseSelect.addEventListener('change', () => {
        // When faculty course changes, update faculty major dropdown and hidden field
        updateCourseAndMajorFields(facultyCourseSelect, facultyMajorSelect, facultyMajorGroup, facultyCourseData);
    });
    facultyMajorSelect.addEventListener('change', () => {
        // When faculty major changes, update hidden field
        updateCombinedHiddenField(facultyCourseSelect, facultyMajorSelect, facultyCourseData);
    });

    // --- Initial setup on page load ---
    toggleRoleSpecificFields(); // Call on DOMContentLoaded to set initial state
});