document.addEventListener('DOMContentLoaded', function() {
            const dropdownButton = document.getElementById('dropdown-button');
            const dropdownMenu = document.querySelector('.dropdown-menu');

            if (dropdownButton && dropdownMenu) {
                dropdownButton.addEventListener('click', function() {
                    dropdownMenu.classList.toggle('show');
                });

                // Close the dropdown if the user clicks outside of it
                window.addEventListener('click', function(event) {
                    if (!event.target.matches('.dropdown-toggle') && !event.target.closest('.dropdown-menu')) {
                        if (dropdownMenu.classList.contains('show')) {
                            dropdownMenu.classList.remove('show');
                        }
                    }
                });
            }
        });