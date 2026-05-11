<?php
session_start();
include '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'member') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$current_borrowed = $conn->query("
    SELECT COUNT(*) as c 
    FROM transactions 
    WHERE user_id = $user_id AND return_date IS NULL
")->fetch_assoc()['c'];

$overdue_count = $conn->query("
    SELECT COUNT(*) as c 
    FROM transactions 
    WHERE user_id = $user_id AND due_date < CURDATE() AND return_date IS NULL
")->fetch_assoc()['c'];

$current_bookings = $conn->query("
    SELECT b.title, b.author, t.due_date, t.transaction_id
    FROM transactions t
    JOIN books b ON t.book_id = b.book_id
    WHERE t.user_id = $user_id AND t.return_date IS NULL
    ORDER BY t.borrow_date DESC
")->fetch_all(MYSQLI_ASSOC);

// Count total borrowed books for View All button
$total_current_bookings = count($current_bookings);

// GET MOST BORROWED BOOKS (All time)
$most_borrowed = $conn->query("
    SELECT b.book_id, b.title, b.author, b.category, COUNT(t.transaction_id) as borrow_count
    FROM books b
    LEFT JOIN transactions t ON b.book_id = t.book_id
    GROUP BY b.book_id
    ORDER BY borrow_count DESC
    LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

// ============================================
// MY READING STATISTICS (NEW FEATURE)
// ============================================

// Total books borrowed by this member (all time)
$total_borrowed_alltime = $conn->query("
    SELECT COUNT(*) as count 
    FROM transactions 
    WHERE user_id = $user_id
")->fetch_assoc()['count'];

// Books borrowed this month
$borrowed_this_month = $conn->query("
    SELECT COUNT(*) as count 
    FROM transactions 
    WHERE user_id = $user_id 
    AND MONTH(borrow_date) = MONTH(CURDATE()) 
    AND YEAR(borrow_date) = YEAR(CURDATE())
")->fetch_assoc()['count'];

// Member's favorite category (most borrowed)
$favorite_category = $conn->query("
    SELECT b.category, COUNT(*) as count
    FROM transactions t
    JOIN books b ON t.book_id = b.book_id
    WHERE t.user_id = $user_id
    GROUP BY b.category
    ORDER BY count DESC
    LIMIT 1
")->fetch_assoc();

// On-time return rate
$total_returns = $conn->query("
    SELECT COUNT(*) as count 
    FROM transactions 
    WHERE user_id = $user_id AND return_date IS NOT NULL
")->fetch_assoc()['count'];

$on_time_returns = $conn->query("
    SELECT COUNT(*) as count 
    FROM transactions 
    WHERE user_id = $user_id 
    AND return_date IS NOT NULL 
    AND return_date <= due_date
")->fetch_assoc()['count'];

$on_time_rate = $total_returns > 0 ? round(($on_time_returns / $total_returns) * 100) : 0;

// ============================================
// DUE DATE REMINDERS (NEW FEATURE)
// ============================================

// Get books due soon (within next 7 days)
$due_soon_books = $conn->query("
    SELECT b.title, b.author, t.due_date, t.transaction_id,
           DATEDIFF(t.due_date, CURDATE()) as days_remaining
    FROM transactions t
    JOIN books b ON t.book_id = b.book_id
    WHERE t.user_id = $user_id 
    AND t.return_date IS NULL
    AND t.due_date >= CURDATE()
    AND t.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY t.due_date ASC
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Member Dashboard - BookShare</title>
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
        <span class="title">BookShare Library System</span>
    </div>

    <!-- APP SHELL -->
    <div class="app-shell">

        <!-- SIDEBAR -->
        <div class="sidebar">
            <div class="logo">
                <span class="material-symbols-outlined" style="font-size:28px;color:#4e6ef2;">menu_book</span>
                <span style="font-size:18px;font-weight:800;color:#1a1a2e;">BookShare</span>
            </div>
            <nav>
                <a href="dashboard.php" class="nav-link active">
                    <span class="material-symbols-outlined">dashboard</span> Dashboard
                </a>
                <a href="catalog.php" class="nav-link">
                    <span class="material-symbols-outlined">book</span> Browse Books
                </a>
                <a href="history.php?filter_status=returned" class="nav-link">
                    <span class="material-symbols-outlined">keyboard_return</span> Return Books
                </a>
                <a href="history.php" class="nav-link">
                    <span class="material-symbols-outlined">history</span> My History
                </a>
                <a href="profile.php" class="nav-link">
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
                    <span class="material-symbols-outlined stat-icon">auto_stories</span>
                    <div>
                        <div class="stat-number"><?= $current_borrowed ?></div>
                        <div class="stat-label">Currently Borrowed</div>
                    </div>
                </div>
                <div class="dash-stat-card <?= $overdue_count > 0 ? 'overdue-card' : '' ?>">
                    <span class="material-symbols-outlined stat-icon <?= $overdue_count > 0 ? 'overdue-icon' : '' ?>">error</span>
                    <div>
                        <div class="stat-number <?= $overdue_count > 0 ? 'overdue-number' : '' ?>">
                            <?= $overdue_count ?>
                        </div>
                        <div class="stat-label">Overdue</div>
                    </div>
                </div>
                <div class="dash-stat-card">
                    <span class="material-symbols-outlined stat-icon">bookmark</span>
                    <div>
                        <div class="stat-number">0</div>
                        <div class="stat-label">Reservations</div>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- MY READING STATISTICS -->
            <!-- ============================================ -->
            <div class="reading-stats-section">
                <div class="dash-section-title"><span class="material-symbols-outlined" style="vertical-align:middle;">bar_chart</span> My Reading Statistics</div>
                
                <div class="stats-grid-full">
                    <!-- Total Books Borrowed -->
                    <div class="stat-item" onclick="showReadingDetails('total')">
                        <span class="material-symbols-outlined stat-icon-circle">library_books</span>
                        <div class="stat-info">
                            <div class="stat-value"><?= $total_borrowed_alltime ?></div>
                            <div class="stat-label">Total Borrowed</div>
                            <div class="stat-subtext">All time</div>
                        </div>
                    </div>

                    <!-- This Month -->
                    <div class="stat-item" onclick="showReadingDetails('month')">
                        <span class="material-symbols-outlined stat-icon-circle">calendar_month</span>
                        <div class="stat-info">
                            <div class="stat-value"><?= $borrowed_this_month ?></div>
                            <div class="stat-label">This Month</div>
                            <div class="stat-subtext"><?= date('F Y') ?></div>
                        </div>
                    </div>

                    <!-- Favorite Category -->
                    <div class="stat-item" onclick="showReadingDetails('category')">
                        <span class="material-symbols-outlined stat-icon-circle">emoji_events</span>
                        <div class="stat-info">
                            <div class="stat-value"><?= $favorite_category['category'] ?? 'N/A' ?></div>
                            <div class="stat-label">Favorite Category</div>
                            <?php if ($favorite_category): ?>
                                <div class="stat-subtext"><?= $favorite_category['count'] ?> books</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- On-Time Rate -->
                    <div class="stat-item" onclick="showReadingDetails('ontime')">
                        <span class="material-symbols-outlined stat-icon-circle">check_circle</span>
                        <div class="stat-info">
                            <div class="stat-value"><?= $on_time_rate ?>%</div>
                            <div class="stat-label">On-Time Rate</div>
                            <div class="stat-subtext"><?= $on_time_returns ?>/<?= $total_returns ?> returns</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MOST BORROWED BOOKS SLIDESHOW -->
            <?php if (!empty($most_borrowed)): ?>
                <div class="dash-section-title"><span class="material-symbols-outlined" style="vertical-align:middle;">star</span> Most Borrowed Books</div>
                <div class="slideshow-container">
                    <div class="slides-wrapper">
                        <?php foreach ($most_borrowed as $index => $book): ?>
                            <div class="slide fade" style="display: <?= $index === 0 ? 'block' : 'none' ?>;">
                                <div class="slide-content">
                                    <span class="material-symbols-outlined slide-book-icon" style="font-size:64px;color:#4e6ef2;">menu_book</span>
                                    <div class="slide-book-info">
                                        <h3 class="slide-title"><?= htmlspecialchars($book['title']) ?></h3>
                                        <p class="slide-author">by <?= htmlspecialchars($book['author']) ?></p>
                                        <p class="slide-category">Category: <?= htmlspecialchars($book['category']) ?></p>
                                        <p class="slide-stats">Borrowed <strong><?= $book['borrow_count'] ?></strong> times</p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Slide Controls -->
                    <div class="slide-controls">
                        <button class="slide-btn slide-prev" onclick="changeSlide(-1)">❮</button>
                        <div class="slide-dots">
                            <?php foreach ($most_borrowed as $index => $book): ?>
                                <span class="dot <?= $index === 0 ? 'active' : '' ?>" onclick="currentSlide(<?= $index ?>)"></span>
                            <?php endforeach; ?>
                        </div>
                        <button class="slide-btn slide-next" onclick="changeSlide(1)">❯</button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- CURRENT BOOKS -->
            <div class="dash-section-title with-button">
                <div style="display:flex; align-items:center; gap:10px;">
                    <span class="material-symbols-outlined" style="vertical-align:middle;">library_books</span> 
                    <span>Currently Borrowed Books</span>
                    <?php if ($current_borrowed > 0): ?>
                        <span class="section-badge"><?= $current_borrowed ?> book<?= $current_borrowed > 1 ? 's' : '' ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($total_current_bookings > 4): ?>
                    <button id="viewAllBtn" class="view-all-btn-header" onclick="toggleViewAll()">
                        Show More
                    </button>
                <?php endif; ?>
            </div>

            <?php if (empty($current_bookings)): ?>
                <div class="no-data">
                    <span class="material-symbols-outlined no-data-icon" style="font-size:64px;color:#ccc;">inbox</span>
                    <div class="no-data-text">No books borrowed right now.</div>
                    <a href="catalog.php" class="no-data-action">Browse Catalog →</a>
                </div>
            <?php else: ?>
                <div class="current-books-grid">
                    <?php 
                    $display_limit = 4;
                    foreach ($current_bookings as $index => $booking): 
                        $due_date = strtotime($booking['due_date']);
                        $today = strtotime(date('Y-m-d'));
                        $days_diff = floor(($due_date - $today) / (60 * 60 * 24));
                        
                        $urgency = '';
                        if ($days_diff < 0) {
                            $urgency = 'overdue';
                        } elseif ($days_diff <= 2) {
                            $urgency = 'urgent';
                        } elseif ($days_diff <= 5) {
                            $urgency = 'warning';
                        }
                        
                        $hide_class = $index >= $display_limit ? 'hidden-book' : '';
                        $hide_style = $index >= $display_limit ? 'display:none;' : '';
                    ?>
                        <div class="current-book-card <?= $urgency ?> <?= $hide_class ?>" onclick="confirmReturn(<?= $booking['transaction_id'] ?>, '<?= htmlspecialchars($booking['title'], ENT_QUOTES) ?>')" style="cursor:pointer;<?= $hide_style ?>">
                            <div class="card-header-row">
                                <span class="material-symbols-outlined book-icon-large">menu_book</span>
                                <div class="due-badge-top">
                                    <?php if ($days_diff < 0): ?>
                                        <span class="due-badge overdue"><span class="material-symbols-outlined" style="font-size:12px;vertical-align:middle;">error</span> OVERDUE</span>
                                    <?php elseif ($days_diff == 0): ?>
                                        <span class="due-badge urgent">TODAY</span>
                                    <?php elseif ($days_diff == 1): ?>
                                        <span class="due-badge urgent">TOMORROW</span>
                                    <?php else: ?>
                                        <span class="due-badge <?= $urgency ?>"><?= $days_diff ?> days</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="book-details">
                                <div class="book-title-large"><?= htmlspecialchars($booking['title']) ?></div>
                                <div class="book-author-small">by <?= htmlspecialchars($booking['author']) ?></div>
                                <div class="book-due-date-bottom">
                                    <span class="due-date-small"><?= date('M j, Y', strtotime($booking['due_date'])) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
            <?php endif; ?>

            <!-- RETURN CONFIRMATION MODAL -->
            <div id="returnModal" class="modal" style="display:none;">
                <div class="modal-content modal-small">
                    <span class="material-symbols-outlined close" onclick="closeReturnModal()">close</span>
                    <h3>Confirm Return</h3>
                    <p id="returnMessage"></p>
                    <div class="modal-actions">
                        <button class="btn-confirm" onclick="processReturn()">Yes</button>
                        <button class="btn-cancel-modal" onclick="closeReturnModal()">No</button>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- READING DETAILS MODAL -->
    <div id="readingDetailsModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="material-symbols-outlined close" onclick="closeReadingDetailsModal()">close</span>
            <h3 id="readingDetailsTitle">Details</h3>
            <div id="readingDetailsContent">
                <div style="text-align:center; padding:20px;">Loading...</div>
            </div>
        </div>
    </div>

    <!-- SLIDESHOW JAVASCRIPT -->
    <script>
        let slideIndex = 0;
        let slideTimer;

        function showSlides() {
            const slides = document.querySelectorAll('.slide');
            if (slides.length === 0) return;

            slideIndex = (slideIndex) % slides.length;
            if (slideIndex < 0) slideIndex = slides.length - 1;

            // Hide all slides
            slides.forEach(slide => slide.style.display = 'none');

            // Show current slide
            slides[slideIndex].style.display = 'block';

            // Update dots
            const dots = document.querySelectorAll('.dot');
            dots.forEach(dot => dot.classList.remove('active'));
            dots[slideIndex].classList.add('active');
        }

        function changeSlide(n) {
            clearTimeout(slideTimer);
            slideIndex += n;
            showSlides();
            autoSlide();
        }

        function currentSlide(n) {
            clearTimeout(slideTimer);
            slideIndex = n;
            showSlides();
            autoSlide();
        }

        function autoSlide() {
            slideTimer = setTimeout(() => {
                slideIndex++;
                showSlides();
                autoSlide();
            }, 5000); // Change slide every 5 seconds
        }

        // Initialize slideshow
        document.addEventListener('DOMContentLoaded', () => {
            showSlides();
            autoSlide();
        });

        // Reading Details Modal
        function showReadingDetails(type) {
            document.getElementById('readingDetailsModal').style.display = 'block';
            
            // Fetch details via AJAX
            fetch(`get_reading_details.php?type=${type}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('readingDetailsTitle').innerHTML = data.title;
                        document.getElementById('readingDetailsContent').innerHTML = data.html;
                    } else {
                        document.getElementById('readingDetailsContent').innerHTML = `
                            <div style="text-align:center; padding:20px; color:#c62828;">
                                Error loading details
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    document.getElementById('readingDetailsContent').innerHTML = `
                        <div style="text-align:center; padding:20px; color:#c62828;">
                            Error: ${error.message}
                        </div>
                    `;
                });
        }

        function closeReadingDetailsModal() {
            document.getElementById('readingDetailsModal').style.display = 'none';
        }

        // Return Book Functionality
        let returnTransactionId = null;

        function confirmReturn(transactionId, bookTitle) {
            returnTransactionId = transactionId;
            document.getElementById('returnMessage').textContent = `Are you sure you want to return "${bookTitle}"?`;
            document.getElementById('returnModal').style.display = 'block';
        }

        function closeReturnModal() {
            document.getElementById('returnModal').style.display = 'none';
            returnTransactionId = null;
        }

        function processReturn() {
            if (!returnTransactionId) return;

            // Send return request
            fetch(`process_return.php?id=${returnTransactionId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Book returned successfully!');
                        location.reload(); // Refresh page to update display
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error processing return: ' + error.message);
                })
                .finally(() => {
                    closeReturnModal();
                });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const readingModal = document.getElementById('readingDetailsModal');
            const returnModal = document.getElementById('returnModal');
            
            if (event.target == readingModal) {
                readingModal.style.display = 'none';
            }
            if (event.target == returnModal) {
                returnModal.style.display = 'none';
            }
        }

        // Toggle View All Books
        let isExpanded = false;
        function toggleViewAll() {
            const hiddenBooks = document.querySelectorAll('.hidden-book');
            const btn = document.getElementById('viewAllBtn');
            
            if (!isExpanded) {
                // Show all hidden books
                hiddenBooks.forEach(book => {
                    book.style.display = 'flex';
                });
                btn.textContent = 'Show Less';
                isExpanded = true;
            } else {
                // Hide books again
                hiddenBooks.forEach(book => {
                    book.style.display = 'none';
                });
                btn.textContent = 'Show More';
                isExpanded = false;
            }
        }
    </script>
</body>
</html>