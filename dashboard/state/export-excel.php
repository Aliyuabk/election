<?php
// ============================================================
// STATE COORDINATOR - EXPORT AS EXCEL (CSV format for Excel)
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

$user_name = SessionManager::get('user_name', 'State Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$state_id = SessionManager::get('state_id');

if (empty($state_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT state_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['state_id'])) {
            $state_id = $user['state_id'];
            SessionManager::set('state_id', $state_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching state_id: " . $e->getMessage());
    }
}

$db = getDB();

// Get parameters
$type = isset($_GET['type']) ? $_GET['type'] : 'state_report';
$election_id = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;
$lga_id = isset($_GET['lga_id']) ? (int)$_GET['lga_id'] : 0;

// Get state name
$state_name = 'Unknown State';
if (!empty($state_id)) {
    $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
    $stmt->execute([$state_id]);
    $state = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_name = $state['name'] ?? 'Unknown State';
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $type . '_' . date('Y-m-d') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add header row based on type
$headers = [];
$data_rows = [];

try {
    switch ($type) {
        case 'state_report':
            $headers = ['LGA', 'Wards', 'Polling Units', 'Results Submitted', 'Verified Results', 'Pending Results', 'Reporting Rate', 'Coordinators', 'Incidents', 'Status'];
            
            $sql = "
                SELECT 
                    l.name as lga_name,
                    COUNT(DISTINCT w.id) as wards,
                    COUNT(DISTINCT pu.id) as pus,
                    COUNT(DISTINCT r.id) as results,
                    COUNT(DISTINCT CASE WHEN r.status IN ('verified', 'approved') THEN r.id END) as verified,
                    COUNT(DISTINCT CASE WHEN r.status = 'pending' THEN r.id END) as pending,
                    COUNT(DISTINCT u.id) as coordinators,
                    COUNT(DISTINCT i.id) as incidents
                FROM lgas l
                LEFT JOIN wards w ON w.lga_id = l.id AND w.is_active = 1
                LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
                LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
                LEFT JOIN users u ON u.lga_id = l.id AND u.status = 'active'
                LEFT JOIN incidents i ON i.lga_id = l.id
                WHERE l.state_id = ? AND l.is_active = 1
                GROUP BY l.id, l.name
                ORDER BY l.name ASC
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute([$tenant_id, $state_id]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($results as $row) {
                $rate = $row['pus'] > 0 ? round(($row['results'] / $row['pus']) * 100, 1) : 0;
                $status = $rate >= 70 ? 'High' : ($rate >= 40 ? 'Medium' : 'Low');
                $data_rows[] = [
                    $row['lga_name'],
                    $row['wards'],
                    $row['pus'],
                    $row['results'],
                    $row['verified'],
                    $row['pending'],
                    $rate . '%',
                    $row['coordinators'],
                    $row['incidents'],
                    $status
                ];
            }
            break;
            
        case 'election_report':
            $election_name = 'All Elections';
            if ($election_id > 0) {
                $stmt = $db->prepare("SELECT name FROM elections WHERE id = ?");
                $stmt->execute([$election_id]);
                $e = $stmt->fetch(PDO::FETCH_ASSOC);
                $election_name = $e['name'] ?? 'All Elections';
            }
            
            $headers = ['LGA', 'Polling Units', 'Results Submitted', 'Verified', 'Approved', 'Rejected', 'Valid Votes', 'Reporting Rate'];
            
            $sql = "
                SELECT 
                    l.name as lga_name,
                    COUNT(DISTINCT pu.id) as pus,
                    COUNT(DISTINCT r.id) as submitted,
                    COUNT(DISTINCT CASE WHEN r.status IN ('verified', 'approved') THEN r.id END) as verified,
                    COUNT(DISTINCT CASE WHEN r.status = 'approved' THEN r.id END) as approved,
                    COUNT(DISTINCT CASE WHEN r.status = 'rejected' THEN r.id END) as rejected,
                    SUM(r.valid_votes) as valid_votes
                FROM lgas l
                LEFT JOIN wards w ON w.lga_id = l.id AND w.is_active = 1
                LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
                LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.election_id = ? AND r.tenant_id = ?
                WHERE l.state_id = ? AND l.is_active = 1
                GROUP BY l.id, l.name
                ORDER BY l.name ASC
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute([$election_id, $tenant_id, $state_id]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($results as $row) {
                $rate = $row['pus'] > 0 ? round(($row['submitted'] / $row['pus']) * 100, 1) : 0;
                $data_rows[] = [
                    $row['lga_name'],
                    $row['pus'],
                    $row['submitted'],
                    $row['verified'],
                    $row['approved'],
                    $row['rejected'],
                    $row['valid_votes'],
                    $rate . '%'
                ];
            }
            break;
            
        case 'incident_report':
            $headers = ['ID', 'Type', 'Title', 'LGA', 'Severity', 'Status', 'Reported By', 'Reported Date'];
            
            $sql = "
                SELECT 
                    i.id,
                    i.incident_type,
                    i.title,
                    l.name as lga_name,
                    i.severity,
                    i.status,
                    CONCAT(u.first_name, ' ', u.last_name) as reporter,
                    i.created_at
                FROM incidents i
                LEFT JOIN lgas l ON i.lga_id = l.id
                LEFT JOIN users u ON i.reporter_id = u.id
                WHERE i.tenant_id = ? AND i.state_id = ?
            ";
            $params = [$tenant_id, $state_id];
            if ($election_id > 0) {
                $sql .= " AND i.election_id = ?";
                $params[] = $election_id;
            }
            $sql .= " ORDER BY i.created_at DESC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
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
            
            foreach ($results as $row) {
                $data_rows[] = [
                    $row['id'],
                    $incident_types[$row['incident_type']] ?? ucfirst($row['incident_type']),
                    $row['title'],
                    $row['lga_name'] ?? 'N/A',
                    ucfirst($row['severity']),
                    ucfirst(str_replace('_', ' ', $row['status'])),
                    $row['reporter'] ?? 'Unknown',
                    date('Y-m-d H:i', strtotime($row['created_at']))
                ];
            }
            break;
            
        case 'coordinators_report':
            $headers = ['Name', 'Email', 'Phone', 'Role', 'LGA', 'Ward', 'PU', 'Status', 'Last Login'];
            
            $sql = "
                SELECT 
                    CONCAT(u.first_name, ' ', u.last_name) as name,
                    u.email,
                    u.phone,
                    r.level as role,
                    l.name as lga_name,
                    w.name as ward_name,
                    pu.name as pu_name,
                    u.status,
                    u.last_login_at
                FROM users u
                JOIN roles r ON u.role_id = r.id
                LEFT JOIN lgas l ON u.lga_id = l.id
                LEFT JOIN wards w ON u.ward_id = w.id
                LEFT JOIN polling_units pu ON u.pu_id = pu.id
                WHERE u.tenant_id = ? AND u.state_id = ? AND u.deleted_at IS NULL
                AND r.level IN ('lga', 'ward', 'pu_agent', 'party_agent')
            ";
            $params = [$tenant_id, $state_id];
            if ($lga_id > 0) {
                $sql .= " AND (u.lga_id = ? OR l.id = ?)";
                $params[] = $lga_id;
                $params[] = $lga_id;
            }
            $sql .= " ORDER BY r.level ASC, l.name ASC, u.first_name ASC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $role_labels = [
                'lga' => 'LGA Coordinator',
                'ward' => 'Ward Coordinator',
                'pu_agent' => 'PU Agent',
                'party_agent' => 'Party Agent'
            ];
            
            foreach ($results as $row) {
                $location = $row['pu_name'] ?? $row['ward_name'] ?? $row['lga_name'] ?? 'N/A';
                $data_rows[] = [
                    $row['name'],
                    $row['email'] ?? 'N/A',
                    $row['phone'] ?? 'N/A',
                    $role_labels[$row['role']] ?? ucfirst($row['role']),
                    $row['lga_name'] ?? 'N/A',
                    $row['ward_name'] ?? 'N/A',
                    $row['pu_name'] ?? 'N/A',
                    ucfirst($row['status']),
                    $row['last_login_at'] ? date('Y-m-d H:i', strtotime($row['last_login_at'])) : 'Never'
                ];
            }
            break;
            
        default:
            $headers = ['Error', 'Invalid report type'];
            $data_rows[] = ['The requested report type is not available.'];
    }
} catch (Exception $e) {
    $headers = ['Error'];
    $data_rows[] = [$e->getMessage()];
    error_log("Excel Export Error: " . $e->getMessage());
}

// Write headers
fputcsv($output, $headers);

// Write data rows
foreach ($data_rows as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit;
?>