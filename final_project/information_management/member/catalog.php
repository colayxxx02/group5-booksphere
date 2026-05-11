<?php
session_start();
include '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'member') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// CHECK KUNG ON HOLD ANG ACCOUNT
$user = $conn->query("SELECT * FROM users WHERE user_id='$user_id'")->fetch_assoc();
if ($user['is_on_hold'] == 1 && strtotime(date('Y-m-d')) <= strtotime($user['hold_until'])) {
    $on_hold = true;
    $hold_until = $user['hold_until'];
} else {
    $on_hold = false;
    if ($user['is_on_hold'] == 1) {
        $conn->query("UPDATE users SET is_on_hold=0, hold_until=NULL WHERE user_id='$user_id'");
    }
}

// BORROW BOOK
if (isset($_POST['borrow_book']) && !$on_hold) {
    $transaction_id = $_POST['transaction_id'];
    $book_id = $_POST['book_id'];
    $borrow_date = date('Y-m-d');
    $due_date = $_POST['due_date'];

    $conn->query("INSERT INTO transactions (transaction_id, user_id, book_id, borrow_date, due_date) 
                  VALUES ('$transaction_id', '$user_id', '$book_id', '$borrow_date', '$due_date')");
}

// SEARCH & FILTER
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter_category = isset($_GET['filter_category']) ? $_GET['filter_category'] : '';

$query = "SELECT b.*,
            (b.stock - IFNULL(
                (SELECT COUNT(*) FROM transactions t 
                 WHERE t.book_id = b.book_id AND t.return_date IS NULL), 0
            )) AS available_stock
          FROM books b
          WHERE b.status != 'damaged'";

if (!empty($search)) {
    $query .= " AND (b.title LIKE '%$search%' OR b.author LIKE '%$search%')";
}
if (!empty($filter_category)) {
    $query .= " AND b.category LIKE '%$filter_category%'";
}

$books = $conn->query($query);

$last = $conn->query("SELECT MAX(transaction_id) as max_id FROM transactions")->fetch_assoc();
$next_transaction_id = ($last['max_id'] ?? 0) + 1;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Browse Books - BookShare</title>
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
                <a href="catalog.php" class="nav-link active">
                    <span class="material-symbols-outlined">book</span>
                    Browse Books
                </a>
                <a href="history.php" class="nav-link">
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
                    <div class="page-title">Browse Books</div>
                    <div class="page-subtitle">Search and borrow available books from the library.</div>
                </div>
            </div>

            <!-- ON HOLD NOTICE -->
            <?php if ($on_hold): ?>
                <div class="on-hold-notice">
                    Your account is currently <strong>On Hold</strong> until <strong><?= $hold_until ?></strong>.
                    You cannot borrow books during this period.
                </div>
            <?php endif; ?>

            <!-- SEARCH & FILTER -->
            <form method="GET" class="filter-bar">
                <input type="text" name="search" placeholder="Search by title or author..." value="<?= htmlspecialchars($search) ?>">
                <select name="filter_category">
                    <option value="">-- All Categories --</option>
                    <?php
                    $categories = $conn->query("SELECT DISTINCT category FROM books WHERE category IS NOT NULL AND category != ''");
                    while ($cat = $categories->fetch_assoc()): ?>
                        <option value="<?= $cat['category'] ?>" <?= $filter_category == $cat['category'] ? 'selected' : '' ?>>
                            <?= $cat['category'] ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" class="btn-primary"><span class="material-symbols-outlined">search</span></button>
                <a href="catalog.php"><button type="button" class="btn-secondary"><span class="material-symbols-outlined">refresh</span></button></a>
            </form>

            <p class="total-results">Available Books: <strong><?= $books->num_rows ?></strong></p>

            <!-- TABLE -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Book ID</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Category</th>
                            <th>Available Copies</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($books->num_rows == 0): ?>
                            <tr><td colspan="6">
                                <div class="no-data">
                                    <span class="material-symbols-outlined no-data-icon" style="font-size:64px;color:#ccc;">inbox</span>
                                    <p class="no-data-text">No available books found.</p>
                                </div>
                            </td></tr>
                        <?php else: ?>
                        <?php while ($row = $books->fetch_assoc()):
                            $avail = max(0, (int)$row['available_stock']);
                            if ($avail == 0) {
                                $stock_class = 'stock-out';
                                $stock_label = 'Out of Stock';
                            } elseif ($avail <= 2) {
                                $stock_class = 'stock-low';
                                $stock_label = $avail . ' left';
                            } else {
                                $stock_class = 'stock-ok';
                                $stock_label = $avail . ' available';
                            }
                        ?>
                        <tr>
                            <td><?= $row['book_id'] ?></td>
                            <td style="font-weight:600;color:#1a1a2e;"><?= $row['title'] ?></td>
                            <td style="color:#666;"><?= $row['author'] ?></td>
                            <td style="color:#888;font-size:13px;"><?= $row['category'] ?></td>
                            <td>
                                <span class="stock-badge <?= $stock_class ?>">
                                    <?= $stock_label ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($on_hold): ?>
                                    <span class="badge badge-pending">On Hold</span>
                                <?php elseif ($avail <= 0): ?>
                                    <span class="stock-badge stock-out">Unavailable</span>
                                <?php else: ?>
                                    <button class="btn-primary" style="padding:6px 14px;font-size:12px;" onclick="openBorrow('<?= $row['book_id'] ?>', '<?= addslashes($row['title']) ?>')"><span class="material-symbols-outlined" style="font-size:18px;">book</span></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- BORROW MODAL -->
    <div id="borrowModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="document.getElementById('borrowModal').style.display='none'"><span class="material-symbols-outlined">close</span></button>
            <h3>Borrow Book</h3>
            <p style="margin-bottom:15px;color:#666;">Book: <strong><span id="borrow_title"></span></strong></p>
            <form method="POST">
                <input type="hidden" name="book_id" id="borrow_book_id">
                <input type="hidden" name="transaction_id" value="<?= $next_transaction_id ?>">
                <div class="form-group">
                    <label>Due Date</label>
                    <input type="date" name="due_date" required min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                </div>
                <div style="display:flex;gap:10px;">
                    <button type="submit" name="borrow_book" class="btn-primary" style="flex:1;">Confirm Borrow</button>
                    <button type="button" class="btn-secondary" style="flex:1;" onclick="document.getElementById('borrowModal').style.display='none'">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openBorrow(book_id, title) {
            document.getElementById('borrow_book_id').value = book_id;
            document.getElementById('borrow_title').innerText = title;
            document.getElementById('borrowModal').style.display = 'flex';
        }

        window.onclick = function(e) {
            if (e.target.id === 'borrowModal') {
                e.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>