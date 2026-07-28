<?php
// ============================================================
// SENATORIAL COORDINATOR - FILTER INCIDENTS BY STATUS
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

$status = isset($_GET['status']) ? $_GET['status'] : '';
header('Location: incidents.php?status=' . urlencode($status));
exit();