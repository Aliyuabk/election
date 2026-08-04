<?php
/**
 * List Notifications
 * GET /api/notifications/list
 */

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/response.php';

$auth = new Auth();
$user = $auth->authenticate();

if (!$user) {
    Response::unauthorized();
}

$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$type = isset($_GET['type']) ? Validator::sanitize($_GET['type']) : null;

$db = Database::getInstance();

$sql = "
    SELECT 
        id, type, title, message, data_json, action_url,
        is_read, read_at, created_at
    FROM notifications
    WHERE user_id = {$user['id']}
";

if ($type) {
    $sql .= " AND type = '" . $db->escapeString($type) . "'";
}

$sql .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";

$result = $db->query($sql);

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $row['is_read'] = (bool)$row['is_read'];
    $notifications[] = $row;
}

// Get unread count
$unreadResult = $db->query("
    SELECT COUNT(*) as unread_count 
    FROM notifications 
    WHERE user_id = {$user['id']} AND is_read = 0
");
$unreadCount = $unreadResult->fetch_assoc()['unread_count'];

Response::success([
    'notifications' => $notifications,
    'unread_count' => intval($unreadCount),
    'pagination' => [
        'limit' => $limit,
        'offset' => $offset
    ]
]);
?>