<?php
require_once "auth.php";
require_once "db_config.php";
require_once "log_action.php";
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $titleRes = $conn->query("SELECT title FROM " . TABLE_SURVEYS . " WHERE id = $id");
    $title = ($titleRes && $titleRes->num_rows > 0)
            ? $titleRes->fetch_assoc()['title']
            : "Unknown Survey";

    $conn->query("DELETE FROM " . TABLE_SURVEYS . " WHERE id = $id");

    // Log the deletion action
    log_action("Deleted survey \"$title\"");

    echo json_encode(["success" => true]);
    exit;
}

echo json_encode(["success" => false, "message" => "Invalid request"]);
exit;
?>
