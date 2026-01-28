<?php
require_once("database.php");
require_once("auth.php");
require_once "db_config.php";
require_login();

// Function to safely encode JSON for JavaScript
function jsEncode($data)
{
    return htmlspecialchars(json_encode($data), ENT_QUOTES, 'UTF-8');
}

// Handle edit form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_alumni'])) {
    $id = $_POST['alumni_id'];
    $name = $_POST['name'];
    $student_id = $_POST['student_id'];
    $graduation_date = $_POST['graduation_date'];
    $program = $_POST['program'];
    $email = $_POST['email'];
    $personal_email = $_POST['personal_email'];
    $mobile_no = $_POST['mobile_no'];

    // Handle extra JSON data
    $extra_json = [];
    if (isset($_POST['extra_key']) && isset($_POST['extra_value'])) {
        $keys = $_POST['extra_key'];
        $values = $_POST['extra_value'];
        for ($i = 0; $i < count($keys); $i++) {
            if (!empty($keys[$i]) && !empty($values[$i])) {
                $extra_json[trim($keys[$i])] = trim($values[$i]);
            }
        }
    }
    $extra_json_str = json_encode($extra_json, JSON_UNESCAPED_UNICODE);

    // Update query
    $stmt = $conn->prepare("UPDATE " . TABLE_ALUMNI . " SET 
        name = ?, student_id = ?, graduation_date = ?, program = ?, 
        email = ?, personal_email = ?, mobile_no = ?, extra = ? 
        WHERE id = ?");
    $stmt->bind_param(
        "ssssssssi",
        $name,
        $student_id,
        $graduation_date,
        $program,
        $email,
        $personal_email,
        $mobile_no,
        $extra_json_str,
        $id
    );

    if ($stmt->execute()) {
        $success_message = "Alumni information updated successfully!";
    } else {
        $error_message = "Error updating alumni: " . $conn->error;
    }
}

// Handle delete
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM " . TABLE_ALUMNI . " WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $success_message = "Alumni deleted successfully!";
    } else {
        $error_message = "Error deleting alumni: " . $conn->error;
    }
    // Redirect to avoid resubmission
    header("Location: alumni.php");
    exit();
}

// Load alumni from DB
$alumniFromDb = [];
if (isset($conn) && $conn) {
    $sql = "SELECT id, name, student_id, graduation_date, program, email, personal_email, mobile_no, extra 
        FROM " . TABLE_ALUMNI . " 
        ORDER BY name ASC";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $alumniFromDb = $result->fetch_all(MYSQLI_ASSOC);
    }
}

// Load courses for dropdown
$courses = [];
$cRes = $conn->query("SELECT name FROM " . TABLE_COURSES . " ORDER BY name ASC");
if ($cRes && $cRes->num_rows > 0) {
    $courses = $cRes->fetch_all(MYSQLI_ASSOC);
}

// Load graduation dates for dropdown
$graduation_dates = [];
$gRes = $conn->query("SELECT date, label FROM " . TABLE_GRADUATION_DATES . " ORDER BY date DESC");
if ($gRes && $gRes->num_rows > 0) {
    $graduation_dates = $gRes->fetch_all(MYSQLI_ASSOC);
}

function formatExtraInfo($extra)
{
    if (!$extra || $extra === '[]' || $extra === 'null') return "No extra info";

    $decoded = json_decode($extra, true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($decoded)) {
        return "No extra info";
    }

    $parts = [];
    foreach ($decoded as $key => $value) {
        $parts[] = "$key: $value";
    }
    return implode(", ", $parts);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Alumni Directory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/main.css">
    <link rel="stylesheet" href="styles/alumni.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="main">
        <div class="container mt-4 data-section">
            <h6 class="mb-3">Alumni Directory</h6>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="mb-3">
                <div class="row g-2">
                    <div class="col-md-4">
                        <input type="text" id="alumniFilter" class="form-control form-control-sm" placeholder="Filter by name">
                    </div>
                    <div class="col-md-4">
                        <select id="programFilter" class="form-select form-select-sm">
                            <option value="">All Programs</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="text" id="graduationFilter" class="form-control form-control-sm" placeholder="Filter by graduation date">
                    </div>
                </div>

                <div class="row g-2 mt-2">
                    <div class="col-md-4">
                        <select id="sortResponses" class="form-select form-select-sm">
                            <option value="alumni">Sort: Alumni Name</option>
                            <option value="newest">Sort: Newest First</option>
                            <option value="oldest">Sort: Oldest First</option>
                            <option value="program">Sort: Courses</option>
                        </select>
                    </div>
                </div>

                <div class="row g-2 mt-2">
                    <div class="col-md-2">
                        <button id="exportCsvBtn" class="btn btn-success btn-sm w-100">Export CSV</button>
                    </div>
                </div>
            </div>

            <!-- Alumni List -->
            <div id="alumniContainer">
                <?php if (empty($alumniFromDb)): ?>
                    <div class="card p-3 text-center">No alumni found.</div>
                <?php else: ?>
                    <?php foreach ($alumniFromDb as $a):
                        // Parse extra data for each alumni
                        $extra_data = [];
                        if ($a['extra'] && $a['extra'] !== '[]' && $a['extra'] !== 'null' && $a['extra'] !== '') {
                            $decoded = json_decode($a['extra'], true);
                            if ($decoded && is_array($decoded)) {
                                $extra_data = $decoded;
                            }
                        }
                    ?>
                        <div class="response-box mb-3" data-id="<?= $a['id'] ?>"
                            data-name="<?= htmlspecialchars($a['name']) ?>"
                            data-student-id="<?= htmlspecialchars($a['student_id']) ?>"
                            data-graduation-date="<?= htmlspecialchars($a['graduation_date']) ?>"
                            data-program="<?= htmlspecialchars($a['program']) ?>"
                            data-email="<?= htmlspecialchars($a['email']) ?>"
                            data-personal-email="<?= htmlspecialchars($a['personal_email']) ?>"
                            data-mobile-no="<?= htmlspecialchars($a['mobile_no']) ?>"
                            data-extra='<?= jsEncode($extra_data) ?>'>
                            <div class="response-header">
                                <div class="response-meta">
                                    <div class="d-flex align-items-center">
                                        <strong class="alumni-name"><?= htmlspecialchars($a['name']) ?></strong>
                                    </div>
                                    <div class="text-muted">
                                        <span><i class="bi bi-mortarboard"></i>
                                            <span class="alumni-program"><?= htmlspecialchars($a['program']) ?></span>
                                        </span> •
                                        <span><i class="bi bi-calendar-check"></i>
                                            <span class="alumni-graduation"><?= htmlspecialchars($a['graduation_date']) ?></span>
                                        </span>
                                    </div>
                                </div>
                                <div class="response-actions">
                                    <div class="response-count-badge">
                                        ID: <span class="alumni-student-id"><?= htmlspecialchars($a['student_id']) ?></span>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button class="edit-btn" onclick="editAlumniFromElement(this)">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <button class="delete-btn" onclick="confirmDelete(<?= $a['id'] ?>, '<?= addslashes($a['name']) ?>')">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </div>
                                    <div class="response-expand-icon"><i class="bi bi-chevron-down"></i></div>
                                </div>
                            </div>

                            <div class="response-details">
                                <hr>
                                <div class="response-answers">
                                    <div><strong>Student ID</strong>
                                        <span class="alumni-student-id"><?= htmlspecialchars($a['student_id']) ?></span>
                                    </div>
                                    <div><strong>Email</strong>
                                        <span class="alumni-email"><?= htmlspecialchars($a['email']) ?></span>
                                    </div>
                                    <div><strong>Personal Email</strong>
                                        <span class="alumni-personal"><?= htmlspecialchars($a['personal_email']) ?></span>
                                    </div>
                                    <div><strong>Mobile No</strong>
                                        <span class="alumni-mobile"><?= htmlspecialchars($a['mobile_no']) ?></span>
                                    </div>
                                    <div><strong>Program</strong>
                                        <span class="alumni-program"><?= htmlspecialchars($a['program']) ?></span>
                                    </div>
                                    <div><strong>Graduation Date</strong>
                                        <span class="alumni-graduation"><?= htmlspecialchars($a['graduation_date']) ?></span>
                                    </div>
                                    <div><strong>Extra Info</strong>
                                        <span class="alumni-extra"><?= htmlspecialchars(formatExtraInfo($a['extra'])) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Edit Alumni Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="alumni_id" id="alumni_id">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Name *</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="student_id" class="form-label">Student ID *</label>
                                <input type="text" class="form-control" id="student_id" name="student_id" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="col-md-6">
                                <label for="personal_email" class="form-label">Personal Email</label>
                                <input type="email" class="form-control" id="personal_email" name="personal_email">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="mobile_no" class="form-label">Mobile No</label>
                                <input type="text" class="form-control" id="mobile_no" name="mobile_no">
                            </div>
                            <div class="col-md-6">
                                <label for="program" class="form-label">Program *</label>
                                <select class="form-select" id="program" name="program" required>
                                    <option value="">Select Program</option>
                                    <?php foreach ($courses as $c): ?>
                                        <option value="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="graduation_date" class="form-label">Graduation Date *</label>
                                <select class="form-select" id="graduation_date" name="graduation_date" required>
                                    <option value="">Select Graduation Date</option>
                                    <?php foreach ($graduation_dates as $g): ?>
                                        <option value="<?= htmlspecialchars($g['date']) ?>">
                                            <?= htmlspecialchars($g['label']) ?> (<?= htmlspecialchars($g['date']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Extra JSON Fields -->
                        <div class="mb-3">
                            <label class="form-label">Additional Information</label>
                            <div id="extraFieldsContainer" class="extra-fields-container">
                                <!-- Extra fields will be added here dynamically -->
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="addExtraField()">
                                <i class="bi bi-plus"></i> Add Additional Info
                            </button>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="update_alumni">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/alumni.js"></script>
    <script>
        let extraFieldCounter = 0;

        function editAlumniFromElement(button) {
            const alumniBox = button.closest('.response-box');

            const id = alumniBox.dataset.id;
            const name = alumniBox.dataset.name;
            const studentId = alumniBox.dataset.studentId;
            const graduationDate = alumniBox.dataset.graduationDate;
            const program = alumniBox.dataset.program;
            const email = alumniBox.dataset.email;
            const personalEmail = alumniBox.dataset.personalEmail || '';
            const mobileNo = alumniBox.dataset.mobileNo || '';

            // Debug: Check what's in the data-extra attribute
            console.log('Raw extra data attribute:', alumniBox.dataset.extra);

            // Parse extra data
            let extraData = {};
            try {
                extraData = JSON.parse(alumniBox.dataset.extra);
                console.log('Parsed extra data:', extraData);
            } catch (e) {
                console.log('Error parsing extra data:', e.message);
                console.log('No extra data or invalid JSON');
            }

            // Call the edit function
            editAlumni(id, name, studentId, graduationDate, program, email, personalEmail, mobileNo, extraData);
        }

        function editAlumni(id, name, studentId, graduationDate, program, email, personalEmail, mobileNo, extraData) {
            console.log('Editing alumni - Extra data received:', extraData);
            console.log('Type of extraData:', typeof extraData);
            console.log('Keys in extraData:', Object.keys(extraData));

            // Reset modal form
            const form = document.querySelector('#editModal form');
            form.reset();

            // Set form values
            document.getElementById('alumni_id').value = id;
            document.getElementById('name').value = name;
            document.getElementById('student_id').value = studentId;
            document.getElementById('email').value = email;
            document.getElementById('personal_email').value = personalEmail;
            document.getElementById('mobile_no').value = mobileNo;

            // Set dropdown values
            const programSelect = document.getElementById('program');
            const graduationSelect = document.getElementById('graduation_date');

            if (program) {
                for (let i = 0; i < programSelect.options.length; i++) {
                    if (programSelect.options[i].value === program) {
                        programSelect.selectedIndex = i;
                        break;
                    }
                }
            }

            if (graduationDate) {
                for (let i = 0; i < graduationSelect.options.length; i++) {
                    if (graduationSelect.options[i].value === graduationDate) {
                        graduationSelect.selectedIndex = i;
                        break;
                    }
                }
            }

            // Clear existing extra fields
            const container = document.getElementById('extraFieldsContainer');
            container.innerHTML = '';
            extraFieldCounter = 0;

            // Parse and populate extra JSON data
            if (extraData && typeof extraData === 'object' && Object.keys(extraData).length > 0) {
                console.log('Loading extra data - Object entries:', Object.entries(extraData));
                Object.entries(extraData).forEach(([key, value]) => {
                    console.log('Adding extra field:', key, '=>', value);
                    if (key && value !== null && value !== undefined) {
                        addExtraField(key, value.toString());
                    }
                });
            } else {
                console.log('No extra data found or empty object');
                // Add one empty field by default
                addExtraField();
            }

            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('editModal'));
            modal.show();
        }

        function addExtraField(key = '', value = '') {
            const container = document.getElementById('extraFieldsContainer');
            const fieldId = 'extraField_' + extraFieldCounter++;

            const fieldRow = document.createElement('div');
            fieldRow.className = 'extra-field-row';
            fieldRow.id = fieldId;

            // Escape quotes for HTML attributes
            const escapedKey = key.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
            const escapedValue = value.replace(/"/g, '&quot;').replace(/'/g, '&#39;');

            fieldRow.innerHTML = `
            <input type="text" class="form-control form-control-sm" 
                   name="extra_key[]" placeholder="Key (e.g., Degree)" 
                   value="${escapedKey}">
            <input type="text" class="form-control form-control-sm" 
                   name="extra_value[]" placeholder="Value (e.g., Double Degree)" 
                   value="${escapedValue}">
            <button type="button" class="btn btn-sm btn-danger" 
                    onclick="removeExtraField('${fieldId}')">
                <i class="bi bi-trash"></i>
            </button>
        `;

            container.appendChild(fieldRow);

            // Focus on the new key field if it's empty
            if (!key) {
                setTimeout(() => {
                    const inputs = fieldRow.querySelectorAll('input');
                    if (inputs[0]) inputs[0].focus();
                }, 100);
            }
        }

        function removeExtraField(fieldId) {
            const field = document.getElementById(fieldId);
            if (field) {
                field.remove();
            }
        }

        function confirmDelete(id, name) {
            if (confirm(`Are you sure you want to delete "${name}"? This action cannot be undone.`)) {
                window.location.href = `alumni.php?delete_id=${id}`;
            }
        }

        // Add one empty extra field by default when modal opens (if no existing data)
        document.getElementById('editModal').addEventListener('show.bs.modal', function() {
            const container = document.getElementById('extraFieldsContainer');
            if (container.children.length === 0) {
                addExtraField();
            }
        });

        // Clear modal when closed
        document.getElementById('editModal').addEventListener('hidden.bs.modal', function() {
            const form = document.querySelector('#editModal form');
            form.reset();
            document.getElementById('extraFieldsContainer').innerHTML = '';
            extraFieldCounter = 0;
        });
    </script>
    <footer class="site-footer fixed-bottom py-3 mt-auto">
        <div class="container text-center">
            <p class="mb-0">ICT30017 ICT PROJECT A | <span class="team-name">TEAM Deadline Dominators</span></p>
        </div>
    </footer>
</body>

</html>