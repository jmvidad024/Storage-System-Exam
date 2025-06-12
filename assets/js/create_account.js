document.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.getElementById('role');
            const studentFieldsDiv = document.getElementById('student_fields');
            const studentInputs = studentFieldsDiv.querySelectorAll('input, select'); // All inputs/selects in student fields

            function toggleStudentFields() {
                if (roleSelect.value === 'student') {
                    studentFieldsDiv.style.display = 'block'; // Show the div
                    // Make fields required when visible
                    studentInputs.forEach(input => input.setAttribute('required', 'required'));
                } else {
                    studentFieldsDiv.style.display = 'none'; // Hide the div
                    // Remove required attribute when hidden
                    studentInputs.forEach(input => input.removeAttribute('required'));
                }
            }

            // Initial call to set correct visibility on page load (e.g., if form redisplays due to error)
            toggleStudentFields();

            // Add event listener for changes to the role select
            roleSelect.addEventListener('change', toggleStudentFields);
        });