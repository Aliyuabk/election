<?php
// permission-data.php - Returns permission data as JSON
header('Content-Type: application/json');
require_once 'includes/db.php';

$db = Database::getInstance()->getConnection();
$permission_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$permission_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid permission ID']);
    exit;
}

$stmt = $db->prepare("SELECT * FROM permissions WHERE id = ?");
$stmt->execute([$permission_id]);
$permission = $stmt->fetch();

if (!$permission) {
    echo json_encode(['success' => false, 'message' => 'Permission not found']);
    exit;
}

echo json_encode([
    'success' => true,
    'permission' => [
        'id' => $permission['id'],
        'module' => $permission['module'],
        'action' => $permission['action'],
        'slug' => $permission['slug'],
        'name' => $permission['name'],
        'description' => $permission['description'],
        'is_enabled' => $permission['is_enabled']
    ]
]);
?>