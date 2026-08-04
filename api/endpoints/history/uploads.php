<?php
/**
 * Get Upload History
 * GET /api/history/uploads
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

$db = Database::getInstance();

// Get EC8A history
$sql = "
    SELECT 
        'ec8a' as record_type,
        id, pu_id, pu_code, pu_name,
        valid_votes, rejected_votes, total_votes_cast,
        photo_url, status, created_at,
        verified_by, verified_at, rejection_reason
    FROM results_ec8a
    WHERE agent_id = {$user['id']}
    UNION ALL
    SELECT 
        'incident' as record_type,
        id, pu_id, '' as pu_code, '' as pu_name,
        0 as valid_votes, 0 as rejected_votes, 0 as total_votes_cast,
        '' as photo_url, status, created_at,
        NULL as verified_by, NULL as verified_at, NULL as rejection_reason
    FROM incidents
    WHERE reporter_id = {$user['id']}
    ORDER BY created_at DESC
    LIMIT $limit OFFSET $offset
";

$result = $db->query($sql);

$records = [];
while ($row = $result->fetch_assoc()) {
    $records[] = $row;
}

// Get counts by status
$ec8aCounts = $db->query("
    SELECT status, COUNT(*) as count 
    FROM results_ec8a 
    WHERE agent_id = {$user['id']}
    GROUP BY status
");

$counts = [];
while ($row = $ec8aCounts->fetch_assoc()) {
    $counts[$row['status']] = intval($row['count']);
}

Response::success([
    'records' => $records,
    'status_counts' => $counts,
    'pagination' => [
        'limit' => $limit,
        'offset' => $offset
    ]
]);
?>