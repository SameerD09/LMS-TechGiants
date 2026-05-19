<?php
// signup_api.php — handles the two-step email-verified signup flow
require 'session.php';
require 'db.php';
require 'mailer.php';

ob_start();
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$body = file_get_contents('php://input');
$data = json_decode($body, true);
$step = trim($data['step'] ?? '');

// CSRF check
if (!hash_equals($_SESSION['csrf_token'] ?? '', $data['csrf_token'] ?? '')) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request. Please refresh the page and try again.']);
    exit;
}

// ── Step 1: validate form data, check uniqueness, send OTP ────────────────
if ($step === 'send_otp') {

    $first    = trim($data['first']            ?? '');
    $last     = trim($data['last']             ?? '');
    $email    = trim($data['email']            ?? '');
    $username = trim($data['username']         ?? '');
    $password = $data['password']              ?? '';
    $confirm  = $data['confirm_password']      ?? '';

    if (!$first || !$last || !$email || !$username || !$password || !$confirm) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }
    if (!preg_match('/^[a-zA-Z\s\'\-\.]+$/', $first)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'First name must contain only letters.', 'field' => 'first']);
        exit;
    }
    if (!preg_match('/^[a-zA-Z\s\'\-\.]+$/', $last)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Last name must contain only letters.', 'field' => 'last']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.', 'field' => 'email']);
        exit;
    }
    if (strlen($password) < 6) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.', 'field' => 'password']);
        exit;
    }
    if ($password !== $confirm) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.', 'field' => 'confirm_password']);
        exit;
    }

    // Check email + username uniqueness
    $chk = $conn->prepare("SELECT email, username FROM users WHERE email = ? OR username = ?");
    $chk->bind_param('ss', $email, $username);
    $chk->execute();
    $rows = $chk->get_result()->fetch_all(MYSQLI_ASSOC);
    $chk->close();

    foreach ($rows as $row) {
        if ($row['email'] === $email) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'This email address is already registered.', 'field' => 'email']);
            exit;
        }
        if ($row['username'] === $username) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'This username is already taken.', 'field' => 'username']);
            exit;
        }
    }

    // Generate 6-digit OTP
    $otp = (string)random_int(100000, 999999);

    // Store pending signup data in session (password stored plain, hashed on account creation)
    $_SESSION['signup_pending'] = compact('first', 'last', 'email', 'username', 'password');
    $_SESSION['signup_otp']     = $otp;
    $_SESSION['signup_otp_at']  = time();

    // Send verification email
    ob_end_clean();
    $sent = sendSignupOTP($email, $otp, $first);

    if (!$sent) {
        echo json_encode(['success' => false, 'message' => 'Failed to send verification email. Please check your email address and try again.']);
        exit;
    }

    // Return masked email hint so user knows where to look
    $atPos    = strpos($email, '@');
    $localLen = $atPos !== false ? $atPos : strlen($email);
    $hint     = substr($email, 0, min(3, $localLen)) . str_repeat('*', max(0, $localLen - 3)) . ($atPos !== false ? substr($email, $atPos) : '');

    echo json_encode(['success' => true, 'email_hint' => $hint]);
    exit;
}

// ── Step 2: verify OTP and create account ─────────────────────────────────
if ($step === 'verify_and_create') {

    $otp = trim($data['otp'] ?? '');

    if (empty($_SESSION['signup_pending']) || empty($_SESSION['signup_otp'])) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Session expired. Please go back and fill the form again.']);
        exit;
    }

    // Check 10-minute expiry
    if (time() - (int)($_SESSION['signup_otp_at'] ?? 0) > 600) {
        unset($_SESSION['signup_pending'], $_SESSION['signup_otp'], $_SESSION['signup_otp_at']);
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Verification code expired. Please go back and try again.']);
        exit;
    }

    if (!hash_equals($_SESSION['signup_otp'], $otp)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Incorrect verification code. Please try again.']);
        exit;
    }

    $pending = $_SESSION['signup_pending'];
    $hashed  = password_hash($pending['password'], PASSWORD_DEFAULT);

    // Create account with 0 starting balance — user can top up via wallet
    $stmt = $conn->prepare(
        "INSERT INTO users (first_name, last_name, email, username, password, role, balance)
         VALUES (?, ?, ?, ?, ?, 'user', 0.00)"
    );
    $stmt->bind_param('sssss', $pending['first'], $pending['last'], $pending['email'], $pending['username'], $hashed);

    ob_end_clean();

    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Could not create your account. Please try again.']);
        exit;
    }

    $newId = (int)$stmt->insert_id;
    $stmt->close();

    // Clean up session OTP data
    unset($_SESSION['signup_pending'], $_SESSION['signup_otp'], $_SESSION['signup_otp_at']);

    // Log user in immediately
    $_SESSION['user'] = [
        'id'        => $newId,
        'username'  => $pending['username'],
        'firstName' => $pending['first'],
        'lastName'  => $pending['last'],
        'email'     => $pending['email'],
        'role'      => 'user',
    ];

    echo json_encode(['success' => true, 'redirect' => 'user_dashboard.php']);
    exit;
}

// ── Resend OTP ─────────────────────────────────────────────────────────────
if ($step === 'resend_otp') {

    if (empty($_SESSION['signup_pending'])) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Session expired. Please go back and fill the form again.']);
        exit;
    }

    $pending = $_SESSION['signup_pending'];
    $otp     = (string)random_int(100000, 999999);
    $_SESSION['signup_otp']    = $otp;
    $_SESSION['signup_otp_at'] = time();

    ob_end_clean();
    $sent = sendSignupOTP($pending['email'], $otp, $pending['first']);

    echo json_encode($sent
        ? ['success' => true]
        : ['success' => false, 'message' => 'Failed to resend code. Please try again.']
    );
    exit;
}

ob_end_clean();
echo json_encode(['success' => false, 'message' => 'Invalid request step.']);
