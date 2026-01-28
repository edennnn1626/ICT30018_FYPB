document.addEventListener('DOMContentLoaded', function () {
    initializeSurveyManagement();
});

function initializeSurveyManagement() {
    // Initialize any survey management specific functionality
    initializeSearchFilter();
    initializeDateRangeValidation();
}

function initializeSearchFilter() {
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        searchInput.addEventListener('keyup', function (e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });
    }
}

function initializeDateRangeValidation() {
    const dateFrom = document.querySelector('input[name="date_from"]');
    const dateTo = document.querySelector('input[name="date_to"]');

    if (dateFrom && dateTo) {
        dateFrom.addEventListener('change', function () {
            if (dateTo.value && this.value > dateTo.value) {
                alert('"Date From" cannot be later than "Date To"');
                this.value = '';
            }
        });

        dateTo.addEventListener('change', function () {
            if (dateFrom.value && this.value < dateFrom.value) {
                alert('"Date To" cannot be earlier than "Date From"');
                this.value = '';
            }
        });
    }
}

function confirmDelete(surveyId) {
    if (confirm('Are you sure you want to delete this survey?')) {
        fetch('delete_survey.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${surveyId}`
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Failed to delete survey');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the survey');
            });
    }
}

// Share Modal Functions
const shareModal = new bootstrap.Modal(document.getElementById('shareModal'));
let currentSurveyId = null;
let currentToken = null;
let currentSurveyUrl = null;

function showShareModal(surveyId, title, btn) {
    currentSurveyId = surveyId;
    document.getElementById('surveyTitle').textContent = title;

    // Get token from button data attribute
    currentToken = btn.dataset.token;

    // Build survey URL
    const protocol = window.location.protocol;
    const host = window.location.host;
    const pathname = window.location.pathname;
    const folder = pathname.substring(0, pathname.lastIndexOf('/') + 1);
    const baseUrl = protocol + '//' + host + folder;

    currentSurveyUrl = `${baseUrl}public_survey.php?token=${currentToken}`;
    document.getElementById('surveyLink').value = currentSurveyUrl;

    // Generate QR code
    const qr = qrcode(0, 'L');
    qr.addData(currentSurveyUrl);
    qr.make();
    document.getElementById('qrCodeImg').src = qr.createDataURL(4);

    // Reset modal UI
    resetShareModal();
    resetProgressBar();

    // Show modal
    shareModal.show();
}

function resetShareModal() {
    // Reset to single share mode
    document.getElementById('singleShare').checked = true;
    document.getElementById('singleShareSection').style.display = 'block';
    document.getElementById('bulkShareSection').style.display = 'none';

    // Reset form fields
    document.getElementById('alumniSearch').value = '';
    document.getElementById('alumniResults').innerHTML = '';
    document.getElementById('alumniResults').style.display = 'none';
    document.getElementById('emailTypeSection').style.display = 'none';
    document.getElementById('singleStudentEmail').checked = true;
    document.getElementById('courseSelect').value = '';
    document.getElementById('yearSelect').value = '';
    document.getElementById('sendStudentEmail').checked = true;
    document.getElementById('sendPersonalEmail').checked = false;
    document.getElementById('shareStatus').style.display = 'none';
    document.getElementById('shareStatus').innerHTML = '';
    document.getElementById('sendProgressWrapper').style.display = 'none';
    document.getElementById('sendProgressBar').style.width = '0%';
    document.getElementById('sendProgressBar').classList.add('progress-bar-animated', 'progress-bar-striped');
}

function copyToClipboard() {
    const copyText = document.getElementById("surveyLink");
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    document.execCommand("copy");

    const copyBtn = document.querySelector('.copy-btn');
    const originalHtml = copyBtn.innerHTML;
    copyBtn.innerHTML = '<i class="bi bi-check"></i> Copied!';
    copyBtn.classList.add('btn-success');
    copyBtn.classList.remove('btn-outline-secondary');

    setTimeout(() => {
        copyBtn.innerHTML = originalHtml;
        copyBtn.classList.remove('btn-success');
        copyBtn.classList.add('btn-outline-secondary');
    }, 2000);
}

function downloadQRCode() {
    const qrCodeImg = document.getElementById('qrCodeImg');
    const link = document.createElement('a');
    link.download = `survey-${currentSurveyId}-qrcode.png`;
    link.href = qrCodeImg.src;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Initialize modal event listeners when DOM is loaded
document.addEventListener('DOMContentLoaded', function () {
    // Modal event listeners
    const singleShareRadio = document.getElementById('singleShare');
    const bulkShareRadio = document.getElementById('bulkShare');
    const singleShareSection = document.getElementById('singleShareSection');
    const bulkShareSection = document.getElementById('bulkShareSection');
    const searchBox = document.getElementById('alumniSearch');
    const resultBox = document.getElementById('alumniResults');
    const emailTypeSection = document.getElementById('emailTypeSection');
    const shareSurveyBtn = document.getElementById('shareSurveyBtn');
    const shareStatus = document.getElementById('shareStatus');

    if (singleShareRadio && bulkShareRadio) {
        singleShareRadio.addEventListener('change', function () {
            if (this.checked) {
                singleShareSection.style.display = 'block';
                bulkShareSection.style.display = 'none';
                resetProgressBar();
            }
        });

        bulkShareRadio.addEventListener('change', function () {
            if (this.checked) {
                singleShareSection.style.display = 'none';
                bulkShareSection.style.display = 'block';
                resetProgressBar();
            }
        });
    }

    // Alumni search functionality
    if (searchBox && resultBox) {
        let selectedAlumni = null;
        let searchTimer = null;

        searchBox.addEventListener("input", function () {
            const q = this.value.trim();
            selectedAlumni = null;
            emailTypeSection.style.display = "none";

            if (q.length < 3) {
                resultBox.style.display = "none";
                resultBox.innerHTML = "";
                return;
            }

            if (searchTimer) clearTimeout(searchTimer);

            searchTimer = setTimeout(() => {
                fetch(`search_alumni.php?q=${encodeURIComponent(q)}`)
                    .then(res => res.json())
                    .then(list => {
                        resultBox.innerHTML = "";

                        if (list.length === 0) {
                            resultBox.style.display = "none";
                            return;
                        }

                        list.forEach(a => {
                            const item = document.createElement("button");
                            item.className = "list-group-item list-group-item-action";

                            const uni = a.email || "No student email";
                            const per = a.personal_email || "No personal email";
                            const label = `${a.name} (${uni} / ${per})`;

                            item.textContent = label;

                            item.addEventListener("click", () => {
                                searchBox.value = `${a.email || 'No student email'} / ${a.personal_email || 'No personal email'}`;
                                selectedAlumni = a;
                                searchBox.dataset.student = a.email || "";
                                searchBox.dataset.personal = a.personal_email || "";
                                resultBox.style.display = "none";
                                emailTypeSection.style.display = "block";

                                // Store the alumni data globally
                                window.selectedAlumni = a;
                            });

                            resultBox.appendChild(item);
                        });

                        resultBox.style.display = "block";
                    })
                    .catch(error => {
                        console.error('Search error:', error);
                        resultBox.style.display = "none";
                    });
            }, 300);
        });

        // Hide result box when clicking outside
        document.addEventListener("click", function (e) {
            if (!searchBox.contains(e.target) && !resultBox.contains(e.target)) {
                resultBox.style.display = "none";
            }
        });
    }

    // Share button handler
    if (shareSurveyBtn) {
        shareSurveyBtn.addEventListener('click', handleShareSurvey);
    }
});

function handleShareSurvey() {
    const isSingleMode = document.getElementById('singleShare').checked;
    const shareSurveyBtn = document.getElementById('shareSurveyBtn');
    const shareStatus = document.getElementById('shareStatus');

    // Show progress bar
    document.getElementById("sendProgressWrapper").style.display = "block";
    const bar = document.getElementById("sendProgressBar");
    bar.style.width = "5%";

    // Progress animation
    let p = 5;
    const progressTimer = setInterval(() => {
        p += 2;
        if (p >= 95) p = 95;
        bar.style.width = p + "%";
    }, 200);

    if (isSingleMode) {
        handleSingleShare(progressTimer, bar);
    } else {
        handleBulkShare(progressTimer, bar);
    }
}

function handleSingleShare(progressTimer, progressBar) {
    const searchBox = document.getElementById('alumniSearch');
    const typedValue = searchBox.value.trim();
    const selectedAlumni = window.selectedAlumni || null;
    const emailType = selectedAlumni ?
        document.querySelector('input[name="singleEmailType"]:checked').value : null;

    // Validate email function
    const validateEmail = (email) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

    let emailsToSend = [];

    if (selectedAlumni) {
        // Use stored alumni data
        if (emailType === "student" && selectedAlumni.email && validateEmail(selectedAlumni.email)) {
            emailsToSend.push({ email: selectedAlumni.email, type: 'student' });
        } else if (emailType === "personal" && selectedAlumni.personal_email && validateEmail(selectedAlumni.personal_email)) {
            emailsToSend.push({ email: selectedAlumni.personal_email, type: 'personal' });
        } else if (emailType === "both") {
            if (selectedAlumni.email && validateEmail(selectedAlumni.email)) {
                emailsToSend.push({ email: selectedAlumni.email, type: 'student' });
            }
            if (selectedAlumni.personal_email && validateEmail(selectedAlumni.personal_email)) {
                emailsToSend.push({ email: selectedAlumni.personal_email, type: 'personal' });
            }
        }
    } else {
        // Manual entry - parse the format "student_email / personal_email"
        const parts = typedValue.split('/').map(part => part.trim());

        if (parts.length === 2) {
            // Format: "student_email / personal_email"
            const [studentEmail, personalEmail] = parts;

            // If both are valid emails, show option to choose
            if (validateEmail(studentEmail) && validateEmail(personalEmail)) {
                // Ask user which email to send to
                const emailChoice = prompt(`Found 2 emails:\n1. ${studentEmail} (student)\n2. ${personalEmail} (personal)\n\nEnter 1, 2, or "both":`);

                if (emailChoice === '1' || emailChoice === 'student') {
                    emailsToSend.push({ email: studentEmail, type: 'student' });
                } else if (emailChoice === '2' || emailChoice === 'personal') {
                    emailsToSend.push({ email: personalEmail, type: 'personal' });
                } else if (emailChoice === 'both' || emailChoice === '12') {
                    emailsToSend.push({ email: studentEmail, type: 'student' });
                    emailsToSend.push({ email: personalEmail, type: 'personal' });
                } else {
                    alert('No email selected.');
                    clearInterval(progressTimer);
                    document.getElementById("sendProgressWrapper").style.display = "none";
                    return;
                }
            } else if (validateEmail(studentEmail)) {
                emailsToSend.push({ email: studentEmail, type: 'student' });
            } else if (validateEmail(personalEmail)) {
                emailsToSend.push({ email: personalEmail, type: 'personal' });
            }
        } else if (parts.length === 1 && validateEmail(parts[0])) {
            // Single email
            emailsToSend.push({ email: parts[0], type: 'manual' });
        }
    }

    // Check if we have any valid emails
    if (emailsToSend.length === 0) {
        alert("No valid email address found. Please check the format:\n\nStudent Email / Personal Email\n\nor select an alumni from the dropdown.");
        clearInterval(progressTimer);
        document.getElementById("sendProgressWrapper").style.display = "none";
        return;
    }

    // Prepare payload
    let payload = {
        survey_id: currentSurveyId,
        survey_link: currentSurveyUrl
    };

    if (selectedAlumni) {
        // Alumni selected from dropdown
        payload.alumni_id = selectedAlumni.id;
        payload.email_type = emailType;
        payload.student_email = selectedAlumni.email || null;
        payload.personal_email = selectedAlumni.personal_email || null;
    } else {
        // Manual entry
        payload.email_type = 'manual';
        payload.emails = emailsToSend;
    }

    sendShareRequest(payload, progressTimer, progressBar);
}

function handleBulkShare(progressTimer, progressBar) {
    const course = document.getElementById('courseSelect').value;
    const year = document.getElementById('yearSelect').value;
    const sendStudent = document.getElementById('sendStudentEmail').checked ? 1 : 0;
    const sendPersonal = document.getElementById('sendPersonalEmail').checked ? 1 : 0;
    const shareStatus = document.getElementById('shareStatus');

    if (!sendStudent && !sendPersonal) {
        shareStatus.innerHTML = '<div class="alert alert-warning">Please select at least one email type to send.</div>';
        shareStatus.style.display = 'block';
        clearInterval(progressTimer);
        document.getElementById("sendProgressWrapper").style.display = "none";
        return;
    }

    // Validate that at least one filter is selected
    if (!course && !year) {
        shareStatus.innerHTML = '<div class="alert alert-warning">Please select at least a course or year to filter alumni.</div>';
        shareStatus.style.display = 'block';
        clearInterval(progressTimer);
        document.getElementById("sendProgressWrapper").style.display = "none";
        return;
    }

    const payload = {
        survey_id: currentSurveyId,
        course: course,
        year: year,
        send_student_email: sendStudent,
        send_personal_email: sendPersonal,
        survey_link: currentSurveyUrl
    };

    sendShareRequest(payload, progressTimer, progressBar);
}

function sendShareRequest(payload, progressTimer, progressBar) {
    const shareSurveyBtn = document.getElementById('shareSurveyBtn');
    const shareStatus = document.getElementById('shareStatus');

    shareSurveyBtn.disabled = true;
    shareStatus.style.display = 'block';
    shareStatus.innerHTML = '<div class="spinner-border spinner-border-sm"></div> Sending...';

    fetch('send_survey.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(result => {
            clearInterval(progressTimer);
            progressBar.style.width = "100%";
            progressBar.classList.remove("progress-bar-animated", "progress-bar-striped");

            shareSurveyBtn.disabled = false;

            if (result.success) {
                let message = `<div class="alert alert-success">
            <strong><i class="bi bi-check-circle"></i> Success!</strong><br>`;

                if (result.total_alumni !== undefined) {
                    // Bulk share response
                    message += `Found ${result.total_alumni} alumni.<br>`;
                    message += `Sent: ${result.sent_count} email${result.sent_count !== 1 ? 's' : ''}.<br>`;

                    if (result.skipped_student_count > 0) {
                        message += `Skipped student emails: ${result.skipped_student_count}.<br>`;
                    }
                    if (result.skipped_personal_count > 0) {
                        message += `Skipped personal emails: ${result.skipped_personal_count}.<br>`;
                    }

                    if (result.summary) {
                        message += `<small class="text-muted">${result.summary}</small>`;
                    }
                } else {
                    // Single share response
                    message += `Email sent successfully.`;
                }

                message += `</div>`;
                shareStatus.innerHTML = message;

            } else {
                let errorMsg = result.message || 'Failed to send email.';
                if (result.errors && result.errors.length > 0) {
                    errorMsg += '<br><small class="text-muted">' + result.errors.join('<br>') + '</small>';
                }
                shareStatus.innerHTML = `<div class="alert alert-danger"><strong><i class="bi bi-exclamation-triangle"></i> Error:</strong> ${errorMsg}</div>`;
            }
        })
        .catch(error => {
            clearInterval(progressTimer);
            shareSurveyBtn.disabled = false;
            progressBar.style.width = "0%";
            document.getElementById("sendProgressWrapper").style.display = "none";
            shareStatus.innerHTML = `<div class="alert alert-danger"><strong>Network Error:</strong> Could not connect to server.</div>`;
            console.error('Share error:', error);
        });
}

function resetProgressBar() {
    const progressWrapper = document.getElementById('sendProgressWrapper');
    const progressBar = document.getElementById('sendProgressBar');
    const shareStatus = document.getElementById('shareStatus');

    // Hide progress wrapper
    progressWrapper.style.display = 'none';

    // Reset progress bar to 0%
    progressBar.style.width = '0%';
    progressBar.classList.add('progress-bar-animated', 'progress-bar-striped');

    // Clear status message
    shareStatus.style.display = 'none';
    shareStatus.innerHTML = '';

    // Enable share button if disabled
    const shareSurveyBtn = document.getElementById('shareSurveyBtn');
    if (shareSurveyBtn) {
        shareSurveyBtn.disabled = false;
    }
}