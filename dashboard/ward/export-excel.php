<?php
// ============================================================
// WARD COORDINATOR - EXPORT EXCEL
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Start session
SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

// Only Ward coordinator can access
if (SessionManager::get('role_level') !== 'ward') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$ward_id = SessionManager::get('ward_id');
$tenant_id = SessionManager::get('tenant_id');

// If ward_id is not set in session, try to get it from user record
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

// ============================================================
// GET REPORT TYPE
// ============================================================
$report_type = isset($_GET['type']) ? $_GET['type'] : 'ward';

// ============================================================
// FETCH WARD DETAILS
// ============================================================
$ward_name = 'Ward';
$lga_name = 'LGA';
$state_name = 'State';

try {
    if ($ward_id) {
        $stmt = $db->prepare("
            SELECT 
                w.name as ward_name,
                l.name as lga_name,
                s.name as state_name
            FROM wards w
            JOIN lgas l ON w.lga_id = l.id
            JOIN states s ON l.state_id = s.id
            WHERE w.id = ?
        ");
        $stmt->execute([$ward_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $ward_name = $result['ward_name'];
            $lga_name = $result['lga_name'];
            $state_name = $result['state_name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching ward details: " . $e->getMessage());
}

// ============================================================
// FETCH DATA FOR EXPORT
// ============================================================
$data = [];
$headers = [];
$filename = '';

try {
    switch ($report_type) {
        case 'ward':
            // Ward summary
            $filename = "ward_report_{$ward_name}_" . date('Y-m-d') . ".csv";
            $headers = ['Metric', 'Value'];
            
            $stmt = $db->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM polling_units WHERE ward_id = ? AND is_active = 1) as total_pus,
                    (SELECT SUM(registered_voters) FROM polling_units WHERE ward_id = ? AND is_active = 1) as total_voters,
                    (SELECT COUNT(*) FROM users WHERE ward_id = ? AND status = 'active' AND deleted_at IS NULL) as total_agents,
                    (SELECT COUNT(*) FROM results_ec8a r JOIN polling_units pu ON r.pu_id = pu.id WHERE pu.ward_id = ? AND r.status = 'verified') as verified_results,
                    (SELECT COUNT(*) FROM results_ec8a r JOIN polling_units pu ON r.pu_id = pu.id WHERE pu.ward_id = ? AND r.status = 'pending') as pending_results,
                    (SELECT COUNT(*) FROM incidents WHERE ward_id = ?) as total_incidents,
                    (SELECT COUNT(*) FROM incidents WHERE ward_id = ? AND status = 'resolved') as resolved_incidents
            ");
            $stmt->execute([$ward_id, $ward_id, $ward_id, $ward_id, $ward_id, $ward_id, $ward_id]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $data = [
                ['Ward Name', $ward_name],
                ['LGA', $lga_name],
                ['State', $state_name],
                ['Generated', date('Y-m-d H:i:s')],
                [''],
                ['Total Polling Units', $stats['total_pus'] ?? 0],
                ['Total Registered Voters', $stats['total_voters'] ?? 0],
                ['Active Agents', $stats['total_agents'] ?? 0],
                ['Verified Results', $stats['verified_results'] ?? 0],
                ['Pending Results', $stats['pending_results'] ?? 0],
                ['Total Incidents', $stats['total_incidents'] ?? 0],
                ['Resolved Incidents', $stats['resolved_incidents'] ?? 0]
            ];
            break;
            
        case 'pu':
            // Polling Unit report
            $filename = "pu_report_{$ward_name}_" . date('Y-m-d') . ".csv";
            $headers = ['PU Name', 'Code', 'Registered Voters', 'Agents', 'Submissions', 'Verified', 'Pending', 'Incidents'];
            
            $stmt = $db->prepare("
                SELECT 
                    pu.name,
                    pu.code,
                    pu.registered_voters,
                    COUNT(DISTINCT u.id) as agents,
                    COUNT(DISTINCT r.id) as submissions,
                    SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) as verified,
                    SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending,
                    COUNT(DISTINCT i.id) as incidents
                FROM polling_units pu
                LEFT JOIN users u ON u.pu_id = pu.id AND u.deleted_at IS NULL
                LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
                LEFT JOIN incidents i ON i.pu_id = pu.id
                WHERE pu.ward_id = ? AND pu.is_active = 1
                GROUP BY pu.id, pu.name, pu.code, pu.registered_voters
                ORDER BY pu.name ASC
            ");
            $stmt->execute([$tenant_id, $ward_id]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'agent':
            // Agent report
            $filename = "agent_report_{$ward_name}_" . date('Y-m-d') . ".csv";
            $headers = ['Agent Name', 'Code', 'Email', 'Phone', 'PU', 'Status', 'Submissions', 'Verified', 'Pending'];
            
            $stmt = $db->prepare("
                SELECT 
                    u.full_name,
                    u.user_code,
                    u.email,
                    u.phone,
                    pu.name as pu_name,
                    u.status,
                    COUNT(DISTINCT r.id) as submissions,
                    SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) as verified,
                    SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending
                FROM users u
                LEFT JOIN polling_units pu ON u.pu_id = pu.id
                LEFT JOIN results_ec8a r ON r.agent_id = u.id
                WHERE u.ward_id = ? AND u.deleted_at IS NULL
                AND EXISTS (SELECT 1 FROM roles rl WHERE rl.id = u.role_id AND rl.level = 'pu_agent')
                GROUP BY u.id, u.full_name, u.user_code, u.email, u.phone, pu.name, u.status
                ORDER BY u.full_name ASC
            ");
            $stmt->execute([$ward_id]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'incident':
            // Incident report
            $filename = "incident_report_{$ward_name}_" . date('Y-m-d') . ".csv";
            $headers = ['ID', 'Title', 'Type', 'Severity', 'Status', 'PU', 'Reported By', 'Date'];
            
            $stmt = $db->prepare("
                SELECT 
                    i.id,
                    i.title,
                    i.incident_type,
                    i.severity,
                    i.status,
                    pu.name as pu_name,
                    u.full_name as reported_by,
                    i.created_at
                FROM incidents i
                LEFT JOIN polling_units pu ON i.pu_id = pu.id
                LEFT JOIN users u ON i.reporter_id = u.id
                WHERE i.ward_id = ?
                ORDER BY i.created_at DESC
                LIMIT 500
            ");
            $stmt->execute([$ward_id]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'result':
            // Result report
            $filename = "result_report_{$ward_name}_" . date('Y-m-d') . ".csv";
            $headers = ['PU', 'Code', 'Agent', 'Valid Votes', 'Rejected Votes', 'Total Votes', 'Status', 'Date'];
            
            $stmt = $db->prepare("
                SELECT 
                    pu.name as pu_name,
                    pu.code as pu_code,
                    u.full_name as agent_name,
                    r.valid_votes,
                    r.rejected_votes,
                    r.total_votes_cast,
                    r.status,
                    r.created_at
                FROM results_ec8a r
                JOIN polling_units pu ON r.pu_id = pu.id
                JOIN users u ON r.agent_id = u.id
                WHERE pu.ward_id = ?
                ORDER BY r.created_at DESC
                LIMIT 500
            ");
            $stmt->execute([$ward_id]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        default:
            // Default to ward summary
            $filename = "ward_report_{$ward_name}_" . date('Y-m-d') . ".csv";
            $headers = ['Metric', 'Value'];
            $data = [
                ['Ward Name', $ward_name],
                ['LGA', $lga_name],
                ['State', $state_name],
                ['Generated', date('Y-m-d H:i:s')]
            ];
            break;
    }
    
} catch (Exception $e) {
    error_log("Error exporting data: " . $e->getMessage());
}

// ============================================================
// GENERATE CSV OUTPUT
// ============================================================
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Write headers
if (!empty($headers) && !empty($data)) {
    fputcsv($output, $headers);
}

// Write data
if (!empty($data)) {
    foreach ($data as $row) {
        // Check if row is associative or indexed
        if (is_array($row)) {
            // If it's a simple key-value pair (for ward summary)
            if (isset($row['Metric']) || isset($row['Value'])) {
                fputcsv($output, [$row['Metric'] ?? '', $row['Value'] ?? '']);
            } else {
                // For table data
                $row_data = [];
                foreach ($headers as $header) {
                    $key = strtolower(str_replace(' ', '_', $header));
                    $row_data[] = $row[$key] ?? $row[str_replace(' ', '_', $header)] ?? '';
                }
                fputcsv($output, $row_data);
            }
        }
    }
}

fclose($output);
exit();