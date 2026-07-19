<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

// Only GET method allowed
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', HTTP_METHOD_NOT_ALLOWED);
}

$userId = validateToken();

try {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        SELECT u.*, r.name as role_name, r.level as role_level, 
               t.name as tenant_name, t.logo_url as tenant_logo,
               (SELECT COUNT(*) FROM agent_assignments WHERE user_id = u.id AND status = 'active') as active_assignments,
               (SELECT COUNT(*) FROM incidents WHERE reporter_id = u.id AND status != 'resolved') as pending_incidents
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        LEFT JOIN tenants t ON u.tenant_id = t.id
        WHERE u.id = ? AND u.status = 'active'
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        unset($user['password_hash']);
        unset($user['remember_token']);
        unset($user['two_factor_secret']);
        
        $stmt->close();
        $conn->close();
        
        sendSuccess('Profile retrieved successfully', $user);
    } else {
        sendError('User not found', HTTP_NOT_FOUND);
    }
    
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), HTTP_INTERNAL_ERROR);
}