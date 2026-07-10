<?php
// ============================================================
// AJAX - GET WARDS BY LGA
// ============================================================
require_once '../../../config/config.php';
require_once '../../../includes/functions.php';

// Set JSON header
header('Content-Type: application/json');

// Get LGA ID
$lga_id = isset($_GET['lga_id']) ? (int)$_GET['lga_id'] : 0;

if ($lga_id <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT id, name, code 
        FROM wards 
        WHERE lga_id = ? AND is_active = 1 
        ORDER BY name ASC
    ");
    $stmt->execute([$lga_id]);
    $wards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($wards);
} catch (Exception $e) {
    error_log("AJAX get_wards error: " . $e->getMessage());
    echo json_encode([]);
}
exit;
?>