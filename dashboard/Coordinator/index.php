<?php
// ============================================================
// DASHBOARD ACCESS CONTROL
// ============================================================

// At the top of each dashboard page
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Start session
SessionManager::start();

// Redirect if not logged in
if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

// Get user role and permissions
$user_id = SessionManager::get('user_id');
$role_level = SessionManager::get('role_level');
$role_id = SessionManager::get('role_id');

// Define allowed roles for each dashboard
$allowed_roles = [
    'national' => ['national'],
    'state' => ['state'],
    'senatorial' => ['senatorial'],
    'federal_constituency' => ['federal_constituency'],
    'lga' => ['lga'],
    'ward' => ['ward'],
    'pu_agent' => ['pu_agent'],
    'client_admin' => ['client_admin', 'super_admin']
];

// Check if current role is allowed
$current_page = basename(dirname(__FILE__));
$allowed = $allowed_roles[$current_page] ?? [];

if (!in_array($role_level, $allowed)) {
    // Redirect to appropriate dashboard
    $redirect_map = [
        'national' => '../national/',
        'state' => '../state/',
        'senatorial' => '../senatorial/',
        'federal_constituency' => '../federal-constituency/',
        'lga' => '../lga/',
        'ward' => '../ward/',
        'pu_agent' => '../pu-agent/',
        'client_admin' => '../client-admin/',
        'super_admin' => '../super-admin/'
    ];
    
    $redirect = $redirect_map[$role_level] ?? '../client-admin/';
    header('Location: ' . $redirect);
    exit();
}

// Get user data for dashboard
$db = getDB();
$stmt = $db->prepare("
    SELECT u.*, r.level as role_level, r.permissions_json, 
           s.name as state_name, l.name as lga_name, w.name as ward_name
    FROM users u
    JOIN roles r ON u.role_id = r.id
    LEFT JOIN states s ON u.state_id = s.id
    LEFT JOIN lgas l ON u.lga_id = l.id
    LEFT JOIN wards w ON u.ward_id = w.id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: ../../auth/logout.php');
    exit();
}

// Get jurisdiction name for display
$jurisdiction_name = '';
switch($role_level) {
    case 'national':
        $jurisdiction_name = 'Nigeria';
        break;
    case 'state':
        $jurisdiction_name = $user['state_name'] ?? 'State';
        break;
    case 'senatorial':
        $jurisdiction_name = 'Senatorial District';
        break;
    case 'federal_constituency':
        $jurisdiction_name = 'Federal Constituency';
        break;
    case 'lga':
        $jurisdiction_name = $user['lga_name'] ?? 'LGA';
        break;
    case 'ward':
        $jurisdiction_name = $user['ward_name'] ?? 'Ward';
        break;
    case 'pu_agent':
        $jurisdiction_name = 'Polling Unit';
        break;
    default:
        $jurisdiction_name = 'Dashboard';
}

// Store in session for sidebar
SessionManager::set('state_name', $user['state_name'] ?? null);
SessionManager::set('lga_name', $user['lga_name'] ?? null);
SessionManager::set('ward_name', $user['ward_name'] ?? null);
SessionManager::set('jurisdiction_name', $jurisdiction_name);
?>