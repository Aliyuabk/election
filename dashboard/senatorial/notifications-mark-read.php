<?php
// ============================================================
// SENATORIAL COORDINATOR - MARK NOTIFICATION AS READ
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
// MARK AS READ
// ============================================================
try {
    $stmt = $db->prepare("
        UPDATE notifications 
        SET is_read = 1, read_at = NOW()
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$notification_id, $user_id]);
    
    // Get notification action URL if exists
    $stmt = $db->prepare("SELECT action_url FROM notifications WHERE id = ?");
    $stmt->execute([$notification_id]);
    $notification = $stmt->fetch();
    
    if ($notification && $notification['action_url']) {
        header('Location: ' . $notification['action_url']);
        exit();
    }
    
} catch (Exception $e) {
    error_log("Error marking notification as read: " . $e->getMessage());
}

header('Location: notifications.php');
exit();
?>