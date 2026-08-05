<?php
/**
 * Logout Endpoint
 * POST /api/auth/logout
 */

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/Response.php';

$auth = new Auth();
$user = $auth->authenticate();

$db = Database::getInstance();

// Log activity
$db->query("
    INSERT INTO activity_logs (user_id, activity_type, description, created_at)
    VALUES ({$user['id']}, 'logout', 'User logged out from mobile app', NOW())
");

// Invalidate session (optional)
// $db->query("DELETE FROM user_sessions WHERE user_id = {$user['id']} AND is_active = 1");

Response::success(null, 'Logged out successfully');