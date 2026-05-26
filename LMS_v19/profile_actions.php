<?php
require 'session.php';
require 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$userId = $_SESSION['user']['id'];

// ── GET PROFILE ──
if ($action === 'getProfile') {
    $stmt = $conn->prepare("SELECT id, first_name, last_name, email, username FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    if ($user) {
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        echo json_encode(['success' => false, 'error' => 'User not found']);
    }
    exit;
}

// ── UPDATE PROFILE ──
if ($action === 'updateProfile') {
    $firstName = trim($input['first_name'] ?? '');
    $lastName  = trim($input['last_name'] ?? '');
    $username  = trim($input['username'] ?? '');
    $email     = trim($input['email'] ?? '');

    if (!$firstName || !$lastName || !$username || !$email) {
        echo json_encode(['success' => false, 'error' => 'All fields are required.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Invalid email address.']);
        exit;
    }

    // Check username uniqueness (exclude current user)
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->bind_param("si", $username, $userId);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'error' => 'Username is already taken.']);
        exit;
    }
    $stmt->close();

    // Check email uniqueness (exclude current user)
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param("si", $email, $userId);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'error' => 'Email is already in use.']);
        exit;
    }
    $stmt->close();

    $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, username=?, email=? WHERE id=?");
    $stmt->bind_param("ssssi", $firstName, $lastName, $username, $email, $userId);
    if ($stmt->execute()) {
        // Update session
        $_SESSION['user']['firstName'] = $firstName;
        $_SESSION['user']['lastName']  = $lastName;
        $_SESSION['user']['username']  = $username;
        $_SESSION['user']['email']     = $email;
        $stmt->close();
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
    } else {
        $stmt->close();
        echo json_encode(['success' => false, 'error' => 'Failed to update profile.']);
    }
    exit;
}

// ── CHANGE PASSWORD ──
if ($action === 'changePassword') {
    $oldPassword  = $input['old_password'] ?? '';
    $newPassword  = $input['new_password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';

    if (!$oldPassword || !$newPassword || !$confirmPassword) {
        echo json_encode(['success' => false, 'error' => 'All password fields are required.']);
        exit;
    }

    if (strlen($newPassword) < 6) {
        echo json_encode(['success' => false, 'error' => 'New password must be at least 6 characters.']);
        exit;
    }

    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'error' => 'New passwords do not match.']);
        exit;
    }

    // Fetch current hashed password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row || !password_verify($oldPassword, $row['password'])) {
        echo json_encode(['success' => false, 'error' => 'Current password is incorrect.']);
        exit;
    }

    $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed, $userId);
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode(['success' => true, 'message' => 'Password changed successfully.']);
    } else {
        $stmt->close();
        echo json_encode(['success' => false, 'error' => 'Failed to change password.']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
