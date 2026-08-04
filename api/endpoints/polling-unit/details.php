<?php
/**
 * Get Polling Unit Details
 * GET /api/polling-unit/details?id={pu_id}
 */

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/response.php';

$auth = new Auth();
$user = $auth->authenticate();

$puId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($puId <= 0) {
    Response::error('Polling Unit ID is required', 400);
}

$db = Database::getInstance();

// Verify user has access to this PU
$accessCheck = $db->prepare("
    SELECT id FROM agent_assignments 
    WHERE user_id = ? AND pu_id = ? AND status = 'active'
");
$accessCheck->bind_param("ii", $user['id'], $puId);
$accessCheck->execute();
$accessResult = $accessCheck->get_result();

if ($accessResult->num_rows === 0) {
    // Check if user is coordinator with jurisdiction
    $roleCheck = $db->prepare("
        SELECT level FROM roles WHERE id = ?
    ");
    $roleCheck->bind_param("i", $user['role_id']);
    $roleCheck->execute();
    $roleResult = $roleCheck->get_result();
    $role = $roleResult->fetch_assoc();
    $roleCheck->close();
    
    $coordinatorLevels = ['lga', 'ward', 'national', 'state'];
    if (!in_array($role['level'] ?? '', $coordinatorLevels)) {
        Response::error('Access denied to this polling unit', 403);
    }
}
$accessCheck->close();

// Get PU details
$stmt = $db->prepare("
    SELECT 
        pu.id, pu.code, pu.name, pu.description, pu.address,
        pu.gps_lat, pu.gps_lng, pu.registered_voters, pu.accredited_voters,
        pu.is_rural, pu.network_quality,
        w.id as ward_id, w.name as ward_name,
        l.id as lga_id, l.name as lga_name,
        s.id as state_id, s.name as state_name
    FROM polling_units pu
    LEFT JOIN wards w ON pu.ward_id = w.id
    LEFT JOIN lgas l ON w.lga_id = l.id
    LEFT JOIN states s ON l.state_id = s.id
    WHERE pu.id = ?
");

$stmt->bind_param("i", $puId);
$stmt->execute();
$result = $stmt->get_result();
$puData = $result->fetch_assoc();
$stmt->close();

if (!$puData) {
    Response::error('Polling Unit not found', 404);
}

// Get coordinator info
$coordinatorResult = $db->query("
    SELECT u.first_name, u.last_name, u.email, u.phone
    FROM users u
    JOIN agent_assignments aa ON aa.user_id = u.id
    WHERE aa.pu_id = $puId 
    AND u.role_id IN (SELECT id FROM roles WHERE level IN ('lga', 'ward'))
    AND aa.status = 'active'
    LIMIT 1
");
$coordinator = $coordinatorResult->fetch_assoc();
$puData['coordinator'] = $coordinator;

// Get recent results
$resultResult = $db->query("
    SELECT party_votes_json, valid_votes, rejected_votes, total_votes_cast,
           accredited_voters, created_at, status
    FROM results_ec8a
    WHERE pu_id = $puId
    ORDER BY created_at DESC
    LIMIT 1
");
$puData['latest_result'] = $resultResult->fetch_assoc();

Response::success($puData);