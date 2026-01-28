<?php
header('Content-Type: application/json');
require_once 'db_config.php';
require_once 'mailer.php';

// Read input (JSON preferred, fallback to POST)
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

// Get survey title/token for message (optional)
$survey_id = intval($data['survey_id'] ?? 0);
$surveyTitle = '';
$surveyToken = '';
if ($survey_id > 0) {
    $stmt = $conn->prepare("SELECT title, token FROM surveys WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $survey_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows === 1) {
            $s = $res->fetch_assoc();
            $surveyTitle = $s['title'] ?? '';
            $surveyToken = $s['token'] ?? '';
        }
        $stmt->close();
    }
}
$baseUrl = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/';
$surveyLinkDefault = $data['survey_link'] ?? ($surveyToken ? ($baseUrl . "public_survey.php?token=" . urlencode($surveyToken)) : '');

// Determine mode: single (email) or bulk (course/year)
$isSingle = isset($data['email']) || isset($data['email_type']) || isset($data['manual_email']);
$isBulk = isset($data['course']) || isset($data['year']);

if ($isSingle && !$isBulk) {
    // Single send
    $student_email = trim($data['student_email'] ?? '');
    $personal_email = trim($data['personal_email'] ?? '');
    $email_type = $data['email_type'] ?? 'manual'; // manual/student/personal/both
    $survey_link = $data['survey_link'] ?? $surveyLinkDefault;

    // Handle manual emails array
    $manual_emails = $data['emails'] ?? [];

    if (!$student_email && !$personal_email && empty($manual_emails)) {
        echo json_encode(['success' => false, 'message' => 'No email provided']);
        exit;
    }

    $subject = "Please complete survey: " . ($surveyTitle ?: 'Survey');
    $message = "Dear recipient,\n\nYou have been invited to complete the following survey:\n\n";
    $message .= "Survey: " . ($surveyTitle ?: 'Survey') . "\n";
    $message .= "Link: " . $survey_link . "\n\n";
    $message .= "Thank you.\n";

    $sent_count = 0;
    $errors = [];

    // Handle manual emails array
    if (!empty($manual_emails) && is_array($manual_emails)) {
        foreach ($manual_emails as $email_data) {
            $email = trim($email_data['email'] ?? '');
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $result = send_email($email, $subject, $message);
                if ($result === true) {
                    $sent_count++;
                } else {
                    $errors[] = "Failed to send to {$email}: $result";
                }
            } else {
                $errors[] = "Invalid email: {$email}";
            }
        }
    } else {
        // Handle alumni emails
        if (($email_type === 'student' || $email_type === 'both') && filter_var($student_email, FILTER_VALIDATE_EMAIL)) {
            $result = send_email($student_email, $subject, $message);
            if ($result === true) {
                $sent_count++;
            } else {
                $errors[] = "Failed to send to student email: {$student_email}";
            }
        }

        if (($email_type === 'personal' || $email_type === 'both') && filter_var($personal_email, FILTER_VALIDATE_EMAIL)) {
            $result = send_email($personal_email, $subject, $message);
            if ($result === true) {
                $sent_count++;
            } else {
                $errors[] = "Failed to send to personal email: {$personal_email}";
            }
        }
    }

    echo json_encode([
        'success' => $sent_count > 0,
        'sent_count' => $sent_count,
        'errors' => $errors
    ]);
    exit;
}

if ($isBulk && !$isSingle) {
    // Bulk send with improved course matching
    $course = trim($data['course'] ?? '');
    $year = trim($data['year'] ?? '');
    $send_student = !empty($data['send_student_email']) || !empty($data['send_student']);
    $send_personal = !empty($data['send_personal_email']) || !empty($data['send_personal']);

    if (!$send_student && !$send_personal) {
        echo json_encode(['success' => false, 'message' => 'Select at least one recipient type (student or personal).']);
        exit;
    }

    // Build WHERE clause with exact course matching
    $where = [];
    $types = '';
    $params = [];

    if ($course !== '') {
        // Exact match on program column
        $where[] = "program = ?";
        $types .= 's';
        $params[] = $course;
    }
    if ($year !== '') {
        // $year now contains a date string (e.g., "2026-03-01")
        // Convert to proper date format for comparison
        $date = date('Y-m-d', strtotime($year));

        // Match exact graduation_date or year part
        $where[] = "DATE(graduation_date) = ?";
        $types .= 's';
        $params[] = $date;
    }

    $whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

    // Get alumni with valid emails based on selection
    $sql = "SELECT id, name, email, personal_email, program, graduation_date 
            FROM alumni 
            $whereSql 
            AND (email IS NOT NULL OR personal_email IS NOT NULL)
            ORDER BY name";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare alumni query: ' . $conn->error]);
        exit;
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $totalAlumni = $res->num_rows;

    if ($totalAlumni === 0) {
        $message = "No alumni found";
        if ($course) $message .= " for course: $course";
        if ($year) $message .= $course ? " and year: $year" : " for year: $year";
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }

    $subject = "Please complete survey: " . ($surveyTitle ?: 'Survey');
    $survey_link = $data['survey_link'] ?? $surveyLinkDefault;

    // Better email template with personalized name
    $message_template = function ($name, $surveyTitle, $survey_link) {
        $greeting = $name ? "Dear $name," : "Dear Alumni,";
        return "$greeting\n\n"
            . "You have been invited to complete the following survey:\n\n"
            . "Survey: " . ($surveyTitle ?: 'Survey') . "\n"
            . "Link: $survey_link\n\n"
            . "Your participation is greatly appreciated.\n\n"
            . "Thank you,\n"
            . "Swinburne Alumni Team";
    };

    $sent_count = 0;
    $skipped_student = 0;
    $skipped_personal = 0;
    $errors = [];
    $successful_emails = [];

    while ($row = $res->fetch_assoc()) {
        $name = $row['name'] ?? '';
        $studentEmail = $row['email'] ?? '';
        $personalEmail = $row['personal_email'] ?? '';
        $program = $row['program'] ?? '';
        $graduationYear = !empty($row['graduation_date']) ? date('Y', strtotime($row['graduation_date'])) : '';

        $msg = $message_template($name, $surveyTitle, $survey_link);

        // Send to student email
        if ($send_student) {
            if (!empty($studentEmail) && filter_var($studentEmail, FILTER_VALIDATE_EMAIL)) {
                $result = send_email($studentEmail, $subject, $msg);
                if ($result === true) {
                    $sent_count++;
                    $successful_emails[] = [
                        'type' => 'student',
                        'email' => $studentEmail,
                        'name' => $name
                    ];
                } else {
                    $errors[] = "Student email for {$name} ({$studentEmail}): $result";
                }
            } else {
                $skipped_student++;
            }
        }

        // Send to personal email
        if ($send_personal) {
            if (!empty($personalEmail) && filter_var($personalEmail, FILTER_VALIDATE_EMAIL)) {
                $result = send_email($personalEmail, $subject, $msg);
                if ($result === true) {
                    $sent_count++;
                    $successful_emails[] = [
                        'type' => 'personal',
                        'email' => $personalEmail,
                        'name' => $name
                    ];
                } else {
                    $errors[] = "Personal email for {$name} ({$personalEmail}): $result";
                }
            } else {
                $skipped_personal++;
            }
        }
    }

    $stmt->close();

    // Prepare response
    $response = [
        'success' => $sent_count > 0,
        'sent_count' => $sent_count,
        'total_alumni' => $totalAlumni,
        'skipped_student_count' => $skipped_student,
        'skipped_personal_count' => $skipped_personal,
        'errors' => $errors
    ];

    // Add summary if needed
    if ($sent_count > 0) {
        $response['summary'] = "Successfully sent $sent_count emails to alumni";
        if ($course) $response['summary'] .= " in course: $course";
        if ($year) $response['summary'] .= $course ? " and year: $year" : " in year: $year";
    }

    echo json_encode($response);
    exit;
}

// If output here is reach, input was unclear
echo json_encode(['success' => false, 'message' => 'Invalid request payload; specify single (email/email_type) or bulk (course/year) parameters.']);
exit;
