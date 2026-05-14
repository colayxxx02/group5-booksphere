<?php
session_start();
include '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'librarian') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Transaction ID required']);
    exit();
}

$transaction_id = intval($_GET['id']);

$query = $conn->query("
    SELECT t.transaction_id, t.borrow_date, t.due_date, t.return_date,
           b.title as book_title, u.fullname as member_name
    FROM transactions t
    JOIN books b ON t.book_id = b.book_id
    JOIN users u ON t.user_id = u.user_id
    WHERE t.transaction_id = $transaction_id
");

if ($query && $query->num_rows > 0) {
    $transaction = $query->fetch_assoc();
    echo json_encode(['success' => true, 'transaction' => $transaction]);
} else {
    echo json_encode(['success' => false, 'message' => 'Transaction not found']);
}
?>
