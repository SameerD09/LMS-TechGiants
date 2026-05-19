<?php
// notifications_helper.php
// Shared helper — include in any action file that needs to push
// a notification to a user. Requires an active $conn (mysqli).

function pushNotification(mysqli $conn, int $userId, string $type, string $title, string $message): void
{
    try {
        $stmt = $conn->prepare(
            "INSERT INTO notifications (user_id, type, title, message) VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param('isss', $userId, $type, $title, $message);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        error_log('[notifications] pushNotification failed: ' . $e->getMessage());
    }
}
