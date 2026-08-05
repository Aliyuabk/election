<?php
/**
 * Update Checklist
 * POST /api/checklist/update
 */

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/Response.php';
require_once dirname(__DIR__, 2) . '/includes/Validator.php';

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

$errors = Validator::required($data, ['checklist_id']);
if (!empty($errors)) {
    Response::validationError($errors);
}

$checklistId = intval($data['checklist_id']);
$fields = [
    'materials_arrived', 'poll_opened', 'accreditation_started',
    'voting_started', 'counting_started', 'poll_closed'
];

$db = Database::getInstance();

// Verify checklist belongs to user
$stmt = $db->prepare("
    SELECT id FROM election_checklists 
    WHERE id = ? AND user_id = ? AND status != 'submitted'
");
$stmt->bind_param("ii", $checklistId, $user['id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    Response::error('Checklist not found or already submitted', 404);
}
$stmt->close();

// Build update query
$updates = [];
$params = [];
$types = "";

foreach ($fields as $field) {
    if (isset($data[$field])) {
        $value = intval($data[$field]);
        $updates[] = "$field = ?";
        $params[] = $value;
        $types .= "i";
    }
}

// Handle status
$status = isset($data['status']) ? Validator::sanitize($data['status']) : null;
if ($status && in_array($status, ['draft', 'submitted', 'completed'])) {
    $updates[] = "status = ?";
    $params[] = $status;
    $types .= "s";
    
    if ($status === 'submitted') {
        $updates[] = "submitted_at = NOW()";
    }
}

if (empty($updates)) {
    Response::error('No fields to update', 400);
}

$params[] = $checklistId;
$types .= "i";

$sql = "UPDATE election_checklists SET " . implode(", ", $updates) . " WHERE id = ?";
$stmt = $db->prepare($sql);

if (!$stmt) {
    Response::error('Database error: ' . $db->getConnection()->error, 500);
}

$stmt->bind_param($types, ...$params);

if (!$stmt->execute()) {
    Response::error('Failed to update checklist', 500);
}

$stmt->close();

// Get updated checklist
$stmt = $db->prepare("
    SELECT 
        id, user_id, election_id, pu_id,
        materials_arrived, poll_opened, accreditation_started,
        voting_started, counting_started, poll_closed,
        status, submitted_at, created_at, updated_at
    FROM election_checklists
    WHERE id = ?
");
$stmt->bind_param("i", $checklistId);
$stmt->execute();
$result = $stmt->get_result();
$checklist = $result->fetch_assoc();
$stmt->close();

Response::success($checklist, 'Checklist updated successfully');