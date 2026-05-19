<?php
require 'session.php';
require 'db.php';
require 'notifications_helper.php';

// ── Pricing constants (keep in sync with wallet_actions.php) ──
define('BORROW_FEE_PER_DAY',   100.00);
define('EXTENSION_FEE_PER_DAY', 80.00);
define('OVERDUE_FINE_PER_DAY', 100.00);
define('DAMAGE_FINE_FAIR',     200.00);
define('DAMAGE_FINE_BAD',      500.00);
define('DAMAGE_FINE_PCT',        0.80);
define('DEFAULT_BOOK_PRICE',  1500.00);

// ── Internal helpers ──────────────────────────────────────────
function _chargeUser($conn, $userId, $amount, $type, $description, $refId = null, $bookId = null) {
    $sel = $conn->prepare("SELECT balance FROM users WHERE id = ?");
    $sel->bind_param("i", $userId);
    $sel->execute();
    $row     = $sel->get_result()->fetch_assoc();
    $balance = $row ? floatval($row['balance']) : 0;
    if ($balance < $amount) {
        return ['ok' => false, 'error' => 'Insufficient balance (Rs ' . number_format($balance,2) . ' available, Rs ' . number_format($amount,2) . ' needed).'];
    }
    $upd = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
    $upd->bind_param("di", $amount, $userId);
    $upd->execute();
    $neg  = -$amount;
    $ins  = $conn->prepare("INSERT INTO wallet_transactions (user_id,type,amount,description,reference_id,book_id) VALUES (?,?,?,?,?,?)");
    $ins->bind_param("isdsii", $userId, $type, $neg, $description, $refId, $bookId);
    $ins->execute();
    $conn->query("UPDATE admin_balance SET total = total + " . floatval($amount) . " WHERE id = 1");
    return ['ok' => true, 'new_balance' => round($balance - $amount, 2)];
}
function _getBookPrice($conn, $bookId) {
    $s = $conn->prepare("SELECT price FROM books WHERE id = ?");
    $s->bind_param("i", $bookId);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    return $r ? floatval($r['price']) : DEFAULT_BOOK_PRICE;
}

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
                b.lost_reported,
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
            'lost_reported'      => intval($row['lost_reported']),
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

// ── GET: rejected requests for this user (unseen) ──
} elseif ($action === 'getMyRejectedRequests') {
    $stmt = $conn->prepare(
        "SELECT br.id, br.book_id, br.requested_at, br.processed_at,
                b.title AS book_title, b.author AS book_author
         FROM borrow_requests br
         JOIN books b ON b.id = br.book_id
         WHERE br.user_id = ? AND br.status = 'rejected' AND (br.seen_by_user = 0 OR br.seen_by_user IS NULL)
         ORDER BY br.processed_at DESC"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) { $rows[] = $row; }
    echo json_encode($rows);

// ── MARK rejected requests as seen ──
} elseif ($action === 'markRejectedSeen') {
    $ids = $data['ids'] ?? [];
    if (!empty($ids)) {
        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $stmt = $conn->prepare("UPDATE borrow_requests SET seen_by_user = 1 WHERE id IN ($placeholders) AND user_id = ?");
        $allParams = array_merge($ids, [$userId]);
        $types .= 'i';
        $stmt->bind_param($types, ...$allParams);
        $stmt->execute();
    }
    echo json_encode(['success' => true]);

// ── SUBMIT BORROW REQUEST ──
} elseif ($action === 'requestBorrow') {
    $bookId     = intval($data['book_id']);
    $username   = trim($data['username'] ?? '');
    $borrowDays = intval($data['borrow_days'] ?? 0);
    $note       = trim($data['note'] ?? '');
    $language   = in_array($data['language'] ?? '', ['english', 'nepali']) ? $data['language'] : 'english';

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
        "INSERT INTO borrow_requests (user_id, book_id, username, borrow_days, note, language, status, requested_at)
         VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())"
    );
    $stmt->bind_param("iissss", $userId, $bookId, $username, $borrowDays, $note, $language);
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
        "SELECT id, due_date FROM borrowings WHERE user_id = ? AND book_id = ? AND status = 'borrowed'"
    );
    $sel->bind_param("ii", $userId, $bookId);
    $sel->execute();
    $selResult   = $sel->get_result()->fetch_assoc();
    $borrowingId  = $selResult ? intval($selResult['id']) : null;
    $dueDate      = $selResult ? $selResult['due_date'] : null;

    // Mark as pending_return — admin must verify condition before finalising
    $stmt = $conn->prepare(
        "UPDATE borrowings SET status = 'pending_return'
         WHERE user_id = ? AND book_id = ? AND status = 'borrowed'"
    );
    $stmt->bind_param("ii", $userId, $bookId);
    $stmt->execute();

    // Record the user's claimed condition in book_returns
    if ($borrowingId) {
        $ins = $conn->prepare(
            "INSERT INTO book_returns (borrowing_id, user_id, book_id, condition_status, description, returned_at)
             VALUES (?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE condition_status=VALUES(condition_status), description=VALUES(description), returned_at=NOW()"
        );
        $ins->bind_param("iiiss", $borrowingId, $userId, $bookId, $condition, $description);
        $ins->execute();
    }

    // Notify user that return is pending admin verification
    $bkR = $conn->prepare("SELECT title FROM books WHERE id = ?");
    $bkR->bind_param("i", $bookId);
    $bkR->execute();
    $bkTitle = ($bkR->get_result()->fetch_assoc())['title'] ?? 'Book';

    pushNotification($conn, (int)$userId, 'book_returned',
        'Return Submitted — Pending Verification',
        "Your return of \"{$bkTitle}\" has been submitted. An admin will verify the book condition and finalise the return shortly."
    );

    echo json_encode(['success' => true, 'pending' => true]);

// ── ADMIN: Get all pending borrow requests ──
} elseif ($action === 'getPendingReturns') {
    if ($userRole !== 'admin') { echo json_encode(['error' => 'Unauthorized']); exit; }
    $rows = $conn->query(
        "SELECT br.id AS return_id, br.condition_status AS user_condition,
                br.description AS user_description, br.returned_at,
                bw.id AS borrowing_id, bw.due_date, bw.borrow_date,
                b.id AS book_id, b.title AS book_title, b.author AS book_author, b.price AS book_price,
                u.id AS user_id, u.first_name, u.last_name, u.username
         FROM book_returns br
         JOIN borrowings bw ON bw.id = br.borrowing_id
         JOIN books b ON b.id = br.book_id
         JOIN users u ON u.id = br.user_id
         WHERE bw.status = 'pending_return'
         ORDER BY br.returned_at DESC"
    )->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['returns' => $rows]);

} elseif ($action === 'approveReturn') {
    if ($userRole !== 'admin') { echo json_encode(['error' => 'Unauthorized']); exit; }

    $returnId      = intval($data['return_id']);
    $realCondition = trim($data['real_condition'] ?? '');
    $adminNotes    = trim($data['admin_notes']    ?? '');
    $validCond     = ['excellent', 'good', 'fair', 'bad', 'damaged'];
    if (!in_array($realCondition, $validCond)) {
        echo json_encode(['success' => false, 'error' => 'Invalid condition.']); exit;
    }

    // Fetch the pending return
    $sel = $conn->prepare(
        "SELECT br.borrowing_id, br.user_id, br.book_id,
                bw.due_date, bw.borrow_date
         FROM book_returns br
         JOIN borrowings bw ON bw.id = br.borrowing_id
         WHERE br.id = ? AND bw.status = 'pending_return'"
    );
    $sel->bind_param("i", $returnId);
    $sel->execute();
    $ret = $sel->get_result()->fetch_assoc();
    if (!$ret) { echo json_encode(['success' => false, 'error' => 'Return not found or already processed.']); exit; }

    $borrowingId = intval($ret['borrowing_id']);
    $targetUser  = intval($ret['user_id']);
    $bookId      = intval($ret['book_id']);
    $dueDate     = $ret['due_date'];

    // Update book_returns with admin's real condition
    $upd = $conn->prepare(
        "UPDATE book_returns SET condition_status=?, description=? WHERE id=?"
    );
    $desc = $adminNotes ?: null;
    $upd->bind_param("ssi", $realCondition, $desc, $returnId);
    $upd->execute();

    // Finalise borrowing as returned
    $fin = $conn->prepare(
        "UPDATE borrowings SET status='returned', return_date=CURDATE() WHERE id=?"
    );
    $fin->bind_param("i", $borrowingId);
    $fin->execute();

    // Book details for fines
    $bkR = $conn->prepare("SELECT title, price FROM books WHERE id=?");
    $bkR->bind_param("i", $bookId);
    $bkR->execute();
    $bkD     = $bkR->get_result()->fetch_assoc();
    $bkTitle = $bkD ? $bkD['title'] : 'Book';

    $finesCharged = [];
    $totalFine    = 0;

    // Overdue fine
    if ($dueDate) {
        $dueDt = new DateTime($dueDate);
        $today = new DateTime(date('Y-m-d'));
        if ($today > $dueDt) {
            $lateDays = intval($today->diff($dueDt)->days);
            if ($lateDays > 0) {
                $fine = $lateDays * OVERDUE_FINE_PER_DAY;
                _chargeUser($conn, $targetUser, $fine, 'overdue_fine',
                    'Overdue fine: "' . $bkTitle . '" — ' . $lateDays . ' day(s) late', $borrowingId, $bookId);
                $finesCharged[] = ['label' => 'Overdue (' . $lateDays . ' days)', 'amount' => $fine];
                $totalFine += $fine;
            }
        }
    }

    // Damage fine (based on admin's real condition)
    $damageFine = 0;
    if ($realCondition === 'fair')    $damageFine = DAMAGE_FINE_FAIR;
    elseif ($realCondition === 'bad') $damageFine = DAMAGE_FINE_BAD;
    elseif ($realCondition === 'damaged') {
        $bookPrice  = $bkD ? floatval($bkD['price']) : DEFAULT_BOOK_PRICE;
        $damageFine = round($bookPrice * DAMAGE_FINE_PCT, 2);
    }
    if ($damageFine > 0) {
        _chargeUser($conn, $targetUser, $damageFine, 'damage_fine',
            'Damage fine: "' . $bkTitle . '" — condition: ' . $realCondition, $borrowingId, $bookId);
        $finesCharged[] = ['label' => 'Damage (' . $realCondition . ')', 'amount' => $damageFine];
        $totalFine += $damageFine;
    }

    // Notify user of final result
    if ($totalFine > 0) {
        $fineDetails = implode(', ', array_map(
            fn($f) => $f['label'] . ' (Rs ' . number_format($f['amount'], 2) . ')',
            $finesCharged
        ));
        pushNotification($conn, $targetUser, 'book_returned',
            'Return Approved — Fines Charged',
            "Your return of \"{$bkTitle}\" has been verified. Admin condition: {$realCondition}. " .
            "Fines: {$fineDetails}. Total deducted: Rs " . number_format($totalFine, 2) . "."
        );
    } else {
        pushNotification($conn, $targetUser, 'book_returned',
            'Return Approved',
            "Your return of \"{$bkTitle}\" has been verified and approved. No fines — thanks!"
        );
    }

    // Notify first waitlisted user
    $wl = $conn->prepare(
        "SELECT w.user_id, w.id AS wl_id FROM borrow_waitlist w
         WHERE w.book_id=? AND w.status='waiting' ORDER BY w.position ASC LIMIT 1"
    );
    $wl->bind_param("i", $bookId);
    $wl->execute();
    $wlRow = $wl->get_result()->fetch_assoc();
    if ($wlRow) {
        $wlId = intval($wlRow['wl_id']);
        pushNotification($conn, intval($wlRow['user_id']), 'waitlist_ready',
            'Book Now Available!',
            "\"{$bkTitle}\" is now available. Head to the library to borrow it!"
        );
        $conn->query("UPDATE borrow_waitlist SET status='notified', notified_at=NOW() WHERE id=$wlId");
    }

    echo json_encode(['success' => true, 'total_fine' => $totalFine, 'fines' => $finesCharged]);

} elseif ($action === 'getPendingRequests') {
    if ($userRole !== 'admin') { echo json_encode(['error' => 'Unauthorized']); exit; }
    $result = $conn->query(
        "SELECT br.id, br.user_id, br.book_id, br.username, br.borrow_days, br.note, br.language,
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
        "SELECT br.id, br.user_id, br.book_id, br.username, br.borrow_days, br.note, br.language,
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

    $req = $conn->prepare("SELECT br.*, b.title AS book_title FROM borrow_requests br JOIN books b ON b.id = br.book_id WHERE br.id = ? AND br.status = 'pending'");
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
    $newBorrowingId = $conn->insert_id;

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

    $bkTitle = $reqData['book_title'] ?? 'Book';

    pushNotification(
        $conn, (int)$reqData['user_id'], 'borrow_approved',
        'Borrow Request Approved',
        "Your request to borrow \"{$bkTitle}\" has been approved! Due date: {$dueDate}."
    );

    echo json_encode(['success' => true]);

// ── ADMIN: Reject a request ──
} elseif ($action === 'rejectRequest') {
    if ($userRole !== 'admin') { echo json_encode(['error' => 'Unauthorized']); exit; }
    $requestId = intval($data['request_id']);

    // Fetch request details before rejecting so we can notify the user
    $rjFetch = $conn->prepare(
        "SELECT br.user_id, b.title AS book_title
         FROM borrow_requests br JOIN books b ON b.id = br.book_id
         WHERE br.id = ? AND br.status = 'pending'"
    );
    $rjFetch->bind_param("i", $requestId);
    $rjFetch->execute();
    $rjData = $rjFetch->get_result()->fetch_assoc();
    $rjFetch->close();

    $upd = $conn->prepare(
        "UPDATE borrow_requests SET status = 'rejected', processed_at = NOW()
         WHERE id = ? AND status = 'pending'"
    );
    $upd->bind_param("i", $requestId);
    $upd->execute();

    if ($upd->affected_rows > 0) {
        if ($rjData) {
            pushNotification(
                $conn, (int)$rjData['user_id'], 'borrow_rejected',
                'Borrow Request Rejected',
                "Your request to borrow \"{$rjData['book_title']}\" was not approved by the admin."
            );
        }
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
    $pendingReturnsCount = $conn->query(
        "SELECT COUNT(*) as cnt FROM borrowings WHERE status = 'pending_return'"
    )->fetch_assoc()['cnt'];

    echo json_encode([
        'borrowed'            => intval($borrowed),
        'available'           => intval($totalBooks) - intval($borrowed),
        'totalBooks'          => intval($totalBooks),
        'totalUsers'          => intval($totalUsers),
        'pendingCount'        => intval($pendingCount),
        'pendingReturnsCount' => intval($pendingReturnsCount)
    ]);

// ── EXTEND a borrow ──
} elseif ($action === 'extendBorrow') {
    $bookId     = intval($data['book_id']);
    $extendDays = intval($data['extend_days'] ?? 0);
    $reason     = trim($data['reason'] ?? '');

    if ($extendDays < 1) {
        echo json_encode(['success' => false, 'error' => 'Extension must be at least 1 day.']);
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
    $newExtId = $conn->insert_id;

    // ── Charge extension fee ────────────────────────────────
    $bkRow = $conn->prepare("SELECT title FROM books WHERE id = ?");
    $bkRow->bind_param("i", $bookId);
    $bkRow->execute();
    $bkData  = $bkRow->get_result()->fetch_assoc();
    $bkTitle = $bkData ? $bkData['title'] : 'Book';
    $extFee  = $extendDays * EXTENSION_FEE_PER_DAY;
    $extDesc = 'Extension fee: "' . $bkTitle . '" +' . $extendDays . ' day(s)';
    $chargeResult = _chargeUser($conn, $userId, $extFee, 'extension_fee', $extDesc, $newExtId, $bookId);

    pushNotification(
        $conn, (int)$userId, 'borrow_extended',
        'Borrow Extended',
        "Your borrow of \"{$bkTitle}\" has been extended by {$extendDays} day(s). " .
        "New due date: {$newDueDate}. Rs " . number_format($extFee, 2) . " deducted from your wallet."
    );

    echo json_encode([
        'success'         => true,
        'new_due_date'    => $newDueDate,
        'fee_charged'     => $extFee,
        'charge_ok'       => $chargeResult['ok'],
        'charge_error'    => $chargeResult['ok'] ? null : ($chargeResult['error'] ?? null),
        'new_balance'     => $chargeResult['new_balance'] ?? null
    ]);

// ── ADMIN: Get all currently borrowed books ──
} elseif ($action === 'getAllBorrowedBooks') {
    if ($userRole !== 'admin') { echo json_encode(['error' => 'Unauthorized']); exit; }

    $result = $conn->query(
        "SELECT bw.id AS borrowing_id, bw.borrow_date, bw.due_date, bw.lost_reported,
                bk.id AS book_id, bk.title AS book_title, bk.author AS book_author, bk.genre AS book_genre, bk.price AS book_price,
                u.id AS user_id, u.first_name, u.last_name, u.username, u.email,
                COALESCE(SUM(e.extend_days), 0) AS total_extended_days,
                COUNT(e.id) AS extension_count
         FROM borrowings bw
         JOIN books bk ON bk.id = bw.book_id
         JOIN users u ON u.id = bw.user_id
         LEFT JOIN borrow_extensions e ON e.borrowing_id = bw.id
         WHERE bw.status = 'borrowed'
         GROUP BY bw.id
         ORDER BY bw.lost_reported DESC, bw.due_date ASC"
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

// ── REPORT BOOK AS LOST (flag only — admin confirms and charges) ──
} elseif ($action === 'reportLost') {
    $bookId = intval($data['book_id']);

    $sel = $conn->prepare(
        "SELECT bw.id AS borrowing_id, bk.title, bk.price
         FROM borrowings bw
         JOIN books bk ON bk.id = bw.book_id
         WHERE bw.user_id = ? AND bw.book_id = ? AND bw.status = 'borrowed'"
    );
    $sel->bind_param("ii", $userId, $bookId);
    $sel->execute();
    $borrowing = $sel->get_result()->fetch_assoc();

    if (!$borrowing) {
        echo json_encode(['success' => false, 'error' => 'Active borrowing not found.']);
        exit;
    }

    $borrowingId = intval($borrowing['borrowing_id']);
    $bkTitle     = $borrowing['title'] ?? 'Book';
    $bookPrice   = $borrowing['price'] ? floatval($borrowing['price']) : DEFAULT_BOOK_PRICE;

    // Just set the flag — admin will confirm and charge
    $upd = $conn->prepare("UPDATE borrowings SET lost_reported = 1 WHERE id = ?");
    $upd->bind_param("i", $borrowingId);
    $upd->execute();

    pushNotification(
        $conn, (int)$userId, 'book_lost_pending',
        'Lost Book Report Submitted',
        "You've reported \"{$bkTitle}\" as lost. The admin will review and process the charge (Rs " . number_format($bookPrice, 2) . "). The book remains on your account until confirmed."
    );

    echo json_encode([
        'success'    => true,
        'book_price' => $bookPrice
    ]);

// ── CANCEL LOST REPORT (user changes mind) ──
} elseif ($action === 'cancelLostReport') {
    $bookId = intval($data['book_id']);
    $upd = $conn->prepare(
        "UPDATE borrowings SET lost_reported = 0
         WHERE user_id = ? AND book_id = ? AND status = 'borrowed' AND lost_reported = 1"
    );
    $upd->bind_param("ii", $userId, $bookId);
    $upd->execute();
    echo json_encode(['success' => $upd->affected_rows > 0]);

// ── ADMIN: Force-mark a borrowing as lost ──
} elseif ($action === 'adminMarkLost') {
    if ($userRole !== 'admin') { echo json_encode(['error' => 'Unauthorized']); exit; }
    $borrowingId = intval($data['borrowing_id']);

    $sel = $conn->prepare(
        "SELECT bw.user_id, bw.book_id, bw.lost_reported, bk.title, bk.price
         FROM borrowings bw JOIN books bk ON bk.id = bw.book_id
         WHERE bw.id = ? AND bw.status = 'borrowed'"
    );
    $sel->bind_param("i", $borrowingId);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();
    if (!$row) { echo json_encode(['success'=>false,'error'=>'Borrowing not found or already resolved.']); exit; }
    if (!intval($row['lost_reported'])) { echo json_encode(['success'=>false,'error'=>'User has not reported this book as lost.']); exit; }

    $targetUserId = intval($row['user_id']);
    $bookId       = intval($row['book_id']);
    $bkTitle      = $row['title'] ?? 'Book';
    $bookPrice    = $row['price'] ? floatval($row['price']) : DEFAULT_BOOK_PRICE;

    $upd2 = $conn->prepare("UPDATE borrowings SET status='lost', return_date=CURDATE() WHERE id=?");
    $upd2->bind_param("i", $borrowingId);
    $upd2->execute();

    $ins = $conn->prepare("INSERT INTO book_returns (borrowing_id,user_id,book_id,condition_status,description,returned_at) VALUES (?,?,?,'lost','Marked as lost by admin',NOW())");
    $ins->bind_param("iii", $borrowingId, $targetUserId, $bookId);
    $ins->execute();

    $desc = 'Lost book charge (admin): "' . $bkTitle . '"';
    _chargeUser($conn, $targetUserId, $bookPrice, 'lost_book', $desc, $borrowingId, $bookId);

    pushNotification($conn, $targetUserId, 'book_lost',
        'Book Marked as Lost by Admin',
        "The book \"{$bkTitle}\" has been marked as lost by the admin. Rs " . number_format($bookPrice, 2) . " has been charged to your wallet."
    );

    // Notify waitlist
    $wlA = $conn->prepare("SELECT user_id, id AS wl_id FROM borrow_waitlist WHERE book_id=? AND status='waiting' ORDER BY position ASC LIMIT 1");
    $wlA->bind_param("i", $bookId);
    $wlA->execute();
    $wlARow = $wlA->get_result()->fetch_assoc();
    if ($wlARow) {
        $wlAId = intval($wlARow['wl_id']);
        pushNotification($conn, intval($wlARow['user_id']), 'waitlist_ready', 'Book Now Available!',
            "\"{$bkTitle}\" is now available to borrow!");
        $conn->query("UPDATE borrow_waitlist SET status='notified', notified_at=NOW() WHERE id=$wlAId");
    }

    echo json_encode(['success'=>true, 'book_price'=>$bookPrice]);

// ── WAITLIST: join ──
} elseif ($action === 'joinWaitlist') {
    $bookId = intval($data['book_id']);

    // Can't join if you already borrow or have pending request
    $chk = $conn->prepare("SELECT id FROM borrowings WHERE user_id=? AND book_id=? AND status='borrowed'");
    $chk->bind_param("ii", $userId, $bookId);
    $chk->execute(); $chk->store_result();
    if ($chk->num_rows > 0) { echo json_encode(['success'=>false,'error'=>'You already have this book borrowed.']); exit; }

    $chk2 = $conn->prepare("SELECT id FROM borrow_requests WHERE user_id=? AND book_id=? AND status='pending'");
    $chk2->bind_param("ii", $userId, $bookId);
    $chk2->execute(); $chk2->store_result();
    if ($chk2->num_rows > 0) { echo json_encode(['success'=>false,'error'=>'You already have a pending request for this book.']); exit; }

    // Get next position
    $posRes = $conn->prepare("SELECT COALESCE(MAX(position),0)+1 AS next_pos FROM borrow_waitlist WHERE book_id=? AND status='waiting'");
    $posRes->bind_param("i", $bookId);
    $posRes->execute();
    $nextPos = intval($posRes->get_result()->fetch_assoc()['next_pos']);

    $ins = $conn->prepare("INSERT INTO borrow_waitlist (book_id,user_id,position) VALUES (?,?,?) ON DUPLICATE KEY UPDATE status='waiting', position=?, joined_at=NOW()");
    $ins->bind_param("iiii", $bookId, $userId, $nextPos, $nextPos);
    $ins->execute();

    echo json_encode(['success'=>true, 'position'=>$nextPos]);

// ── WAITLIST: leave ──
} elseif ($action === 'leaveWaitlist') {
    $bookId = intval($data['book_id']);
    $upd = $conn->prepare("UPDATE borrow_waitlist SET status='expired' WHERE book_id=? AND user_id=? AND status='waiting'");
    $upd->bind_param("ii", $bookId, $userId);
    $upd->execute();
    echo json_encode(['success'=>true]);

// ── WAITLIST: get my positions ──
} elseif ($action === 'getMyWaitlist') {
    $stmt = $conn->prepare(
        "SELECT w.book_id, w.position, w.status,
                (SELECT COUNT(*) FROM borrow_waitlist w2 WHERE w2.book_id=w.book_id AND w2.status='waiting' AND w2.position <= w.position) AS queue_pos
         FROM borrow_waitlist w
         WHERE w.user_id=? AND w.status='waiting'"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $rows = [];
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
    echo json_encode($rows);

// ── WAITLIST: get queue size for a book ──
} elseif ($action === 'getWaitlistInfo') {
    $bookId = intval($data['book_id']);
    $cnt = $conn->prepare("SELECT COUNT(*) AS cnt FROM borrow_waitlist WHERE book_id=? AND status='waiting'");
    $cnt->bind_param("i", $bookId);
    $cnt->execute();
    $queueSize = intval($cnt->get_result()->fetch_assoc()['cnt']);
    // My position
    $pos = $conn->prepare("SELECT position FROM borrow_waitlist WHERE book_id=? AND user_id=? AND status='waiting'");
    $pos->bind_param("ii", $bookId, $userId);
    $pos->execute();
    $posRow = $pos->get_result()->fetch_assoc();
    echo json_encode(['queue_size'=>$queueSize, 'my_position'=>$posRow ? intval($posRow['position']) : null]);

// ── GET book due dates (for availability display) ──
} elseif ($action === 'getBookDueDates') {
    $result = $conn->query(
        "SELECT book_id, due_date FROM borrowings WHERE status='borrowed'"
    );
    $map = [];
    while ($r = $result->fetch_assoc()) {
        $map[intval($r['book_id'])] = $r['due_date'];
    }
    echo json_encode($map);

// ── READING HISTORY for this user ──
} elseif ($action === 'getReadingHistory') {
    $stmt = $conn->prepare(
        "SELECT bw.id, bw.book_id, bw.borrow_date, bw.due_date, bw.return_date, bw.status,
                bk.title, bk.author, bk.genre,
                br.condition_status
         FROM borrowings bw
         JOIN books bk ON bk.id = bw.book_id
         LEFT JOIN book_returns br ON br.borrowing_id = bw.id
         WHERE bw.user_id = ? AND bw.status IN ('returned','lost')
         ORDER BY bw.return_date DESC, bw.due_date DESC
         LIMIT 50"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $rows = [];
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
    echo json_encode($rows);

} else {
    echo json_encode(['error' => 'Unknown action']);
}
?>
