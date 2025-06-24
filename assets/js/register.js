document.addEventListener('DOMContentLoaded', function() {
    const registerForm = document.getElementById('student_register_form');
    const messageArea = document.getElementById('registration_message_area');
    const studentIdInput = document.getElementById('student_id');
    const usernameHiddenInput = document.getElementById('username');
    const courseSelect = document.getElementById('course');
    const majorGroup = document.getElementById('major-group');
    const majorSelect = document.getElementById('major');
    const courseMajorDb = document.getElementById('course_major_db');
    const yearSelect = document.getElementById('year');
    const sectionSelect = document.getElementById('section');

    const majorsByCourse = {
        'Education': ['Science', 'Math', 'English', 'History'],
        'Engineering': ['Civil', 'Electrical', 'Mechanical']
    };

    function displayMessage(type, message) {
        messageArea.classList.remove('hidden', 'success', 'error');
        messageArea.textContent = message;
        if (type === 'success') {
            messageArea.classList.add('success');
            messageArea.classList.remove('error');
        } else if (type === 'error') {
            messageArea.classList.add('error');
            messageArea.classList.remove('success');
        }
        messageArea.style.display = 'block';
        setTimeout(() => {
            messageArea.style.display = 'none';
            messageArea.textContent = '';
        }, 5000);
    }

    studentIdInput.addEventListener('input', function() {
        usernameHiddenInput.value = studentIdInput.value.trim();
    });

    courseSelect.addEventListener('change', function() {
        const selectedCourse = this.value;
        majorSelect.innerHTML = '<option value="">Select Major</option>';
        majorGroup.style.display = 'none';
        courseMajorDb.value = selectedCourse;

        if (selectedCourse && majorsByCourse[selectedCourse]) {
            majorsByCourse[selectedCourse].forEach(function(major) {
                const option = document.createElement('option');
                option.value = major;
                option.textContent = major;
                majorSelect.appendChild(option);
            });
            majorGroup.style.display = 'block';
        }
        majorSelect.dispatchEvent(new Event('change'));
    });

    majorSelect.addEventListener('change', function() {
        const selectedCourse = courseSelect.value;
        const selectedMajor = this.value;
        
        if (selectedCourse && selectedMajor) {
            courseMajorDb.value = `${selectedCourse} : ${selectedMajor}`;
        } else {
            courseMajorDb.value = selectedCourse;
        }
    });

    registerForm.addEventListener('submit', async function(event) {
        event.preventDefault();

        usernameHiddenInput.value = studentIdInput.value.trim();

        const formData = new FormData(registerForm);
        const data = {};
        formData.forEach((value, key) => {
            data[key] = value;
        });

        data.course_major_db = courseMajorDb.value;
        data.year = yearSelect.value;
        data.section = sectionSelect.value;

        // Client-side validation
        if (!data.student_id || data.student_id.trim() === '') {
            displayMessage('error', 'Student ID is required.');
            return;
        }
        if (data.password !== data.confirm_password) {
            displayMessage('error', 'Passwords do not match.');
            return;
        }
        if (data.password.length < 6) {
            displayMessage('error', 'Password must be at least 6 characters long.');
            return;
        }
        if (!data.course_major_db || data.course_major_db === 'Select Course') {
            displayMessage('error', 'Please select your course.');
            return;
        }
        if (majorGroup.style.display === 'block' && !majorSelect.value) {
            displayMessage('error', 'Please select your major.');
            return;
        }
        if (!data.year || data.year.trim() === '') {
            displayMessage('error', 'Please select your year.');
            return;
        }
        if (!data.section || data.section.trim() === '') {
            displayMessage('error', 'Please select your section.');
            return;
        }

        const submitButton = registerForm.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        submitButton.textContent = 'Registering...';
        displayMessage('success', 'Sending registration request...');

        try {
            const response = await fetch('../api/register.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const responseData = await response.json();

            if (response.ok && responseData.status === 'success') {
                displayMessage('success', responseData.message);
                registerForm.reset();
                courseSelect.dispatchEvent(new Event('change'));
                studentIdInput.value = '';
                usernameHiddenInput.value = '';
                yearSelect.value = ''; // Reset year
                sectionSelect.value = ''; // Reset section
            } else {
                displayMessage('error', responseData.message || 'Registration failed. Please try again.');
            }
        } catch (error) {
            console.error('Error during registration:', error);
            displayMessage('error', 'Network error or server unavailable. Please try again later.');
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = 'Register';
        }
    });

    courseSelect.dispatchEvent(new Event('change'));
});
