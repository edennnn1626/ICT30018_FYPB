<?php
require_once 'db_config.php';

$token = $_POST['token'] ?? '';
$password = $_POST['password'] ?? '';

if (!$token || !$password) {
    echo "Invalid request";
    exit;
}

// Validate password (same rules as signup)
if (strlen($password) < 8 || !preg_match('/[0-9]/', $password) || !preg_match('/[\W_]/', $password)) {
    echo "Password must be at least 8 characters, include a number and a symbol";
    exit;
}

// Find user by token
$stmt = $conn->prepare("SELECT id, reset_expires FROM users WHERE reset_token = ? LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    echo "Invalid or expired token";
    exit;
}

$user = $result->fetch_assoc();
if (strtotime($user['reset_expires']) < time()) {
    echo "Token has expired";
    exit;
}

// Update password
$hashed = password_hash($password, PASSWORD_DEFAULT);
$update = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
$update->bind_param("si", $hashed, $user['id']);
if ($update->execute()) {
    echo "Password updated successfully. You can now <a href='login.html'>login</a>.";
} else {
    echo "Error updating password";
}
$update->close();
?>
