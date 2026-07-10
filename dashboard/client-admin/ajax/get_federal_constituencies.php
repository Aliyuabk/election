<?php
require_once '../../../config/config.php';
require_once '../../../includes/functions.php';

$state_id = isset($_GET['state_id']) ? (int)$_GET['state_id'] : 0;

if ($state_id <= 0) {
    echo json_encode([]);
    exit;
}

$db = getDB();
try {
    $stmt = $db->prepare("SELECT id, name FROM federal_constituencies WHERE state_id = ? AND is_active = 1 ORDER BY name ASC");
    $stmt->execute([$state_id]);
    $constituencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($constituencies);
} catch (Exception $e) {
    echo json_encode([]);
}
?>