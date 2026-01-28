const { createApp } = Vue;

createApp({
    data() {
        return {
            title: "",
            description: "",
            expiry: "",
            sections: [],
            currentSectionIndex: 0,
            newSectionTitle: "",
            newType: "",
            newRequired: false,
            initialQuestionCount: 1,
            newQuestions: [],
            editingQuestionIndex: null,
            editingQuestion: null,
            editingSectionIndex: null,
            courseSearchTerm: "",
            dateSearchTerm: "",
            selectedCourses: ['none'],
            selectedDates: ['none'],
            hasMatchingStudentQuestion: false,
            requiredMatchingQuestionId: null,
            quillInstances: {}
        };
    },

    watch: {
        newType(newVal) {
            if (!newVal) {
                this.cleanupQuestionEditors();
                this.newQuestions = [];
                return;
            }

            this.initialQuestionCount = 1;

            this.newQuestions = [
                this.createQuestion(
                    newVal,
                    this.usesOptions(newVal) ? [""] : [],
                    "",
                    false,
                    this.isLinearScale(newVal) ? { min: 1, max: 5, left: "", right: "" } : {}
                )
            ];

            this.$nextTick(() => {
                this.initQuestionEditors();
            });
        },

        initialQuestionCount(newVal) {
            if (!this.newType) return;

            const needsOptions = this.usesOptions(this.newType);
            const isLS = this.isLinearScale(this.newType);
            const currentCount = this.newQuestions.length;

            if (newVal > currentCount) {
                for (let i = currentCount; i < newVal; i++) {
                    this.newQuestions.push(
                        this.createQuestion(
                            this.newType,
                            needsOptions ? [""] : [],
                            "",
                            this.newRequired,
                            isLS ? { min: 1, max: 5, left: "", right: "" } : {}
                        )
                    );
                }
            } else if (newVal < currentCount) {
                this.newQuestions.splice(newVal, currentCount - newVal);
            }

            this.$nextTick(() => {
                this.initQuestionEditors();
            });
        }
    },

    computed: {
        currentSection() {
            return this.sections[this.currentSectionIndex] || null;
        }
    },

    methods: {
        createQuestion(type, options = [], text = "", required = false, scale = {}) {
            const q = {
                type,
                text,
                required,
                options: options.length ? [...options] : (this.usesOptions(type) ? [""] : []),
                matchStudent: "none",
            };

            if (type === "Linear Scale") {
                q.scaleMin = scale.min || 1;
                q.scaleMax = scale.max || 5;
                q.scaleLabelLeft = scale.left || "";
                q.scaleLabelRight = scale.right || "";
            }

            return q;
        },

        initDescriptionEditor() {
            const editorEl = document.getElementById('descriptionEditor');
            if (!editorEl) return;

            // Clean up existing instance
            if (this.quillInstances.description) {
                try {
                    this.quillInstances.description.off('text-change');
                    delete this.quillInstances.description;
                } catch (e) { }
            }

            try {
                const quill = new Quill(editorEl, {
                    theme: 'snow',
                    modules: {
                        toolbar: [
                            ['bold', 'italic', 'underline'],
                            ['link'],
                            [{ 'list': 'ordered' }, { 'list': 'bullet' }]
                        ]
                    }
                });

                if (this.description) {
                    quill.root.innerHTML = this.description;
                }

                quill.on('text-change', () => {
                    this.description = quill.root.innerHTML;
                });

                this.quillInstances.description = quill;
            } catch (error) {
                console.error('Error initializing description editor:', error);
            }
        },

        initQuestionEditors() {
            // Clean up existing editors first
            this.cleanupQuestionEditors();

            this.$nextTick(() => {
                this.newQuestions.forEach((q, index) => {
                    const editorId = "editor-" + index;
                    const editorEl = document.getElementById(editorId);
                    if (!editorEl) return;

                    // Check if editor element already has Quill content
                    if (editorEl.querySelector('.ql-editor')) {
                        return; // Skip if already initialized
                    }

                    try {
                        const quill = new Quill(editorEl, {
                            theme: "snow",
                            modules: {
                                toolbar: [
                                    ["bold", "italic", "underline"],
                                    [{ list: "ordered" }, { list: "bullet" }]
                                ]
                            }
                        });

                        quill.root.innerHTML = q.text || "";

                        quill.on("text-change", () => {
                            q.text = quill.root.innerHTML;
                        });

                        this.quillInstances[editorId] = quill;
                    } catch (error) {
                        console.error('Error initializing Quill editor:', error);
                    }
                });
            });
        },

        cleanupQuestionEditors() {
            // Clean up only the question editors, not the description editor
            Object.keys(this.quillInstances).forEach(key => {
                if (key.startsWith('editor-')) {
                    try {
                        this.quillInstances[key].off('text-change');
                    } catch (e) {
                        console.warn('Error cleaning up editor:', e);
                    }
                    delete this.quillInstances[key];
                }
            });
        },

        cleanupPreviewEditor() {
            const editorId = 'editor-preview';
            if (this.quillInstances[editorId]) {
                try {
                    this.quillInstances[editorId].off('text-change');
                    delete this.quillInstances[editorId];
                } catch (e) {
                    console.warn('Error cleaning up preview editor:', e);
                }
            }
        },

        checkForMatchingStudentQuestion() {
            this.hasMatchingStudentQuestion = false;
            this.requiredMatchingQuestionId = null;

            this.sections.forEach((section, sectionIndex) => {
                section.questions.forEach((question, questionIndex) => {
                    if (question.type === 'Short Answer' &&
                        (question.matchStudent === 'name' || question.matchStudent === 'student_id')) {
                        this.hasMatchingStudentQuestion = true;
                        this.requiredMatchingQuestionId = `${sectionIndex}-${questionIndex}`;
                        question.required = true;
                        const hasRestrictions = (this.selectedCourses.length > 0 && !this.selectedCourses.includes('none')) ||
                            (this.selectedDates.length > 0 && !this.selectedDates.includes('none'));
                        if (hasRestrictions) {
                            question.isRestrictionRequired = true;
                        }
                    }
                });
            });
        },

        handleRestrictionChange() {
            const coursesSelect = document.getElementById('allowedCourses');
            const datesSelect = document.getElementById('allowedGraduationDates');

            if (!coursesSelect || !datesSelect) return;

            this.selectedCourses = Array.from(coursesSelect.selectedOptions).map(opt => opt.value);
            this.selectedDates = Array.from(datesSelect.selectedOptions).map(opt => opt.value);

            if (this.selectedCourses.length > 1 && this.selectedCourses.includes('none')) {
                this.selectedCourses = this.selectedCourses.filter(c => c !== 'none');
                Array.from(coursesSelect.options).forEach(option => {
                    if (option.value === 'none') option.selected = false;
                });
            }

            if (this.selectedDates.length > 1 && this.selectedDates.includes('none')) {
                this.selectedDates = this.selectedDates.filter(d => d !== 'none');
                Array.from(datesSelect.options).forEach(option => {
                    if (option.value === 'none') option.selected = false;
                });
            }

            const hasCourses = this.selectedCourses.length > 0 && !this.selectedCourses.includes('none');
            const hasDates = this.selectedDates.length > 0 && !this.selectedDates.includes('none');

            if (hasCourses || hasDates) {
                this.checkForMatchingStudentQuestion();

                if (!this.hasMatchingStudentQuestion) {
                    const matchingQuestion = this.createQuestion(
                        'Short Answer',
                        [],
                        'Please enter your full name',
                        true,
                        {}
                    );
                    matchingQuestion.matchStudent = 'name';
                    matchingQuestion.required = true;
                    matchingQuestion.isRestrictionRequired = true;

                    this.sections[0].questions.unshift(matchingQuestion);
                    this.hasMatchingStudentQuestion = true;
                    this.requiredMatchingQuestionId = `0-0`;

                    this.$nextTick(() => {
                        alert("A matching student question has been automatically added because you selected course or graduation date restrictions.");
                    });
                } else {
                    const [sectionIndex, questionIndex] = this.requiredMatchingQuestionId.split('-').map(Number);
                    if (this.sections[sectionIndex] && this.sections[sectionIndex].questions[questionIndex]) {
                        this.sections[sectionIndex].questions[questionIndex].isRestrictionRequired = true;
                        this.sections[sectionIndex].questions[questionIndex].required = true;
                    }
                }
            } else {
                this.sections.forEach(section => {
                    section.questions.forEach(question => {
                        if (question.isRestrictionRequired) {
                            question.isRestrictionRequired = false;
                        }
                    });
                });
                this.checkForMatchingStudentQuestion();
            }
        },

        usesOptions(type) {
            return ["Multiple Choice", "Checkbox", "Dropdown"].includes(type);
        },

        isLinearScale(type) {
            return type === "Linear Scale";
        },

        addSection() {
            if (!this.newSectionTitle || !this.newSectionTitle.trim()) {
                alert("Section title cannot be empty.");
                return;
            }
            this.sections.push({
                title: this.newSectionTitle.trim(),
                questions: []
            });
            this.newSectionTitle = "";
            this.currentSectionIndex = this.sections.length - 1;

            if (this.sections.length === 1) {
                this.$nextTick(() => {
                    this.initRestrictionDefaults();
                });
            }
        },

        initRestrictionDefaults() {
            const coursesSelect = document.getElementById('allowedCourses');
            const datesSelect = document.getElementById('allowedGraduationDates');

            if (coursesSelect) {
                Array.from(coursesSelect.options).forEach(option => {
                    option.selected = option.value === 'none';
                });
                this.selectedCourses = ['none'];
            }

            if (datesSelect) {
                Array.from(datesSelect.options).forEach(option => {
                    option.selected = option.value === 'none';
                });
                this.selectedDates = ['none'];
            }
        },

        addQuestionToSection() {
            if (!this.sections[this.currentSectionIndex]) {
                alert("Please create a section first.");
                return;
            }

            let hasError = false;

            for (let i = 0; i < this.newQuestions.length; i++) {
                const q = this.newQuestions[i];
                const editorId = "editor-" + i;
                const quill = this.quillInstances[editorId];

                if (!quill || !quill.getText().trim()) {
                    hasError = true;
                    alert(`Question ${i + 1} text cannot be empty.`);
                    break;
                }

                q.text = quill.root.innerHTML;

                if (this.usesOptions(q.type)) {
                    if (!q.options || q.options.length === 0) {
                        hasError = true;
                        alert(`Question ${i + 1} must have at least one option.`);
                        break;
                    }

                    const emptyOptions = q.options.filter(opt => !opt.trim());
                    if (emptyOptions.length > 0) {
                        hasError = true;
                        alert(`All options for question ${i + 1} must be filled.`);
                        break;
                    }
                }
            }

            if (hasError) return;

            this.newQuestions.forEach(q => {
                const question = this.createQuestion(
                    q.type,
                    this.usesOptions(q.type) ? q.options.map(o => o.trim()) : [],
                    q.text,
                    q.required,
                    this.isLinearScale(q.type)
                        ? {
                            min: q.scaleMin,
                            max: q.scaleMax,
                            left: q.scaleLabelLeft,
                            right: q.scaleLabelRight
                        }
                        : {}
                );

                question.matchStudent = q.matchStudent || "none";
                question.sectionMoveTarget = this.currentSectionIndex;

                this.sections[this.currentSectionIndex].questions.push(question);
            });

            this.resetDraft();
        },

        resetDraft() {
            this.cleanupQuestionEditors();
            this.newType = "";
            this.newRequired = false;
            this.initialQuestionCount = 1;
            this.newQuestions = [];
        },

        addOption(q) {
            if (!this.usesOptions(q.type)) return;
            if (!q.options) q.options = [];
            q.options.push("");
        },

        removeOption(q, i) {
            if (!q.options || q.options.length <= 1) return;
            q.options.splice(i, 1);
        },

        submitSurvey(event) {
            event.preventDefault();

            const coursesSelect = document.getElementById('allowedCourses');
            const datesSelect = document.getElementById('allowedGraduationDates');

            const selectedCourses = coursesSelect ?
                Array.from(coursesSelect.selectedOptions).map(opt => opt.value) : [];
            const selectedDates = datesSelect ?
                Array.from(datesSelect.selectedOptions).map(opt => opt.value) : [];

            const hasRestrictions = (selectedCourses.length > 0 && !selectedCourses.includes('none')) ||
                (selectedDates.length > 0 && !selectedDates.includes('none'));

            if (hasRestrictions && !this.hasMatchingStudentQuestion) {
                alert("You need at least one matching student question (Short Answer with Name or ID matching) because you have course or graduation date restrictions.");
                return;
            }

            if (!this.title.trim()) {
                alert("Survey title cannot be empty.");
                return;
            }

            if (this.sections.length === 0) {
                alert("You must create at least one section.");
                return;
            }

            const totalQuestions = this.sections.reduce((sum, s) => sum + (s.questions?.length || 0), 0);
            if (totalQuestions === 0) {
                alert("You must add at least one question before publishing.");
                return;
            }

            const formattedExpiry = this.expiry ? new Date(this.expiry).toISOString().slice(0, 19).replace("T", " ") : null;
            const form = event.target;

            // Set the hidden inputs
            document.getElementById('allowedCoursesInput').value = selectedCourses.includes('none') ? '' : selectedCourses.join(',');
            document.getElementById('allowedGraduationDatesInput').value = selectedDates.includes('none') ? '' : selectedDates.join(',');

            // Create and append the expiry input
            const expiryInput = document.createElement("input");
            expiryInput.type = "hidden";
            expiryInput.name = "expiry";
            expiryInput.value = formattedExpiry;
            form.appendChild(expiryInput);

            // Create and append the questions JSON input
            const payload = {
                title: this.title,
                description: this.description,
                sections: this.sections.map(section => ({
                    secTitle: section.title,
                    questions: section.questions.map(q => {
                        const clean = {
                            type: q.type,
                            text: q.text,
                            required: q.required,
                            matchStudent: q.matchStudent || "none",
                            isRestrictionRequired: q.isRestrictionRequired || false
                        };

                        if (this.usesOptions(q.type) && q.options) {
                            clean.options = q.options;
                        }

                        if (this.isLinearScale(q.type)) {
                            clean.scaleMin = q.scaleMin || 1;
                            clean.scaleMax = q.scaleMax || 5;
                            clean.scaleLabelLeft = q.scaleLabelLeft || "";
                            clean.scaleLabelRight = q.scaleLabelRight || "";
                        }

                        return clean;
                    })
                }))
            };

            const sectionsInput = document.createElement("input");
            sectionsInput.type = "hidden";
            sectionsInput.name = "questions";
            sectionsInput.value = JSON.stringify(payload);
            form.appendChild(sectionsInput);

            form.submit();
        },

        deleteQuestion(index) {
            const question = this.sections[this.currentSectionIndex].questions[index];
            if (!question) return;

            if (question.isRestrictionRequired) {
                const hasRestrictions = (this.selectedCourses.length > 0 && !this.selectedCourses.includes('none')) ||
                    (this.selectedDates.length > 0 && !this.selectedDates.includes('none'));

                if (hasRestrictions) {
                    const confirmed = confirm("This question is required because you have course or graduation date restrictions. If you delete it, you must add another matching student question (Short Answer with Name or ID matching). Continue?");
                    if (!confirmed) return;

                    alert("Please add another matching student question before deleting this one.");
                    return;
                }
            }

            const confirmed = confirm("Are you sure you want to delete this question?");
            if (!confirmed) return;

            this.sections[this.currentSectionIndex].questions.splice(index, 1);
            this.checkForMatchingStudentQuestion();

            const hasRestrictions = (this.selectedCourses.length > 0 && !this.selectedCourses.includes('none')) ||
                (this.selectedDates.length > 0 && !this.selectedDates.includes('none'));

            if (hasRestrictions && !this.hasMatchingStudentQuestion) {
                alert("You need at least one matching student question (Short Answer with Name or ID matching) because you have course or graduation date restrictions. A new matching question has been added.");

                const matchingQuestion = this.createQuestion(
                    'Short Answer',
                    [],
                    'Please enter your full name',
                    true,
                    {}
                );
                matchingQuestion.matchStudent = 'name';
                matchingQuestion.required = true;
                matchingQuestion.isRestrictionRequired = true;

                this.sections[0].questions.unshift(matchingQuestion);
                this.hasMatchingStudentQuestion = true;
                this.requiredMatchingQuestionId = `0-0`;
            }

            if (this.editingQuestionIndex === index) {
                this.cancelEditingQuestion();
            }
        },

        moveQuestionUp(index) {
            if (index <= 0) return;
            const list = this.sections[this.currentSectionIndex].questions;
            const item = list.splice(index, 1)[0];
            list.splice(index - 1, 0, item);
        },

        moveQuestionDown(index) {
            const list = this.sections[this.currentSectionIndex].questions;
            if (index >= list.length - 1) return;
            const item = list.splice(index, 1)[0];
            list.splice(index + 1, 0, item);
        },

        editQuestion(qIndex) {
            const section = this.sections[this.currentSectionIndex];
            if (!section || !section.questions[qIndex]) return;

            if (this.editingQuestionIndex !== null && this.editingQuestionIndex !== qIndex) {
                this.saveEditingQuestion();
            }

            const q = section.questions[qIndex];

            this.editingQuestionIndex = qIndex;
            this.editingQuestion = {
                ...q,
                options: q.options ? [...q.options] : [],
                scaleMin: q.scaleMin || 1,
                scaleMax: q.scaleMax || 5,
                scaleLabelLeft: q.scaleLabelLeft || "",
                scaleLabelRight: q.scaleLabelRight || "",
                matchStudent: q.matchStudent || "none"
            };

            this.$nextTick(() => {
                this.initPreviewEditor(qIndex);
            });
        },

        initPreviewEditor(qIndex) {
            const editorId = "editor-preview-" + qIndex;
            const containerId = "editor-preview-container-" + qIndex;

            // Clean up existing instance
            this.cleanupPreviewEditor();

            // Find or create container
            let editorEl = document.getElementById(editorId);
            if (!editorEl) {
                // Create new container
                const inlineEditor = document.querySelector(`[data-question-index="${qIndex}"] .inline-editor`);
                if (inlineEditor) {
                    const container = document.createElement('div');
                    container.id = containerId;
                    const editorDiv = document.createElement('div');
                    editorDiv.id = editorId;
                    container.appendChild(editorDiv);

                    // Find the existing editor div and replace it
                    const existingEditor = inlineEditor.querySelector('div[id^="editor-preview-"]');
                    if (existingEditor) {
                        existingEditor.parentNode.replaceChild(container, existingEditor);
                    } else {
                        const mb2Div = inlineEditor.querySelector('.mb-2');
                        if (mb2Div) {
                            mb2Div.parentNode.replaceChild(container, mb2Div);
                        }
                    }
                    editorEl = document.getElementById(editorId);
                }
            }

            if (!editorEl) return;

            // Check if already has Quill instance
            if (editorEl.querySelector('.ql-editor')) {
                return;
            }

            try {
                const quill = new Quill(editorEl, {
                    theme: "snow",
                    modules: {
                        toolbar: [
                            ["bold", "italic", "underline"],
                            [{ list: "ordered" }, { list: "bullet" }]
                        ]
                    }
                });

                quill.root.innerHTML = this.editingQuestion.text || "";
                quill.on("text-change", () => {
                    this.editingQuestion.text = quill.root.innerHTML;
                });

                this.quillInstances['editor-preview'] = quill;
            } catch (error) {
                console.error('Error initializing preview editor:', error);
            }

            const qi = document.querySelectorAll(".question-item")[qIndex];
            if (qi) qi.classList.add("editing");
        },

        saveEditingQuestion() {
            if (this.editingQuestionIndex === null || !this.editingQuestion) return;

            const qIndex = this.editingQuestionIndex;
            const quill = this.quillInstances['editor-preview'];

            if (quill) {
                this.editingQuestion.text = quill.root.innerHTML;
            }

            // Validate options for option-type questions
            if (this.usesOptions(this.editingQuestion.type)) {
                if (!this.editingQuestion.options || this.editingQuestion.options.length === 0) {
                    alert("This question type requires at least one option.");
                    return;
                }

                const emptyOptions = this.editingQuestion.options.filter(opt => !opt.trim());
                if (emptyOptions.length > 0) {
                    alert("All options must be filled.");
                    return;
                }
            }

            // Final validation: Check if we still have a matching question when restrictions exist
            const hasRestrictions = (this.selectedCourses.length > 0 && !this.selectedCourses.includes('none')) ||
                (this.selectedDates.length > 0 && !this.selectedDates.includes('none'));

            if (hasRestrictions) {
                // Check if we have at least one matching question after this change
                let hasMatchingQuestion = false;

                // Temporarily apply the changes to check
                const tempQuestion = { ...this.sections[this.currentSectionIndex].questions[qIndex] };
                Object.assign(tempQuestion, this.editingQuestion);

                // Check all questions including the updated one
                this.sections.forEach((section, sectionIndex) => {
                    section.questions.forEach((question, questionIndex) => {
                        const questionToCheck = (sectionIndex === this.currentSectionIndex &&
                            questionIndex === qIndex) ? tempQuestion : question;

                        if (questionToCheck.type === 'Short Answer' &&
                            (questionToCheck.matchStudent === 'name' || questionToCheck.matchStudent === 'student_id')) {
                            hasMatchingQuestion = true;
                        }
                    });
                });

                if (!hasMatchingQuestion) {
                    alert("Error: You must have at least one Short Answer question with Name or Student ID matching because you have course or graduation date restrictions."
                        + "\n\nPlease add a matching student question before saving.");
                    return;
                }
            }

            // Save changes back to the original question
            const originalQuestion = this.sections[this.currentSectionIndex].questions[qIndex];
            Object.assign(originalQuestion, this.editingQuestion);

            this.cancelEditingQuestion();
        },

        cancelEditingQuestion() {
            if (this.editingQuestionIndex === null) return;

            this.cleanupPreviewEditor();

            const qIndex = this.editingQuestionIndex;
            const qi = document.querySelectorAll(".question-item")[qIndex];
            if (qi) qi.classList.remove("editing");

            this.editingQuestionIndex = null;
            this.editingQuestion = null;
        },

        addInlineOption() {
            if (!this.editingQuestion) return;
            if (!this.editingQuestion.options) this.editingQuestion.options = [];
            this.editingQuestion.options.push("");
            console.log('Added option, options now:', this.editingQuestion.options);
        },

        removeInlineOption(i) {
            if (!this.editingQuestion?.options || this.editingQuestion.options.length <= 1) return;
            this.editingQuestion.options.splice(i, 1);
            console.log('Removed option, options now:', this.editingQuestion.options);
        },

        toggleEditSectionTitle() {
            if (this.editingSectionIndex === this.currentSectionIndex) {
                this.editingSectionIndex = null;
            } else {
                this.editingSectionIndex = this.currentSectionIndex;
            }
        },

        handleTypeChange(event) {
            if (!this.editingQuestion) return;

            // Get the new type from the event
            const newType = event.target.value;
            const oldType = this.editingQuestion.type;
            console.log('Question type changed from', oldType, 'to:', newType);

            // Check if we have restrictions
            const hasRestrictions = (this.selectedCourses.length > 0 && !this.selectedCourses.includes('none')) ||
                (this.selectedDates.length > 0 && !this.selectedDates.includes('none'));

            // If we're changing FROM Short Answer with matchStudent set, and we have restrictions
            if (oldType === 'Short Answer' &&
                this.editingQuestion.matchStudent !== 'none' &&
                hasRestrictions) {

                // Check if there's another matching question in the survey
                let hasOtherMatchingQuestion = false;

                this.sections.forEach((section, sectionIndex) => {
                    section.questions.forEach((question, questionIndex) => {
                        // Skip the current question we're editing
                        if (sectionIndex === this.currentSectionIndex &&
                            questionIndex === this.editingQuestionIndex) {
                            return;
                        }

                        if (question.type === 'Short Answer' &&
                            (question.matchStudent === 'name' || question.matchStudent === 'student_id')) {
                            hasOtherMatchingQuestion = true;
                        }
                    });
                });

                // If changing to non-Short Answer type and no other matching question exists
                if (newType !== 'Short Answer' && !hasOtherMatchingQuestion) {
                    const confirmed = confirm(
                        "You have course or graduation date restrictions. Changing this question type will remove the matching student field.\n\n" +
                        "You need at least one Short Answer question with Name or Student ID matching to validate respondents.\n\n" +
                        "Please add another matching student question first, or keep this as a Short Answer question with matching."
                    );

                    if (!confirmed) {
                        // Revert to old type
                        event.target.value = oldType;
                        this.editingQuestion.type = oldType;
                        return;
                    }
                }
            }

            // Update the editing question type
            this.editingQuestion.type = newType;

            // Handle options based on question type
            if (this.usesOptions(newType)) {
                if (!this.editingQuestion.options || this.editingQuestion.options.length === 0) {
                    this.editingQuestion.options = [""];
                }
            } else {
                this.editingQuestion.options = [];
            }

            // Handle linear scale properties
            if (this.isLinearScale(newType)) {
                this.editingQuestion.scaleMin = this.editingQuestion.scaleMin || 1;
                this.editingQuestion.scaleMax = this.editingQuestion.scaleMax || 5;
                this.editingQuestion.scaleLabelLeft = this.editingQuestion.scaleLabelLeft || "";
                this.editingQuestion.scaleLabelRight = this.editingQuestion.scaleLabelRight || "";
            } else {
                delete this.editingQuestion.scaleMin;
                delete this.editingQuestion.scaleMax;
                delete this.editingQuestion.scaleLabelLeft;
                delete this.editingQuestion.scaleLabelRight;
            }

            // Reset matchStudent if not Short Answer
            if (newType !== 'Short Answer') {
                this.editingQuestion.matchStudent = "none";
            }

            console.log('Updated editingQuestion:', this.editingQuestion);
        },

        handleMatchStudentChange(event) {
            if (!this.editingQuestion) return;

            const newMatchValue = event.target.value;
            const oldMatchValue = this.editingQuestion.matchStudent;

            console.log('Match student changed from', oldMatchValue, 'to:', newMatchValue);

            // Check if we have restrictions
            const hasRestrictions = (this.selectedCourses.length > 0 && !this.selectedCourses.includes('none')) ||
                (this.selectedDates.length > 0 && !this.selectedDates.includes('none'));

            // If changing FROM a matching value to "none" and we have restrictions
            if (oldMatchValue !== 'none' && newMatchValue === 'none' && hasRestrictions) {

                // Check if there's another matching question in the survey
                let hasOtherMatchingQuestion = false;

                this.sections.forEach((section, sectionIndex) => {
                    section.questions.forEach((question, questionIndex) => {
                        // Skip the current question we're editing
                        if (sectionIndex === this.currentSectionIndex &&
                            questionIndex === this.editingQuestionIndex) {
                            return;
                        }

                        if (question.type === 'Short Answer' &&
                            (question.matchStudent === 'name' || question.matchStudent === 'student_id')) {
                            hasOtherMatchingQuestion = true;
                        }
                    });
                });

                if (!hasOtherMatchingQuestion) {
                    const confirmed = confirm(
                        "You have course or graduation date restrictions. Removing the matching student field requires at least one other Short Answer question with Name or Student ID matching.\n\n" +
                        "Please add another matching student question first, or keep this question with matching enabled.\n\n" +
                        "Cancel to keep matching enabled, OK to remove matching anyway?"
                    );

                    if (!confirmed) {
                        // Revert to old value
                        event.target.value = oldMatchValue;
                        this.editingQuestion.matchStudent = oldMatchValue;
                        return;
                    }
                }
            }

            // Update the match student value
            this.editingQuestion.matchStudent = newMatchValue;

            // AUTOMATICALLY SET REQUIRED FOR MATCHING QUESTIONS
            if (newMatchValue !== 'none') {
                this.editingQuestion.required = true;
            }

            console.log('Updated matchStudent:', this.editingQuestion.matchStudent, 'Required:', this.editingQuestion.required);
        },

        hasOtherMatchingQuestion(currentSectionIndex, currentQuestionIndex) {
            let hasOther = false;

            this.sections.forEach((section, sectionIndex) => {
                section.questions.forEach((question, questionIndex) => {
                    // Skip the current question
                    if (sectionIndex === currentSectionIndex && questionIndex === currentQuestionIndex) {
                        return;
                    }

                    if (question.type === 'Short Answer' &&
                        (question.matchStudent === 'name' || question.matchStudent === 'student_id')) {
                        hasOther = true;
                    }
                });
            });

            return hasOther;
        },

        handleNewQuestionRequiredChange(question) {
            // If this question has matching student enabled, don't allow unchecking required
            if (question.matchStudent !== 'none' && !question.required) {
                alert("This question is required because it has matching student enabled. Matching questions must be required to validate respondents.");
                question.required = true;
            }
        },

        handleNewQuestionMatchStudentChange(question) {
            const newMatchValue = question.matchStudent;

            // AUTOMATICALLY SET REQUIRED FOR MATCHING QUESTIONS
            if (newMatchValue !== 'none') {
                question.required = true;
            }

            console.log('New question matchStudent changed to:', newMatchValue, 'Required:', question.required);
        },

        filterCourses() {
            const searchTerm = this.courseSearchTerm.toLowerCase().trim();
            const select = document.getElementById('allowedCourses');
            if (!select) return;

            Array.from(select.options).forEach(option => {
                if (option.value === 'none') {
                    option.style.display = '';
                    return;
                }
                const text = option.text.toLowerCase();
                option.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        },

        filterDates() {
            const searchTerm = this.dateSearchTerm.toLowerCase().trim();
            const select = document.getElementById('allowedGraduationDates');
            if (!select) return;

            Array.from(select.options).forEach(option => {
                if (option.value === 'none') {
                    option.style.display = '';
                    return;
                }
                const text = option.text.toLowerCase();
                option.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        },

        clearCourseSearch() {
            this.courseSearchTerm = "";
            const select = document.getElementById('allowedCourses');
            if (select) {
                Array.from(select.options).forEach(option => {
                    option.style.display = '';
                });
            }
        },

        clearDateSearch() {
            this.dateSearchTerm = "";
            const select = document.getElementById('allowedGraduationDates');
            if (select) {
                Array.from(select.options).forEach(option => {
                    option.style.display = '';
                });
            }
        },

        handleRequiredChange(event) {
            if (!this.editingQuestion) return;

            const isChecked = event.target.checked;

            // If this question has matching student enabled, don't allow unchecking required
            if (this.editingQuestion.matchStudent !== 'none' && !isChecked) {
                alert("This question is required because it has matching student enabled. Matching questions must be required to validate respondents.");
                event.target.checked = true; // Re-check the checkbox
                this.editingQuestion.required = true;
                return;
            }

            this.editingQuestion.required = isChecked;
        },

        moveQuestionToSection(qIndex) {
            const question = this.sections[this.currentSectionIndex].questions[qIndex];
            if (question.sectionMoveTarget === undefined ||
                question.sectionMoveTarget === this.currentSectionIndex) return;

            const targetIndex = parseInt(question.sectionMoveTarget);
            const targetSection = this.sections[targetIndex];
            if (!targetSection) return;

            this.sections[this.currentSectionIndex].questions.splice(qIndex, 1);
            targetSection.questions.push(question);

            question.sectionMoveTarget = targetIndex;
        },

        goToNextSection() {
            if (this.currentSectionIndex < this.sections.length - 1) {
                this.currentSectionIndex++;
            }
        },

        goToPreviousSection() {
            if (this.currentSectionIndex > 0) {
                this.currentSectionIndex--;
            }
        },

        cleanupAll() {
            Object.keys(this.quillInstances).forEach(key => {
                try {
                    this.quillInstances[key].off('text-change');
                } catch (e) {
                    console.warn('Error cleaning up Quill instance:', e);
                }
            });
            this.quillInstances = {};
        }
    },

    mounted() {
        this.initDescriptionEditor();

        const coursesSelect = document.getElementById('allowedCourses');
        const datesSelect = document.getElementById('allowedGraduationDates');

        if (coursesSelect) {
            coursesSelect.addEventListener('change', () => this.handleRestrictionChange());
        }

        if (datesSelect) {
            datesSelect.addEventListener('change', () => this.handleRestrictionChange());
        }

        document.addEventListener('click', (event) => {
            if (this.editingQuestionIndex === null) return;

            const editorEl = document.querySelector(`#editor-preview-${this.editingQuestionIndex}`);
            const questionItem = document.querySelectorAll(".question-item")[this.editingQuestionIndex];

            const isInsideEditor = editorEl && editorEl.contains(event.target);
            const isInsideQuestion = questionItem && questionItem.contains(event.target);
            const isToolbar = event.target.closest('.ql-toolbar');

            if (!isInsideEditor && !isInsideQuestion && !isToolbar) {
                this.saveEditingQuestion();
            }
        });

        window.addEventListener('beforeunload', () => {
            this.cleanupAll();
        });
    },

    beforeUnmount() {
        this.cleanupAll();

        const coursesSelect = document.getElementById('allowedCourses');
        const datesSelect = document.getElementById('allowedGraduationDates');

        if (coursesSelect) {
            coursesSelect.removeEventListener('change', () => this.handleRestrictionChange());
        }

        if (datesSelect) {
            datesSelect.removeEventListener('change', () => this.handleRestrictionChange());
        }
    }
}).mount("#surveycreation");