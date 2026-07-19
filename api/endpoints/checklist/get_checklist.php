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

$electionId = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;
$puId = isset($_GET['pu_id']) ? (int)$_GET['pu_id'] : 0;

if ($electionId <= 0 || $puId <= 0) {
    sendError('Election ID and Polling Unit ID are required', HTTP_BAD_REQUEST);
}

try {
    $conn = getDBConnection();
    
    // Get checklist
    $stmt = $conn->prepare("
        SELECT * FROM election_checklists 
        WHERE user_id = ? AND election_id = ? AND pu_id = ?
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->bind_param("iii", $userId, $electionId, $puId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $checklist = $result->fetch_assoc();
    
    if (!$checklist) {
        // Return default empty checklist
        $checklist = [
            'id' => 0,
            'user_id' => $userId,
            'election_id' => $electionId,
            'pu_id' => $puId,
            'materials_arrived' => 0,
            'poll_opened' => 0,
            'accreditation_started' => 0,
            'voting_started' => 0,
            'counting_started' => 0,
            'poll_closed' => 0,
            'status' => 'draft',
            'submitted_at' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    sendSuccess('Checklist retrieved successfully', $checklist);
    
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), HTTP_INTERNAL_ERROR);
}