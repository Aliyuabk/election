<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

// Only POST method allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', HTTP_METHOD_NOT_ALLOWED);
}

$userId = validateToken();
$userData = getUserData($userId);

if (!$userData) {
    sendError('User not found', HTTP_NOT_FOUND);
}

$input = json_decode(file_get_contents('php://input'), true);
validateRequired($input, ['title', 'description', 'incident_type']);

$title = sanitizeInput($input['title']);
$description = sanitizeInput($input['description']);
$incidentType = sanitizeInput($input['incident_type']);
$severity = isset($input['severity']) ? sanitizeInput($input['severity']) : 'medium';
$isPanic = isset($input['is_panic']) ? (int)$input['is_panic'] : 0;
$electionId = isset($input['election_id']) ? (int)$input['election_id'] : null;
$puId = isset($input['pu_id']) ? (int)$input['pu_id'] : null;
$wardId = isset($input['ward_id']) ? (int)$input['ward_id'] : null;
$lgaId = isset($input['lga_id']) ? (int)$input['lga_id'] : null;
$stateId = isset($input['state_id']) ? (int)$input['state_id'] : null;
$gpsLat = isset($input['gps_lat']) ? (float)$input['gps_lat'] : null;
$gpsLng = isset($input['gps_lng']) ? (float)$input['gps_lng'] : null;
$deviceId = isset($input['device_id']) ? sanitizeInput($input['device_id']) : null;

try {
    $conn = getDBConnection();
    
    // Insert incident
    $stmt = $conn->prepare("
        INSERT INTO incidents 
        (tenant_id, election_id, reporter_id, pu_id, ward_id, lga_id, state_id,
         incident_type, severity, is_panic, title, description,
         gps_lat, gps_lng, device_id, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'reported', NOW(), NOW())
    ");
    
    $tenantId = $userData['tenant_id'];
    
    $stmt->bind_param(
        "iiiiiiissississ", 
        $tenantId, $electionId, $userId, $puId, $wardId, $lgaId, $stateId,
        $incidentType, $severity, $isPanic, $title, $description,
        $gpsLat, $gpsLng, $deviceId
    );
    
    $stmt->execute();
    $incidentId = $stmt->insert_id;
    $stmt->close();
    
    // Log activity
    $logStmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, tenant_id, activity_type, description, entity_type, entity_id, created_at)
        VALUES (?, ?, 'incident_reported', ?, 'incident', ?, NOW())
    ");
    $description = "Reported incident: $title (ID: $incidentId)";
    $logStmt->bind_param("iis i", $userId, $tenantId, $description, $incidentId);
    $logStmt->execute();
    $logStmt->close();
    
    // Get created incident
    $getStmt = $conn->prepare("
        SELECT * FROM incidents WHERE id = ?
    ");
    $getStmt->bind_param("i", $incidentId);
    $getStmt->execute();
    $result = $getStmt->get_result();
    $incident = $result->fetch_assoc();
    $getStmt->close();
    
    $conn->close();
    
    sendSuccess('Incident reported successfully', $incident, HTTP_CREATED);
    
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), HTTP_INTERNAL_ERROR);
}