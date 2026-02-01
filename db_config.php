<?php
// Database configuration
$db_config = [
    "servername" => "localhost",
    "username"   => "root",
    "password"   => "",
    "dbname"     => "projectdb"
];

// Create connection
$conn = new mysqli(
    $db_config["servername"],
    $db_config["username"],
    $db_config["password"],
    $db_config["dbname"] 
);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Check connection
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS " . $db_config["dbname"];
if ($conn->query($sql) === TRUE) {
    
} else {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($db_config["dbname"]);

// Table constants - only define if not already defined
if (!defined('TABLE_SURVEYS')) {
    define('TABLE_SURVEYS', 'surveys');
}
if (!defined('TABLE_ALUMNI')) {
    define('TABLE_ALUMNI', 'alumni');
}
if (!defined('SURVEY_RESPONSES_TABLE')) {
    define('SURVEY_RESPONSES_TABLE', 'survey_responses');
}
if (!defined('TABLE_ALUMNI_SURVEY_RESPONSE')) {
    define('TABLE_ALUMNI_SURVEY_RESPONSE', 'alumni_survey_response');
}
if (!defined('TABLE_USERS')) {
    define('TABLE_USERS', 'users');
}
if (!defined('TABLE_COURSES')) {
    define('TABLE_COURSES', 'courses');
}
if (!defined('TABLE_GRADUATION_DATES')) {
    define('TABLE_GRADUATION_DATES', 'graduation_dates');
}
if (!defined('TABLE_LOGS')) {
    define('TABLE_LOGS', 'logs');
}
if (!defined('BASE_URL')) {
    define('BASE_URL', 'https://ictproject.silvergleam.stream/');
}

register_shutdown_function(function () use ($conn) {
    if ($conn) $conn->close();
});
