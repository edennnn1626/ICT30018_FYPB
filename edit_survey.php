<?php
require_once 'auth.php';
require_once 'db_config.php';
require_login("admin");

// Validate ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid survey ID.");
}
$survey_id = intval($_GET['id']);

// Fetch survey
$survey_res = $conn->query("SELECT * FROM surveys WHERE id = $survey_id");
$survey = $survey_res->fetch_assoc();
if (!$survey) {
    die("Survey not found.");
}

// Decode form_json into array
$formData = json_decode($survey['form_json'], true);

// Extract description from formData
$description = $formData['description'] ?? '';

// Extract allowed courses and dates
$allowed_courses = $survey['allowed_courses'] ?? '';
$allowed_dates = $survey['allowed_graduation_dates'] ?? '';

// Ensure sections exist
if (isset($formData['sections']) && is_array($formData['sections'])) {
    $sections = $formData['sections'];
}

// Normalize question types inside sections
foreach ($sections as &$sec) {
    $sec['title'] = $sec['title'] ?? ($sec['secTitle'] ?? 'Untitled Section');
    $sec['questions'] = $sec['questions'] ?? [];

    foreach ($sec['questions'] as &$q) {
        $t = strtolower(trim($q['type'] ?? 'short'));

        if ($t === 'short answer') $t = 'short';
        elseif ($t === 'paragraph') $t = 'paragraph';
        elseif ($t === 'multiple choice') $t = 'multiple';
        elseif ($t === 'checkbox') $t = 'checkbox';
        elseif ($t === 'dropdown') $t = 'dropdown';
        elseif ($t === 'date picker' || $t === 'date') $t = 'date';
        elseif (strpos($t, 'linear scale') !== false) $t = 'linear_scale';

        $q['type'] = $t;

        if (!isset($q['required'])) $q['required'] = false;
        $q['options'] = $q['options'] ?? [];

        // Linear scale defaults
        if ($t === 'linear_scale') {
            $q['scaleMax'] = $q['scaleMax'] ?? 5;
            $q['labelLeft'] = $q['labelLeft'] ?? ($q['scaleLabelLeft'] ?? '');
            $q['labelRight'] = $q['labelRight'] ?? ($q['scaleLabelRight'] ?? '');
        }
    }
    unset($q);
}
unset($sec);

// Extract expiry date if exists
$expiry_date = $survey['expiry_date'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit Survey</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/main.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="styles/editsurvey.css?v=<?php echo time(); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
</head>

<body class="mb-5">
    <!-- Navigation Bar -->
    <?php include 'navbar.php'; ?>

    <main class="body-survey-management pb-5">
        <div id="zoomContainer">
            <div id="surveyedit">
                <!-- Title -->
                <div class="text-box-view">
                    <h1 class="text-title-view"><i class="bi bi-pencil-square me-2"></i>Edit Survey</h1>
                </div>

                <div class="container mt-4">
                    <form id="surveyForm" method="POST" action="update_survey.php">
                        <input type="hidden" name="id" value="<?php echo $survey['id']; ?>">
                        <input type="hidden" name="form_json" id="questionsData">

                        <!-- Survey Title -->
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-card-text me-2"></i>Survey Title</label>
                            <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($survey['title']); ?>" required>
                        </div>

                        <!-- Survey Description -->
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-text-paragraph me-2"></i>Survey Description (optional)</label>
                            <div id="descriptionEditor" class="description-editor"></div>
                        </div>

                        <!-- Survey Expiry -->
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-calendar me-2"></i>Expiry Date (optional)</label>
                            <input type="datetime-local" class="form-control flatpickr-input date-picker-preview" name="expiry"
                                value="<?= $expiry_date ? date('Y-m-d\TH:i', strtotime($expiry_date)) : '' ?>">
                            <small class="text-muted">Leave empty for no expiry.</small>
                        </div>

                        <!-- Allowed Courses -->
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-mortarboard me-2"></i>Allowed Courses (optional)</label>
                            <select class="form-control" id="allowedCourses" multiple>
                                <option value="none" <?= empty($survey['allowed_courses']) ? 'selected' : '' ?>>None</option>
                                <?php
                                $courses = $conn->query("SELECT * FROM courses ORDER BY name");
                                while ($course = $courses->fetch_assoc()) {
                                    $selected = !empty($survey['allowed_courses']) && str_contains($survey['allowed_courses'], $course['name']) ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($course['name']) . '" ' . $selected . '>' .
                                        htmlspecialchars($course['name']) . '</option>';
                                }
                                ?>
                            </select>
                            <small class="text-muted">Hold Ctrl/Cmd to select multiple courses</small>
                        </div>

                        <!-- Allowed Graduation Dates -->
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-calendar-event me-2"></i>Allowed Graduation Dates (optional)</label>
                            <select class="form-control" id="allowedGraduationDates" multiple>
                                <option value="none" <?= empty($survey['allowed_graduation_dates']) ? 'selected' : '' ?>>None</option>
                                <?php
                                $dates = $conn->query("SELECT * FROM graduation_dates ORDER BY date");
                                while ($date = $dates->fetch_assoc()) {
                                    $selected = !empty($survey['allowed_graduation_dates']) && str_contains($survey['allowed_graduation_dates'], $date['date']) ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($date['date']) . '" ' . $selected . '>' .
                                        htmlspecialchars($date['label'] . ' (' . $date['date'] . ')') . '</option>';
                                }
                                ?>
                            </select>
                            <small class="text-muted">Hold Ctrl/Cmd to select multiple graduation dates</small>
                        </div>

                        <input type="hidden" name="allowed_courses" id="allowedCoursesInput" value="<?= htmlspecialchars($survey['allowed_courses'] ?? '') ?>">
                        <input type="hidden" name="allowed_graduation_dates" id="allowedGraduationDatesInput" value="<?= htmlspecialchars($survey['allowed_graduation_dates'] ?? '') ?>">

                        <!-- Add Question Form -->
                        <div class="add-question-form">
                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-ui-checks me-2"></i>Question Type</label>
                                <select class="form-select" id="questionType">
                                    <option disabled value="">Select question type</option>
                                    <option value="short">Short Answer</option>
                                    <option value="paragraph">Paragraph</option>
                                    <option value="multiple">Multiple Choice</option>
                                    <option value="checkbox">Checkbox</option>
                                    <option value="dropdown">Dropdown</option>
                                    <option value="date">Date Picker</option>
                                    <option value="linear_scale">Linear Scale</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-chat-left-text me-2"></i>Question Text</label>
                                <div id="questionTextEditor" class="question-text-editor"></div>
                            </div>

                            <div id="optionsContainer" class="mb-3" style="display:none;">
                                <label class="form-label"><i class="bi bi-list-ul me-2"></i>Options</label>
                                <div class="options-content">
                                    <!-- Individual options input field will be inserted here by JavaScript -->
                                </div>
                            </div>

                            <div class="d-flex align-items-center gap-3 mb-3">
                                <!-- Required Checkbox -->
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="questionRequired">
                                    <label class="form-check-label">Required</label>
                                </div>

                                <!-- Match Student Database Dropdown - Only for Short Answer -->
                                <div id="matchStudentContainer" class="d-flex align-items-center gap-2 d-none">
                                    <label class="form-label mb-0 small">Match student:</label>
                                    <select class="form-select form-select-sm" id="questionMatchStudent" style="width: 140px;">
                                        <option value="none">None</option>
                                        <option value="name">Name</option>
                                        <option value="student_id">Student ID</option>
                                    </select>
                                </div>
                            </div>
                            <!-- Help text for Match Student -->
                            <div id="matchStudentHelp" class="mb-3" style="display: none;">
                                <small class="text-muted">
                                    <i class="bi bi-info-circle"></i>
                                    The responses to this question will be matched with <span id="matchStudentType">student names</span> in the database
                                </small>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex gap-3 mt-4">
                            <button type="button" class="btn btn-add" id="addQuestionBtn">
                                <i class="bi bi-plus-circle"></i> Add Question
                            </button>
                            <button type="button" class="btn btn-info" id="addSectionBtn">
                                <i class="bi bi-node-plus"></i> Add Section
                            </button>
                            <button type="submit" class="btn btn-publish" id="updateSurveyBtn">
                                <i class="bi bi-check-circle"></i> Update Survey
                            </button>
                            <button type="button" class="btn btn-warning" id="arrangeViewBtn">
                                <i class="bi bi-arrows-move"></i> Arrange View
                            </button>
                            <a class="btn btn-secondary" href="survey_management.php">
                                <i class="bi bi-arrow-left-circle"></i> Back
                            </a>
                        </div>

                        <hr class="my-5">

                        <!-- Preview Section -->
                        <div id="preview">
                            <h5><i class="bi bi-eye me-2"></i>Survey Preview:</h5>
                            <div class="survey-preview">
                                <h4><i class="bi bi-clipboard-check me-2"></i><?php echo htmlspecialchars($survey['title']); ?></h4>
                                <!-- Description Preview -->
                                <div id="descriptionPreview" class="survey-description-preview mb-4 p-3 bg-light rounded">
                                    <?php if (!empty($description)): ?>
                                        <?php echo $description; ?>
                                    <?php else: ?>
                                        <em class="text-muted">No description provided</em>
                                    <?php endif; ?>
                                </div>
                                <div id="previewQuestions"></div>
                            </div>
                        </div>

                        <!-- Section Navigation -->
                        <div class="d-flex justify-content-center align-items-center gap-3 my-4" id="sectionNavButtons">
                            <button type="button" class="btn btn-outline-primary" id="prevSectionBtn">
                                <i class="bi bi-chevron-left me-1"></i>Previous
                            </button>

                            <div class="section-label-container d-flex flex-column align-items-center">
                                <small class="text-muted mb-1">Current Section</small>
                                <span id="currentSectionLabel" class="fw-bold text-primary fs-6"></span>
                            </div>

                            <button type="button" class="btn btn-outline-primary" id="nextSectionBtn">
                                Next<i class="bi bi-chevron-right ms-1"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Back to Top Button -->
        <button id="backToTopBtn" title="Go to top">
            <i class="bi bi-arrow-up-circle-fill"></i>
        </button>
    </main>

    <!-- Footer -->
    <footer class="site-footer fixed-bottom py-3 mt-auto">
        <div class="container text-center">
            <p class="mb-0">ICT30017 ICT PROJECT A | <span class="team-name">TEAM Deadline Dominators</span></p>
        </div>
    </footer>

    <!-- Data to JS -->
    <script>
        window.surveySections = <?= json_encode($sections) ?>;
        window.surveyDescription = <?= json_encode($description) ?>;
    </script>

    <!-- JS Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script src="js/editsurvey.js?v=<?php echo time(); ?>"></script>
</body>

</html>