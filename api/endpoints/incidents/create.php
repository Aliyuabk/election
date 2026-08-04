<?php
/**
 * Create Incident Report
 * POST /api/incidents/create
 */

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/response.php';
require_once dirname(__DIR__, 2) . '/includes/validation.php';

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

$errors = Validator::required($data, ['incident_type', 'title', 'description']);
if (!empty($errors)) {
    Response::validationError($errors);
}

$incidentType = Validator::sanitize($data['incident_type']);
$title = Validator::sanitize($data['title']);
$description = Validator::sanitize($data['description']);
$severity = isset($data['severity']) ? Validator::sanitize($data['severity']) : 'medium';
$electionId = isset($data['election_id']) ? intval($data['election_id']) : null;
$puId = isset($data['pu_id']) ? intval($data['pu_id']) : null;
$wardId = isset($data['ward_id']) ? intval($data['ward_id']) : null;
$lgaId = isset($data['lga_id']) ? intval($data['lga_id']) : null;
$stateId = isset($data['state_id']) ? intval($data['state_id']) : null;
$gpsLat = isset($data['gps_lat']) ? floatval($data['gps_lat']) : null;
$gpsLng = isset($data['gps_lng']) ? floatval($data['gps_lng']) : null;
$deviceId = isset($data['device_id']) ? Validator::sanitize($data['device_id']) : null;
$photoUrls = isset($data['photo_urls']) ? json_encode($data['photo_urls']) : null;
$isPanic = isset($data['is_panic']) ? 1 : 0;
$isOfflineSync = isset($data['is_offline_sync']) ? 1 : 0;

$validTypes = [
    'violence', 'intimidation', 'ballot_stuffing', 'vote_buying',
    'voter_suppression', 'material_shortage', 'delay', 'technical_issue',
    'other', 'panic_button'
];

if (!in_array($incidentType, $validTypes)) {
    Response::error('Invalid incident type', 422);
}

$validSeverities = ['low', 'medium', 'high', 'critical'];
if (!in_array($severity, $validSeverities)) {
    $severity = 'medium';
}

$db = Database::getInstance();

// If no state/lga/ward/ward provided, try to get from assignment
if (!$puId && !$wardId && !$lgaId && !$stateId) {
    $assignResult = $db->query("
        SELECT pu_id, ward_id, lga_id, state_id 
        FROM agent_assignments 
        WHERE user_id = {$user['id']} AND status = 'active'
        LIMIT 1
    ");
    $assign = $assignResult->fetch_assoc();
    
    if ($assign) {
        $puId = $assign['pu_id'] ?? $puId;
        $wardId = $assign['ward_id'] ?? $wardId;
        $lgaId = $assign['lga_id'] ?? $lgaId;
        $stateId = $assign['state_id'] ?? $stateId;
    }
}

$tenantId = $user['tenant_id'];

$stmt = $db->prepare("
    INSERT INTO incidents (
        tenant_id, election_id, reporter_id, pu_id, ward_id, lga_id, state_id,
        incident_type, severity, is_panic, title, description,
        gps_lat, gps_lng, photo_urls_json, device_id,
        is_offline_sync, status, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'reported', NOW())
");

$stmt->bind_param(
    "iiiiiiississsssii",
    $tenantId, $electionId, $user['id'], $puId, $wardId, $lgaId, $stateId,
    $incidentType, $severity, $isPanic, $title, $description,
    $gpsLat, $gpsLng, $photoUrls, $deviceId, $isOfflineSync
);

if (!$stmt->execute()) {
    Response::error('Failed to report incident: ' . $stmt->error, 500);
}

$incidentId = $db->lastInsertId();
$stmt->close();

// Log activity
$db->query("
    INSERT INTO activity_logs (user_id, activity_type, description, device_id, created_at)
    VALUES ({$user['id']}, 'incident_reported', 
            'Reported incident: $title (Type: $incidentType)',
            '$deviceId', NOW())
");

// If panic button, send immediate notification
if ($isPanic) {
    // TODO: Send SMS/Email notifications to coordinators
    $db->query("
        INSERT INTO notifications (user_id, type, title, message, created_at)
        SELECT 
            u.id, 'security', '🚨 PANIC ALERT', 
            CONCAT('Emergency alert from ', u.full_name, ': ', '$title'),
            NOW()
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE r.level IN ('lga', 'ward', 'state', 'national')
        AND u.tenant_id = $tenantId
    ");
}

Response::success([
    'incident_id' => $incidentId,
    'status' => 'reported',
    'is_panic' => (bool)$isPanic
], 'Incident reported successfully');
?>