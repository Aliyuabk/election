<?php
// ============================================================
// WARD COORDINATOR - EXPORT AS CSV
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'ward') {
    header('Location: ../client-admin/');
    exit();
}

// Redirect to export-excel.php which handles CSV export
$type = isset($_GET['type']) ? $_GET['type'] : 'ward_report';
$period = isset($_GET['period']) ? $_GET['period'] : 'month';
$pu_id = isset($_GET['pu_id']) ? (int)$_GET['pu_id'] : 0;

$redirect_url = 'export-excel.php?type=' . urlencode($type);
if ($period) {
    $redirect_url .= '&period=' . urlencode($period);
}
if ($pu_id > 0) {
    $redirect_url .= '&pu_id=' . $pu_id;
}

header('Location: ' . $redirect_url);
exit;
?>