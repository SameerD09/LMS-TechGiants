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
    $color = $data['color'] ?? 'color-1';
    $stmt = $conn->prepare("INSERT INTO books (title, author, genre, year, isbn, description, color) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssisss", $data['title'], $data['author'], $data['genre'], $data['year'], $data['isbn'], $data['desc'], $color);
    $stmt->execute();
    echo json_encode(['success' => true, 'id' => $conn->insert_id]);

} elseif ($action === 'edit') {
    $color = $data['color'] ?? 'color-1';
    $stmt = $conn->prepare("UPDATE books SET title=?, author=?, genre=?, year=?, isbn=?, description=?, color=? WHERE id=?");
    $stmt->bind_param("sssisssi", $data['title'], $data['author'], $data['genre'], $data['year'], $data['isbn'], $data['desc'], $color, $data['id']);
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