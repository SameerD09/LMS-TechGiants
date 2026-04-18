<?php
require 'session.php';
require 'db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

if ($action === 'add') {
    $stmt = $conn->prepare("INSERT INTO books (title, author, genre, year, isbn, description) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssiss", $data['title'], $data['author'], $data['genre'], $data['year'], $data['isbn'], $data['desc']);
    $stmt->execute();
    echo json_encode(['success' => true, 'id' => $conn->insert_id]);

} elseif ($action === 'edit') {
    $stmt = $conn->prepare("UPDATE books SET title=?, author=?, genre=?, year=?, isbn=?, description=? WHERE id=?");
    $stmt->bind_param("sssissi", $data['title'], $data['author'], $data['genre'], $data['year'], $data['isbn'], $data['desc'], $data['id']);
    $stmt->execute();
    echo json_encode(['success' => true]);

} elseif ($action === 'delete') {
    $stmt = $conn->prepare("DELETE FROM books WHERE id=?");
    $stmt->bind_param("i", $data['id']);
    $stmt->execute();
    echo json_encode(['success' => true]);

} else {
    echo json_encode(['error' => 'Unknown action']);
}
?>