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

// Get ward ID from URL
$ward_id = isset($_GET['ward']) ? (int)$_GET['ward'] : 0;
$return_url = isset($_GET['return']) ? $_GET['return'] : 'incidents.php';

// Validate ward exists
if ($ward_id > 0) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT id, name FROM wards WHERE id = ? AND is_active = 1");
        $stmt->execute([$ward_id]);
        $ward = $stmt->fetch();
        if ($ward) {
            // Redirect to incidents with ward filter
            header("Location: incidents.php?ward=$ward_id&ward_name=" . urlencode($ward['name']));
            exit();
        }
    } catch (Exception $e) {
        error_log("Error validating ward: " . $e->getMessage());
    }
}

// Fallback - redirect to incidents
header("Location: incidents.php");
exit();
?>