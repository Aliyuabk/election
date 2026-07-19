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

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';

try {
    $conn = getDBConnection();
    
    $query = "
        SELECT i.*, 
               u.first_name as reporter_first_name, u.last_name as reporter_last_name,
               pu.name as pu_name, w.name as ward_name, l.name as lga_name, s.name as state_name
        FROM incidents i
        LEFT JOIN users u ON i.reporter_id = u.id
        LEFT JOIN polling_units pu ON i.pu_id = pu.id
        LEFT JOIN wards w ON i.ward_id = w.id
        LEFT JOIN lgas l ON i.lga_id = l.id
        LEFT JOIN states s ON i.state_id = s.id
        WHERE i.tenant_id = ?
    ";
    
    $params = [$userData['tenant_id']];
    $types = "i";
    
    if (!empty($status)) {
        $query .= " AND i.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    if (!empty($type)) {
        $query .= " AND i.incident_type = ?";
        $params[] = $type;
        $types .= "s";
    }
    
    // If user is not admin, show only their reports
    if (!in_array($userData['role_level'], ['super_admin', 'national', 'state', 'lga'])) {
        $query .= " AND i.reporter_id = ?";
        $params[] = $userId;
        $types .= "i";
    }
    
    $query .= " ORDER BY i.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $incidents = [];
    while ($row = $result->fetch_assoc()) {
        $incidents[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    sendSuccess('Incidents retrieved successfully', [
        'incidents' => $incidents,
        'total' => count($incidents),
        'limit' => $limit,
        'offset' => $offset
    ]);
    
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), HTTP_INTERNAL_ERROR);
}