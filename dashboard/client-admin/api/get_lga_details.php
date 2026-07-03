<?php
// ============================================================
// API: GET LGA DETAILS FOR VIEW
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
    // Get LGA details with state info
    $stmt = $db->prepare("
        SELECT l.*, s.name as state_name, s.code as state_code 
        FROM lgas l 
        LEFT JOIN states s ON l.state_id = s.id 
        WHERE l.id = ?
    ");
    $stmt->execute([$id]);
    $lga = $stmt->fetch();
    
    if (!$lga) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'LGA not found']);
        exit();
    }
    
    // Get counts
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM wards WHERE lga_id = ?");
    $stmt->execute([$id]);
    $ward_count = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM polling_units pu 
        LEFT JOIN wards w ON pu.ward_id = w.id 
        WHERE w.lga_id = ?
    ");
    $stmt->execute([$id]);
    $pu_count = $stmt->fetch()['count'] ?? 0;
    
    // Get recent wards
    $stmt = $db->prepare("
        SELECT name, code, registered_voters, is_active 
        FROM wards 
        WHERE lga_id = ? 
        ORDER BY name 
        LIMIT 5
    ");
    $stmt->execute([$id]);
    $recent_wards = $stmt->fetchAll();
    
    // Get total voters
    $stmt = $db->prepare("
        SELECT SUM(registered_voters) as total 
        FROM wards 
        WHERE lga_id = ?
    ");
    $stmt->execute([$id]);
    $total_voters = $stmt->fetch()['total'] ?? 0;
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => [
            'lga' => $lga,
            'stats' => [
                'ward_count' => $ward_count,
                'pu_count' => $pu_count,
                'total_voters' => $total_voters
            ],
            'recent_wards' => $recent_wards
        ]
    ]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>