<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$headers = getallheaders();
$token = null;
if (isset($headers['Authorization'])) {
    $token = str_replace('Bearer ', '', $headers['Authorization']);
}

if (!$token) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$host = 'localhost';
$db_name = 'utgoohwm_election';
$username = 'utgoohwm_election'; // Your actual database username
$password_db = 'Jiddaahh@1'; // Your actual database password

try {
    $conn = new mysqli($host, $username, $password_db, $db_name);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed");
    }
    
    $conn->set_charset("utf8mb4");
    
    $sessionStmt = $conn->prepare("
        SELECT user_id FROM user_sessions WHERE token = ? AND is_active = 1
    ");
    $sessionStmt->bind_param("s", $token);
    $sessionStmt->execute();
    $sessionResult = $sessionStmt->get_result();
    
    if ($sessionResult->num_rows === 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid session']);
        $sessionStmt->close();
        $conn->close();
        exit;
    }
    
    $session = $sessionResult->fetch_assoc();
    $userId = $session['user_id'];
    $sessionStmt->close();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get EC8A results
        $stmt = $conn->prepare("
            SELECT * FROM results_ec8a 
            WHERE agent_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $results = [];
        while ($row = $result->fetch_assoc()) {
            $results[] = [
                'id' => $row['id'],
                'tenant_id' => $row['tenant_id'],
                'election_id' => $row['election_id'],
                'pu_id' => $row['pu_id'],
                'ward_id' => $row['ward_id'],
                'lga_id' => $row['lga_id'],
                'state_id' => $row['state_id'],
                'agent_id' => $row['agent_id'],
                'assignment_id' => $row['assignment_id'],
                'pu_code' => $row['pu_code'],
                'pu_name' => $row['pu_name'],
                'registered_voters' => $row['registered_voters'],
                'accredited_voters' => $row['accredited_voters'],
                'ballot_papers_issued' => $row['ballot_papers_issued'],
                'unused_ballots' => $row['unused_ballots'],
                'spoiled_ballots' => $row['spoiled_ballots'],
                'rejected_votes' => $row['rejected_votes'],
                'valid_votes' => $row['valid_votes'],
                'total_votes_cast' => $row['total_votes_cast'],
                'party_votes_json' => $row['party_votes_json'],
                'photo_url' => $row['photo_url'],
                'video_url' => $row['video_url'],
                'audio_url' => $row['audio_url'],
                'remarks' => $row['remarks'],
                'gps_lat' => $row['gps_lat'],
                'gps_lng' => $row['gps_lng'],
                'status' => $row['status'],
                'created_at' => $row['created_at']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $results
        ]);
        
        $stmt->close();
        
    } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Submit EC8A result
        $input = json_decode(file_get_contents('php://input'), true);
        
        $required = [
            'pu_id', 'ward_id', 'lga_id', 'state_id',
            'pu_code', 'pu_name', 'registered_voters', 'accredited_voters',
            'valid_votes', 'rejected_votes', 'party_votes_json'
        ];
        
        foreach ($required as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "$field is required"]);
                exit;
            }
        }
        
        // Get assignment
        $assignmentStmt = $conn->prepare("
            SELECT id FROM agent_assignments 
            WHERE user_id = ? AND pu_id = ? AND status IN ('active', 'pending')
            ORDER BY created_at DESC LIMIT 1
        ");
        $assignmentStmt->bind_param("ii", $userId, $input['pu_id']);
        $assignmentStmt->execute();
        $assignmentResult = $assignmentStmt->get_result();
        
        if ($assignmentResult->num_rows === 0) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No active assignment for this polling unit']);
            $assignmentStmt->close();
            $conn->close();
            exit;
        }
        
        $assignment = $assignmentResult->fetch_assoc();
        $assignmentStmt->close();
        
        // Check if result already exists
        $checkStmt = $conn->prepare("
            SELECT id FROM results_ec8a 
            WHERE agent_id = ? AND pu_id = ? AND election_id = (
                SELECT election_id FROM agent_assignments WHERE id = ?
            )
        ");
        $checkStmt->bind_param("iii", $userId, $input['pu_id'], $assignment['id']);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'EC8A result already submitted for this polling unit'
            ]);
            $checkStmt->close();
            $conn->close();
            exit;
        }
        $checkStmt->close();
        
        $stmt = $conn->prepare("
            INSERT INTO results_ec8a (
                tenant_id, election_id, pu_id, ward_id, lga_id, state_id,
                agent_id, assignment_id, pu_code, pu_name,
                registered_voters, accredited_voters, ballot_papers_issued,
                unused_ballots, spoiled_ballots, rejected_votes, valid_votes,
                total_votes_cast, party_votes_json, photo_url, video_url,
                audio_url, remarks, gps_lat, gps_lng, device_id,
                status, is_offline_sync, created_at
            ) VALUES (
                (SELECT tenant_id FROM agent_assignments WHERE id = ?),
                (SELECT election_id FROM agent_assignments WHERE id = ?),
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                'pending', ?, NOW()
            )
        ");
        
        $tenantId = $input['tenant_id'] ?? null;
        $electionId = $input['election_id'] ?? null;
        $partyVotesJson = json_encode($input['party_votes_json']);
        $photoUrl = $input['photo_url'] ?? null;
        $videoUrl = $input['video_url'] ?? null;
        $audioUrl = $input['audio_url'] ?? null;
        $remarks = $input['remarks'] ?? null;
        $gpsLat = $input['gps_lat'] ?? null;
        $gpsLng = $input['gps_lng'] ?? null;
        $deviceId = $input['device_id'] ?? null;
        $isOfflineSync = $input['is_offline_sync'] ?? 0;
        
        $stmt->bind_param(
            "iiiiiiiiiiiiiiiiiiissssddssi",
            $assignment['id'],
            $assignment['id'],
            $input['pu_id'],
            $input['ward_id'],
            $input['lga_id'],
            $input['state_id'],
            $userId,
            $assignment['id'],
            $input['pu_code'],
            $input['pu_name'],
            $input['registered_voters'],
            $input['accredited_voters'],
            $input['ballot_papers_issued'] ?? 0,
            $input['unused_ballots'] ?? 0,
            $input['spoiled_ballots'] ?? 0,
            $input['rejected_votes'],
            $input['valid_votes'],
            $input['total_votes_cast'] ?? ($input['valid_votes'] + $input['rejected_votes']),
            $partyVotesJson,
            $photoUrl,
            $videoUrl,
            $audioUrl,
            $remarks,
            $gpsLat,
            $gpsLng,
            $deviceId,
            $isOfflineSync
        );
        
        if ($stmt->execute()) {
            $id = $conn->insert_id;
            
            $logStmt = $conn->prepare("
                INSERT INTO activity_logs (user_id, activity_type, description, entity_type, entity_id, created_at)
                VALUES (?, 'ec8a_submitted', 'Submitted EC8A result', 'results_ec8a', ?, NOW())
            ");
            $logStmt->bind_param("ii", $userId, $id);
            $logStmt->execute();
            $logStmt->close();
            
            echo json_encode([
                'success' => true,
                'message' => 'EC8A result submitted successfully',
                'id' => $id
            ]);
        } else {
            throw new Exception("Failed to submit EC8A: " . $stmt->error);
        }
        
        $stmt->close();
    }
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>