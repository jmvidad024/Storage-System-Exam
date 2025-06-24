document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role');
    const facultyFields = document.getElementById('faculty_fields');
    const studentFields = document.getElementById('student_fields');
    const usernameInput = document.getElementById('username'); // The primary username input
    const studentIdInput = document.getElementById('student_id'); // The student ID input

    // Faculty specific dropdowns
    const facultyCourseSelect = document.getElementById('faculty_course');
    const facultyMajorGroup = document.getElementById('faculty_major_group');
    const facultyMajorSelect = document.getElementById('faculty_major');
    const facultyCourseDataHidden = document.getElementById('faculty_course_data');

    // Student specific dropdowns
    const studentCourseSelect = document.getElementById('course'); // Note: ID 'course'
    const studentMajorGroup = document.getElementById('major-group');
    const studentMajorSelect = document.getElementById('major'); // Note: ID 'major'
    const studentCourseMajorDbHidden = document.getElementById('course_major_db'); // Note: ID 'course_major_db'
    const studentYearSelect = document.getElementById('year');
    const studentSectionSelect = document.getElementById('section');


    // majorsByCourseData is passed from PHP
    // const majorsByCourseData = { ... }; // Already defined in the PHP HTML file

    function toggleRoleFields() {
        const selectedRole = roleSelect.value;

        // Hide all extra fields by default
        facultyFields.style.display = 'none';
        studentFields.style.display = 'none';

        // Enable username input by default
        usernameInput.disabled = false;
        usernameInput.placeholder = "Choose a unique username";
        usernameInput.required = true; // Ensure it's required for non-students

        // Reset student-specific requirements
        studentIdInput.required = false;
        studentCourseSelect.required = false;
        studentYearSelect.required = false;
        studentSectionSelect.required = false;
        studentMajorSelect.required = false;

        // Reset faculty-specific requirements
        facultyCourseSelect.required = false;
        facultyMajorSelect.required = false; // Only if visible

        if (selectedRole === 'faculty') {
            facultyFields.style.display = 'block';
            usernameInput.placeholder = "Enter faculty username"; // Suggest specific username type
            facultyCourseSelect.required = true;
            // Re-trigger change to populate major for faculty if pre-selected
            facultyCourseSelect.dispatchEvent(new Event('change'));

        } else if (selectedRole === 'student') {
            studentFields.style.display = 'block';
            usernameInput.disabled = true; // Disable username input for students
            usernameInput.placeholder = "Student ID will be your username"; // Inform user
            usernameInput.required = false; // Not directly filled by user, but by student_id
            
            studentIdInput.required = true;
            studentCourseSelect.required = true;
            studentYearSelect.required = true;
            studentSectionSelect.required = true;

            // Re-trigger change to populate major for student if pre-selected
            studentCourseSelect.dispatchEvent(new Event('change'));

        }
    }

    // --- Event Listeners for Dynamic Fields ---

    // Primary role selection
    roleSelect.addEventListener('change', toggleRoleFields);

    // Student ID input event to populate username
    studentIdInput.addEventListener('input', function() {
        if (roleSelect.value === 'student') {
            usernameInput.value = studentIdInput.value.trim();
        }
    });

    // Faculty Course selection changes
    facultyCourseSelect.addEventListener('change', function() {
        const selectedCourse = this.value;
        facultyMajorSelect.innerHTML = '<option value="">Select Major</option>';
        facultyMajorGroup.style.display = 'none';
        facultyMajorSelect.required = false; // Reset required status

        if (selectedCourse && majorsByCourseData[selectedCourse] && majorsByCourseData[selectedCourse].length > 0) {
            majorsByCourseData[selectedCourse].forEach(function(major) {
                const option = document.createElement('option');
                option.value = major;
                option.textContent = major;
                facultyMajorSelect.appendChild(option);
            });
            facultyMajorGroup.style.display = 'block';
            facultyMajorSelect.required = true;
        }
        // Update hidden field for faculty course data
        facultyMajorSelect.dispatchEvent(new Event('change')); // Trigger to update combined data
    });

    // Faculty Major selection changes
    facultyMajorSelect.addEventListener('change', function() {
        const selectedCourse = facultyCourseSelect.value;
        const selectedMajor = this.value;
        if (selectedCourse && selectedMajor) {
            facultyCourseDataHidden.value = `${selectedCourse} : ${selectedMajor}`;
        } else {
            facultyCourseDataHidden.value = selectedCourse; // Still store just the course if major is empty
        }
    });

    // Student Course selection changes
    studentCourseSelect.addEventListener('change', function() {
        const selectedCourse = this.value;
        studentMajorSelect.innerHTML = '<option value="">Select Major</option>';
        studentMajorGroup.style.display = 'none';
        studentMajorSelect.required = false; // Reset required status

        if (selectedCourse && majorsByCourseData[selectedCourse] && majorsByCourseData[selectedCourse].length > 0) {
            majorsByCourseData[selectedCourse].forEach(function(major) {
                const option = document.createElement('option');
                option.value = major;
                option.textContent = major;
                studentMajorSelect.appendChild(option);
            });
            studentMajorGroup.style.display = 'block';
            studentMajorSelect.required = true;
        }
        // Update hidden field for student course data
        studentMajorSelect.dispatchEvent(new Event('change')); // Trigger to update combined data
    });

    // Student Major selection changes
    studentMajorSelect.addEventListener('change', function() {
        const selectedCourse = studentCourseSelect.value;
        const selectedMajor = this.value;
        if (selectedCourse && selectedMajor) {
            studentCourseMajorDbHidden.value = `${selectedCourse} : ${selectedMajor}`;
        } else {
            studentCourseMajorDbHidden.value = selectedCourse;
        }
    });

    // Initial call to set up fields based on default/retained role
    toggleRoleFields();

    // Re-populate dropdowns if there was a POST and role-specific data was submitted
    // This is for keeping selected values after form submission with errors
    const initialRole = roleSelect.value;
    if (initialRole) {
        if (initialRole === 'faculty') {
            const currentFacultyData = facultyCourseDataHidden.value;
            if (currentFacultyData) {
                const [coursePart, majorPart] = currentFacultyData.split(' : ').map(p => p.trim());
                facultyCourseSelect.value = coursePart;
                if (majorsByCourseData[coursePart] && majorsByCourseData[coursePart].length > 0) {
                    // Populate majors first
                    facultyMajorSelect.innerHTML = '<option value="">Select Major</option>';
                    majorsByCourseData[coursePart].forEach(major => {
                        const option = document.createElement('option');
                        option.value = major;
                        option.textContent = major;
                        facultyMajorSelect.appendChild(option);
                    });
                    facultyMajorGroup.style.display = 'block';
                    if (majorPart) {
                        facultyMajorSelect.value = majorPart;
                    }
                }
            }
        } else if (initialRole === 'student') {
            const currentStudentData = studentCourseMajorDbHidden.value;
            if (currentStudentData) {
                const [coursePart, majorPart] = currentStudentData.split(' : ').map(p => p.trim());
                studentCourseSelect.value = coursePart;
                if (majorsByCourseData[coursePart] && majorsByCourseData[coursePart].length > 0) {
                    // Populate majors first
                    studentMajorSelect.innerHTML = '<option value="">Select Major</option>';
                    majorsByCourseData[coursePart].forEach(major => {
                        const option = document.createElement('option');
                        option.value = major;
                        option.textContent = major;
                        studentMajorSelect.appendChild(option);
                    });
                    studentMajorGroup.style.display = 'block';
                    if (majorPart) {
                        studentMajorSelect.value = majorPart;
                    }
                }
            }
        }
    }
});
