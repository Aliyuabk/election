<?php
// ============================================================
// API: GET POLLING UNIT DETAILS FOR VIEW
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
    // Get PU details with all hierarchy info
    $stmt = $db->prepare("
        SELECT pu.*, 
               w.name as ward_name, w.code as ward_code,
               l.name as lga_name, l.code as lga_code,
               s.name as state_name, s.code as state_code
        FROM polling_units pu 
        LEFT JOIN wards w ON pu.ward_id = w.id 
        LEFT JOIN lgas l ON w.lga_id = l.id 
        LEFT JOIN states s ON l.state_id = s.id 
        WHERE pu.id = ?
    ");
    $stmt->execute([$id]);
    $pu = $stmt->fetch();
    
    if (!$pu) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Polling Unit not found']);
        exit();
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => $pu
    ]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>