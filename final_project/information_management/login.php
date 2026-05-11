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

// PROCESS LOGIN
if (isset($_POST['login'])) {
    include 'db.php';
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    $user = $conn->query("SELECT * FROM users WHERE email='$email'")->fetch_assoc();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['role'] = $user['role'];
        
        if ($user['role'] == 'librarian') {
            header("Location: librarian/dashboard.php");
        } else {
            header("Location: member/dashboard.php");
        }
        exit();
    } else {
        header("Location: index.php?login_error=" . urlencode("Invalid email or password."));
        exit();
    }
}

// If accessed directly, redirect to index
header("Location: index.php");
exit();
?>
