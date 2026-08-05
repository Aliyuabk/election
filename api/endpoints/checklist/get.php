<?php
/**
 * Get Checklist
 * GET /api/checklist/get?election_id={election_id}
 */

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/Response.php';

$auth = new Auth();
$user = $auth->authenticate();

if (!$user) {
    Response::unauthorized();
}

$electionId = isset($_GET['election_id']) ? intval($_GET['election_id']) : 0;

if ($electionId <= 0) {
    Response::error('Election ID is required', 400);
}

$db = Database::getInstance();

// Get checklist
$stmt = $db->prepare("
    SELECT 
        id, user_id, election_id, pu_id,
        materials_arrived, poll_opened, accreditation_started,
        voting_started, counting_started, poll_closed,
        status, submitted_at, created_at, updated_at
    FROM election_checklists
    WHERE user_id = ? AND election_id = ?
    ORDER BY created_at DESC
    LIMIT 1
");

$stmt->bind_param("ii", $user['id'], $electionId);
$stmt->execute();
$result = $stmt->get_result();
$checklist = $result->fetch_assoc();
$stmt->close();

// If no checklist exists, create one
if (!$checklist) {
    $db->query("
        INSERT INTO election_checklists (user_id, election_id, status, created_at)
        VALUES ({$user['id']}, $electionId, 'draft', NOW())
    ");
    
    $checklistId = $db->lastInsertId();
    
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
}

Response::success($checklist);