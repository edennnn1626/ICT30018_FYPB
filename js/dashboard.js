// Dashboard functionality
document.addEventListener('DOMContentLoaded', () => {
    initDashboard();
    setupYearFilter();
    initializeDashboardAnalytics();
});

// Global variables
let selectedSurveyId = null;
let surveyPage = 1;
let surveyPerPage = 4;
let aggregatedPage = 1;
let aggregatedPerPage = 3; // Show 3 questions at a time
let individualPage = 1;
let individualPerPage = 5; // Show 5 responses at a time
let currentAggregatedData = null;
let currentIndividualData = null;

function initDashboard() {
    setupSurveyFilter();
    setupSurveyPagination();
    backToTopButton();
    renderSurveys();
}

// dashboard.js - Update setupYearFilter function
function setupYearFilter() {
    const yearSelect = document.getElementById('yearSelect');
    const customInput = document.getElementById('customYearsInput');
    const applyBtn = document.getElementById('applyYearsBtn');

    if (yearSelect) {
        yearSelect.addEventListener('change', () => {
            if (yearSelect.value === 'custom') {
                customInput.style.display = 'inline-block';
                applyBtn.style.display = 'inline-block';
                customInput.focus();
                customInput.required = true;
            } else {
                customInput.style.display = 'none';
                applyBtn.style.display = 'none';
                customInput.required = false;
                // Auto-submit when non-custom is selected
                document.getElementById('yearFilterForm').submit();
            }
        });

        // Also handle form submit for custom years
        const form = document.getElementById('yearFilterForm');
        form.addEventListener('submit', function (e) {
            if (yearSelect.value === 'custom' && (!customInput.value || customInput.value < 1)) {
                e.preventDefault();
                alert('Please enter a valid number of years');
                customInput.focus();
            }
        });
    }
}

function setupSurveyFilter() {
    const input = document.getElementById('surveyFilter');
    if (!input) return;
    input.addEventListener('input', () => {
        surveyPage = 1;
        renderSurveys();
        renderSurveyPagination();
    });
}

function renderSurveys() {
    const filter = document.getElementById('surveyFilter')?.value.toLowerCase() || '';
    const items = Array.from(document.querySelectorAll('.survey-item'));
    items.forEach(it => it.style.display = 'none');

    const filtered = items.filter(it => {
        const title = it.dataset.surveyTitle || '';
        const id = it.dataset.surveyId || '';
        return title.includes(filter) || id.includes(filter);
    });

    const start = (surveyPage - 1) * surveyPerPage;
    const end = start + surveyPerPage;
    filtered.slice(start, end).forEach(it => it.style.display = 'block');

    // Keep the first survey as active if none is selected
    if (!selectedSurveyId && filtered.length > 0) {
        const firstVisible = filtered.slice(start, end)[0];
        if (firstVisible) {
            selectedSurveyId = firstVisible.dataset.surveyId;
        }
    }

    items.forEach(it => it.classList.remove('active-survey'));
    if (selectedSurveyId) {
        const active = document.querySelector(`.survey-item[data-survey-id="${selectedSurveyId}"]`);
        if (active) active.classList.add('active-survey');
    }
}

function setupSurveyPagination() {
    renderSurveys();
    renderSurveyPagination();
}

function renderSurveyPagination() {
    const container = document.getElementById('surveyPagination');
    if (!container) return;

    const items = Array.from(document.querySelectorAll('.survey-item'));
    const filter = document.getElementById('surveyFilter')?.value.toLowerCase() || '';

    const filtered = items.filter(it => {
        const title = it.dataset.surveyTitle || '';
        const id = it.dataset.surveyId || '';
        return title.includes(filter) || id.includes(filter);
    });

    const totalPages = Math.ceil(filtered.length / surveyPerPage);
    container.innerHTML = '';
    if (totalPages <= 1) return;

    // Previous button
    const prevBtn = document.createElement('button');
    prevBtn.className = 'pagination-btn';
    prevBtn.innerHTML = '<i class="bi bi-chevron-left"></i>';
    prevBtn.disabled = surveyPage === 1;
    prevBtn.addEventListener('click', () => {
        if (surveyPage > 1) {
            surveyPage--;
            renderSurveys();
            renderSurveyPagination();
        }
    });
    container.appendChild(prevBtn);

    // Page numbers - always show page 1, then current page, then last page if applicable
    // Always show page 1
    const firstBtn = document.createElement('button');
    firstBtn.className = 'pagination-btn';
    firstBtn.innerText = '1';
    if (1 === surveyPage) firstBtn.classList.add('active');
    firstBtn.addEventListener('click', () => {
        surveyPage = 1;
        renderSurveys();
        renderSurveyPagination();
    });
    container.appendChild(firstBtn);

    // Show current page if it's not page 1
    if (surveyPage > 1 && surveyPage < totalPages) {
        const currentBtn = document.createElement('button');
        currentBtn.className = 'pagination-btn';
        currentBtn.innerText = surveyPage;
        currentBtn.classList.add('active');
        currentBtn.addEventListener('click', () => {
            surveyPage = surveyPage;
            renderSurveys();
            renderSurveyPagination();
        });
        container.appendChild(currentBtn);
    }

    // Show ellipsis if there are pages between current and last
    if (totalPages > 3 && surveyPage < totalPages - 1) {
        const ellipsis = document.createElement('span');
        ellipsis.className = 'pagination-ellipsis';
        ellipsis.innerHTML = '...';
        container.appendChild(ellipsis);
    }

    // Always show last page if it's different from page 1
    if (totalPages > 1) {
        const lastBtn = document.createElement('button');
        lastBtn.className = 'pagination-btn';
        lastBtn.innerText = totalPages;
        if (totalPages === surveyPage) lastBtn.classList.add('active');
        lastBtn.addEventListener('click', () => {
            surveyPage = totalPages;
            renderSurveys();
            renderSurveyPagination();
        });
        container.appendChild(lastBtn);
    }

    // Next button
    const nextBtn = document.createElement('button');
    nextBtn.className = 'pagination-btn';
    nextBtn.innerHTML = '<i class="bi bi-chevron-right"></i>';
    nextBtn.disabled = surveyPage === totalPages;
    nextBtn.addEventListener('click', () => {
        if (surveyPage < totalPages) {
            surveyPage++;
            renderSurveys();
            renderSurveyPagination();
        }
    });
    container.appendChild(nextBtn);
}

// Enhanced Dashboard Analytics Functions
function initializeDashboardAnalytics() {
    // Set default to first survey (which is the latest)
    const firstSurvey = document.querySelector('.survey-item.active-survey');
    if (firstSurvey) {
        const surveyId = firstSurvey.dataset.surveyId;
        selectedSurveyId = surveyId;
        loadSurveyData(surveyId);
    }

    // Setup pagination buttons
    setupPaginationButtons();

    // Toggle view button - starts with "Switch to Individual View" since we're in aggregated view
    const toggleBtn = document.getElementById('toggleViewBtn');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            toggleAggregatedIndividualView();
        });
    }

    // Export button
    const exportBtn = document.getElementById('exportSurveyBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function () {
            const activeSurvey = document.querySelector('.survey-item.active-survey');
            if (activeSurvey) {
                exportSurveyData(activeSurvey.dataset.surveyId);
            } else {
                alert('Please select a survey first');
            }
        });
    }

    // Response search
    const responseSearch = document.getElementById('responseSearch');
    if (responseSearch) {
        responseSearch.addEventListener('input', function (e) {
            searchResponses(e.target.value);
        });
    }

    // Update survey click to also load analytics
    setupSurveyClickWithAnalytics();
}

function setupPaginationButtons() {
    // Aggregated view pagination
    const firstAggregated = document.getElementById('firstAggregated');
    const prevAggregated = document.getElementById('prevAggregated');
    const nextAggregated = document.getElementById('nextAggregated');
    const lastAggregated = document.getElementById('lastAggregated');

    if (firstAggregated) {
        firstAggregated.addEventListener('click', () => {
            aggregatedPage = 1;
            renderAggregatedAnswers(currentAggregatedData);
        });
    }

    if (prevAggregated) {
        prevAggregated.addEventListener('click', () => {
            if (aggregatedPage > 1) {
                aggregatedPage--;
                renderAggregatedAnswers(currentAggregatedData);
            }
        });
    }

    if (nextAggregated) {
        nextAggregated.addEventListener('click', () => {
            const totalQuestions = currentAggregatedData ? Object.keys(currentAggregatedData).length : 0;
            const totalPages = Math.ceil(totalQuestions / aggregatedPerPage);
            if (aggregatedPage < totalPages) {
                aggregatedPage++;
                renderAggregatedAnswers(currentAggregatedData);
            }
        });
    }

    if (lastAggregated) {
        lastAggregated.addEventListener('click', () => {
            const totalQuestions = currentAggregatedData ? Object.keys(currentAggregatedData).length : 0;
            const totalPages = Math.ceil(totalQuestions / aggregatedPerPage);
            aggregatedPage = totalPages;
            renderAggregatedAnswers(currentAggregatedData);
        });
    }

    // Individual view pagination
    const firstResponse = document.getElementById('firstResponse');
    const prevResponse = document.getElementById('prevResponse');
    const nextResponse = document.getElementById('nextResponse');
    const lastResponse = document.getElementById('lastResponse');

    if (firstResponse) {
        firstResponse.addEventListener('click', () => {
            individualPage = 1;
            renderIndividualResponses(currentIndividualData);
        });
    }

    if (prevResponse) {
        prevResponse.addEventListener('click', () => {
            if (individualPage > 1) {
                individualPage--;
                renderIndividualResponses(currentIndividualData);
            }
        });
    }

    if (nextResponse) {
        nextResponse.addEventListener('click', () => {
            const totalResponses = currentIndividualData ? currentIndividualData.length : 0;
            const totalPages = Math.ceil(totalResponses / individualPerPage);
            if (individualPage < totalPages) {
                individualPage++;
                renderIndividualResponses(currentIndividualData);
            }
        });
    }

    if (lastResponse) {
        lastResponse.addEventListener('click', () => {
            const totalResponses = currentIndividualData ? currentIndividualData.length : 0;
            const totalPages = Math.ceil(totalResponses / individualPerPage);
            individualPage = totalPages;
            renderIndividualResponses(currentIndividualData);
        });
    }
}

function setupSurveyClickWithAnalytics() {
    document.addEventListener('click', e => {
        const survey = e.target.closest('.survey-item');
        if (!survey) return;

        const id = survey.dataset.surveyId;

        // Remove active class from all surveys
        document.querySelectorAll('.survey-item').forEach(s => {
            s.classList.remove('active-survey');
        });

        // Add active class to clicked survey
        survey.classList.add('active-survey');
        selectedSurveyId = id;

        // Load analytics for this survey
        loadSurveyData(id);
    });
}

function toggleAggregatedIndividualView() {
    const aggregatedView = document.getElementById('aggregatedView');
    const individualView = document.getElementById('individualView');
    const toggleBtn = document.getElementById('toggleViewBtn');

    if (aggregatedView.style.display === 'none') {
        // Switch to aggregated view
        aggregatedView.style.display = 'block';
        individualView.style.display = 'none';
        toggleBtn.innerHTML = '<i class="bi bi-list-ul"></i> Switch to Individual View';
        toggleBtn.classList.remove('btn-outline-secondary');
        toggleBtn.classList.add('btn-outline-primary');
    } else {
        // Switch to individual view
        aggregatedView.style.display = 'none';
        individualView.style.display = 'block';
        toggleBtn.innerHTML = '<i class="bi bi-bar-chart"></i> Switch to Aggregated View';
        toggleBtn.classList.remove('btn-outline-primary');
        toggleBtn.classList.add('btn-outline-secondary');
    }
}

function loadSurveyData(surveyId) {
    if (!surveyId) return;

    // Reset pagination
    aggregatedPage = 1;
    individualPage = 1;

    // Show loading state
    const aggregatedAnswers = document.getElementById('aggregatedAnswers');
    const responsesContainer = document.getElementById('responsesListContainer');

    if (aggregatedAnswers) {
        aggregatedAnswers.innerHTML =
            '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Loading survey data...</p></div>';
    }

    if (responsesContainer) {
        responsesContainer.innerHTML =
            '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Loading responses...</p></div>';
    }

    // Add cache busting parameter
    const timestamp = new Date().getTime();

    // Fetch survey data via AJAX
    fetch(`get_survey_data.php?survey_id=${surveyId}&_=${timestamp}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Survey data loaded:', data);

            if (data.error) {
                showAlert(data.error, 'danger');
                // Show error in UI
                if (aggregatedAnswers) {
                    aggregatedAnswers.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                }
                return;
            }

            if (!data.success) {
                showAlert('Failed to load survey data', 'danger');
                if (aggregatedAnswers) {
                    aggregatedAnswers.innerHTML = '<div class="alert alert-warning">No data available for this survey</div>';
                }
                return;
            }

            updateSurveyStats(data.stats);
            currentAggregatedData = data.aggregated;
            currentIndividualData = data.responses;
            renderAggregatedAnswers(data.aggregated);
            renderIndividualResponses(data.responses);
        })
        .catch(error => {
            console.error('Error loading survey data:', error);
            const errorMsg = error.message || 'Failed to load survey data. Please try again.';

            if (aggregatedAnswers) {
                aggregatedAnswers.innerHTML = `
                    <div class="alert alert-danger">
                        <strong>Error:</strong> ${errorMsg}<br>
                        <small>Check browser console for details.</small>
                    </div>`;
            }
            if (responsesContainer) {
                responsesContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <strong>Error:</strong> Failed to load responses.
                    </div>`;
            }

            showAlert('Error loading survey data', 'danger');
        });
}

function updateSurveyStats(stats) {
    const statsContainer = document.getElementById('surveyStats');
    if (!statsContainer || !stats) return;

    statsContainer.innerHTML = `
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="bi bi-chat-square-text"></i></div>
                <div class="stat-value">${stats.total_responses || 0}</div>
                <div class="stat-label">Total Responses</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="bi bi-people"></i></div>
                <div class="stat-value">${stats.unique_respondents || 0}</div>
                <div class="stat-label">Unique Respondents</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="bi bi-calendar-plus"></i></div>
                <div class="stat-value">${stats.first_response || 'N/A'}</div>
                <div class="stat-label">First Response</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
                <div class="stat-value">${stats.last_response || 'N/A'}</div>
                <div class="stat-label">Last Response</div>
            </div>
        </div>
    `;
}

function renderAggregatedAnswers(aggregatedData) {
    const container = document.getElementById('aggregatedAnswers');
    const aggregatedCount = document.getElementById('aggregatedCount');
    const firstBtn = document.getElementById('firstAggregated');
    const prevBtn = document.getElementById('prevAggregated');
    const nextBtn = document.getElementById('nextAggregated');
    const lastBtn = document.getElementById('lastAggregated');

    if (!container) return;

    if (!aggregatedData || Object.keys(aggregatedData).length === 0) {
        container.innerHTML = '<div class="text-center py-4"><p class="text-muted">No responses yet for this survey</p></div>';
        if (aggregatedCount) aggregatedCount.textContent = '0 questions';
        if (firstBtn) firstBtn.disabled = true;
        if (prevBtn) prevBtn.disabled = true;
        if (nextBtn) nextBtn.disabled = true;
        if (lastBtn) lastBtn.disabled = true;
        renderAggregatedPagination(0);
        return;
    }

    // Store the data
    currentAggregatedData = aggregatedData;

    // Get all questions
    const questions = Object.entries(aggregatedData);
    const totalQuestions = questions.length;
    const totalPages = Math.ceil(totalQuestions / aggregatedPerPage);

    // Calculate start and end indices
    const start = (aggregatedPage - 1) * aggregatedPerPage;
    const end = Math.min(start + aggregatedPerPage, totalQuestions);

    // Update pagination buttons and count
    if (aggregatedCount) aggregatedCount.textContent = `Showing ${start + 1}-${end} of ${totalQuestions} questions`;
    if (firstBtn) firstBtn.disabled = aggregatedPage === 1;
    if (prevBtn) prevBtn.disabled = aggregatedPage === 1;
    if (nextBtn) nextBtn.disabled = aggregatedPage === totalPages;
    if (lastBtn) lastBtn.disabled = aggregatedPage === totalPages;

    // Render paginated questions
    let html = '';

    for (let i = start; i < end; i++) {
        const [question, answers] = questions[i];
        const total = Object.values(answers).reduce((sum, count) => sum + count, 0);

        // Skip if no valid answers
        if (total === 0) continue;

        // Format the question text with line breaks
        const formattedQuestion = formatForDisplay(question);

        // Sort answers by frequency (descending)
        const sortedAnswers = Object.entries(answers)
            .filter(([answer, count]) => answer.trim() !== '' && count > 0)
            .sort(([, a], [, b]) => b - a);

        html += `
            <div class="aggregated-question mb-4">
                <h6 class="mb-2">${formattedQuestion}</h6>
                <div class="progress-stack">
        `;

        if (sortedAnswers.length === 0) {
            html += '<p class="text-muted small">No valid answers</p>';
        } else {
            sortedAnswers.forEach(([answer, count]) => {
                const percentage = total > 0 ? ((count / total) * 100).toFixed(1) : 0;

                // Format answer text if it contains HTML
                const formattedAnswer = formatForDisplay(answer);
                const plainAnswer = htmlToFormattedText(answer);
                const truncatedPlain = truncateText(plainAnswer, 50);

                html += `
                    <div class="progress-item mb-2">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="answer-text" title="${plainAnswer.replace(/"/g, '&quot;')}">
                                ${truncatedPlain}
                            </span>
                            <span class="answer-count">${count} (${percentage}%)</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar" 
                                 role="progressbar" 
                                 style="width: ${percentage}%"
                                 aria-valuenow="${percentage}" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100"></div>
                        </div>
                    </div>
                `;
            });
        }

        html += `
                </div>
                <small class="text-muted">Total responses: ${total}</small>
            </div>
        `;
    }

    container.innerHTML = html || '<div class="text-center py-4"><p class="text-muted">No aggregated data available</p></div>';

    // Render pagination numbers
    renderAggregatedPagination(totalPages);
}

function renderAggregatedPagination(totalPages) {
    const container = document.getElementById('aggregatedPagination');
    if (!container || totalPages <= 1) {
        container.innerHTML = '<button class="btn btn-sm btn-primary">1</button>';
        return;
    }

    let html = '';
    const maxVisible = 5;
    let startPage = Math.max(2, aggregatedPage - 1);
    let endPage = Math.min(totalPages - 1, startPage + maxVisible - 1);

    // Adjust if we're at the end
    if (endPage - startPage + 1 < maxVisible) {
        startPage = Math.max(2, endPage - maxVisible + 1);
    }

    // Always show page 1
    html += `<button class="btn btn-sm ${1 === aggregatedPage ? 'btn-primary' : 'btn-outline-secondary'}" 
            onclick="aggregatedPage = 1; renderAggregatedAnswers(currentAggregatedData);">
            1
        </button>`;

    // Add ellipsis after page 1 if there's a gap
    if (startPage > 2) {
        html += '<span class="mx-1">...</span>';
    }

    // Middle pages (2, 3, 4, etc.)
    for (let i = startPage; i <= endPage; i++) {
        html += `<button class="btn btn-sm ${i === aggregatedPage ? 'btn-primary' : 'btn-outline-secondary'} mx-1" 
                onclick="aggregatedPage = ${i}; renderAggregatedAnswers(currentAggregatedData);">
                ${i}
            </button>`;
    }

    // Add ellipsis before last page if there's a gap
    if (endPage < totalPages - 1) {
        html += '<span class="mx-1">...</span>';
    }

    // Always show last page if it's different from page 1
    if (totalPages > 1) {
        html += `<button class="btn btn-sm ${totalPages === aggregatedPage ? 'btn-primary' : 'btn-outline-secondary'}" 
                onclick="aggregatedPage = ${totalPages}; renderAggregatedAnswers(currentAggregatedData);">
                ${totalPages}
            </button>`;
    }

    container.innerHTML = html;
}

function renderIndividualPagination(totalPages) {
    const container = document.getElementById('responsePagination');
    if (!container || totalPages <= 1) {
        container.innerHTML = '<button class="btn btn-sm btn-primary">1</button>';
        return;
    }

    let html = '';
    const maxVisible = 5;
    let startPage = Math.max(2, individualPage - 1);
    let endPage = Math.min(totalPages - 1, startPage + maxVisible - 1);

    // Adjust if we're at the end
    if (endPage - startPage + 1 < maxVisible) {
        startPage = Math.max(2, endPage - maxVisible + 1);
    }

    // Always show page 1
    html += `<button class="btn btn-sm ${1 === individualPage ? 'btn-primary' : 'btn-outline-secondary'}" 
            onclick="individualPage = 1; renderIndividualResponses(currentIndividualData);">
            1
        </button>`;

    // Add ellipsis after page 1 if there's a gap
    if (startPage > 2) {
        html += '<span class="mx-1">...</span>';
    }

    // Middle pages (2, 3, 4, etc.)
    for (let i = startPage; i <= endPage; i++) {
        html += `<button class="btn btn-sm ${i === individualPage ? 'btn-primary' : 'btn-outline-secondary'} mx-1" 
                onclick="individualPage = ${i}; renderIndividualResponses(currentIndividualData);">
                ${i}
            </button>`;
    }

    // Add ellipsis before last page if there's a gap
    if (endPage < totalPages - 1) {
        html += '<span class="mx-1">...</span>';
    }

    // Always show last page if it's different from page 1
    if (totalPages > 1) {
        html += `<button class="btn btn-sm ${totalPages === individualPage ? 'btn-primary' : 'btn-outline-secondary'}" 
                onclick="individualPage = ${totalPages}; renderIndividualResponses(currentIndividualData);">
                ${totalPages}
            </button>`;
    }

    container.innerHTML = html;
}

function searchResponses(query) {
    const responseItems = document.querySelectorAll('#responsesListContainer .response-item');
    const searchTerm = query.toLowerCase().trim();

    responseItems.forEach(item => {
        const text = item.textContent.toLowerCase();
        item.style.display = text.includes(searchTerm) ? 'block' : 'none';
    });
}

function initializeResponseExpansion() {
    document.querySelectorAll('.response-item').forEach(resp => {
        const header = resp.querySelector('.response-header');
        if (header) {
            // Remove existing listeners first
            const newHeader = header.cloneNode(true);
            header.parentNode.replaceChild(newHeader, header);

            newHeader.addEventListener('click', () => {
                const details = resp.querySelector('.response-details');
                const icon = newHeader.querySelector('.response-expand-icon i');

                if (details.style.display === 'none' || details.style.display === '') {
                    details.style.display = 'block';
                    icon.className = 'bi bi-chevron-up';
                    resp.classList.add('expanded');
                } else {
                    details.style.display = 'none';
                    icon.className = 'bi bi-chevron-down';
                    resp.classList.remove('expanded');
                }
            });
        }
    });
}

function renderIndividualResponses(responses) {
    const container = document.getElementById('responsesListContainer');
    const responseCountText = document.getElementById('responseCountText');
    const firstBtn = document.getElementById('firstResponse');
    const prevBtn = document.getElementById('prevResponse');
    const nextBtn = document.getElementById('nextResponse');
    const lastBtn = document.getElementById('lastResponse');

    if (!container) return;

    if (!responses || responses.length === 0) {
        container.innerHTML = '<div class="text-center py-4"><p class="text-muted">No individual responses found for this survey.</p></div>';
        if (responseCountText) responseCountText.textContent = 'Total: 0 responses';
        if (firstBtn) firstBtn.disabled = true;
        if (prevBtn) prevBtn.disabled = true;
        if (nextBtn) nextBtn.disabled = true;
        if (lastBtn) lastBtn.disabled = true;
        renderIndividualPagination(0);
        return;
    }

    // Store the data
    currentIndividualData = responses;

    const totalResponses = responses.length;
    const totalPages = Math.ceil(totalResponses / individualPerPage);

    // Calculate start and end indices
    const start = (individualPage - 1) * individualPerPage;
    const end = Math.min(start + individualPerPage, totalResponses);

    // Update pagination info and buttons
    if (responseCountText) responseCountText.textContent = `Total: ${totalResponses} responses`;
    if (firstBtn) firstBtn.disabled = individualPage === 1;
    if (prevBtn) prevBtn.disabled = individualPage === 1;
    if (nextBtn) nextBtn.disabled = individualPage === totalPages;
    if (lastBtn) lastBtn.disabled = individualPage === totalPages;

    let html = '';

    // Get response number offset for this survey
    const responseNumberOffset = totalResponses - start;

    for (let i = start; i < end; i++) {
        const response = responses[i];
        const responseNumber = responseNumberOffset - (i - start);

        const answersHtml = Object.entries(response.answers)
            .map(([question, answer]) => {
                let answerText = '';
                if (Array.isArray(answer)) {
                    answerText = answer
                        .filter(a => a.trim() !== '' && a !== 'N/A')
                        .map(a => formatForDisplay(a))
                        .join(', ');
                } else {
                    answerText = formatForDisplay(answer);
                }

                if (answerText.trim() === '' || answerText === 'N/A') return '';

                const formattedQuestion = formatForDisplay(question);

                // ONE-LINE FIX: Put colon before closing tags
                const questionWithColon = formattedQuestion.replace(
                    /(<\/strong>|<\/b>|<\/span>|<\/em>|<\/i>|<\/u>)/g,
                    ':$1'
                );

                return `<div class="answer-item mb-2">
            <div>${questionWithColon}</div>
            <div class="ms-3 mt-1">${answerText}</div>
        </div>`;
            })
            .filter(html => html !== '')
            .join('');

        const finalAnswersHtml = answersHtml === ''
            ? '<div class="text-muted small">No answers provided</div>'
            : answersHtml;

        html += `
            <div class="response-box mb-3 response-item">
                <div class="response-header d-flex justify-content-between align-items-center" style="cursor: pointer;">
                    <div>
                        <span class="badge bg-primary">Response ${responseNumber} of ${totalResponses}</span>
                        <span class="text-muted ms-2">
                            <i class="bi bi-calendar-check"></i> ${response.submitted_at}
                        </span>
                    </div>
                    <div class="response-expand-icon"><i class="bi bi-chevron-down"></i></div>
                </div>
                <div class="response-details mt-2" style="display:none;">
                    <hr>
                    <div class="response-content">
                        <strong>Answers:</strong>
                        <div class="clean-answers mt-2">
                            ${finalAnswersHtml}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    container.innerHTML = html || '<div class="text-center py-4"><p class="text-muted">No valid responses to display</p></div>';

    // Initialize expand functionality
    initializeResponseExpansion();

    // Render pagination numbers
    renderIndividualPagination(totalPages);
}

function exportSurveyData(surveyId) {
    if (!surveyId) {
        alert('No survey selected');
        return;
    }

    // Get survey title
    const activeSurvey = document.querySelector('.survey-item.active-survey');
    const surveyTitle = activeSurvey ? activeSurvey.querySelector('.survey-title').textContent : 'Survey_' + surveyId;

    // Simple export - just download the aggregated data as CSV
    const aggregatedView = document.getElementById('aggregatedView');
    if (aggregatedView.style.display !== 'none') {
        // Export aggregated data
        window.location.href = `export_survey.php?survey_id=${surveyId}&type=aggregated`;
    } else {
        // Export individual responses
        window.location.href = `export_survey.php?survey_id=${surveyId}&type=individual`;
    }
}

function showAlert(message, type = 'info') {
    // Check if an alert already exists
    const existingAlert = document.querySelector('.alert.position-fixed');
    if (existingAlert) {
        existingAlert.remove();
    }

    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    document.body.appendChild(alertDiv);

    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

// Helper functions
function escapeHtml(text) {
    if (!text) return '';

    // Convert text to string
    text = String(text);

    // Create temporary element to get plain text
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = text;

    // Get plain text while preserving line breaks from block elements
    let plainText = '';

    function extractTextWithLineBreaks(node) {
        if (node.nodeType === Node.TEXT_NODE) {
            return node.textContent;
        }

        if (node.nodeType === Node.ELEMENT_NODE) {
            const tagName = node.tagName.toLowerCase();
            let result = '';

            // Process child nodes
            for (const child of node.childNodes) {
                result += extractTextWithLineBreaks(child);
            }

            // Add line breaks for block elements
            if (['p', 'div', 'br', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'].includes(tagName)) {
                result += '\n';
            }

            // Add bullet points for list items
            if (tagName === 'li') {
                result = '• ' + result;
            }

            return result;
        }

        return '';
    }

    plainText = extractTextWithLineBreaks(tempDiv);

    // Clean up multiple newlines
    plainText = plainText.replace(/\n{3,}/g, '\n\n').trim();

    return plainText;
}

function htmlToFormattedText(html) {
    if (!html) return '';

    // If it's not HTML, return as-is
    if (!/<[a-z][\s\S]*>/i.test(html)) {
        return html;
    }

    // Create temporary element
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = html;

    // Get text content while preserving some structure
    let text = '';

    // Process child nodes to preserve some structure
    function processNode(node) {
        if (node.nodeType === Node.TEXT_NODE) {
            return node.textContent;
        }

        if (node.nodeType === Node.ELEMENT_NODE) {
            const tagName = node.tagName.toLowerCase();
            let result = '';

            // Process children
            for (const child of node.childNodes) {
                result += processNode(child);
            }

            // Add appropriate spacing
            switch (tagName) {
                case 'p':
                case 'div':
                case 'br':
                    result += '\n';
                    break;
                case 'li':
                    result = '• ' + result + '\n';
                    break;
                case 'h1':
                case 'h2':
                case 'h3':
                case 'h4':
                case 'h5':
                case 'h6':
                    result = result.trim().toUpperCase() + '\n\n';
                    break;
                case 'strong':
                case 'b':
                    result = '**' + result + '**';
                    break;
                case 'em':
                case 'i':
                    result = '*' + result + '*';
                    break;
                case 'u':
                    result = '_' + result + '_';
                    break;
            }

            return result;
        }

        return '';
    }

    text = processNode(tempDiv);

    // Clean up multiple newlines
    text = text.replace(/\n{3,}/g, '\n\n').trim();

    return text;
}

function formatForDisplay(text) {
    if (!text) return '';

    // If the text already contains HTML tags, return it as-is
    // Check if it contains HTML tags (simple check for opening/closing tags)
    if (/<[a-z][\s\S]*>/i.test(text)) {
        return text; // Return HTML as-is
    }

    // Otherwise, escape HTML entities and preserve line breaks
    const escapedText = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    // Convert newlines to <br> tags
    return escapedText.replace(/\n/g, '<br>');
}

function truncateText(text, maxLength) {
    if (!text) return '';

    // Check if it's HTML
    if (/<[a-z][\s\S]*>/i.test(text)) {
        // Create temporary element to get text content
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = text;
        const plainText = tempDiv.textContent || tempDiv.innerText || '';

        if (plainText.length <= maxLength) return text;

        // Truncate plain text
        const truncated = plainText.substring(0, maxLength) + '...';

        // Try to preserve the original HTML structure for the truncated part
        const parser = new DOMParser();
        const doc = parser.parseFromString(text, 'text/html');
        let truncatedHtml = '';
        let currentLength = 0;

        function extractTextFromNode(node, depth = 0) {
            if (currentLength >= maxLength) return '';

            if (node.nodeType === Node.TEXT_NODE) {
                const text = node.textContent;
                const remaining = maxLength - currentLength;
                if (text.length <= remaining) {
                    currentLength += text.length;
                    return text;
                } else {
                    const part = text.substring(0, remaining);
                    currentLength += remaining;
                    return part + '...';
                }
            }

            if (node.nodeType === Node.ELEMENT_NODE) {
                const tagName = node.tagName.toLowerCase();
                let result = `<${tagName}`;

                // Copy attributes (simplified)
                for (const attr of node.attributes) {
                    result += ` ${attr.name}="${attr.value}"`;
                }
                result += '>';

                for (const child of node.childNodes) {
                    result += extractTextFromNode(child, depth + 1);
                    if (currentLength >= maxLength) break;
                }

                result += `</${tagName}>`;
                return result;
            }

            return '';
        }

        truncatedHtml = extractTextFromNode(doc.body);
        return truncatedHtml || truncated;
    }

    // Plain text truncation
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
}

function backToTopButton() {
    const btn = document.getElementById('backToTop');
    if (!btn) return;

    window.onscroll = () => {
        btn.style.display = (document.documentElement.scrollTop > 100) ? 'block' : 'none';
    };
}

function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

function logout() {
    if (confirm('Are you sure you want to logout?')) {
        // Get the base path dynamically
        const pathArray = window.location.pathname.split('/');
        const basePath = pathArray.slice(0, pathArray.indexOf('js')).join('/');
        window.location.href = basePath + '/logout.php';
    }
}

// Make scrollToTop available globally
window.scrollToTop = scrollToTop;