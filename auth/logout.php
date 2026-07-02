<?php
// ============================================================
// LOGOUT - Destroy session and redirect
// ============================================================
require_once '../config/config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

SessionManager::start();

// Log activity before logout
if (SessionManager::isLoggedIn()) {
    $userId = SessionManager::get('user_id');
    $sessionToken = SessionManager::get('session_token');
    
    // Log logout activity
    logActivity($userId, 'logout', 'User logged out successfully');
    logSecurityEvent($userId, 'logout', 'User logged out from IP: ' . getClientIP());
    
    // Invalidate session token in database
    if ($sessionToken) {
        try {
            $db = getDB();
            $stmt = $db->prepare("UPDATE user_sessions SET is_active = 0 WHERE token = ?");
            $stmt->execute([$sessionToken]);
        } catch (Exception $e) {
            // Skip if table doesn't exist
        }
    }
}

// Clear remember me cookie
setcookie('remember_token', '', time() - 3600, '/', '', false, true);

// Clear all session data
$_SESSION = array();

// Destroy session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Redirect to login page with success message
header('Location: login.php?logout=success');
exit();
?>