<?php
/**
 * Delete Media
 * POST /api/media/delete
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

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

if (!$data || !isset($data['media_id'])) {
    Response::error('Media ID is required', 400);
}

$mediaId = intval($data['media_id']);

$db = Database::getInstance();

// Verify media belongs to user
$stmt = $db->prepare("
    SELECT file_path FROM media_uploads WHERE id = ? AND user_id = ?
");
$stmt->bind_param("ii", $mediaId, $user['id']);
$stmt->execute();
$result = $stmt->get_result();
$media = $result->fetch_assoc();
$stmt->close();

if (!$media) {
    Response::error('Media not found or access denied', 404);
}

// Delete file
$filePath = $_SERVER['DOCUMENT_ROOT'] . $media['file_path'];
if (file_exists($filePath)) {
    unlink($filePath);
}

// Delete from database
$stmt = $db->prepare("DELETE FROM media_uploads WHERE id = ?");
$stmt->bind_param("i", $mediaId);
$stmt->execute();
$stmt->close();

Response::success(null, 'Media deleted successfully');