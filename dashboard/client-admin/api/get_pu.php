<?php
// ============================================================
// API: GET POLLING UNIT DATA FOR EDIT
// ============================================================
require_once '../../../config/config.php';
require_once '../../../includes/session.php';
require_once '../../../includes/functions.php';

// Start session and check login
SessionManager::start();

// Redirect if not logged in
if (!SessionManager::isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check role
if (SessionManager::get('role_level') !== 'client_admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get database connection
$db = getDB();

// Get Polling Unit ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit();
}

try {
    $stmt = $db->prepare("SELECT * FROM polling_units WHERE id = ?");
    $stmt->execute([$id]);
    $pu = $stmt->fetch();
    
    if ($pu) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $pu]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Polling Unit not found']);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>