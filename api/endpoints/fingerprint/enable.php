<?php
/**
 * Enable Fingerprint Login
 * POST /api/fingerprint/enable
 */

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/Response.php';
require_once dirname(__DIR__, 2) . '/includes/MobileAuth.php';
require_once dirname(__DIR__, 2) . '/includes/Validator.php';

$auth = new Auth();
$user = $auth->authenticate();

if (!$user) {
    Response::unauthorized();
}

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

if (!$data || !isset($data['fingerprint_hash'])) {
    Response::error('Fingerprint hash is required', 400);
}

$fingerprintHash = Validator::sanitize($data['fingerprint_hash']);

$mobileAuth = new MobileAuth();
$result = $mobileAuth->enableFingerprint($user['id'], $fingerprintHash);

if ($result['success']) {
    Response::success(null, $result['message']);
} else {
    Response::error($result['message'], 400);
}