<?php
require 'session.php';
require 'db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$type = $_GET['type'] ?? 'borrowings';

if ($type === 'borrowings') {
    $result = $conn->query(
        "SELECT bw.id, bk.title AS book_title, bk.author,
                u.first_name, u.last_name, u.username, u.email,
                bw.borrow_date, bw.due_date, bw.return_date, bw.status,
                COALESCE(SUM(e.extend_days), 0) AS total_extended_days
         FROM borrowings bw
         JOIN books bk ON bk.id = bw.book_id
         JOIN users u  ON u.id  = bw.user_id
         LEFT JOIN borrow_extensions e ON e.borrowing_id = bw.id
         GROUP BY bw.id
         ORDER BY bw.borrow_date DESC"
    );

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="borrowings_report_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel

    fputcsv($out, ['ID', 'Book Title', 'Author', 'First Name', 'Last Name', 'Username', 'Email',
                    'Borrow Date', 'Due Date', 'Return Date', 'Status', 'Extended Days']);

    while ($row = $result->fetch_assoc()) {
        fputcsv($out, [
            $row['id'], $row['book_title'], $row['author'],
            $row['first_name'], $row['last_name'], $row['username'], $row['email'],
            $row['borrow_date'], $row['due_date'], $row['return_date'] ?? '',
            $row['status'], $row['total_extended_days']
        ]);
    }
    fclose($out);

} elseif ($type === 'fines') {
    $result = $conn->query(
        "SELECT wt.id, u.first_name, u.last_name, u.username, u.email,
                bk.title AS book_title, wt.type, wt.amount, wt.description, wt.created_at
         FROM wallet_transactions wt
         JOIN users u ON u.id = wt.user_id
         LEFT JOIN books bk ON bk.id = wt.book_id
         WHERE wt.type IN ('overdue_fine','damage_fine','lost_book','extension_fee')
         ORDER BY wt.created_at DESC"
    );

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="fines_report_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($out, ['ID', 'First Name', 'Last Name', 'Username', 'Email', 'Book', 'Type', 'Amount', 'Description', 'Date']);
    while ($row = $result->fetch_assoc()) {
        fputcsv($out, [
            $row['id'], $row['first_name'], $row['last_name'], $row['username'], $row['email'],
            $row['book_title'] ?? '—', $row['type'], $row['amount'], $row['description'], $row['created_at']
        ]);
    }
    fclose($out);

} else {
    http_response_code(400);
    exit('Unknown export type');
}
exit;
?>