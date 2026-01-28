document.addEventListener('DOMContentLoaded', function () {
    // ========== DOM ELEMENTS & GLOBAL VARIABLES ==========
    const elements = {
        questionType: document.getElementById('questionType'),
        optionsContainer: document.getElementById('optionsContainer'),
        addQuestionBtn: document.getElementById('addQuestionBtn'),
        previewQuestions: document.getElementById('previewQuestions'),
        questionsDataInput: document.getElementById('questionsData'),
        surveyForm: document.getElementById('surveyForm'),
        questionRequired: document.getElementById('questionRequired'),
        arrangeViewBtn: document.getElementById('arrangeViewBtn'),
        addSectionBtn: document.getElementById('addSectionBtn'),
        prevSectionBtn: document.getElementById('prevSectionBtn'),
        nextSectionBtn: document.getElementById('nextSectionBtn'),
        currentSectionLabel: document.getElementById('currentSectionLabel'),
        descriptionPreview: document.getElementById('descriptionPreview')
    };

    let state = {
        description: window.surveyDescription || '',
        sections: (window.surveySections || []).map(sec => ({
            title: sec.secTitle || sec.title || 'Untitled Section',
            questions: (sec.questions || []).map(q => ({
                text: q.text || '',
                type: q.type || 'short',
                options: q.options || [],
                required: !!q.required,
                showRequired: !!q.required,
                matchStudent: q.matchStudent || "none",
                scaleMax: q.type === 'linear_scale' ? (q.scaleMax ? Number(q.scaleMax) : 5) : undefined,
                labelLeft: q.type === 'linear_scale' ? (q.labelLeft || q.scaleLabelLeft || '') : '',
                labelRight: q.type === 'linear_scale' ? (q.labelRight || q.scaleLabelRight || '') : '',
                isRestrictionRequired: q.isRestrictionRequired || false,
                scaleMax: q.type === 'linear_scale' ? (q.scaleMax ? Number(q.scaleMax) : 5) : undefined,
                labelLeft: q.type === 'linear_scale' ? (q.labelLeft || q.scaleLabelLeft || '') : '',
                labelRight: q.type === 'linear_scale' ? (q.labelRight || q.scaleLabelRight || '') : ''
            }))
        })),
        currentSectionIndex: 0,
        highlightedQuestions: new Set(),
        scrollPosition: 0,
        isInitialLoad: true,
        isArrangeView: false,
        originalQuestionPositions: new Map(),
        allowedCourses: [],
        allowedGraduationDates: [],
        hasMatchingStudentQuestion: false,
        requiredMatchingQuestionId: null
    };

    const addQuestionQuill = new Quill('#questionTextEditor', {
        theme: 'snow',
        modules: { toolbar: ['bold', 'italic', 'underline', { list: 'ordered' }, { list: 'bullet' }] }
    });

    // Initialize description editor
    const descriptionQuill = new Quill('#descriptionEditor', {
        theme: 'snow',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline'],
                ['link'],
                [{ 'list': 'ordered' }, { 'list': 'bullet' }]
            ]
        }
    });

    // Set initial description content
    if (state.description) {
        descriptionQuill.root.innerHTML = state.description;
    }

    // Update state when description changes
    descriptionQuill.on('text-change', () => {
        state.description = descriptionQuill.root.innerHTML;
        updateDescriptionPreview();
        updateQuestionsData();
    });

    // ========== UTILITY FUNCTIONS ==========
    const utils = {
        showError: (message) => alert(message),

        getQuestionId: (sectionIndex, questionIndex) => `question-${sectionIndex}-${questionIndex}`,

        getQuestionHash: (question) => btoa(encodeURIComponent(
            question.text + '|' + question.type + '|' + JSON.stringify(question.options)
        )),

        stripHtml: (html) => {
            const tmp = document.createElement('div');
            tmp.innerHTML = html;
            return tmp.textContent || tmp.innerText || '';
        },

        convertTypeForOutput: (t) => {
            const types = {
                "short": "Short Answer",
                "paragraph": "Paragraph",
                "multiple": "Multiple Choice",
                "checkbox": "Checkbox",
                "dropdown": "Dropdown",
                "date": "Date Picker",
                "linear_scale": "Linear Scale"
            };
            return types[t] || t;
        },

        getQuestionTypeBadge: (type) => {
            const badges = {
                'short': 'Short text', 'paragraph': 'Paragraph', 'multiple': 'Multiple Choice', 'checkbox': 'CheckBox',
                'dropdown': 'Dropdown', 'date': 'Date', 'linear_scale': 'Linear Scale'
            };
            return badges[type] || 'Q';
        },

        saveScrollPosition: () => state.scrollPosition = window.pageYOffset || document.documentElement.scrollTop,

        restoreScrollPosition: () => window.scrollTo(0, state.scrollPosition)
    };

    // ========== DESCRIPTION MANAGEMENT ==========
    const descriptionManager = {
        updateDescriptionPreview: () => {
            if (!elements.descriptionPreview) return;

            if (state.description && state.description.trim() &&
                state.description !== '<p><br></p>' && state.description !== '<br>') {
                elements.descriptionPreview.innerHTML = state.description;
            } else {
                elements.descriptionPreview.innerHTML = '<em class="text-muted">No description provided</em>';
            }
        }
    };

    const updateDescriptionPreview = () => descriptionManager.updateDescriptionPreview();

    // ========== VALIDATION FUNCTIONS ==========
    const validation = {
        validateQuillContent: (quill) => {
            if (!quill) return false;
            const text = quill.getText().trim();
            const html = quill.root.innerHTML.trim();
            return text.length > 0 && html !== '<p><br></p>' && html !== '<br>' &&
                html !== '<p></p>' && html !== '' && !/^<p>\s*<\/p>$/i.test(html) &&
                !/^<p>\s*<br>\s*<\/p>$/i.test(html);
        },

        highlightEmptyQuill: (editorEl) => {
            editorEl.classList.add('is-invalid');
            const quillContainer = editorEl.closest('.mb-3');
            if (quillContainer) {
                const feedback = quillContainer.querySelector('.invalid-feedback') ||
                    document.createElement('div');
                feedback.className = 'invalid-feedback';
                feedback.textContent = 'Question text is required';
                quillContainer.appendChild(feedback);
            }
        },

        removeQuillErrorHighlight: (editorEl) => {
            editorEl.classList.remove('is-invalid');
            const feedback = editorEl.closest('.mb-3')?.querySelector('.invalid-feedback');
            if (feedback) feedback.remove();
        },

        validateSectionQuestions: () => {
            return state.sections.map((section, index) => ({
                index, title: section.title || 'Untitled Section'
            })).filter(sec => {
                const currentSection = state.sections[sec.index];
                return !currentSection.questions || currentSection.questions.length === 0;
            });
        },

        validateRequiredForMatching: () => {
            let isValid = true;
            let errorMessages = [];

            state.sections.forEach((section, sIdx) => {
                section.questions.forEach((question, qIdx) => {
                    // Only check short answer questions
                    if (question.type === 'short' &&
                        question.matchStudent &&
                        question.matchStudent !== 'none' &&
                        !question.required) {

                        isValid = false;
                        const shortText = utils.stripHtml(question.text).substring(0, 50);
                        errorMessages.push(`Question "${shortText}..." must be required because it has matching student enabled.`);

                        // Highlight the problematic question
                        const questionElement = document.getElementById(`question-${sIdx}-${qIdx}`);
                        if (questionElement) {
                            questionElement.classList.add('invalid-matching-question');

                            // Force the required checkbox to be checked
                            const requiredCheckbox = questionElement.querySelector('.question-required-toggle');
                            if (requiredCheckbox) {
                                requiredCheckbox.checked = true;
                            }

                            // Also update the data model
                            question.required = true;
                        }
                    }
                });
            });

            if (!isValid) {
                // Fix the data model first
                updateQuestionsData();

                // Show error message
                utils.showError(`The following issues were found:\n\n${errorMessages.join('\n')}\n\nThese questions have been automatically marked as required.`);

                // Re-render to show the fixed state
                renderAllQuestions();
            }

            return isValid;
        },

        validateBeforeSubmit: () => {
            // Check for matching student question if restrictions exist
            const hasCourses = state.allowedCourses.length > 0 && !state.allowedCourses.includes('none');
            const hasDates = state.allowedGraduationDates.length > 0 && !state.allowedGraduationDates.includes('none');

            if ((hasCourses || hasDates) && !state.hasMatchingStudentQuestion) {
                utils.showError("You need at least one matching student question (Short Answer with Name or ID matching) because you have course or graduation date restrictions.");
                return false;
            }

            // Matching student validation
            if (!validation.validateRequiredForMatching()) {
                return false;
            }

            const emptySections = validation.validateSectionQuestions();
            if (emptySections.length > 0) {
                const sectionTitles = emptySections.map(sec => `"${sec.title}"`).join(', ');
                utils.showError(`The following sections have no questions:\n${sectionTitles}\n\nPlease add questions to these sections or delete them.`);
                if (emptySections.length > 0) {
                    state.currentSectionIndex = emptySections[0].index;
                    renderAllQuestions();
                    updateSectionNav();
                }
                return false;
            }
            return true;
        }
    };

    const matchStudentManager = {
        updateRequiredCheckboxState: function (questionElement, matchStudentValue) {
            // Find the required checkbox and its container
            const requiredCheckbox = questionElement.querySelector('.question-required-toggle');
            const requiredContainer = questionElement.querySelector('.form-check.form-switch');

            if (requiredCheckbox && requiredContainer) {
                if (matchStudentValue !== 'none') {
                    // For 'name' or 'student_id' - automatically check and disable
                    requiredCheckbox.checked = true;
                    requiredCheckbox.disabled = true;
                    requiredCheckbox.setAttribute('data-forced-required', 'true');

                    // Add visual indicator
                    requiredContainer.classList.add('required-forced');

                    // Add tooltip or help text
                    let forcedText = requiredContainer.querySelector('.forced-required-text');
                    if (!forcedText) {
                        forcedText = document.createElement('small');
                        forcedText.className = 'forced-required-text text-muted ms-2';
                        forcedText.innerHTML = '(required for matching)';
                        requiredContainer.appendChild(forcedText);
                    }
                } else {
                    // For 'none' - enable and remove forced state
                    requiredCheckbox.disabled = false;
                    requiredCheckbox.removeAttribute('data-forced-required');
                    requiredContainer.classList.remove('required-forced');

                    // Remove tooltip
                    const forcedText = requiredContainer.querySelector('.forced-required-text');
                    if (forcedText) {
                        forcedText.remove();
                    }
                }
            }
        },

        // Also update the form's new question match student handler
        updateNewQuestionMatchStudent: function () {
            const matchStudentSelect = document.getElementById('questionMatchStudent');
            const requiredCheckbox = document.getElementById('questionRequired');
            const requiredContainer = requiredCheckbox?.closest('.form-check.form-switch');

            if (matchStudentSelect && requiredCheckbox && requiredContainer) {
                matchStudentSelect.addEventListener('change', function () {
                    if (this.value !== 'none') {
                        // Automatically check and disable
                        requiredCheckbox.checked = true;
                        requiredCheckbox.disabled = true;
                        requiredContainer.classList.add('required-forced');

                        // Add tooltip
                        let forcedText = requiredContainer.querySelector('.forced-required-text');
                        if (!forcedText) {
                            forcedText = document.createElement('small');
                            forcedText.className = 'forced-required-text text-muted ms-2';
                            forcedText.innerHTML = '(required for matching)';
                            requiredContainer.appendChild(forcedText);
                        }
                    } else {
                        // Enable and remove forced state
                        requiredCheckbox.disabled = false;
                        requiredContainer.classList.remove('required-forced');

                        // Remove tooltip
                        const forcedText = requiredContainer.querySelector('.forced-required-text');
                        if (forcedText) forcedText.remove();
                    }
                });

                // Initialize state on page load
                if (matchStudentSelect.value !== 'none') {
                    requiredCheckbox.checked = true;
                    requiredCheckbox.disabled = true;
                    requiredContainer.classList.add('required-forced');

                    let forcedText = requiredContainer.querySelector('.forced-required-text');
                    if (!forcedText) {
                        forcedText = document.createElement('small');
                        forcedText.className = 'forced-required-text text-muted ms-2';
                        forcedText.innerHTML = '(required for matching)';
                        requiredContainer.appendChild(forcedText);
                    }
                }
            }
        },

        // Handle question type change with match student preservation
        handleQuestionTypeChange: function (wrapper, newType, oldType, question, sIdx, qIdx) {
            const matchStudentSelect = wrapper.querySelector('.question-match-student');
            const requiredCheckbox = wrapper.querySelector('.question-required-toggle');
            const requiredContainer = requiredCheckbox?.closest('.form-check.form-switch');

            // Store current match student value before changing type
            const currentMatchValue = matchStudentSelect ? matchStudentSelect.value : question.matchStudent;

            if (newType === 'short') {
                // Switching TO short answer - restore match student dropdown

                // Show match student container
                const matchStudentContainer = wrapper.querySelector('.d-flex.align-items-center.gap-2');
                if (matchStudentContainer) {
                    matchStudentContainer.classList.remove('d-none');
                }

                // Restore match student value if it exists
                if (matchStudentSelect) {
                    matchStudentSelect.value = currentMatchValue || 'none';

                    // Update required checkbox state based on match student value
                    if (currentMatchValue && currentMatchValue !== 'none') {
                        // Force required if match student is enabled
                        requiredCheckbox.checked = true;
                        requiredCheckbox.disabled = true;
                        if (requiredContainer) {
                            requiredContainer.classList.add('required-forced');

                            // Add tooltip
                            let forcedText = requiredContainer.querySelector('.forced-required-text');
                            if (!forcedText) {
                                forcedText = document.createElement('small');
                                forcedText.className = 'forced-required-text text-muted ms-2';
                                forcedText.innerHTML = '(required for matching)';
                                requiredContainer.appendChild(forcedText);
                            }
                        }

                        // Ensure question data model reflects this
                        question.required = true;
                    } else {
                        // Enable checkbox if no match student
                        requiredCheckbox.disabled = false;
                        if (requiredContainer) {
                            requiredContainer.classList.remove('required-forced');

                            // Remove tooltip
                            const forcedText = requiredContainer.querySelector('.forced-required-text');
                            if (forcedText) forcedText.remove();
                        }
                    }
                }

            } else {
                // Switching FROM short answer to another type

                // Hide match student container
                const matchStudentContainer = wrapper.querySelector('.d-flex.align-items-center.gap-2');
                if (matchStudentContainer) {
                    matchStudentContainer.classList.add('d-none');
                }

                // Enable required checkbox if it was disabled for matching
                if (requiredCheckbox && requiredCheckbox.disabled) {
                    requiredCheckbox.disabled = false;
                    if (requiredContainer) {
                        requiredContainer.classList.remove('required-forced');

                        // Remove tooltip
                        const forcedText = requiredContainer.querySelector('.forced-required-text');
                        if (forcedText) forcedText.remove();
                    }
                }

                // Reset matchStudent to 'none' in data model for non-short answer types
                question.matchStudent = "none";
            }

            // Update data model and UI
            updateQuestionsData();
            highlightManager.highlightQuestion(sIdx, qIdx);
        },

        // NEW FUNCTION: Validate and fix required state when switching question types
        validateRequiredStateOnTypeChange: function (question) {
            // If question has matchStudent enabled, it MUST be required
            if (question.matchStudent && question.matchStudent !== 'none' && !question.required) {
                console.warn('Fixing required state for matching question:', question);
                question.required = true;
                return true; // State was fixed
            }
            return false; // State was already correct
        }
    };

    // ========== HIGHLIGHT MANAGEMENT ==========
    const highlightManager = {
        highlightQuestion: (sIdx, qIdx) => {
            const question = state.sections[sIdx].questions[qIdx];
            const questionHash = utils.getQuestionHash(question);
            state.highlightedQuestions.add(questionHash);

            const wrapper = document.getElementById(utils.getQuestionId(sIdx, qIdx));
            if (wrapper) wrapper.classList.add('highlight-updated');
        },

        removeAllHighlights: () => {
            document.querySelectorAll('.question-item.highlight-updated').forEach(el => {
                el.classList.remove('highlight-updated');
            });
            state.highlightedQuestions.clear();
        },

        restoreHighlights: () => {
            document.querySelectorAll('.question-item.highlight-updated').forEach(el => {
                el.classList.remove('highlight-updated');
            });

            state.sections.forEach((section, sIdx) => {
                section.questions.forEach((question, qIdx) => {
                    const questionHash = utils.getQuestionHash(question);
                    if (state.highlightedQuestions.has(questionHash)) {
                        const wrapper = document.getElementById(utils.getQuestionId(sIdx, qIdx));
                        if (wrapper) wrapper.classList.add('highlight-updated');
                    }
                });
            });
        }
    };

    // ========== ARRANGE VIEW MANAGEMENT ==========
    const arrangeView = {
        enterArrangeView: (button) => {
            state.isArrangeView = true;
            button.innerHTML = '<i class="bi bi-check-circle"></i> Complete Arrangement';
            button.classList.replace('btn-warning', 'btn-success');
            arrangeView.storeOriginalPositions();
            document.querySelector('.body-survey-management').classList.add('arrange-view-active');
            arrangeView.disableOtherButtons(true);
            arrangeView.renderArrangeView();
            updateSectionNav();
        },

        exitArrangeView: (button) => {
            state.isArrangeView = false;
            button.innerHTML = '<i class="bi bi-arrows-move"></i> Arrange View';
            button.classList.replace('btn-success', 'btn-warning');
            document.querySelector('.body-survey-management').classList.remove('arrange-view-active');
            arrangeView.disableOtherButtons(false);
            renderAllQuestions();
            updateSectionNav();
        },

        disableOtherButtons: (disable) => {
            ['addQuestionBtn', 'addSectionBtn', 'updateSurveyBtn'].forEach(buttonId => {
                const button = document.getElementById(buttonId);
                if (button) {
                    button.disabled = disable;
                    button.classList.toggle('btn-disabled-arrange', disable);
                    button.style.opacity = disable ? '0.6' : '';
                    button.style.cursor = disable ? 'not-allowed' : '';
                }
            });

            document.querySelectorAll('#surveyForm input:not(.section-title-input), #surveyForm select:not(.move-section-dropdown), #surveyForm textarea')
                .forEach(input => {
                    if (input.id !== 'arrangeViewBtn') {
                        input.disabled = disable;
                        input.classList.toggle('input-disabled-arrange', disable);
                        input.style.opacity = disable ? '0.6' : '';
                        input.style.cursor = disable ? 'not-allowed' : '';
                    }
                });

            document.querySelectorAll('.ql-editor').forEach(editor => {
                if (!editor.closest('.section-header')) {
                    editor.setAttribute('contenteditable', !disable);
                    editor.classList.toggle('quill-disabled-arrange', disable);
                }
            });
        },

        storeOriginalPositions: () => {
            const currentSection = state.sections[state.currentSectionIndex];
            if (currentSection?.questions) {
                state.originalQuestionPositions.set(state.currentSectionIndex, [...currentSection.questions]);
            }
        },

        renderArrangeView: () => {
            const currentSection = state.sections[state.currentSectionIndex];
            if (!currentSection) return;

            elements.previewQuestions.innerHTML = `
                <div class="arrange-view-header mb-4">
                    <h5 class="text-center text-muted">
                        <i class="bi bi-grid-3x3-gap me-2"></i>
                        Arrange Questions for: "${currentSection.title}"
                    </h5>
                    <p class="text-center small text-muted mb-0">
                        Click anywhere on a question card to view details • Drag using the handle to rearrange
                    </p>
                </div>
                <div class="arrange-grid-container" id="arrangeGrid"></div>
            `;

            const gridContainer = document.getElementById('arrangeGrid');
            currentSection.questions.forEach((question, qIdx) => {
                const questionCard = document.createElement('div');
                const questionHash = utils.getQuestionHash(question);

                questionCard.className = `arrange-question-card ${state.highlightedQuestions.has(questionHash) ? 'highlight-updated' : ''}`;
                questionCard.dataset.section = state.currentSectionIndex;
                questionCard.dataset.index = qIdx;
                questionCard.innerHTML = `
                    <div class="arrange-card-header">
                        <div class="drag-handle arrange-drag-handle"><i class="bi bi-arrows-move"></i></div>
                        <small class="question-type-badge badge bg-primary">${utils.getQuestionTypeBadge(question.type)}</small>
                    </div>
                    <div class="arrange-card-content">
                        <div class="question-text-preview">
                            ${utils.stripHtml(question.text).substring(0, 80)}${utils.stripHtml(question.text).length > 80 ? '...' : ''}
                        </div>
                    </div>
                    <div class="arrange-card-footer">
                        ${question.required ? '<span class="badge bg-danger">Required</span>' : ''}
                        ${question.matchStudent && question.matchStudent !== 'none' ? `<span class="badge bg-info">Matches ${question.matchStudent}</span>` : ''}
                        <small class="text-muted">Q${qIdx + 1}</small>
                    </div>
                `;

                questionCard.addEventListener('click', (e) => {
                    if (!e.target.closest('.arrange-drag-handle')) {
                        arrangeView.showQuestionDetails(state.currentSectionIndex, qIdx, question);
                    }
                });

                gridContainer.appendChild(questionCard);
            });

            arrangeView.initArrangeViewSortable();
        },

        initArrangeViewSortable: () => {
            const gridContainer = document.getElementById('arrangeGrid');
            if (!gridContainer) return;

            if (gridContainer._arrangeSortable) gridContainer._arrangeSortable.destroy();

            const sortable = Sortable.create(gridContainer, {
                animation: 150,
                handle: '.arrange-drag-handle',
                ghostClass: 'arrange-ghost',
                chosenClass: 'arrange-chosen',
                dragClass: 'arrange-drag',
                scroll: true,
                scrollSensitivity: 100,
                onEnd: function (evt) {
                    if (evt.oldIndex !== evt.newIndex) {
                        const currentSection = state.sections[state.currentSectionIndex];
                        const movedItem = currentSection.questions.splice(evt.oldIndex, 1)[0];
                        currentSection.questions.splice(evt.newIndex, 0, movedItem);
                        updateQuestionsData();
                        arrangeView.renderArrangeView();
                        highlightManager.restoreHighlights();
                    }
                }
            });
            gridContainer._arrangeSortable = sortable;
        },

        showQuestionDetails: (sectionIndex, questionIndex, question) => {
            const modal = document.createElement('div');
            modal.className = 'modal fade question-details-modal';
            modal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="bi bi-info-circle me-2"></i>Question Details</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="question-details-content">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong>Question Type:</strong>
                                        <span class="badge bg-primary ms-2">${utils.convertTypeForOutput(question.type)}</span>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Required:</strong>
                                        <span class="badge ${question.required ? 'bg-danger' : 'bg-secondary'} ms-2">
                                            ${question.required ? 'Yes' : 'No'}
                                        </span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <strong>Question Text:</strong>
                                    <div class="question-text-display border rounded p-3 mt-1 bg-light">
                                        ${question.text || '<em class="text-muted">No text provided</em>'}
                                    </div>
                                </div>
                                ${arrangeView.renderQuestionSpecificDetails(question)}
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();

            modal.addEventListener('hidden.bs.modal', () => document.body.removeChild(modal));
        },

        renderQuestionSpecificDetails: (question) => {
            switch (question.type) {
                case 'multiple':
                case 'checkbox':
                case 'dropdown':
                    return `
                        <div class="mb-3">
                            <strong>Options (${question.options.length}):</strong>
                            <div class="options-list mt-1">
                                ${question.options && question.options.length > 0
                            ? question.options.map((opt, idx) => `
                                        <div class="option-item border rounded p-2 mb-1 bg-white">
                                            <span class="badge bg-light text-dark me-2">${idx + 1}</span>
                                            ${opt || '<em class="text-muted">Empty option</em>'}
                                        </div>
                                    `).join('')
                            : '<div class="text-muted"><em>No options defined</em></div>'
                        }
                            </div>
                        </div>
                    `;
                case 'linear_scale':
                    return `
                        <div class="row">
                            <div class="col-md-4"><strong>Scale Range:</strong><div class="mt-1">1 to ${question.scaleMax || 5}</div></div>
                            <div class="col-md-4"><strong>Left Label:</strong><div class="mt-1">${question.labelLeft || '<em class="text-muted">None</em>'}</div></div>
                            <div class="col-md-4"><strong>Right Label:</strong><div class="mt-1">${question.labelRight || '<em class="text-muted">None</em>'}</div></div>
                        </div>
                    `;
                default:
                    return `
                        <div class="alert alert-danger">
                            <i class="bi bi-${question.type === 'short' ? 'input-cursor-text' : question.type === 'paragraph' ? 'text-paragraph' : 'calendar'}"></i>
                            ${utils.convertTypeForOutput(question.type)} field
                        </div>
                    `;
            }
        }
    };

    // ========== QUESTION MANAGEMENT ==========
    const questionManager = {
        // Add new question form handling
        setupAddQuestionForm: () => {
            elements.questionType.addEventListener('change', questionManager.handleQuestionTypeChange);
            elements.addQuestionBtn.addEventListener('click', questionManager.handleAddQuestion);

            // Initialize match student manager for new questions
            matchStudentManager.updateNewQuestionMatchStudent();

            // Add event listener for match student dropdown change
            const matchStudentSelect = document.getElementById('questionMatchStudent');
            if (matchStudentSelect) {
                matchStudentSelect.addEventListener('change', function () {
                    const matchStudentType = document.getElementById('matchStudentType');
                    const matchStudentHelp = document.getElementById('matchStudentHelp');
                    const questionType = document.getElementById('questionType').value;

                    if (matchStudentType && matchStudentHelp) {
                        if (this.value === 'none') {
                            // Hide helper text when "None" is selected
                            matchStudentHelp.style.display = 'none';
                        } else {
                            // Show helper text and update content for "name" or "student_id"
                            matchStudentHelp.style.display = 'block';
                            matchStudentType.textContent = this.value === 'name' ? 'student names' : 'student IDs';
                        }
                    }
                });
            }

            // Initialize match student dropdown visibility based on initial selection
            setTimeout(() => {
                questionManager.handleQuestionTypeChange.call(elements.questionType);
            }, 100);
        },

        handleQuestionTypeChange: function () {
            const val = this.value;
            const isOptionBased = ['multiple', 'checkbox', 'dropdown'].includes(val);
            const isLinearScale = val === 'linear_scale';
            const isShortAnswer = val === 'short';

            elements.optionsContainer.style.display = isOptionBased ? 'block' : 'none';

            // Show/hide match student dropdown for Short Answer
            const matchStudentContainer = document.getElementById('matchStudentContainer');
            const matchStudentHelp = document.getElementById('matchStudentHelp');
            const matchStudentSelect = document.getElementById('questionMatchStudent');

            if (matchStudentContainer && matchStudentHelp && matchStudentSelect) {
                if (isShortAnswer) {
                    matchStudentContainer.classList.remove('d-none');

                    // Show helper text only if not "none"
                    if (matchStudentSelect.value === 'none') {
                        matchStudentHelp.style.display = 'none';
                    } else {
                        matchStudentHelp.style.display = 'block';
                        const matchStudentType = document.getElementById('matchStudentType');
                        if (matchStudentType) {
                            matchStudentType.textContent = matchStudentSelect.value === 'name' ? 'student names' : 'student IDs';
                        }
                    }
                } else {
                    matchStudentContainer.classList.add('d-none');
                    matchStudentHelp.style.display = 'none';
                }
            }

            if (isOptionBased) {
                questionManager.renderIndividualOptionsInput();
            } else {
                const existingOptionsContainer = document.querySelector('.individual-options-container');
                if (existingOptionsContainer) existingOptionsContainer.remove();

                // Reset options container to initial state when switching away from option-based types
                if (!isLinearScale) {
                    // Clear the options content but keep the structure
                    const optionsContent = elements.optionsContainer.querySelector('.options-content');
                    if (optionsContent) {
                        optionsContent.innerHTML = '';
                    } else {
                        elements.optionsContainer.innerHTML = '<label class="form-label"><i class="bi bi-list-ul me-2"></i>Options</label><div class="options-content"></div>';
                    }
                }
            }

            questionManager.toggleLinearScaleSettings(isLinearScale);
        },

        renderIndividualOptionsInput: () => {
            const existingContainer = document.querySelector('.individual-options-container');
            if (existingContainer) existingContainer.remove();

            const individualContainer = document.createElement('div');
            individualContainer.className = 'individual-options-container mt-2';
            individualContainer.innerHTML = `
                <div class="options-list mb-2">
                    <div class="option-row d-flex align-items-center gap-2 mb-2">
                        <input type="text" class="form-control option-input" placeholder="Option 1" value="">
                        <button type="button" class="btn btn-outline-danger btn-sm remove-option" disabled>
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                    <div class="option-row d-flex align-items-center gap-2 mb-2">
                        <input type="text" class="form-control option-input" placeholder="Option 2" value="">
                        <button type="button" class="btn btn-outline-danger btn-sm remove-option">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm add-option">
                    <i class="bi bi-plus-circle"></i> Add Option
                </button>
            `;

            // Always use the options-content div
            let optionsContent = elements.optionsContainer.querySelector('.options-content');
            if (!optionsContent) {
                // If options-content doesn't exist, create it
                optionsContent = document.createElement('div');
                optionsContent.className = 'options-content';
                elements.optionsContainer.appendChild(optionsContent);
            }

            optionsContent.innerHTML = '';
            optionsContent.appendChild(individualContainer);

            questionManager.setupIndividualOptionsEvents(individualContainer);
        },

        setupIndividualOptionsEvents: (container) => {
            container.querySelector('.add-option').addEventListener('click', function () {
                const optionsList = container.querySelector('.options-list');
                const optionCount = optionsList.querySelectorAll('.option-row').length;

                const optionRow = document.createElement('div');
                optionRow.className = 'option-row d-flex align-items-center gap-2 mb-2';
                optionRow.innerHTML = `
                    <input type="text" class="form-control option-input" placeholder="Option ${optionCount + 1}" value="">
                    <button type="button" class="btn btn-outline-danger btn-sm remove-option">
                        <i class="bi bi-x"></i>
                    </button>
                `;

                optionsList.appendChild(optionRow);
                optionRow.querySelector('.remove-option').addEventListener('click', function () {
                    if (optionsList.querySelectorAll('.option-row').length > 1) {
                        optionRow.remove();
                    }
                });
                optionRow.querySelector('.option-input').focus();
            });

            container.querySelectorAll('.remove-option').forEach(button => {
                button.addEventListener('click', function () {
                    const optionsList = container.querySelector('.options-list');
                    if (optionsList.querySelectorAll('.option-row').length > 1) {
                        this.closest('.option-row').remove();
                    }
                });
            });
        },

        toggleLinearScaleSettings: (show) => {
            const existingScaleSettings = document.querySelector('.linear-scale-settings-add');
            if (existingScaleSettings) existingScaleSettings.remove();

            if (show) {
                const scaleSettings = document.createElement('div');
                scaleSettings.className = 'linear-scale-settings-add mt-3';
                scaleSettings.innerHTML = `
                    <label class="form-label"><i class="bi bi-sliders me-2"></i>Linear Scale Settings</label>
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label">Scale Range</label>
                            <select class="form-select" id="linearScaleMax">
                                ${[...Array(9).keys()].map(i => {
                    const val = i + 2;
                    return `<option value="${val}" ${val === 5 ? 'selected' : ''}>1 to ${val}</option>`;
                }).join('')}
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Left Label</label>
                            <input type="text" class="form-control" id="linearScaleLabelLeft" placeholder="e.g., Strongly Disagree">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Right Label</label>
                            <input type="text" class="form-control" id="linearScaleLabelRight" placeholder="e.g., Strongly Agree">
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">Preview:</small>
                        <div id="linearScalePreview" class="linear-scale-preview mt-1 p-2 border rounded bg-light"></div>
                    </div>
                `;

                // Insert after the options container
                elements.optionsContainer.after(scaleSettings);
                questionManager.setupLinearScalePreview();
            }
        },

        setupLinearScalePreview: () => {
            const updatePreview = () => {
                const max = parseInt(document.getElementById('linearScaleMax').value);
                const leftLabel = document.getElementById('linearScaleLabelLeft').value;
                const rightLabel = document.getElementById('linearScaleLabelRight').value;

                const preview = document.getElementById('linearScalePreview');
                preview.innerHTML = `
                    <div class="linear-scale mb-2" data-scale-range="${max}">
                        <div class="d-flex justify-content-between mb-1">
                            <small>${leftLabel || 'Left label'}</small>
                            <small>${rightLabel || 'Right label'}</small>
                        </div>
                        <div class="d-flex justify-content-between">
                            ${[...Array(max).keys()].map(i => `
                                <label class="d-flex flex-column align-items-center">
                                    <input type="radio" name="scale-preview" value="${i + 1}" disabled>
                                    <small class="text-muted">${i + 1}</small>
                                </label>
                            `).join('')}
                        </div>
                    </div>
                `;
            };

            document.getElementById('linearScaleMax').addEventListener('change', updatePreview);
            document.getElementById('linearScaleLabelLeft').addEventListener('input', updatePreview);
            document.getElementById('linearScaleLabelRight').addEventListener('input', updatePreview);
            updatePreview();
        },

        handleAddQuestion: () => {
            const type = elements.questionType.value;
            const text = addQuestionQuill.root.innerHTML.trim();

            if (!validation.validateQuillContent(addQuestionQuill)) {
                utils.showError('Please enter question text');
                validation.highlightEmptyQuill(document.getElementById('questionTextEditor'));
                document.getElementById('questionTextEditor').scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            const options = questionManager.getOptionsFromForm(type);
            if (options === null) return; // Validation failed

            const { scaleMax, labelLeft, labelRight } = questionManager.getLinearScaleSettings(type);

            // Get matchStudent value
            const matchStudent = document.getElementById('questionMatchStudent')?.value || "none";

            const newQ = {
                text, type, options,
                required: elements.questionRequired.checked,
                showRequired: false,
                matchStudent: matchStudent,
                scaleMax, labelLeft, labelRight,
                isEdited: true
            };

            if (state.sections.length === 0) {
                state.sections.push({ title: 'Untitled Section', questions: [] });
            }

            const newQuestionIndex = state.sections[state.currentSectionIndex].questions.length;
            state.sections[state.currentSectionIndex].questions.push(newQ);

            renderAllQuestions();
            updateQuestionsData();
            questionManager.resetForm();

            highlightManager.highlightQuestion(state.currentSectionIndex, newQuestionIndex);

            // Auto-focus and scroll to the new question
            setTimeout(() => {
                const questionId = utils.getQuestionId(state.currentSectionIndex, newQuestionIndex);
                const newQuestionElement = document.getElementById(questionId);

                if (newQuestionElement) {
                    // Scroll to the new question
                    newQuestionElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });

                    // Focus the Quill editor
                    const editorEl = document.getElementById(`editor-${state.currentSectionIndex}-${newQuestionIndex}`);
                    if (editorEl?._quill) {
                        setTimeout(() => {
                            editorEl._quill.focus();
                        }, 300);
                    }
                }
            }, 100);
        },

        getOptionsFromForm: (type) => {
            if (!['multiple', 'checkbox', 'dropdown'].includes(type)) return [];

            const optionInputs = document.querySelectorAll('.individual-options-container .option-input');
            const options = Array.from(optionInputs).map(input => input.value.trim()).filter(opt => opt !== '');

            if (options.length === 0) {
                utils.showError('Please add at least one option for this question type');
                return null;
            }
            return options;
        },

        getLinearScaleSettings: (type) => {
            if (type !== 'linear_scale') return { scaleMax: undefined, labelLeft: '', labelRight: '' };

            return {
                scaleMax: parseInt(document.getElementById('linearScaleMax')?.value) || 5,
                labelLeft: document.getElementById('linearScaleLabelLeft')?.value || '',
                labelRight: document.getElementById('linearScaleLabelRight')?.value || ''
            };
        },

        resetForm: () => {
            addQuestionQuill.root.innerHTML = "";
            elements.questionType.value = '';
            elements.questionRequired.checked = false;
            elements.optionsContainer.style.display = 'none';

            // Reset match student dropdown visibility based on current question type
            const currentType = elements.questionType.value;
            const isShortAnswer = currentType === 'short';
            const matchStudentContainer = document.getElementById('matchStudentContainer');
            const matchStudentHelp = document.getElementById('matchStudentHelp');

            if (matchStudentContainer && matchStudentHelp) {
                if (isShortAnswer) {
                    matchStudentContainer.classList.remove('d-none');
                    matchStudentHelp.style.display = 'block';
                } else {
                    matchStudentContainer.classList.add('d-none');
                    matchStudentHelp.style.display = 'none';
                }
            }

            // Reset match student dropdown value
            const matchStudentSelect = document.getElementById('questionMatchStudent');
            if (matchStudentSelect) {
                matchStudentSelect.value = 'none';

                // Reset helper text
                const matchStudentType = document.getElementById('matchStudentType');
                if (matchStudentType) {
                    matchStudentType.textContent = 'student names';
                }
            }

            // Reset options container to initial state
            elements.optionsContainer.innerHTML = '<label class="form-label"><i class="bi bi-list-ul me-2"></i>Options</label><div class="options-content"></div>';

            document.querySelector('.individual-options-container')?.remove();
            document.querySelector('.linear-scale-settings-add')?.remove();
            validation.removeQuillErrorHighlight(document.getElementById('questionTextEditor'));
        },

        addQuestionAtSection: (sIdx, qIdx, position = 'below') => {
            const newQ = {
                text: 'New question', type: 'short', options: [],
                required: false, showRequired: false, isEdited: true
            };
            const insertIndex = position === 'above' ? qIdx : qIdx + 1;

            if (!state.sections[sIdx].questions) state.sections[sIdx].questions = [];
            state.sections[sIdx].questions.splice(insertIndex, 0, newQ);

            renderAllQuestions();
            updateQuestionsData();
            highlightManager.highlightQuestion(sIdx, insertIndex);

            // Auto-focus and scroll to the new question
            setTimeout(() => {
                const questionId = utils.getQuestionId(sIdx, insertIndex);
                const newQuestionElement = document.getElementById(questionId);

                if (newQuestionElement) {
                    newQuestionElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });

                    const editorEl = document.getElementById(`editor-${sIdx}-${insertIndex}`);
                    if (editorEl?._quill) {
                        setTimeout(() => {
                            editorEl._quill.focus();
                        }, 300);
                    }
                }
            }, 100);
        }
    };

    // ========== EVENT LISTENERS ==========
    const eventListeners = {
        initialize: () => {
            // Arrange View
            elements.arrangeViewBtn.addEventListener('click', eventListeners.handleArrangeViewToggle);

            // Section Management
            elements.addSectionBtn.addEventListener('click', eventListeners.handleAddSection);
            elements.prevSectionBtn.addEventListener('click', eventListeners.handlePreviousSection);
            elements.nextSectionBtn.addEventListener('click', eventListeners.handleNextSection);

            // Form Submission
            elements.surveyForm.addEventListener('submit', eventListeners.handleFormSubmit);

            // Question Management
            questionManager.setupAddQuestionForm();
        },

        handleArrangeViewToggle: function () {
            state.isArrangeView
                ? arrangeView.exitArrangeView(this)
                : arrangeView.enterArrangeView(this);
        },

        handleAddSection: () => {
            if (state.isArrangeView) {
                utils.showError('Please complete arrangement before adding new sections.');
                return;
            }
            state.sections.push({ id: Date.now(), title: "Untitled Section", questions: [] });
            state.currentSectionIndex = state.sections.length - 1;
            renderAllQuestions();
            updateSectionNav();
            utils.saveScrollPosition();
        },

        handlePreviousSection: () => {
            if (state.currentSectionIndex > 0) {
                if (state.isArrangeView) {
                    arrangeView.storeOriginalPositions();
                }

                utils.saveScrollPosition();
                state.currentSectionIndex--;

                if (state.isArrangeView) {
                    arrangeView.renderArrangeView();
                } else {
                    renderAllQuestions();
                }
                updateSectionNav();
                utils.restoreScrollPosition();
            }
        },

        handleNextSection: () => {
            if (state.currentSectionIndex < state.sections.length - 1) {
                if (state.isArrangeView) {
                    arrangeView.storeOriginalPositions();
                }

                utils.saveScrollPosition();
                state.currentSectionIndex++;

                if (state.isArrangeView) {
                    arrangeView.renderArrangeView();
                } else {
                    renderAllQuestions();
                }
                updateSectionNav();
                utils.restoreScrollPosition();
            }
        },

        handleFormSubmit: (e) => {
            if (state.isArrangeView) {
                e.preventDefault();
                utils.showError('Please complete arrangement before updating the survey.');
                return;
            }

            e.preventDefault();

            if (!validation.validateBeforeSubmit()) {
                return;
            }
            updateQuestionsData();
            highlightManager.removeAllHighlights();
            elements.surveyForm.submit();
        }
    };

    // ========== RENDERING FUNCTIONS ==========
    const renderer = {
        renderAllQuestions: () => {
            elements.previewQuestions.innerHTML = '';

            if (state.sections.length === 0) return;

            const currentSection = state.sections[state.currentSectionIndex];
            const secDiv = document.createElement('div');
            secDiv.className = `section mb-4 ${currentSection.questions.length === 0 ? 'section-empty' : ''}`;
            secDiv.dataset.index = state.currentSectionIndex;

            secDiv.innerHTML = `
                <div class="section-header mb-2 p-2 border rounded bg-light d-flex justify-content-between align-items-center">
                    <input type="text" class="form-control section-title-input" data-index="${state.currentSectionIndex}" value="${currentSection.title}">
                    <button class="btn btn-sm btn-danger delete-section" data-index="${state.currentSectionIndex}">Delete</button>
                </div>
                <div class="questions-container"></div>
            `;

            elements.previewQuestions.appendChild(secDiv);
            renderer.setupSectionEvents(secDiv, currentSection);
            renderer.renderQuestionsInSection(secDiv, currentSection);
            renderer.initQuillEditors();
            renderer.initSortable();
            highlightManager.restoreHighlights();
        },

        setupSectionEvents: (secDiv, currentSection) => {
            // Section title change
            secDiv.querySelector('.section-title-input').addEventListener('input', (e) => {
                state.sections[state.currentSectionIndex].title = e.target.value;
                updateQuestionsData();
                updateSectionNav();
            });

            // Delete section
            secDiv.querySelector('.delete-section').addEventListener('click', (e) => {
                if (confirm('Delete this section and all the questions inside it?')) {
                    state.sections.splice(state.currentSectionIndex, 1);
                    if (state.currentSectionIndex >= state.sections.length) {
                        state.currentSectionIndex = state.sections.length - 1;
                    }
                    renderAllQuestions();
                    updateQuestionsData();
                    updateSectionNav();
                }
            });
        },

        renderQuestionsInSection: (secDiv, currentSection) => {
            const questionsContainer = secDiv.querySelector('.questions-container');

            currentSection.questions.forEach((q, qIdx) => {
                const wrapper = document.createElement('div');
                wrapper.id = utils.getQuestionId(state.currentSectionIndex, qIdx);
                wrapper.className = 'question-item mb-3 p-3 border rounded d-flex flex-column';
                questionsContainer.appendChild(wrapper);

                renderer.renderQuestionInSection(q, state.currentSectionIndex, qIdx, wrapper);
            });
        },

        renderQuestionInSection: (q, sIdx, qIdx, wrapper) => {
            wrapper.innerHTML = `
                <div class="mb-3">
                    <div class="drag-handle me-2" style="cursor: grab;">☰</div>
                    <label class="form-label">Question Type</label>
                    <select class="form-select question-type-select" data-section="${sIdx}" data-index="${qIdx}">
                        <option value="short" ${q.type === 'short' ? 'selected' : ''}>Short Answer</option>
                        <option value="paragraph" ${q.type === 'paragraph' ? 'selected' : ''}>Paragraph</option>
                        <option value="multiple" ${q.type === 'multiple' ? 'selected' : ''}>Multiple Choice</option>
                        <option value="checkbox" ${q.type === 'checkbox' ? 'selected' : ''}>Checkbox</option>
                        <option value="dropdown" ${q.type === 'dropdown' ? 'selected' : ''}>Dropdown</option>
                        <option value="date" ${q.type === 'date' ? 'selected' : ''}>Date Picker</option>
                        <option value="linear_scale" ${q.type === 'linear_scale' ? 'selected' : ''}>Linear Scale</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Question Text</label>
                    <div id="editor-${sIdx}-${qIdx}" class="question-text-editor" style="min-height:50px;"></div>
                </div>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <!-- Required Checkbox -->
                    <div class="form-check form-switch">
                        <input class="form-check-input question-required-toggle" type="checkbox" data-section="${sIdx}" data-index="${qIdx}" ${q.required ? 'checked' : ''}>
                        <label class="form-check-label">Required</label>
                    </div>

                    <!-- Match Student Database Dropdown - Only for Short Answer -->
                    <div class="d-flex align-items-center gap-2 ${q.type !== 'short' ? 'd-none' : ''}">
                        <label class="form-label mb-0 small">Match student:</label>
                        <select class="form-select form-select-sm question-match-student" data-section="${sIdx}" data-index="${qIdx}" style="width: 140px;">
                            <option value="none" ${q.matchStudent === 'none' ? 'selected' : ''}>None</option>
                            <option value="name" ${q.matchStudent === 'name' ? 'selected' : ''}>Name</option>
                            <option value="student_id" ${q.matchStudent === 'student_id' ? 'selected' : ''}>Student ID</option>
                        </select>
                    </div>
                </div>
                <div class="question-preview mb-2"></div>
                <div class="d-flex gap-2 justify-content-end flex-wrap">
                    <button type="button" class="btn btn-secondary btn-sm add-top" data-section="${sIdx}" data-index="${qIdx}">Add Question Above</button>
                    <button type="button" class="btn btn-secondary btn-sm add-below" data-section="${sIdx}" data-index="${qIdx}">Add Question Below</button>
                    <button type="button" class="btn btn-danger btn-sm delete-question" data-section="${sIdx}" data-index="${qIdx}">Delete</button>
                </div>
            `;

            renderer.setupQuestionEvents(q, sIdx, qIdx, wrapper);
            renderer.updatePreviewOptions(qIdx, q, wrapper);
            renderer.restoreMatchStudentState(wrapper, q, sIdx, qIdx);

            // Add options container for option-based questions
            if (['multiple', 'checkbox', 'dropdown'].includes(q.type)) {
                renderer.addOptionsContainer(q, sIdx, qIdx, wrapper);
            }
        },

        addOptionsContainer: (q, sIdx, qIdx, wrapper) => {
            let optionsContainer = wrapper.querySelector('.question-options-container');
            if (!optionsContainer) {
                optionsContainer = document.createElement('div');
                optionsContainer.className = 'question-options-container mb-3';
                const previewDiv = wrapper.querySelector('.question-preview');
                wrapper.insertBefore(optionsContainer, previewDiv);
            }

            optionsContainer.innerHTML = `
                <label class="form-label mb-2">Options</label>
                <div class="options-list">
                    ${q.options.map((option, optIndex) => `
                        <div class="option-row d-flex align-items-center gap-2 mb-2">
                            <input type="text" 
                                class="form-control option-input" 
                                value="${option}" 
                                placeholder="Option ${optIndex + 1}"
                                data-index="${optIndex}">
                            <button type="button" class="btn btn-outline-danger btn-sm remove-option" data-index="${optIndex}">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    `).join('')}
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm add-option">
                    <i class="bi bi-plus-circle"></i> Add Option
                </button>
            `;

            // Option input event listeners
            optionsContainer.querySelectorAll('.option-input').forEach(input => {
                input.addEventListener('input', (e) => {
                    const optIndex = parseInt(e.target.dataset.index);
                    q.options[optIndex] = e.target.value;

                    if (e.target.value.trim()) {
                        e.target.classList.remove('is-invalid');
                    }

                    renderer.updatePreviewOptions(qIdx, q, wrapper);
                    updateQuestionsData();
                    highlightManager.highlightQuestion(sIdx, qIdx);
                });

                input.addEventListener('blur', (e) => {
                    if (!e.target.value.trim()) {
                        e.target.classList.add('is-invalid');
                    }
                });
            });

            // Remove option event listener
            optionsContainer.querySelectorAll('.remove-option').forEach(button => {
                button.addEventListener('click', (e) => {
                    const optIndex = parseInt(e.target.closest('.remove-option').dataset.index);

                    if (q.options.length <= 1) {
                        utils.showError('Cannot remove the last option. Each question must have at least one option.');
                        return;
                    }

                    q.options.splice(optIndex, 1);
                    renderer.renderQuestionInSection(q, sIdx, qIdx, wrapper);
                    updateQuestionsData();
                    highlightManager.highlightQuestion(sIdx, qIdx);
                });
            });

            // Add option event listener
            optionsContainer.querySelector('.add-option').addEventListener('click', (e) => {
                q.options.push('');
                renderer.renderQuestionInSection(q, sIdx, qIdx, wrapper);
                updateQuestionsData();
                highlightManager.highlightQuestion(sIdx, qIdx);
            });
        },

        setupQuestionEvents: (q, sIdx, qIdx, wrapper) => {
            // Question type change
            const typeSelect = wrapper.querySelector('.question-type-select');
            typeSelect.addEventListener('change', (e) => {
                renderer.handleQuestionTypeChange(q, sIdx, qIdx, wrapper, e.target.value);
            });

            // Add above/below buttons
            wrapper.querySelector('.add-top').addEventListener('click', () =>
                questionManager.addQuestionAtSection(sIdx, qIdx, 'above'));
            wrapper.querySelector('.add-below').addEventListener('click', () =>
                questionManager.addQuestionAtSection(sIdx, qIdx, 'below'));

            // Delete question
            wrapper.querySelector('.delete-question').addEventListener('click', () => {
                const question = state.sections[sIdx].questions[qIdx];

                // Check if this is a required matching question
                if (question.isRestrictionRequired) {
                    const hasCourses = state.allowedCourses.length > 0 && !state.allowedCourses.includes('none');
                    const hasDates = state.allowedGraduationDates.length > 0 && !state.allowedGraduationDates.includes('none');

                    if (hasCourses || hasDates) {
                        const confirmed = confirm("This question is required because you have course or graduation date restrictions. If you delete it, you must add another matching student question (Short Answer with Name or ID matching). Continue?");
                        if (!confirmed) return;
                    }
                }

                if (confirm('Delete this question?')) {
                    state.sections[sIdx].questions.splice(qIdx, 1);
                    checkForMatchingStudentQuestion();

                    // If we deleted the required matching question but still have restrictions,
                    // add a new one
                    const hasCourses = state.allowedCourses.length > 0 && !state.allowedCourses.includes('none');
                    const hasDates = state.allowedGraduationDates.length > 0 && !state.allowedGraduationDates.includes('none');

                    if ((hasCourses || hasDates) && !state.hasMatchingStudentQuestion) {
                        setTimeout(() => {
                            alert("You need at least one matching student question (Short Answer with Name or ID matching) because you have course or graduation date restrictions." 
                                    + "A new matching question has been added.");

                            const matchingQuestion = {
                                text: 'Please enter your full name',
                                type: 'short',
                                options: [],
                                required: true,
                                showRequired: true,
                                matchStudent: 'name',
                                isRestrictionRequired: true,
                                isAutoAdded: true
                            };

                            state.sections[0].questions.unshift(matchingQuestion);
                            state.hasMatchingStudentQuestion = true;
                            state.requiredMatchingQuestionId = `0-0`;

                            renderAllQuestions();
                            updateQuestionsData();

                            // Highlight the new auto-added question
                            setTimeout(() => {
                                highlightManager.highlightQuestion(0, 0);
                            }, 300);
                        }, 100);
                    }

                    renderAllQuestions();
                    updateQuestionsData();
                }
            });

            // Required toggle
            wrapper.querySelector('.question-required-toggle').addEventListener('change', (e) => {
                q.required = e.target.checked;
                q.showRequired = e.target.checked;
                renderer.updatePreviewOptions(qIdx, q, wrapper);
                updateQuestionsData();
                highlightManager.highlightQuestion(sIdx, qIdx);
            });

            // Match student dropdown change
            const matchStudentSelect = wrapper.querySelector('.question-match-student');
            if (matchStudentSelect) {
                matchStudentSelect.addEventListener('change', (e) => {
                    q.matchStudent = e.target.value;

                    // Automatically update required checkbox state
                    matchStudentManager.updateRequiredCheckboxState(wrapper, e.target.value);

                    // Also update the question's required state in the data model
                    q.required = (e.target.value !== 'none');

                    updateQuestionsData();
                    highlightManager.highlightQuestion(sIdx, qIdx);
                    renderer.updatePreviewOptions(qIdx, q, wrapper);
                });

                // Initialize the required checkbox state based on current value
                matchStudentManager.updateRequiredCheckboxState(wrapper, q.matchStudent);
            }

            // Section movement dropdown
            renderer.addSectionMovementDropdown(wrapper, sIdx, qIdx);
        },

        handleQuestionTypeChange: (q, sIdx, qIdx, wrapper, newType) => {
            const oldType = q.type;
            q.type = newType;

            // Use matchStudentManager to handle the transition
            matchStudentManager.handleQuestionTypeChange(wrapper, newType, oldType, q, sIdx, qIdx);

            highlightManager.highlightQuestion(sIdx, qIdx);

            // Remove existing settings
            wrapper.querySelector('.linear-scale-settings')?.remove();
            const optionsContainer = wrapper.querySelector('.question-options-container');
            if (optionsContainer && !['multiple', 'checkbox', 'dropdown'].includes(newType)) {
                optionsContainer.remove();
                q.options = [];
            }

            // Add appropriate inputs based on type
            if (['multiple', 'checkbox', 'dropdown'].includes(newType)) {
                if (!q.options || q.options.length === 0) {
                    q.options = ['', ''];
                }
                renderer.addOptionsContainer(q, sIdx, qIdx, wrapper);
            } else if (newType === 'linear_scale') {
                if (!q.scaleMax || q.scaleMax < 2) {
                    q.scaleMax = 5;
                }
                renderer.addLinearScaleSettings(q, sIdx, qIdx, wrapper);
            }

            renderer.updatePreviewOptions(qIdx, q, wrapper);
            updateQuestionsData();

            // Validate and fix required state if needed
            if (matchStudentManager.validateRequiredStateOnTypeChange(q)) {
                // Update the checkbox in UI if state was fixed
                const requiredCheckbox = wrapper.querySelector('.question-required-toggle');
                if (requiredCheckbox) {
                    requiredCheckbox.checked = true;
                }
            }
        },

        addSectionMovementDropdown: (wrapper, sIdx, qIdx) => {
            const actionDiv = wrapper.querySelector('.d-flex.gap-2.justify-content-end');
            const sectionSelect = document.createElement('select');
            sectionSelect.className = 'form-select form-select-sm move-section-dropdown';
            sectionSelect.style.width = '180px';
            sectionSelect.dataset.section = sIdx;
            sectionSelect.dataset.index = qIdx;

            // Populate section options
            state.sections.forEach((sec, idx) => {
                const opt = document.createElement('option');
                opt.value = idx;
                opt.textContent = sec.title || `Untitled Section`;
                if (idx === sIdx) opt.selected = true;
                sectionSelect.appendChild(opt);
            });

            // Move question between sections
            sectionSelect.addEventListener('change', (e) => {
                const oldSIdx = parseInt(e.target.dataset.section, 10);
                const qIndex = parseInt(e.target.dataset.index, 10);
                const newSIdx = parseInt(e.target.value, 10);
                if (oldSIdx === newSIdx) return;

                const [qObj] = state.sections[oldSIdx].questions.splice(qIndex, 1);
                const newQuestionIndex = state.sections[newSIdx].questions.length;
                state.sections[newSIdx].questions.push(qObj);

                utils.saveScrollPosition();
                state.currentSectionIndex = newSIdx;
                renderAllQuestions();
                updateSectionNav();
                highlightManager.restoreHighlights();
                updateQuestionsData();

                // Auto-focus on the moved question
                setTimeout(() => {
                    const movedQuestionId = utils.getQuestionId(newSIdx, newQuestionIndex);
                    const movedQuestionElement = document.getElementById(movedQuestionId);

                    if (movedQuestionElement) {
                        movedQuestionElement.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });

                        // Highlight the moved question
                        movedQuestionElement.classList.add('highlight-updated');

                        // Focus the Quill editor
                        const editorEl = document.getElementById(`editor-${newSIdx}-${newQuestionIndex}`);
                        if (editorEl?._quill) {
                            setTimeout(() => {
                                editorEl._quill.focus();
                            }, 300);
                        }
                    }
                }, 100);

                utils.restoreScrollPosition();
            });

            actionDiv.appendChild(sectionSelect);
        },

        addLinearScaleSettings: (q, sIdx, qIdx, wrapper) => {
            const scaleDiv = document.createElement('div');
            scaleDiv.className = 'linear-scale-settings mb-2';
            scaleDiv.innerHTML = `
                <div class="mb-2">
                    <label class="form-label">Max Scale</label>
                    <select class="form-select linear-scale-max" data-section="${sIdx}" data-index="${qIdx}">
                        ${[...Array(9).keys()].map(i => {
                const val = i + 2;
                return `<option value="${val}" ${q.scaleMax === val ? 'selected' : ''}>${val}</option>`;
            }).join('')}
                    </select>
                </div>
                <div class="mb-2 d-flex gap-2">
                    <input type="text" class="form-control linear-scale-label-left" placeholder="Left Label" 
                           value="${q.labelLeft || ''}" data-section="${sIdx}" data-index="${qIdx}">
                    <input type="text" class="form-control linear-scale-label-right" placeholder="Right Label" 
                           value="${q.labelRight || ''}" data-section="${sIdx}" data-index="${qIdx}">
                </div>
            `;

            const actionDiv = wrapper.querySelector('.d-flex.gap-2.justify-content-end');
            wrapper.insertBefore(scaleDiv, actionDiv);

            // Linear scale event listeners
            scaleDiv.querySelector('.linear-scale-max').addEventListener('change', (e) => {
                q.scaleMax = Math.max(parseInt(e.target.value, 10), 2);
                renderer.updatePreviewOptions(qIdx, q, wrapper);
                updateQuestionsData();
                highlightManager.highlightQuestion(sIdx, qIdx);
            });

            scaleDiv.querySelector('.linear-scale-label-left').addEventListener('input', (e) => {
                q.labelLeft = e.target.value;
                renderer.updatePreviewOptions(qIdx, q, wrapper);
                updateQuestionsData();
                highlightManager.highlightQuestion(sIdx, qIdx);
            });

            scaleDiv.querySelector('.linear-scale-label-right').addEventListener('input', (e) => {
                q.labelRight = e.target.value;
                renderer.updatePreviewOptions(qIdx, q, wrapper);
                updateQuestionsData();
                highlightManager.highlightQuestion(sIdx, qIdx);
            });
        },

        updatePreviewOptions: (qIdx, q, wrapper) => {
            const previewDiv = wrapper.querySelector('.question-preview');
            if (!previewDiv) return;

            let previewHTML = '';

            switch (q.type) {
                case 'short':
                    previewHTML = '<input type="text" class="form-control" placeholder="Short answer response">';
                    // Only show match student badge if it's not "none"
                    if (q.matchStudent && q.matchStudent !== 'none') {
                        previewHTML += `<div class="mt-2">
                            <small class="badge bg-info">
                                <i class="bi bi-database me-1"></i>
                                Matches with ${q.matchStudent === 'name' ? 'student names' : 'student IDs'}
                            </small>
                        </div>`;
                    }
                    break;
                case 'paragraph':
                    previewHTML = '<textarea class="form-control" rows="3" placeholder="Paragraph response"></textarea>';
                    break;
                case 'dropdown':
                    previewHTML = `
                        <select class="form-select" disabled>
                            <option disabled selected>Select an option</option>
                            ${q.options.map(opt => `<option>${opt || 'Empty option'}</option>`).join('')}
                        </select>
                    `;
                    break;
                case 'date':
                    previewHTML = '<input type="date" class="form-control" disabled>';
                    break;
                case 'linear_scale':
                    const max = q.scaleMax || 5;
                    previewHTML = `
                        <div class="linear-scale mb-2" data-scale-range="${max}">
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted">${q.labelLeft || ''}</small>
                                <small class="text-muted">${q.labelRight || ''}</small>
                            </div>
                            <div class="d-flex justify-content-between">
                                ${[...Array(max).keys()].map(i => `
                                    <label class="d-flex flex-column align-items-center">
                                        <input type="radio" name="scale-${qIdx}" value="${i + 1}" disabled>
                                        <small class="text-muted">${i + 1}</small>
                                    </label>
                                `).join('')}
                            </div>
                        </div>`;
                    break;
                case 'multiple':
                case 'checkbox':
                    // No preview for Multiple Choice and Checkbox
                    break;
            }

            previewDiv.innerHTML = `
                ${previewHTML}
                <div class="required-area ${q.showRequired ? '' : 'd-none'}">
                    <small class="text-danger ms-2">(Required)</small>
                </div>
            `;
        },

        initQuillEditors: () => {
            state.sections.forEach((sec, sIdx) => {
                sec.questions.forEach((q, qIdx) => {
                    const editorEl = document.getElementById(`editor-${sIdx}-${qIdx}`);
                    if (!editorEl || editorEl._quill) return;

                    const quill = new Quill(editorEl, {
                        theme: 'snow',
                        modules: { toolbar: ['bold', 'italic', 'underline', { list: 'ordered' }, { list: 'bullet' }] }
                    });

                    // Set content from existing question
                    if (q.text && q.text.trim()) {
                        quill.root.innerHTML = q.text;
                    } else {
                        quill.root.innerHTML = '';
                    }

                    // Wait for Quill to fully initialize
                    setTimeout(() => {
                        const hasExistingContent = q.text && q.text.trim() && q.text !== '<p><br></p>' && q.text !== '<br>';

                        if (hasExistingContent) {
                            validation.removeQuillErrorHighlight(editorEl);
                        } else if (!validation.validateQuillContent(quill)) {
                            validation.highlightEmptyQuill(editorEl);
                        }

                        // Add text-change listener
                        quill.on('text-change', () => {
                            if (!state.isInitialLoad) {
                                q.text = quill.root.innerHTML;

                                if (validation.validateQuillContent(quill)) {
                                    validation.removeQuillErrorHighlight(editorEl);
                                } else {
                                    validation.highlightEmptyQuill(editorEl);
                                }

                                highlightManager.highlightQuestion(sIdx, qIdx);
                                updateQuestionsData();
                            }
                        });

                        editorEl._quill = quill;
                    }, 150);
                });
            });
        },

        restoreMatchStudentState: function (wrapper, question, sIdx, qIdx) {
            // Only for short answer questions
            if (question.type !== 'short') return;

            const matchStudentSelect = wrapper.querySelector('.question-match-student');
            if (matchStudentSelect) {
                // Restore saved value
                matchStudentSelect.value = question.matchStudent || 'none';

                // Apply required checkbox state based on match student
                matchStudentManager.updateRequiredCheckboxState(wrapper, question.matchStudent);

                // Ensure the question data model has correct required state
                if (question.matchStudent && question.matchStudent !== 'none') {
                    question.required = true;
                }
            }
        },

        initSortable: () => {
            document.querySelectorAll('.questions-container').forEach((container, sIdx) => {
                if (container._sortableInstance) container._sortableInstance.destroy();

                const sortable = Sortable.create(container, {
                    animation: 150,
                    handle: '.drag-handle',
                    ghostClass: 'sortable-ghost',
                    scroll: true,
                    scrollSensitivity: 200,
                    scrollSpeed: 100,
                    bubbleScroll: true,
                    forceAutoScrollFallback: true,
                    preventOnFilter: false,
                    supportPointer: false,

                    onStart: (evt) => evt.originalEvent.preventDefault(),
                    onmove: (evt) => true,
                    onEnd: (evt) => {
                        const oldIndex = evt.oldIndex;
                        const newIndex = evt.newIndex;
                        if (!state.sections[sIdx].questions) return;

                        const movedItem = state.sections[sIdx].questions.splice(oldIndex, 1)[0];
                        state.sections[sIdx].questions.splice(newIndex, 0, movedItem);

                        renderAllQuestions();
                        updateQuestionsData();
                        highlightManager.restoreHighlights();
                    }
                });
                container._sortableInstance = sortable;
            });
        }
    };

    // ========== DATA MANAGEMENT ==========
    const updateQuestionsData = () => {
        const output = {
            title: document.querySelector('input[name="title"]')?.value || "",
            description: state.description || "",
            sections: state.sections.map(sec => ({
                secTitle: sec.title,
                questions: sec.questions.map(q => {
                    const obj = {
                        type: utils.convertTypeForOutput(q.type),
                        text: q.text,
                        required: q.required || false,
                        matchStudent: q.matchStudent || "none"
                    };

                    if (["multiple", "checkbox", "dropdown"].includes(q.type)) {
                        obj.options = q.options || [];
                    }

                    if (q.type === "linear_scale") {
                        obj.scaleMin = 1;
                        obj.scaleMax = q.scaleMax || 5;
                        obj.scaleLabelLeft = q.labelLeft || "";
                        obj.scaleLabelRight = q.labelRight || "";
                    }

                    return obj;
                })
            }))
        };
        elements.questionsDataInput.value = JSON.stringify(output);
    };

    const updateSectionNav = () => {
        if (state.sections.length === 0) {
            elements.currentSectionLabel.textContent = 'No Sections';
            elements.prevSectionBtn.disabled = true;
            elements.nextSectionBtn.disabled = true;
            return;
        }

        const sec = state.sections[state.currentSectionIndex];
        if (state.isArrangeView) {
            elements.currentSectionLabel.textContent = `${sec.title || 'Untitled'} (Arrange View)`;
            elements.currentSectionLabel.classList.add('text-warning');
        } else {
            elements.currentSectionLabel.textContent = sec.title || 'Untitled';
            elements.currentSectionLabel.classList.remove('text-warning');
        }

        // Enable/disable buttons
        elements.prevSectionBtn.disabled = state.currentSectionIndex === 0;
        elements.nextSectionBtn.disabled = state.currentSectionIndex === state.sections.length - 1;

        // Update button styling
        elements.prevSectionBtn.classList.toggle('btn-secondary', elements.prevSectionBtn.disabled);
        elements.prevSectionBtn.classList.toggle('btn-outline-primary', !elements.prevSectionBtn.disabled);
        elements.nextSectionBtn.classList.toggle('btn-secondary', elements.nextSectionBtn.disabled);
        elements.nextSectionBtn.classList.toggle('btn-outline-primary', !elements.nextSectionBtn.disabled);
    };

    // ========== BACK TO TOP BUTTON FUNCTIONALITY ==========
    const backToTopManager = {
        init: function () {
            this.backToTopBtn = document.getElementById('backToTopBtn');
            if (!this.backToTopBtn) {
                console.error('Back to top button not found!');
                return;
            }

            console.log('Back to top button initialized'); // Debug log

            // Show/hide button based on scroll position
            window.addEventListener('scroll', () => this.toggleVisibility());

            // Scroll to top when clicked
            this.backToTopBtn.addEventListener('click', () => this.scrollToTop());

            // Initial check
            this.toggleVisibility();
        },

        toggleVisibility: function () {
            if (!this.backToTopBtn) return;

            // Show button after scrolling 200px
            if (window.pageYOffset > 200) {
                this.backToTopBtn.classList.add('show');
            } else {
                this.backToTopBtn.classList.remove('show');
            }
        },

        scrollToTop: function () {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }
    };

    // Initialize back to top button
    backToTopManager.init();

    // ========== INITIALIZATION ==========
    function initialize() {
        eventListeners.initialize();
        renderAllQuestions();
        updateQuestionsData();
        updateSectionNav();
        updateDescriptionPreview();
        initializeRestrictions();
        // Fix any existing data inconsistencies
        fixExistingDataInconsistencies();

        // Initialize required checkbox state for all existing questions
        state.sections.forEach((section, sIdx) => {
            section.questions.forEach((question, qIdx) => {
                const questionElement = document.getElementById(`question-${sIdx}-${qIdx}`);
                if (questionElement && question.matchStudent) {
                    matchStudentManager.updateRequiredCheckboxState(questionElement, question.matchStudent);
                }
            });
        });

        setTimeout(() => {
            state.isInitialLoad = false;
        }, 500);
    }

    function initializeRestrictions() {
        const coursesSelect = document.getElementById('allowedCourses');
        const datesSelect = document.getElementById('allowedGraduationDates');

        if (coursesSelect && datesSelect) {
            // Get initial values
            state.allowedCourses = Array.from(coursesSelect.selectedOptions).map(opt => opt.value);
            state.allowedGraduationDates = Array.from(datesSelect.selectedOptions).map(opt => opt.value);

            // Add event listeners
            coursesSelect.addEventListener('change', handleRestrictionChange);
            datesSelect.addEventListener('change', handleRestrictionChange);

            // Check initial state
            checkForMatchingStudentQuestion();
        }
    }

    function checkForMatchingStudentQuestion() {
        state.hasMatchingStudentQuestion = false;
        state.requiredMatchingQuestionId = null;

        state.sections.forEach((section, sectionIndex) => {
            section.questions.forEach((question, questionIndex) => {
                if (question.type === 'short' &&
                    (question.matchStudent === 'name' || question.matchStudent === 'student_id')) {
                    state.hasMatchingStudentQuestion = true;
                    state.requiredMatchingQuestionId = `${sectionIndex}-${questionIndex}`;
                    // Automatically make it required
                    question.required = true;

                    // Mark as restriction required if needed
                    const hasCourses = state.allowedCourses.length > 0 && !state.allowedCourses.includes('none');
                    const hasDates = state.allowedGraduationDates.length > 0 && !state.allowedGraduationDates.includes('none');

                    if (hasCourses || hasDates) {
                        question.isRestrictionRequired = true;
                    }
                }
            });
        });
    }

    function handleRestrictionChange() {
        const coursesSelect = document.getElementById('allowedCourses');
        const datesSelect = document.getElementById('allowedGraduationDates');

        state.allowedCourses = Array.from(coursesSelect.selectedOptions).map(opt => opt.value);
        state.allowedGraduationDates = Array.from(datesSelect.selectedOptions).map(opt => opt.value);

        const hasCourses = state.allowedCourses.length > 0 && !state.allowedCourses.includes('none');
        const hasDates = state.allowedGraduationDates.length > 0 && !state.allowedGraduationDates.includes('none');

        // Update hidden inputs for form submission
        document.getElementById('allowedCoursesInput').value =
            state.allowedCourses.includes('none') ? '' : state.allowedCourses.join(',');
        document.getElementById('allowedGraduationDatesInput').value =
            state.allowedGraduationDates.includes('none') ? '' : state.allowedGraduationDates.join(',');

        if (hasCourses || hasDates) {
            checkForMatchingStudentQuestion();

            if (!state.hasMatchingStudentQuestion) {
                // Add a matching student question
                if (state.sections.length === 0) {
                    state.sections.push({ title: 'Untitled Section', questions: [] });
                }

                const matchingQuestion = {
                    text: 'Please enter your full name',
                    type: 'short',
                    options: [],
                    required: true,
                    showRequired: true,
                    matchStudent: 'name',
                    isRestrictionRequired: true,
                    isAutoAdded: true
                };

                // Add to first section at position 0
                state.sections[0].questions.unshift(matchingQuestion);
                state.hasMatchingStudentQuestion = true;
                state.requiredMatchingQuestionId = `0-0`;

                // Show simple alert about the auto-added question
                setTimeout(() => {
                    alert("A matching student question has been automatically added because you selected course or graduation date restrictions.");
                }, 100);

                renderAllQuestions();
                updateQuestionsData();

                // Highlight the new question using existing highlight system
                setTimeout(() => {
                    highlightManager.highlightQuestion(0, 0);
                }, 300);
            }
        } else {
            // Remove restriction requirement flag
            state.sections.forEach(section => {
                section.questions.forEach(question => {
                    if (question.isRestrictionRequired) {
                        question.isRestrictionRequired = false;
                    }
                });
            });
            checkForMatchingStudentQuestion();
        }
    }

    function fixExistingDataInconsistencies() {
        state.sections.forEach((section, sIdx) => {
            section.questions.forEach((question, qIdx) => {
                // Ensure matching questions are always required
                if (question.type === 'short' &&
                    question.matchStudent &&
                    question.matchStudent !== 'none' &&
                    !question.required) {

                    console.log(`Fixing required state for question at section ${sIdx}, index ${qIdx}`);
                    question.required = true;

                    // If question has isRestrictionRequired flag, ensure it's respected
                    if (question.isRestrictionRequired) {
                        question.required = true;
                    }
                }

                // Ensure non-short answer questions don't have matchStudent
                if (question.type !== 'short' && question.matchStudent && question.matchStudent !== 'none') {
                    console.log(`Resetting matchStudent for non-short question at section ${sIdx}, index ${qIdx}`);
                    question.matchStudent = "none";
                }
            });
        });

        // Update the data after fixing
        updateQuestionsData();
    }

    // Aliases for external calls
    const renderAllQuestions = () => renderer.renderAllQuestions();

    // Start the application
    initialize();
});