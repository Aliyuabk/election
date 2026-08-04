<?php
/**
 * Update Incident (Edit/Delete Draft)
 * POST /api/incidents/update
 */

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/response.php';
require_once dirname(__DIR__, 2) . '/includes/validation.php';

$auth = new Auth();
$user = $auth->authenticate();

if (!$user) {
    Response::unauthorized();
}

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

if (!$data) {
    Response::error('Invalid request data', 400);
}

$errors = Validator::required($data, ['incident_id']);
if (!empty($errors)) {
    Response::validationError($errors);
}

$incidentId = intval($data['incident_id']);
$action = isset($data['action']) ? Validator::sanitize($data['action']) : 'update';

$db = Database::getInstance();

// Verify incident belongs to user and is not resolved
$stmt = $db->prepare("
    SELECT id, status FROM incidents 
    WHERE id = ? AND reporter_id = ?
");
$stmt->bind_param("ii", $incidentId, $user['id']);
$stmt->execute();
$result = $stmt->get_result();
$incident = $result->fetch_assoc();
$stmt->close();

if (!$incident) {
    Response::error('Incident not found or access denied', 404);
}

if ($action === 'delete' && $incident['status'] === 'reported') {
    // Can't delete reported incidents
    Response::error('Cannot delete a reported incident', 400);
}

if ($action === 'delete') {
    $stmt = $db->prepare("DELETE FROM incidents WHERE id = ?");
    $stmt->bind_param("i", $incidentId);
    $stmt->execute();
    $stmt->close();
    
    Response::success(null, 'Incident deleted successfully');
}

// Update incident
$updates = [];
$params = [];
$types = "";

$fields = ['title', 'description', 'incident_type', 'severity'];
foreach ($fields as $field) {
    if (isset($data[$field])) {
        $value = Validator::sanitize($data[$field]);
        $updates[] = "$field = ?";
        $params[] = $value;
        $types .= "s";
    }
}

if (isset($data['photo_urls'])) {
    $value = json_encode($data['photo_urls']);
    $updates[] = "photo_urls_json = ?";
    $params[] = $value;
    $types .= "s";
}

if (empty($updates)) {
    Response::error('No fields to update', 400);
}

$params[] = $incidentId;
$types .= "i";

$sql = "UPDATE incidents SET " . implode(", ", $updates) . " WHERE id = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);

if (!$stmt->execute()) {
    Response::error('Failed to update incident', 500);
}

$stmt->close();

Response::success(null, 'Incident updated successfully');
?>