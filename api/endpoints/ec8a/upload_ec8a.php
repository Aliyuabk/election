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

// Validate required fields
if (!isset($_POST['election_id']) || !isset($_POST['pu_id'])) {
    sendError('Election ID and Polling Unit ID are required', HTTP_BAD_REQUEST);
}

$electionId = (int)$_POST['election_id'];
$puId = (int)$_POST['pu_id'];
$wardId = isset($_POST['ward_id']) ? (int)$_POST['ward_id'] : null;
$lgaId = isset($_POST['lga_id']) ? (int)$_POST['lga_id'] : null;
$stateId = isset($_POST['state_id']) ? (int)$_POST['state_id'] : null;
$partyVotes = isset($_POST['party_votes']) ? json_decode($_POST['party_votes'], true) : [];
$registeredVoters = isset($_POST['registered_voters']) ? (int)$_POST['registered_voters'] : 0;
$accreditedVoters = isset($_POST['accredited_voters']) ? (int)$_POST['accredited_voters'] : 0;
$gpsLat = isset($_POST['gps_lat']) ? (float)$_POST['gps_lat'] : null;
$gpsLng = isset($_POST['gps_lng']) ? (float)$_POST['gps_lng'] : null;
$deviceId = isset($_POST['device_id']) ? sanitizeInput($_POST['device_id']) : null;

// Validate file
if (!isset($_FILES['ec8a_photo'])) {
    sendError('EC8A photo is required', HTTP_BAD_REQUEST);
}

$file = $_FILES['ec8a_photo'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
$maxSize = 5 * 1024 * 1024; // 5MB

if ($file['error'] !== UPLOAD_ERR_OK) {
    sendError('File upload error: ' . $file['error'], HTTP_BAD_REQUEST);
}

if (!in_array($file['type'], $allowedTypes)) {
    sendError('File type not allowed. Please upload JPEG, PNG, or GIF', HTTP_BAD_REQUEST);
}

if ($file['size'] > $maxSize) {
    sendError('File size exceeds maximum allowed (5MB)', HTTP_BAD_REQUEST);
}

try {
    $conn = getDBConnection();
    
    // Get assignment ID
    $assignmentStmt = $conn->prepare("
        SELECT id FROM agent_assignments 
        WHERE user_id = ? AND election_id = ? AND pu_id = ? AND status = 'active'
        ORDER BY id DESC LIMIT 1
    ");
    $assignmentStmt->bind_param("iii", $userId, $electionId, $puId);
    $assignmentStmt->execute();
    $assignmentResult = $assignmentStmt->get_result();
    $assignment = $assignmentResult->fetch_assoc();
    $assignmentStmt->close();
    
    if (!$assignment) {
        sendError('No active assignment found for this polling unit', HTTP_FORBIDDEN);
    }
    
    $assignmentId = $assignment['id'];
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'ec8a_' . uniqid() . '_' . date('Ymd_His') . '.' . $extension;
    $filePath = EC8A_PATH . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        sendError('Failed to save file', HTTP_INTERNAL_ERROR);
    }
    
    // Calculate total votes
    $totalVotes = array_sum($partyVotes);
    $validVotes = $totalVotes;
    
    // Insert EC8A result
    $stmt = $conn->prepare("
        INSERT INTO results_ec8a 
        (tenant_id, election_id, pu_id, ward_id, lga_id, state_id, 
         agent_id, assignment_id, pu_code, pu_name, 
         registered_voters, accredited_voters, valid_votes, total_votes_cast,
         party_votes_json, photo_url, gps_lat, gps_lng, device_id, status, created_at)
        SELECT ?, ?, ?, ?, ?, ?, 
               ?, ?, pu.code, pu.name,
               ?, ?, ?, ?,
               ?, ?, ?, ?, ?, 'pending', NOW()
        FROM polling_units pu
        WHERE pu.id = ?
    ");
    
    $tenantId = $userData['tenant_id'];
    $photoUrl = '/uploads/ec8a/' . $filename;
    $partyVotesJson = json_encode($partyVotes);
    
    $stmt->bind_param(
        "iiiiiiiisiiiiissisi", 
        $tenantId, $electionId, $puId, $wardId, $lgaId, $stateId,
        $userId, $assignmentId,
        $registeredVoters, $accreditedVoters, $validVotes, $totalVotes,
        $partyVotesJson, $photoUrl, $gpsLat, $gpsLng, $deviceId, $puId
    );
    
    $stmt->execute();
    $resultId = $stmt->insert_id;
    $stmt->close();
    
    // Log activity
    $logStmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, tenant_id, activity_type, description, entity_type, entity_id, created_at)
        VALUES (?, ?, 'ec8a_uploaded', ?, 'results_ec8a', ?, NOW())
    ");
    $description = "Uploaded EC8A form for PU ID: $puId";
    $logStmt->bind_param("iis i", $userId, $tenantId, $description, $resultId);
    $logStmt->execute();
    $logStmt->close();
    
    $conn->close();
    
    sendSuccess('EC8A uploaded successfully', [
        'id' => $resultId,
        'photo_url' => $photoUrl,
        'status' => 'pending'
    ]);
    
} catch (Exception $e) {
    // Delete uploaded file if error
    if (isset($filePath) && file_exists($filePath)) {
        unlink($filePath);
    }
    sendError('Server error: ' . $e->getMessage(), HTTP_INTERNAL_ERROR);
}