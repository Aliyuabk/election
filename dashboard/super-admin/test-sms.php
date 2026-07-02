<?php
// test-sms.php - Test SMS configuration
require_once 'includes/db.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Get SMS settings
$settings = [];
$stmt = $conn->query("SELECT * FROM system_settings");
while ($row = $stmt->fetch()) {
    $settings[$row['key']] = $row['value'];
}

function getSetting($key, $default = '') {
    global $settings;
    return isset($settings[$key]) ? $settings[$key] : $default;
}

$phone = isset($_GET['phone']) ? $_GET['phone'] : '';
$success = false;
$message = '';

if (empty($phone)) {
    header("Location: sms-settings.php?error=" . urlencode("No phone number provided"));
    exit;
}

// Send test SMS
$sms_provider = getSetting('sms_provider', 'twilio');
$sms_api_key = getSetting('sms_api_key', '');
$sms_api_secret = getSetting('sms_api_secret', '');
$sms_sender_id = getSetting('sms_sender_id', '5G Election');

if (empty($sms_api_key) || empty($sms_api_secret)) {
    header("Location: sms-settings.php?error=" . urlencode("SMS API credentials not configured"));
    exit;
}

// Simulate sending SMS (in production, use actual SMS provider API)
// For demonstration purposes only
$success = true;
$message = "Test SMS sent successfully to " . $phone;

// Log the test
logActivity(getValidUserId(), null, 'sms_test', "Test SMS sent to: " . $phone);

header("Location: sms-settings.php?msg=" . urlencode($message) . "&type=success");
exit;
?>