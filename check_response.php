<?php
require_once 'auth.php';
require_once 'db_config.php';
require_login();

// Get all surveys that require alumni verification
$surveys_result = $conn->query("
    SELECT s.id, s.title, s.allowed_courses, s.allowed_graduation_dates 
    FROM surveys s 
    WHERE s.require_alumni_verification = 1 
    ORDER BY s.created_at DESC
");

// Define base url
$baseUrl = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/';
// Ensure base URL ends with a slash
if (substr($baseUrl, -1) !== '/') {
    $baseUrl .= '/';
}

// Process form submission for sending reminder emails
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_emails'])) {
    $survey_id = intval($_POST['survey_id']);
    $selected_alumni = $_POST['selected_alumni'] ?? [];

    if (!empty($selected_alumni)) {
        // Get survey details
        $survey_stmt = $conn->prepare("SELECT title, token FROM surveys WHERE id = ?");
        $survey_stmt->bind_param("i", $survey_id);
        $survey_stmt->execute();
        $survey_result = $survey_stmt->get_result();
        $survey = $survey_result->fetch_assoc();

        // Build survey link
        $survey_link = $baseUrl . "public_survey.php?token=" . urlencode($survey['token']);

        // Send emails to selected alumni
        $sent_count = 0;
        $errors = [];

        foreach ($selected_alumni as $alumni_id) {
            $alumni_stmt = $conn->prepare("SELECT name, email, personal_email FROM alumni WHERE id = ?");
            $alumni_stmt->bind_param("i", $alumni_id);
            $alumni_stmt->execute();
            $alumni_result = $alumni_stmt->get_result();

            if ($alumni_result->num_rows > 0) {
                $alumni = $alumni_result->fetch_assoc();

                // Try to send to student email first
                if (!empty($alumni['email'])) {
                    $subject = "Reminder: Complete Survey - " . $survey['title'];
                    $message = "Dear " . $alumni['name'] . ",\n\n";
                    $message .= "You haven't yet completed the survey: " . $survey['title'] . "\n";
                    $message .= "Please complete it at: " . $survey_link . "\n\n";
                    $message .= "Thank you,\nSwinburne Alumni Team";

                    // Use your existing mailer function
                    require_once 'mailer.php';
                    $result = send_email($alumni['email'], $subject, $message);

                    if ($result === true) {
                        $sent_count++;
                    } else {
                        $errors[] = "Failed to send to " . $alumni['email'] . ": " . $result;
                    }
                }
            }
        }

        $message = "Sent " . $sent_count . " reminder email(s).";
        if (!empty($errors)) {
            $message .= " Errors: " . implode(", ", $errors);
        }
        echo "<script>alert('" . addslashes($message) . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Check Response</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/main.css">
    <link rel="stylesheet" href="styles/surveymanagement.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>

<body>
    <!-- Navigation bar -->
    <?php include 'navbar.php'; ?>

    <main class="body-survey-management pb-5">
        <div class="text-box-view">
            <h1 class="text-title-view">Response</h1>
        </div>

        <div class="container mt-4">
            <!-- Survey Selection -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-filter me-2"></i>Select Survey</h5>
                    <form method="GET" id="surveyForm">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <select class="form-select" name="survey_id" id="surveySelect" required onchange="this.form.submit()">
                                    <option value="">-- Select a survey --</option>
                                    <?php while ($survey = $surveys_result->fetch_assoc()): ?>
                                        <option value="<?= $survey['id'] ?>"
                                            <?= ($_GET['survey_id'] ?? '') == $survey['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($survey['title']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search me-2"></i>Check
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (isset($_GET['survey_id']) && !empty($_GET['survey_id'])):
                $survey_id = intval($_GET['survey_id']);

                // Get survey details
                $survey_stmt = $conn->prepare("
                    SELECT s.title, s.allowed_courses, s.allowed_graduation_dates, 
                           s.form_json, s.token, s.require_alumni_verification,
                           asr.alumni_check
                    FROM surveys s 
                    LEFT JOIN alumni_survey_response asr ON s.id = asr.survey_id
                    WHERE s.id = ?
                ");
                $survey_stmt->bind_param("i", $survey_id);
                $survey_stmt->execute();
                $survey_result = $survey_stmt->get_result();

                if ($survey_result->num_rows > 0):
                    $survey = $survey_result->fetch_assoc();

                    // Get alumni_check data - NEW FORMAT: {"John Doe / 12345678": 1, "Jane Smith / 87654321": 1}
                    $alumni_check_data = [];
                    if (!empty($survey['alumni_check']) && $survey['alumni_check'] != 'null') {
                        $alumni_check_data = json_decode($survey['alumni_check'], true);
                        if (!is_array($alumni_check_data)) {
                            $alumni_check_data = [];
                        }
                    }

                    // Create a lookup array from alumni_check data
                    $responded_lookup = [];
                    foreach ($alumni_check_data as $key => $value) {
                        if ($value == 1) {
                            // Split the combined key to get name and student_id
                            $parts = explode(' / ', $key);
                            if (count($parts) == 2) {
                                $name = trim($parts[0]);
                                $student_id = trim($parts[1]);
                                $responded_lookup[$name] = true;
                                $responded_lookup[$student_id] = true;
                            }
                        }
                    }

                    // Parse allowed courses and dates
                    $allowed_courses = !empty($survey['allowed_courses']) ?
                        explode(',', $survey['allowed_courses']) : [];
                    $allowed_dates = !empty($survey['allowed_graduation_dates']) ?
                        explode(',', $survey['allowed_graduation_dates']) : [];

                    // Build WHERE clause for alumni query based on restrictions
                    $where_conditions = [];
                    $params = [];
                    $param_types = '';

                    if (!empty($allowed_courses)) {
                        $course_placeholders = implode(',', array_fill(0, count($allowed_courses), '?'));
                        $where_conditions[] = "program IN ($course_placeholders)";
                        $params = array_merge($params, $allowed_courses);
                        $param_types .= str_repeat('s', count($allowed_courses));
                    }

                    if (!empty($allowed_dates)) {
                        $date_placeholders = implode(',', array_fill(0, count($allowed_dates), '?'));
                        $where_conditions[] = "DATE(graduation_date) IN ($date_placeholders)";
                        $params = array_merge($params, $allowed_dates);
                        $param_types .= str_repeat('s', count($allowed_dates));
                    }

                    $alumni_where = !empty($where_conditions) ?
                        "WHERE " . implode(' AND ', $where_conditions) : '';

                    // Get all eligible alumni
                    $alumni_sql = "SELECT id, name, student_id, email, personal_email, program, graduation_date 
                                   FROM alumni $alumni_where ORDER BY name";

                    $alumni_stmt = $conn->prepare($alumni_sql);
                    if (!empty($params)) {
                        $alumni_stmt->bind_param($param_types, ...$params);
                    }
                    $alumni_stmt->execute();
                    $alumni_result = $alumni_stmt->get_result();

                    // Separate into responders and non-responders
                    $responders = [];
                    $non_responders = [];
                    $total_eligible = 0;

                    while ($alumni = $alumni_result->fetch_assoc()) {
                        $total_eligible++;
                        $has_responded = false;

                        // Check if alumni has responded by looking for their name or student ID in the lookup array
                        if (isset($responded_lookup[$alumni['name']]) || isset($responded_lookup[$alumni['student_id']])) {
                            $has_responded = true;
                        }

                        if ($has_responded) {
                            $responders[] = $alumni;
                        } else {
                            $non_responders[] = $alumni;
                        }
                    }

                    $total_responded = count($responders);
                    $total_non_responded = count($non_responders);
                    $response_rate = $total_eligible > 0 ? round(($total_responded / $total_eligible) * 100) : 0;
            ?>

                    <!-- Results Section -->
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-people me-2"></i>
                                Survey: <?= htmlspecialchars($survey['title']) ?>
                            </h5>
                            <span class="badge bg-light text-dark">
                                <i class="bi bi-shield-lock me-1"></i>
                                <?= !empty($allowed_courses) || !empty($allowed_dates) ? 'Restricted Survey' : 'Open Survey' ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <!-- Statistics -->
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h6 class="card-title text-muted">Total Eligible</h6>
                                            <h3 class="text-primary"><?= $total_eligible ?></h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h6 class="card-title text-muted">Responded</h6>
                                            <h3 class="text-success"><?= $total_responded ?></h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h6 class="card-title text-muted">Non-Responders</h6>
                                            <h3 class="text-danger"><?= $total_non_responded ?></h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h6 class="card-title text-muted">Response Rate</h6>
                                            <h3 class="<?= $response_rate >= 70 ? 'text-success' : ($response_rate >= 50 ? 'text-warning' : 'text-danger') ?>">
                                                <?= $response_rate ?>%
                                            </h3>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Restrictions Info -->
                            <?php if (!empty($allowed_courses) || !empty($allowed_dates)): ?>
                                <div class="alert alert-info mb-4">
                                    <h6><i class="bi bi-info-circle me-2"></i>Survey Restrictions:</h6>
                                    <div class="row mt-2">
                                        <?php if (!empty($allowed_courses)): ?>
                                            <div class="col-md-6">
                                                <strong>Allowed Courses:</strong><br>
                                                <?php foreach ($allowed_courses as $course): ?>
                                                    <span class="badge bg-secondary me-1 mb-1"><?= htmlspecialchars($course) ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($allowed_dates)): ?>
                                            <div class="col-md-6">
                                                <strong>Allowed Graduation Dates:</strong><br>
                                                <?php foreach ($allowed_dates as $date): ?>
                                                    <span class="badge bg-secondary me-1 mb-1"><?= htmlspecialchars($date) ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Non-Responders Table (Only if there are non-responders) -->
                            <?php if (!empty($non_responders)): ?>
                                <form method="POST" id="emailForm">
                                    <input type="hidden" name="survey_id" value="<?= $survey_id ?>">

                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0 text-danger">
                                            <i class="bi bi-exclamation-triangle me-2"></i>
                                            Non-Responders (<?= $total_non_responded ?> alumni)
                                        </h6>
                                        <div>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="selectAll()">
                                                <i class="bi bi-check-all me-1"></i>Select All
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="deselectAll()">
                                                <i class="bi bi-x-circle me-1"></i>Deselect All
                                            </button>
                                        </div>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-hover table-sm">
                                            <thead>
                                                <tr>
                                                    <th width="50">
                                                        <input type="checkbox" id="selectAllCheckbox" onchange="toggleAll(this)">
                                                    </th>
                                                    <th>Name</th>
                                                    <th>Student ID</th>
                                                    <th>Course</th>
                                                    <th>Graduation Date</th>
                                                    <th>Student Email</th>
                                                    <th>Personal Email</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($non_responders as $alumni): ?>
                                                    <tr>
                                                        <td>
                                                            <input type="checkbox" name="selected_alumni[]"
                                                                value="<?= $alumni['id'] ?>"
                                                                class="alumni-checkbox">
                                                        </td>
                                                        <td><?= htmlspecialchars($alumni['name']) ?></td>
                                                        <td><?= htmlspecialchars($alumni['student_id']) ?></td>
                                                        <td><?= htmlspecialchars($alumni['program']) ?></td>
                                                        <td><?= !empty($alumni['graduation_date']) ? date('Y', strtotime($alumni['graduation_date'])) : 'N/A' ?></td>
                                                        <td><?= htmlspecialchars($alumni['email']) ?></td>
                                                        <td><?= htmlspecialchars($alumni['personal_email']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="mt-3">
                                        <button type="submit" name="send_emails" class="btn btn-primary">
                                            <i class="bi bi-envelope me-2"></i>Send Reminder Emails to Selected
                                        </button>
                                        <a href="<?= htmlspecialchars($baseUrl) ?>public_survey.php?token=<?= urlencode($survey['token']) ?>"
                                            target="_blank" class="btn btn-outline-primary ms-2">
                                            <i class="bi bi-eye me-2"></i>View Survey
                                        </a>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-success">
                                    <i class="bi bi-check-circle me-2"></i>
                                    <strong>Excellent!</strong> All <?= $total_eligible ?> eligible alumni have responded to this survey!
                                </div>
                            <?php endif; ?>

                            <!-- Responders Table (Collapsed) -->
                            <?php if (!empty($responders)): ?>
                                <div class="mt-4">
                                    <a class="btn btn-outline-secondary w-100" data-bs-toggle="collapse" href="#respondersCollapse">
                                        <i class="bi bi-chevron-down me-2"></i>
                                        Show Responders (<?= $total_responded ?>)
                                    </a>
                                    <div class="collapse mt-3" id="respondersCollapse">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Student ID</th>
                                                        <th>Course</th>
                                                        <th>Graduation Date</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($responders as $alumni): ?>
                                                        <tr>
                                                            <td><i class="bi bi-check-circle text-success me-2"></i><?= htmlspecialchars($alumni['name']) ?></td>
                                                            <td><?= htmlspecialchars($alumni['student_id']) ?></td>
                                                            <td><?= htmlspecialchars($alumni['program']) ?></td>
                                                            <td><?= !empty($alumni['graduation_date']) ? date('Y', strtotime($alumni['graduation_date'])) : 'N/A' ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <script>
                        function toggleAll(source) {
                            const checkboxes = document.querySelectorAll('.alumni-checkbox');
                            checkboxes.forEach(checkbox => {
                                checkbox.checked = source.checked;
                            });
                        }

                        function selectAll() {
                            const checkboxes = document.querySelectorAll('.alumni-checkbox');
                            checkboxes.forEach(checkbox => {
                                checkbox.checked = true;
                            });
                            document.getElementById('selectAllCheckbox').checked = true;
                        }

                        function deselectAll() {
                            const checkboxes = document.querySelectorAll('.alumni-checkbox');
                            checkboxes.forEach(checkbox => {
                                checkbox.checked = false;
                            });
                            document.getElementById('selectAllCheckbox').checked = false;
                        }

                        // Update select all checkbox when individual checkboxes change
                        document.addEventListener('DOMContentLoaded', function() {
                            const checkboxes = document.querySelectorAll('.alumni-checkbox');
                            const selectAllCheckbox = document.getElementById('selectAllCheckbox');

                            if (checkboxes.length > 0 && selectAllCheckbox) {
                                checkboxes.forEach(checkbox => {
                                    checkbox.addEventListener('change', function() {
                                        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                                        const someChecked = Array.from(checkboxes).some(cb => cb.checked);

                                        selectAllCheckbox.checked = allChecked;
                                        selectAllCheckbox.indeterminate = someChecked && !allChecked;
                                    });
                                });
                            }
                        });
                    </script>

                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Survey not found or doesn't require alumni verification.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <footer class="site-footer fixed-bottom py-3 mt-4 mt-auto">
        <div class="container text-center">
            <p class="mb-0">ICT30017 ICT PROJECT A | <span class="team-name">TEAM Deadline Dominators</span></p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>