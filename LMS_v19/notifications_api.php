<?php
// notifications_api.php
// Fetch and mark-read notifications for the logged-in user.

ob_start();
ini_set('display_errors', '0');
ini_set('log_errors',     '1');
error_reporting(E_ALL);

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/session.php';

    if (empty($_SESSION['user']) || $_SESSION['user']['role'] === 'admin') {
        ob_end_clean();
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ob_end_clean();
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed.']);
        exit;
    }

    $userId = (int)$_SESSION['user']['id'];
    $body   = file_get_contents('php://input');
    $data   = json_decode($body, true);
    $action = $data['action'] ?? '';

    switch ($action) {

        case 'fetch': {
            $stmt = $conn->prepare(
                "SELECT id, type, title, message, is_read, created_at
                 FROM notifications
                 WHERE user_id = ?
                 ORDER BY created_at DESC
                 LIMIT 50"
            );
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $unread = 0;
            foreach ($rows as &$row) {
                $row['id']      = (int)$row['id'];
                $row['is_read'] = (bool)$row['is_read'];
                if (!$row['is_read']) $unread++;
            }
            unset($row);

            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['notifications' => $rows, 'unread_count' => $unread]);
            exit;
        }

        case 'markRead': {
            $ids = $data['ids'] ?? [];
            if (!is_array($ids) || empty($ids)) {
                ob_end_clean();
                header('Content-Type: application/json');
                echo json_encode(['ok' => true]);
                exit;
            }
            $ids      = array_map('intval', $ids);
            $holders  = implode(',', array_fill(0, count($ids), '?'));
            $types    = 'i' . str_repeat('i', count($ids));
            $params   = array_merge([$userId], $ids);

            $stmt = $conn->prepare(
                "UPDATE notifications SET is_read = 1
                 WHERE user_id = ? AND id IN ($holders)"
            );
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $stmt->close();

            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }

        case 'markAllRead': {
            $stmt = $conn->prepare(
                "UPDATE notifications SET is_read = 1 WHERE user_id = ?"
            );
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();

            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }

        default: {
            ob_end_clean();
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action.']);
            exit;
        }
    }

} catch (Throwable $e) {
    ob_get_clean();
    error_log('[notifications_api] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    exit;
}
