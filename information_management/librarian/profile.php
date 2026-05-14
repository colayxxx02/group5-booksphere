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
        /* Profile Header Card */
        .profile-header-card {
            background:linear-gradient(135deg, #5b7cde 0%, #8b9dc3 100%);
            border-radius:12px;
            padding:24px;
            margin-bottom:20px;
            display:flex;
            align-items:center;
            gap:20px;
            box-shadow:0 2px 12px rgba(91,124,222,0.15);
            position:relative;
            max-width:800px;
            margin-left:auto;
            margin-right:auto;
        }

        .profile-avatar-large {
            width:80px;
            height:80px;
            border-radius:50%;
            background:#fff;
            display:flex;
            align-items:center;
            justify-content:center;
            box-shadow:0 2px 8px rgba(0,0,0,0.1);
            flex-shrink:0;
        }

        .avatar-initials {
            font-size:32px;
            font-weight:700;
            color:#5b7cde;
        }

        .profile-header-info {
            flex:1;
        }

        .profile-name {
            font-size:22px;
            font-weight:700;
            color:#fff;
            margin:0 0 6px 0;
        }

        .profile-email {
            color:rgba(255,255,255,0.95);
            font-size:14px;
            margin:0 0 10px 0;
            display:flex;
            align-items:center;
            gap:6px;
        }

        .profile-email .material-symbols-outlined {
            font-size:16px;
        }

        .profile-badges {
            display:flex;
            gap:6px;
            flex-wrap:wrap;
        }

        .profile-badges .badge {
            background:rgba(255,255,255,0.2);
            color:#fff;
            border:1px solid rgba(255,255,255,0.25);
            backdrop-filter:blur(10px);
            font-size:12px;
            padding:4px 10px;
        }

        .profile-actions-header {
            display:flex;
            gap:8px;
        }

        .btn-icon-action {
            width:40px;
            height:40px;
            border-radius:10px;
            background:rgba(255,255,255,0.2);
            border:1px solid rgba(255,255,255,0.25);
            color:#fff;
            display:flex;
            align-items:center;
            justify-content:center;
            cursor:pointer;
            transition:all 0.2s;
            backdrop-filter:blur(10px);
        }

        .btn-icon-action:hover {
            background:rgba(255,255,255,0.3);
            transform:translateY(-1px);
        }

        .btn-icon-action .material-symbols-outlined {
            font-size:20px;
        }

        /* Modern Card */
        .modern-card {
            background:#fff;
            border-radius:12px;
            box-shadow:0 1px 6px rgba(0,0,0,0.06);
            overflow:hidden;
            margin-bottom:16px;
            max-width:800px;
            margin-left:auto;
            margin-right:auto;
        }

        .card-header {
            background:#f5f7fa;
            padding:16px 20px;
            border-bottom:1px solid #e8ecf1;
        }

        .card-header h3 {
            margin:0;
            font-size:16px;
            font-weight:700;
            color:#2c3e50;
        }

        .card-body {
            padding:20px;
        }

        .info-grid {
            display:grid;
            grid-template-columns:repeat(2,1fr);
            gap:16px;
            margin-bottom:0;
        }

        .info-item {
            display:flex;
            flex-direction:column;
            gap:5px;
        }

        .info-label {
            font-size:12px;
            color:#7f8c8d;
            font-weight:600;
            display:flex;
            align-items:center;
            gap:5px;
        }

        .info-label .material-symbols-outlined {
            font-size:15px;
            color:#5b7cde;
        }

        .info-value {
            font-size:14px;
            color:#2c3e50;
            font-weight:600;
        }

        /* Form Groups Modern */
        .form-group-modern {
            margin-bottom:16px;
        }

        .form-group-modern label {
            display:flex;
            align-items:center;
            gap:6px;
            font-size:13px;
            font-weight:600;
            color:#2c3e50;
            margin-bottom:6px;
        }

        .form-group-modern label .material-symbols-outlined {
            font-size:16px;
            color:#5b7cde;
        }

        .form-group-modern input {
            width:100%;
            padding:10px 12px;
            border:1px solid #dfe4ea;
            border-radius:8px;
            font-size:14px;
            font-family:inherit;
            transition:all 0.2s;
            background:#f8f9fa;
        }

        .form-group-modern input:focus {
            outline:none;
            border-color:#5b7cde;
            background:#fff;
            box-shadow:0 0 0 3px rgba(91,124,222,0.08);
        }

        .button-group-modern {
            display:flex;
            gap:10px;
            justify-content:flex-end;
            margin-top:20px;
        }

        .btn-save-modern {
            padding:10px 20px;
            background:#5b7cde;
            color:#fff;
            border:none;
            border-radius:8px;
            font-size:14px;
            font-weight:600;
            cursor:pointer;
            transition:all 0.2s;
            display:inline-flex;
            align-items:center;
            gap:6px;
        }

        .btn-save-modern:hover {
            background:#4a6bc9;
            transform:translateY(-1px);
            box-shadow:0 3px 10px rgba(91,124,222,0.25);
        }

        .btn-save-modern .material-symbols-outlined {
            font-size:18px;
        }

        /* Responsive */
        @media (max-width:768px) {
            .profile-header-card {
                flex-direction:column;
                text-align:center;
                padding:20px;
            }
            .profile-header-info {
                text-align:center;
            }
            .profile-badges {
                justify-content:center;
            }
            .profile-actions-header {
                justify-content:center;
            }
            .info-grid {
                grid-template-columns:1fr;
            }
            .button-group-modern {
                flex-direction:column;
            }
            .btn-save-modern {
                width:100%;
                justify-content:center;
            }
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

            <!-- ALERTS -->
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <span class="material-symbols-outlined">error</span>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <span class="material-symbols-outlined">check_circle</span>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <!-- PROFILE HEADER CARD -->
            <div class="profile-header-card">
                <div class="profile-avatar-large">
                    <span class="avatar-initials"><?= strtoupper(substr($user['fullname'], 0, 1)) ?></span>
                </div>
                <div class="profile-header-info">
                    <h2 class="profile-name"><?= htmlspecialchars($user['fullname']) ?></h2>
                    <p class="profile-email"><span class="material-symbols-outlined">email</span> <?= htmlspecialchars($user['email']) ?></p>
                    <div class="profile-badges">
                        <span class="badge badge-active"><span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;">admin_panel_settings</span> Librarian</span>
                        <span class="badge badge-returned"><span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;">verified</span> Staff Member</span>
                    </div>
                </div>
                <div class="profile-actions-header">
                    <button class="btn-icon-action" onclick="scrollToSection('editSection')" title="Edit Profile">
                        <span class="material-symbols-outlined">edit</span>
                    </button>
                    <button class="btn-icon-action" onclick="scrollToSection('passwordSection')" title="Change Password">
                        <span class="material-symbols-outlined">lock</span>
                    </button>
                </div>
            </div>

            <!-- ACCOUNT INFORMATION CARD -->
            <div class="modern-card">
                <div class="card-header">
                    <h3><span class="material-symbols-outlined" style="vertical-align:middle;font-size:22px;color:#4e6ef2;">badge</span> Account Information</h3>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label"><span class="material-symbols-outlined">tag</span> User ID</span>
                            <span class="info-value">#<?= $user['user_id'] ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><span class="material-symbols-outlined">work</span> Role</span>
                            <span class="info-value"><span class="badge badge-active">Librarian</span></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><span class="material-symbols-outlined">person</span> Full Name</span>
                            <span class="info-value"><?= htmlspecialchars($user['fullname']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><span class="material-symbols-outlined">email</span> Email Address</span>
                            <span class="info-value"><?= htmlspecialchars($user['email']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><span class="material-symbols-outlined">calendar_today</span> Member Since</span>
                            <span class="info-value"><?= date('F d, Y', strtotime($user['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- EDIT PROFILE CARD -->
            <div class="modern-card" id="editSection">
                <div class="card-header">
                    <h3><span class="material-symbols-outlined" style="vertical-align:middle;font-size:22px;color:#4e6ef2;">edit</span> Edit Profile</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group-modern">
                            <label for="fullname"><span class="material-symbols-outlined">person</span> Full Name</label>
                            <input 
                                type="text" 
                                id="fullname"
                                name="fullname" 
                                value="<?= htmlspecialchars($user['fullname']) ?>"
                                required>
                        </div>

                        <div class="form-group-modern">
                            <label for="email"><span class="material-symbols-outlined">email</span> Email Address</label>
                            <input 
                                type="email" 
                                id="email"
                                name="email" 
                                value="<?= htmlspecialchars($user['email']) ?>"
                                required>
                        </div>

                        <div class="button-group-modern">
                            <button type="submit" name="update_profile" class="btn-save-modern">
                                <span class="material-symbols-outlined">save</span> Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- CHANGE PASSWORD CARD -->
            <div class="modern-card" id="passwordSection">
                <div class="card-header">
                    <h3><span class="material-symbols-outlined" style="vertical-align:middle;font-size:22px;color:#4e6ef2;">lock</span> Change Password</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group-modern">
                            <label for="current_password"><span class="material-symbols-outlined">lock_open</span> Current Password</label>
                            <input 
                                type="password" 
                                id="current_password"
                                name="current_password" 
                                placeholder="Enter your current password"
                                required>
                        </div>

                        <div class="form-group-modern">
                            <label for="new_password"><span class="material-symbols-outlined">vpn_key</span> New Password</label>
                            <input 
                                type="password" 
                                id="new_password"
                                name="new_password" 
                                placeholder="Enter new password (min. 6 characters)"
                                minlength="6"
                                required>
                        </div>

                        <div class="form-group-modern">
                            <label for="confirm_password"><span class="material-symbols-outlined">check_circle</span> Confirm New Password</label>
                            <input 
                                type="password" 
                                id="confirm_password"
                                name="confirm_password" 
                                placeholder="Confirm new password"
                                minlength="6"
                                required>
                        </div>

                        <div class="button-group-modern">
                            <button type="submit" name="change_password" class="btn-save-modern">
                                <span class="material-symbols-outlined">lock_reset</span> Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert, .error, .success');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(function() {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000); // 5 seconds
            });
        });

        // Smooth scroll to section
        function scrollToSection(sectionId) {
            const section = document.getElementById(sectionId);
            if (section) {
                section.scrollIntoView({ behavior: 'smooth', block: 'start' });
                // Add highlight effect
                section.style.transition = 'box-shadow 0.3s ease';
                section.style.boxShadow = '0 0 0 3px rgba(78,110,242,0.3)';
                setTimeout(() => {
                    section.style.boxShadow = '';
                }, 2000);
            }
        }
    </script>

</body>
</html>