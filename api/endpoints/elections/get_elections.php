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
$userData = getUserData($userId);

if (!$userData) {
    sendError('User not found', HTTP_NOT_FOUND);
}

try {
    $conn = getDBConnection();
    
    // Get elections for user's tenant
    $query = "
        SELECT e.*, 
               (SELECT COUNT(*) FROM elections WHERE tenant_id = e.tenant_id AND status = 'active') as active_elections
        FROM elections e
        WHERE e.tenant_id = ? AND e.deleted_at IS NULL
        ORDER BY e.election_date DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userData['tenant_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $elections = [];
    while ($row = $result->fetch_assoc()) {
        $elections[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    sendSuccess('Elections retrieved successfully', [
        'elections' => $elections,
        'total' => count($elections)
    ]);
    
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), HTTP_INTERNAL_ERROR);
}