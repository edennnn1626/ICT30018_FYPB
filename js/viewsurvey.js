// viewsurvey.js - Professional Survey View Functionality
document.addEventListener('DOMContentLoaded', function() {
    initializeSurveyView();
});

function initializeSurveyView() {
    initializeDatePickers();
    initializeSectionNavigation();
    initializeFormInteractions();
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

function initializeSectionNavigation() {
    const sections = document.querySelectorAll('.survey-section');
    const prevBtn = document.getElementById('prevSectionBtn');
    const nextBtn = document.getElementById('nextSectionBtn');
    const currentSectionTitle = document.getElementById('currentSectionTitle');
    const currentSectionNumber = document.getElementById('currentSectionNumber');
    
    // Only initialize if there are multiple sections and navigation elements exist
    if (sections.length > 1 && prevBtn && nextBtn) {
        let currentSectionIndex = 0;
        const totalSections = sections.length;

        function updateNavigation() {
            // Update button states with smooth transitions
            prevBtn.disabled = currentSectionIndex === 0;
            nextBtn.disabled = currentSectionIndex === totalSections - 1;
            
            // Update section display with fade animation
            sections.forEach((section, index) => {
                if (index === currentSectionIndex) {
                    section.style.display = 'block';
                    section.classList.add('active');
                    // Trigger reflow for animation
                    void section.offsetWidth;
                } else {
                    section.style.display = 'none';
                    section.classList.remove('active');
                }
            });
            
            // Update section info
            updateSectionInfo(currentSectionIndex, sections, currentSectionTitle, currentSectionNumber, totalSections);
            
            // Update button text and styling
            updateButtonStates(currentSectionIndex, totalSections, nextBtn);
            
            // Scroll to top of section for better UX
            scrollToSection();
        }

        function updateSectionInfo(index, sections, titleEl, numberEl, total) {
            const currentSection = sections[index];
            const sectionHeader = currentSection.querySelector('.section-header h3');
            
            if (sectionHeader) {
                titleEl.textContent = sectionHeader.textContent.trim();
            } else {
                titleEl.textContent = `Section ${index + 1}`;
            }
            
            numberEl.textContent = index + 1;
        }

        function updateButtonStates(currentIndex, totalSections, nextButton) {
            if (currentIndex === totalSections - 1) {
                nextButton.innerHTML = 'Next Survey <i class="bi bi-eye ms-2"></i>';
                nextButton.classList.add('btn-review');
                nextButton.classList.remove('btn-outline-primary');
                nextButton.disabled = true;
            } else {
                nextButton.innerHTML = 'Next Section <i class="bi bi-chevron-right ms-2"></i>';
                nextButton.classList.remove('btn-review');
                nextButton.classList.add('btn-outline-primary');
                nextButton.disabled = false;
            }
            
            // Update previous button styling
            const prevButton = document.getElementById('prevSectionBtn');
            if (prevButton) {
                if (currentIndex === 0) {
                    prevButton.disabled = true;
                } else {
                    prevButton.disabled = false;
                }
            }
        }

        function scrollToSection() {
            const formWrapper = document.querySelector('.form-wrapper');
            if (formWrapper) {
                formWrapper.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'start'
                });
            }
        }

        // Event listeners with debouncing
        let isNavigating = false;
        
        prevBtn.addEventListener('click', function() {
            if (!isNavigating && currentSectionIndex > 0) {
                isNavigating = true;
                currentSectionIndex--;
                updateNavigation();
                setTimeout(() => { isNavigating = false; }, 300);
            }
        });

        nextBtn.addEventListener('click', function() {
            if (!isNavigating && currentSectionIndex < totalSections - 1) {
                isNavigating = true;
                currentSectionIndex++;
                updateNavigation();
                setTimeout(() => { isNavigating = false; }, 300);
            }
        });

        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft' && !prevBtn.disabled) {
                prevBtn.click();
            } else if (e.key === 'ArrowRight' && !nextBtn.disabled) {
                nextBtn.click();
            }
        });

        // Initialize navigation
        updateNavigation();
    } else {
        // Hide navigation if only one section or elements not found
        const sectionNav = document.querySelector('.section-navigation');
        if (sectionNav) {
            sectionNav.style.display = 'none';
        }
        
        // Show all sections if only one exists
        if (sections.length === 1) {
            sections[0].style.display = 'block';
            sections[0].classList.add('active');
        }
    }
}

function initializeFormInteractions() {
    // Add required field indicators
    addRequiredFieldIndicators();

    // Add focus styles to form elements
    const formElements = document.querySelectorAll('.form-control, .form-select, .form-check-input');
    
    formElements.forEach(element => {
        element.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        element.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
        });
    });

    // Linear scale hover effects
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

    // Enhanced radio and checkbox styling
    const customChecks = document.querySelectorAll('.form-check-input');
    customChecks.forEach(check => {
        check.addEventListener('change', function() {
            // For radio buttons: reset all labels in the same group first
            if (this.type === 'radio') {
                const groupName = this.name;
                document.querySelectorAll(`input[name="${groupName}"]`).forEach(radio => {
                    const radioLabel = radio.closest('.form-check')?.querySelector('.form-check-label');
                    if (radioLabel) {
                        radioLabel.classList.remove('text-primary', 'fw-semibold');
                    }
                });
            }
            
            // For checkboxes and now-selected radio button
            const label = this.closest('.form-check')?.querySelector('.form-check-label');
            if (label) {
                if (this.checked) {
                    label.classList.add('text-primary', 'fw-semibold');
                } else {
                    label.classList.remove('text-primary', 'fw-semibold');
                }
            }
        });
        
        // Initialize styles
        const label = check.closest('.form-check')?.querySelector('.form-check-label');
        if (label && check.checked) {
            label.classList.add('text-primary', 'fw-semibold');
        }
    });
}

function addRequiredFieldIndicators() {
    const questionBlocks = document.querySelectorAll('.question-block');
    
    questionBlocks.forEach(block => {
        const requiredInput = block.querySelector('[required]');
        if (requiredInput) {
            block.classList.add('required-field');
        }
    });
}

// Utility function to validate required fields in current section
function validateCurrentSection() {
    const currentSection = document.querySelector('.survey-section.active');
    const requiredFields = currentSection.querySelectorAll('[required]');
    let isValid = true;

    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });

    return isValid;
}

// Export functions for potential future use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        initializeSurveyView,
        validateCurrentSection
    };
}