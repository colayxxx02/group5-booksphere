<?php
session_start();
include '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'member') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = "";
$error = "";

// UPDATE PROFILE
if (isset($_POST['update_profile'])) {
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];

    $check = $conn->query("SELECT * FROM users WHERE email='$email' AND user_id != '$user_id'");
    if ($check->num_rows > 0) {
        $error = "Email already used by another account!";
    } else {
        $conn->query("UPDATE users SET fullname='$fullname', email='$email' WHERE user_id='$user_id'");
        $_SESSION['fullname'] = $fullname;
        $success = "Profile updated successfully!";
    }
}

// CHANGE PASSWORD
if (isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    $user = $conn->query("SELECT * FROM users WHERE user_id='$user_id'")->fetch_assoc();

    if (!password_verify($current, $user['password'])) {
        $error = "Current password is incorrect!";
    } elseif ($new != $confirm) {
        $error = "New passwords do not match!";
    } elseif (strlen($new) < 6) {
        $error = "Password must be at least 6 characters!";
    } else {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password='$hashed' WHERE user_id='$user_id'");
        $success = "Password changed successfully!";
    }
}

// GET USER INFO
$user = $conn->query("SELECT * FROM users WHERE user_id='$user_id'")->fetch_assoc();

// COUNTS
$total_borrowed = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE user_id='$user_id' AND return_date IS NULL AND due_date >= CURDATE()")->fetch_assoc()['count'];
$total_returned = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE user_id='$user_id' AND return_date IS NOT NULL")->fetch_assoc()['count'];
$total_overdue = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE user_id='$user_id' AND return_date IS NULL AND due_date < CURDATE()")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile - BookShare</title>
    <link rel="stylesheet" href="../member/style.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
</head>
<body class="dashboard-page">

    <!-- TITLEBAR -->
    <div class="titlebar">
        <div class="dots">
            <span class="dot-red"></span>
            <span class="dot-yellow"></span>
            <span class="dot-green"></span>
        </div>
        <div class="title"><span class="material-symbols-outlined" style="vertical-align:middle;">menu_book</span> BookShare - Member Dashboard</div>
    </div>

    <!-- APP SHELL WITH SIDEBAR -->
    <div class="app-shell">
        <!-- SIDEBAR -->
        <div class="sidebar">
            <div class="logo"><span class="material-symbols-outlined">menu_book</span> BookShare</div>
            <nav>
                <a href="dashboard.php" class="nav-link">
                    <span class="material-symbols-outlined">dashboard</span>
                    Dashboard
                </a>
                <a href="catalog.php" class="nav-link">
                    <span class="material-symbols-outlined">book</span>
                    Browse Books
                </a>
                <a href="history.php" class="nav-link">
                    <span class="material-symbols-outlined">history</span>
                    My History
                </a>
                <a href="profile.php" class="nav-link active">
                    <span class="material-symbols-outlined">person</span>
                    My Profile
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
            <div class="page-header">
                <div>
                    <div class="page-title">My Profile</div>
                    <div class="page-subtitle">Manage your account information and password.</div>
                </div>
            </div>

            <!-- ALERTS -->
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <span class="material-symbols-outlined">check_circle</span>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <span class="material-symbols-outlined">error</span>
                    <span><?= htmlspecialchars($error) ?></span>
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
                        <span class="badge badge-active"><span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;">verified</span> <?= ucfirst($user['role']) ?></span>
                        <?php if ($user['is_on_hold'] == 1): ?>
                            <span class="badge badge-hold"><span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;">block</span> On Hold</span>
                        <?php else: ?>
                            <span class="badge badge-returned"><span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;">check_circle</span> Active</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="profile-actions-header">
                    <button class="btn-icon-action" onclick="document.getElementById('editModal').style.display='flex'" title="Edit Profile">
                        <span class="material-symbols-outlined">edit</span>
                    </button>
                    <button class="btn-icon-action" onclick="document.getElementById('passwordModal').style.display='flex'" title="Change Password">
                        <span class="material-symbols-outlined">lock</span>
                    </button>
                </div>
            </div>

            <!-- STATS GRID -->
            <div class="profile-stats-grid">
                <div class="profile-stat-card">
                    <span class="material-symbols-outlined stat-icon-profile">auto_stories</span>
                    <div class="stat-content">
                        <div class="stat-number-profile"><?= $total_borrowed ?></div>
                        <div class="stat-label-profile">Currently Borrowed</div>
                    </div>
                </div>
                <div class="profile-stat-card">
                    <span class="material-symbols-outlined stat-icon-profile" style="color:#2e7d32;">check_circle</span>
                    <div class="stat-content">
                        <div class="stat-number-profile"><?= $total_returned ?></div>
                        <div class="stat-label-profile">Total Returned</div>
                    </div>
                </div>
                <div class="profile-stat-card <?= $total_overdue > 0 ? 'stat-warning' : '' ?>">
                    <span class="material-symbols-outlined stat-icon-profile" style="color:<?= $total_overdue > 0 ? '#c62828' : '#888' ?>;">warning</span>
                    <div class="stat-content">
                        <div class="stat-number-profile" style="color:<?= $total_overdue > 0 ? '#c62828' : '#1a1a2e' ?>;"><?= $total_overdue ?></div>
                        <div class="stat-label-profile">Overdue Books</div>
                    </div>
                </div>
            </div>

            <!-- ACCOUNT INFORMATION CARD -->
            <div class="profile-card modern-card">
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
                            <span class="info-label"><span class="material-symbols-outlined">person</span> Full Name</span>
                            <span class="info-value"><?= htmlspecialchars($user['fullname']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><span class="material-symbols-outlined">email</span> Email Address</span>
                            <span class="info-value"><?= htmlspecialchars($user['email']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><span class="material-symbols-outlined">calendar_today</span> Member Since</span>
                            <span class="info-value"><?= date('F j, Y', strtotime($user['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($user['is_on_hold'] == 1): ?>
            <!-- ON HOLD WARNING CARD -->
            <div class="profile-card warning-card">
                <div class="card-header">
                    <h3><span class="material-symbols-outlined" style="vertical-align:middle;font-size:22px;color:#c62828;">block</span> Account On Hold</h3>
                </div>
                <div class="card-body">
                    <p style="color:#c62828;font-weight:600;">Your account is currently on hold until <strong><?= date('F j, Y', strtotime($user['hold_until'])) ?></strong></p>
                    <p style="color:#666;font-size:14px;margin-top:8px;">You cannot borrow new books during this period. Please return any overdue books.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- EDIT PROFILE MODAL -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="document.getElementById('editModal').style.display='none'"><span class="material-symbols-outlined">close</span></button>
            <h3><span class="material-symbols-outlined" style="vertical-align:middle;">edit</span> Edit Profile</h3>
            <form method="POST">
                <div class="form-group">
                    <label><span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">person</span> Full Name</label>
                    <input type="text" name="fullname" value="<?= htmlspecialchars($user['fullname']) ?>" required>
                </div>
                <div class="form-group">
                    <label><span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">email</span> Email Address</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>
                <div style="display:flex;gap:10px;">
                    <button type="submit" name="update_profile" class="btn-save" style="flex:1;">
                        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">save</span> Save Changes
                    </button>
                    <button type="button" class="btn-cancel" style="flex:1;" onclick="document.getElementById('editModal').style.display='none'">
                        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">close</span> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- CHANGE PASSWORD MODAL -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="document.getElementById('passwordModal').style.display='none'"><span class="material-symbols-outlined">close</span></button>
            <h3><span class="material-symbols-outlined" style="vertical-align:middle;">lock</span> Change Password</h3>
            <form method="POST">
                <div class="form-group">
                    <label><span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">lock_open</span> Current Password</label>
                    <input type="password" name="current_password" placeholder="Enter current password" required>
                </div>
                <div class="form-group">
                    <label><span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">vpn_key</span> New Password</label>
                    <input type="password" name="new_password" placeholder="Enter new password (min. 6 characters)" minlength="6" required>
                </div>
                <div class="form-group">
                    <label><span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">check_circle</span> Confirm Password</label>
                    <input type="password" name="confirm_password" placeholder="Confirm new password" minlength="6" required>
                </div>
                <div style="display:flex;gap:10px;">
                    <button type="submit" name="change_password" class="btn-save" style="flex:1;">
                        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">lock_reset</span> Change Password
                    </button>
                    <button type="button" class="btn-cancel" style="flex:1;" onclick="document.getElementById('passwordModal').style.display='none'">
                        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">close</span> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        window.onclick = function(e) {
            if (e.target.id === 'editModal' || e.target.id === 'passwordModal') {
                e.target.style.display = 'none';
            }
        }

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
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
    </script>
</body>
</html>