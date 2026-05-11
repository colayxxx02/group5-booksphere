<?php
session_start();

// Kung naka-login na, i-redirect dayon
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'librarian') {
        header("Location: librarian/dashboard.php");
    } else {
        header("Location: member/dashboard.php");
    }
    exit();
}

// PROCESS SIGNUP
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['signup'])) {
    include 'db.php';
    
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = 'member'; // Always register as member
    
    // Validation
    if (empty($fullname) || empty($email) || empty($password) || empty($confirm_password)) {
        header("Location: index.php?signup_error=" . urlencode("All fields are required."));
        exit();
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: index.php?signup_error=" . urlencode("Please enter a valid email address."));
        exit();
    } elseif (strlen($password) < 6) {
        header("Location: index.php?signup_error=" . urlencode("Password must be at least 6 characters long."));
        exit();
    } elseif ($password !== $confirm_password) {
        header("Location: index.php?signup_error=" . urlencode("Passwords do not match."));
        exit();
    } else {
        // Check if email already exists
        $check = $conn->query("SELECT user_id FROM users WHERE email='$email'");
        if ($check->num_rows > 0) {
            header("Location: index.php?signup_error=" . urlencode("Email already registered!"));
            exit();
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Escape strings to prevent SQL injection
            $fullname = $conn->real_escape_string($fullname);
            $email = $conn->real_escape_string($email);
            
            // Get next user_id
            $result = $conn->query("SELECT MAX(user_id) as max_id FROM users");
            $row = $result->fetch_assoc();
            $user_id = ($row['max_id'] ?? 0) + 1;
            
            // Insert new user
            $sql = "INSERT INTO users (user_id, fullname, email, password, role, is_on_hold, hold_until) 
                    VALUES ('$user_id', '$fullname', '$email', '$hashed_password', '$role', 0, NULL)";
            
            if ($conn->query($sql)) {
                header("Location: index.php?signup_success=" . urlencode("Account created successfully! You can now login."));
                exit();
            } else {
                header("Location: index.php?signup_error=" . urlencode("Error creating account. Please try again."));
                exit();
            }
        }
    }
}

// If accessed directly, redirect to index
header("Location: index.php");
exit();
?>
