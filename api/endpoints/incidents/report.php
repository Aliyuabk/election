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
$required = ['title', 'description', 'incident_type'];
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
    
    $stmt = $db->prepare("
        INSERT INTO incidents (
            tenant_id, reporter_id, pu_id, ward_id, lga_id, state_id,
            incident_type, severity, is_panic, title, description,
            gps_lat, gps_lng, device_id, status, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, 'reported', NOW()
        )
    ");
    
    $is_panic = $input['is_panic'] ?? 0;
    $severity = $input['severity'] ?? 'medium';
    
    $stmt->bind_param(
        "iiiiiiisissddss",
        $tenant_id,
        $user_id,
        $input['pu_id'] ?? null,
        $input['ward_id'] ?? null,
        $input['lga_id'] ?? null,
        $input['state_id'] ?? null,
        $input['incident_type'],
        $severity,
        $is_panic,
        $input['title'],
        $input['description'],
        $input['gps_lat'] ?? null,
        $input['gps_lng'] ?? null,
        $input['device_id'] ?? null
    );
    
    if ($stmt->execute()) {
        $incident_id = $db->insert_id;
        $stmt->close();
        
        // Log activity
        $logStmt = $db->prepare("
            INSERT INTO activity_logs (user_id, tenant_id, activity_type, description, entity_type, entity_id, ip_address, created_at)
            VALUES (?, ?, 'incident_reported', 'Reported incident: ' || ?, 'incidents', ?, ?, NOW())
        ");
        $logStmt->bind_param("iisis", $user_id, $tenant_id, $input['title'], $incident_id, $_SERVER['REMOTE_ADDR']);
        $logStmt->execute();
        $logStmt->close();
        
        // If panic button, create high priority notification
        if ($is_panic) {
            // You can add logic here to send SMS/email notifications
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Incident reported successfully',
            'incident_id' => $incident_id
        ]);
    } else {
        throw new Exception("Failed to report incident: " . $stmt->error);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to report incident: ' . $e->getMessage()]);
}
?>