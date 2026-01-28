document.addEventListener('DOMContentLoaded', function () {
    initializeAlumniForm();
    initializeSelect2Dropdowns();
});

function initializeAlumniForm() {
    // Check if on a conflict page - if so, don't restore form state
    const urlParams = new URLSearchParams(window.location.search);
    const isConflictPage = urlParams.has('conflict');

    if (!isConflictPage) {
        // Save form data before any modals/alerts appear
        saveFormState();

        // Restore form state if needed
        restoreFormState();
    } else {
        // Clear any saved form state on conflict pages
        sessionStorage.removeItem('alumniFormState');
        localStorage.removeItem('alumniFormState');
    }

    // Initialize all alumni form components
    initializeMethodToggle();
    initializeExtraFields();
    initializeGraduationDateModal();
    initializeCourseModal();
    initializeSuccessAlert();
    initializeFormValidation();
}

function initializeSelect2Dropdowns() {
    // Initialize Select2 for graduation date dropdown
    $('.select2-graduation').select2({
        theme: 'bootstrap-5',
        placeholder: 'Search or select graduation date',
        allowClear: true,
        width: '100%'
    });

    // Initialize Select2 for program dropdown
    $('.select2-program').select2({
        theme: 'bootstrap-5',
        placeholder: 'Search or select program',
        allowClear: true,
        width: '100%'
    });

    // Refresh Select2 when method toggles
    document.querySelectorAll('input[name="add_method"]').forEach(radio => {
        radio.addEventListener('change', function () {
            // Small delay to ensure fields are visible
            setTimeout(() => {
                $('.select2-graduation').select2();
                $('.select2-program').select2();
            }, 100);
        });
    });
}

// Store form data in sessionStorage
function saveFormState() {
    const form = document.getElementById('addStudentForm');
    if (form) {
        const formObject = {};

        // Save regular inputs
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(element => {
            // Skip file inputs (they can't be restored anyway)
            if (element.type === 'file') {
                return;
            }

            // Skip radio and checkbox inputs (handled separately)
            if (element.type === 'radio' || element.type === 'checkbox') {
                return;
            }

            // Handle Select2 dropdowns
            if (element.classList.contains('select2-graduation') || element.classList.contains('select2-program')) {
                const select2Instance = $(element).data('select2');
                if (select2Instance) {
                    formObject[element.name] = element.value;
                }
            } else {
                formObject[element.name] = element.value;
            }
        });

        // Save radio button state
        const selectedMethod = form.querySelector('input[name="add_method"]:checked');
        if (selectedMethod) {
            formObject.add_method = selectedMethod.value;
        }

        sessionStorage.setItem('alumniFormState', JSON.stringify(formObject));
    }
}

// Restore form data from sessionStorage
function restoreFormState() {
    const savedState = sessionStorage.getItem('alumniFormState');
    if (savedState) {
        const formData = JSON.parse(savedState);
        const form = document.getElementById('addStudentForm');

        if (!form) return;

        // Restore radio buttons
        const methodRadios = form.querySelectorAll('input[name="add_method"]');
        methodRadios.forEach(radio => {
            if (radio.value === formData.add_method) {
                radio.checked = true;
            }
        });

        // Restore other fields
        Object.keys(formData).forEach(key => {
            const element = form.querySelector(`[name="${key}"]`);
            if (element) {
                // Skip file inputs (browser security restriction)
                if (element.type === 'file') {
                    return;
                }

                // Skip radio and checkbox inputs (handled separately)
                if (element.type === 'radio' || element.type === 'checkbox') {
                    return;
                }

                // Set the value for valid input types
                element.value = formData[key];
                
                // Trigger change event for Select2
                if (element.classList.contains('select2-graduation') || element.classList.contains('select2-program')) {
                    $(element).trigger('change');
                }
            }
        });

        // Trigger toggle if needed
        const currentRadio = form.querySelector('input[name="add_method"]:checked');
        if (currentRadio) {
            const toggleEvent = new Event('change');
            currentRadio.dispatchEvent(toggleEvent);
        }

        // Clear saved state
        sessionStorage.removeItem('alumniFormState');
    }
}

function initializeMethodToggle() {
    const manualRadio = document.getElementById('manualEntry');
    const excelRadio = document.getElementById('excelUpload');
    const manualFields = document.getElementById('manualFields');
    const excelFields = document.getElementById('excelFields');

    function toggleFields() {
        if (manualRadio.checked) {
            manualFields.style.display = 'block';
            excelFields.style.display = 'none';

            // Set required attributes for manual fields
            setRequiredFields(true);
        } else {
            manualFields.style.display = 'none';
            excelFields.style.display = 'block';

            // Remove required attributes
            setRequiredFields(false);
        }
    }

    function setRequiredFields(required) {
        const fields = [
            'student_name',
            'student_id',
            'graduation_date_select',
            'mobile_no',
            'email',
            'personal_email',
            'program_select'
        ];

        fields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                if (required) {
                    field.setAttribute('required', true);
                } else {
                    field.removeAttribute('required');
                }
            }
        });
    }

    if (manualRadio && excelRadio) {
        manualRadio.addEventListener('change', toggleFields);
        excelRadio.addEventListener('change', toggleFields);
        toggleFields(); // Initialize
    }
}

function initializeExtraFields() {
    let extraFieldCount = 0;
    const addExtraFieldBtn = document.getElementById('addExtraFieldBtn');
    const extraFieldsContainer = document.getElementById('extraFields');

    if (addExtraFieldBtn && extraFieldsContainer) {
        addExtraFieldBtn.addEventListener('click', function () {
            extraFieldCount++;
            const fieldHtml = `
                <div class="row g-3 extra-field" id="extraField${extraFieldCount}">
                    <div class="col-md-5">
                        <input type="text" name="extra_fields[${extraFieldCount}][key]" 
                               class="form-control" placeholder="Field Name (e.g., LinkedIn)">
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="extra_fields[${extraFieldCount}][value]" 
                               class="form-control" placeholder="Field Value">
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-danger btn-sm" 
                                onclick="removeExtraField(${extraFieldCount})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            extraFieldsContainer.insertAdjacentHTML('beforeend', fieldHtml);
        });
    }
}

function initializeGraduationDateModal() {
    const addGradBtn = document.getElementById('addGraduationDateBtn');
    const gradModalEl = document.getElementById('graduationDateModal');
    const gradAlert = document.getElementById('graduationDateAlert');
    const gradSelect = document.getElementById('graduation_date_select');
    const submitGradBtn = document.getElementById('submitGraduationDateBtn');

    if (addGradBtn && gradModalEl) {
        const gradModal = new bootstrap.Modal(gradModalEl);

        addGradBtn.addEventListener('click', () => {
            if (gradAlert) gradAlert.innerHTML = '';
            const dateInput = document.getElementById('newGraduationDate');
            const labelInput = document.getElementById('newGraduationLabel');
            if (dateInput) dateInput.value = '';
            if (labelInput) labelInput.value = '';
            gradModal.show();
        });

        if (submitGradBtn) {
            submitGradBtn.addEventListener('click', function () {
                if (gradAlert) gradAlert.innerHTML = '';

                const dateVal = document.getElementById('newGraduationDate')?.value || '';
                const labelVal = document.getElementById('newGraduationLabel')?.value.trim() || '';

                if (!dateVal || !labelVal) {
                    if (gradAlert) {
                        gradAlert.innerHTML = '<div class="alert alert-warning">Both date and label are required.</div>';
                    }
                    return;
                }

                // Validate date format
                const dateObj = new Date(dateVal);
                if (isNaN(dateObj.getTime())) {
                    if (gradAlert) {
                        gradAlert.innerHTML = '<div class="alert alert-warning">Invalid date format.</div>';
                    }
                    return;
                }

                fetch('add_alumni.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'add_graduation_date',
                        date: dateVal,
                        label: labelVal
                    })
                })
                    .then(r => {
                        if (!r.ok) throw new Error('Network response was not ok');
                        return r.json();
                    })
                    .then(res => {
                        if (res.success && gradSelect) {
                            const opt = document.createElement('option');
                            opt.value = res.date;
                            opt.textContent = res.label;
                            gradSelect.prepend(opt);
                            gradSelect.value = res.date;
                            gradModal.hide();
                        } else if (gradAlert) {
                            gradAlert.innerHTML = `<div class="alert alert-danger">${res.message || 'Failed to add date'}</div>`;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        if (gradAlert) {
                            gradAlert.innerHTML = '<div class="alert alert-danger">Something went wrong.</div>';
                        }
                    });
            });
        }
    }
}

function initializeCourseModal() {
    const addCourseBtn = document.getElementById('addCourseBtn');
    const courseModalEl = document.getElementById('courseModal');
    const courseAlert = document.getElementById('courseAlert');
    const courseSelect = document.getElementById('program_select');
    const submitCourseBtn = document.getElementById('submitCourseBtn');

    if (addCourseBtn && courseModalEl) {
        const courseModal = new bootstrap.Modal(courseModalEl);

        addCourseBtn.addEventListener('click', () => {
            if (courseAlert) courseAlert.innerHTML = '';
            const nameInput = document.getElementById('newCourseName');
            if (nameInput) nameInput.value = '';
            courseModal.show();
        });

        if (submitCourseBtn) {
            submitCourseBtn.addEventListener('click', function () {
                if (courseAlert) courseAlert.innerHTML = '';

                const name = document.getElementById('newCourseName')?.value.trim() || '';
                if (!name) {
                    if (courseAlert) {
                        courseAlert.innerHTML = '<div class="alert alert-warning">Program name is required.</div>';
                    }
                    return;
                }

                fetch('add_alumni.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'add_course',
                        name: name
                    })
                })
                    .then(r => {
                        if (!r.ok) throw new Error('Network response was not ok');
                        return r.json();
                    })
                    .then(res => {
                        if (res.success && courseSelect) {
                            const opt = document.createElement('option');
                            opt.value = res.name;
                            opt.textContent = res.name;
                            courseSelect.prepend(opt);
                            courseSelect.value = res.name;
                            courseModal.hide();
                        } else if (courseAlert) {
                            courseAlert.innerHTML = `<div class="alert alert-danger">${res.message || 'Failed to add program'}</div>`;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        if (courseAlert) {
                            courseAlert.innerHTML = '<div class="alert alert-danger">Network error.</div>';
                        }
                    });
            });
        }
    }
}

function initializeSuccessAlert() {
    const successAlert = document.getElementById('successAlert');
    const errorAlert = document.getElementById('errorAlert');
    const warningAlert = document.getElementById('warningAlert');

    // Set timeout for all alerts to 3 seconds (reduced from 5)
    const hideAlert = (alertElement) => {
        if (alertElement) {
            setTimeout(() => {
                // Use Bootstrap's built-in fade out
                alertElement.classList.remove('show');
                setTimeout(() => {
                    alertElement.style.display = 'none';
                }, 500);
            }, 3000); // Reduced to 3 seconds
        }
    };

    hideAlert(successAlert);
    hideAlert(errorAlert);
    hideAlert(warningAlert);
}



function initializeFormValidation() {
    const form = document.getElementById('addStudentForm');
    if (form) {
        // ⚡ SIMPLE FIX: Check if we're on a conflict page
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('conflict')) {
            return; // DON'T set up validation on conflict pages
        }

        // Rest of your existing code stays the same...
        form.addEventListener('submit', function (event) {
            saveFormState();

            const method = document.querySelector('input[name="add_method"]:checked')?.value;

            if (method === 'manual') {
                if (!validateManualForm()) {
                    event.preventDefault();
                    setTimeout(restoreFormState, 100);
                }
            } else if (method === 'excel') {
                if (!validateExcelFile()) {
                    event.preventDefault();
                    setTimeout(restoreFormState, 100);
                }
            }
        });

        form.addEventListener('input', function () {
            saveFormState();
        });
    }
}

// Helper function to remove required attributes when on conflict page
function removeRequiredAttributes() {
    const requiredFields = [
        'student_name',
        'student_id',
        'graduation_date_select',
        'mobile_no',
        'email',
        'personal_email',
        'program_select'
    ];

    requiredFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.removeAttribute('required');
        }
    });

    // Also remove required from any extra fields
    document.querySelectorAll('#extraFields input').forEach(input => {
        input.removeAttribute('required');
    });
}

function validateManualForm() {
    // Check if we're on a conflict page - if so, skip validation
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('conflict')) {
        return true;
    }
    const isConflictPage = urlParams.has('conflict');

    if (isConflictPage) {
        return true; // Skip validation on conflict pages
    }

    const name = document.getElementById('student_name')?.value.trim();
    const studentId = document.getElementById('student_id')?.value.trim();
    const graduationDate = document.getElementById('graduation_date_select')?.value;
    const program = document.getElementById('program_select')?.value;
    const mobile = document.getElementById('mobile_no')?.value.trim();
    const email = document.getElementById('email')?.value.trim();
    const personalEmail = document.getElementById('personal_email')?.value.trim();

    if (!name || !studentId || !graduationDate || !program || !mobile || !email || !personalEmail) {
        alert('Please fill in all required fields.');
        return false;
    }

    // Validate email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        alert('Please enter a valid email address.');
        return false;
    }

    if (!emailRegex.test(personalEmail)) {
        alert('Please enter a valid personal email address.');
        return false;
    }

    return true;
}

function validateExcelFile() {
    // Check if on a conflict page - if so, skip validation
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('conflict')) {
        return true;
    }
    const isConflictPage = urlParams.has('conflict');

    if (isConflictPage) {
        return true; // Skip validation on conflict pages
    }

    const fileInput = document.getElementById('upload_excel');
    if (!fileInput || !fileInput.files.length) {
        alert('Please select a CSV file to upload.');
        return false;
    }

    const fileName = fileInput.files[0].name.toLowerCase();
    if (!fileName.endsWith('.csv')) {
        alert('Please upload a CSV file.');
        return false;
    }

    return true;
}

// Global function for removing extra fields
function removeExtraField(fieldId) {
    const fieldElement = document.getElementById(`extraField${fieldId}`);
    if (fieldElement) {
        fieldElement.remove();
    }
}

// Make functions available globally
window.removeExtraField = removeExtraField;