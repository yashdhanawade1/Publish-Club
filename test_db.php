<?php
require_once 'config/config.php';

try {
    // First try to connect to MySQL server
    $pdo = new PDO(
        "mysql:host=" . DB_HOST,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "✓ Connected to MySQL server successfully<br>";
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    echo "✓ Database '" . DB_NAME . "' created or already exists<br>";
    
    // Connect to the specific database
    $pdo = db_connect();
    echo "✓ Connected to database '" . DB_NAME . "' successfully<br>";
    
    // Read and execute the schema.sql file
    $schema = file_get_contents(__DIR__ . '/database/schema.sql');
    $pdo->exec($schema);
    echo "✓ Database schema created successfully<br>";
    
    echo "<br>Database setup complete! You can now:<br>";
    echo "1. <a href='create_admin.php'>Create an admin user</a><br>";
    echo "2. <a href='register.php'>Register a regular user</a><br>";
    echo "3. <a href='login.php'>Login to your account</a>";
    
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
