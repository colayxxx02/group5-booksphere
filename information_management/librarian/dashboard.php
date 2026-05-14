<?php
session_start();
include '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'librarian') {
    header("Location: ../index.php");
    exit();
}

$total_books   = $conn->query("SELECT COUNT(*) as c FROM books")->fetch_assoc()['c'];
$available     = $conn->query("SELECT COUNT(*) as c FROM books WHERE status='available'")->fetch_assoc()['c'];
$borrowed      = $conn->query("SELECT COUNT(*) as c FROM books WHERE status='borrowed'")->fetch_assoc()['c'];
$damaged       = $conn->query("SELECT COUNT(*) as c FROM books WHERE status='damaged'")->fetch_assoc()['c'];
$overdue       = $conn->query("SELECT COUNT(*) as c FROM transactions WHERE due_date < CURDATE() AND return_date IS NULL")->fetch_assoc()['c'];
$total_members = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='member'")->fetch_assoc()['c'];

$most_borrowed = $conn->query("
    SELECT b.title, b.author, b.category, COUNT(t.book_id) as borrow_count
    FROM transactions t
    JOIN books b ON t.book_id = b.book_id
    GROUP BY t.book_id
    ORDER BY borrow_count DESC
    LIMIT 5
");
$top_books = [];
while ($row = $most_borrowed->fetch_assoc()) {
    $top_books[] = $row;
}

// Get recent transactions for activity feed
$recent_transactions = $conn->query("
    SELECT t.transaction_id, t.borrow_date, t.return_date, t.due_date,
           b.title as book_title, u.fullname as member_name
    FROM transactions t
    JOIN books b ON t.book_id = b.book_id
    JOIN users u ON t.user_id = u.user_id
    ORDER BY t.borrow_date DESC
    LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

// Get monthly statistics
$current_month_borrows = $conn->query("
    SELECT COUNT(*) as count 
    FROM transactions 
    WHERE MONTH(borrow_date) = MONTH(CURDATE()) 
    AND YEAR(borrow_date) = YEAR(CURDATE())
")->fetch_assoc()['count'];

$last_month_borrows = $conn->query("
    SELECT COUNT(*) as count 
    FROM transactions 
    WHERE MONTH(borrow_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
    AND YEAR(borrow_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
")->fetch_assoc()['count'];

$current_month_returns = $conn->query("
    SELECT COUNT(*) as count 
    FROM transactions 
    WHERE MONTH(return_date) = MONTH(CURDATE()) 
    AND YEAR(return_date) = YEAR(CURDATE())
    AND return_date IS NOT NULL
")->fetch_assoc()['count'];

$most_borrowed_category = $conn->query("
    SELECT b.category, COUNT(*) as count
    FROM transactions t
    JOIN books b ON t.book_id = b.book_id
    WHERE MONTH(t.borrow_date) = MONTH(CURDATE()) 
    AND YEAR(t.borrow_date) = YEAR(CURDATE())
    GROUP BY b.category
    ORDER BY count DESC
    LIMIT 1
")->fetch_assoc();

$new_members_this_month = $conn->query("
    SELECT COUNT(*) as count 
    FROM users 
    WHERE role = 'member'
    AND MONTH(created_at) = MONTH(CURDATE()) 
    AND YEAR(created_at) = YEAR(CURDATE())
")->fetch_assoc()['count'] ?? 0;

// Calculate percentage change
$borrow_change = 0;
if ($last_month_borrows > 0) {
    $borrow_change = (($current_month_borrows - $last_month_borrows) / $last_month_borrows) * 100;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Librarian Dashboard - BooksPhere</title>
    <link rel="stylesheet" href="../librarian/style.css">
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
        <span class="title">BooksPhere Library System</span>
    </div>

    <!-- APP SHELL -->
    <div class="app-shell">

        <!-- SIDEBAR -->
        <div class="sidebar">
            <div class="logo"><span class="material-symbols-outlined">menu_book</span> BooksPhere</div>
            <nav>
                <a href="dashboard.php" class="active">
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
                <a href="profile.php">
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
                <h2>Welcome, <?= $_SESSION['fullname'] ?>!</h2>
                <p>Here's your library overview for today.</p>
            </div>

            <!-- STAT CARDS -->
            <div class="dash-stat-grid">
                <div class="dash-stat-card">
                    <span class="material-symbols-outlined stat-icon">library_books</span>
                    <div>
                        <div class="stat-number"><?= $total_books ?></div>
                        <div class="stat-label">Total Books</div>
                    </div>
                </div>
                <div class="dash-stat-card">
                    <span class="material-symbols-outlined stat-icon">check_circle</span>
                    <div>
                        <div class="stat-number"><?= $available ?></div>
                        <div class="stat-label">Available</div>
                    </div>
                </div>
                <div class="dash-stat-card">
                    <span class="material-symbols-outlined stat-icon">auto_stories</span>
                    <div>
                        <div class="stat-number"><?= $borrowed ?></div>
                        <div class="stat-label">Borrowed</div>
                    </div>
                </div>
                <div class="dash-stat-card">
                    <span class="material-symbols-outlined stat-icon">build_circle</span>
                    <div>
                        <div class="stat-number"><?= $damaged ?></div>
                        <div class="stat-label">Damaged</div>
                    </div>
                </div>
                <div class="dash-stat-card">
                    <span class="material-symbols-outlined stat-icon" style="color:#c62828;">error</span>
                    <div>
                        <div class="stat-number" style="color:#c62828;"><?= $overdue ?></div>
                        <div class="stat-label">Overdue</div>
                    </div>
                </div>
                <div class="dash-stat-card">
                    <span class="material-symbols-outlined stat-icon">group</span>
                    <div>
                        <div class="stat-number"><?= $total_members ?></div>
                        <div class="stat-label">Total Members</div>
                    </div>
                </div>
            </div>

            <!-- MONTHLY STATS + RECENT ACTIVITY -->
            <div class="dashboard-widgets">
                <!-- MONTHLY STATISTICS -->
                <div class="widget monthly-stats-widget">
                    <div class="widget-header">
                        <span class="material-symbols-outlined widget-icon">trending_up</span>
                        <h3>Monthly Statistics</h3>
                        <span class="month-label"><?= date('F Y') ?></span>
                    </div>
                    
                    <div class="stats-grid">
                        <!-- Total Borrows -->
                        <div class="stat-item" onclick="showMonthlyDetails('borrows')">
                            <span class="material-symbols-outlined stat-icon-circle">upload</span>
                            <div class="stat-info">
                                <div class="stat-value"><?= $current_month_borrows ?></div>
                                <div class="stat-label">Total Borrows</div>
                                <div class="stat-subtext">This Month</div>
                            </div>
                        </div>

                        <!-- Total Returns -->
                        <div class="stat-item" onclick="showMonthlyDetails('returns')">
                            <span class="material-symbols-outlined stat-icon-circle">download</span>
                            <div class="stat-info">
                                <div class="stat-value"><?= $current_month_returns ?></div>
                                <div class="stat-label">Total Returns</div>
                                <div class="stat-subtext">
                                    <?= $current_month_borrows - $current_month_returns ?> still borrowed
                                </div>
                            </div>
                        </div>

                        <!-- Most Popular Category -->
                        <div class="stat-item" onclick="showMonthlyDetails('category')">
                            <span class="material-symbols-outlined stat-icon-circle">emoji_events</span>
                            <div class="stat-info">
                                <div class="stat-value"><?= $most_borrowed_category['category'] ?? 'N/A' ?></div>
                                <div class="stat-label">Top Category</div>
                                <?php if ($most_borrowed_category): ?>
                                    <div class="stat-subtext">
                                        <?= $most_borrowed_category['count'] ?> borrows
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- New Members -->
                        <div class="stat-item" onclick="showMonthlyDetails('members')">
                            <span class="material-symbols-outlined stat-icon-circle">group_add</span>
                            <div class="stat-info">
                                <div class="stat-value"><?= $new_members_this_month ?></div>
                                <div class="stat-label">New Members</div>
                                <div class="stat-subtext">Joined this month</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RECENT ACTIVITY -->
                <div class="widget activity-widget">
                    <div class="widget-header">
                        <span class="material-symbols-outlined widget-icon">receipt_long</span>
                        <h3>Recent Activity</h3>
                    </div>
                    <div class="activity-feed">
                        <?php if (empty($recent_transactions)): ?>
                            <div class="no-activity">No recent activity</div>
                        <?php else: ?>
                            <?php foreach ($recent_transactions as $trans): ?>
                                <div class="activity-item" onclick="showTransactionDetails(<?= $trans['transaction_id'] ?>)">
                                    <span class="material-symbols-outlined activity-icon">
                                        <?= $trans['return_date'] ? 'download' : 'upload' ?>
                                    </span>
                                    <div class="activity-details">
                                        <div class="activity-text">
                                            <strong><?= htmlspecialchars($trans['member_name']) ?></strong>
                                            <?= $trans['return_date'] ? 'returned' : 'borrowed' ?>
                                            <em><?= htmlspecialchars($trans['book_title']) ?></em>
                                        </div>
                                        <div class="activity-time">
                                            <?= date('M j, g:i A', strtotime($trans['borrow_date'])) ?>
                                        </div>
                                    </div>
                                    <div class="activity-arrow">›</div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- TRANSACTION DETAILS MODAL -->
            <div id="transactionModal" class="modal" style="display:none;">
                <div class="modal-content">
                    <span class="material-symbols-outlined close" onclick="closeTransactionModal()">close</span>
                    <h3>Transaction Details</h3>
                    <div id="transactionDetailsContent">
                        <div style="text-align:center; padding:20px;">Loading...</div>
                    </div>
                </div>
            </div>

            <!-- MONTHLY STATS DETAILS MODAL -->
            <div id="monthlyStatsModal" class="modal" style="display:none;">
                <div class="modal-content">
                    <span class="material-symbols-outlined close" onclick="closeMonthlyStatsModal()">close</span>
                    <h3 id="monthlyStatsTitle">Details</h3>
                    <div id="monthlyStatsContent" class="modal-scrollable-content">
                        <div style="text-align:center; padding:20px;">Loading...</div>
                    </div>
                </div>
            </div>

            <!-- MOST BORROWED SLIDESHOW -->
            <div class="dash-section-title"><span class="material-symbols-outlined" style="vertical-align:middle;">emoji_events</span> Most Borrowed Books</div>

            <?php if (empty($top_books)): ?>
                <div class="no-data"><span class="material-symbols-outlined" style="font-size:32px;color:#ccc;">inbox</span> No transaction data yet.</div>
            <?php else: ?>
            <div class="slideshow-wrapper">
                <button class="slide-arrow slide-prev" onclick="changeSlide(-1)">&#8249;</button>
                <button class="slide-arrow slide-next" onclick="changeSlide(1)">&#8250;</button>

                <div class="slideshow-track" id="slideshowTrack">
                    <?php
                    $book_icons = ['auto_stories','menu_book','book','import_contacts','library_books'];
                    foreach ($top_books as $i => $book):
                    ?>
                    <div class="slide">
                        <div class="slide-rank">#<?= $i + 1 ?></div>
                        <span class="material-symbols-outlined slide-icon" style="color:#4e6ef2;"><?= $book_icons[$i] ?></span>
                        <div class="slide-info">
                            <div class="slide-title"><?= htmlspecialchars($book['title']) ?></div>
                            <div class="slide-author">by <?= htmlspecialchars($book['author']) ?></div>
                            <span class="slide-category"><?= htmlspecialchars($book['category']) ?></span>
                        </div>
                        <div class="slide-count">
                            <div class="count-num"><?= $book['borrow_count'] ?></div>
                            <div class="count-label">times borrowed</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="slideshow-dots" id="slideshowDots">
                    <?php foreach ($top_books as $i => $book): ?>
                        <div class="dot <?= $i == 0 ? 'active' : '' ?>" onclick="goToSlide(<?= $i ?>)"></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <script>
        let currentSlide = 0;
        const total = <?= count($top_books) ?>;
        const track = document.getElementById('slideshowTrack');
        const dots  = document.querySelectorAll('.dot');

        function updateSlide() {
            track.style.transform = `translateX(-${currentSlide * 100}%)`;
            dots.forEach((d, i) => d.classList.toggle('active', i === currentSlide));
        }

        function changeSlide(dir) {
            currentSlide = (currentSlide + dir + total) % total;
            updateSlide();
        }

        function goToSlide(index) {
            currentSlide = index;
            updateSlide();
        }

        setInterval(() => changeSlide(1), 3000);

        // Transaction details modal
        function showTransactionDetails(transactionId) {
            document.getElementById('transactionModal').style.display = 'block';
            
            // Fetch transaction details via AJAX
            fetch(`get_transaction_details.php?id=${transactionId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const t = data.transaction;
                        const status = t.return_date ? 'Returned' : (new Date(t.due_date) < new Date() ? 'Overdue' : 'Active');
                        const statusClass = t.return_date ? 'badge-returned' : (new Date(t.due_date) < new Date() ? 'badge-overdue' : 'badge-active');
                        
                        document.getElementById('transactionDetailsContent').innerHTML = `
                            <div class="transaction-detail-grid">
                                <div class="detail-row">
                                    <span class="detail-label">Transaction ID:</span>
                                    <span class="detail-value">#${t.transaction_id}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Status:</span>
                                    <span class="badge ${statusClass}">${status}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Book:</span>
                                    <span class="detail-value">${t.book_title}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Member:</span>
                                    <span class="detail-value">${t.member_name}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Borrow Date:</span>
                                    <span class="detail-value">${new Date(t.borrow_date).toLocaleDateString('en-US', {year: 'numeric', month: 'short', day: 'numeric'})}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Due Date:</span>
                                    <span class="detail-value">${new Date(t.due_date).toLocaleDateString('en-US', {year: 'numeric', month: 'short', day: 'numeric'})}</span>
                                </div>
                                ${t.return_date ? `
                                <div class="detail-row">
                                    <span class="detail-label">Return Date:</span>
                                    <span class="detail-value">${new Date(t.return_date).toLocaleDateString('en-US', {year: 'numeric', month: 'short', day: 'numeric'})}</span>
                                </div>
                                ` : ''}
                            </div>
                        `;
                    } else {
                        document.getElementById('transactionDetailsContent').innerHTML = `
                            <div style="text-align:center; padding:20px; color:#c62828;">
                                Error loading transaction details
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    document.getElementById('transactionDetailsContent').innerHTML = `
                        <div style="text-align:center; padding:20px; color:#c62828;">
                            Error: ${error.message}
                        </div>
                    `;
                });
        }

        function closeTransactionModal() {
            document.getElementById('transactionModal').style.display = 'none';
        }

        // Monthly stats details modal
        function showMonthlyDetails(type) {
            document.getElementById('monthlyStatsModal').style.display = 'block';
            
            // Fetch details via AJAX
            fetch(`get_monthly_details.php?type=${type}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('monthlyStatsTitle').innerHTML = data.title;
                        document.getElementById('monthlyStatsContent').innerHTML = data.html;
                    } else {
                        document.getElementById('monthlyStatsContent').innerHTML = `
                            <div style="text-align:center; padding:20px; color:#c62828;">
                                Error loading details
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    document.getElementById('monthlyStatsContent').innerHTML = `
                        <div style="text-align:center; padding:20px; color:#c62828;">
                            Error: ${error.message}
                        </div>
                    `;
                });
        }

        function closeMonthlyStatsModal() {
            document.getElementById('monthlyStatsModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const transModal = document.getElementById('transactionModal');
            const statsModal = document.getElementById('monthlyStatsModal');
            if (event.target == transModal) {
                transModal.style.display = 'none';
            }
            if (event.target == statsModal) {
                statsModal.style.display = 'none';
            }
        }
    </script>

</body>
</html>