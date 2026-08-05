<?php
/**
 * Sync Offline Data
 * POST /api/sync/sync
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

if (!$data || !isset($data['data'])) {
    Response::error('Sync data is required', 400);
}

$syncData = $data['data'];
$deviceId = isset($data['device_id']) ? Validator::sanitize($data['device_id']) : null;

$db = Database::getInstance();

$results = [
    'synced_count' => 0,
    'failed_count' => 0,
    'errors' => [],
    'synced_items' => []
];

foreach ($syncData as $index => $item) {
    if (!isset($item['type']) || !isset($item['data'])) {
        $results['failed_count']++;
        $results['errors'][] = "Item $index: Missing type or data";
        continue;
    }
    
    $type = $item['type'];
    $dataItem = $item['data'];
    
    try {
        switch ($type) {
            case 'incident':
                $result = syncIncident($db, $user, $dataItem, $deviceId);
                break;
            case 'checkin':
                $result = syncCheckin($db, $user, $dataItem, $deviceId);
                break;
            case 'ec8a':
                $result = syncEC8A($db, $user, $dataItem, $deviceId);
                break;
            case 'chat':
                $result = syncChat($db, $user, $dataItem);
                break;
            case 'accreditation':
                $result = syncAccreditation($db, $user, $dataItem, $deviceId);
                break;
            case 'vote_count':
                $result = syncVoteCount($db, $user, $dataItem, $deviceId);
                break;
            case 'checklist':
                $result = syncChecklist($db, $user, $dataItem);
                break;
            case 'profile_update':
                $result = syncProfileUpdate($db, $user, $dataItem);
                break;
            default:
                $result = ['success' => false, 'error' => "Unknown sync type: $type"];
        }
        
        if ($result['success']) {
            $results['synced_count']++;
            $results['synced_items'][] = [
                'type' => $type,
                'id' => $result['id'] ?? null,
                'local_id' => $item['local_id'] ?? null
            ];
        } else {
            $results['failed_count']++;
            $results['errors'][] = "Item $index ($type): " . ($result['error'] ?? 'Unknown error');
        }
    } catch (Exception $e) {
        $results['failed_count']++;
        $results['errors'][] = "Item $index ($type): " . $e->getMessage();
    }
}

Response::success($results);

// ============================================
// SYNC HELPER FUNCTIONS
// ============================================

function syncIncident($db, $user, $data, $deviceId) {
    $required = ['incident_type', 'title', 'description'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return ['success' => false, 'error' => "$field is required"];
        }
    }
    
    $incidentType = Validator::sanitize($data['incident_type']);
    $title = Validator::sanitize($data['title']);
    $description = Validator::sanitize($data['description']);
    $severity = isset($data['severity']) ? Validator::sanitize($data['severity']) : 'medium';
    $electionId = isset($data['election_id']) ? intval($data['election_id']) : null;
    $puId = isset($data['pu_id']) ? intval($data['pu_id']) : null;
    $wardId = isset($data['ward_id']) ? intval($data['ward_id']) : null;
    $lgaId = isset($data['lga_id']) ? intval($data['lga_id']) : null;
    $stateId = isset($data['state_id']) ? intval($data['state_id']) : null;
    $gpsLat = isset($data['gps_lat']) ? floatval($data['gps_lat']) : null;
    $gpsLng = isset($data['gps_lng']) ? floatval($data['gps_lng']) : null;
    $photoUrls = isset($data['photo_urls']) ? json_encode($data['photo_urls']) : null;
    $isPanic = isset($data['is_panic']) ? 1 : 0;
    $offlineCreatedAt = isset($data['created_at']) ? $data['created_at'] : date('Y-m-d H:i:s');
    
    $validTypes = ['violence', 'intimidation', 'ballot_stuffing', 'vote_buying',
        'voter_suppression', 'material_shortage', 'delay', 'technical_issue',
        'other', 'panic_button'];
    
    if (!in_array($incidentType, $validTypes)) {
        return ['success' => false, 'error' => 'Invalid incident type'];
    }
    
    $tenantId = $user['tenant_id'];
    
    $stmt = $db->prepare("
        INSERT INTO incidents (
            tenant_id, election_id, reporter_id, pu_id, ward_id, lga_id, state_id,
            incident_type, severity, is_panic, title, description,
            gps_lat, gps_lng, photo_urls_json, device_id,
            is_offline_sync, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'reported', ?)
    ");
    
    $stmt->bind_param(
        "iiiiiiississsdsss",
        $tenantId, $electionId, $user['id'], $puId, $wardId, $lgaId, $stateId,
        $incidentType, $severity, $isPanic, $title, $description,
        $gpsLat, $gpsLng, $photoUrls, $deviceId, $offlineCreatedAt
    );
    
    if (!$stmt->execute()) {
        return ['success' => false, 'error' => $stmt->error];
    }
    
    $id = $db->lastInsertId();
    $stmt->close();
    
    return ['success' => true, 'id' => $id];
}

function syncCheckin($db, $user, $data, $deviceId) {
    $required = ['assignment_id', 'checkin_type', 'gps_lat', 'gps_lng'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            return ['success' => false, 'error' => "$field is required"];
        }
    }
    
    $assignmentId = intval($data['assignment_id']);
    $checkinType = Validator::sanitize($data['checkin_type']);
    $gpsLat = floatval($data['gps_lat']);
    $gpsLng = floatval($data['gps_lng']);
    $gpsAccuracy = isset($data['gps_accuracy']) ? floatval($data['gps_accuracy']) : null;
    $deviceBattery = isset($data['device_battery']) ? intval($data['device_battery']) : null;
    $networkType = isset($data['network_type']) ? Validator::sanitize($data['network_type']) : null;
    $offlineCreatedAt = isset($data['created_at']) ? $data['created_at'] : date('Y-m-d H:i:s');
    
    $validCheckinTypes = ['arrival', 'departure', 'material_received', 
        'accreditation_started', 'voting_started', 'voting_ended', 
        'counting_started', 'counting_ended'];
    
    if (!in_array($checkinType, $validCheckinTypes)) {
        return ['success' => false, 'error' => 'Invalid check-in type'];
    }
    
    $stmt = $db->prepare("
        SELECT pu_id, election_id, tenant_id FROM agent_assignments 
        WHERE id = ? AND user_id = ? AND status = 'active'
    ");
    $stmt->bind_param("ii", $assignmentId, $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $assignment = $result->fetch_assoc();
    $stmt->close();
    
    if (!$assignment) {
        return ['success' => false, 'error' => 'Invalid assignment'];
    }
    
    $stmt = $db->prepare("
        INSERT INTO agent_checkins (
            tenant_id, election_id, agent_id, assignment_id, pu_id,
            checkin_type, gps_lat, gps_lng, gps_accuracy,
            device_id, device_battery, network_type, is_offline_sync, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
    ");
    
    $stmt->bind_param(
        "iiiiisdidsiis",
        $assignment['tenant_id'], $assignment['election_id'], $user['id'], 
        $assignmentId, $assignment['pu_id'],
        $checkinType, $gpsLat, $gpsLng, $gpsAccuracy,
        $deviceId, $deviceBattery, $networkType, $offlineCreatedAt
    );
    
    if (!$stmt->execute()) {
        return ['success' => false, 'error' => $stmt->error];
    }
    
    $id = $db->lastInsertId();
    $stmt->close();
    
    return ['success' => true, 'id' => $id];
}

function syncEC8A($db, $user, $data, $deviceId) {
    $required = ['election_id', 'pu_id'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return ['success' => false, 'error' => "$field is required"];
        }
    }
    
    $electionId = intval($data['election_id']);
    $puId = intval($data['pu_id']);
    $validVotes = isset($data['valid_votes']) ? intval($data['valid_votes']) : 0;
    $rejectedVotes = isset($data['rejected_votes']) ? intval($data['rejected_votes']) : 0;
    $totalVotes = isset($data['total_votes']) ? intval($data['total_votes']) : 0;
    $partyVotes = isset($data['party_votes']) ? json_encode($data['party_votes']) : '{}';
    $photoUrl = isset($data['photo_url']) ? Validator::sanitize($data['photo_url']) : null;
    $photoHash = isset($data['photo_hash']) ? Validator::sanitize($data['photo_hash']) : null;
    $gpsLat = isset($data['gps_lat']) ? floatval($data['gps_lat']) : null;
    $gpsLng = isset($data['gps_lng']) ? floatval($data['gps_lng']) : null;
    $offlineCreatedAt = isset($data['created_at']) ? $data['created_at'] : date('Y-m-d H:i:s');
    
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
        return ['success' => false, 'error' => 'Access denied to this polling unit'];
    }
    
    $puResult = $db->query("SELECT code, name, registered_voters FROM polling_units WHERE id = $puId");
    $pu = $puResult->fetch_assoc();
    
    $stmt = $db->prepare("
        INSERT INTO results_ec8a (
            tenant_id, election_id, pu_id, ward_id, lga_id, state_id,
            agent_id, assignment_id, pu_code, pu_name, registered_voters,
            party_votes_json, valid_votes, rejected_votes, total_votes_cast,
            photo_url, photo_sha256, gps_lat, gps_lng, device_id,
            is_offline_sync, status, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?,
            ?, (SELECT id FROM agent_assignments WHERE user_id = ? AND pu_id = ? AND status = 'active' LIMIT 1),
            ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            1, 'pending', ?
        )
    ");
    
    $tenantId = $assignment['tenant_id'];
    $wardId = $assignment['ward_id'];
    $lgaId = $assignment['lga_id'];
    $stateId = $assignment['state_id'];
    
    $stmt->bind_param(
        "iiiiiiiiissiiidddsss",
        $tenantId, $electionId, $puId, $wardId, $lgaId, $stateId,
        $user['id'], $user['id'], $puId,
        $pu['code'], $pu['name'], $pu['registered_voters'],
        $partyVotes, $validVotes, $rejectedVotes, $totalVotes,
        $photoUrl, $photoHash, $gpsLat, $gpsLng, $deviceId,
        $offlineCreatedAt
    );
    
    if (!$stmt->execute()) {
        return ['success' => false, 'error' => $stmt->error];
    }
    
    $id = $db->lastInsertId();
    $stmt->close();
    
    return ['success' => true, 'id' => $id];
}

function syncChat($db, $user, $data) {
    $required = ['receiver_id', 'content'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return ['success' => false, 'error' => "$field is required"];
        }
    }
    
    $receiverId = intval($data['receiver_id']);
    $content = Validator::sanitize($data['content']);
    $messageType = isset($data['message_type']) ? Validator::sanitize($data['message_type']) : 'text';
    $mediaUrl = isset($data['media_url']) ? Validator::sanitize($data['media_url']) : null;
    $gpsLat = isset($data['gps_lat']) ? floatval($data['gps_lat']) : null;
    $gpsLng = isset($data['gps_lng']) ? floatval($data['gps_lng']) : null;
    $offlineCreatedAt = isset($data['created_at']) ? $data['created_at'] : date('Y-m-d H:i:s');
    
    $stmt = $db->prepare("SELECT id, tenant_id FROM users WHERE id = ? AND status = 'active'");
    $stmt->bind_param("i", $receiverId);
    $stmt->execute();
    $result = $stmt->get_result();
    $receiver = $result->fetch_assoc();
    $stmt->close();
    
    if (!$receiver) {
        return ['success' => false, 'error' => 'Receiver not found'];
    }
    
    $stmt = $db->prepare("
        SELECT id FROM chat_rooms 
        WHERE type = 'direct' 
        AND id IN (SELECT room_id FROM chat_room_members WHERE user_id = ?)
        AND id IN (SELECT room_id FROM chat_room_members WHERE user_id = ?)
    ");
    $stmt->bind_param("ii", $user['id'], $receiverId);
    $stmt->execute();
    $result = $stmt->get_result();
    $room = $result->fetch_assoc();
    $stmt->close();
    
    if (!$room) {
        $roomName = 'Chat between ' . $user['first_name'] . ' ' . $user['last_name'] . 
                    ' and ' . $receiver['first_name'] . ' ' . $receiver['last_name'];
        
        $stmt = $db->prepare("
            INSERT INTO chat_rooms (tenant_id, name, type, created_by, created_at)
            VALUES (?, ?, 'direct', ?, NOW())
        ");
        $tenantId = $user['tenant_id'];
        $stmt->bind_param("isi", $tenantId, $roomName, $user['id']);
        $stmt->execute();
        $roomId = $db->lastInsertId();
        $stmt->close();
        
        $stmt = $db->prepare("INSERT INTO chat_room_members (room_id, user_id, joined_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("ii", $roomId, $user['id']);
        $stmt->execute();
        $stmt->bind_param("ii", $roomId, $receiverId);
        $stmt->execute();
        $stmt->close();
    } else {
        $roomId = $room['id'];
    }
    
    $stmt = $db->prepare("
        INSERT INTO chat_messages (
            room_id, sender_id, receiver_id, message_type, content,
            media_url, gps_lat, gps_lng, is_offline_sync, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
    ");
    
    $stmt->bind_param(
        "iiisssdds",
        $roomId, $user['id'], $receiverId, $messageType, $content,
        $mediaUrl, $gpsLat, $gpsLng, $offlineCreatedAt
    );
    
    if (!$stmt->execute()) {
        return ['success' => false, 'error' => $stmt->error];
    }
    
    $id = $db->lastInsertId();
    $stmt->close();
    
    $db->query("
        INSERT INTO notifications (user_id, type, title, message, created_at)
        VALUES (
            $receiverId, 'chat',
            'New message from " . $user['first_name'] . " " . $user['last_name'] . "',
            '" . $db->escapeString(substr($content, 0, 100)) . "',
            NOW()
        )
    ");
    
    return ['success' => true, 'id' => $id];
}

function syncAccreditation($db, $user, $data, $deviceId) {
    $required = ['election_id', 'pu_id', 'accredited_voters'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            return ['success' => false, 'error' => "$field is required"];
        }
    }
    
    $electionId = intval($data['election_id']);
    $puId = intval($data['pu_id']);
    $accreditedVoters = intval($data['accredited_voters']);
    $gpsLat = isset($data['gps_lat']) ? floatval($data['gps_lat']) : null;
    $gpsLng = isset($data['gps_lng']) ? floatval($data['gps_lng']) : null;
    $offlineCreatedAt = isset($data['created_at']) ? $data['created_at'] : date('Y-m-d H:i:s');
    
    if ($accreditedVoters < 0) {
        return ['success' => false, 'error' => 'Accredited voters cannot be negative'];
    }
    
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
        return ['success' => false, 'error' => 'Access denied to this polling unit'];
    }
    
    $puResult = $db->query("SELECT registered_voters FROM polling_units WHERE id = $puId");
    $pu = $puResult->fetch_assoc();
    
    if ($accreditedVoters > $pu['registered_voters']) {
        return ['success' => false, 'error' => 'Accredited voters cannot exceed registered voters'];
    }
    
    $today = date('Y-m-d');
    $checkResult = $db->query("
        SELECT id FROM results_ec8a 
        WHERE pu_id = $puId AND DATE(created_at) = '$today'
    ");
    
    if ($checkResult->num_rows > 0) {
        $stmt = $db->prepare("
            UPDATE results_ec8a SET accredited_voters = ?, updated_at = NOW()
            WHERE pu_id = ? AND DATE(created_at) = ?
        ");
        $stmt->bind_param("iis", $accreditedVoters, $puId, $today);
    } else {
        $stmt = $db->prepare("
            INSERT INTO results_ec8a (
                tenant_id, election_id, pu_id, ward_id, lga_id, state_id,
                agent_id, assignment_id, pu_code, pu_name, registered_voters,
                accredited_voters, gps_lat, gps_lng, device_id,
                is_offline_sync, status, created_at
            ) SELECT 
                ?, ?, ?, ?, ?, ?,
                ?, aa.id, pu.code, pu.name, pu.registered_voters,
                ?, ?, ?, ?,
                1, 'pending', ?
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
            "iiiiiiiiiiddsii",
            $tenantId, $electionId, $puId, $wardId, $lgaId, $stateId,
            $user['id'], $accreditedVoters, $gpsLat, $gpsLng, $deviceId,
            $offlineCreatedAt, $user['id'], $puId
        );
    }
    
    if (!$stmt->execute()) {
        return ['success' => false, 'error' => $stmt->error];
    }
    
    $id = $db->lastInsertId();
    $stmt->close();
    
    $db->query("UPDATE polling_units SET accredited_voters = $accreditedVoters WHERE id = $puId");
    
    return ['success' => true, 'id' => $id];
}

function syncVoteCount($db, $user, $data, $deviceId) {
    $required = ['election_id', 'pu_id', 'party_votes'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            return ['success' => false, 'error' => "$field is required"];
        }
    }
    
    $electionId = intval($data['election_id']);
    $puId = intval($data['pu_id']);
    $partyVotes = $data['party_votes'];
    $validVotes = isset($data['valid_votes']) ? intval($data['valid_votes']) : 0;
    $rejectedVotes = isset($data['rejected_votes']) ? intval($data['rejected_votes']) : 0;
    $totalVotes = isset($data['total_votes']) ? intval($data['total_votes']) : 0;
    $gpsLat = isset($data['gps_lat']) ? floatval($data['gps_lat']) : null;
    $gpsLng = isset($data['gps_lng']) ? floatval($data['gps_lng']) : null;
    $offlineCreatedAt = isset($data['created_at']) ? $data['created_at'] : date('Y-m-d H:i:s');
    
    if (!is_array($partyVotes)) {
        return ['success' => false, 'error' => 'Party votes must be an array'];
    }
    
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
        return ['success' => false, 'error' => 'Access denied to this polling unit'];
    }
    
    $partyVotesJson = json_encode($partyVotes);
    
    $stmt = $db->prepare("
        INSERT INTO results_ec8a (
            tenant_id, election_id, pu_id, ward_id, lga_id, state_id,
            agent_id, assignment_id, pu_code, pu_name, registered_voters,
            party_votes_json, valid_votes, rejected_votes, total_votes_cast,
            gps_lat, gps_lng, device_id, is_offline_sync, status, created_at
        ) SELECT 
            ?, ?, ?, ?, ?, ?,
            ?, aa.id, pu.code, pu.name, pu.registered_voters,
            ?, ?, ?, ?,
            ?, ?, ?,
            1, 'pending', ?
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
        "iiiiiiisiiidddssii",
        $tenantId, $electionId, $puId, $wardId, $lgaId, $stateId,
        $user['id'], $partyVotesJson, $validVotes, $rejectedVotes, $totalVotes,
        $gpsLat, $gpsLng, $deviceId, $offlineCreatedAt,
        $user['id'], $puId
    );
    
    if (!$stmt->execute()) {
        return ['success' => false, 'error' => $stmt->error];
    }
    
    $id = $db->lastInsertId();
    $stmt->close();
    
    return ['success' => true, 'id' => $id];
}

function syncChecklist($db, $user, $data) {
    $required = ['election_id'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return ['success' => false, 'error' => "$field is required"];
        }
    }
    
    $electionId = intval($data['election_id']);
    $offlineCreatedAt = isset($data['created_at']) ? $data['created_at'] : date('Y-m-d H:i:s');
    
    $stmt = $db->prepare("
        SELECT id FROM election_checklists 
        WHERE user_id = ? AND election_id = ?
    ");
    $stmt->bind_param("ii", $user['id'], $electionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();
    
    if ($existing) {
        $fields = ['materials_arrived', 'poll_opened', 'accreditation_started', 
            'voting_started', 'counting_started', 'poll_closed'];
        
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
        
        if (isset($data['status'])) {
            $status = Validator::sanitize($data['status']);
            if (in_array($status, ['draft', 'submitted', 'completed'])) {
                $updates[] = "status = ?";
                $params[] = $status;
                $types .= "s";
                if ($status === 'submitted') {
                    $updates[] = "submitted_at = NOW()";
                }
            }
        }
        
        if (empty($updates)) {
            return ['success' => false, 'error' => 'No fields to update'];
        }
        
        $params[] = $existing['id'];
        $types .= "i";
        
        $sql = "UPDATE election_checklists SET " . implode(", ", $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            return ['success' => false, 'error' => $stmt->error];
        }
        
        $id = $existing['id'];
        $stmt->close();
    } else {
        $stmt = $db->prepare("
            INSERT INTO election_checklists (
                user_id, election_id, pu_id,
                materials_arrived, poll_opened, accreditation_started,
                voting_started, counting_started, poll_closed,
                status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?)
        ");
        
        $puId = isset($data['pu_id']) ? intval($data['pu_id']) : null;
        $materials = isset($data['materials_arrived']) ? intval($data['materials_arrived']) : 0;
        $pollOpened = isset($data['poll_opened']) ? intval($data['poll_opened']) : 0;
        $accreditation = isset($data['accreditation_started']) ? intval($data['accreditation_started']) : 0;
        $voting = isset($data['voting_started']) ? intval($data['voting_started']) : 0;
        $counting = isset($data['counting_started']) ? intval($data['counting_started']) : 0;
        $pollClosed = isset($data['poll_closed']) ? intval($data['poll_closed']) : 0;
        
        $stmt->bind_param(
            "iiiiiiiiis",
            $user['id'], $electionId, $puId,
            $materials, $pollOpened, $accreditation,
            $voting, $counting, $pollClosed,
            $offlineCreatedAt
        );
        
        if (!$stmt->execute()) {
            return ['success' => false, 'error' => $stmt->error];
        }
        
        $id = $db->lastInsertId();
        $stmt->close();
    }
    
    return ['success' => true, 'id' => $id];
}

function syncProfileUpdate($db, $user, $data) {
    $updates = [];
    $params = [];
    $types = "";
    
    $allowedFields = ['phone', 'photograph_url', 'gender', 'date_of_birth', 
        'residential_address', 'emergency_contact_name', 'emergency_contact_phone'];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $value = Validator::sanitize($data[$field]);
            $updates[] = "$field = ?";
            $params[] = $value;
            $types .= "s";
        }
    }
    
    if (empty($updates)) {
        return ['success' => false, 'error' => 'No fields to update'];
    }
    
    $params[] = $user['id'];
    $types .= "i";
    
    $sql = "UPDATE users SET " . implode(", ", $updates) . ", updated_at = NOW() WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if (!$stmt->execute()) {
        return ['success' => false, 'error' => $stmt->error];
    }
    
    $stmt->close();
    
    return ['success' => true, 'id' => $user['id']];
}