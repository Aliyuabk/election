<?php
// ============================================================
// API: GET STATE DETAILS FOR VIEW
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

// Get state ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit();
}

try {
    // Get state details
    $stmt = $db->prepare("SELECT * FROM states WHERE id = ?");
    $stmt->execute([$id]);
    $state = $stmt->fetch();
    
    if (!$state) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'State not found']);
        exit();
    }
    
    // Get counts
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM lgas WHERE state_id = ?");
    $stmt->execute([$id]);
    $lga_count = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM wards w 
        LEFT JOIN lgas l ON w.lga_id = l.id 
        WHERE l.state_id = ?
    ");
    $stmt->execute([$id]);
    $ward_count = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM polling_units pu 
        LEFT JOIN wards w ON pu.ward_id = w.id 
        LEFT JOIN lgas l ON w.lga_id = l.id 
        WHERE l.state_id = ?
    ");
    $stmt->execute([$id]);
    $pu_count = $stmt->fetch()['count'] ?? 0;
    
    // Get recent LGAs
    $stmt = $db->prepare("
        SELECT name, code, registered_voters, is_active 
        FROM lgas 
        WHERE state_id = ? 
        ORDER BY name 
        LIMIT 5
    ");
    $stmt->execute([$id]);
    $recent_lgas = $stmt->fetchAll();
    
    // Get total voters
    $stmt = $db->prepare("
        SELECT SUM(registered_voters) as total 
        FROM lgas 
        WHERE state_id = ?
    ");
    $stmt->execute([$id]);
    $total_voters = $stmt->fetch()['total'] ?? 0;
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => [
            'state' => $state,
            'stats' => [
                'lga_count' => $lga_count,
                'ward_count' => $ward_count,
                'pu_count' => $pu_count,
                'total_voters' => $total_voters
            ],
            'recent_lgas' => $recent_lgas
        ]
    ]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>