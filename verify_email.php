<?php
require_once 'db_config.php';

$token = $_GET['token'] ?? '';

if (!$token) {
    die("Invalid request.");
}

$stmt = $conn->prepare("SELECT id FROM users WHERE verification_token = ? LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {

    // Mark email as verified
    $update = $conn->prepare("UPDATE users SET email_verified = 1, verification_token = NULL WHERE verification_token = ? LIMIT 1");
    $update->bind_param("s", $token);
    $update->execute();

    // Redirect to login page
    header("Location: login.html?verified=1");
    exit();

} else {
    // Invalid token
    header("Location: login.html?verified=0");
    exit();
}
?>
