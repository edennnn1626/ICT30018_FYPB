<?php
require_once 'auth.php';
require_once 'db_config.php';
require_login();
require_once 'log_action.php';

// When form submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST["title"] ?? "");
    $questionsJSON = $_POST["questions"] ?? "";
    $expiry = $_POST["expiry"] ?? null;

    if (!$title || !$questionsJSON) {
        die("Missing title or survey questions.");
    }

    // Get allowed courses and graduation dates
    $allowed_courses = !empty($_POST['allowed_courses']) ? $_POST['allowed_courses'] : '';
    $allowed_dates = !empty($_POST['allowed_graduation_dates']) ? $_POST['allowed_graduation_dates'] : '';

    // Determine if alumni verification is required
    $require_verification = (!empty($allowed_courses) || !empty($allowed_dates)) ? 1 : 0;

    // Generate token BEFORE preparing statement
    $token = bin2hex(random_bytes(16));

    // Convert expiry to proper format
    $expiry_sql = !empty($expiry) ? date('Y-m-d H:i:s', strtotime($expiry)) : null;

    // Handle empty strings for database NULL
    $allowed_courses_for_db = (!empty($allowed_courses) && $allowed_courses !== 'none') ? $allowed_courses : null;
    $allowed_dates_for_db = (!empty($allowed_dates) && $allowed_dates !== 'none') ? $allowed_dates : null;

    // Prepare the SQL statement
    $stmt = $conn->prepare("
        INSERT INTO surveys (title, form_json, expiry_date, token, 
        require_alumni_verification, allowed_courses, allowed_graduation_dates, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NULL)
    ");

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    // Bind parameters
    $stmt->bind_param(
        "ssssiss",  // 7 parameters: string, string, string, string, int, string, string
        $title,
        $questionsJSON,
        $expiry_sql,
        $token,
        $require_verification,
        $allowed_courses_for_db,
        $allowed_dates_for_db
    );

    if ($stmt->execute()) {
        $new_survey_id = $conn->insert_id;

        // If alumni verification is required, create entry in alumni_survey_response
        if ($require_verification) {
            // IMPORTANT: Use prepared statement to prevent SQL injection
            $stmt2 = $conn->prepare("
                INSERT INTO alumni_survey_response (survey_id, survey_title, alumni_check) 
                VALUES (?, ?, NULL)
            ");
            $stmt2->bind_param("is", $new_survey_id, $title);
            $stmt2->execute();
            $stmt2->close();
        }

        log_action("Created survey '$title'");
        header("Location: survey_management.php?success=1");
        exit;
    } else {
        echo "Database error: " . $conn->error . "<br>";
        echo "SQL error: " . $stmt->error . "<br>";
        echo "<pre>Debug info:<br>";
        echo "Title: " . htmlspecialchars($title) . "<br>";
        echo "Questions JSON length: " . strlen($questionsJSON) . "<br>";
        echo "Expiry SQL: " . ($expiry_sql ?: 'NULL') . "<br>";
        echo "Token: " . $token . "<br>";
        echo "Require Verification: " . $require_verification . "<br>";
        echo "Allowed Courses: '" . htmlspecialchars($allowed_courses_for_db ?: 'NULL') . "'<br>";
        echo "Allowed Dates: '" . htmlspecialchars($allowed_dates_for_db ?: 'NULL') . "'<br>";
        echo "</pre>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Create Survey</title>

    <!-- UI resources -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/main.css">
    <link rel="stylesheet" href="styles/createsurvey.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">

    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>

<body>

    <!-- Navigation bar -->
    <?php include 'navbar.php'; ?>

    <!-- PAGE WRAPPER -->
    <main class="body-survey-management pb-5">

        <div id="surveycreation">

            <!-- HEADER BOX -->
            <div class="text-box-view">
                <h1 class="text-title-view">Create Survey</h1>
            </div>

            <div class="container mt-4">
                <!-- Form -->
                <form method="POST" @submit="submitSurvey">
                    <!-- Survey title -->
                    <div class="mb-3">
                        <label class="form-label">Survey Title</label>
                        <input type="text" class="form-control" v-model="title" name="title" placeholder="Enter survey title" required>
                    </div>

                    <!-- Survey Description -->
                    <div class="mb-3">
                        <label class="form-label">Survey Description (optional)</label>
                        <div id="descriptionEditor" class="description-editor"></div>
                    </div>

                    <!-- Expiry -->
                    <div class="mb-3">
                        <label class="form-label">Expiry Date (optional)</label>
                        <input type="datetime-local" class="form-control" v-model="expiry">
                        <small class="text-muted">Leave empty for no expiry.</small>
                    </div>

                    <!-- Section Creation Panel -->
                    <div class="section-create-panel shadow-sm p-3 mb-4 rounded">
                        <h5 class="mb-3"><i class="bi bi-layers"></i> Create Section</h5>

                        <div class="row g-3 align-items-center">
                            <div class="col-md-8">
                                <input type="text" v-model="newSectionTitle" class="form-control" placeholder="Enter section title">
                            </div>

                            <div class="col-md-4">
                                <button type="button" class="btn btn-primary w-100" @click="addSection">
                                    <i class="bi bi-plus-circle"></i> {{ sections.length === 0 ? 'Add Section' : 'New Section' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Survey Restriction -->
                    <div v-if="sections.length > 0" class="restriction-settings shadow-sm p-3 mb-4 rounded border">
                        <h5><i class="bi bi-shield-lock me-2"></i>Survey Restrictions (Optional)</h5>
                        <p class="text-muted small mb-3">Restrict this survey to specific courses and graduation dates.</p>

                        <!-- Allowed Courses -->
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-mortarboard me-2"></i>Allowed Courses (optional)</label>
                            <div class="input-group mb-2">
                                <input type="text" class="form-control" placeholder="Search courses..."
                                    v-model="courseSearchTerm" @input="filterCourses">
                                <button class="btn btn-outline-secondary" type="button" @click="clearCourseSearch">
                                    <i class="bi bi-x"></i>
                                </button>
                            </div>
                            <select class="form-control" id="allowedCourses" multiple style="min-height: 120px;"
                                @change="handleRestrictionChange">
                                <option value="none" selected>None (Allow all courses)</option>
                                <?php
                                // Render courses from PHP directly
                                $courses = $conn->query("SELECT id, name FROM courses ORDER BY name");
                                while ($course = $courses->fetch_assoc()) {
                                    echo '<option value="' . htmlspecialchars($course['name']) . '" data-id="' . $course['id'] . '">' .
                                        htmlspecialchars($course['name']) . '</option>';
                                }
                                ?>
                            </select>
                            <small class="text-muted">Hold Ctrl/Cmd to select multiple courses</small>
                        </div>

                        <!-- Allowed Graduation Dates -->
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-calendar-event me-2"></i>Allowed Graduation Dates (optional)</label>
                            <div class="input-group mb-2">
                                <input type="text" class="form-control" placeholder="Search graduation dates..."
                                    v-model="dateSearchTerm" @input="filterDates">
                                <button class="btn btn-outline-secondary" type="button" @click="clearDateSearch">
                                    <i class="bi bi-x"></i>
                                </button>
                            </div>
                            <select class="form-control" id="allowedGraduationDates" multiple style="min-height: 120px;"
                                @change="handleRestrictionChange">
                                <option value="none" selected>None (Allow all graduation dates)</option>
                                <?php
                                // Render dates from PHP directly
                                $dates = $conn->query("SELECT id, date, label FROM graduation_dates ORDER BY date");
                                while ($date = $dates->fetch_assoc()) {
                                    echo '<option value="' . htmlspecialchars($date['date']) . '" data-id="' . $date['id'] . '">' .
                                        htmlspecialchars($date['label'] . ' (' . $date['date'] . ')') . '</option>';
                                }
                                ?>
                            </select>
                            <small class="text-muted">Hold Ctrl/Cmd to select multiple graduation dates</small>
                        </div>

                        <!-- Hidden inputs for form submission -->
                        <input type="hidden" name="allowed_courses" id="allowedCoursesInput">
                        <input type="hidden" name="allowed_graduation_dates" id="allowedGraduationDatesInput">
                    </div>

                    <!-- Question Builder Panel -->
                    <div v-if="sections.length > 0" class="question-builder shadow-sm p-3 mb-4 rounded">

                        <h5 class="mb-3"><i class="bi bi-ui-checks"></i> Add Question</h5>

                        <!-- Question Type -->
                        <label class="form-label">Question Type</label>
                        <select class="form-select mb-3" v-model="newType">
                            <option disabled value="">Select question type</option>
                            <option>Short Answer</option>
                            <option>Paragraph</option>
                            <option>Multiple Choice</option>
                            <option>Checkbox</option>
                            <option>Dropdown</option>
                            <option>Date Picker</option>
                            <option>Linear Scale</option>
                        </select>

                        <!-- Number of Questions -->
                        <label class="form-label" v-if="newType">Number of Questions</label>
                        <select class="form-select mb-3" v-if="newType" v-model.number="initialQuestionCount">
                            <option v-for="n in 10" :key="n" :value="n">{{ n }}</option>
                        </select>

                        <!-- Question Inputs -->
                        <div v-if="newType">
                            <div v-for="(q, index) in newQuestions" :key="index" class="question-box p-3 rounded mb-3">

                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label mb-0">Question {{ index + 1 }}</label>
                                </div>

                                <div class="question-text-editor" :id="'editor-'+index" placeholder="Enter question text"></div>

                                <!-- Required and Match Student in same row -->
                                <div class="d-flex align-items-center gap-3 my-2">
                                    <!-- Required Checkbox -->
                                    <div class="form-check">
                                        <input class="form-check-input"
                                            type="checkbox"
                                            v-model="q.required"
                                            @change="handleNewQuestionRequiredChange(q)"
                                            :disabled="q.matchStudent !== 'none'">
                                        <label class="form-check-label">
                                            Required
                                            <span v-if="q.matchStudent !== 'none'" class="text-muted small">
                                                (required for matching)
                                            </span>
                                        </label>
                                    </div>

                                    <!-- Match Student Database Dropdown - Only for Short Answer -->
                                    <div v-if="q.type === 'Short Answer'" class="d-flex align-items-center gap-2">
                                        <label class="form-label mb-0 small">Match student:</label>
                                        <select class="form-select form-select-sm"
                                            v-model="q.matchStudent"
                                            style="width: 140px;"
                                            @change="handleNewQuestionMatchStudentChange(q)">
                                            <option value="none">None</option>
                                            <option value="name">Name</option>
                                            <option value="student_id">Student ID</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Help text for Match Student -->
                                <div v-if="newType === 'Short Answer' && q.matchStudent !== 'none'" class="mb-2">
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle"></i>
                                        The responses to this question will be matched with {{ q.matchStudent === 'name' ? 'student names' : 'student IDs' }} in the database
                                    </small>
                                </div>

                                <!-- LINEAR SCALE SETTINGS -->
                                <div v-if="isLinearScale(newType)" class="options-box p-2 mt-2 border rounded">

                                    <div class="mt-3">
                                        <label class="form-label">Scale Max</label>
                                        <select class="form-select form-select-sm" v-model.number="newQuestions[index].scaleMax">
                                            <option v-for="n in 9" :key="n" :value="n+1">{{ n+1 }}</option>
                                        </select>
                                    </div>

                                    <label class="form-label">Labels (Optional)</label>
                                    <input type="text" class="form-control mb-1" placeholder="Left label"
                                        v-model="newQuestions[index].scaleLabelLeft">
                                    <input type="text" class="form-control" placeholder="Right label"
                                        v-model="newQuestions[index].scaleLabelRight">
                                </div>

                                <!-- OPTIONS -->
                                <div v-if="usesOptions(newType)" class="options-box p-2 mt-2 rounded">

                                    <div v-for="(opt, i) in q.options" :key="i" class="option-row d-flex align-items-center gap-2 mb-2">
                                        <input type="text"
                                            class="form-control option-input"
                                            v-model="q.options[i]"
                                            placeholder="Option"
                                            :class="{ 'is-invalid': q.optionErrors && q.optionErrors[i] }">

                                        <button class="btn btn-outline-danger btn-sm" @click.prevent="removeOption(q, i)">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>

                                    <button class="btn btn-outline-primary btn-sm mt-2"
                                        @click.prevent="addOption(q)">
                                        <i class="bi bi-plus-circle"></i> Add Option
                                    </button>

                                </div>
                            </div>
                        </div>

                        <button type="button" class="btn btn-success mt-3" @click="addQuestionToSection">
                            <i class="bi bi-plus-square"></i> Add Question
                        </button>
                        <button type="submit" class="btn btn-publish mt-3 ms-1">Publish</button>

                    </div>
                </form>

                <!-- PREVIEW SECTION -->
                <hr class="my-4">

                <div v-if="sections.length">
                    <h5><i class="bi bi-eye me-2"></i><b>Survey Preview:</b></h5>

                    <div class="survey-preview p-3 border rounded">

                        <h4><b>{{ title || "Untitled Survey" }}</b></h4>

                        <!-- Survey Description -->
                        <div v-if="description" class="survey-description-preview ms-3 rounded">
                            <div v-html="description"></div>
                        </div>

                        <!-- CURRENT SECTION -->
                        <div v-if="sections[currentSectionIndex]">

                            <!-- Section Title -->
                            <div class="d-flex align-items-center gap-2 mt-3 mb-3">
                                <i class="bi bi-folder2-open text-primary me-2"></i>

                                <!-- If editing this section title -->
                                <input v-if="editingSectionIndex === currentSectionIndex" type="text" v-model="sections[currentSectionIndex].title" class="form-control form-control-sm w-auto">

                                <!-- Normal title display -->
                                <h5 v-else class="text-primary fw-bold mb-0">{{ sections[currentSectionIndex].title }}</h5>

                                <!-- Edit / Save buttons -->
                                <button type="button" class="btn btn-outline-secondary btn-sm" @click="toggleEditSectionTitle">
                                    <i :class="editingSectionIndex === currentSectionIndex ? 'bi bi-check-lg' : 'bi bi-pencil'"></i>
                                </button>
                            </div>

                            <!-- QUESTIONS -->
                            <template v-if="sections[currentSectionIndex].questions.length > 0">
                                <div v-for="(question, index) in sections[currentSectionIndex].questions"
                                    :key="index"
                                    class="question-item mb-3 p-3 border rounded position-relative"
                                    style="cursor: default;"
                                    @click.self="editQuestion(index)">

                                    <!-- Action buttons -->
                                    <div class="btn-group position-absolute top-0 end-0 m-2" role="group">

                                        <button v-if="index > 0" type="button" class="btn btn-outline-secondary btn-sm"
                                            @click.stop="moveQuestionUp(index)">
                                            <i class="bi bi-arrow-up"></i>
                                        </button>

                                        <button v-if="index < sections[currentSectionIndex].questions.length - 1" type="button"
                                            class="btn btn-outline-secondary btn-sm"
                                            @click.stop="moveQuestionDown(index)">
                                            <i class="bi bi-arrow-down"></i>
                                        </button>

                                        <button type="button" class="btn btn-danger btn-sm" @click.stop="deleteQuestion(index)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>

                                    <!-- Dropdown button for changing sections -->
                                    <div class="mt-2">
                                        <label class="form-label small mb-1">Move to Section: </label>
                                        <select class="form-select form-select-sm move-section-dropdown" v-model="question.sectionMoveTarget" @change="moveQuestionToSection(index)">
                                            <option v-for="(sec, secIndex) in sections" :key="secIndex" :value="secIndex">
                                                {{ sec.title }}
                                            </option>
                                        </select>
                                    </div>

                                    <!-- Click area to start inline edit -->
                                    <div @click="editQuestion(index)">

                                        <!-- Inline editor (when editing this question) -->
                                        <div v-if="editingQuestionIndex === index" class="inline-editor" :key="'editor-preview-' + index">
                                            <div :id="'editor-preview-' + index" class="mb-2"></div>

                                            <!-- Question Type Selector -->
                                            <div class="mb-2 d-flex align-items-center gap-2" @click.stop>
                                                <label class="form-label mb-0">Question Type:</label>
                                                <select class="form-select form-select-sm w-auto"
                                                    v-model="editingQuestion.type"
                                                    @change="handleTypeChange">
                                                    <option>Short Answer</option>
                                                    <option>Paragraph</option>
                                                    <option>Multiple Choice</option>
                                                    <option>Checkbox</option>
                                                    <option>Dropdown</option>
                                                    <option>Date Picker</option>
                                                    <option>Linear Scale</option>
                                                </select>
                                            </div>

                                            <!-- Linear Scale Editor (Inline Edit) -->
                                            <div v-if="editingQuestion.type === 'Linear Scale'" class="options-box mt-2 p-2 border rounded" @click.stop>

                                                <label class="form-label">Scale Range</label>
                                                <select class="form-select form-select-sm w-auto" v-model.number="editingQuestion.scaleMax">
                                                    <option v-for="n in 9" :key="n" :value="n+1">{{ n+1 }}</option>
                                                </select>

                                                <label class="form-label">Labels (Optional)</label>

                                                <input type="text" class="form-control form-control-sm mb-1"
                                                    placeholder="Left label" v-model="editingQuestion.scaleLabelLeft" @click.stop>

                                                <input type="text" class="form-control form-control-sm"
                                                    placeholder="Right label" v-model="editingQuestion.scaleLabelRight" @click.stop>
                                            </div>

                                            <!-- Options editor -->
                                            <div v-if="usesOptions(editingQuestion.type)" class="mt-2" @click.stop>
                                                <div v-for="(opt, i) in editingQuestion.options" :key="i" class="d-flex gap-2 mb-1">
                                                    <input type="text"
                                                        v-model="editingQuestion.options[i]"
                                                        class="form-control"
                                                        :class="{ 'option-error': editingQuestion.optionErrors && editingQuestion.optionErrors[i] }"
                                                        @click.stop>
                                                    <button class="btn btn-danger btn-sm" @click.stop="removeInlineOption(i)">×</button>
                                                </div>
                                                <button class="btn btn-outline-primary btn-sm mt-2" @click.stop="addInlineOption">
                                                    <i class="bi bi-plus-circle"></i> Add Option
                                                </button>
                                            </div>

                                            <div class="d-flex align-items-center gap-3 my-2" @click.stop>
                                                <!-- Required Checkbox -->
                                                <div class="form-check">
                                                    <input class="form-check-input"
                                                        type="checkbox"
                                                        v-model="editingQuestion.required"
                                                        :id="'req-'+index"
                                                        @click.stop
                                                        @change="handleRequiredChange"
                                                        :disabled="editingQuestion.matchStudent !== 'none'">
                                                    <label class="form-label mb-0 small" :for="'req-'+index">
                                                        Required
                                                        <span v-if="editingQuestion.matchStudent !== 'none'" class="text-muted small">
                                                            (required for matching)
                                                        </span>
                                                    </label>
                                                </div>

                                                <div v-if="editingQuestion.type === 'Short Answer'" class="d-flex align-items-center gap-2">
                                                    <label class="form-label mb-0 small">Match student:</label>
                                                    <select class="form-select form-select-sm"
                                                        v-model="editingQuestion.matchStudent"
                                                        style="width: 140px;"
                                                        @click.stop
                                                        @change="handleMatchStudentChange">
                                                        <option value="none">None</option>
                                                        <option value="name">Name</option>
                                                        <option value="student_id">Student ID</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div v-if="editingQuestion.type === 'Short Answer' && editingQuestion.matchStudent !== 'none'" class="mb-2">
                                                <small class="text-muted">
                                                    <i class="bi bi-info-circle"></i>
                                                    The responses to this question will be matched with {{ editingQuestion.matchStudent === 'name' ? 'student names' : 'student IDs' }} in the database
                                                </small>
                                            </div>

                                            <!-- Save/Cancel buttons -->
                                            <div class="mt-3" @click.stop>
                                                <button type="button" class="btn btn-success btn-sm me-2" @click="saveEditingQuestion">
                                                    <i class="bi bi-check-lg"></i> Save
                                                </button>
                                                <button type="button" class="btn btn-secondary btn-sm" @click="cancelEditingQuestion">
                                                    <i class="bi bi-x-lg"></i> Cancel
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Normal preview -->
                                        <template v-else>
                                            <label class="form-label d-inline-flex align-items-start gap-1 mb-2">
                                                <span v-if="question.required" class="text-danger fw-bold mt-1">*</span>
                                                <span v-html="question.text"></span>
                                                <!-- Show match student badge -->
                                                <span v-if="question.type === 'Short Answer' && question.matchStudent !== 'none'"
                                                    class="badge bg-info ms-2 small" title="Matches with {{ question.matchStudent === 'name' ? 'student names' : 'student IDs' }} in database">
                                                    <i class="bi bi-database me-1"></i>
                                                    {{ question.matchStudent === 'name' ? 'Name' : 'ID' }}
                                                </span>
                                            </label>

                                            <input v-if="question.type === 'Short Answer'" type="text" class="form-control mb-2" readonly>
                                            <textarea v-if="question.type === 'Paragraph'" class="form-control mb-2" rows="3" readonly></textarea>

                                            <div v-if="question.type === 'Multiple Choice'" class="mb-2">
                                                <div v-for="(opt, optIndex) in question.options" :key="optIndex" class="form-check">
                                                    <input class="form-check-input" type="radio" :name="'question'+index">
                                                    <label class="form-check-label">{{ opt }}</label>
                                                </div>
                                            </div>

                                            <div v-if="question.type === 'Checkbox'" class="mb-2">
                                                <div v-for="(opt, optIndex) in question.options" :key="optIndex" class="form-check">
                                                    <input class="form-check-input" type="checkbox">
                                                    <label class="form-check-label">{{ opt }}</label>
                                                </div>
                                            </div>

                                            <select v-if="question.type === 'Dropdown'" class="form-select mb-2" disabled>
                                                <option disabled selected>Select an option</option>
                                                <option v-for="(opt, optIndex) in question.options" :key="optIndex">{{ opt }}</option>
                                            </select>

                                            <div v-if="question.type === 'Linear Scale'" class="mb-2 linear-scale-container">
                                                <div class="scale-labels">
                                                    <span class="scale-label-left">{{ question.scaleLabelLeft }}</span>
                                                    <span class="scale-label-right">{{ question.scaleLabelRight }}</span>
                                                </div>
                                                <div class="linear-scale-display"
                                                    :style="{ '--scale-count': (question.scaleMax - question.scaleMin + 1) }">
                                                    <div v-for="n in ((question.scaleMax || 5) - (question.scaleMin || 1) + 1)"
                                                        class="scale-item">
                                                        <input type="radio" disabled>
                                                        <div class="scale-number">{{ question.scaleMin + n - 1 }}</div>
                                                    </div>
                                                </div>

                                            </div>

                                            <input v-if="question.type === 'Date Picker'" type="date" class="form-control mb-2" disabled>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <!-- Empty section message -->
                            <div v-else class="text-muted fst-italic p-2">
                                No questions added to this section yet.
                            </div>

                            <!-- Navigation buttons -->
                            <div class="d-flex justify-content-between mt-3">
                                <button type="button" class="btn btn-secondary" @click="goToPreviousSection" :disabled="currentSectionIndex === 0">Previous</button>
                                <button type="button" class="btn btn-primary" @click="goToNextSection" :disabled="currentSectionIndex === sections.length - 1">Next</button>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- No questions -->
                <div v-else class="text-muted fst-italic p-3">No Section added yet.</div>
            </div>
        </div>
    </main>

    <!-- FOOTER -->
    <footer class="site-footer fixed-bottom py-3 mt-4 mt-auto">
        <div class="container text-center">
            <p class="mb-0">ICT30017 ICT PROJECT A | <span class="team-name">TEAM Deadline Dominators</span></p>
        </div>
    </footer>

    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/vue@3"></script>
    <script src="js/main.js"></script>
    <script src="js/createsurvey.js"></script>
    <script>
        // Pass courses and dates data from PHP to Vue
        const coursesFromPHP = <?php
                                $courses = $conn->query("SELECT id, name FROM courses ORDER BY name");
                                $coursesData = [];
                                while ($course = $courses->fetch_assoc()) {
                                    $coursesData[] = [
                                        'id' => $course['id'],
                                        'name' => $course['name']
                                    ];
                                }
                                echo json_encode($coursesData);
                                ?>;

        const datesFromPHP = <?php
                                $dates = $conn->query("SELECT id, date, label FROM graduation_dates ORDER BY date");
                                $datesData = [];
                                while ($date = $dates->fetch_assoc()) {
                                    $datesData[] = [
                                        'id' => $date['id'],
                                        'date' => $date['date'],
                                        'label' => $date['label']
                                    ];
                                }
                                echo json_encode($datesData);
                                ?>;

        // Initialize Vue after DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // This will be available to Vue
            window.coursesData = coursesFromPHP;
            window.datesData = datesFromPHP;
        });
    </script>
</body>

</html>