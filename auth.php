<?php
require_once 'database.php'; // provides $conn
session_start();

/**
 * Require login and ensure the logged-in user is approved.
 * Redirects to login.html when not approved.
 */
function require_login() {
    if (!isset($_SESSION['user']['id'])) {
        header("Location: login.html");
        exit;
    }
    global $conn;
    $userId = intval($_SESSION['user']['id']);
    if ($userId <= 0) {
        session_unset();
        session_destroy();
        header("Location: login.html");
        exit;
    }

    $stmt = $conn->prepare("SELECT approved FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) {
        // If DB error, force logout
        session_unset();
        session_destroy();
        header("Location: login.html");
        exit;
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows !== 1) {
        $stmt->close();
        session_unset();
        session_destroy();
        header("Location: login.html");
        exit;
    }
    $row = $res->fetch_assoc();
    $stmt->close();

    if (intval($row['approved']) !== 1) {
        // User not approved -> destroy session and redirect with flag
        session_unset();
        session_destroy();
        header("Location: login.html?not_approved=1");
        exit;
    }
}
?>