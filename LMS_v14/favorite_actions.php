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

if ($action === 'getFavorites') {
    $stmt = $conn->prepare("SELECT book_id FROM favorites WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $favs = [];
    while ($row = $result->fetch_assoc()) {
        $favs[] = intval($row['book_id']);
    }
    echo json_encode($favs);

} elseif ($action === 'addFavorite') {
    $bookId = intval($data['book_id']);
    // INSERT IGNORE prevents duplicate if unique key exists
    $stmt = $conn->prepare("INSERT IGNORE INTO favorites (user_id, book_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $userId, $bookId);
    $stmt->execute();
    echo json_encode(['success' => true]);

} elseif ($action === 'removeFavorite') {
    $bookId = intval($data['book_id']);
    $stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND book_id = ?");
    $stmt->bind_param("ii", $userId, $bookId);
    $stmt->execute();
    echo json_encode(['success' => true]);

} else {
    echo json_encode(['error' => 'Unknown action']);
}
?>
