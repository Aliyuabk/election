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

$input = json_decode(file_get_contents('php://input'), true);
validateRequired($input, ['election_id', 'pu_id']);

$electionId = (int)$input['election_id'];
$puId = (int)$input['pu_id'];
$materialsArrived = isset($input['materials_arrived']) ? (int)$input['materials_arrived'] : 0;
$pollOpened = isset($input['poll_opened']) ? (int)$input['poll_opened'] : 0;
$accreditationStarted = isset($input['accreditation_started']) ? (int)$input['accreditation_started'] : 0;
$votingStarted = isset($input['voting_started']) ? (int)$input['voting_started'] : 0;
$countingStarted = isset($input['counting_started']) ? (int)$input['counting_started'] : 0;
$pollClosed = isset($input['poll_closed']) ? (int)$input['poll_closed'] : 0;
$status = isset($input['status']) ? trim($input['status']) : 'draft';

try {
    $conn = getDBConnection();
    
    // Check if checklist exists
    $checkStmt = $conn->prepare("
        SELECT id FROM election_checklists 
        WHERE user_id = ? AND election_id = ? AND pu_id = ?
    ");
    $checkStmt->bind_param("iii", $userId, $electionId, $puId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $exists = $result->num_rows > 0;
    $checkStmt->close();
    
    if ($exists) {
        // Update existing checklist
        $stmt = $conn->prepare("
            UPDATE election_checklists 
            SET materials_arrived = ?, poll_opened = ?, accreditation_started = ?, 
                voting_started = ?, counting_started = ?, poll_closed = ?,
                status = ?, updated_at = NOW()
            WHERE user_id = ? AND election_id = ? AND pu_id = ?
        ");
        $stmt->bind_param(
            "iiiiissiii", 
            $materialsArrived, $pollOpened, $accreditationStarted,
            $votingStarted, $countingStarted, $pollClosed,
            $status, $userId, $electionId, $puId
        );
    } else {
        // Insert new checklist
        $stmt = $conn->prepare("
            INSERT INTO election_checklists 
            (user_id, election_id, pu_id, materials_arrived, poll_opened, 
             accreditation_started, voting_started, counting_started, poll_closed, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->bind_param(
            "iiiiiiiiis", 
            $userId, $electionId, $puId,
            $materialsArrived, $pollOpened, $accreditationStarted,
            $votingStarted, $countingStarted, $pollClosed, $status
        );
    }
    
    $stmt->execute();
    $stmt->close();
    $conn->close();
    
    sendSuccess('Checklist updated successfully');
    
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), HTTP_INTERNAL_ERROR);
}