<?php
/**
 * Logout Endpoint
 * POST /api/auth/logout
 */

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/response.php';

$auth = new Auth();
$user = $auth->authenticate();

$db = Database::getInstance();
$db->query("
    INSERT INTO activity_logs (user_id, activity_type, description, created_at)
    VALUES ({$user['id']}, 'logout', 'User logged out from mobile app', NOW())
");

Response::success(null, 'Logged out successfully');