<?php
// ============================================================
// AJAX - GET SENATORIAL DISTRICTS BY STATE
// ============================================================
require_once '../../../config/config.php';
require_once '../../../includes/functions.php';

// Set JSON header
header('Content-Type: application/json');

// Get State ID
$state_id = isset($_GET['state_id']) ? (int)$_GET['state_id'] : 0;

if ($state_id <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT id, name, code 
        FROM senatorial_districts 
        WHERE state_id = ? AND is_active = 1 
        ORDER BY name ASC
    ");
    $stmt->execute([$state_id]);
    $districts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($districts);
} catch (Exception $e) {
    error_log("AJAX get_senatorial_districts error: " . $e->getMessage());
    echo json_encode([]);
}
exit;
?>