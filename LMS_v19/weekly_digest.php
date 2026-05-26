<?php
/**
 * Weekly Digest Sender
 *
 * Run modes:
 *   CLI  — php weekly_digest.php          (Windows Task Scheduler)
 *   HTTP — POST from admin dashboard      (admin session required)
 */
$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    require 'session.php';
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    header('Content-Type: application/json');
}

set_time_limit(120); // 13 users × SMTP overhead — give it breathing room

require 'db.php';
require 'mailer.php'; // loads vendor/autoload.php, making PHPMailer classes available

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

// All non-admin users with an email address
$stmt = $conn->prepare(
    "SELECT id, first_name, last_name, email, balance
     FROM users
     WHERE role = 'user' AND email IS NOT NULL AND email != ''"
);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// New books added in the last 7 days (graceful — books may not have created_at yet)
$newBooks = [];
try {
    $nbStmt = $conn->prepare(
        "SELECT title, author, genre FROM books
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         ORDER BY created_at DESC
         LIMIT 5"
    );
    $nbStmt->execute();
    $newBooks = $nbStmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $e) {
    // created_at column not yet added — skip section
}

// Build one persistent SMTP connection shared across all emails
$mailer = new PHPMailer(true);
$mailer->isSMTP();
$mailer->Host       = 'sandbox.smtp.mailtrap.io';
$mailer->SMTPAuth   = true;
$mailer->Username   = 'c9088b9ce0f403';
$mailer->Password   = '9ed6d53be2fd3f';
$mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mailer->Port       = 587;
$mailer->Timeout    = 15;
$mailer->SMTPKeepAlive = true; // reuse the same connection for every user
$mailer->SMTPDebug  = SMTP::DEBUG_OFF;
$mailer->setFrom('noreply@lms.com', 'TechGiants Library');
$mailer->isHTML(true);

$sent   = 0;
$failed = 0;
$errors = [];

foreach ($users as $user) {
    // Active borrowings for this user
    $bStmt = $conn->prepare(
        "SELECT bk.title, bk.author, bw.due_date,
                DATEDIFF(bw.due_date, CURDATE()) AS days_left
         FROM borrowings bw
         JOIN books bk ON bk.id = bw.book_id
         WHERE bw.user_id = ?
           AND bw.status  = 'borrowed'
         ORDER BY bw.due_date ASC"
    );
    $bStmt->bind_param("i", $user['id']);
    $bStmt->execute();
    $borrowings = $bStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    try {
        $mailer->clearAddresses();
        $mailer->addAddress($user['email']);
        $mailer->Subject = 'Your Weekly Library Summary — TechGiants Library';

        // Build body using the helper in mailer.php
        [$html, $plain] = _buildDigestBody(
            $user['first_name'],
            floatval($user['balance']),
            $borrowings,
            $newBooks
        );
        $mailer->Body    = $html;
        $mailer->AltBody = $plain;
        $mailer->send();
        $sent++;
        if ($isCli) echo "  Sent → {$user['email']}\n";
    } catch (Throwable $e) {
        $failed++;
        $errors[] = $user['email'];
        error_log("Digest error for {$user['email']}: " . $e->getMessage());
        if ($isCli) echo "  Failed → {$user['email']}: " . $e->getMessage() . "\n";
    }
}

$mailer->smtpClose(); // cleanly close the persistent connection

if ($isCli) {
    echo "\nDone. Sent: {$sent}  Failed: {$failed}  Total: " . count($users) . "\n";
    if ($errors) echo "Failed: " . implode(', ', $errors) . "\n";
} else {
    echo json_encode([
        'success' => true,
        'sent'    => $sent,
        'failed'  => $failed,
        'total'   => count($users),
    ]);
}
