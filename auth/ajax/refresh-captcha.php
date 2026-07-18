<?php
// ============================================================
// AJAX CAPTCHA REFRESH
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';

// Start session if not already started
SessionManager::start();

// Generate new CAPTCHA
$captcha = generateCaptcha();
$_SESSION['captcha_code'] = $captcha;

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'captcha' => $captcha
]);