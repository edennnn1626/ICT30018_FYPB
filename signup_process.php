<?php
require_once 'db_config.php';

$email = $_POST['email'] ?? '';
$password_raw = $_POST['password'] ?? '';
$errors = [];

// Password validation
if (empty($password_raw)) {
    $errors['password'] = "Password is required.";
} elseif (strlen($password_raw) < 8) {
    $errors['password'] = "Password must be at least 8 characters long.";
} elseif (!preg_match('/[0-9]/', $password_raw)) {
    $errors['password'] = "Password must contain at least one number.";
} elseif (!preg_match('/[\W_]/', $password_raw)) {
    $errors['password'] = "Password must contain at least one symbol.";
}

if (!empty($errors)) {
    echo "invalid:" . $errors['password'];
    exit;
}

// Email validation
if (!$email) {
    echo "error";
    exit;
}

$password = password_hash($password_raw, PASSWORD_DEFAULT);

// Check if email exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo "exists";
    $stmt->close();
    exit;
}
$stmt->close();

// Create new user with verification token
$token = bin2hex(random_bytes(16));
$stmt = $conn->prepare("INSERT INTO users (email, password, approved, email_verified, verification_token) VALUES (?, ?, 0, 0, ?)");
$stmt->bind_param("sss", $email, $password, $token);

if ($stmt->execute()) {

    // Send verification email
    require_once 'mailer.php';
    $verifyLink = BASE_URL . "verify_email.php?token=" . urlencode($token);
    $subject = "Verify Your Email - Alumni Survey";
    $body = "Thank you for signing up!\n\nPlease click the link below to verify your email:\n$verifyLink\n\nIf you did not create an account, ignore this email.";

    send_email($email, $subject, $body);

    // Return a new status specifically for pending verification
    echo "verify_pending";  

} else {
    echo "error";
}
$stmt->close();
?>
