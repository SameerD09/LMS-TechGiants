<?php
require 'session.php';
require 'db.php';

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$userId = $_SESSION['user']['id'] ?? null;

// ── CSRF check for mutating actions ──────────────────────────────────────────
if ($action === 'addComment') {
    $clientToken = $data['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $clientToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid request. Please refresh and try again.']);
        exit;
    }
}

if ($action === 'getComments') {
    $bookId = intval($data['book_id']);
    $stmt = $conn->prepare("
        SELECT c.comment, c.created_at, u.first_name, u.last_name
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.book_id = ?
        ORDER BY c.created_at ASC
    ");
    $stmt->bind_param("i", $bookId);
    $stmt->execute();
    $result = $stmt->get_result();
    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $comments[] = [
            // htmlspecialchars applied here to prevent XSS when rendered
            'user' => htmlspecialchars($row['first_name'] . ' ' . $row['last_name'], ENT_QUOTES, 'UTF-8'),
            'text' => htmlspecialchars($row['comment'], ENT_QUOTES, 'UTF-8'),
            'time' => date('M j, Y', strtotime($row['created_at']))
        ];
    }
    $stmt->close();
    echo json_encode($comments);

} elseif ($action === 'addComment') {
    $bookId  = intval($data['book_id']);
    $comment = trim($data['comment'] ?? '');

    if (!$comment) {
        echo json_encode(['error' => 'Empty comment']);
        exit;
    }

    // Limit comment length to prevent abuse
    if (mb_strlen($comment) > 1000) {
        echo json_encode(['error' => 'Comment is too long (max 1000 characters).']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO comments (book_id, user_id, comment) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $bookId, $userId, $comment);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);

} else {
    echo json_encode(['error' => 'Unknown action']);
}
?>
