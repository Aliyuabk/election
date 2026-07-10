<?php
// ============================================================
// AJAX - SEARCH USERS
// ============================================================
require_once '../../../config/config.php';
require_once '../../../includes/session.php';
require_once '../../../includes/functions.php';

// Start session and check login
SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    echo json_encode([]);
    exit;
}

// Get search query
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

$db = getDB();
$tenant_id = SessionManager::get('tenant_id');

try {
    $search_term = '%' . $query . '%';
    
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.user_code,
            u.first_name,
            u.last_name,
            CONCAT(u.first_name, ' ', u.last_name) as full_name,
            u.email,
            u.phone,
            r.name as role_name,
            r.level as role_level,
            'user' as type,
            'fa-user' as icon,
            'users-view.php?id=' as url
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.tenant_id = ? 
        AND u.deleted_at IS NULL
        AND (
            u.first_name LIKE ? 
            OR u.last_name LIKE ? 
            OR u.email LIKE ? 
            OR u.phone LIKE ? 
            OR u.user_code LIKE ?
            OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?
        )
        ORDER BY u.first_name ASC
        LIMIT 15
    ");
    $stmt->execute([
        $tenant_id,
        $search_term,
        $search_term,
        $search_term,
        $search_term,
        $search_term,
        $search_term
    ]);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add URL to each result
    foreach ($results as &$result) {
        $result['url'] = 'users-view.php?id=' . $result['id'];
        $result['label'] = $result['full_name'] . ' (' . $result['email'] . ')';
    }
    
    echo json_encode($results);
} catch (Exception $e) {
    error_log("AJAX search error: " . $e->getMessage());
    echo json_encode([]);
}
exit;
?>