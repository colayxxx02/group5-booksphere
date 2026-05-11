<?php
session_start();
include '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'librarian') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = "";
$success = "";

// Fetch librarian info
$user = $conn->query("SELECT user_id, fullname, email, role, created_at FROM users WHERE user_id='$user_id'")->fetch_assoc();

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    
    // Validation
    if (empty($fullname) || empty($email)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Check if email is already taken by another user
        $check = $conn->query("SELECT user_id FROM users WHERE email='$email' AND user_id != '$user_id'");
        if ($check->num_rows > 0) {
            $error = "Email already registered by another user!";
        } else {
            $fullname = $conn->real_escape_string($fullname);
            $email = $conn->real_escape_string($email);
            
            $update = $conn->query("UPDATE users SET fullname='$fullname', email='$email' WHERE user_id='$user_id'");
            
            if ($update) {
                $_SESSION['fullname'] = $fullname;
                $success = "Profile updated successfully!";
                $user['fullname'] = $fullname;
                $user['email'] = $email;
            } else {
                $error = "Error updating profile: " . $conn->error;
            }
        }
    }
}

// Handle password change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    $current_user = $conn->query("SELECT password FROM users WHERE user_id='$user_id'")->fetch_assoc();
    
    if (!password_verify($current_password, $current_user['password'])) {
        $error = "Current password is incorrect.";
    } elseif (empty($new_password) || empty($confirm_password)) {
        $error = "All password fields are required.";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update = $conn->query("UPDATE users SET password='$hashed_password' WHERE user_id='$user_id'");
        
        if ($update) {
            $success = "Password changed successfully!";
        } else {
            $error = "Error changing password: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile -  BooksPhere</title>
    <link rel="stylesheet" href="../librarian/style.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <style>
        .profile-container {
            max-width: 600px;
            margin: 0 auto;
        }

        .profile-section {
            background: #fff;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .profile-section h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #1a1a2e;
            font-size: 18px;
            border-bottom: 2px solid #4e6ef2;
            padding-bottom: 10px;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4e6ef2, #9b59b6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin: 0 auto 15px;
            color: white;
        }

        .profile-info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .profile-info-row:last-child {
            border-bottom: none;
        }

        .profile-info-label {
            font-weight: 600;
            color: #666;
        }

        .profile-info-value {
            color: #333;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .form-group input:focus {
            outline: none;
            border-color: #4e6ef2;
            box-shadow: 0 0 0 3px rgba(78, 110, 242, 0.1);
        }

        .button-group {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-submit {
            background: #4e6ef2;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: background 0.3s;
        }

        .btn-submit:hover {
            background: #3d54d1;
        }

        .btn-cancel {
            background: #e0e0e0;
            color: #333;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
        }

        .btn-cancel:hover {
            background: #ccc;
        }

        .error {
            background: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 16px;
            border-left: 4px solid #c62828;
        }

        .success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 16px;
            border-left: 4px solid #2e7d32;
        }
    </style>
</head>
<body class="dashboard-page">

    <!-- TITLEBAR -->
    <div class="titlebar">
        <div class="dots">
            <span class="dot-red"></span>
            <span class="dot-yellow"></span>
            <span class="dot-green"></span>
        </div>
        <span class="title">BookShare Library System</span>
    </div>

    <!-- APP SHELL -->
    <div class="app-shell">

        <!-- SIDEBAR -->
        <div class="sidebar">
            <div class="logo"><span class="material-symbols-outlined">menu_book</span> BookShare</div>
            <nav>
                <a href="dashboard.php">
                    <span class="material-symbols-outlined">dashboard</span> Dashboard
                </a>
                <a href="books.php">
                    <span class="material-symbols-outlined">book</span> Books
                </a>
                <a href="transactions.php">
                    <span class="material-symbols-outlined">sync_alt</span> Transactions
                </a>
                <a href="overdue.php">
                    <span class="material-symbols-outlined">warning</span> Overdue
                </a>
                <a href="maintenance.php">
                    <span class="material-symbols-outlined">build</span> Maintenance
                </a>
                <a href="profile.php" class="active">
                    <span class="material-symbols-outlined">person</span> My Profile
                </a>
            </nav>
            <div class="sidebar-footer">
                <form method="POST" action="../logout.php">
                    <button type="submit"><span class="material-symbols-outlined">logout</span> Logout</button>
                </form>
            </div>
        </div>

        <!-- MAIN CONTENT -->
        <div class="main-content">

            <!-- WELCOME -->
            <div class="welcome-bar">
                <h2>My Profile</h2>
                <p>Manage your account information and security</p>
            </div>

            <div class="profile-container">

                <!-- PROFILE HEADER -->
                <div class="profile-section profile-header">
                    <div class="profile-avatar"><span class="material-symbols-outlined" style="font-size:48px;">person</span></div>
                    <h2><?= htmlspecialchars($user['fullname']) ?></h2>
                    <p style="color: #666; margin: 0;">Librarian</p>
                </div>

                <!-- ALERTS -->
                <?php if ($error): ?>
                    <div class="error">
                        <strong>❌ Error:</strong> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="success">
                        <strong><span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;color:#4caf50;">check_circle</span> Success:</strong> <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <!-- PROFILE INFORMATION -->
                <div class="profile-section">
                    <h3>Profile Information</h3>
                    <div class="profile-info-row">
                        <span class="profile-info-label">User ID:</span>
                        <span class="profile-info-value">#<?= $user['user_id'] ?></span>
                    </div>
                    <div class="profile-info-row">
                        <span class="profile-info-label">Role:</span>
                        <span class="profile-info-value">👨‍💼 Librarian</span>
                    </div>
                    <div class="profile-info-row">
                        <span class="profile-info-label">Member Since:</span>
                        <span class="profile-info-value"><?= date('F d, Y', strtotime($user['created_at'])) ?></span>
                    </div>
                </div>

                <!-- EDIT PROFILE FORM -->
                <div class="profile-section">
                    <h3>Edit Profile</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label for="fullname">Full Name</label>
                            <input 
                                type="text" 
                                id="fullname"
                                name="fullname" 
                                value="<?= htmlspecialchars($user['fullname']) ?>"
                                required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input 
                                type="email" 
                                id="email"
                                name="email" 
                                value="<?= htmlspecialchars($user['email']) ?>"
                                required>
                        </div>

                        <div class="button-group">
                            <a href="dashboard.php" class="btn-cancel">Cancel</a>
                            <button type="submit" name="update_profile" class="btn-submit">Update Profile</button>
                        </div>
                    </form>
                </div>

                <!-- CHANGE PASSWORD FORM -->
                <div class="profile-section">
                    <h3>Change Password</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input 
                                type="password" 
                                id="current_password"
                                name="current_password" 
                                placeholder="Enter your current password"
                                required>
                        </div>

                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input 
                                type="password" 
                                id="new_password"
                                name="new_password" 
                                placeholder="Enter new password (min. 6 characters)"
                                minlength="6"
                                required>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input 
                                type="password" 
                                id="confirm_password"
                                name="confirm_password" 
                                placeholder="Confirm new password"
                                minlength="6"
                                required>
                        </div>

                        <div class="button-group">
                            <a href="dashboard.php" class="btn-cancel">Cancel</a>
                            <button type="submit" name="change_password" class="btn-submit">Change Password</button>
                        </div>
                    </form>
                </div>

            </div>

        </div>
    </div>

</body>
</html>