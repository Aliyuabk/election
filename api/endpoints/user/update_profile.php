<?php
/**
 * Create Check-in
 * POST /api/checkin/create
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

$errors = Validator::required($data, [
    'assignment_id', 'checkin_type', 'gps_lat', 'gps_lng'
]);

if (!empty($errors)) {
    Response::validationError($errors);
}

$assignmentId = intval($data['assignment_id']);
$checkinType = Validator::sanitize($data['checkin_type']);
$gpsLat = floatval($data['gps_lat']);
$gpsLng = floatval($data['gps_lng']);
$gpsAccuracy = isset($data['gps_accuracy']) ? floatval($data['gps_accuracy']) : null;
$deviceId = isset($data['device_id']) ? Validator::sanitize($data['device_id']) : null;
$deviceBattery = isset($data['device_battery']) ? intval($data['device_battery']) : null;
$networkType = isset($data['network_type']) ? Validator::sanitize($data['network_type']) : null;

// Validate GPS
if (!Validator::validateLat($gpsLat) || !Validator::validateLng($gpsLng)) {
    Response::error('Invalid GPS coordinates', 422);
}

$validCheckinTypes = [
    'arrival', 'departure', 'material_received', 
    'accreditation_started', 'voting_started', 
    'voting_ended', 'counting_started', 'counting_ended'
];

if (!in_array($checkinType, $validCheckinTypes)) {
    Response::error('Invalid check-in type', 422);
}

$db = Database::getInstance();

// Verify assignment belongs to user
$stmt = $db->prepare("
    SELECT pu_id, election_id, tenant_id FROM agent_assignments 
    WHERE id = ? AND user_id = ? AND status = 'active'
");
$stmt->bind_param("ii", $assignmentId, $user['id']);
$stmt->execute();
$result = $stmt->get_result();
$assignment = $result->fetch_assoc();
$stmt->close();

if (!$assignment) {
    Response::error('Invalid assignment', 400);
}

// Insert check-in
$stmt = $db->prepare("
    INSERT INTO agent_checkins (
        tenant_id, election_id, agent_id, assignment_id, pu_id,
        checkin_type, gps_lat, gps_lng, gps_accuracy,
        device_id, device_battery, network_type, is_offline_sync, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");

$tenantId = $assignment['tenant_id'];
$electionId = $assignment['election_id'];
$puId = $assignment['pu_id'];
$isOfflineSync = isset($data['is_offline_sync']) ? 1 : 0;

$stmt->bind_param(
    "iiiiisdidsii", 
    $tenantId, $electionId, $user['id'], $assignmentId, $puId,
    $checkinType, $gpsLat, $gpsLng, $gpsAccuracy,
    $deviceId, $deviceBattery, $networkType, $isOfflineSync
);

if (!$stmt->execute()) {
    Response::error('Failed to record check-in', 500);
}

$checkinId = $db->lastInsertId();
$stmt->close();

// Log activity
$db->query("
    INSERT INTO activity_logs (user_id, activity_type, description, ip_address, device_id, created_at)
    VALUES ({$user['id']}, 'checkin', 'Check-in type: $checkinType at PU: $puId', '{$_SERVER['REMOTE_ADDR']}', '$deviceId', NOW())
");

Response::success([
    'checkin_id' => $checkinId,
    'message' => 'Check-in recorded successfully',
    'checkin_type' => $checkinType,
    'time' => date('Y-m-d H:i:s')
]);