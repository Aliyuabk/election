<?php
// ============================================================
// API: GET WARD DETAILS FOR VIEW
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

// Get Ward ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit();
}

try {
    // Get Ward details with LGA and State info
    $stmt = $db->prepare("
        SELECT w.*, 
               l.name as lga_name, l.code as lga_code,
               s.name as state_name, s.code as state_code
        FROM wards w 
        LEFT JOIN lgas l ON w.lga_id = l.id 
        LEFT JOIN states s ON l.state_id = s.id 
        WHERE w.id = ?
    ");
    $stmt->execute([$id]);
    $ward = $stmt->fetch();
    
    if (!$ward) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Ward not found']);
        exit();
    }
    
    // Get PU count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM polling_units WHERE ward_id = ?");
    $stmt->execute([$id]);
    $pu_count = $stmt->fetch()['count'] ?? 0;
    
    // Get recent PUs
    $stmt = $db->prepare("
        SELECT name, code, registered_voters, is_active, network_quality 
        FROM polling_units 
        WHERE ward_id = ? 
        ORDER BY name 
        LIMIT 5
    ");
    $stmt->execute([$id]);
    $recent_pus = $stmt->fetchAll();
    
    // Get total voters
    $stmt = $db->prepare("
        SELECT SUM(registered_voters) as total 
        FROM polling_units 
        WHERE ward_id = ?
    ");
    $stmt->execute([$id]);
    $total_voters = $stmt->fetch()['total'] ?? 0;
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => [
            'ward' => $ward,
            'stats' => [
                'pu_count' => $pu_count,
                'total_voters' => $total_voters
            ],
            'recent_pus' => $recent_pus
        ]
    ]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>