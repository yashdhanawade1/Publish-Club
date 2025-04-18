<?php
include 'db_connect.php'; // Make sure this file connects to your database

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"];
    $email = $_POST["email"];
    $password = password_hash($_POST["password"], PASSWORD_BCRYPT); // Hash password for security
    $role = 'reviewer'; // Always set role as reviewer

    // Database connection
    $conn = new mysqli("localhost", "root", "", "publish_club");

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Check if email already exists
    $check_email = "SELECT * FROM users WHERE email = '$email'";
    $result = $conn->query($check_email);
    if ($result->num_rows > 0) {
        echo "This email is already registered.";
    } else {
        // Insert new reviewer
        $sql = "INSERT INTO users (username, email, password, role) VALUES ('$username', '$email', '$password', '$role')";

        if ($conn->query($sql) === TRUE) {
            echo "Reviewer added successfully!";
        } else {
            echo "Error: " . $conn->error;
        }
    }
    $conn->close();
}
?>
