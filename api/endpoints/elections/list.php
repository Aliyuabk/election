<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';

// Authenticate the user
$user = AuthMiddleware::authenticate();

try {
    $db = Database::getInstance()->getConnection();
    
    $tenant_id = $user['tenant_id'];
    $role_level = $user['role_level'];
    
    // Build query based on user role
    $query = "
        SELECT e.*, 
               COUNT(DISTINCT c.id) as candidate_count,
               COUNT(DISTINCT aa.id) as agent_count
        FROM elections e
        LEFT JOIN candidates c ON e.id = c.election_id AND c.is_active = 1
        LEFT JOIN agent_assignments aa ON e.id = aa.election_id AND aa.status = 'active'
    ";
    
    $params = [];
    $types = "";
    
    // Filter by tenant
    if ($tenant_id) {
        $query .= " WHERE e.tenant_id = ? AND e.deleted_at IS NULL";
        $params[] = $tenant_id;
        $types .= "i";
    } else {
        $query .= " WHERE e.deleted_at IS NULL";
    }
    
    // Add role-based filtering
    if (in_array($role_level, ['lga', 'ward', 'pu_agent'])) {
        $query .= " AND e.status IN ('active', 'upcoming')";
    }
    
    $query .= " GROUP BY e.id ORDER BY e.election_date DESC, e.created_at DESC";
    
    $stmt = $db->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $elections = [];
    while ($row = $result->fetch_assoc()) {
        // Decode JSON fields
        $row['states_json'] = json_decode($row['states_json'] ?? '[]', true);
        $row['lgas_json'] = json_decode($row['lgas_json'] ?? '[]', true);
        $row['wards_json'] = json_decode($row['wards_json'] ?? '[]', true);
        $row['pus_json'] = json_decode($row['pus_json'] ?? '[]', true);
        
        $elections[] = $row;
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $elections,
        'count' => count($elections)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch elections: ' . $e->getMessage()]);
}
?>