<?php
// ============================================================
// API: GET LGA DATA FOR EDIT
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

// Get LGA ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit();
}

try {
    $stmt = $db->prepare("SELECT * FROM lgas WHERE id = ?");
    $stmt->execute([$id]);
    $lga = $stmt->fetch();
    
    if ($lga) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $lga]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'LGA not found']);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>