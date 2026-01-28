<?php
require_once 'db_config.php';

function log_action($action)
{
    global $conn;

    if (!isset($_SESSION['user']['id'])) return;
    $uid = intval($_SESSION['user']['id']);
    error_log("LOG DEBUG: Attempting to log action: '$action' for user ID: $uid");

    $stmt = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
    if (!$stmt) {
        error_log("LOG ERROR: Prepare failed: " . $conn->error);
        return;
    }

    $stmt->bind_param("is", $uid, $action);

    if ($stmt->execute()) {
        error_log("LOG DEBUG: Successfully logged action: '$action'");
    } else {
        error_log("LOG ERROR: Execute failed: " . $stmt->error);
    }

    $stmt->close();
}
