<?php
require 'session.php';
require 'db.php';
require 'mailer.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

// CSRF check
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid request. Please refresh and try again.']);
    exit;
}

$step = $_POST['step'] ?? '';

// ─── STEP 1: Verify identity (username + email) and send OTP ─────────────────
if ($step === 'verify') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');

    if (!$username || !$email) {
        echo json_encode(['success' => false, 'message' => 'Username and email are required.']);
        exit;
    }

    // Block admin recovery
    if ($username === 'admin') {
        echo json_encode(['success' => false, 'message' => 'Admin account recovery is not supported here.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, username, email FROM users WHERE username = ? AND email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'No account found with that username and email combination.']);
        $stmt->close();
        exit;
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    // Generate a 6-digit OTP
    $otp = strval(random_int(100000, 999999));

    // Store OTP and user id in session
    $_SESSION['pwd_reset_user_id']   = $user['id'];
    $_SESSION['pwd_reset_otp']       = $otp;
    $_SESSION['pwd_reset_otp_at']    = time();
    $_SESSION['pwd_reset_verified']  = false;

    // Send OTP to their email
    $sent = sendOTPEmail($user['email'], $otp);

    if (!$sent) {
        echo json_encode(['success' => false, 'message' => 'Failed to send OTP email. Please try again.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'OTP sent to your registered email. Please check your inbox.']);
    exit;
}

// ─── STEP 2: Verify OTP ───────────────────────────────────────────────────────
if ($step === 'verify_otp') {
    if (
        empty($_SESSION['pwd_reset_user_id']) ||
        empty($_SESSION['pwd_reset_otp']) ||
        empty($_SESSION['pwd_reset_otp_at'])
    ) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please start over.']);
        exit;
    }

    // OTP expires in 10 minutes
    if ((time() - $_SESSION['pwd_reset_otp_at']) > 600) {
        unset($_SESSION['pwd_reset_otp'], $_SESSION['pwd_reset_otp_at']);
        echo json_encode(['success' => false, 'message' => 'OTP has expired. Please start over.']);
        exit;
    }

    $entered_otp = trim($_POST['otp'] ?? '');

    if (!$entered_otp) {
        echo json_encode(['success' => false, 'message' => 'Please enter the OTP.']);
        exit;
    }

    // Use hash_equals to prevent timing attacks
    if (!hash_equals($_SESSION['pwd_reset_otp'], $entered_otp)) {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please try again.']);
        exit;
    }

    // OTP is correct — allow password reset
    $_SESSION['pwd_reset_verified']  = true;
    $_SESSION['pwd_reset_verified_at'] = time();
    unset($_SESSION['pwd_reset_otp'], $_SESSION['pwd_reset_otp_at']);

    echo json_encode(['success' => true, 'message' => 'OTP verified. Please set your new password.']);
    exit;
}

// ─── STEP 3: Set new password ─────────────────────────────────────────────────
if ($step === 'reset') {
    // Must have verified OTP within the last 10 minutes
    if (
        empty($_SESSION['pwd_reset_user_id']) ||
        empty($_SESSION['pwd_reset_verified']) ||
        $_SESSION['pwd_reset_verified'] !== true ||
        empty($_SESSION['pwd_reset_verified_at']) ||
        (time() - $_SESSION['pwd_reset_verified_at']) > 600
    ) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please start over.']);
        exit;
    }

    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!$new_password || !$confirm_password) {
        echo json_encode(['success' => false, 'message' => 'Both fields are required.']);
        exit;
    }

    if (strlen($new_password) < 6) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters.']);
        exit;
    }

    if ($new_password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    }

    $user_id = (int) $_SESSION['pwd_reset_user_id'];
    $hashed  = password_hash($new_password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed, $user_id);
    $ok = $stmt->execute();
    $stmt->close();

    // Clear all reset session data
    unset(
        $_SESSION['pwd_reset_user_id'],
        $_SESSION['pwd_reset_verified'],
        $_SESSION['pwd_reset_verified_at']
    );

    if ($ok) {
        echo json_encode(['success' => true, 'message' => 'Password reset successfully! You can now log in.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown step.']);
