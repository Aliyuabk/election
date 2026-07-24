<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$userId = validateToken();
$userData = getUserData($userId);

if (!$userData) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$roleId = isset($_GET['role_id']) ? (int)$_GET['role_id'] : 9;
$tenantId = $userData['tenant_id'];
$wardId = $userData['ward_id'] ?? 0;

try {
    $conn = getDBConnection();
    
    // Get contacts with role
    $stmt = $conn->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.email,
            u.phone,
            u.photograph_url,
            u.role_id,
            u.last_login_at,
            u.pu_id,
            r.name as role_name,
            r.level as role_level,
            pu.name as pu_name,
            pu.code as pu_code,
            (SELECT content FROM chat_messages 
             WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id)
             ORDER BY created_at DESC LIMIT 1) as last_message,
            (SELECT created_at FROM chat_messages 
             WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id)
             ORDER BY created_at DESC LIMIT 1) as last_message_time,
            (SELECT COUNT(*) FROM chat_messages 
             WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread_count,
            (SELECT 1 FROM user_sessions WHERE user_id = u.id AND is_active = 1 AND expires_at > NOW() LIMIT 1) as is_online
        FROM users u
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.tenant_id = ? 
        AND u.ward_id = ?
        AND u.deleted_at IS NULL
        AND u.status = 'active'
        AND u.id != ?
        AND u.role_id = ?
        ORDER BY unread_count DESC, last_message_time DESC NULLS LAST
    ");
    $stmt->bind_param("iiiiiiiiii", 
        $userId, $userId, $userId, $userId, $userId,
        $tenantId, $wardId, $userId, $roleId
    );
    $stmt->execute();
    $result = $stmt->get_result();
    
    $contacts = [];
    while ($row = $result->fetch_assoc()) {
        $contacts[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'contacts' => $contacts,
        'total' => count($contacts)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}