<?php
session_start();
include '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'member') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['type'])) {
    echo json_encode(['success' => false, 'message' => 'Type required']);
    exit();
}

$user_id = $_SESSION['user_id'];
$type = $_GET['type'];
$html = '';
$title = '';

switch ($type) {
    case 'total':
        $title = '<span class="material-symbols-outlined" style="font-size:20px;vertical-align:middle;color:#4e6ef2;">library_books</span> All Borrowed Books';
        
        // Get all borrowed books with dates
        $all_books = $conn->query("
            SELECT b.title, b.author, b.category, t.borrow_date, t.return_date
            FROM transactions t
            JOIN books b ON t.book_id = b.book_id
            WHERE t.user_id = $user_id
            ORDER BY t.borrow_date DESC
            LIMIT 20
        ")->fetch_all(MYSQLI_ASSOC);
        
        if (empty($all_books)) {
            $html = '<div style="text-align:center; padding:20px; color:#888;">No borrowing history yet</div>';
        } else {
            $html = '<div class="details-list">';
            foreach ($all_books as $book) {
                $status = $book['return_date'] ? 'Returned' : 'Currently Borrowed';
                $statusClass = $book['return_date'] ? 'badge-returned' : 'badge-active';
                
                $html .= '<div class="detail-item book-detail-item">';
                $html .= '<div>';
                $html .= '<div class="book-detail-title">' . htmlspecialchars($book['title']) . '</div>';
                $html .= '<div class="book-detail-author">by ' . htmlspecialchars($book['author']) . '</div>';
                $html .= '<div class="book-detail-meta">';
                $html .= '<span class="badge ' . $statusClass . '">' . $status . '</span>';
                $html .= '<span class="book-detail-date">' . date('M j, Y', strtotime($book['borrow_date'])) . '</span>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
            }
            $html .= '</div>';
        }
        break;
        
    case 'month':
        $title = '<span class="material-symbols-outlined" style="font-size:20px;vertical-align:middle;color:#4e6ef2;">calendar_month</span> Books Borrowed This Month';
        
        // Get this month's borrowed books
        $month_books = $conn->query("
            SELECT b.title, b.author, t.borrow_date, t.return_date
            FROM transactions t
            JOIN books b ON t.book_id = b.book_id
            WHERE t.user_id = $user_id
            AND MONTH(t.borrow_date) = MONTH(CURDATE())
            AND YEAR(t.borrow_date) = YEAR(CURDATE())
            ORDER BY t.borrow_date DESC
        ")->fetch_all(MYSQLI_ASSOC);
        
        if (empty($month_books)) {
            $html = '<div style="text-align:center; padding:20px; color:#888;">No books borrowed this month</div>';
        } else {
            $html = '<div class="details-list">';
            foreach ($month_books as $book) {
                $html .= '<div class="detail-item">';
                $html .= '<div>';
                $html .= '<div class="book-detail-title">' . htmlspecialchars($book['title']) . '</div>';
                $html .= '<div class="book-detail-author">by ' . htmlspecialchars($book['author']) . '</div>';
                $html .= '</div>';
                $html .= '<span class="detail-date">' . date('M j', strtotime($book['borrow_date'])) . '</span>';
                $html .= '</div>';
            }
            $html .= '</div>';
        }
        break;
        
    case 'category':
        $title = '<span class="material-symbols-outlined" style="font-size:20px;vertical-align:middle;color:#4e6ef2;">emoji_events</span> Books by Category';
        
        // Get all categories with counts
        $categories = $conn->query("
            SELECT b.category, COUNT(*) as count
            FROM transactions t
            JOIN books b ON t.book_id = b.book_id
            WHERE t.user_id = $user_id
            GROUP BY b.category
            ORDER BY count DESC
        ")->fetch_all(MYSQLI_ASSOC);
        
        if (empty($categories)) {
            $html = '<div style="text-align:center; padding:20px; color:#888;">No data available</div>';
        } else {
            $html = '<div class="details-list">';
            foreach ($categories as $cat) {
                $html .= '<div class="detail-item">';
                $html .= '<span class="detail-category">' . htmlspecialchars($cat['category']) . '</span>';
                $html .= '<span class="detail-count">' . $cat['count'] . ' book' . ($cat['count'] > 1 ? 's' : '') . '</span>';
                $html .= '</div>';
            }
            $html .= '</div>';
        }
        break;
        
    case 'ontime':
        $title = '<span class="material-symbols-outlined" style="font-size:20px;vertical-align:middle;color:#4e6ef2;">check_circle</span> Return History';
        
        // Get return history
        $returns = $conn->query("
            SELECT b.title, t.due_date, t.return_date,
                   CASE 
                       WHEN t.return_date <= t.due_date THEN 'On Time'
                       ELSE 'Late'
                   END as status
            FROM transactions t
            JOIN books b ON t.book_id = b.book_id
            WHERE t.user_id = $user_id
            AND t.return_date IS NOT NULL
            ORDER BY t.return_date DESC
            LIMIT 15
        ")->fetch_all(MYSQLI_ASSOC);
        
        if (empty($returns)) {
            $html = '<div style="text-align:center; padding:20px; color:#888;">No return history yet</div>';
        } else {
            $html = '<div class="details-list">';
            foreach ($returns as $ret) {
                $statusClass = $ret['status'] == 'On Time' ? 'badge-returned' : 'badge-overdue';
                
                $html .= '<div class="detail-item">';
                $html .= '<div>';
                $html .= '<div class="book-detail-title">' . htmlspecialchars($ret['title']) . '</div>';
                $html .= '<div class="book-detail-meta">';
                $html .= '<span class="badge ' . $statusClass . '">' . $ret['status'] . '</span>';
                $html .= '<span class="book-detail-date">Returned: ' . date('M j, Y', strtotime($ret['return_date'])) . '</span>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
            }
            $html .= '</div>';
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid type']);
        exit();
}

echo json_encode(['success' => true, 'title' => $title, 'html' => $html]);
?>
