<?php
session_start();
include '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'member') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// SEARCH & FILTER
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';

$query = "SELECT t.*, b.title, b.author, b.category 
          FROM transactions t 
          JOIN books b ON t.book_id = b.book_id 
          WHERE t.user_id = '$user_id'";

if (!empty($search)) {
    $query .= " AND (b.title LIKE '%$search%' OR b.author LIKE '%$search%')";
}
if ($filter_status == 'active') {
    $query .= " AND t.return_date IS NULL AND t.due_date >= CURDATE()";
} elseif ($filter_status == 'returned') {
    $query .= " AND t.return_date IS NOT NULL";
} elseif ($filter_status == 'overdue') {
    $query .= " AND t.return_date IS NULL AND t.due_date < CURDATE()";
}

$query .= " ORDER BY t.borrow_date DESC";
$history = $conn->query($query);

// COUNT
$total = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE user_id='$user_id'")->fetch_assoc()['count'];
$active = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE user_id='$user_id' AND return_date IS NULL AND due_date >= CURDATE()")->fetch_assoc()['count'];
$returned = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE user_id='$user_id' AND return_date IS NOT NULL")->fetch_assoc()['count'];
$overdue = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE user_id='$user_id' AND return_date IS NULL AND due_date < CURDATE()")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Borrowing History - BookShare</title>
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
                <a href="history.php" class="nav-link active">
                    <span class="material-symbols-outlined">history</span>
                    My History
                </a>
                <a href="profile.php" class="nav-link">
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
                    <div class="page-title">Borrowing History</div>
                    <div class="page-subtitle">View all your past and current borrowed books.</div>
                </div>
            </div>

            <!-- STAT CARDS -->
            <div class="dash-stat-grid">
                <div class="dash-stat-card">
                    <span class="material-symbols-outlined stat-icon">library_books</span>
                    <div>
                        <div class="stat-number"><?= $total ?></div>
                        <div class="stat-label">Total Borrowed</div>
                    </div>
                </div>
                <div class="dash-stat-card">
                    <span class="material-symbols-outlined stat-icon">auto_stories</span>
                    <div>
                        <div class="stat-number"><?= $active ?></div>
                        <div class="stat-label">Currently Active</div>
                    </div>
                </div>
                <div class="dash-stat-card">
                    <span class="material-symbols-outlined stat-icon" style="color:#4caf50;">check_circle</span>
                    <div>
                        <div class="stat-number"><?= $returned ?></div>
                        <div class="stat-label">Returned</div>
                    </div>
                </div>
                <div class="dash-stat-card overdue-card" style="<?= $overdue > 0 ? '' : 'opacity:0.7;' ?>">
                    <span class="material-symbols-outlined stat-icon" style="color:#c62828;">warning</span>
                    <div>
                        <div class="stat-number overdue-number"><?= $overdue ?></div>
                        <div class="stat-label" style="color:#c62828;">Overdue</div>
                    </div>
                </div>
            </div>

            <!-- SEARCH & FILTER -->
            <form method="GET" class="filter-bar">
                <input type="text" name="search" placeholder="Search by title or author..." value="<?= htmlspecialchars($search) ?>">
                <select name="filter_status">
                    <option value="">-- All Status --</option>
                    <option value="active" <?= $filter_status == 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="returned" <?= $filter_status == 'returned' ? 'selected' : '' ?>>Returned</option>
                    <option value="overdue" <?= $filter_status == 'overdue' ? 'selected' : '' ?>>Overdue</option>
                </select>
                <button type="submit" class="btn-primary"><span class="material-symbols-outlined">search</span></button>
                <a href="history.php"><button type="button" class="btn-secondary"><span class="material-symbols-outlined">refresh</span></button></a>
            </form>

            <!-- TABLE -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Category</th>
                            <th>Borrow Date</th>
                            <th>Due Date</th>
                            <th>Return Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($history->num_rows == 0): ?>
                            <tr><td colspan="8">
                                <div class="no-data">
                                    <span class="material-symbols-outlined no-data-icon" style="font-size:48px;color:#ccc;">description</span>
                                    <p class="no-data-text">No records found.</p>
                                </div>
                            </td></tr>
                        <?php else: ?>
                        <?php while ($row = $history->fetch_assoc()):
                            $today = date('Y-m-d');
                            $is_returned = $row['return_date'] != null;
                            $is_overdue = !$is_returned && $today > $row['due_date'];
                            $status_class = $is_returned ? 'badge-returned' : ($is_overdue ? 'badge-overdue' : 'badge-active');
                            $status_text = $is_returned ? 'Returned' : ($is_overdue ? 'Overdue' : 'Active');
                        ?>
                        <tr>
                            <td><?= $row['transaction_id'] ?></td>
                            <td style="font-weight:600;color:#1a1a2e;"><?= $row['title'] ?></td>
                            <td style="color:#666;font-size:13px;"><?= $row['author'] ?></td>
                            <td style="color:#888;font-size:13px;"><?= $row['category'] ?></td>
                            <td style="color:#666;font-size:13px;"><?= $row['borrow_date'] ?></td>
                            <td style="color:#666;font-size:13px;"><?= $row['due_date'] ?></td>
                            <td style="color:#666;font-size:13px;"><?= $row['return_date'] ?? '—' ?></td>
                            <td>
                                <span class="badge <?= $status_class ?>"><?= $status_text ?></span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
</html>