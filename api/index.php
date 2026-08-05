<?php
/**
 * API Router
 * Routes requests to appropriate endpoints for mobile app
 */

// Load configuration
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/Response.php';

// Parse route
$route = isset($_GET['route']) ? $_GET['route'] : '';
$method = $_SERVER['REQUEST_METHOD'];

// Log request
logApiRequest($route);

// Route definitions for mobile app
$routes = [
    // Auth
    'auth/login' => ['file' => 'endpoints/auth/login.php', 'methods' => ['POST']],
    'auth/logout' => ['file' => 'endpoints/auth/logout.php', 'methods' => ['POST']],
    'auth/forgot-password' => ['file' => 'endpoints/auth/forgot_password.php', 'methods' => ['POST']],
    'auth/change-password' => ['file' => 'endpoints/auth/change_password.php', 'methods' => ['POST']],
    
    // Fingerprint
    'fingerprint/enable' => ['file' => 'endpoints/fingerprint/enable.php', 'methods' => ['POST']],
    'fingerprint/disable' => ['file' => 'endpoints/fingerprint/disable.php', 'methods' => ['POST']],
    
    // User
    'user/profile' => ['file' => 'endpoints/user/profile.php', 'methods' => ['GET']],
    'user/dashboard' => ['file' => 'endpoints/user/dashboard.php', 'methods' => ['GET']],
    'user/update-profile' => ['file' => 'endpoints/user/update_profile.php', 'methods' => ['POST']],
    
    // Polling Unit
    'polling-unit/assigned' => ['file' => 'endpoints/polling-unit/assigned.php', 'methods' => ['GET']],
    'polling-unit/details' => ['file' => 'endpoints/polling-unit/details.php', 'methods' => ['GET']],
    
    // Check-in
    'checkin/create' => ['file' => 'endpoints/checkin/create.php', 'methods' => ['POST']],
    
    // Checklist
    'checklist/get' => ['file' => 'endpoints/checklist/get.php', 'methods' => ['GET']],
    'checklist/update' => ['file' => 'endpoints/checklist/update.php', 'methods' => ['POST']],
    
    // Accreditation
    'accreditation/record' => ['file' => 'endpoints/accreditation/record.php', 'methods' => ['POST']],
    
    // Vote Count
    'vote-count/record' => ['file' => 'endpoints/vote-count/record.php', 'methods' => ['POST']],
    
    // EC8A
    'ec8a/upload' => ['file' => 'endpoints/ec8a/upload.php', 'methods' => ['POST']],
    
    // Media
    'media/upload' => ['file' => 'endpoints/media/upload.php', 'methods' => ['POST']],
    'media/delete' => ['file' => 'endpoints/media/delete.php', 'methods' => ['POST']],
    
    // Incidents
    'incidents/create' => ['file' => 'endpoints/incidents/create.php', 'methods' => ['POST']],
    'incidents/list' => ['file' => 'endpoints/incidents/list.php', 'methods' => ['GET']],
    
    // Panic
    'panic/trigger' => ['file' => 'endpoints/panic/trigger.php', 'methods' => ['POST']],
    
    // Chat
    'chat/send' => ['file' => 'endpoints/chat/send.php', 'methods' => ['POST']],
    'chat/list' => ['file' => 'endpoints/chat/list.php', 'methods' => ['GET']],
    'chat/history' => ['file' => 'endpoints/chat/history.php', 'methods' => ['GET']],
    
    // Notifications
    'notifications/list' => ['file' => 'endpoints/notifications/list.php', 'methods' => ['GET']],
    'notifications/mark-read' => ['file' => 'endpoints/notifications/mark_read.php', 'methods' => ['POST']],
    
    // Sync
    'sync/sync' => ['file' => 'endpoints/sync/sync.php', 'methods' => ['POST']],
    
    // History
    'history/uploads' => ['file' => 'endpoints/history/uploads.php', 'methods' => ['GET']],
];

// Find matching route
$matched = false;
foreach ($routes as $routePath => $routeConfig) {
    if ($route === $routePath) {
        if (!in_array($method, $routeConfig['methods'])) {
            Response::error('Method not allowed', 405);
        }
        if (file_exists($routeConfig['file'])) {
            require_once $routeConfig['file'];
            $matched = true;
        }
        break;
    }
}

if (!$matched) {
    Response::error('Endpoint not found', 404);
}