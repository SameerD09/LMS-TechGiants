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

$userId   = $_SESSION['user']['id']   ?? null;
$userRole = $_SESSION['user']['role'] ?? 'user';

// ── GET: which books does THIS user currently have borrowed (approved) ──
if ($action === 'getMyBorrowings') {
    $stmt = $conn->prepare(
        "SELECT b.id AS borrowing_id, b.book_id, b.borrow_date, b.due_date,
                COALESCE(SUM(e.extend_days), 0) AS total_extended_days
         FROM borrowings b
         LEFT JOIN borrow_extensions e ON e.borrowing_id = b.id
         WHERE b.user_id = ? AND b.status = 'borrowed'
         GROUP BY b.id"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'borrowing_id'       => intval($row['borrowing_id']),
            'book_id'            => intval($row['book_id']),
            'borrow_date'        => $row['borrow_date'],
            'due_date'           => $row['due_date'],
            'total_extended_days'=> intval($row['total_extended_days'])
        ];
    }
    echo json_encode($rows);

// ── GET: which books are borrowed/pending by ANYONE ──
} elseif ($action === 'getAllBorrowedBookIds') {
    $result = $conn->query(
        "SELECT DISTINCT book_id FROM borrowings WHERE status = 'borrowed'"
    );
    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $ids[] = intval($row['book_id']);
    }
    // Also include books with pending requests
    $result2 = $conn->query(
        "SELECT DISTINCT book_id FROM borrow_requests WHERE status = 'pending'"
    );
    while ($row = $result2->fetch_assoc()) {
        $bid = intval($row['book_id']);
        if (!in_array($bid, $ids)) $ids[] = $bid;
    }
    echo json_encode($ids);

// ── GET: pending request book IDs for this user ──
} elseif ($action === 'getMyPendingRequests') {
    $stmt = $conn->prepare(
        "SELECT book_id FROM borrow_requests WHERE user_id = ? AND status = 'pending'"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $ids[] = intval($row['book_id']);
    }
    echo json_encode($ids);

// ── SUBMIT BORROW REQUEST ──
} elseif ($action === 'requestBorrow') {
    $bookId     = intval($data['book_id']);
    $username   = trim($data['username'] ?? '');
    $borrowDays = intval($data['borrow_days'] ?? 0);
    $note       = trim($data['note'] ?? '');

    if (!$username) {
        echo json_encode(['success' => false, 'error' => 'Username is required.']);
        exit;
    }
    if ($borrowDays < 1 || $borrowDays > 7) {
        echo json_encode(['success' => false, 'error' => 'Borrow duration must be between 1 and 7 days.']);
        exit;
    }

    // Verify username matches the logged-in user
    $uCheck = $conn->prepare("SELECT id FROM users WHERE username = ? AND id = ?");
    $uCheck->bind_param("si", $username, $userId);
    $uCheck->execute();
    $uCheck->store_result();
    if ($uCheck->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Username does not match your account.']);
        exit;
    }

    // Check if book is already actively borrowed
    $check = $conn->prepare(
        "SELECT id FROM borrowings WHERE book_id = ? AND status = 'borrowed'"
    );
    $check->bind_param("i", $bookId);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'This book is already borrowed by someone else.']);
        exit;
    }

    // Check if user already has a pending request for this book
    $check2 = $conn->prepare(
        "SELECT id FROM borrow_requests WHERE book_id = ? AND user_id = ? AND status = 'pending'"
    );
    $check2->bind_param("ii", $bookId, $userId);
    $check2->execute();
    $check2->store_result();
    if ($check2->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'You already have a pending request for this book.']);
        exit;
    }

    // Check if user already has this book borrowed
    $check3 = $conn->prepare(
        "SELECT id FROM borrowings WHERE book_id = ? AND user_id = ? AND status = 'borrowed'"
    );
    $check3->bind_param("ii", $bookId, $userId);
    $check3->execute();
    $check3->store_result();
    if ($check3->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'You already have this book borrowed.']);
        exit;
    }

    $stmt = $conn->prepare(
        "INSERT INTO borrow_requests (user_id, book_id, username, borrow_days, note, status, requested_at)
         VALUES (?, ?, ?, ?, ?, 'pending', NOW())"
    );
    $stmt->bind_param("iisss", $userId, $bookId, $username, $borrowDays, $note);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to submit request.']);
    }

// ── RETURN a book ──
} elseif ($action === 'returnBook') {
    $bookId      = intval($data['book_id']);
    $condition   = trim($data['condition']   ?? 'good');
    $description = trim($data['description'] ?? '');

    // Valid condition values
    $validConditions = ['excellent', 'good', 'fair', 'bad', 'damaged'];
    if (!in_array($condition, $validConditions)) {
        echo json_encode(['success' => false, 'error' => 'Invalid condition value.']);
        exit;
    }

    // Require description for bad/damaged
    if (in_array($condition, ['bad', 'damaged']) && empty($description)) {
        echo json_encode(['success' => false, 'error' => 'Description is required for bad or damaged condition.']);
        exit;
    }

    // Get the borrowing record id
    $sel = $conn->prepare(
        "SELECT id FROM borrowings WHERE user_id = ? AND book_id = ? AND status = 'borrowed'"
    );
    $sel->bind_param("ii", $userId, $bookId);
    $sel->execute();
    $selResult  = $sel->get_result()->fetch_assoc();
    $borrowingId = $selResult ? intval($selResult['id']) : null;

    // Update borrowing status
    $stmt = $conn->prepare(
        "UPDATE borrowings SET status = 'returned', return_date = CURDATE()
         WHERE user_id = ? AND book_id = ? AND status = 'borrowed'"
    );
    $stmt->bind_param("ii", $userId, $bookId);
    $stmt->execute();

    // Insert into book_returns table
    if ($borrowingId) {
        $ins = $conn->prepare(
            "INSERT INTO book_returns (borrowing_id, user_id, book_id, condition_status, description, returned_at)
             VALUES (?, ?, ?, ?, ?, NOW())"
        );
        $ins->bind_param("iiiss", $borrowingId, $userId, $bookId, $condition, $description);
        $ins->execute();
    }

    echo json_encode(['success' => true]);

// ── ADMIN: Get all pending borrow requests ──
} elseif ($action === 'getPendingRequests') {
    if ($userRole !== 'admin') { echo json_encode(['error' => 'Unauthorized']); exit; }
    $result = $conn->query(
        "SELECT br.id, br.user_id, br.book_id, br.username, br.borrow_days, br.note,
                br.status, br.requested_at,
                b.title AS book_title, b.author AS book_author, b.genre AS book_genre,
                u.first_name, u.last_name, u.email
         FROM borrow_requests br
         JOIN books b ON b.id = br.book_id
         JOIN users u ON u.id = br.user_id
         WHERE br.status = 'pending'
         ORDER BY br.requested_at ASC"
    );
    $rows = [];
    while ($row = $result->fetch_assoc()) { $rows[] = $row; }
    echo json_encode($rows);

// ── ADMIN: Get ALL requests (all statuses) ──
} elseif ($action === 'getAllRequests') {
    if ($userRole !== 'admin') { echo json_encode(['error' => 'Unauthorized']); exit; }
    $result = $conn->query(
        "SELECT br.id, br.user_id, br.book_id, br.username, br.borrow_days, br.note,
                br.status, br.requested_at, br.processed_at,
                b.title AS book_title, b.author AS book_author, b.genre AS book_genre,
                u.first_name, u.last_name, u.email
         FROM borrow_requests br
         JOIN books b ON b.id = br.book_id
         JOIN users u ON u.id = br.user_id
         ORDER BY br.requested_at DESC"
    );
    $rows = [];
    while ($row = $result->fetch_assoc()) { $rows[] = $row; }
    echo json_encode($rows);

// ── ADMIN: Approve a request ──
} elseif ($action === 'approveRequest') {
    if ($userRole !== 'admin') { echo json_encode(['error' => 'Unauthorized']); exit; }
    $requestId = intval($data['request_id']);

    $req = $conn->prepare("SELECT * FROM borrow_requests WHERE id = ? AND status = 'pending'");
    $req->bind_param("i", $requestId);
    $req->execute();
    $reqData = $req->get_result()->fetch_assoc();

    if (!$reqData) {
        echo json_encode(['success' => false, 'error' => 'Request not found or already processed.']);
        exit;
    }

    // Check book not already borrowed
    $chk = $conn->prepare("SELECT id FROM borrowings WHERE book_id = ? AND status = 'borrowed'");
    $chk->bind_param("i", $reqData['book_id']);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'Book is already borrowed by someone else.']);
        exit;
    }

    $dueDate = date('Y-m-d', strtotime('+' . intval($reqData['borrow_days']) . ' days'));

    $ins = $conn->prepare(
        "INSERT INTO borrowings (user_id, book_id, borrow_date, due_date, status)
         VALUES (?, ?, CURDATE(), ?, 'borrowed')"
    );
    $ins->bind_param("iis", $reqData['user_id'], $reqData['book_id'], $dueDate);
    $ins->execute();

    $upd = $conn->prepare(
        "UPDATE borrow_requests SET status = 'approved', processed_at = NOW() WHERE id = ?"
    );
    $upd->bind_param("i", $requestId);
    $upd->execute();

    // Auto-reject other pending requests for same book
    $rej = $conn->prepare(
        "UPDATE borrow_requests SET status = 'rejected', processed_at = NOW()
         WHERE book_id = ? AND status = 'pending' AND id != ?"
    );
    $rej->bind_param("ii", $reqData['book_id'], $requestId);
    $rej->execute();

    echo json_encode(['success' => true]);

// ── ADMIN: Reject a request ──
} elseif ($action === 'rejectRequest') {
    if ($userRole !== 'admin') { echo json_encode(['error' => 'Unauthorized']); exit; }
    $requestId = intval($data['request_id']);

    $upd = $conn->prepare(
        "UPDATE borrow_requests SET status = 'rejected', processed_at = NOW()
         WHERE id = ? AND status = 'pending'"
    );
    $upd->bind_param("i", $requestId);
    $upd->execute();

    if ($upd->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Request not found or already processed.']);
    }

// ── ADMIN: Get all returned books with condition info ──
} elseif ($action === 'getReturnedBooks') {
    if ($userRole !== 'admin') { echo json_encode(['error' => 'Unauthorized']); exit; }
    $result = $conn->query(
        "SELECT br.id, br.condition_status, br.description, br.returned_at,
                b.title AS book_title, b.author AS book_author,
                u.first_name, u.last_name, u.username
         FROM book_returns br
         JOIN books b ON b.id = br.book_id
         JOIN users u ON u.id = br.user_id
         ORDER BY br.returned_at DESC"
    );
    $rows = [];
    while ($row = $result->fetch_assoc()) { $rows[] = $row; }
    echo json_encode($rows);

// ── ADMIN STATS ──
} elseif ($action === 'getStats') {
    $borrowed = $conn->query(
        "SELECT COUNT(*) as cnt FROM borrowings WHERE status = 'borrowed'"
    )->fetch_assoc()['cnt'];
    $totalBooks = $conn->query("SELECT COUNT(*) as cnt FROM books")->fetch_assoc()['cnt'];
    $totalUsers = $conn->query("SELECT COUNT(*) as cnt FROM users")->fetch_assoc()['cnt'];
    $pendingCount = $conn->query(
        "SELECT COUNT(*) as cnt FROM borrow_requests WHERE status = 'pending'"
    )->fetch_assoc()['cnt'];

    echo json_encode([
        'borrowed'     => intval($borrowed),
        'available'    => intval($totalBooks) - intval($borrowed),
        'totalBooks'   => intval($totalBooks),
        'totalUsers'   => intval($totalUsers),
        'pendingCount' => intval($pendingCount)
    ]);

// ── EXTEND a borrow ──
} elseif ($action === 'extendBorrow') {
    $bookId     = intval($data['book_id']);
    $extendDays = intval($data['extend_days'] ?? 0);
    $reason     = trim($data['reason'] ?? '');

    if ($extendDays < 1 || $extendDays > 7) {
        echo json_encode(['success' => false, 'error' => 'Extension must be between 1 and 7 days.']);
        exit;
    }
    if (empty($reason)) {
        echo json_encode(['success' => false, 'error' => 'Please provide a reason for extending.']);
        exit;
    }

    // Get the active borrowing
    $sel = $conn->prepare(
        "SELECT b.id, b.due_date, COALESCE(SUM(e.extend_days), 0) AS already_extended
         FROM borrowings b
         LEFT JOIN borrow_extensions e ON e.borrowing_id = b.id
         WHERE b.user_id = ? AND b.book_id = ? AND b.status = 'borrowed'
         GROUP BY b.id"
    );
    $sel->bind_param("ii", $userId, $bookId);
    $sel->execute();
    $borrowing = $sel->get_result()->fetch_assoc();

    if (!$borrowing) {
        echo json_encode(['success' => false, 'error' => 'Active borrowing not found.']);
        exit;
    }

    $alreadyExtended = intval($borrowing['already_extended']);
    if ($alreadyExtended + $extendDays > 7) {
        $remaining = 7 - $alreadyExtended;
        echo json_encode(['success' => false, 'error' => 'You can only extend by up to 7 total extra days. You have ' . $remaining . ' day(s) remaining.']);
        exit;
    }

    $oldDueDate  = $borrowing['due_date'];
    $newDueDate  = date('Y-m-d', strtotime($oldDueDate . ' +' . $extendDays . ' days'));
    $borrowingId = intval($borrowing['id']);

    // Update the due date
    $upd = $conn->prepare("UPDATE borrowings SET due_date = ? WHERE id = ?");
    $upd->bind_param("si", $newDueDate, $borrowingId);
    $upd->execute();

    // Log the extension
    $ins = $conn->prepare(
        "INSERT INTO borrow_extensions (borrowing_id, user_id, book_id, extend_days, reason, old_due_date, new_due_date)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $ins->bind_param("iiissss", $borrowingId, $userId, $bookId, $extendDays, $reason, $oldDueDate, $newDueDate);
    $ins->execute();

    echo json_encode(['success' => true, 'new_due_date' => $newDueDate]);

// ── ADMIN: Get all currently borrowed books ──
} elseif ($action === 'getAllBorrowedBooks') {
    if ($userRole !== 'admin') { echo json_encode(['error' => 'Unauthorized']); exit; }

    $result = $conn->query(
        "SELECT bw.id AS borrowing_id, bw.borrow_date, bw.due_date,
                bk.id AS book_id, bk.title AS book_title, bk.author AS book_author, bk.genre AS book_genre,
                u.id AS user_id, u.first_name, u.last_name, u.username, u.email,
                COALESCE(SUM(e.extend_days), 0) AS total_extended_days,
                COUNT(e.id) AS extension_count
         FROM borrowings bw
         JOIN books bk ON bk.id = bw.book_id
         JOIN users u ON u.id = bw.user_id
         LEFT JOIN borrow_extensions e ON e.borrowing_id = bw.id
         WHERE bw.status = 'borrowed'
         GROUP BY bw.id
         ORDER BY bw.due_date ASC"
    );
    $rows = [];
    while ($row = $result->fetch_assoc()) { $rows[] = $row; }

    foreach ($rows as &$row) {
        $bid = intval($row['borrowing_id']);
        $extResult = $conn->query(
            "SELECT extend_days, reason, old_due_date, new_due_date, requested_at
             FROM borrow_extensions WHERE borrowing_id = $bid ORDER BY requested_at ASC"
        );
        $exts = [];
        while ($er = $extResult->fetch_assoc()) { $exts[] = $er; }
        $row['extensions'] = $exts;
    }
    unset($row);

    echo json_encode($rows);

} else {
    echo json_encode(['error' => 'Unknown action']);
}
?>
