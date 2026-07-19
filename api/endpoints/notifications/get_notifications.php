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

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$isRead = isset($_GET['is_read']) ? (int)$_GET['is_read'] : -1;

try {
    $conn = getDBConnection();
    
    $query = "
        SELECT * FROM notifications 
        WHERE user_id = ?
    ";
    $params = [$userId];
    $types = "i";
    
    if ($isRead >= 0) {
        $query .= " AND is_read = ?";
        $params[] = $isRead;
        $types .= "i";
    }
    
    $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    // Get unread count
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as unread_count 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $countStmt->bind_param("i", $userId);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $unreadCount = $countResult->fetch_assoc()['unread_count'] ?? 0;
    $countStmt->close();
    
    $stmt->close();
    $conn->close();
    
    sendSuccess('Notifications retrieved successfully', [
        'notifications' => $notifications,
        'unread_count' => $unreadCount,
        'total' => count($notifications),
        'limit' => $limit,
        'offset' => $offset
    ]);
    
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), HTTP_INTERNAL_ERROR);
}