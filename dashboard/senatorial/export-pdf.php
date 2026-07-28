<?php
// ============================================================
// SENATORIAL COORDINATOR - EXPORT PDF
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'senatorial') {
    header('Location: ../client-admin/');
    exit();
}

// Get report type and data
$report_type = isset($_GET['type']) ? $_GET['type'] : 'progress';
$format = isset($_GET['format']) ? $_GET['format'] : 'pdf';

// Redirect to the appropriate report with download parameter
$redirect_url = "report-$report_type.php?" . http_build_query(array_merge($_GET, ['download' => 'pdf']));
header('Location: ' . $redirect_url);
exit();