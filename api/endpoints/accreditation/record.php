<?php
/**
 * Record Accreditation
 * POST /api/accreditation/record
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

$errors = Validator::required($data, ['election_id', 'pu_id', 'accredited_voters']);
if (!empty($errors)) {
    Response::validationError($errors);
}

$electionId = intval($data['election_id']);
$puId = intval($data['pu_id']);
$accreditedVoters = intval($data['accredited_voters']);
$gpsLat = isset($data['gps_lat']) ? floatval($data['gps_lat']) : null;
$gpsLng = isset($data['gps_lng']) ? floatval($data['gps_lng']) : null;
$deviceId = isset($data['device_id']) ? Validator::sanitize($data['device_id']) : null;

if ($accreditedVoters < 0) {
    Response::error('Accredited voters cannot be negative', 422);
}

$db = Database::getInstance();

// Verify user has access to this PU
$stmt = $db->prepare("
    SELECT pu_id, lga_id, state_id, ward_id, tenant_id 
    FROM agent_assignments 
    WHERE user_id = ? AND pu_id = ? AND status = 'active'
");
$stmt->bind_param("ii", $user['id'], $puId);
$stmt->execute();
$result = $stmt->get_result();
$assignment = $result->fetch_assoc();
$stmt->close();

if (!$assignment) {
    Response::error('Access denied to this polling unit', 403);
}

// Get current registered voters
$stmt = $db->prepare("SELECT registered_voters FROM polling_units WHERE id = ?");
$stmt->bind_param("i", $puId);
$stmt->execute();
$result = $stmt->get_result();
$pu = $result->fetch_assoc();
$stmt->close();

if ($accreditedVoters > $pu['registered_voters']) {
    Response::error('Accredited voters cannot exceed registered voters (' . $pu['registered_voters'] . ')', 422);
}

// Check if accreditation already recorded today
$today = date('Y-m-d');
$checkResult = $db->query("
    SELECT COUNT(*) as count FROM results_ec8a 
    WHERE pu_id = $puId AND DATE(created_at) = '$today'
");

if ($checkResult->fetch_assoc()['count'] > 0) {
    // Update existing record
    $stmt = $db->prepare("
        UPDATE results_ec8a SET accredited_voters = ?, updated_at = NOW()
        WHERE pu_id = ? AND DATE(created_at) = ?
    ");
    $stmt->bind_param("iis", $accreditedVoters, $puId, $today);
} else {
    // Insert new record
    $stmt = $db->prepare("
        INSERT INTO results_ec8a (
            tenant_id, election_id, pu_id, ward_id, lga_id, state_id,
            agent_id, assignment_id, pu_code, pu_name, registered_voters,
            accredited_voters, gps_lat, gps_lng, device_id, status, created_at
        ) SELECT 
            ?, ?, ?, ?, ?, ?,
            ?, aa.id, pu.code, pu.name, pu.registered_voters,
            ?, ?, ?, ?, 'pending', NOW()
        FROM agent_assignments aa
        JOIN polling_units pu ON pu.id = aa.pu_id
        WHERE aa.id = (
            SELECT id FROM agent_assignments 
            WHERE user_id = ? AND pu_id = ? AND status = 'active'
            LIMIT 1
        )
    ");
    
    $tenantId = $assignment['tenant_id'];
    $wardId = $assignment['ward_id'];
    $lgaId = $assignment['lga_id'];
    $stateId = $assignment['state_id'];
    
    $stmt->bind_param(
        "iiiiiiiiiiss", 
        $tenantId, $electionId, $puId, $wardId, $lgaId, $stateId,
        $user['id'], $accreditedVoters, $gpsLat, $gpsLng, $deviceId,
        $user['id'], $puId
    );
}

if (!$stmt->execute()) {
    Response::error('Failed to record accreditation: ' . $stmt->error, 500);
}

$stmt->close();

// Update polling unit accredited voters
$db->query("
    UPDATE polling_units SET accredited_voters = $accreditedVoters WHERE id = $puId
");

// Log activity
$db->query("
    INSERT INTO activity_logs (user_id, activity_type, description, device_id, created_at)
    VALUES ({$user['id']}, 'accreditation', 
            'Recorded accreditation: $accreditedVoters voters at PU: $puId',
            '$deviceId', NOW())
");

Response::success([
    'accredited_voters' => $accreditedVoters,
    'pu_id' => $puId,
    'registered_voters' => $pu['registered_voters'],
    'turnout_percentage' => round(($accreditedVoters / $pu['registered_voters']) * 100, 2)
], 'Accreditation recorded successfully');
?>