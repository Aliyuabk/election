<?php
// ============================================================
// LGA COORDINATOR - EXPORT AS EXCEL (CSV format)
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'lga') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'LGA Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$lga_id = SessionManager::get('lga_id');

if (empty($lga_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT lga_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['lga_id'])) {
            $lga_id = $user['lga_id'];
            SessionManager::set('lga_id', $lga_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching lga_id: " . $e->getMessage());
    }
}

$db = getDB();

// Get parameters
$type = isset($_GET['type']) ? $_GET['type'] : 'lga_report';
$ward_id = isset($_GET['ward_id']) ? (int)$_GET['ward_id'] : 0;

// Get LGA name
$lga_name = 'LGA';
try {
    if ($lga_id) {
        $stmt = $db->prepare("SELECT name FROM lgas WHERE id = ?");
        $stmt->execute([$lga_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $lga_name = $result['name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching LGA: " . $e->getMessage());
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $type . '_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

$headers = [];
$data_rows = [];

try {
    if ($type === 'lga_report') {
        $headers = ['Ward', 'Code', 'Polling Units', 'Results', 'Verified', 'Pending', 'Reporting Rate', 'Coordinators', 'Incidents', 'Voters'];
        
        $stmt = $db->prepare("
            SELECT 
                w.name,
                w.code,
                w.registered_voters,
                COUNT(DISTINCT pu.id) as total_pus,
                COUNT(DISTINCT r.id) as total_results,
                COUNT(DISTINCT CASE WHEN r.status IN ('verified', 'approved') THEN r.id END) as verified_results,
                COUNT(DISTINCT CASE WHEN r.status = 'pending' THEN r.id END) as pending_results,
                COUNT(DISTINCT u.id) as coordinators,
                COUNT(DISTINCT i.id) as incidents
            FROM wards w
            LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
            LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
            LEFT JOIN users u ON u.ward_id = w.id AND u.status = 'active'
            LEFT JOIN incidents i ON i.ward_id = w.id
            WHERE w.lga_id = ? AND w.is_active = 1
            GROUP BY w.id, w.name, w.code, w.registered_voters
            ORDER BY w.name ASC
        ");
        $stmt->execute([$tenant_id, $lga_id]);
        $wards = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($wards as $ward) {
            $rate = $ward['total_pus'] > 0 ? round(($ward['total_results'] / $ward['total_pus']) * 100, 1) : 0;
            $data_rows[] = [
                $ward['name'],
                $ward['code'],
                $ward['total_pus'],
                $ward['total_results'],
                $ward['verified_results'],
                $ward['pending_results'],
                $rate . '%',
                $ward['coordinators'],
                $ward['incidents'],
                $ward['registered_voters']
            ];
        }
        
    } elseif ($type === 'ward_report') {
        $headers = ['Ward', 'Code', 'Polling Units', 'Results', 'Verified', 'Pending', 'Reporting Rate', 'Coordinators', 'Incidents', 'Voters'];
        
        $sql = "
            SELECT 
                w.name,
                w.code,
                w.registered_voters,
                COUNT(DISTINCT pu.id) as total_pus,
                COUNT(DISTINCT r.id) as total_results,
                COUNT(DISTINCT CASE WHEN r.status IN ('verified', 'approved') THEN r.id END) as verified_results,
                COUNT(DISTINCT CASE WHEN r.status = 'pending' THEN r.id END) as pending_results,
                COUNT(DISTINCT u.id) as coordinators,
                COUNT(DISTINCT i.id) as incidents
            FROM wards w
            LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
            LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
            LEFT JOIN users u ON u.ward_id = w.id AND u.status = 'active'
            LEFT JOIN incidents i ON i.ward_id = w.id
            WHERE w.lga_id = ? AND w.is_active = 1
        ";
        $params = [$tenant_id, $lga_id];
        if ($ward_id > 0) {
            $sql .= " AND w.id = ?";
            $params[] = $ward_id;
        }
        $sql .= " GROUP BY w.id, w.name, w.code, w.registered_voters ORDER BY w.name ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $wards = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($wards as $ward) {
            $rate = $ward['total_pus'] > 0 ? round(($ward['total_results'] / $ward['total_pus']) * 100, 1) : 0;
            $data_rows[] = [
                $ward['name'],
                $ward['code'],
                $ward['total_pus'],
                $ward['total_results'],
                $ward['verified_results'],
                $ward['pending_results'],
                $rate . '%',
                $ward['coordinators'],
                $ward['incidents'],
                $ward['registered_voters']
            ];
        }
        
    } elseif ($type === 'agent_report') {
        $headers = ['Agent', 'Email', 'Phone', 'PU', 'Ward', 'Status', 'Total Results', 'Verified Results', 'Pending Results', 'Incidents'];
        
        $sql = "
            SELECT 
                CONCAT(u.first_name, ' ', u.last_name) as name,
                u.email,
                u.phone,
                pu.name as pu_name,
                w.name as ward_name,
                u.status,
                COUNT(ra.id) as total_results,
                COUNT(CASE WHEN ra.status IN ('verified', 'approved') THEN 1 END) as verified_results,
                COUNT(CASE WHEN ra.status = 'pending' THEN 1 END) as pending_results,
                COUNT(i.id) as incidents
            FROM users u
            JOIN roles r ON u.role_id = r.id
            LEFT JOIN polling_units pu ON u.pu_id = pu.id
            LEFT JOIN wards w ON pu.ward_id = w.id
            LEFT JOIN results_ec8a ra ON ra.agent_id = u.id
            LEFT JOIN incidents i ON i.reporter_id = u.id
            WHERE u.tenant_id = ? AND u.lga_id = ?
            AND r.level = 'pu_agent'
            AND u.deleted_at IS NULL
        ";
        $params = [$tenant_id, $lga_id];
        if ($ward_id > 0) {
            $sql .= " AND w.id = ?";
            $params[] = $ward_id;
        }
        $sql .= " GROUP BY u.id, u.first_name, u.last_name, u.email, u.phone, pu.name, w.name, u.status
                  ORDER BY u.first_name ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($agents as $agent) {
            $data_rows[] = [
                $agent['name'],
                $agent['email'] ?? 'N/A',
                $agent['phone'] ?? 'N/A',
                $agent['pu_name'] ?? 'N/A',
                $agent['ward_name'] ?? 'N/A',
                ucfirst($agent['status']),
                $agent['total_results'] ?? 0,
                $agent['verified_results'] ?? 0,
                $agent['pending_results'] ?? 0,
                $agent['incidents'] ?? 0
            ];
        }
    }
} catch (Exception $e) {
    $headers = ['Error'];
    $data_rows[] = [$e->getMessage()];
    error_log("Excel Export Error: " . $e->getMessage());
}

fputcsv($output, $headers);
foreach ($data_rows as $row) {
    fputcsv($output, $row);
}
fclose($output);
exit;
?>