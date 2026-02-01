<?php
require_once 'db_config.php';

if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
} else {
    echo "DB connected successfully";
}
?>