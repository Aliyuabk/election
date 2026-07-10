<?php
require_once '../../../config/config.php';
require_once '../../../includes/functions.php';

$ward_id = isset($_GET['ward_id']) ? (int)$_GET['ward_id'] : 0;

if ($ward_id <= 0) {
    echo json_encode([]);
    exit;
}

$db = getDB();
try {
    $stmt = $db->prepare("SELECT id, name, code FROM polling_units WHERE ward_id = ? AND is_active = 1 ORDER BY name ASC");
    $stmt->execute([$ward_id]);
    $pus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($pus);
} catch (Exception $e) {
    echo json_encode([]);
}
?>