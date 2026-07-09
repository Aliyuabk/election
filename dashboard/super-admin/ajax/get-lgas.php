<?php
// ============================================================
// GET LGAS BY STATE ID - AJAX
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

// Get state_id from request
$state_id = isset($_GET['state_id']) ? (int)$_GET['state_id'] : 0;

if ($state_id <= 0) {
    echo json_encode([]);
    exit();
}

try {
    // Fetch LGAs for the given state
    $stmt = $db->prepare("SELECT id, name FROM lgas WHERE state_id = ? AND is_active = 1 ORDER BY name");
    $stmt->execute([$state_id]);
    $lgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no LGAs found, return empty array
    echo json_encode($lgas);
} catch (Exception $e) {
    // Log error and return empty array
    error_log("Error fetching LGAs: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>