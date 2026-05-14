<?php
session_start();
include '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'member') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Transaction ID required']);
    exit();
}

$transaction_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Verify this transaction belongs to the logged-in user
$check = $conn->query("
    SELECT t.transaction_id, t.book_id 
    FROM transactions t 
    WHERE t.transaction_id = $transaction_id 
    AND t.user_id = $user_id 
    AND t.return_date IS NULL
");

if ($check->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid transaction or already returned']);
    exit();
}

$transaction = $check->fetch_assoc();
$book_id = $transaction['book_id'];

// Update transaction with return date and time
$return_datetime = date('Y-m-d H:i:s');
$update_transaction = $conn->query("
    UPDATE transactions 
    SET return_date = '$return_datetime' 
    WHERE transaction_id = $transaction_id
");

if (!$update_transaction) {
    echo json_encode(['success' => false, 'message' => 'Failed to update transaction']);
    exit();
}

// Update book status to available
$update_book = $conn->query("
    UPDATE books 
    SET status = 'available' 
    WHERE book_id = $book_id
");

if (!$update_book) {
    echo json_encode(['success' => false, 'message' => 'Failed to update book status']);
    exit();
}

echo json_encode(['success' => true, 'message' => 'Book returned successfully']);
?>
