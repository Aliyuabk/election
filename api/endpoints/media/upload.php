<?php
/**
 * Upload Media (Photo/Video/Audio)
 * POST /api/media/upload
 */

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/response.php';
require_once dirname(__DIR__, 2) . '/includes/validation.php';

$auth = new Auth();
$user = $auth->authenticate();

if (!$user) {
    Response::unauthorized();
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    Response::error('File is required', 400);
}

$file = $_FILES['file'];
$mediaType = isset($_POST['media_type']) ? Validator::sanitize($_POST['media_type']) : 'photo';
$electionId = isset($_POST['election_id']) ? intval($_POST['election_id']) : 0;
$puId = isset($_POST['pu_id']) ? intval($_POST['pu_id']) : 0;

$allowedTypes = [
    'photo' => ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'],
    'video' => ['video/mp4', 'video/avi', 'video/mov', 'video/3gp'],
    'audio' => ['audio/mp3', 'audio/wav', 'audio/m4a']
];

$maxSizes = [
    'photo' => 10 * 1024 * 1024, // 10MB
    'video' => 100 * 1024 * 1024, // 100MB
    'audio' => 20 * 1024 * 1024 // 20MB
];

if (!in_array($mediaType, ['photo', 'video', 'audio'])) {
    Response::error('Invalid media type', 422);
}

if (!in_array($file['type'], $allowedTypes[$mediaType])) {
    Response::error('Invalid file type for ' . $mediaType, 422);
}

if ($file['size'] > $maxSizes[$mediaType]) {
    Response::error('File too large. Maximum size for ' . $mediaType . ' is ' . ($maxSizes[$mediaType] / 1024 / 1024) . 'MB', 422);
}

$db = Database::getInstance();

// Verify user has access to PU
if ($puId > 0) {
    $accessCheck = $db->prepare("
        SELECT id FROM agent_assignments 
        WHERE user_id = ? AND pu_id = ? AND status = 'active'
    ");
    $accessCheck->bind_param("ii", $user['id'], $puId);
    $accessCheck->execute();
    $accessResult = $accessCheck->get_result();
    
    if ($accessResult->num_rows === 0) {
        Response::error('Access denied to this polling unit', 403);
    }
    $accessCheck->close();
}

// Upload directory
$uploadDir = '/path/to/uploads/' . $mediaType . '/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = $mediaType . '_' . $user['id'] . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
$filePath = $uploadDir . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    Response::error('Failed to upload file', 500);
}

// Save to database
$fileUrl = '/uploads/' . $mediaType . '/' . $filename;
$fileSize = $file['size'];
$originalName = $file['name'];

$stmt = $db->prepare("
    INSERT INTO media_uploads (
        user_id, tenant_id, election_id, pu_id,
        filename, original_name, file_type, file_size, file_path, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");

$tenantId = $user['tenant_id'] ?? null;

$stmt->bind_param(
    "iiissssis",
    $user['id'], $tenantId, $electionId, $puId,
    $filename, $originalName, $file['type'], $fileSize, $fileUrl
);

if (!$stmt->execute()) {
    unlink($filePath);
    Response::error('Failed to save media record', 500);
}

$mediaId = $db->lastInsertId();
$stmt->close();

// Log activity
$db->query("
    INSERT INTO activity_logs (user_id, activity_type, description, created_at)
    VALUES ({$user['id']}, 'media_upload', 
            'Uploaded $mediaType: $originalName', NOW())
");

Response::success([
    'media_id' => $mediaId,
    'file_url' => $fileUrl,
    'file_name' => $filename,
    'original_name' => $originalName,
    'file_size' => $fileSize,
    'media_type' => $mediaType
], 'Media uploaded successfully');
?>