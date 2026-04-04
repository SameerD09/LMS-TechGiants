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
$userId = $_SESSION['user']['id'];

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
            'user' => $row['first_name'] . ' ' . $row['last_name'],
            'text' => $row['comment'],
            'time' => date('M j, Y', strtotime($row['created_at']))
        ];
    }
    echo json_encode($comments);

} elseif ($action === 'addComment') {
    $bookId = intval($data['book_id']);
    $comment = trim($data['comment'] ?? '');
    if (!$comment) {
        echo json_encode(['error' => 'Empty comment']);
        exit;
    }
    $stmt = $conn->prepare("INSERT INTO comments (book_id, user_id, comment) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $bookId, $userId, $comment);
    $stmt->execute();
    echo json_encode(['success' => true]);

} else {
    echo json_encode(['error' => 'Unknown action']);
}
?>
