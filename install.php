<?php
// Installation script - Run this once to set up the database

require_once 'includes/config.php';

echo "<h1>Dental Clinic Management System - Installation</h1>";

try {
    // Create connection without database
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Create database
    $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color:green'>✓ Database created successfully</p>";
    } else {
        echo "<p style='color:red'>✗ Error creating database: " . $conn->error . "</p>";
    }

    // Select database
    $conn->select_db(DB_NAME);

    // Read and execute SQL file
    $sql_file = file_get_contents('database.sql');
    if ($conn->multi_query($sql_file)) {
        echo "<p style='color:green'>✓ Tables created successfully</p>";

        // Clear multi_query results
        while ($conn->next_result()) {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        }
    } else {
        echo "<p style='color:red'>✗ Error creating tables: " . $conn->error . "</p>";
    }

    // Create admin user
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (username, email, password_hash, full_name, role) 
            VALUES ('admin', 'admin@clinic.com', '$admin_password', 'Administrator', 'doctor')
            ON DUPLICATE KEY UPDATE id=id";

    if ($conn->query($sql) === TRUE) {
        echo "<p style='color:green'>✓ Admin user created (username: admin, password: admin123)</p>";
    }

    // Create sample doctor
    $doctor_password = password_hash('doctor123', PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (username, email, password_hash, full_name, role) 
            VALUES ('doctor', 'doctor@clinic.com', '$doctor_password', 'Dr. John Smith', 'doctor')
            ON DUPLICATE KEY UPDATE id=id";

    if ($conn->query($sql) === TRUE) {
        echo "<p style='color:green'>✓ Doctor user created (username: doctor, password: doctor123)</p>";
    }

    // Create sample assistant
    $assistant_password = password_hash('assistant123', PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (username, email, password_hash, full_name, role) 
            VALUES ('assistant', 'assistant@clinic.com', '$assistant_password', 'Jane Doe', 'assistant')
            ON DUPLICATE KEY UPDATE id=id";

    if ($conn->query($sql) === TRUE) {
        echo "<p style='color:green'>✓ Assistant user created (username: assistant, password: assistant123)</p>";
    }

    echo "<hr>";
    echo "<h2>Installation Complete!</h2>";
    echo "<p>You can now <a href='login.php'>login to the system</a></p>";
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

$conn->close();
