<?php
/**
 * Record Vote Counts
 * POST /api/vote-count/record
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

$errors = Validator::required($data, ['election_id', 'pu_id', 'party_votes']);
if (!empty($errors)) {
    Response::validationError($errors);
}

$electionId = intval($data['election_id']);
$puId = intval($data['pu_id']);
$partyVotes = $data['party_votes'];
$validVotes = isset($data['valid_votes']) ? intval($data['valid_votes']) : 0;
$rejectedVotes = isset($data['rejected_votes']) ? intval($data['rejected_votes']) : 0;
$totalVotes = isset($data['total_votes']) ? intval($data['total_votes']) : 0;
$gpsLat = isset($data['gps_lat']) ? floatval($data['gps_lat']) : null;
$gpsLng = isset($data['gps_lng']) ? floatval($data['gps_lng']) : null;
$deviceId = isset($data['device_id']) ? Validator::sanitize($data['device_id']) : null;

// Validate party votes
if (!is_array($partyVotes)) {
    Response::error('Party votes must be an array', 422);
}

$totalPartyVotes = array_sum($partyVotes);

// Validate totals
if ($validVotes > 0 && $validVotes != $totalPartyVotes) {
    Response::error('Valid votes must equal sum of party votes', 422);
}

$db = Database::getInstance();

// Verify user has access
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

// Check if result already exists for today
$today = date('Y-m-d');
$checkResult = $db->query("
    SELECT id FROM results_ec8a 
    WHERE pu_id = $puId AND DATE(created_at) = '$today' AND status != 'rejected'
");

$partyVotesJson = json_encode($partyVotes);

if ($checkResult->num_rows > 0) {
    // Update existing
    $stmt = $db->prepare("
        UPDATE results_ec8a SET 
            party_votes_json = ?,
            valid_votes = ?,
            rejected_votes = ?,
            total_votes_cast = ?,
            gps_lat = ?,
            gps_lng = ?,
            updated_at = NOW()
        WHERE pu_id = ? AND DATE(created_at) = ?
    ");
    $stmt->bind_param(
        "siiiddis", 
        $partyVotesJson, $validVotes, $rejectedVotes, $totalVotes,
        $gpsLat, $gpsLng, $puId, $today
    );
} else {
    // Insert new
    $stmt = $db->prepare("
        INSERT INTO results_ec8a (
            tenant_id, election_id, pu_id, ward_id, lga_id, state_id,
            agent_id, assignment_id, pu_code, pu_name, registered_voters,
            party_votes_json, valid_votes, rejected_votes, total_votes_cast,
            gps_lat, gps_lng, device_id, status, created_at
        ) SELECT 
            ?, ?, ?, ?, ?, ?,
            ?, aa.id, pu.code, pu.name, pu.registered_voters,
            ?, ?, ?, ?,
            ?, ?, ?, 'pending', NOW()
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
        "iiiiiiisiiiddiss",
        $tenantId, $electionId, $puId, $wardId, $lgaId, $stateId,
        $user['id'], $partyVotesJson, $validVotes, $rejectedVotes, $totalVotes,
        $gpsLat, $gpsLng, $deviceId,
        $user['id'], $puId
    );
}

if (!$stmt->execute()) {
    Response::error('Failed to record vote count: ' . $stmt->error, 500);
}

$stmt->close();

// Log activity
$db->query("
    INSERT INTO activity_logs (user_id, activity_type, description, device_id, created_at)
    VALUES ({$user['id']}, 'vote_count', 
            'Recorded vote counts at PU: $puId',
            '$deviceId', NOW())
");

Response::success([
    'pu_id' => $puId,
    'valid_votes' => $validVotes,
    'rejected_votes' => $rejectedVotes,
    'total_votes' => $totalVotes,
    'party_votes' => $partyVotes
], 'Vote counts recorded successfully');
?>