<?php
/**
 * Get Dashboard Data
 * GET /api/user/dashboard
 */

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/Response.php';

$auth = new Auth();
$user = $auth->authenticate();

$db = Database::getInstance();

// Get role level
$stmt = $db->prepare("SELECT level FROM roles WHERE id = ?");
$stmt->bind_param("i", $user['role_id']);
$stmt->execute();
$result = $stmt->get_result();
$role = $result->fetch_assoc();
$stmt->close();

$roleLevel = $role['level'] ?? '';

$dashboardData = [
    'user' => [
        'id' => $user['id'],
        'name' => $user['first_name'] . ' ' . $user['last_name'],
        'role_level' => $roleLevel
    ],
    'stats' => [],
    'recent_activity' => [],
    'notifications' => [],
    'pending_tasks' => []
];

// Get statistics based on role
$stats = [];

// Check if user has polling unit assignment
$puCheck = $db->query("
    SELECT COUNT(*) as count FROM agent_assignments 
    WHERE user_id = {$user['id']} AND status = 'active'
");
$puCount = $puCheck->fetch_assoc();
$stats['assigned_polling_units'] = $puCount['count'];

// Get check-in status
$checkinCheck = $db->query("
    SELECT COUNT(*) as count FROM agent_checkins 
    WHERE agent_id = {$user['id']} AND DATE(created_at) = CURDATE()
");
$stats['today_checkins'] = $checkinCheck->fetch_assoc()['count'];

// Get incidents count
$incidentCheck = $db->query("
    SELECT COUNT(*) as count FROM incidents 
    WHERE reporter_id = {$user['id']} AND status != 'resolved'
");
$stats['pending_incidents'] = $incidentCheck->fetch_assoc()['count'];

// Get unread notifications
$notificationCheck = $db->query("
    SELECT COUNT(*) as count FROM notifications 
    WHERE user_id = {$user['id']} AND is_read = 0
");
$stats['unread_notifications'] = $notificationCheck->fetch_assoc()['count'];

// Get pending checklists
$checklistCheck = $db->query("
    SELECT COUNT(*) as count FROM election_checklists 
    WHERE user_id = {$user['id']} AND status = 'draft'
");
$stats['pending_checklists'] = $checklistCheck->fetch_assoc()['count'];

// Get EC8A pending uploads
$ec8aCheck = $db->query("
    SELECT COUNT(*) as count FROM results_ec8a 
    WHERE agent_id = {$user['id']} AND status = 'pending'
");
$stats['pending_ec8a'] = $ec8aCheck->fetch_assoc()['count'];

$dashboardData['stats'] = $stats;

// Get recent activity
$activityResult = $db->query("
    SELECT activity_type, description, created_at 
    FROM activity_logs 
    WHERE user_id = {$user['id']} 
    ORDER BY created_at DESC 
    LIMIT 5
");

while ($row = $activityResult->fetch_assoc()) {
    $dashboardData['recent_activity'][] = $row;
}

// Get recent notifications
$notificationResult = $db->query("
    SELECT id, type, title, message, is_read, created_at 
    FROM notifications 
    WHERE user_id = {$user['id']} 
    ORDER BY created_at DESC 
    LIMIT 5
");

while ($row = $notificationResult->fetch_assoc()) {
    $row['is_read'] = (bool)$row['is_read'];
    $dashboardData['notifications'][] = $row;
}

// Get assigned polling unit
$puResult = $db->query("
    SELECT pu.id, pu.code, pu.name, pu.registered_voters, pu.accredited_voters
    FROM agent_assignments aa
    JOIN polling_units pu ON aa.pu_id = pu.id
    WHERE aa.user_id = {$user['id']} AND aa.status = 'active'
    LIMIT 1
");
$dashboardData['assigned_pu'] = $puResult->fetch_assoc();

Response::success($dashboardData);