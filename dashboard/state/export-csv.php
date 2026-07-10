<?php
// ============================================================
// STATE COORDINATOR - EXPORT AS CSV
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'state') {
    header('Location: ../client-admin/');
    exit();
}

// Redirect to export-excel.php which handles CSV export
$type = isset($_GET['type']) ? $_GET['type'] : 'state_report';
$election_id = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;
$lga_id = isset($_GET['lga_id']) ? (int)$_GET['lga_id'] : 0;

$redirect_url = 'export-excel.php?type=' . urlencode($type);
if ($election_id > 0) {
    $redirect_url .= '&election_id=' . $election_id;
}
if ($lga_id > 0) {
    $redirect_url .= '&lga_id=' . $lga_id;
}

header('Location: ' . $redirect_url);
exit;
?>