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

$electionId = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;
$wardId = isset($_GET['ward_id']) ? (int)$_GET['ward_id'] : 0;

try {
    $conn = getDBConnection();
    
    $query = "
        SELECT pu.*, w.name as ward_name, l.name as lga_name, s.name as state_name,
               (SELECT COUNT(*) FROM agent_assignments WHERE pu_id = pu.id AND status = 'active') as assigned_agents
        FROM polling_units pu
        LEFT JOIN wards w ON pu.ward_id = w.id
        LEFT JOIN lgas l ON w.lga_id = l.id
        LEFT JOIN states s ON l.state_id = s.id
        WHERE pu.is_active = 1
    ";
    
    $params = [];
    $types = "";
    
    if ($electionId > 0) {
        $query .= " AND pu.id IN (
            SELECT pu_id FROM election_polling_units WHERE election_id = ?
        )";
        $params[] = $electionId;
        $types .= "i";
    }
    
    if ($wardId > 0) {
        $query .= " AND pu.ward_id = ?";
        $params[] = $wardId;
        $types .= "i";
    }
    
    // If user is PU agent, get only assigned PU
    if ($userData['role_level'] === 'pu_agent') {
        $query .= " AND pu.id IN (
            SELECT pu_id FROM agent_assignments WHERE user_id = ? AND status = 'active'
        )";
        $params[] = $userId;
        $types .= "i";
    }
    
    $query .= " ORDER BY pu.name";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $pollingUnits = [];
    while ($row = $result->fetch_assoc()) {
        $pollingUnits[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    sendSuccess('Polling units retrieved successfully', [
        'polling_units' => $pollingUnits,
        'total' => count($pollingUnits)
    ]);
    
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), HTTP_INTERNAL_ERROR);
}