<?php
require_once 'db_config.php';

// Get token from URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    die("Invalid survey link.");
}

// Query survey by token
$stmt = $conn->prepare("SELECT id, title, form_json, expiry_date FROM surveys WHERE token = ? LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Survey not found.");
}

$survey = $result->fetch_assoc();
$survey_id = $survey['id'];

$form_data = json_decode($survey['form_json'], true);
$title = $form_data['title'] ?? 'Survey';
$description = $form_data['description'] ?? '';
$sections = $form_data['sections'] ?? [];

function updateAlumniCheck($conn, $survey_id, $matched_by, $matched_value, $alumni_id)
{
    // Get alumni info
    $alumni_info = $conn->query("SELECT name, student_id FROM alumni WHERE id = $alumni_id");
    if ($alumni_info->num_rows === 0) {
        return false;
    }

    $alumni = $alumni_info->fetch_assoc();
    $alumni_name = trim($alumni['name'] ?? '');
    $student_id = trim($alumni['student_id'] ?? '');

    // Create the combined key: "Name / Student ID"
    $combined_key = $alumni_name . " / " . $student_id;

    // Check if record exists for this survey
    $check_sql = "SELECT id, alumni_check FROM alumni_survey_response WHERE survey_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $survey_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        // Update existing record
        $row = $check_result->fetch_assoc();
        $current_data = [];

        if (!empty($row['alumni_check']) && $row['alumni_check'] != 'null') {
            $current_data = json_decode($row['alumni_check'], true);
            if (!is_array($current_data)) {
                $current_data = [];
            }
        }

        // Add alumni with combined key
        $current_data[$combined_key] = 1;

        // Update the record
        $update_sql = "UPDATE alumni_survey_response SET alumni_check = ? WHERE survey_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $json_data = json_encode($current_data, JSON_UNESCAPED_SLASHES);
        $update_stmt->bind_param("si", $json_data, $survey_id);
        return $update_stmt->execute();
    } else {
        // Create new record
        $current_data = [];

        // Get survey title
        $survey_title_result = $conn->query("SELECT title FROM surveys WHERE id = $survey_id");
        $survey_title = $survey_title_result->fetch_assoc()['title'] ?? '';

        // Add alumni with combined key
        $current_data[$combined_key] = 1;

        // Insert new record
        $insert_sql = "INSERT INTO alumni_survey_response (survey_id, survey_title, alumni_check) VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $json_data = json_encode($current_data);
        $insert_stmt->bind_param("iss", $survey_id, $survey_title, $json_data);
        return $insert_stmt->execute();
    }
}

// Handle response submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $survey_id > 0) {
    // Collect answers from POST
    $answers = [];
    $validation_errors = [];
    $matched_alumni_id = null;
    $matched_by = null;
    $matched_value = null;

    foreach ($sections as $section_index => $section) {
        $section_questions = $section['questions'] ?? [];
        foreach ($section_questions as $question_index => $q) {
            $question_text = $q['text'] ?? '';
            $question_type = $q['type'] ?? '';
            $is_required = $q['required'] ?? false;
            $match_student = $q['matchStudent'] ?? 'none';
            $key = 'q_' . $section_index . '_' . $question_index;

            // Get submitted value
            $submitted_value = '';
            if ($question_type === 'checkbox' || $question_type === 'Checkbox') {
                $submitted_value = $_POST[$key] ?? [];
                // Sanitize each checkbox value
                $submitted_value = array_map(function ($value) {
                    return htmlspecialchars(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }, $submitted_value);
            } else {
                $submitted_value = $_POST[$key] ?? '';
                // Sanitize single values
                $submitted_value = htmlspecialchars(trim($submitted_value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            // Handle empty non-required fields
            if (!$is_required && empty($submitted_value)) {
                if ($question_type === 'checkbox' || $question_type === 'Checkbox') {
                    $submitted_value = ['N/A'];
                } else {
                    $submitted_value = 'N/A';
                }
            }

            // Validate required fields
            if ($is_required) {
                if (
                    empty($submitted_value) ||
                    (is_array($submitted_value) && empty(array_filter($submitted_value))) ||
                    (is_string($submitted_value) && trim($submitted_value) === '')
                ) {
                    $validation_errors[] = "Question '{$question_text}' is required.";
                    continue;
                }
            }

            // Validate matchStudent fields
            if ($match_student !== 'none' && !empty($submitted_value) && $submitted_value !== 'N/A') {
                $field_to_match = $match_student === 'name' ? 'name' : 'student_id';
                // Use original POST value for database lookup
                $original_value = $_POST[$key] ?? '';
                if (is_array($original_value)) {
                    $original_value = $original_value[0] ?? '';
                }
                $safe_value = $conn->real_escape_string(trim($original_value));

                // Get alumni ID
                $alumni_check = $conn->query("SELECT id FROM alumni WHERE $field_to_match = '$safe_value' LIMIT 1");

                if ($alumni_check->num_rows == 0) {
                    $field_name = $match_student === 'name' ? 'name' : 'student ID';
                    $validation_errors[] = "The {$field_name} '{$original_value}' was not found in our records. Please check your entry.";
                    continue;
                } else {
                    // Store alumni matching info
                    $alumni_data = $alumni_check->fetch_assoc();
                    $matched_alumni_id = $alumni_data['id'];
                    $matched_by = $match_student;
                    $matched_value = $original_value;
                }
            }

            // Store the sanitized answer
            $answers[$question_text] = $submitted_value;
        }
    }

    // If there are validation errors, show them and stop
    if (!empty($validation_errors)) {
        // Clean each error message for JavaScript
        $clean_errors = array_map(function ($error) {
            // Remove HTML tags
            $error = strip_tags($error);
            // Escape quotes
            $error = str_replace("'", "\\'", $error);
            $error = str_replace('"', '\\"', $error);
            // Escape newlines
            $error = str_replace(["\r", "\n"], ['', '\\n'], $error);
            return $error;
        }, $validation_errors);

        // Create JavaScript-safe error message
        $error_message = implode("\\n", $clean_errors);

        // Output JavaScript alert
        echo "<script>
        alert('Following are not contained in our records:\\n{$error_message}');
        window.history.back();
    </script>";
        exit;
    }

    // Insert sanitized response into survey_responses table
    $answers_json = $conn->real_escape_string(json_encode($answers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP));
    $sql = "INSERT INTO survey_responses (survey_id, answers) VALUES ($survey_id, '$answers_json')";

    if ($conn->query($sql)) {
        // If alumni matched, update alumni_check
        if ($matched_alumni_id && $matched_by && $matched_value) {
            updateAlumniCheck($conn, $survey_id, $matched_by, $matched_value, $matched_alumni_id);
        }

        echo "<script>alert('Thank you for your response!'); window.location.href = 'public_survey.php?token=$token';</script>";
        exit;
    } else {
        // Sanitize error message for display
        $error_msg = htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8');
        echo "Error saving response: " . $error_msg;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Survey - <?= htmlspecialchars($title ?? 'Survey') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/main.css">
    <link rel="stylesheet" href="styles/viewsurvey.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <meta name="viewport" content="width=device-width,initial-scale=1">
</head>

<body>
    <div class="topnav">
        <div class="nav-left">
            <a href="dashboard.php" class="text-white text-decoration-none">Swinburne Alumni Survey</a>
        </div>
        <div class="nav-center"></div>
        <div class="nav-right"></div>
    </div>

    <main class="body-view-survey">
        <div class="form-wrapper"
            data-expiry="<?= !empty($survey['expiry_date']) ? htmlspecialchars($survey['expiry_date']) : '' ?>">
            <?php if (!empty($sections)): ?>
                <h1 class="text-title-view"><?= htmlspecialchars($title) ?></h1>

                <?php if (!empty($description)): ?>
                    <div class="survey-description mb-4 p-3 bg-light rounded">
                        <?= $description ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="surveyForm">
                    <?php
                    $global_question_counter = 0;
                    foreach ($sections as $section_index => $section):
                        $section_title = $section['secTitle'] ?? $section['title'] ?? '';
                        $section_questions = $section['questions'] ?? [];
                        $is_active = $section_index === 0 ? 'active' : '';
                    ?>
                        <div class="survey-section <?= $is_active ?>" data-section-index="<?= $section_index ?>">
                            <?php if (!empty($section_title)): ?>
                                <div class="section-header mb-4">
                                    <h3 class="h4 mb-0"><?= htmlspecialchars($section_title) ?></h3>
                                </div>
                            <?php endif; ?>

                            <?php foreach ($section_questions as $question_index => $q):
                                $global_question_counter++;
                                $question_text = $q['text'] ?? '';
                                $question_type = $q['type'] ?? 'short';
                                $is_required = $q['required'] ?? false;
                                $options = $q['options'] ?? [];
                                $match_student = $q['matchStudent'] ?? 'none';
                                $key = 'q_' . $section_index . '_' . $question_index;
                            ?>
                                <div class="vs-container question-block mb-4 <?= $is_required ? 'required-field' : '' ?>">
                                    <div class="question-header d-flex align-items-start mb-3">
                                        <div class="question-number fw-bold me-2" style="min-width: 25px;">
                                            <?= $global_question_counter ?>.
                                        </div>
                                        <div class="question-content flex-grow-1">
                                            <label class="form-label fw-bold mb-0 d-flex align-items-start">
                                                <?php if ($is_required): ?>
                                                    <span class="required-asterisk text-danger fw-bold me-1" style="color: #dc3545 !important;">*</span>
                                                <?php endif; ?>
                                                <span class="question-text"><?= $question_text ?></span>
                                            </label>

                                            <!-- Options container -->
                                            <div class="options-container mt-2">
                                                <?php
                                                switch (strtolower($question_type)):
                                                    case 'short':
                                                    case 'short answer': ?>
                                                        <input type="text" class="form-control" name="<?= $key ?>" <?= $is_required ? 'required' : '' ?>>
                                                    <?php break;

                                                    case 'paragraph':
                                                    case 'long answer': ?>
                                                        <textarea class="form-control" rows="3" name="<?= $key ?>" <?= $is_required ? 'required' : '' ?>></textarea>
                                                    <?php break;

                                                    case 'multiple':
                                                    case 'multiple choice': ?>
                                                        <div class="radio-options ms-5">
                                                            <?php foreach ($options as $opt_index => $opt): ?>
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="radio"
                                                                        name="<?= $key ?>"
                                                                        value="<?= htmlspecialchars($opt) ?>"
                                                                        id="<?= $key ?>_<?= $opt_index ?>"
                                                                        <?= $is_required ? 'required' : '' ?>>
                                                                    <label class="form-check-label" for="<?= $key ?>_<?= $opt_index ?>">
                                                                        <?= htmlspecialchars($opt) ?>
                                                                    </label>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php break;

                                                    case 'checkbox': ?>
                                                        <div class="checkbox-options ms-5">
                                                            <?php foreach ($options as $opt_index => $opt): ?>
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox"
                                                                        name="<?= $key ?>[]"
                                                                        value="<?= htmlspecialchars($opt) ?>"
                                                                        id="<?= $key ?>_<?= $opt_index ?>">
                                                                    <label class="form-check-label" for="<?= $key ?>_<?= $opt_index ?>">
                                                                        <?= htmlspecialchars($opt) ?>
                                                                    </label>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php break;

                                                    case 'dropdown': ?>
                                                        <select class="form-select" name="<?= $key ?>" <?= $is_required ? 'required' : '' ?>>
                                                            <option value="" disabled selected>Select an option</option>
                                                            <?php foreach ($options as $opt): ?>
                                                                <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    <?php break;

                                                    case 'date':
                                                    case 'date picker': ?>
                                                        <input type="text" class="form-control date-picker" name="<?= $key ?>"
                                                            placeholder="Select date" <?= $is_required ? 'required' : '' ?> readonly>
                                                    <?php break;

                                                    case 'linear scale':
                                                    case 'linear_scale':
                                                        $scaleMax = $q['scaleMax'] ?? 5;
                                                        $labelLeft = $q['scaleLabelLeft'] ?? $q['labelLeft'] ?? '';
                                                        $labelRight = $q['scaleLabelRight'] ?? $q['labelRight'] ?? '';
                                                    ?>
                                                        <div class="linear-scale-container">
                                                            <?php if (!empty($labelLeft) || !empty($labelRight)): ?>
                                                                <div class="scale-labels d-flex justify-content-between mb-2">
                                                                    <small class="text-muted"><?= htmlspecialchars($labelLeft) ?></small>
                                                                    <small class="text-muted"><?= htmlspecialchars($labelRight) ?></small>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div class="scale-options d-flex justify-content-between">
                                                                <?php for ($i = 1; $i <= $scaleMax; $i++): ?>
                                                                    <div class="scale-option text-center">
                                                                        <input type="radio" name="<?= $key ?>" value="<?= $i ?>"
                                                                            id="<?= $key ?>_scale<?= $i ?>" <?= $is_required ? 'required' : '' ?>>
                                                                        <label for="<?= $key ?>_scale<?= $i ?>" class="d-block mt-1">
                                                                            <small class="text-muted"><?= $i ?></small>
                                                                        </label>
                                                                    </div>
                                                                <?php endfor; ?>
                                                            </div>
                                                        </div>
                                                    <?php break;

                                                    default: ?>
                                                        <input type="text" class="form-control" name="<?= $key ?>" <?= $is_required ? 'required' : '' ?>>
                                                <?php break;
                                                endswitch;
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>

                    <!-- Section Navigation -->
                    <?php if (count($sections) > 1): ?>
                        <div class="section-navigation mb-4">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <button type="button" class="btn btn-outline-primary" id="prevSectionBtn" disabled>
                                    <i class="bi bi-chevron-left me-1"></i>Previous Section
                                </button>

                                <button type="button" class="btn btn-outline-primary" id="nextSectionBtn">
                                    Next Section<i class="bi bi-chevron-right ms-1"></i>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Submit Button - Will only show for single section surveys -->
                    <?php if (count($sections) === 1): ?>
                        <div class="d-flex justify-content-center mt-4" id="submitButtonContainer">
                            <button type="submit" class="btn btn-primary btn-lg submit-response-btn">
                                Submit Response <i class="bi bi-check-circle ms-2"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            <?php else: ?>
                <p>Survey not found or no questions available.</p>
            <?php endif; ?>
        </div>
    </main>

    <footer class="site-footer fixed-bottom py-3 mt-auto">
        <div class="container text-center"></div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="js/publicsurvey.js"></script>
</body>

</html>