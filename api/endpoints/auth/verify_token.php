<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';

// Only GET method allowed
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', HTTP_METHOD_NOT_ALLOWED);
}

$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    sendError('Unauthorized: No token provided', HTTP_UNAUTHORIZED);
}

$token = $matches[1];
$userId = verifyToken($token);

if (!$userId) {
    sendError('Invalid or expired token', HTTP_UNAUTHORIZED);
}

$user = getUserData($userId);

if (!$user) {
    sendError('User not found', HTTP_NOT_FOUND);
}

sendSuccess('Token is valid', [
    'user' => $user
]);