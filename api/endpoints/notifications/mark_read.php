<?php
/**
 * Mark Notification as Read
 * POST /api/notifications/mark-read
 */

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/Response.php';

$auth = new Auth();
$user = $auth->authenticate();

if (!$user) {
    Response::unauthorized();
}

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

if (!$data) {
    Response::error('Invalid request data', 400);
}

$db = Database::getInstance();

if (isset($data['notification_id']) && $data['notification_id'] > 0) {
    // Mark single notification
    $notificationId = intval($data['notification_id']);
    $stmt = $db->prepare("
        UPDATE notifications 
        SET is_read = 1, read_at = NOW()
        WHERE id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $notificationId, $user['id']);
    $stmt->execute();
    $stmt->close();
} else {
    // Mark all as read
    $db->query("
        UPDATE notifications 
        SET is_read = 1, read_at = NOW()
        WHERE user_id = {$user['id']} AND is_read = 0
    ");
}

Response::success(null, 'Notifications marked as read');