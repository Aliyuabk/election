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

$phone = isset($input['phone']) ? sanitizeInput($input['phone']) : null;
$gender = isset($input['gender']) ? sanitizeInput($input['gender']) : null;
$dateOfBirth = isset($input['date_of_birth']) ? sanitizeInput($input['date_of_birth']) : null;
$address = isset($input['address']) ? sanitizeInput($input['address']) : null;
$residentialAddress = isset($input['residential_address']) ? sanitizeInput($input['residential_address']) : null;
$emergencyContactName = isset($input['emergency_contact_name']) ? sanitizeInput($input['emergency_contact_name']) : null;
$emergencyContactPhone = isset($input['emergency_contact_phone']) ? sanitizeInput($input['emergency_contact_phone']) : null;

try {
    $conn = getDBConnection();
    
    // Build update query
    $updates = [];
    $params = [];
    $types = "";
    
    if ($phone !== null) {
        $updates[] = "phone = ?";
        $params[] = $phone;
        $types .= "s";
    }
    if ($gender !== null) {
        $updates[] = "gender = ?";
        $params[] = $gender;
        $types .= "s";
    }
    if ($dateOfBirth !== null) {
        $updates[] = "date_of_birth = ?";
        $params[] = $dateOfBirth;
        $types .= "s";
    }
    if ($address !== null) {
        $updates[] = "address = ?";
        $params[] = $address;
        $types .= "s";
    }
    if ($residentialAddress !== null) {
        $updates[] = "residential_address = ?";
        $params[] = $residentialAddress;
        $types .= "s";
    }
    if ($emergencyContactName !== null) {
        $updates[] = "emergency_contact_name = ?";
        $params[] = $emergencyContactName;
        $types .= "s";
    }
    if ($emergencyContactPhone !== null) {
        $updates[] = "emergency_contact_phone = ?";
        $params[] = $emergencyContactPhone;
        $types .= "s";
    }
    
    if (empty($updates)) {
        sendError('No fields to update', HTTP_BAD_REQUEST);
    }
    
    $updates[] = "updated_at = NOW()";
    $params[] = $userId;
    $types .= "i";
    
    $query = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();
    
    // Log activity
    $logStmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, activity_type, description, created_at)
        VALUES (?, 'profile_updated', 'Profile information updated', NOW())
    ");
    $logStmt->bind_param("i", $userId);
    $logStmt->execute();
    $logStmt->close();
    
    $conn->close();
    
    sendSuccess('Profile updated successfully');
    
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), HTTP_INTERNAL_ERROR);
}