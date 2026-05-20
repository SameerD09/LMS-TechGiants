<?php
require 'session.php';
require 'db.php';
require 'notifications_helper.php';

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

// ── PRICING CONSTANTS ──────────────────────────────────────
define('BORROW_FEE_PER_DAY',   100.00);  // rs per borrow day
define('EXTENSION_FEE_PER_DAY', 80.00);  // rs per extension day
define('PDF_PURCHASE_FEE',     400.00);  // rs flat per PDF
define('OVERDUE_FINE_PER_DAY', 100.00);  // rs per overdue day
define('DAMAGE_FINE_FAIR',     200.00);  // rs for fair condition
define('DAMAGE_FINE_BAD',      500.00);  // rs for bad condition
define('DAMAGE_FINE_PCT',        0.80);  // 80% book price for damaged
define('DEFAULT_BOOK_PRICE',  1500.00);  // fallback if book has no price
define('NEW_USER_BALANCE',   35000.00);  // balance given to new users

// ── Helper: deduct from user, add to admin ─────────────────
function chargeUser($conn, $userId, $amount, $type, $description, $refId = null, $bookId = null) {
    // Check balance
    $sel = $conn->prepare("SELECT balance FROM users WHERE id = ?");
    $sel->bind_param("i", $userId);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();
    if (!$row) return ['success' => false, 'error' => 'User not found.'];
    $balance = floatval($row['balance']);
    if ($balance < $amount) {
        return ['success' => false, 'error' => 'Insufficient balance. You need Rs ' . number_format($amount, 2) . ' but have Rs ' . number_format($balance, 2) . '.'];
    }

    // Deduct from user
    $upd = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
    $upd->bind_param("di", $amount, $userId);
    $upd->execute();

    // Log transaction (negative = money leaving user)
    $neg = -$amount;
    $ins = $conn->prepare(
        "INSERT INTO wallet_transactions (user_id, type, amount, description, reference_id, book_id)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $ins->bind_param("isdsii", $userId, $type, $neg, $description, $refId, $bookId);
    $ins->execute();

    // Add to admin balance
    $conn->query("UPDATE admin_balance SET total = total + " . floatval($amount) . " WHERE id = 1");

    return ['success' => true, 'new_balance' => round($balance - $amount, 2)];
}

// ── Helper: get book price ──────────────────────────────────
function getBookPrice($conn, $bookId) {
    $s = $conn->prepare("SELECT price FROM books WHERE id = ?");
    $s->bind_param("i", $bookId);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    return $r ? floatval($r['price']) : DEFAULT_BOOK_PRICE;
}

// ══════════════════════════════════════════════════════════════
// ACTION: getMyBalance — current user's balance
// ══════════════════════════════════════════════════════════════
if ($action === 'getMyBalance') {
    $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    echo json_encode(['balance' => $row ? floatval($row['balance']) : 0]);

// ══════════════════════════════════════════════════════════════
// ACTION: getMyTransactions — user's own transaction history
// ══════════════════════════════════════════════════════════════
} elseif ($action === 'getMyTransactions') {
    $stmt = $conn->prepare(
        "SELECT wt.id, wt.type, wt.amount, wt.description, wt.created_at,
                b.title AS book_title
         FROM wallet_transactions wt
         LEFT JOIN books b ON b.id = wt.book_id
         WHERE wt.user_id = ?
         ORDER BY wt.created_at DESC
         LIMIT 50"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $rows = [];
    $res  = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    echo json_encode($rows);

// ══════════════════════════════════════════════════════════════
// ACTION: getBorrowFeePreview — show cost before confirming borrow
// ══════════════════════════════════════════════════════════════
} elseif ($action === 'getBorrowFeePreview') {
    $borrowDays = intval($data['borrow_days'] ?? 1);
    if ($borrowDays < 1) $borrowDays = 1;
    if ($borrowDays > 7) $borrowDays = 7;
    $fee = $borrowDays * BORROW_FEE_PER_DAY;

    // Get user's current balance
    $sel = $conn->prepare("SELECT balance FROM users WHERE id = ?");
    $sel->bind_param("i", $userId);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();
    $balance = $row ? floatval($row['balance']) : 0;

    echo json_encode([
        'fee'            => $fee,
        'balance'        => $balance,
        'can_afford'     => $balance >= $fee,
        'fee_per_day'    => BORROW_FEE_PER_DAY,
    ]);

// ══════════════════════════════════════════════════════════════
// ACTION: chargeBorrowFee — called when admin APPROVES a request
// ══════════════════════════════════════════════════════════════
} elseif ($action === 'chargeBorrowFee') {
    // Admin only (called from borrow_actions approveRequest)
    if ($userRole !== 'admin') { echo json_encode(['error' => 'Unauthorized']); exit; }
    $targetUserId = intval($data['target_user_id']);
    $borrowDays   = intval($data['borrow_days']);
    $borrowingId  = intval($data['borrowing_id']);
    $bookId       = intval($data['book_id']);
    $bookTitle    = trim($data['book_title'] ?? 'Book');

    $fee  = $borrowDays * BORROW_FEE_PER_DAY;
    $desc = 'Borrow fee: "' . $bookTitle . '" for ' . $borrowDays . ' day(s)';
    $result = chargeUser($conn, $targetUserId, $fee, 'borrow_fee', $desc, $borrowingId, $bookId);
    echo json_encode($result);

// ══════════════════════════════════════════════════════════════
// ACTION: chargeExtensionFee — deduct when user requests extension
// ══════════════════════════════════════════════════════════════
} elseif ($action === 'chargeExtensionFee') {
    $bookId      = intval($data['book_id']);
    $extendDays  = intval($data['extend_days']);
    $extensionId = intval($data['extension_id'] ?? 0);
    $bookTitle   = trim($data['book_title'] ?? 'Book');

    if ($extendDays < 1 || $extendDays > 7) {
        echo json_encode(['success' => false, 'error' => 'Invalid extension days.']);
        exit;
    }

    $fee  = $extendDays * EXTENSION_FEE_PER_DAY;
    $desc = 'Extension fee: "' . $bookTitle . '" +' . $extendDays . ' day(s)';
    $result = chargeUser($conn, $userId, $fee, 'extension_fee', $desc, $extensionId, $bookId);
    echo json_encode($result);

// ══════════════════════════════════════════════════════════════
// ACTION: chargePdfPurchase — charge 400rs to download PDF
// ══════════════════════════════════════════════════════════════
} elseif ($action === 'chargePdfPurchase') {
    $bookId    = intval($data['book_id']);
    $bookTitle = trim($data['book_title'] ?? 'Book');

    // Check if already purchased (no double-charge)
    $chk = $conn->prepare("SELECT id FROM pdf_purchases WHERE user_id = ? AND book_id = ?");
    $chk->bind_param("ii", $userId, $bookId);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows > 0) {
        // Already purchased — allow free re-download
        echo json_encode(['success' => true, 'already_owned' => true, 'fee' => 0]);
        exit;
    }

    $fee    = PDF_PURCHASE_FEE;
    $desc   = 'Digital purchase: "' . $bookTitle . '" (PDF)';
    $result = chargeUser($conn, $userId, $fee, 'pdf_purchase', $desc, null, $bookId);

    if ($result['success']) {
        // Record purchase
        $ins = $conn->prepare("INSERT INTO pdf_purchases (user_id, book_id) VALUES (?, ?)");
        $ins->bind_param("ii", $userId, $bookId);
        $ins->execute();
        $result['already_owned'] = false;
        $result['fee'] = $fee;
    }
    echo json_encode($result);

// ══════════════════════════════════════════════════════════════
// ACTION: checkPdfOwnership — has user already paid for this PDF?
// ══════════════════════════════════════════════════════════════
} elseif ($action === 'checkPdfOwnership') {
    $bookId = intval($data['book_id']);
    $chk = $conn->prepare("SELECT id FROM pdf_purchases WHERE user_id = ? AND book_id = ?");
    $chk->bind_param("ii", $userId, $bookId);
    $chk->execute();
    $chk->store_result();
    $owned = $chk->num_rows > 0;

    // Include download count if owned
    $dlCount = 0;
    if ($owned) {
        $dlC = $conn->prepare("SELECT COUNT(*) AS cnt FROM pdf_download_logs WHERE user_id = ? AND book_id = ?");
        $dlC->bind_param("ii", $userId, $bookId);
        $dlC->execute();
        $dlCount = intval($dlC->get_result()->fetch_assoc()['cnt']);
    }
    echo json_encode(['owned' => $owned, 'fee' => PDF_PURCHASE_FEE, 'download_count' => $dlCount, 'max_downloads' => 3]);

// ══════════════════════════════════════════════════════════════
// ACTION: chargeReturnFines — overdue + damage fines on return
// ══════════════════════════════════════════════════════════════
} elseif ($action === 'chargeReturnFines') {
    $bookId      = intval($data['book_id']);
    $condition   = trim($data['condition'] ?? 'good');
    $borrowingId = intval($data['borrowing_id']);
    $bookTitle   = trim($data['book_title'] ?? 'Book');

    // Fetch borrowing to calculate overdue
    $sel = $conn->prepare("SELECT due_date FROM borrowings WHERE id = ? AND user_id = ?");
    $sel->bind_param("ii", $borrowingId, $userId);
    $sel->execute();
    $borrow = $sel->get_result()->fetch_assoc();

    $totalFine   = 0;
    $fineDetails = [];

    // Overdue fine
    if ($borrow && $borrow['due_date']) {
        $dueDate  = new DateTime($borrow['due_date']);
        $today    = new DateTime(date('Y-m-d'));
        $diffDays = $today->diff($dueDate)->days;
        $isLate   = $today > $dueDate;
        if ($isLate && $diffDays > 0) {
            $overdueFine  = $diffDays * OVERDUE_FINE_PER_DAY;
            $totalFine   += $overdueFine;
            $fineDetails[] = ['type' => 'overdue', 'days' => $diffDays, 'amount' => $overdueFine];
        }
    }

    // Damage fine
    $damageFine = 0;
    if ($condition === 'fair') {
        $damageFine = DAMAGE_FINE_FAIR;
    } elseif ($condition === 'bad') {
        $damageFine = DAMAGE_FINE_BAD;
    } elseif ($condition === 'damaged') {
        $bookPrice  = getBookPrice($conn, $bookId);
        $damageFine = round($bookPrice * DAMAGE_FINE_PCT, 2);
    }
    if ($damageFine > 0) {
        $totalFine   += $damageFine;
        $fineDetails[] = ['type' => 'damage', 'condition' => $condition, 'amount' => $damageFine];
    }

    if ($totalFine <= 0) {
        echo json_encode(['success' => true, 'total_fine' => 0, 'fines' => [], 'new_balance' => null]);
        exit;
    }

    // Check balance before charging
    $selBal = $conn->prepare("SELECT balance FROM users WHERE id = ?");
    $selBal->bind_param("i", $userId);
    $selBal->execute();
    $balRow  = $selBal->get_result()->fetch_assoc();
    $balance = $balRow ? floatval($balRow['balance']) : 0;

    if ($balance < $totalFine) {
        // Still allow return but flag insufficient — admin can handle offline
        // For now we charge what they have and flag the remainder
        // Better UX: let them return and note negative balance
    }

    // Charge each fine individually for detailed history
    $newBalance = $balance;
    foreach ($fineDetails as $fine) {
        if ($fine['type'] === 'overdue') {
            $desc = 'Overdue fine: "' . $bookTitle . '" — ' . $fine['days'] . ' day(s) late';
            $r = chargeUser($conn, $userId, $fine['amount'], 'overdue_fine', $desc, $borrowingId, $bookId);
        } else {
            $desc = 'Damage fine: "' . $bookTitle . '" — condition: ' . $fine['condition'];
            $r = chargeUser($conn, $userId, $fine['amount'], 'damage_fine', $desc, $borrowingId, $bookId);
        }
        if (isset($r['new_balance'])) $newBalance = $r['new_balance'];
    }

    echo json_encode([
        'success'     => true,
        'total_fine'  => $totalFine,
        'fines'       => $fineDetails,
        'new_balance' => $newBalance
    ]);

// ══════════════════════════════════════════════════════════════
// ADMIN: getAllTransactions — full transaction log
// ══════════════════════════════════════════════════════════════
} elseif ($action === 'getAllTransactions') {
    if ($userRole !== 'admin') { echo json_encode(['error' => 'Unauthorized']); exit; }

    $result = $conn->query(
        "SELECT wt.id, wt.type, wt.amount, wt.description, wt.created_at,
                u.first_name, u.last_name, u.username,
                b.title AS book_title
         FROM wallet_transactions wt
         JOIN users u ON u.id = wt.user_id
         LEFT JOIN books b ON b.id = wt.book_id
         ORDER BY wt.created_at DESC
         LIMIT 200"
    );
    $rows = [];
    while ($r = $result->fetch_assoc()) { $rows[] = $r; }
    echo json_encode($rows);

// ══════════════════════════════════════════════════════════════
// ADMIN: getAdminBalance — total money earned
// ══════════════════════════════════════════════════════════════
} elseif ($action === 'getAdminBalance') {
    if ($userRole !== 'admin') { echo json_encode(['error' => 'Unauthorized']); exit; }
    $row = $conn->query("SELECT total FROM admin_balance WHERE id = 1")->fetch_assoc();
    echo json_encode(['balance' => $row ? floatval($row['total']) : 0]);

// ══════════════════════════════════════════════════════════════
// ADMIN: getAllUsersBalance — list all user balances
// ══════════════════════════════════════════════════════════════
} elseif ($action === 'getAllUsersBalance') {
    if ($userRole !== 'admin') { echo json_encode(['error' => 'Unauthorized']); exit; }
    $result = $conn->query(
        "SELECT id, first_name, last_name, username, balance
         FROM users WHERE role = 'user'
         ORDER BY first_name ASC"
    );
    $rows = [];
    while ($r = $result->fetch_assoc()) { $rows[] = $r; }
    echo json_encode($rows);

// ══════════════════════════════════════════════════════════════
// ADMIN: topUpUser — add balance to any user
// ══════════════════════════════════════════════════════════════
} elseif ($action === 'topUpUser') {
    if ($userRole !== 'admin') { echo json_encode(['error' => 'Unauthorized']); exit; }
    $targetId = intval($data['target_user_id']);
    $amount   = floatval($data['amount']);
    if ($amount <= 0 || $amount > 100000) {
        echo json_encode(['success' => false, 'error' => 'Invalid top-up amount.']);
        exit;
    }

    $upd = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
    $upd->bind_param("di", $amount, $targetId);
    $upd->execute();

    // Get user name for description
    $uSel = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $uSel->bind_param("i", $targetId);
    $uSel->execute();
    $uRow = $uSel->get_result()->fetch_assoc();
    $name = $uRow ? $uRow['first_name'] . ' ' . $uRow['last_name'] : 'User';

    $desc = 'Admin top-up for ' . $name;
    $ins  = $conn->prepare(
        "INSERT INTO wallet_transactions (user_id, type, amount, description) VALUES (?, 'top_up', ?, ?)"
    );
    $ins->bind_param("ids", $targetId, $amount, $desc);
    $ins->execute();

    $newBal = $conn->prepare("SELECT balance FROM users WHERE id = ?");
    $newBal->bind_param("i", $targetId);
    $newBal->execute();
    $newRow = $newBal->get_result()->fetch_assoc();
    $newBalance = $newRow ? floatval($newRow['balance']) : 0.0;

    pushNotification(
        $conn, $targetId, 'wallet_topup',
        'Wallet Topped Up',
        "Rs " . number_format($amount, 2) . " has been added to your wallet by the admin. " .
        "New balance: Rs " . number_format($newBalance, 2) . "."
    );

    echo json_encode(['success' => true, 'new_balance' => $newBalance]);

// ══════════════════════════════════════════════════════════════
// USER: selfTopUp — user adds balance to their own wallet
// ══════════════════════════════════════════════════════════════
} elseif ($action === 'selfTopUp') {
    $amount = floatval($data['amount'] ?? 0);
    if ($amount < 100 || $amount > 100000) {
        echo json_encode(['success' => false, 'error' => 'Amount must be between Rs 100 and Rs 1,00,000.']);
        exit;
    }

    $upd = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
    $upd->bind_param("di", $amount, $userId);
    $upd->execute();
    $upd->close();

    $desc = 'Self top-up of Rs ' . number_format($amount, 2);
    $ins  = $conn->prepare(
        "INSERT INTO wallet_transactions (user_id, type, amount, description) VALUES (?, 'top_up', ?, ?)"
    );
    $ins->bind_param("ids", $userId, $amount, $desc);
    $ins->execute();
    $ins->close();

    $sel = $conn->prepare("SELECT balance FROM users WHERE id = ?");
    $sel->bind_param("i", $userId);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();
    $sel->close();
    $newBalance = $row ? floatval($row['balance']) : 0.0;

    echo json_encode(['success' => true, 'new_balance' => $newBalance]);

} else {
    echo json_encode(['error' => 'Unknown action']);
}
?>
