<?php
require_once 'auth.php';
require_once 'db_config.php';
require_login();
require_once 'log_action.php';

// Validate inputs
$survey_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$title = isset($_POST['title']) ? $conn->real_escape_string($_POST['title']) : '';

$form_json_raw = $_POST['form_json'] ?? '';
$allowed_courses = isset($_POST['allowed_courses']) ? $conn->real_escape_string($_POST['allowed_courses']) : '';
$allowed_graduation_dates = isset($_POST['allowed_graduation_dates']) ? $conn->real_escape_string($_POST['allowed_graduation_dates']) : '';

if (!$survey_id || !$title || empty($form_json_raw)) {
    die("Invalid update request.");
}

$form_data = json_decode($form_json_raw, true);

if (!is_array($form_data) || empty($form_data['sections'])) {
    die("Invalid update request.");
}

// Update title inside json
$form_data['title'] = $title;

// Check if alumni verification is required
$require_alumni_verification = (!empty($allowed_courses) || !empty($allowed_graduation_dates)) ? 1 : 0;

// Expiry handling
$expiry_input = $_POST['expiry'] ?? null;
$expiry_sql = null;
if ($expiry_input) {
    $timestamp = strtotime($expiry_input);
    if ($timestamp !== false) {
        $expiry_sql = date('Y-m-d H:i:s', $timestamp);
    }
}
$expiry_value = $expiry_sql ? ("'" . $conn->real_escape_string($expiry_sql) . "'") : "NULL";

// Prepare allowed courses and dates values
$allowed_courses_value = !empty($allowed_courses) ? "'" . $conn->real_escape_string($allowed_courses) . "'" : "NULL";
$allowed_dates_value = !empty($allowed_graduation_dates) ? "'" . $conn->real_escape_string($allowed_graduation_dates) . "'" : "NULL";

// Save JSON
$form_json = json_encode($form_data);
$form_json_escaped = $conn->real_escape_string($form_json);

// Check if the survey exists and get old data
$check_query = $conn->query("SELECT title FROM surveys WHERE id = $survey_id");
if ($check_query->num_rows === 0) {
    die("Survey not found.");
}
$old_survey = $check_query->fetch_assoc();
$old_title = $old_survey['title'];

// Update survey with updated_at timestamp and new fields
$current_time = date('Y-m-d H:i:s');
$update_query = "
    UPDATE surveys 
    SET title = '$title', 
        form_json = '$form_json_escaped', 
        expiry_date = $expiry_value,
        updated_at = '$current_time',
        require_alumni_verification = $require_alumni_verification,
        allowed_courses = $allowed_courses_value,
        allowed_graduation_dates = $allowed_dates_value
    WHERE id = $survey_id
";

if (!$conn->query($update_query)) {
    die("Database error: " . $conn->error);
}

// Handle alumni_survey_response table
if ($require_alumni_verification) {
    // Check if entry exists in alumni_survey_response
    $check_alumni_response = $conn->query("SELECT * FROM alumni_survey_response WHERE survey_id = $survey_id");

    if ($check_alumni_response->num_rows === 0) {
        // Create new entry with NULL alumni_check
        $conn->query("
            INSERT INTO alumni_survey_response (survey_id, survey_title, alumni_check) 
            VALUES ($survey_id, '$title', NULL)
        ");
    } else {
        // Update existing entry - only update survey_title, keep alumni_check as NULL
        $conn->query("
            UPDATE alumni_survey_response 
            SET survey_title = '$title'
            WHERE survey_id = $survey_id
        ");
    }
} else {
    // If no verification required, delete the entry from alumni_survey_response
    $conn->query("
        DELETE FROM alumni_survey_response 
        WHERE survey_id = $survey_id
    ");
}

// Log the action
log_action("Updated survey \"$title\"");

echo "<script>alert('Survey updated successfully.'); window.location.href='survey_management.php';</script>";
exit;
