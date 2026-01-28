<?php
include 'db_config.php';

// SQL statements to create normalized tables
$sql_statements = [

    // Create surveys table
    "CREATE TABLE IF NOT EXISTS surveys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL,
        form_json TEXT,
        expiry_date TIMESTAMP NULL,
        token VARCHAR(256),
        require_alumni_verification TINYINT(1) DEFAULT 0,
        allowed_courses VARCHAR(255) DEFAULT NULL,
        allowed_graduation_dates VARCHAR(255) DEFAULT NULL
    )",

    // Create alumni response check table
    "CREATE TABLE IF NOT EXISTS alumni_survey_response (
        id INT AUTO_INCREMENT PRIMARY KEY,
        survey_id INT,
        survey_title VARCHAR(255),
        alumni_check MEDIUMTEXT DEFAULT NULL,
        FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
        UNIQUE KEY survey_alumni_unique (survey_id)
    )",

    // Create users table
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(100) UNIQUE,
        password VARCHAR(255),
        approved TINYINT(1) DEFAULT 0,
        email_verified TINYINT(1) DEFAULT 0,
        verification_token VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    // Create survey_responses table
    "CREATE TABLE IF NOT EXISTS survey_responses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        survey_id INT,
        answers TEXT,
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    // Create alumni table
    "CREATE TABLE IF NOT EXISTS alumni (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255),
        student_id VARCHAR(100),
        graduation_date DATE,
        program VARCHAR(255),
        mobile_no VARCHAR(50),
        email VARCHAR(100),
        personal_email VARCHAR(100),
        extra JSON
    )",

    // Create graduation_dates table
    "CREATE TABLE IF NOT EXISTS graduation_dates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        date DATE UNIQUE,
        label VARCHAR(255)
    )",

    // Create Courses Table
    "CREATE TABLE IF NOT EXISTS courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) UNIQUE
    )",

    // Create logs table
    "CREATE TABLE IF NOT EXISTS logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
];

// Execute each SQL statement
foreach ($sql_statements as $sql) {
    if ($conn->query($sql) === TRUE) {
        // echo "Table created successfully.<br>";
    } else {
        error_log("Error creating table: " . $conn->error);
    }
}
