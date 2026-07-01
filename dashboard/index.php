<?php
require_once '../config/database.php';
require_once '../includes/session.php';

SessionManager::start();

// Check if user is logged in
if (!SessionManager::isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit();
}

// Check session timeout
if (!SessionManager::checkSessionTimeout()) {
    header('Location: ../auth/login.php?timeout=1');
    exit();
}

$db = Database::getInstance()->getConnection();
$user_id = SessionManager::get('user_id');

// Get user role
$stmt = $db->prepare("SELECT r.level, u.tenant_id FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$role = $user['level'] ?? 'client_admin';
$tenant_id = $user['tenant_id'] ?? null;

// Redirect to appropriate dashboard
$dashboardMap = [
    'super_admin' => 'super-admin/',
    'client_admin' => 'client-admin/',
    'national' => 'national/',
    'state' => 'state/',
    'senatorial' => 'senatorial/',
    'federal_constituency' => 'federal-constituency/',
    'lga' => 'lga/',
    'ward' => 'ward/',
    'pu_agent' => 'agent/',
    'party_agent' => 'party-agent/',
    'volunteer' => 'volunteer/',
    'observer' => 'observer/',
    'situation_room' => 'situation-room/',
    'finance_officer' => 'finance-officer/',
    'citizen' => 'citizen/'
];

$dashboard = $dashboardMap[$role] ?? 'client-admin/';

// Load the appropriate dashboard
include $dashboard;
?>