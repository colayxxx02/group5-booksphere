<?php
session_start();
include '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'librarian') {
    header("Location: ../login.php");
    exit();
}

$success_msg = "";
$error_msg = "";

if (isset($_POST['add_book'])) {
    $book_id = $_POST['book_id'];
    $title = $_POST['title'];
    $author = $_POST['author'];
    $category = $_POST['category'];
    $status = $_POST['status'];
    $stock = (int)$_POST['stock'];
    
    // Check if book_id already exists
    $check = $conn->query("SELECT book_id FROM books WHERE book_id='$book_id'");
    if ($check->num_rows > 0) {
        $error_msg = "Book ID already exists!";
    } else {
        if ($conn->query("INSERT INTO books (book_id, title, author, category, status, stock) 
                      VALUES ('$book_id', '$title', '$author', '$category', '$status', '$stock')")) {
            $success_msg = "Book added successfully!";
        } else {
            $error_msg = "Error adding book: " . $conn->error;
        }
    }
}

if (isset($_POST['update_book'])) {
    $book_id = $_POST['book_id'];
    $title = $_POST['title'];
    $author = $_POST['author'];
    $category = $_POST['category'];
    $status = $_POST['status'];
    $stock = (int)$_POST['stock'];
    
    if ($conn->query("UPDATE books SET title='$title', author='$author', 
                  category='$category', status='$status', stock='$stock' 
                  WHERE book_id='$book_id'")) {
        $success_msg = "Book updated successfully!";
    } else {
        $error_msg = "Error updating book: " . $conn->error;
    }
}

if (isset($_POST['delete_book'])) {
    $book_id = $_POST['book_id'];
    $conn->query("SET FOREIGN_KEY_CHECKS=0");
    $conn->query("DELETE FROM maintenance_log WHERE book_id='$book_id'");
    $conn->query("DELETE FROM transactions WHERE book_id='$book_id'");
    
    if ($conn->query("DELETE FROM books WHERE book_id='$book_id'")) {
        $success_msg = "Book deleted successfully!";
    } else {
        $error_msg = "Error deleting book: " . $conn->error;
    }
    $conn->query("SET FOREIGN_KEY_CHECKS=1");
}

$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
$filter_category = isset($_GET['filter_category']) ? $_GET['filter_category'] : '';

// Pagination setup
$items_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, $current_page); // Ensure page is at least 1
$offset = ($current_page - 1) * $items_per_page;

// Build query
$query = "SELECT b.*,
            b.stock,
            (b.stock - IFNULL(
                (SELECT COUNT(*) FROM transactions t 
                 WHERE t.book_id = b.book_id AND t.return_date IS NULL), 0
            )) AS available_stock
          FROM books b WHERE 1=1";

if (!empty($search)) $query .= " AND (b.title LIKE '%$search%' OR b.author LIKE '%$search%')";
if (!empty($filter_status)) $query .= " AND b.status = '$filter_status'";
if (!empty($filter_category)) $query .= " AND b.category LIKE '%$filter_category%'";

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM books b WHERE 1=1";
if (!empty($search)) $count_query .= " AND (b.title LIKE '%$search%' OR b.author LIKE '%$search%')";
if (!empty($filter_status)) $count_query .= " AND b.status = '$filter_status'";
if (!empty($filter_category)) $count_query .= " AND b.category LIKE '%$filter_category%'";

$count_result = $conn->query($count_query);
$total_items = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_items / $items_per_page);

// Add limit and offset to main query
$query .= " ORDER BY b.book_id DESC LIMIT $items_per_page OFFSET $offset";

$books = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Books - BooksPhere</title>
    <link rel="stylesheet" href="../librarian/style.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
</head>
<body class="dashboard-page">

    <div class="titlebar">
        <div class="dots">
            <span class="dot-red"></span>
            <span class="dot-yellow"></span>
            <span class="dot-green"></span>
        </div>
        <span class="title"> BooksPhere Library System</span>
    </div>

    <div class="app-shell">

        <div class="sidebar">
            <div class="logo"><span class="material-symbols-outlined">menu_book</span> BooksPhere</div>
            <nav>
                <a href="dashboard.php"><span class="material-symbols-outlined">dashboard</span> Dashboard</a>
                <a href="books.php" class="active"><span class="material-symbols-outlined">book</span> Books</a>
                <a href="transactions.php"><span class="material-symbols-outlined">sync_alt</span> Transactions</a>
                <a href="overdue.php"><span class="material-symbols-outlined">warning</span> Overdue</a>
                <a href="maintenance.php"><span class="material-symbols-outlined">build</span> Maintenance</a>
            </nav>
            <div class="sidebar-footer">
                <div class="sidebar-user"><span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">person</span> <?= $_SESSION['fullname'] ?></div>
                <form method="POST" action="../logout.php">
                    <button type="submit"><span class="material-symbols-outlined">logout</span> Logout</button>
                </form>
            </div>
        </div>

        <div class="main-content">
            <div class="page-header">
                <div>
                    <div class="page-title">Manage Books</div>
                    <div class="page-subtitle">Add, edit, or remove books from the library collection.</div>
                </div>
                <button class="btn-add" onclick="document.getElementById('addModal').style.display='block'"><span class="material-symbols-outlined">add</span></button>
            </div>

            <?php if ($success_msg): ?>
                <div class="alert alert-success">
                    <span class="material-symbols-outlined">check_circle</span>
                    <span><?= htmlspecialchars($success_msg) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="alert alert-error">
                    <span class="material-symbols-outlined">error</span>
                    <span><?= htmlspecialchars($error_msg) ?></span>
                </div>
            <?php endif; ?>

            <form method="GET" class="filter-bar">
                <input type="text" name="search" placeholder="Search by title or author..." value="<?= htmlspecialchars($search) ?>">
                <select name="filter_status">
                    <option value="">-- All Status --</option>
                    <option value="available" <?= $filter_status=='available'?'selected':'' ?>>Available</option>
                    <option value="borrowed"  <?= $filter_status=='borrowed' ?'selected':'' ?>>Borrowed</option>
                    <option value="damaged"   <?= $filter_status=='damaged'  ?'selected':'' ?>>Damaged</option>
                </select>
                <select name="filter_category">
                    <option value="">-- All Categories --</option>
                    <?php 
                    $cats = $conn->query("SELECT DISTINCT category FROM books WHERE category IS NOT NULL AND category != ''");
                    while ($cat = $cats->fetch_assoc()): ?>
                        <option value="<?= $cat['category'] ?>" <?= $filter_category==$cat['category']?'selected':'' ?>><?= $cat['category'] ?></option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" class="btn-search">Search</button>
                <a href="books.php"><button type="button" class="btn-reset">Reset</button></a>
            </form>

            <p class="total-results">Total Results: <strong><?= $total_items ?></strong> | Showing <strong><?= min($offset + 1, $total_items) ?>-<?= min($offset + $items_per_page, $total_items) ?></strong></p>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Book ID</th><th>Title</th><th>Author</th><th>Category</th>
                            <th>Status</th><th>Stock</th><th>Available</th><th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($books->num_rows == 0): ?>
                            <tr><td colspan="8"><div class="empty-state"><div class="empty-icon"><span class="material-symbols-outlined" style="font-size:48px;color:#ccc;">inbox</span></div><p>No books found.</p></div></td></tr>
                        <?php else: ?>
                        <?php while ($row = $books->fetch_assoc()):
                            $avail = max(0, (int)$row['available_stock']);
                            $stock = (int)$row['stock'];
                            if ($avail == 0) { $sc = 'stock-out'; $sl = 'Out of Stock'; }
                            elseif ($avail <= 2) { $sc = 'stock-low'; $sl = $avail.' left'; }
                            else { $sc = 'stock-ok'; $sl = $avail.' available'; }
                        ?>
                        <tr>
                            <td><?= $row['book_id'] ?></td>
                            <td><?= $row['title'] ?></td>
                            <td><?= $row['author'] ?></td>
                            <td><?= $row['category'] ?></td>
                            <td><span class="badge badge-<?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span></td>
                            <td style="text-align:center;font-weight:600;"><?= $stock ?></td>
                            <td><span class="stock-badge <?= $sc ?>"><?= $sl ?></span></td>
                            <td>
                                <button class="btn-edit" onclick="openEdit('<?= $row['book_id'] ?>','<?= addslashes($row['title']) ?>','<?= addslashes($row['author']) ?>','<?= addslashes($row['category']) ?>','<?= $row['status'] ?>','<?= $stock ?>')"><span class="material-symbols-outlined">edit</span></button>
                                <button class="btn-delete" onclick="openDelete('<?= $row['book_id'] ?>','<?= addslashes($row['title']) ?>')"><span class="material-symbols-outlined">delete</span></button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php
                // Build query string for pagination links
                $query_params = [];
                if (!empty($search)) $query_params[] = 'search=' . urlencode($search);
                if (!empty($filter_status)) $query_params[] = 'filter_status=' . urlencode($filter_status);
                if (!empty($filter_category)) $query_params[] = 'filter_category=' . urlencode($filter_category);
                $query_string = !empty($query_params) ? '&' . implode('&', $query_params) : '';
                ?>

                <?php if ($current_page > 1): ?>
                    <a href="?page=<?= $current_page - 1 ?><?= $query_string ?>" class="page-btn">
                        <span class="material-symbols-outlined">chevron_left</span> Previous
                    </a>
                <?php else: ?>
                    <span class="page-btn disabled">
                        <span class="material-symbols-outlined">chevron_left</span> Previous
                    </span>
                <?php endif; ?>

                <div class="page-numbers">
                    <?php
                    // Show page numbers with smart ellipsis
                    $range = 2; // Show 2 pages before and after current page
                    
                    for ($i = 1; $i <= $total_pages; $i++) {
                        // Always show first page, last page, and pages around current page
                        if ($i == 1 || $i == $total_pages || ($i >= $current_page - $range && $i <= $current_page + $range)) {
                            if ($i == $current_page) {
                                echo '<span class="page-number active">' . $i . '</span>';
                            } else {
                                echo '<a href="?page=' . $i . $query_string . '" class="page-number">' . $i . '</a>';
                            }
                        } elseif ($i == $current_page - $range - 1 || $i == $current_page + $range + 1) {
                            echo '<span class="page-ellipsis">...</span>';
                        }
                    }
                    ?>
                </div>

                <?php if ($current_page < $total_pages): ?>
                    <a href="?page=<?= $current_page + 1 ?><?= $query_string ?>" class="page-btn">
                        Next <span class="material-symbols-outlined">chevron_right</span>
                    </a>
                <?php else: ?>
                    <span class="page-btn disabled">
                        Next <span class="material-symbols-outlined">chevron_right</span>
                    </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ADD MODAL -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="document.getElementById('addModal').style.display='none'"><span class="material-symbols-outlined">close</span></button>
            <h3>Add Book</h3>
            <form method="POST">
                <div class="form-group"><label>Book ID</label><input type="number" name="book_id" required></div>
                <div class="form-group"><label>Title</label><input type="text" name="title" required></div>
                <div class="form-group"><label>Author</label><input type="text" name="author" required></div>
                <div class="form-group"><label>Category</label><input type="text" name="category"></div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="available">Available</option>
                        <option value="borrowed">Borrowed</option>
                        <option value="damaged">Damaged</option>
                    </select>
                </div>
                <div class="form-group"><label>Total Stock (copies)</label><input type="number" name="stock" min="1" value="1" required></div>
                <button type="submit" name="add_book" class="btn-save">Save Book</button>
                <button type="button" class="btn-cancel" onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
            </form>
        </div>
    </div>

    <!-- EDIT MODAL -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="document.getElementById('editModal').style.display='none'"><span class="material-symbols-outlined">close</span></button>
            <h3>Edit Book</h3>
            <form method="POST">
                <input type="hidden" name="book_id" id="edit_book_id">
                <div class="form-group"><label>Title</label><input type="text" name="title" id="edit_title" required></div>
                <div class="form-group"><label>Author</label><input type="text" name="author" id="edit_author" required></div>
                <div class="form-group"><label>Category</label><input type="text" name="category" id="edit_category"></div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status">
                        <option value="available">Available</option>
                        <option value="borrowed">Borrowed</option>
                        <option value="damaged">Damaged</option>
                    </select>
                </div>
                <div class="form-group"><label>Total Stock (copies)</label><input type="number" name="stock" id="edit_stock" min="1" required></div>
                <button type="submit" name="update_book" class="btn-save">Update Book</button>
                <button type="button" class="btn-cancel" onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
            </form>
        </div>
    </div>

    <!-- DELETE MODAL -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="document.getElementById('deleteModal').style.display='none'"><span class="material-symbols-outlined">close</span></button>
            <h3>Delete Book</h3>
            <p class="delete-warning">Are you sure you want to delete "<span id="delete_title"></span>"?</p>
            <p style="color:#888;font-size:13px;margin-bottom:15px;"><span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;color:#c62828;">warning</span> This will also delete all related transaction and maintenance records.</p>
            <form method="POST">
                <input type="hidden" name="book_id" id="delete_book_id">
                <button type="submit" name="delete_book" class="btn-save" style="background:#c62828;">Confirm Delete</button>
                <button type="button" class="btn-cancel" onclick="document.getElementById('deleteModal').style.display='none'">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function openEdit(id,title,author,category,status,stock){
            document.getElementById('edit_book_id').value=id;
            document.getElementById('edit_title').value=title;
            document.getElementById('edit_author').value=author;
            document.getElementById('edit_category').value=category;
            document.getElementById('edit_status').value=status;
            document.getElementById('edit_stock').value=stock;
            document.getElementById('editModal').style.display='block';
        }
        function openDelete(id,title){
            document.getElementById('delete_book_id').value=id;
            document.getElementById('delete_title').innerText=title;
            document.getElementById('deleteModal').style.display='block';
        }
        window.onclick=function(e){if(e.target.classList.contains('modal'))e.target.style.display='none';}
        
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(function() {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>