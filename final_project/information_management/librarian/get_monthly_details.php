<?php
session_start();
include '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'librarian') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['type'])) {
    echo json_encode(['success' => false, 'message' => 'Type required']);
    exit();
}

$type = $_GET['type'];
$html = '';
$title = '';

switch ($type) {
    case 'borrows':
        $title = '<span class="material-symbols-outlined" style="font-size:20px;vertical-align:middle;color:#4e6ef2;">upload</span> Monthly Borrows Breakdown';
        
        // Get daily borrows for current month
        $daily_borrows = $conn->query("
            SELECT DATE(borrow_date) as date, COUNT(*) as count
            FROM transactions
            WHERE MONTH(borrow_date) = MONTH(CURDATE()) 
            AND YEAR(borrow_date) = YEAR(CURDATE())
            GROUP BY DATE(borrow_date)
            ORDER BY date DESC
            LIMIT 10
        ")->fetch_all(MYSQLI_ASSOC);
        
        if (empty($daily_borrows)) {
            $html = '<div style="text-align:center; padding:20px; color:#888;">No borrows this month</div>';
        } else {
            $html = '<div class="details-list">';
            foreach ($daily_borrows as $day) {
                $html .= '<div class="detail-item">';
                $html .= '<span class="detail-date">' . date('M j, Y', strtotime($day['date'])) . '</span>';
                $html .= '<span class="detail-count">' . $day['count'] . ' book' . ($day['count'] > 1 ? 's' : '') . '</span>';
                $html .= '</div>';
            }
            $html .= '</div>';
        }
        break;
        
    case 'returns':
        $title = '<span class="material-symbols-outlined" style="font-size:20px;vertical-align:middle;color:#4e6ef2;">download</span> Monthly Returns Breakdown';
        
        // Get daily returns for current month
        $daily_returns = $conn->query("
            SELECT DATE(return_date) as date, COUNT(*) as count
            FROM transactions
            WHERE MONTH(return_date) = MONTH(CURDATE()) 
            AND YEAR(return_date) = YEAR(CURDATE())
            AND return_date IS NOT NULL
            GROUP BY DATE(return_date)
            ORDER BY date DESC
            LIMIT 10
        ")->fetch_all(MYSQLI_ASSOC);
        
        if (empty($daily_returns)) {
            $html = '<div style="text-align:center; padding:20px; color:#888;">No returns this month</div>';
        } else {
            $html = '<div class="details-list">';
            foreach ($daily_returns as $day) {
                $html .= '<div class="detail-item">';
                $html .= '<span class="detail-date">' . date('M j, Y', strtotime($day['date'])) . '</span>';
                $html .= '<span class="detail-count">' . $day['count'] . ' book' . ($day['count'] > 1 ? 's' : '') . '</span>';
                $html .= '</div>';
            }
            $html .= '</div>';
        }
        break;
        
    case 'category':
        $title = '<span class="material-symbols-outlined" style="font-size:20px;vertical-align:middle;color:#4e6ef2;">emoji_events</span> Category Breakdown';
        
        // Get all categories with borrow counts
        $categories = $conn->query("
            SELECT b.category, COUNT(*) as count
            FROM transactions t
            JOIN books b ON t.book_id = b.book_id
            WHERE MONTH(t.borrow_date) = MONTH(CURDATE()) 
            AND YEAR(t.borrow_date) = YEAR(CURDATE())
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
                $html .= '<span class="detail-count">' . $cat['count'] . ' borrow' . ($cat['count'] > 1 ? 's' : '') . '</span>';
                $html .= '</div>';
            }
            $html .= '</div>';
        }
        break;
        
    case 'members':
        $title = '<span class="material-symbols-outlined" style="font-size:20px;vertical-align:middle;color:#4e6ef2;">group_add</span> New Members This Month';
        
        // Get new members
        $new_members = $conn->query("
            SELECT fullname, email, created_at
            FROM users
            WHERE role = 'member'
            AND MONTH(created_at) = MONTH(CURDATE()) 
            AND YEAR(created_at) = YEAR(CURDATE())
            ORDER BY created_at DESC
        ")->fetch_all(MYSQLI_ASSOC);
        
        if (empty($new_members)) {
            $html = '<div style="text-align:center; padding:20px; color:#888;">No new members this month</div>';
        } else {
            $html = '<div class="details-list">';
            foreach ($new_members as $member) {
                $html .= '<div class="detail-item member-item">';
                $html .= '<div>';
                $html .= '<div class="member-name">' . htmlspecialchars($member['fullname']) . '</div>';
                $html .= '<div class="member-email">' . htmlspecialchars($member['email']) . '</div>';
                $html .= '</div>';
                $html .= '<span class="detail-date">' . date('M j', strtotime($member['created_at'])) . '</span>';
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
