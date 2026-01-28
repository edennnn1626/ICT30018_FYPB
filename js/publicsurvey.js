document.addEventListener('DOMContentLoaded', function() {
    initializeSurvey();
});

function initializeSurvey() {
    initializeDatePickers();
    
    // Initialize section navigation first
    initializeSectionNavigation();
    
    // Then initialize form validation
    initializeFormValidation();

    // Initialize radio button validation clearing
    initializeRadioButtonValidation();
    
    // Only initialize expiry check if there's an expiry date
    const formWrapper = document.querySelector('.form-wrapper');
    const expiryDateStr = formWrapper?.dataset.expiry || '';
    
    if (expiryDateStr && expiryDateStr !== '' && expiryDateStr !== '0000-00-00 00:00:00') {
        initializeSurveyExpiryCheck();
    }
}

function initializeSectionNavigation() {
    const sections = document.querySelectorAll('.survey-section');
    const prevBtn = document.getElementById('prevSectionBtn');
    const nextBtn = document.getElementById('nextSectionBtn');
    const submitButtonContainer = document.getElementById('submitButtonContainer');
    const sectionNav = document.querySelector('.section-navigation');
    
    // Hide submit button container for multi-section surveys immediately
    if (sections.length > 1 && submitButtonContainer) {
        submitButtonContainer.style.display = 'none';
    }
    
    // Single section survey
    if (sections.length === 1) {
        // Hide the navigation completely for single section
        if (sectionNav) {
            sectionNav.style.display = 'none';
        }
        
        // Show the submit button in the center with proper styling
        if (submitButtonContainer) {
            submitButtonContainer.style.display = 'flex';
            submitButtonContainer.classList.add('justify-content-center');
            
            // Style the submit button like navigation buttons
            const submitBtn = submitButtonContainer.querySelector('button');
            if (submitBtn) {
                submitBtn.classList.add('btn-primary', 'btn-lg', 'submit-response-btn');
                submitBtn.innerHTML = 'Submit Response <i class="bi bi-check-circle ms-2"></i>';
            }
        }
        
        // Show the single section
        sections.forEach(section => {
            section.style.display = 'block';
        });
        
        return; // Exit early for single section
    }
    
    // Multi-section survey handling
    if (sections.length > 1 && prevBtn && nextBtn) {
        let currentSectionIndex = 0;
        const totalSections = sections.length;

        function updateNavigation() {
            // Update button states
            prevBtn.disabled = currentSectionIndex === 0;
            
            // Update section display - hide all, show current
            sections.forEach((section, index) => {
                section.style.display = index === currentSectionIndex ? 'block' : 'none';
            });
            
            // Update button text and styling
            updateButtonStates(currentSectionIndex, totalSections, nextBtn);
            
            // Scroll to top for better UX
            scrollToSection();
        }

        function updateButtonStates(currentIndex, totalSections, nextButton) {
            if (currentIndex === totalSections - 1) {
                // Last section - change next button to submit style
                nextButton.innerHTML = 'Submit Response <i class="bi bi-check-circle ms-2"></i>';
                nextButton.classList.remove('btn-outline-primary');
                nextButton.classList.add('btn-primary', 'btn-lg');
                nextButton.style.minWidth = '180px';
            } else {
                // Not last section - show next section button
                nextButton.innerHTML = 'Next Section <i class="bi bi-chevron-right ms-2"></i>';
                nextButton.classList.remove('btn-primary', 'btn-lg');
                nextButton.classList.add('btn-outline-primary');
                nextButton.style.minWidth = '';
            }
        }

        function scrollToSection() {
            window.scrollTo({ 
                top: 0,
                behavior: 'smooth'
            });
        }

        // Event listeners
        prevBtn.addEventListener('click', function() {
            if (currentSectionIndex > 0) {
                currentSectionIndex--;
                updateNavigation();
            }
        });

        nextBtn.addEventListener('click', function() {
            if (currentSectionIndex < totalSections - 1) {
                // Validate current section before proceeding
                if (validateCurrentSection()) {
                    currentSectionIndex++;
                    updateNavigation();
                }
            } else {
                // On last section, next button becomes submit button
                if (validateAllSections()) {
                    document.getElementById('surveyForm').submit();
                }
            }
        });

        // Initialize navigation - hide all sections except first
        sections.forEach((section, index) => {
            section.style.display = index === 0 ? 'block' : 'none';
        });
        updateNavigation();
        
    } else {
        // Fallback handling
        if (sectionNav) {
            sectionNav.style.display = 'none';
        }
        
        // Show submit button if no navigation
        if (submitButtonContainer) {
            submitButtonContainer.style.display = 'flex';
            submitButtonContainer.classList.add('justify-content-center');
        }
        
        // Show all sections
        sections.forEach(section => {
            section.style.display = 'block';
        });
    }
}

function validateCurrentSection() {
    const currentSectionIndex = getCurrentSectionIndex();
    const currentSection = document.querySelectorAll('.survey-section')[currentSectionIndex];
    
    if (!currentSection) return true;
    
    const requiredFields = currentSection.querySelectorAll('[required]');
    let isValid = true;
    let firstInvalidField = null;

    requiredFields.forEach(field => {
        if (!validateSingleField(field)) {
            isValid = false;
            if (!firstInvalidField) {
                firstInvalidField = field;
            }
        }
    });

    if (!isValid && firstInvalidField) {
        showAlert('Please fill in all required fields in this section before continuing.', 'warning');
        firstInvalidField.focus();
        firstInvalidField.scrollIntoView({ 
            behavior: 'smooth', 
            block: 'center' 
        });
    }

    return isValid;
}

function getCurrentSectionIndex() {
    const sections = document.querySelectorAll('.survey-section');
    for (let i = 0; i < sections.length; i++) {
        if (sections[i].style.display === 'block') {
            return i;
        }
    }
    return 0;
}

function validateAllSections() {
    let isValid = true;
    const form = document.getElementById('surveyForm');
    const requiredFields = form.querySelectorAll('[required]');
    let firstInvalidField = null;
    
    requiredFields.forEach(field => {
        if (!validateSingleField(field)) {
            isValid = false;
            if (!firstInvalidField) {
                firstInvalidField = field;
            }
        }
    });

    if (!isValid) {
        showAlert('Please fill in all required fields before submitting.', 'warning');
        
        // Scroll to first error
        if (firstInvalidField) {
            // Find which section this field belongs to and show it
            const section = firstInvalidField.closest('.survey-section');
            if (section) {
                section.style.display = 'block';
                section.classList.add('active');
                // Hide other sections
                document.querySelectorAll('.survey-section').forEach(sec => {
                    if (sec !== section) {
                        sec.style.display = 'none';
                        sec.classList.remove('active');
                    }
                });
            }
            
            firstInvalidField.focus();
            firstInvalidField.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center' 
            });
        }
    }

    return isValid;
}

function validateSingleField(field) {
    let isValid = true;
    
    // Remove previous validation states
    field.classList.remove('is-invalid', 'is-valid');
    
    // Check different field types
    if (field.type === 'checkbox' || field.type === 'radio') {
        const groupName = field.name;
        const group = document.querySelectorAll(`[name="${groupName}"]`);
        const isChecked = Array.from(group).some(input => input.checked);
        isValid = isChecked;
        
        // For radio buttons: if invalid, mark all in group; if valid, clear all
        if (!isValid && field.type === 'radio') {
            group.forEach(radio => {
                radio.classList.add('is-invalid');
            });
        } else if (isValid && field.type === 'radio') {
            group.forEach(radio => {
                radio.classList.remove('is-invalid');
            });
        }
    } else if (field.type === 'select-one') {
        isValid = !!field.value;
    } else {
        isValid = field.value.trim() !== '';
    }
    
    // Apply validation state (for non-radio fields)
    if (field.type !== 'radio') {
        if (isValid) {
            field.classList.remove('is-invalid');
        } else {
            field.classList.add('is-invalid');
        }
    }
    
    return isValid;
}

function initializeRadioButtonValidation() {
    // Add change event to all radio buttons to clear validation when selected
    const radioButtons = document.querySelectorAll('input[type="radio"]');
    
    radioButtons.forEach(radio => {
        radio.addEventListener('change', function() {
            const groupName = this.name;
            const group = document.querySelectorAll(`[name="${groupName}"]`);
            
            // Remove invalid class from all radio buttons in this group
            group.forEach(r => {
                r.classList.remove('is-invalid');
            });
            
            // Also validate the field immediately
            validateSingleField(this);
        });
    });
}

function initializeDatePickers() {
    const datePickers = document.querySelectorAll('.date-picker');
    if (datePickers.length > 0 && typeof flatpickr !== 'undefined') {
        datePickers.forEach(el => {
            flatpickr(el, {
                dateFormat: "Y-m-d",
                allowInput: true,
                theme: "light",
                static: true
            });
        });
    }
}

function initializeSurveyExpiryCheck() {
    const formWrapper = document.querySelector('.form-wrapper');
    const expiryDateStr = formWrapper?.dataset.expiry || '';
    
    function checkSurveyExpiry() {
        const expiryTime = new Date(expiryDateStr).getTime();
        const currentTime = new Date().getTime();
        
        if (currentTime > expiryTime) {
            const formWrapper = document.querySelector('.form-wrapper');
            if (formWrapper) {
                formWrapper.innerHTML = `
                    <div class="alert alert-danger text-center">
                        <h4>Survey Expired</h4>
                        <p>This survey has expired and is no longer accepting responses.</p>
                    </div>
                `;
            }
            
            const form = document.getElementById('surveyForm');
            if (form) {
                form.querySelectorAll('input, textarea, select, button').forEach(element => {
                    element.disabled = true;
                });
            }
        }
    }

    checkSurveyExpiry();
    setInterval(checkSurveyExpiry, 60000);
}

function initializeFormValidation() {
    const form = document.getElementById('surveyForm');
    if (!form) return;

    form.addEventListener('submit', function(e) {
        // Client-side validation for better UX
        if (!validateAllSections()) {
            e.preventDefault();
        }
    });

    // Real-time validation for required fields
    const requiredFields = form.querySelectorAll('[required]');
    requiredFields.forEach(field => {
        field.addEventListener('blur', function() {
            validateSingleField(this);
        });
        
        field.addEventListener('input', function() {
            if (this.classList.contains('is-invalid')) {
                validateSingleField(this);
            }
        });
    });
}

function showAlert(message, type = 'info') {
    // Remove existing alerts
    const existingAlert = document.querySelector('.custom-alert');
    if (existingAlert) {
        existingAlert.remove();
    }
    
    // Create new alert
    const alert = document.createElement('div');
    alert.className = `custom-alert alert alert-${type} alert-dismissible fade show`;
    alert.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        max-width: 500px;
    `;
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alert);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (alert.parentNode) {
            alert.remove();
        }
    }, 5000);
}

// Enhanced linear scale interaction
function enhanceLinearScales() {
    const scaleOptions = document.querySelectorAll('.scale-option');
    scaleOptions.forEach(option => {
        option.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.05)';
            this.style.transition = 'transform 0.2s ease';
        });
        
        option.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
}

// Initialize enhanced features
document.addEventListener('DOMContentLoaded', function() {
    enhanceLinearScales();
});