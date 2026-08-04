<?php
/**
 * List Incident Reports
 * GET /api/incidents/list
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
$status = isset($_GET['status']) ? Validator::sanitize($_GET['status']) : null;

$db = Database::getInstance();

$sql = "
    SELECT 
        i.id, i.incident_type, i.severity, i.is_panic,
        i.title, i.description, i.status, i.created_at,
        i.gps_lat, i.gps_lng,
        pu.name as pu_name, pu.code as pu_code,
        w.name as ward_name, l.name as lga_name, s.name as state_name
    FROM incidents i
    LEFT JOIN polling_units pu ON i.pu_id = pu.id
    LEFT JOIN wards w ON i.ward_id = w.id
    LEFT JOIN lgas l ON i.lga_id = l.id
    LEFT JOIN states s ON i.state_id = s.id
    WHERE i.reporter_id = {$user['id']}
";

if ($status) {
    $sql .= " AND i.status = '" . $db->escapeString($status) . "'";
}

$sql .= " ORDER BY i.created_at DESC LIMIT $limit OFFSET $offset";

$result = $db->query($sql);

$incidents = [];
while ($row = $result->fetch_assoc()) {
    $row['is_panic'] = (bool)$row['is_panic'];
    $incidents[] = $row;
}

// Get total count
$countSql = "
    SELECT COUNT(*) as total FROM incidents WHERE reporter_id = {$user['id']}
";
if ($status) {
    $countSql .= " AND status = '" . $db->escapeString($status) . "'";
}

$countResult = $db->query($countSql);
$total = $countResult->fetch_assoc()['total'];

Response::success([
    'incidents' => $incidents,
    'pagination' => [
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset
    ]
]);
?>