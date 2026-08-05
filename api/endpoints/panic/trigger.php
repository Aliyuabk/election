<?php
/**
 * Trigger Panic Button
 * POST /api/panic/trigger
 */

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/Response.php';
require_once dirname(__DIR__, 2) . '/includes/Validator.php';

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

$message = isset($data['message']) ? Validator::sanitize($data['message']) : 'Emergency assistance needed';
$gpsLat = isset($data['gps_lat']) ? floatval($data['gps_lat']) : null;
$gpsLng = isset($data['gps_lng']) ? floatval($data['gps_lng']) : null;
$deviceId = isset($data['device_id']) ? Validator::sanitize($data['device_id']) : null;

$db = Database::getInstance();

// Create incident with panic type
$tenantId = $user['tenant_id'];

$stmt = $db->prepare("
    INSERT INTO incidents (
        tenant_id, reporter_id, incident_type, severity, is_panic,
        title, description, gps_lat, gps_lng, device_id, status, created_at
    ) VALUES (?, ?, 'panic_button', 'critical', 1, ?, ?, ?, ?, ?, 'reported', NOW())
");

$title = '🚨 PANIC ALERT: ' . $user['first_name'] . ' ' . $user['last_name'];
$description = $message . ' | User: ' . $user['first_name'] . ' ' . $user['last_name'] . ' | Phone: ' . $user['phone'];

$stmt->bind_param(
    "iisssdss",
    $tenantId, $user['id'], $title, $description,
    $gpsLat, $gpsLng, $deviceId
);

if (!$stmt->execute()) {
    Response::error('Failed to trigger panic alert', 500);
}

$incidentId = $db->lastInsertId();
$stmt->close();

// Send notifications to coordinators
$db->query("
    INSERT INTO notifications (user_id, type, title, message, created_at)
    SELECT 
        u.id, 'security', '🚨 PANIC ALERT', 
        CONCAT('Emergency alert from ', u2.full_name, ': ', '$message'),
        NOW()
    FROM users u
    JOIN roles r ON u.role_id = r.id
    CROSS JOIN users u2
    WHERE r.level IN ('lga', 'ward', 'state', 'national')
    AND u.tenant_id = $tenantId
    AND u2.id = {$user['id']}
");

// Log activity
$db->query("
    INSERT INTO activity_logs (user_id, activity_type, description, device_id, created_at)
    VALUES ({$user['id']}, 'panic_alert', 
            'Panic alert triggered: $message',
            '$deviceId', NOW())
");

Response::success([
    'incident_id' => $incidentId,
    'message' => 'Emergency alert sent successfully'
]);