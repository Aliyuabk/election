<?php
/**
 * Get User Profile
 * GET /api/user/profile
 */

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/Response.php';

$auth = new Auth();
$user = $auth->authenticate();

$db = Database::getInstance();

// Get full user details
$stmt = $db->prepare("
    SELECT 
        u.id, u.user_code, u.first_name, u.last_name, u.email, u.phone,
        u.gender, u.date_of_birth, u.photograph_url,
        u.state_id, u.lga_id, u.ward_id, u.pu_id,
        u.jurisdiction_type, u.jurisdiction_id,
        r.name as role_name, r.level as role_level,
        t.name as tenant_name,
        u.fingerprint_enabled, u.device_bound,
        u.last_login_at, u.last_login_ip
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    LEFT JOIN tenants t ON u.tenant_id = t.id
    WHERE u.id = ?
");

$stmt->bind_param("i", $user['id']);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$stmt->close();

if (!$userData) {
    Response::error('User not found', 404);
}

// Get assigned polling unit
$puData = null;
if ($userData['pu_id']) {
    $stmt = $db->prepare("
        SELECT pu.id, pu.code, pu.name, pu.address, pu.gps_lat, pu.gps_lng,
               pu.registered_voters, pu.accredited_voters,
               w.name as ward_name, l.name as lga_name, s.name as state_name,
               w.id as ward_id, l.id as lga_id, s.id as state_id
        FROM polling_units pu
        LEFT JOIN wards w ON pu.ward_id = w.id
        LEFT JOIN lgas l ON w.lga_id = l.id
        LEFT JOIN states s ON l.state_id = s.id
        WHERE pu.id = ?
    ");
    $stmt->bind_param("i", $userData['pu_id']);
    $stmt->execute();
    $puResult = $stmt->get_result();
    $puData = $puResult->fetch_assoc();
    $stmt->close();
}

// Get assignments
$assignments = [];
$stmt = $db->prepare("
    SELECT 
        aa.id, aa.assignment_type, aa.status,
        e.id as election_id, e.name as election_name, e.election_date,
        pu.name as pu_name, pu.code as pu_code,
        w.name as ward_name, l.name as lga_name
    FROM agent_assignments aa
    LEFT JOIN elections e ON aa.election_id = e.id
    LEFT JOIN polling_units pu ON aa.pu_id = pu.id
    LEFT JOIN wards w ON aa.ward_id = w.id
    LEFT JOIN lgas l ON aa.lga_id = l.id
    WHERE aa.user_id = ? AND aa.status != 'completed'
    ORDER BY aa.assigned_at DESC
");

$stmt->bind_param("i", $user['id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $assignments[] = $row;
}
$stmt->close();

// Get unread notifications count
$notificationResult = $db->query("
    SELECT COUNT(*) as unread_count FROM notifications 
    WHERE user_id = {$user['id']} AND is_read = 0
");
$unreadCount = $notificationResult->fetch_assoc()['unread_count'];

Response::success([
    'user' => $userData,
    'assigned_polling_unit' => $puData,
    'assignments' => $assignments,
    'unread_notifications' => intval($unreadCount)
]);