<?php
require_once 'db_config.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');

if (strlen($q) < 3) {
    echo json_encode([]);
    exit;
}

$sql = "
    SELECT 
        student_id,
        name,
        email,
        personal_email
    FROM alumni
    WHERE 
        name LIKE CONCAT('%', ?, '%')
        OR email LIKE CONCAT('%', ?, '%')
        OR personal_email LIKE CONCAT('%', ?, '%')
    ORDER BY name ASC
    LIMIT 15
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $q, $q, $q);
$stmt->execute();
$result = $stmt->get_result();

$alumni = [];
while ($row = $result->fetch_assoc()) {
    // Guarantee empty strings not nulls
    $row['email'] = $row['email'] ?? "";
    $row['personal_email'] = $row['personal_email'] ?? "";
    $alumni[] = $row;
}

echo json_encode($alumni);