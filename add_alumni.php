<?php
require_once "auth.php";
require_once "db_config.php";
require_once "log_action.php";
require_login();

// -----------------------------------------
// AJAX JSON CALLS (Add graduation date / add course)
// -----------------------------------------
$rawContentType = $_SERVER["CONTENT_TYPE"] ?? "";
if (strpos($rawContentType, "application/json") !== false) {

    ob_start(); // Capture stray output
    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);

    $response = ["success" => false, "message" => "Unknown error"];

    if (is_array($data) && isset($data["action"])) {

        // -----------------------------------------
        // ADD GRADUATION DATE
        // -----------------------------------------
        if ($data["action"] === "add_graduation_date") {
            $dateRaw = trim($data["date"] ?? "");
            $label = trim($data["label"] ?? "");

            // Validate
            $dateObj = DateTime::createFromFormat("Y-m-d", $dateRaw);
            if (!$dateObj || $label === "") {
                $response = ["success" => false, "message" => "Invalid date or label."];
            } else {
                $dateVal = $dateObj->format("Y-m-d");

                $stmt = $conn->prepare("
                    INSERT INTO graduation_dates (`date`, `label`)
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE label = VALUES(label)
                ");
                $stmt->bind_param("ss", $dateVal, $label);

                if ($stmt->execute()) {
                    // Log action of adding graduation date
                    log_action("Added graduation date \"$label\" ($dateVal)");
                    $response = ["success" => true, "date" => $dateVal, "label" => $label];
                } else {
                    $response = ["success" => false, "message" => "Database error: " . $stmt->error];
                }

                $stmt->close();
            }
        }

        // -----------------------------------------
        // ADD COURSE (PROGRAM)
        // -----------------------------------------
        elseif ($data["action"] === "add_course") {

            $name = trim($data["name"] ?? "");
            if ($name === "") {
                $response = ["success" => false, "message" => "Program name is required."];
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO courses (`name`)
                    VALUES (?)
                    ON DUPLICATE KEY UPDATE name = VALUES(name)
                ");
                $stmt->bind_param("s", $name);

                if ($stmt->execute()) {
                    // Log action of adding course
                    log_action("Added program \"$name\"");
                    $response = ["success" => true, "name" => $name];
                } else {
                    $response = ["success" => false, "message" => "Database error: " . $stmt->error];
                }

                $stmt->close();
            }
        } else {
            $response = ["success" => false, "message" => "Unknown action type."];
        }
    }

    ob_end_clean(); // Remove stray output
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($response);
    exit;
}

// -----------------------------------------
// NORMAL FORM SUBMISSION (Manual or CSV upload)
// -----------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (isset($_GET['action']) && $_GET['action'] === 'resolve_conflict' && $_GET['type'] === 'csv') {
        // Do nothing here
    } else {
        $method = $_POST["add_method"] ?? "manual";

        // -------------------------------------------------
        // MANUAL ENTRY WITH CONFLICT DETECTION
        // -------------------------------------------------
        if ($method === "manual") {

            $name = trim($_POST['name'] ?? "");
            $student_id = trim($_POST['student_id'] ?? "");
            $graduation_date = trim($_POST['graduation_date'] ?? "");
            $program = trim($_POST['program'] ?? "");
            $mobile_no = trim($_POST['mobile_no'] ?? "");
            $email = trim($_POST['email'] ?? "");
            $personal_email = trim($_POST['personal_email'] ?? "");

            // Validate required fields
            $missingFields = [];
            if ($name === "") $missingFields[] = "Name";
            if ($student_id === "") $missingFields[] = "Student ID";
            if ($graduation_date === "") $missingFields[] = "Graduation Date";
            if ($program === "") $missingFields[] = "Program";
            if ($mobile_no === "") $missingFields[] = "Mobile No";
            if ($email === "") $missingFields[] = "Email";
            if ($personal_email === "") $missingFields[] = "Personal Email";

            if (!empty($missingFields)) {
                $_SESSION['error_message'] = "Missing required fields: " . implode(", ", $missingFields);
                header("Location: create_alumni.php?error=1");
                exit;
            }

            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error_message'] = "Invalid email format for Email";
                header("Location: create_alumni.php?error=1");
                exit;
            }

            if (!filter_var($personal_email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error_message'] = "Invalid email format for Personal Email";
                header("Location: create_alumni.php?error=1");
                exit;
            }

            // Check for conflicts
            $conflict = checkForConflicts($student_id, $name);

            if ($conflict) {
                // Store data in session for later processing after user confirmation
                $_SESSION['pending_alumni'] = [
                    'name' => $name,
                    'student_id' => $student_id,
                    'graduation_date' => $graduation_date,
                    'program' => $program,
                    'mobile_no' => $mobile_no,
                    'email' => $email,
                    'personal_email' => $personal_email,
                    'extra_fields' => $_POST["extra_fields"] ?? [],
                    'conflict_type' => $conflict['type'],
                    'conflicting_record' => $conflict['record']
                ];

                header("Location: create_alumni.php?conflict=1&student_id=" . urlencode($student_id));
                exit;
            }

            // Process extra fields for manual entry
            $extraFields = [];
            if (isset($_POST["extra_fields"]) && is_array($_POST["extra_fields"])) {
                foreach ($_POST["extra_fields"] as $field) {
                    if (isset($field['key']) && isset($field['value'])) {
                        $key = trim($field['key']);
                        $value = trim($field['value']);
                        if ($key !== '' && $value !== '') {
                            $extraFields[] = ['key' => $key, 'value' => $value];
                        }
                    }
                }
            }

            // No conflict, proceed with insertion
            if (insertAlumniRecord($name, $student_id, $graduation_date, $program, $mobile_no, $email, $personal_email, $extraFields)) {
                // Log action of adding alumni manually
                log_action("Added alumni manually: $name - $student_id");
                header("Location: create_alumni.php?success=1");
                exit;
            } else {
                $_SESSION['error_message'] = "Failed to add alumni record. Please try again.";
                header("Location: create_alumni.php?error=1");
                exit;
            }
        }

        // -------------------------------------------------
        // CSV UPLOAD WITH CONFLICT DETECTION AND AUTO-ADD GRADUATION DATES/PROGRAMS IF DOESNT EXIST IN DATABASE
        // -------------------------------------------------
        elseif ($method === "excel") {

            if (!isset($_FILES["upload_excel"]) || $_FILES["upload_excel"]["error"] !== 0) {
                $_SESSION['error_message'] = "File upload error. Please try again.";
                header("Location: create_alumni.php?error=1");
                exit;
            }

            $file = fopen($_FILES["upload_excel"]["tmp_name"], "r");
            if (!$file) {
                $_SESSION['error_message'] = "Failed to open file.";
                header("Location: create_alumni.php?error=1");
                exit;
            }

            $header = fgetcsv($file);
            if (!$header) {
                $_SESSION['error_message'] = "CSV file is empty.";
                header("Location: create_alumni.php?error=1");
                exit;
            }

            // Standard fields for the alumni table
            $standardColumns = ["student_id", "name", "program", "graduation_date", "mobile_no", "email", "personal_email"];

            // Check for required standard columns
            $missingColumns = [];
            foreach ($standardColumns as $col) {
                if (!in_array($col, $header)) {
                    $missingColumns[] = $col;
                }
            }

            if (!empty($missingColumns)) {
                $_SESSION['error_message'] = "Missing required columns: " . implode(", ", $missingColumns);
                header("Location: create_alumni.php?error=1");
                exit;
            }

            $rowNum = 1;
            $conflicts = [];
            $pendingRecords = [];
            $invalidRows = [];
            $newGraduationDates = []; // Track new graduation dates
            $newPrograms = []; // Track new programs

            while ($row = fgetcsv($file)) {
                $rowNum++;
                $data = array_combine($header, $row);

                // Separate standard fields
                $stdData = [];
                $hasMissingFields = false;
                foreach ($standardColumns as $col) {
                    $value = $data[$col] ?? null;
                    if (empty(trim($value ?? ''))) {
                        $hasMissingFields = true;
                        $invalidRows[] = $rowNum;
                    }
                    $stdData[$col] = trim($value);
                }

                // Skip rows with missing required fields
                if ($hasMissingFields) {
                    continue;
                }

                // Validate email format
                if (
                    !filter_var($stdData["email"], FILTER_VALIDATE_EMAIL) ||
                    !filter_var($stdData["personal_email"], FILTER_VALIDATE_EMAIL)
                ) {
                    $invalidRows[] = $rowNum;
                    continue;
                }

                // -------------------------------
                // AUTO-ADD GRADUATION DATE
                // -------------------------------
                $graduationDate = $stdData["graduation_date"];
                if (!empty($graduationDate)) {
                    // Convert date format if needed
                    $formattedDate = convertDateFormat($graduationDate);

                    // Validate the formatted date
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $formattedDate)) {
                        $invalidRows[] = $rowNum;
                        continue;
                    }

                    // Check if date exists in graduation_dates table
                    $checkGradStmt = $conn->prepare("SELECT id FROM graduation_dates WHERE date = ?");
                    $checkGradStmt->bind_param("s", $formattedDate);
                    $checkGradStmt->execute();
                    $gradResult = $checkGradStmt->get_result();

                    if ($gradResult->num_rows === 0) {
                        // Date doesn't exist, add it
                        // Format label: "September 2025 Ceremony"
                        $dateObj = DateTime::createFromFormat('Y-m-d', $formattedDate);
                        if ($dateObj) {
                            $monthName = $dateObj->format('F'); // Full month name
                            $year = $dateObj->format('Y');
                            $label = "$monthName $year Ceremony";

                            $insertGradStmt = $conn->prepare("
                                INSERT INTO graduation_dates (date, label) 
                                VALUES (?, ?)
                                ON DUPLICATE KEY UPDATE label = VALUES(label)
                            ");
                            $insertGradStmt->bind_param("ss", $formattedDate, $label);
                            $insertGradStmt->execute();
                            $insertGradStmt->close();

                            // Track for logging
                            if (!in_array($formattedDate, $newGraduationDates)) {
                                $newGraduationDates[] = $formattedDate;
                            }
                        }
                    }
                    $checkGradStmt->close();

                    // Update the data with formatted date
                    $stdData["graduation_date"] = $formattedDate;
                }

                // -------------------------------
                // AUTO-ADD PROGRAM
                // -------------------------------
                $program = $stdData["program"];
                if (!empty($program)) {
                    // Check if program exists in courses table
                    $checkProgStmt = $conn->prepare("SELECT id FROM courses WHERE name = ?");
                    $checkProgStmt->bind_param("s", $program);
                    $checkProgStmt->execute();
                    $progResult = $checkProgStmt->get_result();

                    if ($progResult->num_rows === 0) {
                        // Program doesn't exist, add it
                        $insertProgStmt = $conn->prepare("
                            INSERT INTO courses (name) 
                            VALUES (?)
                            ON DUPLICATE KEY UPDATE name = VALUES(name)
                        ");
                        $insertProgStmt->bind_param("s", $program);
                        $insertProgStmt->execute();
                        $insertProgStmt->close();

                        // Track for logging
                        if (!in_array($program, $newPrograms)) {
                            $newPrograms[] = $program;
                        }
                    }
                    $checkProgStmt->close();
                }

                // Collect extra columns - ONLY if they have values
                $extraData = [];
                foreach ($data as $colName => $value) {
                    if (!in_array($colName, $standardColumns)) {
                        $trimmedValue = trim($value);
                        // Only include if value is not empty
                        if ($trimmedValue !== '') {
                            $extraData[$colName] = $trimmedValue;
                        }
                    }
                }

                // Check for conflicts
                $conflict = checkForConflicts($stdData["student_id"], $stdData["name"]);

                if ($conflict) {
                    $conflicts[] = [
                        'row' => $rowNum,
                        'data' => $stdData,
                        'extra' => $extraData,
                        'conflict_type' => $conflict['type'],
                        'conflicting_record' => $conflict['record']
                    ];
                } else {
                    $pendingRecords[] = [
                        'data' => $stdData,
                        'extra' => $extraData
                    ];
                }
            }

            fclose($file);

            // Log auto-added graduation dates and programs
            if (!empty($newGraduationDates)) {
                log_action("Auto-added graduation dates from CSV: " . implode(", ", $newGraduationDates));
            }
            if (!empty($newPrograms)) {
                log_action("Auto-added programs from CSV: " . implode(", ", $newPrograms));
            }

            // Store invalid rows info in session if any
            if (!empty($invalidRows)) {
                $_SESSION['warning_message'] = "Skipped " . count($invalidRows) . " row(s) with missing or invalid data (Rows: " . implode(", ", $invalidRows) . ")";
            }

            // Also add info about auto-added items
            $autoAddInfo = [];
            if (!empty($newGraduationDates)) {
                $autoAddInfo[] = count($newGraduationDates) . " new graduation date(s)";
            }
            if (!empty($newPrograms)) {
                $autoAddInfo[] = count($newPrograms) . " new program(s)";
            }

            if (!empty($autoAddInfo)) {
                if (!isset($_SESSION['warning_message'])) {
                    $_SESSION['warning_message'] = "";
                }
                $_SESSION['warning_message'] .= (!empty($_SESSION['warning_message']) ? " " : "") .
                    "Auto-added: " . implode(", ", $autoAddInfo) . ".";
            }

            if (!empty($conflicts)) {
                // Store conflicts in session for review
                $_SESSION['csv_conflicts'] = $conflicts;
                $_SESSION['csv_pending_records'] = $pendingRecords;
                header("Location: create_alumni.php?conflict=csv");
                exit;
            } else {
                // No conflicts, insert all records
                $successCount = 0;
                $failCount = 0;

                foreach ($pendingRecords as $record) {
                    if (insertAlumniRecord(
                        $record['data']["name"],
                        $record['data']["student_id"],
                        $record['data']["graduation_date"],
                        $record['data']["program"],
                        $record['data']["mobile_no"],
                        $record['data']["email"],
                        $record['data']["personal_email"],
                        $record['extra']
                    )) {
                        $successCount++;
                    } else {
                        $failCount++;
                    }
                }

                // Log action of uploading alumni via CSV
                log_action("Uploaded alumni via CSV: $successCount successful, $failCount failed");

                $_SESSION['success_message'] = "Successfully added $successCount alumni record(s)" .
                    ($failCount > 0 ? " ($failCount failed)" : "") .
                    (!empty($invalidRows) ? ". Skipped " . count($invalidRows) . " invalid row(s)." : "") .
                    (!empty($autoAddInfo) ? " Auto-added: " . implode(", ", $autoAddInfo) . "." : "");
                header("Location: create_alumni.php?success=1");
                exit;
            }
        }
    }
}

// -------------------------------------------------
// PROCESS CONFLICT RESOLUTION WITH AUTO-ADD FEATURE
// -------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'resolve_conflict') {
    if ($_GET['type'] === 'manual' && isset($_SESSION['pending_alumni'])) {
        // ... (keep manual conflict resolution unchanged) ...
    } elseif ($_GET['type'] === 'csv') {
        if (!isset($_POST['conflicts']) || !is_array($_POST['conflicts'])) {
            $_SESSION['error_message'] = "No conflict data received. Please try again.";
            header("Location: create_alumni.php?error=1");
            exit;
        }

        // Get pending records from JSON
        $pendingRecords = [];
        if (isset($_POST['pending_records_json']) && !empty($_POST['pending_records_json'])) {
            $pendingRecords = json_decode($_POST['pending_records_json'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $_SESSION['error_message'] = "Invalid pending records data.";
                header("Location: create_alumni.php?error=1");
                exit;
            }
        }

        // First, auto-add graduation dates and programs from all records
        $newGraduationDates = [];
        $newPrograms = [];

        // Check all conflicts for new dates/programs
        foreach ($_POST['conflicts'] as $conflictData) {
            // Auto-add graduation date
            if (isset($conflictData['graduation_date']) && !empty($conflictData['graduation_date'])) {
                $formattedDate = convertDateFormat($conflictData['graduation_date']);

                // Validate the formatted date
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $formattedDate)) {
                    continue;
                }

                $checkGradStmt = $conn->prepare("SELECT id FROM graduation_dates WHERE date = ?");
                $checkGradStmt->bind_param("s", $formattedDate);
                $checkGradStmt->execute();
                $gradResult = $checkGradStmt->get_result();

                if ($gradResult->num_rows === 0) {
                    $dateObj = DateTime::createFromFormat('Y-m-d', $formattedDate);
                    if ($dateObj) {
                        $monthName = $dateObj->format('F');
                        $year = $dateObj->format('Y');
                        $label = "$monthName $year Ceremony";

                        $insertGradStmt = $conn->prepare("
                            INSERT INTO graduation_dates (date, label) 
                            VALUES (?, ?)
                            ON DUPLICATE KEY UPDATE label = VALUES(label)
                        ");
                        $insertGradStmt->bind_param("ss", $formattedDate, $label);
                        $insertGradStmt->execute();
                        $insertGradStmt->close();

                        if (!in_array($formattedDate, $newGraduationDates)) {
                            $newGraduationDates[] = $formattedDate;
                        }
                    }
                }
                $checkGradStmt->close();
            }

            // Auto-add program
            if (isset($conflictData['program']) && !empty($conflictData['program'])) {
                $program = trim($conflictData['program']);

                $checkProgStmt = $conn->prepare("SELECT id FROM courses WHERE name = ?");
                $checkProgStmt->bind_param("s", $program);
                $checkProgStmt->execute();
                $progResult = $checkProgStmt->get_result();

                if ($progResult->num_rows === 0) {
                    $insertProgStmt = $conn->prepare("
                        INSERT INTO courses (name) 
                        VALUES (?)
                        ON DUPLICATE KEY UPDATE name = VALUES(name)
                    ");
                    $insertProgStmt->bind_param("s", $program);
                    $insertProgStmt->execute();
                    $insertProgStmt->close();

                    if (!in_array($program, $newPrograms)) {
                        $newPrograms[] = $program;
                    }
                }
                $checkProgStmt->close();
            }
        }

        // Also check pending records
        if (isset($pendingRecords)) {
            foreach ($pendingRecords as $record) {
                $data = $record['data'];

                // Auto-add graduation date from pending records
                if (isset($data['graduation_date']) && !empty($data['graduation_date'])) {
                    $formattedDate = convertDateFormat($data['graduation_date']);

                    // Validate the formatted date
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $formattedDate)) {
                        continue; // Skip this record
                    }

                    $checkGradStmt = $conn->prepare("SELECT id FROM graduation_dates WHERE date = ?");
                    $checkGradStmt->bind_param("s", $formattedDate);
                    $checkGradStmt->execute();
                    $gradResult = $checkGradStmt->get_result();

                    if ($gradResult->num_rows === 0) {
                        $dateObj = DateTime::createFromFormat('Y-m-d', $formattedDate);
                        if ($dateObj) {
                            $monthName = $dateObj->format('F');
                            $year = $dateObj->format('Y');
                            $label = "$monthName $year Ceremony";

                            $insertGradStmt = $conn->prepare("
                                INSERT INTO graduation_dates (date, label) 
                                VALUES (?, ?)
                                ON DUPLICATE KEY UPDATE label = VALUES(label)
                            ");
                            $insertGradStmt->bind_param("ss", $formattedDate, $label);
                            $insertGradStmt->execute();
                            $insertGradStmt->close();

                            if (!in_array($formattedDate, $newGraduationDates)) {
                                $newGraduationDates[] = $formattedDate;
                            }
                        }
                    }
                    $checkGradStmt->close();
                }

                // Auto-add program from pending records
                if (isset($data['program']) && !empty($data['program'])) {
                    $program = trim($data['program']);

                    $checkProgStmt = $conn->prepare("SELECT id FROM courses WHERE name = ?");
                    $checkProgStmt->bind_param("s", $program);
                    $checkProgStmt->execute();
                    $progResult = $checkProgStmt->get_result();

                    if ($progResult->num_rows === 0) {
                        $insertProgStmt = $conn->prepare("
                            INSERT INTO courses (name) 
                            VALUES (?)
                            ON DUPLICATE KEY UPDATE name = VALUES(name)
                        ");
                        $insertProgStmt->bind_param("s", $program);
                        $insertProgStmt->execute();
                        $insertProgStmt->close();

                        if (!in_array($program, $newPrograms)) {
                            $newPrograms[] = $program;
                        }
                    }
                    $checkProgStmt->close();
                }
            }
        }

        // Log auto-added items
        if (!empty($newGraduationDates)) {
            log_action("Auto-added graduation dates from CSV conflict resolution: " . implode(", ", $newGraduationDates));
        }
        if (!empty($newPrograms)) {
            log_action("Auto-added programs from CSV conflict resolution: " . implode(", ", $newPrograms));
        }

        // Add auto-add info to success message
        $autoAddInfo = [];
        if (!empty($newGraduationDates)) {
            $autoAddInfo[] = count($newGraduationDates) . " new graduation date(s)";
        }
        if (!empty($newPrograms)) {
            $autoAddInfo[] = count($newPrograms) . " new program(s)";
        }

        // Process conflicts based on user choice
        $processedCount = 0;
        $mergedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        foreach ($_POST['conflicts'] as $conflictData) {
            if (!isset($conflictData['action'])) {
                $skippedCount++;
                continue;
            }

            $action = $conflictData['action'];

            if ($action === 'overwrite') {
                // Extract data
                $student_id = trim($conflictData['student_id'] ?? '');
                $name = trim($conflictData['name'] ?? '');
                $program = trim($conflictData['program'] ?? '');
                $graduation_date = trim($conflictData['graduation_date'] ?? '');
                $mobile_no = trim($conflictData['mobile_no'] ?? '');
                $email = trim($conflictData['email'] ?? '');
                $personal_email = trim($conflictData['personal_email'] ?? '');

                // Validate required fields
                if (
                    empty($student_id) || empty($name) || empty($graduation_date) || empty($program) ||
                    empty($mobile_no) || empty($email) || empty($personal_email)
                ) {
                    $skippedCount++;
                    continue;
                }

                // Validate email format
                if (
                    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
                    !filter_var($personal_email, FILTER_VALIDATE_EMAIL)
                ) {
                    $skippedCount++;
                    continue;
                }

                // Get extra fields
                $extraData = [];
                if (isset($conflictData['extra_json']) && !empty($conflictData['extra_json'])) {
                    $extraData = json_decode($conflictData['extra_json'], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $extraData = [];
                    }
                }

                // Update existing record (overwrite everything)
                $graduation_date = convertDateFormat($graduation_date);
                $extraJson = json_encode($extraData, JSON_UNESCAPED_UNICODE);

                $stmt = $conn->prepare("
                    UPDATE alumni 
                    SET name = ?, graduation_date = ?, program = ?, mobile_no = ?, 
                        email = ?, personal_email = ?, extra = ?
                    WHERE student_id = ?
                ");
                $stmt->bind_param(
                    "ssssssss",
                    $name,
                    $graduation_date,
                    $program,
                    $mobile_no,
                    $email,
                    $personal_email,
                    $extraJson,
                    $student_id
                );

                if ($stmt->execute()) {
                    $processedCount++;
                } else {
                    $errorCount++;
                }

                $stmt->close();
            } elseif ($action === 'merge') {
                // Merge: Update only non-empty fields and merge extra data
                $student_id = trim($conflictData['student_id'] ?? '');

                // Get existing record
                $existingRecord = getAlumniByStudentId($student_id);
                if (!$existingRecord) {
                    $skippedCount++;
                    continue;
                }

                // Extract new data
                $name = trim($conflictData['name'] ?? '');
                $program = trim($conflictData['program'] ?? '');
                $graduation_date = trim($conflictData['graduation_date'] ?? '');
                $mobile_no = trim($conflictData['mobile_no'] ?? '');
                $email = trim($conflictData['email'] ?? '');
                $personal_email = trim($conflictData['personal_email'] ?? '');

                // Get new extra fields
                $newExtraData = [];
                if (isset($conflictData['extra_json']) && !empty($conflictData['extra_json'])) {
                    $newExtraData = json_decode($conflictData['extra_json'], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $newExtraData = [];
                    }
                }

                // Get existing extra data
                $existingExtraData = [];
                if (!empty($existingRecord['extra']) && $existingRecord['extra'] !== '[]' && $existingRecord['extra'] !== 'null') {
                    $existingExtraData = json_decode($existingRecord['extra'], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $existingExtraData = [];
                    }
                }

                // Merge extra data - new values overwrite existing ones, but keep existing ones if new is empty
                $mergedExtraData = array_merge($existingExtraData, $newExtraData);

                // Prepare update - only update fields that have values in CSV
                $updateFields = [];
                $updateValues = [];
                $types = '';

                if (!empty($name) && $name !== $existingRecord['name']) {
                    $updateFields[] = "name = ?";
                    $updateValues[] = $name;
                    $types .= "s";
                }

                if (!empty($graduation_date)) {
                    $graduation_date = convertDateFormat($graduation_date);
                    if ($graduation_date !== $existingRecord['graduation_date']) {
                        $updateFields[] = "graduation_date = ?";
                        $updateValues[] = $graduation_date;
                        $types .= "s";
                    }
                }

                if (!empty($program) && $program !== $existingRecord['program']) {
                    $updateFields[] = "program = ?";
                    $updateValues[] = $program;
                    $types .= "s";
                }

                if (!empty($mobile_no) && $mobile_no !== $existingRecord['mobile_no']) {
                    $updateFields[] = "mobile_no = ?";
                    $updateValues[] = $mobile_no;
                    $types .= "s";
                }

                if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL) && $email !== $existingRecord['email']) {
                    $updateFields[] = "email = ?";
                    $updateValues[] = $email;
                    $types .= "s";
                }

                if (!empty($personal_email) && filter_var($personal_email, FILTER_VALIDATE_EMAIL) && $personal_email !== $existingRecord['personal_email']) {
                    $updateFields[] = "personal_email = ?";
                    $updateValues[] = $personal_email;
                    $types .= "s";
                }

                // Always update extra data if there's something to merge
                if (!empty($mergedExtraData)) {
                    $updateFields[] = "extra = ?";
                    $updateValues[] = json_encode($mergedExtraData, JSON_UNESCAPED_UNICODE);
                    $types .= "s";
                }

                // Only update if there are fields to update
                if (!empty($updateFields)) {
                    $updateValues[] = $student_id;
                    $types .= "s";

                    $sql = "UPDATE alumni SET " . implode(", ", $updateFields) . " WHERE student_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param($types, ...$updateValues);

                    if ($stmt->execute()) {
                        $mergedCount++;
                    } else {
                        $errorCount++;
                    }

                    $stmt->close();
                } else {
                    $skippedCount++; // No changes needed
                }
            } else {
                // Skip - do nothing
                $skippedCount++;
            }
        }

        // Process all pending records regardless of conflict choices
        $pendingProcessed = 0;
        $pendingErrors = 0;

        foreach ($pendingRecords as $record) {
            // Validate before inserting
            $data = $record['data'];
            if (
                empty($data['name']) || empty($data['student_id']) || empty($data['graduation_date']) ||
                empty($data['program']) || empty($data['mobile_no']) || empty($data['email']) ||
                empty($data['personal_email'])
            ) {
                $pendingErrors++;
                continue;
            }

            if (
                !filter_var($data['email'], FILTER_VALIDATE_EMAIL) ||
                !filter_var($data['personal_email'], FILTER_VALIDATE_EMAIL)
            ) {
                $pendingErrors++;
                continue;
            }

            if (insertAlumniRecord(
                $data["name"],
                $data["student_id"],
                $data["graduation_date"],
                $data["program"],
                $data["mobile_no"],
                $data["email"],
                $data["personal_email"],
                $record['extra']
            )) {
                $pendingProcessed++;
            } else {
                $pendingErrors++;
            }
        }

        // Clean up session
        unset($_SESSION['csv_conflicts']);
        unset($_SESSION['csv_pending_records']);

        // Log the action
        log_action("Processed CSV upload: $processedCount overwritten, $mergedCount merged, $pendingProcessed new, $skippedCount skipped, $errorCount errors");

        // Set success message with details
        $message = "Processed CSV records: ";
        $details = [];
        if ($processedCount > 0) $details[] = "$processedCount overwritten";
        if ($mergedCount > 0) $details[] = "$mergedCount merged";
        if ($pendingProcessed > 0) $details[] = "$pendingProcessed new";
        if ($skippedCount > 0) $details[] = "$skippedCount skipped";
        if ($errorCount > 0) $details[] = "$errorCount errors";
        if ($pendingErrors > 0) $details[] = "$pendingErrors failed";

        // Add auto-add info to message
        if (!empty($autoAddInfo)) {
            $details[] = "auto-added " . implode(" and ", $autoAddInfo);
        }

        $_SESSION['success_message'] = $message . implode(", ", $details);
        header("Location: create_alumni.php?success=1&processed=$processedCount&new=$pendingProcessed&skipped=$skippedCount");
        exit;
    }
}

// -------------------------------------------------
// HELPER FUNCTIONS
// -------------------------------------------------
function getAlumniByStudentId($student_id)
{
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM alumni WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }

    $stmt->close();
    return null;
}

function checkForConflicts($student_id, $name)
{
    global $conn;

    // Check by student ID
    $stmt = $conn->prepare("SELECT * FROM alumni WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $record = $result->fetch_assoc();
        return [
            'type' => 'student_id',
            'record' => $record
        ];
    }

    $stmt->close();

    // Check by name (case-insensitive)
    $stmt = $conn->prepare("SELECT * FROM alumni WHERE LOWER(name) = LOWER(?)");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $record = $result->fetch_assoc();
        return [
            'type' => 'name',
            'record' => $record
        ];
    }

    $stmt->close();

    return false;
}

function insertAlumniRecord($name, $student_id, $graduation_date, $program, $mobile_no, $email, $personal_email, $extraFields = [])
{
    global $conn;

    // Convert date format from dd/mm/yyyy to yyyy-mm-dd
    $graduation_date = convertDateFormat($graduation_date);

    $extraAssoc = [];

    // Handle different types of extra fields
    if (!empty($extraFields)) {
        foreach ($extraFields as $key => $value) {
            // Check if this is from manual entry (nested array) or CSV (associative array)
            if (is_array($value) && isset($value['key']) && isset($value['value'])) {
                // Manual entry format: ['key' => 'Role', 'value' => 'Team Member']
                $fieldKey = trim($value['key']);
                $fieldValue = trim($value['value']);
            } else {
                // CSV format: ['LinkedIn' => 'profile_url', 'Company' => 'company_name']
                $fieldKey = trim($key);
                $fieldValue = trim($value);
            }

            // Only include if both key and value are not empty
            if ($fieldKey !== '' && $fieldValue !== '' && $fieldValue !== null) {
                $extraAssoc[$fieldKey] = $fieldValue;
            }
        }
    }

    $extraJson = !empty($extraAssoc) ? json_encode($extraAssoc, JSON_UNESCAPED_UNICODE) : '[]';

    try {
        $stmt = $conn->prepare("
            INSERT INTO alumni 
            (student_id, name, graduation_date, program, mobile_no, email, personal_email, extra)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssssss", $student_id, $name, $graduation_date, $program, $mobile_no, $email, $personal_email, $extraJson);

        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            $stmt->close();
            return false;
        }
    } catch (Exception $e) {
        error_log("Error inserting alumni record: " . $e->getMessage());
        return false;
    }
}

function updateAlumniRecord($data)
{
    global $conn;

    $graduation_date = convertDateFormat($data['graduation_date']);

    $extraAssoc = [];
    foreach ($data['extra_fields'] as $field) {
        $key = trim($field['key'] ?? '');
        $value = trim($field['value'] ?? '');
        if ($key !== '' && $value !== '') {
            $extraAssoc[$key] = $value;
        }
    }
    $extraJson = json_encode($extraAssoc, JSON_UNESCAPED_UNICODE);

    $stmt = $conn->prepare("
        UPDATE alumni 
        SET name = ?, graduation_date = ?, program = ?, mobile_no = ?, 
            email = ?, personal_email = ?, extra = ?
        WHERE student_id = ?
    ");
    $stmt->bind_param(
        "ssssssss",
        $data['name'],
        $graduation_date,
        $data['program'],
        $data['mobile_no'],
        $data['email'],
        $data['personal_email'],
        $extraJson,
        $data['student_id']
    );
    $stmt->execute();
    $stmt->close();
}

function updateAlumniRecordFromCSV($data, $extraData)
{
    global $conn;

    $graduation_date = convertDateFormat($data["graduation_date"]);
    $extraJson = json_encode($extraData, JSON_UNESCAPED_UNICODE);

    $stmt = $conn->prepare("
        UPDATE alumni 
        SET name = ?, graduation_date = ?, program = ?, mobile_no = ?, 
            email = ?, personal_email = ?, extra = ?
        WHERE student_id = ?
    ");
    $stmt->bind_param(
        "ssssssss",
        $data["name"],
        $graduation_date,
        $data["program"],
        $data["mobile_no"],
        $data["email"],
        $data["personal_email"],
        $extraJson,
        $data["student_id"]
    );
    $stmt->execute();
    $stmt->close();
}

function convertDateFormat($date)
{
    if (empty($date)) {
        return '';
    }

    $date = trim($date);

    // Already in yyyy-mm-dd format
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return $date;
    }

    // Handle dd/mm/yyyy
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)) {
        return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
    }

    // Handle mm/dd/yyyy (American format)
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)) {
        // Check if it's likely American format (month > 12)
        if ($matches[1] > 12 && $matches[2] <= 12) {
            // Actually dd/mm/yyyy
            return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }
        return $matches[3] . '-' . $matches[1] . '-' . $matches[2];
    }

    // Handle dd-mm-yyyy
    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $date, $matches)) {
        return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
    }

    // Handle yyyy/mm/dd
    if (preg_match('/^(\d{4})\/(\d{2})\/(\d{2})$/', $date, $matches)) {
        return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
    }

    // Handle Excel serial date numbers (common in CSV exports from Excel)
    if (is_numeric($date) && $date > 25569) {
        $unixTimestamp = ($date - 25569) * 86400;
        return date('Y-m-d', $unixTimestamp);
    }

    // Try to parse with DateTime
    try {
        $dateTime = new DateTime($date);
        return $dateTime->format('Y-m-d');
    } catch (Exception $e) {
        // If all parsing fails, try strtotime as last resort
        $timestamp = strtotime($date);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
    }

    // If we can't parse it, return the original (will cause validation to fail)
    return $date;
}
