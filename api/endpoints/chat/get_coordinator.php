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

$tenantId = $userData['tenant_id'];
$wardId = $userData['ward_id'] ?? 0;

// If ward_id is not set, try to get it from the user's record
if (empty($wardId)) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT ward_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        if ($user && !empty($user['ward_id'])) {
            $wardId = $user['ward_id'];
        }
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        // Ignore
    }
}

try {
    $conn = getDBConnection();
    
    // Find ward coordinator (role_level = 'ward')
    $stmt = $conn->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.email,
            u.phone,
            u.photograph_url,
            u.role_id,
            u.last_login_at,
            r.name as role_name,
            r.level as role_level,
            (SELECT COUNT(*) FROM chat_messages 
             WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread_count,
            (SELECT 1 FROM user_sessions WHERE user_id = u.id AND is_active = 1 AND expires_at > NOW() LIMIT 1) as is_online
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.tenant_id = ? 
        AND u.ward_id = ?
        AND u.deleted_at IS NULL
        AND u.status = 'active'
        AND u.id != ?
        AND r.level = 'ward'
        LIMIT 1
    ");
    $stmt->bind_param("iiii", $tenantId, $wardId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($coordinator = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'coordinator' => $coordinator
        ]);
    } else {
        // If no ward coordinator found, try to find LGA coordinator
        $lgaId = $userData['lga_id'] ?? 0;
        if ($lgaId > 0) {
            $stmt = $conn->prepare("
                SELECT 
                    u.id,
                    u.full_name,
                    u.email,
                    u.phone,
                    u.photograph_url,
                    u.role_id,
                    u.last_login_at,
                    r.name as role_name,
                    r.level as role_level,
                    (SELECT COUNT(*) FROM chat_messages 
                     WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread_count,
                    (SELECT 1 FROM user_sessions WHERE user_id = u.id AND is_active = 1 AND expires_at > NOW() LIMIT 1) as is_online
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.tenant_id = ? 
                AND u.lga_id = ?
                AND u.deleted_at IS NULL
                AND u.status = 'active'
                AND u.id != ?
                AND r.level = 'lga'
                LIMIT 1
            ");
            $stmt->bind_param("iiii", $tenantId, $lgaId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($coordinator = $result->fetch_assoc()) {
                echo json_encode([
                    'success' => true,
                    'coordinator' => $coordinator
                ]);
                exit;
            }
        }
        
        echo json_encode([
            'success' => false,
            'message' => 'No coordinator found for this ward'
        ]);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}