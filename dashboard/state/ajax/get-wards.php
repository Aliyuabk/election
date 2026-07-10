<?php
// ============================================================
// GET WARDS BY LGA ID - AJAX
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

// Get lga_id from request
$lga_id = isset($_GET['lga_id']) ? (int)$_GET['lga_id'] : 0;

if ($lga_id <= 0) {
    echo json_encode([]);
    exit();
}

try {
    // Fetch Wards for the given LGA
    $stmt = $db->prepare("SELECT id, name FROM wards WHERE lga_id = ? AND is_active = 1 ORDER BY name");
    $stmt->execute([$lga_id]);
    $wards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($wards);
} catch (PDOException $e) {
    error_log("Error fetching Wards: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
} catch (Exception $e) {
    error_log("Error fetching Wards: " . $e->getMessage());
    echo json_encode([]);
}
?>