<?php
/**
 * Disable Fingerprint Login
 * POST /api/fingerprint/disable
 */

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/Response.php';
require_once dirname(__DIR__, 2) . '/includes/MobileAuth.php';

$auth = new Auth();
$user = $auth->authenticate();

if (!$user) {
    Response::unauthorized();
}

$mobileAuth = new MobileAuth();
$result = $mobileAuth->disableFingerprint($user['id']);

if ($result['success']) {
    Response::success(null, $result['message']);
} else {
    Response::error($result['message'], 400);
}