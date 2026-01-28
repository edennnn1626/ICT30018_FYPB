<?php
require_once 'db_config.php';
require_once 'mailer.php';

$email = $_POST['email'] ?? '';
if (!$email) {
    echo "Invalid email";
    exit;
}

// Check if user exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows === 1) {
    $user = $result->fetch_assoc();
    $userId = $user['id'];

    // Generate a secure token
    $token = bin2hex(random_bytes(16));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour')); // valid for 1 hour

    // Store token and expiry
    $update = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
    $update->bind_param("ssi", $token, $expires, $userId);
    $update->execute();
    $update->close();

    // Send reset link via email
    $resetLink = BASE_URL . "reset_password.php?token=" . urlencode($token);
    $subject = "Reset Your Alumni Survey Password";
    $body = "Hello,\n\nClick the link below to reset your password (valid for 1 hour):\n$resetLink\n\nIf you did not request this, ignore this email.";

    send_email($email, $subject, $body);

    echo "A password reset link has been sent to your email.";
} else {
    echo "If the email exists in our system, a reset link has been sent.";
}
?>
