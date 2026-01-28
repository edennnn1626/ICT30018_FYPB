<?php
require_once 'auth.php';
require_once 'db_config.php';
require_login();

// Clean up URL if we have conflict parameter but no actual conflict data
if (isset($_GET['conflict']) && !isset($_SESSION['pending_alumni']) && !isset($_SESSION['csv_conflicts'])) {
    header("Location: create_alumni.php");
    exit;
}

// Prevent form state restoration on conflict pages
if (isset($_GET['conflict'])) {
    // Clear any form-related session data
    unset($_SESSION['form_data']);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Add Alumni</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
    <link rel="stylesheet" href="styles/main.css">
    <link rel="stylesheet" href="styles/createalumni.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
</head>

<body>
    <!-- Navigation Bar -->
    <?php include 'navbar.php'; ?>

    <main class="body-survey-management pb-5">
        <div id="surveycreation">
            <div class="text-box-view">
                <h1 class="text-title-view">Add Alumni</h1>
            </div>
            <div class="container mt-4">
                <?php if (isset($_GET['success']) && $_GET['success'] == 1):
                    $processed = $_GET['processed'] ?? 0;
                    $new = $_GET['new'] ?? 0;
                    $skipped = $_GET['skipped'] ?? 0;
                ?>
                    <div id="successAlert" class="alert alert-success alert-dismissible fade show text-center mb-4" role="alert" style="z-index: 1000;">
                        <i class="bi bi-check-circle-fill"></i>
                        Alumni records processed successfully!<br>
                        <small>
                            <?php if ($processed > 0): ?>Overwritten: <?= $processed ?><br><?php endif; ?>
                        <?php if ($new > 0): ?>New: <?= $new ?><br><?php endif; ?>
                    <?php if ($skipped > 0): ?>Skipped: <?= $skipped ?><br><?php endif; ?>
                        </small>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <!-- ERROR ALERT -->
                <?php if (isset($_GET['error']) && $_GET['error'] == 1 && isset($_SESSION['error_message'])): ?>
                    <div id="errorAlert" class="alert alert-danger alert-dismissible fade show text-center mb-4" role="alert" style="z-index: 1000;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <?php
                        echo $_SESSION['error_message'];
                        unset($_SESSION['error_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- WARNING ALERT -->
                <?php if (isset($_SESSION['warning_message'])): ?>
                    <div id="warningAlert" class="alert alert-warning alert-dismissible fade show text-center mb-4" role="alert" style="z-index: 1000;">
                        <i class="bi bi-info-circle-fill"></i>
                        <?php
                        echo $_SESSION['warning_message'];
                        unset($_SESSION['warning_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <form id="addStudentForm" enctype="multipart/form-data" method="POST" action="add_alumni.php">
                    <div class="modal-body">
                        <!-- Radio buttons to choose method -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Add Students By:</label><br>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="add_method" id="manualEntry" value="manual" checked>
                                <label class="form-check-label" for="manualEntry">Manual Entry</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="add_method" id="excelUpload" value="excel">
                                <label class="form-check-label" for="excelUpload">Upload Excel</label>
                            </div>
                        </div>

                        <!-- Manual Alumni Info Entry Fields -->
                        <div id="manualFields">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="student_name" class="form-label fw-bold">Student Name</label>
                                    <input type="text" name="name" id="student_name" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="student_id" class="form-label fw-bold">Student ID</label>
                                    <input type="text" name="student_id" id="student_id" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="graduation_date_select" class="form-label fw-bold">Graduation Date</label>
                                    <?php
                                    // Fetch graduation dates from database
                                    $gdRes = $conn->query("SELECT date, label FROM graduation_dates ORDER BY date DESC");
                                    ?>
                                    <div class="input-group">
                                        <select name="graduation_date" id="graduation_date_select" class="form-select select2-graduation" required>
                                            <option value="" selected>Select Graduation Date</option>
                                            <?php if ($gdRes && $gdRes->num_rows > 0): ?>
                                                <?php while ($gd = $gdRes->fetch_assoc()): ?>
                                                    <option value="<?= htmlspecialchars($gd['date']) ?>">
                                                        <?= htmlspecialchars($gd['label']) ?> (<?= htmlspecialchars($gd['date']) ?>)
                                                    </option>
                                                <?php endwhile; ?>
                                            <?php endif; ?>
                                        </select>
                                        <button type="button" class="btn btn-outline-secondary" id="addGraduationDateBtn" title="Add new Graduation Date">
                                            <i class="bi bi-plus"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="program_select" class="form-label fw-bold">Program</label>
                                    <?php
                                    // fetch courses
                                    $coursesRes = $conn->query("SELECT id, name FROM courses ORDER BY name ASC");
                                    ?>
                                    <div class="input-group">
                                        <select name="program" id="program_select" class="form-select select2-program" required>
                                            <option value="">Select Program</option>
                                            <?php if ($coursesRes && $coursesRes->num_rows > 0): ?>
                                                <?php while ($c = $coursesRes->fetch_assoc()): ?>
                                                    <option value="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?></option>
                                                <?php endwhile; ?>
                                            <?php endif; ?>
                                        </select>
                                        <button type="button" class="btn btn-outline-secondary" id="addCourseBtn" title="Add new Program">
                                            <i class="bi bi-plus"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="mobile_no" class="form-label fw-bold">Mobile No</label>
                                    <input type="text" name="mobile_no" id="mobile_no" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label fw-bold">Email</label>
                                    <input type="email" name="email" id="email" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="personal_email" class="form-label fw-bold">Personal Email</label>
                                    <input type="email" name="personal_email" id="personal_email" class="form-control" required>
                                </div>
                            </div>
                            <hr>
                            <div id="extraFields">
                                <!-- Extra fields will be appended here -->
                            </div>
                            <button type="button" class="btn btn-outline-secondary mt-2" id="addExtraFieldBtn">
                                <i class="bi bi-plus-circle"></i> Add Extra Info
                            </button>
                        </div>

                        <!-- Excel Alumni Upload Field -->
                        <div id="excelFields" style="display: none;">
                            <div class="mb-3">
                                <label for="upload_excel" class="form-label fw-bold">Upload CSV File</label>
                                <input type="file" name="upload_excel" id="upload_excel" class="form-control" accept=".csv">
                                <small class="text-muted">Supported formats: .csv</small>
                                <div class="alert alert-info mt-2">
                                    <h6 class="alert-heading"><i class="bi bi-info-circle"></i> CSV File Format</h6>
                                    <p class="mb-1"><strong>Required columns:</strong> name, student_id, program, graduation_date, mobile_no, email, personal_email</p>
                                    <p class="mb-1"><strong>Extra columns:</strong> Any additional columns (like "LinkedIn", "Company", etc.) will be saved as extra information.</p>
                                    <p class="mb-1"><strong>Date formats accepted:</strong> dd/mm/yyyy, mm/dd/yyyy, dd-mm-yyyy, yyyy-mm-dd, or text dates like "15 September 2023"</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="dashboard.php" class="btn btn-cancel">Cancel</a>
                        <button type="submit" class="btn btn-save"><i class="bi bi-save"></i> Save</button>
                    </div>
                </form>
                <br><br>
            </div>
        </div>
    </main>

    <!-- Graduation Date Modal -->
    <div class="modal fade" id="graduationDateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div id="graduationDateForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Graduation Date</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="graduationDateAlert"></div>
                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" id="newGraduationDate" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Label (display text)</label>
                            <input type="text" id="newGraduationLabel" class="form-control" placeholder="e.g., Oct 2025 Ceremony" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" id="submitGraduationDateBtn" class="btn btn-primary">Add Date</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Course Modal -->
    <div class="modal fade" id="courseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Program</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="courseAlert"></div>
                    <div class="mb-3">
                        <label class="form-label">Program name</label>
                        <input type="text" id="newCourseName" class="form-control" placeholder="e.g., Bachelor of IT" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="submitCourseBtn" class="btn btn-primary">Add Program</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Conflict Resolution Modal -->
    <div class="modal fade" id="conflictModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Duplicate Alumni Found</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (isset($_GET['conflict']) && $_GET['conflict'] === '1' && isset($_SESSION['pending_alumni'])):
                        $data = $_SESSION['pending_alumni'];
                        $conflict = $data['conflicting_record'];
                    ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            An alumni with <?= $data['conflict_type'] === 'student_id' ? 'Student ID' : 'Name' ?>
                            "<?= htmlspecialchars($data['conflict_type'] === 'student_id' ? $data['student_id'] : $data['name']) ?>" already exists.
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <h6>Existing Record:</h6>
                                <div class="card">
                                    <div class="card-body">
                                        <p><strong>Name:</strong> <?= htmlspecialchars($conflict['name'] ?? 'N/A') ?></p>
                                        <p><strong>Student ID:</strong> <?= htmlspecialchars($conflict['student_id'] ?? 'N/A') ?></p>
                                        <p><strong>Program:</strong> <?= htmlspecialchars($conflict['program'] ?? 'N/A') ?></p>
                                        <p><strong>Email:</strong> <?= htmlspecialchars($conflict['email'] ?? 'N/A') ?></p>
                                        <?php if ($conflict['extra'] && $conflict['extra'] !== '[]' && $conflict['extra'] !== 'null'):
                                            $extraData = json_decode($conflict['extra'], true);
                                            if ($extraData && !empty($extraData)): ?>
                                                <p><strong>Extra Info:</strong></p>
                                                <ul class="list-unstyled">
                                                    <?php foreach ($extraData as $key => $value): ?>
                                                        <li><small><?= htmlspecialchars($key) ?>: <?= htmlspecialchars($value) ?></small></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6>New Record:</h6>
                                <div class="card">
                                    <div class="card-body">
                                        <p><strong>Name:</strong> <?= htmlspecialchars($data['name']) ?></p>
                                        <p><strong>Student ID:</strong> <?= htmlspecialchars($data['student_id']) ?></p>
                                        <p><strong>Program:</strong> <?= htmlspecialchars($data['program']) ?></p>
                                        <p><strong>Email:</strong> <?= htmlspecialchars($data['email']) ?></p>
                                        <?php if (!empty($data['extra_fields'])): ?>
                                            <p><strong>New Extra Info:</strong></p>
                                            <ul class="list-unstyled">
                                                <?php foreach ($data['extra_fields'] as $field): ?>
                                                    <li><small><?= htmlspecialchars($field['key'] ?? '') ?>: <?= htmlspecialchars($field['value'] ?? '') ?></small></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <p>What would you like to do?</p>
                            <form method="POST" action="add_alumni.php?action=resolve_conflict&type=manual">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="overwrite" id="overwriteYes" value="1" checked>
                                    <label class="form-check-label" for="overwriteYes">
                                        <strong>Overwrite</strong> the existing record with new information
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="overwrite" id="overwriteNo" value="0">
                                    <label class="form-check-label" for="overwriteNo">
                                        <strong>Skip</strong> this record (keep existing)
                                    </label>
                                </div>
                                <div class="mt-3 text-end">
                                    <button type="submit" class="btn btn-primary">Confirm</button>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                </div>
                            </form>
                        </div>

                    <?php elseif (isset($_GET['conflict']) && $_GET['conflict'] === 'csv' && isset($_SESSION['csv_conflicts'])):
                        $conflicts = $_SESSION['csv_conflicts'];
                        $pendingRecords = $_SESSION['csv_pending_records'] ?? [];
                    ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            Found <?= count($conflicts) ?> duplicate record(s) in your CSV file.
                        </div>

                        <form method="POST" action="add_alumni.php?action=resolve_conflict&type=csv" id="csvConflictForm" class="mt-3" novalidate>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Row</th>
                                            <th>Student ID</th>
                                            <th>Name</th>
                                            <th>Conflict Type</th>
                                            <th>Existing Record</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($conflicts as $index => $conflict):
                                            $rowNum = $conflict['row'];
                                            $data = $conflict['data'];
                                            $extra = $conflict['extra'];
                                        ?>
                                            <tr>
                                                <td><?= $rowNum ?></td>
                                                <td>
                                                    <?= htmlspecialchars($data['student_id']) ?>
                                                    <!-- Hidden fields to pass all data -->
                                                    <input type="hidden" name="conflicts[<?= $index ?>][student_id]" value="<?= htmlspecialchars($data['student_id']) ?>">
                                                    <input type="hidden" name="conflicts[<?= $index ?>][name]" value="<?= htmlspecialchars($data['name']) ?>">
                                                    <input type="hidden" name="conflicts[<?= $index ?>][program]" value="<?= htmlspecialchars($data['program']) ?>">
                                                    <input type="hidden" name="conflicts[<?= $index ?>][graduation_date]" value="<?= htmlspecialchars($data['graduation_date']) ?>">
                                                    <input type="hidden" name="conflicts[<?= $index ?>][mobile_no]" value="<?= htmlspecialchars($data['mobile_no']) ?>">
                                                    <input type="hidden" name="conflicts[<?= $index ?>][email]" value="<?= htmlspecialchars($data['email']) ?>">
                                                    <input type="hidden" name="conflicts[<?= $index ?>][personal_email]" value="<?= htmlspecialchars($data['personal_email']) ?>">
                                                    <!-- Extra fields as JSON -->
                                                    <input type="hidden" name="conflicts[<?= $index ?>][extra_json]" value="<?= htmlspecialchars(json_encode($extra, JSON_UNESCAPED_UNICODE)) ?>">
                                                    <input type="hidden" name="conflicts[<?= $index ?>][conflict_type]" value="<?= htmlspecialchars($conflict['conflict_type']) ?>">
                                                    <input type="hidden" name="conflicts[<?= $index ?>][row]" value="<?= $rowNum ?>">
                                                </td>
                                                <td><?= htmlspecialchars($data['name']) ?></td>
                                                <td>
                                                    <span class="badge bg-warning">
                                                        <?= $conflict['conflict_type'] === 'student_id' ? 'Student ID' : 'Name' ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars($conflict['conflicting_record']['name'] ?? 'N/A') ?>
                                                    (ID: <?= htmlspecialchars($conflict['conflicting_record']['student_id'] ?? 'N/A') ?>)
                                                </td>
                                                <td>
                                                    <select class="form-select form-select-sm action-select" name="conflicts[<?= $index ?>][action]" required>
                                                        <option value="skip">Skip</option>
                                                        <option value="overwrite">Overwrite</option>
                                                        <option value="merge">Merge</option>
                                                    </select>
                                                    <small class="form-text text-muted d-block mt-1">
                                                        <i class="bi bi-info-circle"></i> <strong>Merge:</strong>Add onto "Extra Info"
                                                    </small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Hidden field for pending records -->
                            <input type="hidden" name="pending_records_json" value="<?= htmlspecialchars(json_encode($pendingRecords, JSON_UNESCAPED_UNICODE)) ?>">

                            <div class="mt-3">
                                <p>Bulk actions:</p>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-outline-primary" id="selectAllOverwrite">
                                        Select All "Overwrite"
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" id="selectAllSkip">
                                        Select All "Skip"
                                    </button>
                                    <button type="button" class="btn btn-outline-info" id="selectAllMerge">
                                        Select All "Merge"
                                    </button>
                                </div>
                                <div class="alert alert-info mt-3">
                                    <i class="bi bi-info-circle-fill"></i>
                                    <strong>About Merge option:</strong> When you choose "Merge", it will <strong>update all fields from the CSV</strong>, 
                                            including required fields. The difference from "Overwrite" is that:
                                    <ul class="mb-0">
                                        <li><strong>Merge:</strong> Updates fields only if CSV has values (empty non-required cells in CSV won't erase existing data)</li>
                                        <li><strong>Overwrite:</strong> Replaces ALL data with CSV values (empty non-required cells will clear existing data)</li>
                                    </ul>
                                    <br>
                                    <strong>Example:</strong> If CSV has empty "Company" field but existing record has "Company: ABC Corp", Merge keeps "ABC Corp"
                                                    while Overwrite clears it.
                                </div>
                                <div class="text-end mt-3">
                                    <button type="submit" class="btn btn-primary">Process Records</button>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <footer class="site-footer fixed-bottom py-3 mt-auto">
        <div class="container text-center">
            <p class="mb-0">ICT30017 ICT PROJECT A | <span class="team-name">TEAM Deadline Dominators</span></p>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/vue@3/dist/vue.global.prod.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="js/main.js"></script>
    <script src="js/createalumni.js"></script>
    <script>
        // Initialize Select2 for searchable dropdowns
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Select2 for graduation date dropdown
            $('.select2-graduation').select2({
                theme: 'bootstrap-5',
                placeholder: 'Search or select graduation date',
                allowClear: true,
                width: '100%'
            });

            // Initialize Select2 for program dropdown
            $('.select2-program').select2({
                theme: 'bootstrap-5',
                placeholder: 'Search or select program',
                allowClear: true,
                width: '100%'
            });

            // Handle modal show events to refresh Select2
            $('#graduationDateModal').on('shown.bs.modal', function() {
                $('#newGraduationDate').focus();
            });

            $('#courseModal').on('shown.bs.modal', function() {
                $('#newCourseName').focus();
            });

            <?php if (isset($_GET['conflict'])): ?>
                // DON'T save form state when on conflict page
                // Clear any existing saved state
                sessionStorage.removeItem('alumniFormState');
                localStorage.removeItem('alumniFormState');

                // Remove required attributes from form fields
                const requiredFields = [
                    'student_name', 'student_id', 'graduation_date_select',
                    'mobile_no', 'email', 'personal_email', 'program_select'
                ];

                requiredFields.forEach(fieldId => {
                    const field = document.getElementById(fieldId);
                    if (field) {
                        field.removeAttribute('required');
                    }
                });

                // Prevent main form from submitting on conflict pages
                const mainForm = document.getElementById('addStudentForm');
                if (mainForm) {
                    mainForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        return false;
                    });
                }

                const conflictModal = new bootstrap.Modal(document.getElementById('conflictModal'));

                // Fix bulk action buttons
                const selectAllOverwriteBtn = document.getElementById('selectAllOverwrite');
                const selectAllSkipBtn = document.getElementById('selectAllSkip');
                const selectAllMergeBtn = document.getElementById('selectAllMerge');

                if (selectAllOverwriteBtn) {
                    selectAllOverwriteBtn.addEventListener('click', function() {
                        document.querySelectorAll('.action-select').forEach(select => {
                            select.value = 'overwrite';
                        });
                    });
                }

                if (selectAllSkipBtn) {
                    selectAllSkipBtn.addEventListener('click', function() {
                        document.querySelectorAll('.action-select').forEach(select => {
                            select.value = 'skip';
                        });
                    });
                }

                if (selectAllMergeBtn) {
                    selectAllMergeBtn.addEventListener('click', function() {
                        document.querySelectorAll('.action-select').forEach(select => {
                            select.value = 'merge';
                        });
                    });
                }

                // When modal is hidden, redirect to clean URL without conflict parameter
                conflictModal._element.addEventListener('hidden.bs.modal', function(event) {
                    // Check if modal was closed by clicking the X or backdrop (not form submit)
                    if (!event.target.classList.contains('modal-content') || event.target.closest('.btn-close')) {
                        // Use history.replaceState to clean URL without reloading
                        if (window.history.replaceState) {
                            window.history.replaceState({}, document.title, 'create_alumni.php');
                        } else {
                            window.location.href = 'create_alumni.php';
                        }
                    }
                });

                conflictModal.show();

            <?php endif; ?>
        });
    </script>
    <script>
        // Auto-hide alerts after 3 seconds
        document.addEventListener('DOMContentLoaded', function() {
            // Function to hide an alert
            const hideAlert = (alertElement) => {
                if (alertElement) {
                    setTimeout(() => {
                        // Use Bootstrap's fade out animation
                        alertElement.classList.remove('show');
                        setTimeout(() => {
                            alertElement.style.display = 'none';
                        }, 500);
                    }, 3000); // 3 seconds for all alerts
                }
            };

            // Hide all alerts
            hideAlert(document.getElementById('successAlert'));
            hideAlert(document.getElementById('errorAlert'));
            hideAlert(document.getElementById('warningAlert'));
        });
    </script>
</body>

</html>