<?php
require 'session.php';
require 'db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');
$data   = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

// ── Summary stat cards ────────────────────────────────────────────
if ($action === 'summaryStats') {
    $overdueRes = $conn->query(
        "SELECT COUNT(*) AS cnt FROM borrowings
         WHERE status = 'borrowed' AND due_date < CURDATE()"
    );
    $overdue = (int)$overdueRes->fetch_assoc()['cnt'];

    $revRes  = $conn->query("SELECT total FROM admin_balance WHERE id = 1");
    $revenue = (float)($revRes->fetch_assoc()['total'] ?? 0);

    $activeRes = $conn->query(
        "SELECT COUNT(DISTINCT user_id) AS cnt FROM borrowings
         WHERE status = 'borrowed'"
    );
    $activeBorrowers = (int)$activeRes->fetch_assoc()['cnt'];

    $usersRes   = $conn->query("SELECT COUNT(*) AS cnt FROM users WHERE role = 'user'");
    $totalUsers = (int)$usersRes->fetch_assoc()['cnt'];

    echo json_encode([
        'overdue'         => $overdue,
        'revenue'         => $revenue,
        'activeBorrowers' => $activeBorrowers,
        'totalUsers'      => $totalUsers,
    ]);
    exit;
}

// ── Monthly borrowings — last 6 months ───────────────────────────
if ($action === 'monthlyBorrowings') {
    $result = $conn->query(
        "SELECT DATE_FORMAT(borrow_date, '%b %Y') AS month,
                DATE_FORMAT(borrow_date, '%Y-%m')  AS month_key,
                COUNT(*) AS count
         FROM borrowings
         WHERE borrow_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY month_key, month
         ORDER BY month_key ASC"
    );
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    echo json_encode(['data' => $rows]);
    exit;
}

// ── Revenue breakdown by transaction type ─────────────────────────
if ($action === 'revenueBreakdown') {
    $result = $conn->query(
        "SELECT type, SUM(ABS(amount)) AS total
         FROM wallet_transactions
         WHERE type IN ('borrow_fee','extension_fee','pdf_purchase','overdue_fine','damage_fine','lost_book')
         GROUP BY type
         ORDER BY total DESC"
    );
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    echo json_encode(['data' => $rows]);
    exit;
}

// ── Top 8 most-borrowed books ─────────────────────────────────────
if ($action === 'topBooks') {
    $result = $conn->query(
        "SELECT bk.title, bk.author, COUNT(bw.id) AS borrow_count
         FROM borrowings bw
         JOIN books bk ON bk.id = bw.book_id
         GROUP BY bk.id, bk.title, bk.author
         ORDER BY borrow_count DESC
         LIMIT 8"
    );
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    echo json_encode(['data' => $rows]);
    exit;
}

echo json_encode(['error' => 'Unknown action']);
