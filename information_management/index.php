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
$login_error = "";
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
        $login_error = "Invalid email or password.";
    }
}

// PROCESS SIGNUP
$signup_error = "";
$signup_success = "";
$fullname = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['signup'])) {
    include 'db.php';
    
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = 'member'; // Always register as member

    // Validation
    if (empty($fullname) || empty($email) || empty($password) || empty($confirm_password)) {
        $signup_error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $signup_error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $signup_error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $signup_error = "Passwords do not match.";
    } else {
        // Check if email already exists
        $check = $conn->query("SELECT user_id FROM users WHERE email='$email'");
        if ($check->num_rows > 0) {
            $signup_error = "Email already registered!";
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
                $signup_success = "Account created successfully! You can now login.";
                $fullname = "";
                $email = "";
            } else {
                $signup_error = "Error creating account: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BooksPhere - Library Management System</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body>

    <!-- NAVBAR -->
    <nav>
        <div class="logo">
            <span>📚</span> BooksPhere
        </div>
        <ul class="nav-links">
            <li><a href="#features">Features</a></li>
            <li><a href="#about">About</a></li>
            <li><a href="#contact">Contact</a></li>
        </ul>
        <div class="nav-actions">
            <button class="btn-login" onclick="document.getElementById('loginModal').style.display='block'">Login</button>
            <button class="btn-signup" onclick="document.getElementById('signupModal').style.display='block'">Sign Up</button>
        </div>
    </nav>

    <!-- HERO -->
    <section class="hero">
        <h1>Smart Library<br><span>Management</span></h1>
        <p>BooksPhere makes borrowing, returning, and managing library books easier than ever — for members and librarians alike.</p>
        <div class="hero-btns">
            <button class="btn-primary" onclick="document.getElementById('loginModal').style.display='block'">Get Started</button>
            <button class="btn-secondary" onclick="document.getElementById('about').scrollIntoView({behavior:'smooth'})">Learn More</button>
        </div>
    </section>

    <!-- APP PREVIEW -->
    <div class="preview">
        <div class="preview-bar">
            <div class="dot" style="background:#ff5f57;"></div>
            <div class="dot" style="background:#febc2e;"></div>
            <div class="dot" style="background:#28c840;"></div>
            <span> BooksPhere Library System</span>
        </div>
        <div class="preview-content">
            <div class="preview-sidebar">
                <div style="font-weight:700; color:#1a1a2e; margin-bottom:12px; font-size:15px;">📚  BooksPhere</div>
                <div class="menu-item active">📖 Browse Books</div>
                <div class="menu-item">🕐 My Borrows</div>
                <div class="menu-item">📋 History</div>
                <div class="menu-item">👤 My Profile</div>
            </div>
            <div class="preview-main">
                <div style="font-weight:700; font-size:16px; color:#1a1a2e;">Available Books <span style="background:#4e6ef2;color:#fff;padding:2px 10px;border-radius:10px;font-size:12px;">24</span></div>
                <div class="book-grid">
                    <div class="book-card"><span class="book-icon">📗</span>Science Fiction</div>
                    <div class="book-card"><span class="book-icon">📘</span>History</div>
                    <div class="book-card"><span class="book-icon">📙</span>Technology</div>
                    <div class="book-card"><span class="book-icon">📕</span>Philosophy</div>
                </div>
            </div>
        </div>
    </div>

    <!-- FEATURES -->
    <section class="features" id="features">
        <h2>Everything You Need</h2>
        <p class="sub">Designed for both members and librarians</p>
        <div class="feature-grid">
            <div class="feature-card">
                <div class="icon">📖</div>
                <h3>Easy Borrowing</h3>
                <p>Members can browse, search, and borrow available books with just a few clicks.</p>
            </div>
            <div class="feature-card">
                <div class="icon">🔔</div>
                <h3>Overdue Tracking</h3>
                <p>Automatic overdue detection with account hold system to manage late returns.</p>
            </div>
            <div class="feature-card">
                <div class="icon">🛠️</div>
                <h3>Maintenance Logs</h3>
                <p>Track damaged books and log maintenance status from pending to resolved.</p>
            </div>
            <div class="feature-card">
                <div class="icon">📊</div>
                <h3>Librarian Dashboard</h3>
                <p>Full control over books, members, transactions, and overdue records.</p>
            </div>
            <div class="feature-card">
                <div class="icon">📋</div>
                <h3>Borrow History</h3>
                <p>Members can view their complete borrowing history with status tracking.</p>
            </div>
            <div class="feature-card">
                <div class="icon">🔒</div>
                <h3>Role-Based Access</h3>
                <p>Separate portals for Members and Librarians with secure login system.</p>
            </div>
        </div>
    </section>

    <!-- ABOUT -->
    <section id="about" class="about-section">
        <h2>About BooksPhere</h2>
        <p>
             BooksPhere is a web-based Library Management System built to simplify the borrowing and 
            returning of library books. It features a dual-role system — <strong>Members</strong> 
            can browse and borrow books, while <strong>Librarians</strong> have full control over 
            the library's inventory, transactions, overdue tracking, and maintenance records.
        </p>
    </section>

    <!-- CONTACT -->
    <section id="contact" class="contact-section">
        <h2>Need Help?</h2>
        <p>Contact your librarian for account setup and assistance.</p>
    </section>

    <!-- FOOTER -->
    <footer>
        &copy; <?= date('Y') ?>  BooksPhere Library Management System. All rights reserved.
    </footer>

    <!-- LOGIN MODAL -->
    <div id="loginModal" class="modal" <?= $login_error ? 'style="display:block;"' : '' ?>>
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('loginModal').style.display='none'">&times;</span>
            <h2>Welcome Back</h2>
            <p>Login to your BooksPhere account</p>

            <?php if ($login_error): ?>
                <div class="error">
                    <span class="material-icons">error</span>
                    <span><?= htmlspecialchars($login_error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <input type="email" name="email" placeholder="Email Address" required>
                </div>
                <div class="form-group">
                    <input type="password" name="password" placeholder="Password" required>
                </div>
                <button type="submit" name="login" class="btn-submit">Login</button>
            </form>
            <div class="modal-footer">
                Don't have an account? <a href="javascript:void(0);" onclick="document.getElementById('loginModal').style.display='none'; document.getElementById('signupModal').style.display='block';">Sign Up</a>
            </div>
        </div>
    </div>

    <!-- SIGNUP MODAL -->
    <div id="signupModal" class="modal" <?= $signup_error || $signup_success ? 'style="display:block;"' : '' ?>>
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('signupModal').style.display='none'">&times;</span>
            <h2>Create Account</h2>
            <p>Join  BooksPhereand start borrowing books</p>

            <?php if ($signup_error): ?>
                <div class="error">
                    <span class="material-icons">error</span>
                    <span><?= htmlspecialchars($signup_error) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($signup_success): ?>
                <div class="success">
                    <span class="material-icons">check_circle</span>
                    <span><?= htmlspecialchars($signup_success) ?></span>
                </div>
                <p style="text-align:center; margin-top:10px; font-size:14px; color:#2e7d32; font-weight:600;">Redirecting to login in 2 seconds...</p>
            <?php endif; ?>

            <?php if (!$signup_success): ?>
            <form method="POST">
                <div class="form-group">
                    <input type="text" name="fullname" placeholder="Full Name" value="<?= htmlspecialchars($fullname ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <input type="email" name="email" placeholder="Email Address" value="<?= htmlspecialchars($email ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <input type="password" name="password" placeholder="Password (min. 6 characters)" required minlength="6">
                </div>
                <div class="form-group">
                    <input type="password" name="confirm_password" placeholder="Confirm Password" required minlength="6">
                </div>
                <button type="submit" name="signup" class="btn-submit">Create My Account</button>
            </form>
            <?php endif; ?>

            <div class="modal-footer">
                Already have an account? <a href="javascript:void(0);" onclick="document.getElementById('signupModal').style.display='none'; document.getElementById('loginModal').style.display='block';">Login here</a>
            </div>
        </div>
    </div>

    <script>
        window.onclick = function(e) {
            if (e.target.id === 'loginModal') {
                e.target.style.display = 'none';
            }
            if (e.target.id === 'signupModal') {
                e.target.style.display = 'none';
            }
        }

        // Auto redirect after successful signup
        <?php if ($signup_success): ?>
            setTimeout(function() {
                document.getElementById('signupModal').style.display = 'none';
                document.getElementById('loginModal').style.display = 'block';
            }, 2000);
        <?php endif; ?>
    </script>
</body>
</html>