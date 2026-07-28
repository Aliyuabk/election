<?php
// ============================================================
// SENATORIAL COORDINATOR - DELETE NOTIFICATION
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'senatorial') {
    header('Location: ../client-admin/');
    exit();
}

$user_id = SessionManager::get('user_id');
$db = getDB();

// ============================================================
// GET NOTIFICATION ID
// ============================================================
$notification_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$notification_id) {
    header('Location: notifications.php');
    exit();
}

// ============================================================
// DELETE NOTIFICATION
// ============================================================
try {
    $stmt = $db->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->execute([$notification_id, $user_id]);
} catch (Exception $e) {
    error_log("Error deleting notification: " . $e->getMessage());
}

header('Location: notifications.php');
exit();
?>