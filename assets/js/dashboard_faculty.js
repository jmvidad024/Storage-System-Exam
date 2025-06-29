document.addEventListener('DOMContentLoaded', function() {

    const container = document.getElementById('faculty_exam_list');
    if (!container) return; 

    fetch(`../api/exam.php`)
        .then(response => response.json())
        .then(data => {
            
            if (data.status === 'success') {
                if (data.exams.length === 0) {
                    container.innerHTML = '<p>No exams found.</p>';
                } else {
                    data.exams.forEach((exam, index) => {
    const card = document.createElement('div');
    card.className = 'exam-card';

    const chartId = `donut-chart-${index}`;
    const taken = exam.students_taken ?? 0;
    const total = exam.total_students ?? 0;
    const remaining = Math.max(total - taken, 0);

    card.innerHTML = `
        <h3>${exam.title}</h3>
        <p><strong>Code:</strong> ${exam.code}</p>
        <p><strong>Instruction:</strong> ${exam.instruction}</p>
        <p><strong>Year & Section:</strong> ${exam.year}-${exam.section}</p>
        <p><strong>Total Students:</strong> ${total}</p>
        <p><strong>Taken:</strong> ${taken}</p>
        <canvas id="${chartId}" width="150" height="150"></canvas>
    `;

    container.appendChild(card);

    const ctx = document.getElementById(chartId).getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Taken', 'Remaining'],
            datasets: [{
                data: [taken, remaining],
                backgroundColor: ['#2ecc71', '#e74c3c'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: false,
            cutout: '60%',
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: context => `${context.label}: ${context.parsed}`
                    }
                }
            }
        }
    });
});

                }
            } else {
                container.innerHTML = `<p>Error: ${data.message}</p>`;
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            document.getElementById('faculty_exam_list').innerHTML = '<p>Failed to load exams.</p>';
        });
});
