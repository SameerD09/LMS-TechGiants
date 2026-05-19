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
$userId = intval($_SESSION['user']['id'] ?? 0);

// ── Submit or update a rating ──
if ($action === 'submitRating') {
    $bookId = intval($data['book_id']);
    $rating = intval($data['rating']);
    $review = trim($data['review'] ?? '');

    if ($rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'error' => 'Rating must be 1–5.']);
        exit;
    }

    // Must have borrowed this book at least once
    $chk = $conn->prepare("SELECT id FROM borrowings WHERE user_id=? AND book_id=? AND status IN ('returned','lost','pending_return') LIMIT 1");
    $chk->bind_param("ii", $userId, $bookId);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'You can only rate books you have borrowed.']);
        exit;
    }

    $ins = $conn->prepare(
        "INSERT INTO book_reviews (book_id, user_id, rating, review)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE rating=VALUES(rating), review=VALUES(review), created_at=NOW()"
    );
    $ins->bind_param("iiis", $bookId, $userId, $rating, $review);
    $ins->execute();

    // Return new average
    $avg = $conn->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS total FROM book_reviews WHERE book_id=?");
    $avg->bind_param("i", $bookId);
    $avg->execute();
    $avgRow = $avg->get_result()->fetch_assoc();

    echo json_encode([
        'success'    => true,
        'avg_rating' => round(floatval($avgRow['avg_rating']), 1),
        'total'      => intval($avgRow['total'])
    ]);

// ── Get ratings for a book ──
} elseif ($action === 'getBookRatings') {
    $bookId = intval($data['book_id']);

    $stmt = $conn->prepare(
        "SELECT r.rating, r.review, r.created_at,
                u.first_name, u.last_name, u.username
         FROM book_reviews r
         JOIN users u ON u.id = r.user_id
         WHERE r.book_id = ?
         ORDER BY r.created_at DESC"
    );
    $stmt->bind_param("i", $bookId);
    $stmt->execute();
    $rows = [];
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }

    $avg = $conn->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS total FROM book_reviews WHERE book_id=?");
    $avg->bind_param("i", $bookId);
    $avg->execute();
    $avgRow = $avg->get_result()->fetch_assoc();

    // My existing rating
    $mine = $conn->prepare("SELECT rating, review FROM book_reviews WHERE book_id=? AND user_id=?");
    $mine->bind_param("ii", $bookId, $userId);
    $mine->execute();
    $myRow = $mine->get_result()->fetch_assoc();

    echo json_encode([
        'ratings'    => $rows,
        'avg_rating' => $avgRow['avg_rating'] ? round(floatval($avgRow['avg_rating']), 1) : null,
        'total'      => intval($avgRow['total']),
        'my_rating'  => $myRow ? intval($myRow['rating']) : null,
        'my_review'  => $myRow ? $myRow['review'] : null
    ]);

// ── Admin: get all reviews across all books ──
} elseif ($action === 'getAllReviews') {
    if ($_SESSION['user']['role'] !== 'admin') {
        echo json_encode(['error' => 'Unauthorized']); exit;
    }
    $stmt = $conn->prepare(
        "SELECT r.id, r.rating, r.review, r.created_at,
                b.title AS book_title, b.author AS book_author,
                u.first_name, u.last_name, u.username
         FROM book_reviews r
         JOIN books b ON b.id = r.book_id
         JOIN users u ON u.id = r.user_id
         ORDER BY r.created_at DESC"
    );
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    echo json_encode(['reviews' => $rows]);

} else {
    echo json_encode(['error' => 'Unknown action']);
}
?>