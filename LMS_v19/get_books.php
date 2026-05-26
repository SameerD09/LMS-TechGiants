<?php
require 'session.php';
require 'db.php';

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

header('Content-Type: application/json');

$userId = intval($_SESSION['user']['id'] ?? 0);

$stmt = $conn->prepare(
    "SELECT b.*,
            ROUND(AVG(r.rating), 1) AS avg_rating,
            COUNT(r.id)             AS review_count,
            MAX(CASE WHEN r.user_id = ? THEN r.rating END) AS my_rating
     FROM books b
     LEFT JOIN book_reviews r ON r.book_id = b.id
     GROUP BY b.id
     ORDER BY b.id ASC"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$books = [];
while ($row = $result->fetch_assoc()) {
    $books[] = $row;
}
$stmt->close();
echo json_encode($books);
?>