<?php
session_start();
require_once 'db_config.php';

header("Content-Type: application/json");

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    echo json_encode(["status" => "error", "message" => "db_error"]);
    exit;
}

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

error_log("Login attempt - Email: $email, Password: $password");

// Log the exact POST data received
error_log("POST data: " . print_r($_POST, true));

$stmt = $conn->prepare("SELECT id, email, password, approved, email_verified FROM users WHERE email = ? LIMIT 1");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    echo json_encode(["status" => "error", "message" => "prepare_failed"]);
    exit;
}

$stmt->bind_param("s", $email);
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    echo json_encode(["status" => "error", "message" => "execute_failed"]);
    exit;
}

$result = $stmt->get_result();

if ($result && $result->num_rows === 1) {
    $user = $result->fetch_assoc();

    error_log("User found - ID: " . $user['id'] . ", Email: " . $user['email']);
    error_log("Stored password: " . $user['password']);
    error_log("Password length: " . strlen($user['password']));

    $password_valid = false;

    // Direct comparison
    if ($password === $user['password']) {
        error_log("Password match: Direct comparison");
        $password_valid = true;
    }
    // Trimmed comparison (in case of whitespace)
    elseif (trim($password) === trim($user['password'])) {
        error_log("Password match: After trimming");
        $password_valid = true;
    }
    // Case-insensitive comparison
    elseif (strtolower($password) === strtolower($user['password'])) {
        error_log("Password match: Case-insensitive");
        $password_valid = true;
    }
    // password_verify for hashed passwords
    elseif (password_verify($password, $user['password'])) {
        error_log("Password match: password_verify");
        $password_valid = true;
    }

    error_log("Password validation result: " . ($password_valid ? "VALID" : "INVALID"));

    if (!$password_valid) {
        error_log("Password mismatch. Input: '$password', Stored: '" . $user['password'] . "'");
        echo json_encode(["status" => "invalid"]);
        exit;
    }

    // Not verified
    if (intval($user['email_verified']) !== 1) {
        error_log("User not verified: " . $user['email_verified']);
        echo json_encode(["status" => "not_verified"]);
        exit;
    }

    // Not approved
    if (intval($user['approved']) !== 1) {
        error_log("User not approved: " . $user['approved']);
        echo json_encode(["status" => "not_approved"]);
        exit;
    }

    // Success - set session
    $_SESSION['user']['id'] = $user['id'];
    $_SESSION['user']['email'] = $user['email'];
    $_SESSION['logged_in'] = true;

    error_log("Login successful for user: " . $user['email']);
    echo json_encode(["status" => "success"]);
    exit;
} else {
    error_log("User not found for email: $email");
    echo json_encode(["status" => "not_found"]);
}

$stmt->close();
$conn->close();
