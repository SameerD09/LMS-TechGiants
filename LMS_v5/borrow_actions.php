<?php
require 'session.php';
require 'db.php';

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');
$data   = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

// Only set userId if not admin (admin has no id in session)
$userId = $_SESSION['user']['id'] ?? null;

// ── GET: which books does THIS user currently have borrowed ──
if ($action === 'getMyBorrowings') {
    $stmt = $conn->prepare(
        "SELECT book_id FROM borrowings WHERE user_id = ? AND status = 'borrowed'"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $ids[] = intval($row['book_id']);
    }
    echo json_encode($ids);

// ── GET: which books are borrowed by ANYONE ──
} elseif ($action === 'getAllBorrowedBookIds') {
    $result = $conn->query(
        "SELECT DISTINCT book_id FROM borrowings WHERE status = 'borrowed'"
    );
    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $ids[] = intval($row['book_id']);
    }
    echo json_encode($ids);

// ── BORROW a book ──
} elseif ($action === 'borrow') {
    $bookId = intval($data['book_id']);

    // Check if anyone already has this book borrowed
    $check = $conn->prepare(
        "SELECT id FROM borrowings WHERE book_id = ? AND status = 'borrowed'"
    );
    $check->bind_param("i", $bookId);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'Book is already borrowed by someone else.']);
        exit;
    }

    // Check if this user already has this book
    $check2 = $conn->prepare(
        "SELECT id FROM borrowings WHERE book_id = ? AND user_id = ? AND status = 'borrowed'"
    );
    $check2->bind_param("ii", $bookId, $userId);
    $check2->execute();
    $check2->store_result();
    if ($check2->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'You already borrowed this book.']);
        exit;
    }

    $stmt = $conn->prepare(
        "INSERT INTO borrowings (user_id, book_id, borrow_date, status) VALUES (?, ?, CURDATE(), 'borrowed')"
    );
    $stmt->bind_param("ii", $userId, $bookId);
    $stmt->execute();
    echo json_encode(['success' => true]);

// ── RETURN a book ──
} elseif ($action === 'returnBook') {
    $bookId = intval($data['book_id']);
    $stmt = $conn->prepare(
        "UPDATE borrowings SET status = 'returned', return_date = CURDATE()
         WHERE user_id = ? AND book_id = ? AND status = 'borrowed'"
    );
    $stmt->bind_param("ii", $userId, $bookId);
    $stmt->execute();
    echo json_encode(['success' => true]);

// ── ADMIN STATS ──
} elseif ($action === 'getStats') {
    $borrowed = $conn->query(
        "SELECT COUNT(*) as cnt FROM borrowings WHERE status = 'borrowed'"
    )->fetch_assoc()['cnt'];

    $totalBooks = $conn->query(
        "SELECT COUNT(*) as cnt FROM books"
    )->fetch_assoc()['cnt'];

    $totalUsers = $conn->query(
        "SELECT COUNT(*) as cnt FROM users"
    )->fetch_assoc()['cnt'];

    echo json_encode([
        'borrowed'   => intval($borrowed),
        'available'  => intval($totalBooks) - intval($borrowed),
        'totalBooks' => intval($totalBooks),
        'totalUsers' => intval($totalUsers)
    ]);

} else {
    echo json_encode(['error' => 'Unknown action']);
}
?>