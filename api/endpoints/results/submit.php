<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';

$user = AuthMiddleware::authenticate();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required = ['election_id', 'pu_id', 'ward_id', 'lga_id', 'state_id', 'party_votes_json'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "$field is required"]);
        exit;
    }
}

try {
    $db = Database::getInstance()->getConnection();
    $tenant_id = $user['tenant_id'];
    $user_id = $user['user_id'];
    
    // Start transaction
    $db->begin_transaction();
    
    // Check if result already exists for this PU and election
    $checkStmt = $db->prepare("
        SELECT id FROM results_ec8a 
        WHERE election_id = ? AND pu_id = ? AND tenant_id = ?
    ");
    $checkStmt->bind_param("iii", $input['election_id'], $input['pu_id'], $tenant_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Result already submitted for this polling unit']);
        $checkStmt->close();
        $db->rollback();
        exit;
    }
    $checkStmt->close();
    
    // Get agent assignment for this PU
    $assignmentStmt = $db->prepare("
        SELECT id FROM agent_assignments 
        WHERE user_id = ? AND pu_id = ? AND election_id = ? AND status = 'active'
    ");
    $assignmentStmt->bind_param("iii", $user_id, $input['pu_id'], $input['election_id']);
    $assignmentStmt->execute();
    $assignmentResult = $assignmentStmt->get_result();
    
    if ($assignmentResult->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You are not assigned to this polling unit']);
        $assignmentStmt->close();
        $db->rollback();
        exit;
    }
    $assignment = $assignmentResult->fetch_assoc();
    $assignmentStmt->close();
    
    // Get PU details
    $puStmt = $db->prepare("
        SELECT code, name, registered_voters FROM polling_units WHERE id = ?
    ");
    $puStmt->bind_param("i", $input['pu_id']);
    $puStmt->execute();
    $pu = $puStmt->get_result()->fetch_assoc();
    $puStmt->close();
    
    // Insert EC8A result
    $stmt = $db->prepare("
        INSERT INTO results_ec8a (
            tenant_id, election_id, pu_id, ward_id, lga_id, state_id,
            agent_id, assignment_id, pu_code, pu_name, registered_voters,
            accredited_voters, ballot_papers_issued, unused_ballots,
            spoiled_ballots, rejected_votes, valid_votes, total_votes_cast,
            party_votes_json, gps_lat, gps_lng, device_id,
            remarks, status, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, 'pending', NOW()
        )
    ");
    
    $party_votes_json = $input['party_votes_json'];
    if (is_array($party_votes_json)) {
        $party_votes_json = json_encode($party_votes_json);
    }
    
    $device_id = $input['device_id'] ?? null;
    $gps_lat = $input['gps_lat'] ?? null;
    $gps_lng = $input['gps_lng'] ?? null;
    $remarks = $input['remarks'] ?? '';
    
    $stmt->bind_param(
        "iiiiiiiiisiiiiiiissss",
        $tenant_id,
        $input['election_id'],
        $input['pu_id'],
        $input['ward_id'],
        $input['lga_id'],
        $input['state_id'],
        $user_id,
        $assignment['id'],
        $pu['code'] ?? '',
        $pu['name'] ?? '',
        $input['registered_voters'] ?? 0,
        $input['accredited_voters'] ?? 0,
        $input['ballot_papers_issued'] ?? 0,
        $input['unused_ballots'] ?? 0,
        $input['spoiled_ballots'] ?? 0,
        $input['rejected_votes'] ?? 0,
        $input['valid_votes'] ?? 0,
        $input['total_votes_cast'] ?? 0,
        $party_votes_json,
        $gps_lat,
        $gps_lng,
        $device_id,
        $remarks
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to submit result: " . $stmt->error);
    }
    
    $result_id = $db->insert_id;
    $stmt->close();
    
    // Log activity
    $logStmt = $db->prepare("
        INSERT INTO activity_logs (user_id, tenant_id, activity_type, description, entity_type, entity_id, ip_address, created_at)
        VALUES (?, ?, 'result_submitted', 'Submitted EC8A result for PU: ' || ?, 'results_ec8a', ?, ?, NOW())
    ");
    $logStmt->bind_param("iisss", $user_id, $tenant_id, $input['pu_id'], $result_id, $_SERVER['REMOTE_ADDR']);
    $logStmt->execute();
    $logStmt->close();
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Result submitted successfully',
        'result_id' => $result_id
    ]);
    
} catch (Exception $e) {
    $db->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to submit result: ' . $e->getMessage()]);
}
?>