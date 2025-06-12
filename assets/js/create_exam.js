const question_form = document.getElementById("question_form");
const add_button = document.getElementById("add_button");
let question_count = 1;

// Declare modal elements globally but assign them inside DOMContentLoaded
let confirmationModal;
let confirmSubmitBtn;
let cancelSubmitBtn;
let modalFormSummary; // New: Reference to the summary container

// Function to send form data (no changes needed here)
function sendFormData() {
    const formData = new FormData(question_form);
    const jsonObject = {};

    for (let [key, value] of formData.entries()) {
        // Normalize keys like choices_1[] to choices_1
        const cleanKey = key.replace(/\[\]$/, "");

        if (jsonObject[cleanKey]) {
            if (!Array.isArray(jsonObject[cleanKey])) {
                jsonObject[cleanKey] = [jsonObject[cleanKey]];
            }
            jsonObject[cleanKey].push(value);
        } else {
            jsonObject[cleanKey] = value;
        }
    }

    fetch("/storage-system/api/exam.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify(jsonObject)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log("Server response:", data);
        alert("Form submitted successfully!");
        question_form.reset();
        document.querySelectorAll('.question_container').forEach(container => container.remove());
        question_count = 1;
        reindexQuestions();
    })
    .catch(error => {
        console.error("Error submitting form:", error);
        alert("Error submitting form. Please try again.");
    });
}

// Function to reindex questions and choices (no changes needed here)
function reindexQuestions() {
    const containers = document.querySelectorAll('.question_container');
    let new_question_index = 1;

    containers.forEach((div) => {
        const qIndex = new_question_index;

        const questionLabel = div.querySelector('label');
        if (questionLabel) {
            questionLabel.textContent = `Question ${qIndex}:`;
            questionLabel.htmlFor = `question_${qIndex}`;
        }

        const questionInput = div.querySelector('.question-input');
        if (questionInput) {
            questionInput.name = `question_${qIndex}`;
            questionInput.id = `question_${qIndex}`;
        }

        const answerInput = div.querySelector('.answer-input');
        if (answerInput) {
            answerInput.name = `answer_${qIndex}`;
            answerInput.id = `answer_${qIndex}`;
        }

        const addOptionButton = div.querySelector('.add-option-btn');
        if (addOptionButton) {
            addOptionButton.id = `add_option_${qIndex}`;
        }

        const choiceInputs = div.querySelectorAll('.choice-input');
        choiceInputs.forEach((input, choiceIndex) => {
            input.name = `choices_${qIndex}[]`;
            input.id = `choice_${qIndex}_${choiceIndex + 1}`;
        });

        const removeChoiceButtons = div.querySelectorAll('.remove-choice-btn');
        removeChoiceButtons.forEach((btn, i) => {
             btn.id = `remove_choice_${qIndex}_${i + 1}`;
        });

        new_question_index++;
    });

    question_count = containers.length + 1;
}

// --- NEW FUNCTION: Generate Modal Summary ---
function generateModalSummary() {
    let summaryHtml = '';

    // 1. Get Title and Instruction
    const title = document.getElementById('title').value;
    const instruction = document.getElementById('instruction').value;

    if (title) {
        summaryHtml += `<div class="summary-title">Exam Title: ${title}</div>`;
    }
    if (instruction) {
        summaryHtml += `<div class="summary-instruction">Instruction: ${instruction}</div>`;
    }

    // 2. Get all questions
    const questionContainers = document.querySelectorAll('.question_container');

    if (questionContainers.length === 0) {
        summaryHtml += '<p>No questions added yet.</p>';
    } else {
        questionContainers.forEach((qContainer, qIndex) => {
            const questionText = qContainer.querySelector('.question-input').value;
            const rightAnswer = qContainer.querySelector('.answer-input').value;
            const choiceInputs = qContainer.querySelectorAll('.choice-input');

            summaryHtml += `<h3>Question ${qIndex + 1}: ${questionText || '[Empty Question]'}</h3>`;
            summaryHtml += `<ul>`;

            if (choiceInputs.length === 0) {
                summaryHtml += `<li>No choices added.</li>`;
            } else {
                choiceInputs.forEach((choiceInput, choiceIndex) => {
                    summaryHtml += `<li>Choice ${choiceIndex + 1}: ${choiceInput.value || '[Empty Choice]'}</li>`;
                });
            }

            summaryHtml += `<li>Right Answer: <span class="right-answer">${rightAnswer || '[Empty Answer]'}</span></li>`;
            summaryHtml += `</ul>`;
        });
    }

    // Insert the generated HTML into the summary div
    if (modalFormSummary) {
        modalFormSummary.innerHTML = summaryHtml;
    } else {
        console.error("Error: modalFormSummary element not found. Cannot display summary.");
    }
}

// --- DOM Content Loaded Listener ---
document.addEventListener('DOMContentLoaded', () => {
    // Assign modal elements here after the DOM is fully loaded
    confirmationModal = document.getElementById("confirmationModal");
    confirmSubmitBtn = document.getElementById("confirmSubmitBtn");
    cancelSubmitBtn = document.getElementById("cancelSubmitBtn");
    modalFormSummary = document.getElementById("modal-form-summary"); // New: Get the summary div

    // Initial reindexing (for any pre-existing questions on page load)
    reindexQuestions();

    // --- Event Listener for Form Submission (to show modal) ---
    question_form.addEventListener('submit', function(e){
        e.preventDefault(); // Prevent default form submission initially

        reindexQuestions(); // Reindex before displaying modal (good practice)

        // Robust check for modal elements
        if (!confirmationModal || !confirmSubmitBtn || !cancelSubmitBtn || !modalFormSummary) { // Added modalFormSummary check
            console.error("Error: Modal elements not found in the DOM. Check your HTML IDs and ensure script loads after HTML.");
            alert("An internal error occurred. Submitting directly.");
            sendFormData(); // Fallback: Submit directly if modal elements aren't found
            return;
        }

        // --- NEW: Generate and populate the modal summary content ---
        generateModalSummary();

        // Show the confirmation modal
        confirmationModal.style.display = "flex";

        // --- Attach Event Listeners to Modal Buttons ---
        confirmSubmitBtn.onclick = function() {
            confirmationModal.style.display = "none";
            sendFormData();
        };

        cancelSubmitBtn.onclick = function() {
            confirmationModal.style.display = "none";
        };

        // Handle clicks on the modal overlay to close it
        confirmationModal.onclick = function(event) {
            if (event.target === confirmationModal) {
                confirmationModal.style.display = "none";
            }
        };

        // Prevent clicks within modal content from bubbling up to close the modal
        const modalContent = confirmationModal.querySelector('.modal-content');
        if (modalContent) {
            modalContent.onclick = function(event) {
                event.stopPropagation();
            };
        }
    });

    // --- Event Listener for Adding New Questions (no changes needed here) ---
    add_button.addEventListener('click', () => {
        const parent = question_form;
        const add_div = document.createElement("div");
        add_div.className = "question_container";

        const add_question = document.createElement("input");
        add_question.type = "text";
        add_question.className = "question-input";
        add_question.placeholder = "Enter Question";
        add_question.name = `question_${question_count}`;
        add_question.id = `question_${question_count}`;

        const question_label = document.createElement("label");
        question_label.textContent = `Question ${question_count}:`;
        question_label.htmlFor = `question_${question_count}`;

        const add_option = document.createElement("button");
        add_option.type = "button";
        add_option.className = "add-option-btn";
        add_option.textContent = "Add Option";
        add_option.id = `add_option_${question_count}`;

        const add_answer = document.createElement("input");
        add_answer.type = "text";
        add_answer.className = "answer-input";
        add_answer.placeholder = "Enter Right Answer";
        add_answer.name = `answer_${question_count}`;
        add_answer.id = `answer_${question_count}`;

        const remove_question = document.createElement("button");
        remove_question.type = "button";
        remove_question.className = "remove-question-btn";
        remove_question.textContent = "Remove Question";

        const buttonContainer = document.createElement("div");
        buttonContainer.className = "question-actions";

        add_div.appendChild(question_label);
        add_div.appendChild(add_question);
        add_div.appendChild(add_answer);
        buttonContainer.appendChild(add_option);
        buttonContainer.appendChild(remove_question);
        add_div.appendChild(buttonContainer);

        const submit_button = document.getElementById("submit_button");
        if (submit_button && submit_button.parentNode === parent) {
            parent.insertBefore(add_div, submit_button);
        } else {
            parent.insertBefore(add_div, add_button);
        }

        remove_question.addEventListener('click', () => {
            add_div.remove();
            reindexQuestions();
        });

        add_option.addEventListener('click', () => {
            const choiceContainer = document.createElement("div");
            choiceContainer.className = "choice-container";

            const choice = document.createElement("input");
            choice.type = "text";
            choice.className = "choice-input";
            choice.placeholder = "Enter Choice";

            const currentQuestionDiv = choiceContainer.closest('.question_container');
            let currentQIndex = 1;
            if (currentQuestionDiv) {
                const questionInputInDiv = currentQuestionDiv.querySelector('.question-input');
                if (questionInputInDiv && questionInputInDiv.name) {
                    const match = questionInputInDiv.name.match(/question_(\d+)/);
                    if (match) {
                        currentQIndex = parseInt(match[1]);
                    }
                }
            }
            choice.name = `choices_${currentQIndex}[]`;
            choice.id = `choice_${currentQIndex}_${add_div.querySelectorAll('.choice-input').length + 1}`;

            const remove_choice = document.createElement("button");
            remove_choice.type = "button";
            remove_choice.className = "remove-choice-btn";
            remove_choice.textContent = "Remove Choice";

            choiceContainer.appendChild(choice);
            choiceContainer.appendChild(remove_choice);

            add_div.insertBefore(choiceContainer, buttonContainer);

            remove_choice.addEventListener("click", () => {
                choiceContainer.remove();
            });
        });

        question_count++;
    });
});