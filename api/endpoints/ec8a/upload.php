<?php
/**
 * Upload EC8A Result Sheet
 * POST /api/ec8a/upload
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

// Check if file was uploaded
if (!isset($_FILES['ec8a_image']) || $_FILES['ec8a_image']['error'] !== UPLOAD_ERR_OK) {
    Response::error('EC8A image is required', 400);
}

$file = $_FILES['ec8a_image'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
$maxSize = 5 * 1024 * 1024; // 5MB

if (!in_array($file['type'], $allowedTypes)) {
    Response::error('Invalid file type. Only JPG and PNG are allowed', 422);
}

if ($file['size'] > $maxSize) {
    Response::error('File too large. Maximum size is 5MB', 422);
}

// Get other data
$electionId = isset($_POST['election_id']) ? intval($_POST['election_id']) : 0;
$puId = isset($_POST['pu_id']) ? intval($_POST['pu_id']) : 0;
$validVotes = isset($_POST['valid_votes']) ? intval($_POST['valid_votes']) : 0;
$rejectedVotes = isset($_POST['rejected_votes']) ? intval($_POST['rejected_votes']) : 0;
$totalVotes = isset($_POST['total_votes']) ? intval($_POST['total_votes']) : 0;
$partyVotes = isset($_POST['party_votes']) ? json_decode($_POST['party_votes'], true) : [];
$gpsLat = isset($_POST['gps_lat']) ? floatval($_POST['gps_lat']) : null;
$gpsLng = isset($_POST['gps_lng']) ? floatval($_POST['gps_lng']) : null;
$deviceId = isset($_POST['device_id']) ? Validator::sanitize($_POST['device_id']) : null;

if ($electionId <= 0 || $puId <= 0) {
    Response::error('Election ID and Polling Unit ID are required', 400);
}

$db = Database::getInstance();

// Verify user has access
$stmt = $db->prepare("
    SELECT pu_id, lga_id, state_id, ward_id, tenant_id 
    FROM agent_assignments 
    WHERE user_id = ? AND pu_id = ? AND status = 'active'
");
$stmt->bind_param("ii", $user['id'], $puId);
$stmt->execute();
$result = $stmt->get_result();
$assignment = $result->fetch_assoc();
$stmt->close();

if (!$assignment) {
    Response::error('Access denied to this polling unit', 403);
}

// Upload directory
$uploadDir = '/path/to/uploads/ec8a/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'ec8a_' . $puId . '_' . date('Ymd_His') . '.' . $extension;
$filePath = $uploadDir . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    Response::error('Failed to upload file', 500);
}

// Calculate file hash
$fileHash = hash_file('sha256', $filePath);

// Get PU details
$puResult = $db->query("
    SELECT code, name, registered_voters FROM polling_units WHERE id = $puId
");
$pu = $puResult->fetch_assoc();

// Save to database
$partyVotesJson = json_encode($partyVotes);
$photoUrl = '/uploads/ec8a/' . $filename;

$stmt = $db->prepare("
    INSERT INTO results_ec8a (
        tenant_id, election_id, pu_id, ward_id, lga_id, state_id,
        agent_id, assignment_id, pu_code, pu_name, registered_voters,
        party_votes_json, valid_votes, rejected_votes, total_votes_cast,
        photo_url, photo_sha256, gps_lat, gps_lng, device_id, status, created_at
    ) SELECT 
        ?, ?, ?, ?, ?, ?,
        ?, aa.id, pu.code, pu.name, pu.registered_voters,
        ?, ?, ?, ?,
        ?, ?, ?, ?, ?, 'pending', NOW()
    FROM agent_assignments aa
    JOIN polling_units pu ON pu.id = aa.pu_id
    WHERE aa.id = (
        SELECT id FROM agent_assignments 
        WHERE user_id = ? AND pu_id = ? AND status = 'active'
        LIMIT 1
    )
");

$tenantId = $assignment['tenant_id'];
$wardId = $assignment['ward_id'];
$lgaId = $assignment['lga_id'];
$stateId = $assignment['state_id'];

$stmt->bind_param(
    "iiiiiiisiiidissii",
    $tenantId, $electionId, $puId, $wardId, $lgaId, $stateId,
    $user['id'], $partyVotesJson, $validVotes, $rejectedVotes, $totalVotes,
    $photoUrl, $fileHash, $gpsLat, $gpsLng, $deviceId,
    $user['id'], $puId
);

if (!$stmt->execute()) {
    // Delete uploaded file on error
    unlink($filePath);
    Response::error('Failed to save EC8A record: ' . $stmt->error, 500);
}

$recordId = $db->lastInsertId();
$stmt->close();

// Log activity
$db->query("
    INSERT INTO activity_logs (user_id, activity_type, description, device_id, created_at)
    VALUES ({$user['id']}, 'ec8a_upload', 
            'Uploaded EC8A for PU: $puId',
            '$deviceId', NOW())
");

Response::success([
    'record_id' => $recordId,
    'photo_url' => $photoUrl,
    'pu_id' => $puId,
    'status' => 'pending'
], 'EC8A uploaded successfully');
?>