<?php
/**
 * Get Assigned Polling Unit
 * GET /api/polling-unit/assigned
 */

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/Response.php';

$auth = new Auth();
$user = $auth->authenticate();

$db = Database::getInstance();

// Get assigned polling units
$stmt = $db->prepare("
    SELECT 
        aa.id as assignment_id,
        aa.assignment_type,
        aa.status as assignment_status,
        pu.id, pu.code, pu.name, pu.address, pu.gps_lat, pu.gps_lng,
        pu.registered_voters, pu.accredited_voters,
        w.name as ward_name, l.name as lga_name, s.name as state_name,
        e.id as election_id, e.name as election_name, e.election_date
    FROM agent_assignments aa
    JOIN polling_units pu ON aa.pu_id = pu.id
    LEFT JOIN wards w ON pu.ward_id = w.id
    LEFT JOIN lgas l ON w.lga_id = l.id
    LEFT JOIN states s ON l.state_id = s.id
    LEFT JOIN elections e ON aa.election_id = e.id
    WHERE aa.user_id = ? AND aa.status = 'active'
    ORDER BY aa.assigned_at DESC
");

$stmt->bind_param("i", $user['id']);
$stmt->execute();
$result = $stmt->get_result();

$assignments = [];
while ($row = $result->fetch_assoc()) {
    $assignments[] = $row;
}
$stmt->close();

Response::success([
    'assignments' => $assignments,
    'total' => count($assignments)
]);