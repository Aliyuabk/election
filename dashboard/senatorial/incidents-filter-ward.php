<?php
// ============================================================
// SENATORIAL COORDINATOR - FILTER INCIDENTS BY WARD
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

$ward_id = isset($_GET['ward']) ? (int)$_GET['ward'] : 0;
header('Location: incidents.php?ward=' . $ward_id);
exit();