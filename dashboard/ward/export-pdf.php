<?php
// ============================================================
// WARD COORDINATOR - EXPORT AS EXCEL (CSV format)
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

$user_name = SessionManager::get('user_name', 'Ward Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$ward_id = SessionManager::get('ward_id');

if (empty($ward_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT ward_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['ward_id'])) {
            $ward_id = $user['ward_id'];
            SessionManager::set('ward_id', $ward_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching ward_id: " . $e->getMessage());
    }
}

$db = getDB();

// Get parameters
$type = isset($_GET['type']) ? $_GET['type'] : 'ward_report';
$period = isset($_GET['period']) ? $_GET['period'] : 'month';
$pu_id = isset($_GET['pu_id']) ? (int)$_GET['pu_id'] : 0;

// Get ward name
$ward_name = 'Ward';
try {
    if ($ward_id) {
        $stmt = $db->prepare("SELECT name FROM wards WHERE id = ?");
        $stmt->execute([$ward_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $ward_name = $result['name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching ward: " . $e->getMessage());
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $type . '_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

$headers = [];
$data_rows = [];

try {
    if ($type === 'ward_report') {
        $headers = ['Polling Unit', 'Code', 'Voters', 'Agents', 'Results', 'Verified', 'Pending', 'Reporting Rate', 'Incidents'];
        
        $stmt = $db->prepare("
            SELECT 
                pu.name,
                pu.code,
                pu.registered_voters,
                COUNT(DISTINCT u.id) as agents,
                COUNT(DISTINCT r.id) as total_results,
                COUNT(DISTINCT CASE WHEN r.status IN ('verified', 'approved') THEN r.id END) as verified_results,
                COUNT(DISTINCT CASE WHEN r.status = 'pending' THEN r.id END) as pending_results,
                COUNT(DISTINCT i.id) as incidents
            FROM polling_units pu
            LEFT JOIN users u ON u.pu_id = pu.id AND u.status = 'active'
            LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
            LEFT JOIN incidents i ON i.pu_id = pu.id
            WHERE pu.ward_id = ? AND pu.is_active = 1
            GROUP BY pu.id, pu.name, pu.code, pu.registered_voters
            ORDER BY pu.name ASC
        ");
        $stmt->execute([$tenant_id, $ward_id]);
        $pus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($pus as $pu) {
            $rate = $pu['registered_voters'] > 0 ? round(($pu['total_results'] / max(1, $pu['registered_voters'])) * 100, 1) : 0;
            $data_rows[] = [
                $pu['name'],
                $pu['code'],
                $pu['registered_voters'],
                $pu['agents'],
                $pu['total_results'],
                $pu['verified_results'],
                $pu['pending_results'],
                $rate . '%',
                $pu['incidents']
            ];
        }
        
    } elseif ($type === 'agent_performance') {
        $headers = ['Agent', 'Email', 'PU', 'Total Results', 'Verified', 'Pending', 'Incidents'];
        
        $date_filter = "";
        switch ($period) {
            case 'today': $date_filter = "DATE(r.created_at) = CURDATE()"; break;
            case 'week': $date_filter = "r.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"; break;
            case 'month': $date_filter = "r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"; break;
            case 'quarter': $date_filter = "r.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)"; break;
            default: $date_filter = "1=1";
        }
        
        $sql = "
            SELECT 
                CONCAT(u.first_name, ' ', u.last_name) as name,
                u.email,
                pu.name as pu_name,
                COUNT(r.id) as total_results,
                COUNT(CASE WHEN r.status IN ('verified', 'approved') THEN 1 END) as verified_results,
                COUNT(CASE WHEN r.status = 'pending' THEN 1 END) as pending_results,
                COUNT(i.id) as incidents
            FROM users u
            JOIN roles r ON u.role_id = r.id
            LEFT JOIN polling_units pu ON u.pu_id = pu.id
            LEFT JOIN results_ec8a ra ON ra.agent_id = u.id AND ra.tenant_id = ? AND $date_filter
            LEFT JOIN incidents i ON i.reporter_id = u.id AND i.tenant_id = ? AND $date_filter
            WHERE u.tenant_id = ?
            AND u.ward_id = ?
            AND u.deleted_at IS NULL
            AND r.level = 'pu_agent'
        ";
        $params = [$tenant_id, $tenant_id, $tenant_id, $ward_id];
        if ($pu_id > 0) {
            $sql .= " AND u.pu_id = ?";
            $params[] = $pu_id;
        }
        $sql .= " GROUP BY u.id, u.first_name, u.last_name, u.email, pu.name
                  ORDER BY verified_results DESC, total_results DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($agents as $agent) {
            $data_rows[] = [
                $agent['name'],
                $agent['email'] ?? 'N/A',
                $agent['pu_name'] ?? 'N/A',
                $agent['total_results'] ?? 0,
                $agent['verified_results'] ?? 0,
                $agent['pending_results'] ?? 0,
                $agent['incidents'] ?? 0
            ];
        }
        
    } elseif ($type === 'incident_report') {
        $headers = ['ID', 'Type', 'Title', 'PU', 'Severity', 'Status', 'Reported By', 'Reported Date', 'Resolved Date'];
        
        $date_filter = "";
        switch ($period) {
            case 'today': $date_filter = "DATE(i.created_at) = CURDATE()"; break;
            case 'week': $date_filter = "i.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"; break;
            case 'month': $date_filter = "i.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"; break;
            case 'quarter': $date_filter = "i.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)"; break;
            default: $date_filter = "1=1";
        }
        
        $sql = "
            SELECT 
                i.id,
                i.incident_type,
                i.title,
                pu.name as pu_name,
                i.severity,
                i.status,
                CONCAT(u.first_name, ' ', u.last_name) as reporter,
                i.created_at,
                i.resolved_at
            FROM incidents i
            LEFT JOIN users u ON i.reporter_id = u.id
            LEFT JOIN polling_units pu ON i.pu_id = pu.id
            WHERE i.tenant_id = ? AND i.ward_id = ? AND $date_filter
        ";
        $params = [$tenant_id, $ward_id];
        if ($pu_id > 0) {
            $sql .= " AND i.pu_id = ?";
            $params[] = $pu_id;
        }
        $sql .= " ORDER BY i.created_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $incident_types = [
            'violence' => 'Violence',
            'intimidation' => 'Intimidation',
            'ballot_stuffing' => 'Ballot Stuffing',
            'vote_buying' => 'Vote Buying',
            'voter_suppression' => 'Voter Suppression',
            'material_shortage' => 'Material Shortage',
            'delay' => 'Delay',
            'technical_issue' => 'Technical Issue',
            'other' => 'Other',
            'panic_button' => 'Panic Button'
        ];
        
        foreach ($incidents as $incident) {
            $data_rows[] = [
                $incident['id'],
                $incident_types[$incident['incident_type']] ?? ucfirst($incident['incident_type']),
                $incident['title'],
                $incident['pu_name'] ?? 'N/A',
                ucfirst($incident['severity']),
                ucfirst(str_replace('_', ' ', $incident['status'])),
                $incident['reporter'] ?? 'Unknown',
                date('Y-m-d H:i', strtotime($incident['created_at'])),
                $incident['resolved_at'] ? date('Y-m-d H:i', strtotime($incident['resolved_at'])) : ''
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