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

$type = isset($_POST['type']) ? sanitizeInput($_POST['type']) : 'photo';
$electionId = isset($_POST['election_id']) ? (int)$_POST['election_id'] : null;
$puId = isset($_POST['pu_id']) ? (int)$_POST['pu_id'] : null;

// Validate file
if (!isset($_FILES['file'])) {
    sendError('No file uploaded', HTTP_BAD_REQUEST);
}

$file = $_FILES['file'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/mov', 'application/pdf'];
$maxSize = 10 * 1024 * 1024; // 10MB

if ($file['error'] !== UPLOAD_ERR_OK) {
    sendError('File upload error: ' . $file['error'], HTTP_BAD_REQUEST);
}

if (!in_array($file['type'], $allowedTypes)) {
    sendError('File type not allowed', HTTP_BAD_REQUEST);
}

if ($file['size'] > $maxSize) {
    sendError('File size exceeds maximum allowed (10MB)', HTTP_BAD_REQUEST);
}

try {
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . date('Ymd_His') . '.' . $extension;
    
    // Determine upload directory
    $uploadDir = MEDIA_PATH . $type . '/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $filePath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        sendError('Failed to save file', HTTP_INTERNAL_ERROR);
    }
    
    // Store file info in database
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        INSERT INTO media_uploads 
        (user_id, tenant_id, election_id, pu_id, filename, original_name, file_type, file_size, file_path, created_at)
        SELECT ?, u.tenant_id, ?, ?, ?, ?, ?, ?, NOW()
        FROM users u
        WHERE u.id = ?
    ");
    $originalName = sanitizeInput($file['name']);
    $fileType = sanitizeInput($file['type']);
    $fileSize = $file['size'];
    $filePath = $type . '/' . $filename;
    
    $stmt->bind_param(
        "iiissssi", 
        $userId, $electionId, $puId, $filename, $originalName, $fileType, $fileSize, $filePath, $userId
    );
    $stmt->execute();
    $uploadId = $stmt->insert_id;
    $stmt->close();
    $conn->close();
    
    $url = '/uploads/media/' . $filePath;
    
    sendSuccess('File uploaded successfully', [
        'id' => $uploadId,
        'url' => $url,
        'filename' => $filename,
        'original_name' => $originalName,
        'file_type' => $fileType,
        'file_size' => $fileSize
    ]);
    
} catch (Exception $e) {
    // Delete uploaded file if error
    if (isset($filePath) && file_exists($filePath)) {
        unlink($filePath);
    }
    sendError('Server error: ' . $e->getMessage(), HTTP_INTERNAL_ERROR);
}