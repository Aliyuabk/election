<?php
// ============================================================
// GET POLLING UNITS BY WARD ID - AJAX
// ============================================================
require_once '../../../config/config.php';
require_once '../../../includes/session.php';
require_once '../../../includes/functions.php';

// Start session and check login
SessionManager::start();

// Redirect if not logged in
if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get database connection
$db = getDB();

// Set JSON response header
header('Content-Type: application/json');

// Get ward_id from request
$ward_id = isset($_GET['ward_id']) ? (int)$_GET['ward_id'] : 0;

if ($ward_id <= 0) {
    echo json_encode([]);
    exit();
}

try {
    // Fetch Polling Units for the given Ward
    $stmt = $db->prepare("SELECT id, name, code FROM polling_units WHERE ward_id = ? AND is_active = 1 ORDER BY name");
    $stmt->execute([$ward_id]);
    $polling_units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($polling_units);
} catch (PDOException $e) {
    error_log("Error fetching Polling Units: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
} catch (Exception $e) {
    error_log("Error fetching Polling Units: " . $e->getMessage());
    echo json_encode([]);
}
?>