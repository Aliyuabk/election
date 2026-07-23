<?php
// ============================================================
// WARD COORDINATOR - EXPORT PDF
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
// GET REPORT TYPE AND PARAMETERS
// ============================================================
$report_type = isset($_GET['type']) ? $_GET['type'] : 'ward';
$export_format = isset($_GET['format']) ? $_GET['format'] : 'pdf';

// ============================================================
// FETCH DATA FOR REPORT
// ============================================================
$ward_name = 'Ward';
$lga_name = 'LGA';
$state_name = 'State';
$data = [];

try {
    // Fetch ward details
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
    
    // Fetch report data based on type
    switch ($report_type) {
        case 'ward':
            // Ward summary report
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
            $data['summary'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // PU list
            $stmt = $db->prepare("
                SELECT 
                    pu.name,
                    pu.code,
                    pu.registered_voters,
                    COUNT(DISTINCT u.id) as agents,
                    COUNT(DISTINCT r.id) as submissions,
                    SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) as verified
                FROM polling_units pu
                LEFT JOIN users u ON u.pu_id = pu.id AND u.deleted_at IS NULL
                LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
                WHERE pu.ward_id = ? AND pu.is_active = 1
                GROUP BY pu.id, pu.name, pu.code, pu.registered_voters
                ORDER BY pu.name ASC
            ");
            $stmt->execute([$tenant_id, $ward_id]);
            $data['pus'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'incident':
            // Incident report
            $stmt = $db->prepare("
                SELECT 
                    incident_type,
                    severity,
                    status,
                    COUNT(*) as count,
                    DATE(created_at) as date
                FROM incidents
                WHERE ward_id = ?
                GROUP BY incident_type, severity, status, DATE(created_at)
                ORDER BY created_at DESC
                LIMIT 100
            ");
            $stmt->execute([$ward_id]);
            $data['incidents'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'agent':
            // Agent performance report
            $stmt = $db->prepare("
                SELECT 
                    u.full_name,
                    u.user_code,
                    u.email,
                    u.phone,
                    pu.name as pu_name,
                    COUNT(DISTINCT r.id) as submissions,
                    SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) as verified,
                    SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending
                FROM users u
                LEFT JOIN polling_units pu ON u.pu_id = pu.id
                LEFT JOIN results_ec8a r ON r.agent_id = u.id
                WHERE u.ward_id = ? AND u.deleted_at IS NULL
                AND EXISTS (SELECT 1 FROM roles rl WHERE rl.id = u.role_id AND rl.level = 'pu_agent')
                GROUP BY u.id, u.full_name, u.user_code, u.email, u.phone, pu.name
                ORDER BY submissions DESC
            ");
            $stmt->execute([$ward_id]);
            $data['agents'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'result':
            // Results report
            $stmt = $db->prepare("
                SELECT 
                    r.*,
                    u.full_name as agent_name,
                    pu.name as pu_name,
                    pu.code as pu_code
                FROM results_ec8a r
                JOIN polling_units pu ON r.pu_id = pu.id
                JOIN users u ON r.agent_id = u.id
                WHERE pu.ward_id = ?
                ORDER BY r.created_at DESC
                LIMIT 200
            ");
            $stmt->execute([$ward_id]);
            $data['results'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        default:
            // Default to ward summary
            $stmt = $db->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM polling_units WHERE ward_id = ? AND is_active = 1) as total_pus,
                    (SELECT SUM(registered_voters) FROM polling_units WHERE ward_id = ? AND is_active = 1) as total_voters,
                    (SELECT COUNT(*) FROM users WHERE ward_id = ? AND status = 'active' AND deleted_at IS NULL) as total_agents,
                    (SELECT COUNT(*) FROM results_ec8a r JOIN polling_units pu ON r.pu_id = pu.id WHERE pu.ward_id = ? AND r.status = 'verified') as verified_results
            ");
            $stmt->execute([$ward_id, $ward_id, $ward_id, $ward_id]);
            $data['summary'] = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
    }
    
} catch (Exception $e) {
    error_log("Error fetching report data: " . $e->getMessage());
}

// ============================================================
// GENERATE PDF (Using simple HTML output with print-friendly CSS)
// ============================================================
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ward Report - <?php echo htmlspecialchars($ward_name); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: Arial, sans-serif; 
            padding: 40px; 
            color: #333;
            background: white;
        }
        .header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #0F4C81;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 24px;
            color: #0F4C81;
        }
        .header .sub {
            color: #666;
            font-size: 14px;
            margin-top: 4px;
        }
        .header .date {
            color: #999;
            font-size: 12px;
            margin-top: 4px;
        }
        .section {
            margin-bottom: 24px;
        }
        .section h2 {
            font-size: 16px;
            color: #0F4C81;
            border-bottom: 1px solid #ddd;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 16px;
        }
        .stat-box {
            background: #f5f7fa;
            padding: 12px;
            border-radius: 6px;
            text-align: center;
        }
        .stat-box .number {
            font-size: 20px;
            font-weight: 700;
            color: #0F4C81;
        }
        .stat-box .label {
            font-size: 11px;
            color: #666;
            margin-top: 2px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-top: 8px;
        }
        table th {
            background: #f0f2f5;
            padding: 8px 10px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #ddd;
        }
        table td {
            padding: 6px 10px;
            border-bottom: 1px solid #eee;
        }
        table tr:nth-child(even) td {
            background: #fafafa;
        }
        .footer {
            margin-top: 30px;
            padding-top: 16px;
            border-top: 1px solid #ddd;
            font-size: 11px;
            color: #999;
            text-align: center;
        }
        .status-badge {
            display: inline-block;
            padding: 1px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 500;
        }
        .status-badge.verified { background: #d1fae5; color: #065f46; }
        .status-badge.pending { background: #fef3c7; color: #92400e; }
        .status-badge.rejected { background: #fee2e2; color: #991b1b; }
        .status-badge.flagged { background: #fef3c7; color: #92400e; }
        
        @media print {
            body { padding: 20px; }
            .no-print { display: none; }
            table { page-break-inside: avoid; }
            .stat-box { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>5G Election Guru - Ward Report</h1>
        <div class="sub"><?php echo htmlspecialchars($ward_name); ?> Ward • <?php echo htmlspecialchars($lga_name); ?> LGA • <?php echo htmlspecialchars($state_name); ?> State</div>
        <div class="date">Generated: <?php echo date('F d, Y H:i:s'); ?></div>
    </div>

    <?php if (isset($data['summary'])): ?>
    <div class="section">
        <h2>Summary Statistics</h2>
        <div class="stats-grid">
            <div class="stat-box">
                <div class="number"><?php echo number_format($data['summary']['total_pus'] ?? 0); ?></div>
                <div class="label">Polling Units</div>
            </div>
            <div class="stat-box">
                <div class="number"><?php echo number_format($data['summary']['total_voters'] ?? 0); ?></div>
                <div class="label">Registered Voters</div>
            </div>
            <div class="stat-box">
                <div class="number"><?php echo number_format($data['summary']['total_agents'] ?? 0); ?></div>
                <div class="label">Active Agents</div>
            </div>
            <div class="stat-box">
                <div class="number"><?php echo number_format($data['summary']['verified_results'] ?? 0); ?></div>
                <div class="label">Verified Results</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (isset($data['pus']) && !empty($data['pus'])): ?>
    <div class="section">
        <h2>Polling Unit Performance</h2>
        <table>
            <thead>
                <tr>
                    <th>PU Name</th>
                    <th>Code</th>
                    <th>Voters</th>
                    <th>Agents</th>
                    <th>Submissions</th>
                    <th>Verified</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['pus'] as $pu): ?>
                <tr>
                    <td><?php echo htmlspecialchars($pu['name']); ?></td>
                    <td><?php echo htmlspecialchars($pu['code']); ?></td>
                    <td><?php echo number_format($pu['registered_voters'] ?? 0); ?></td>
                    <td><?php echo number_format($pu['agents'] ?? 0); ?></td>
                    <td><?php echo number_format($pu['submissions'] ?? 0); ?></td>
                    <td><?php echo number_format($pu['verified'] ?? 0); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (isset($data['agents']) && !empty($data['agents'])): ?>
    <div class="section">
        <h2>Agent Performance</h2>
        <table>
            <thead>
                <tr>
                    <th>Agent Name</th>
                    <th>Code</th>
                    <th>PU</th>
                    <th>Submissions</th>
                    <th>Verified</th>
                    <th>Pending</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['agents'] as $agent): ?>
                <tr>
                    <td><?php echo htmlspecialchars($agent['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($agent['user_code']); ?></td>
                    <td><?php echo htmlspecialchars($agent['pu_name'] ?? 'Unassigned'); ?></td>
                    <td><?php echo number_format($agent['submissions'] ?? 0); ?></td>
                    <td><?php echo number_format($agent['verified'] ?? 0); ?></td>
                    <td><?php echo number_format($agent['pending'] ?? 0); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (isset($data['incidents']) && !empty($data['incidents'])): ?>
    <div class="section">
        <h2>Incident Report</h2>
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Severity</th>
                    <th>Status</th>
                    <th>Count</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['incidents'] as $inc): ?>
                <tr>
                    <td><?php echo ucfirst(str_replace('_', ' ', $inc['incident_type'] ?? 'Unknown')); ?></td>
                    <td><?php echo ucfirst($inc['severity'] ?? 'Medium'); ?></td>
                    <td><?php echo ucfirst($inc['status'] ?? 'Reported'); ?></td>
                    <td><?php echo number_format($inc['count'] ?? 0); ?></td>
                    <td><?php echo date('M d, Y', strtotime($inc['date'] ?? 'now')); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (isset($data['results']) && !empty($data['results'])): ?>
    <div class="section">
        <h2>Recent Results</h2>
        <table>
            <thead>
                <tr>
                    <th>PU</th>
                    <th>Agent</th>
                    <th>Valid Votes</th>
                    <th>Total Votes</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['results'] as $result): ?>
                <tr>
                    <td><?php echo htmlspecialchars($result['pu_name']); ?></td>
                    <td><?php echo htmlspecialchars($result['agent_name']); ?></td>
                    <td><?php echo number_format($result['valid_votes'] ?? 0); ?></td>
                    <td><?php echo number_format($result['total_votes_cast'] ?? 0); ?></td>
                    <td><span class="status-badge <?php echo $result['status'] ?? 'pending'; ?>"><?php echo ucfirst($result['status'] ?? 'Pending'); ?></span></td>
                    <td><?php echo date('M d, Y', strtotime($result['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="footer">
        <p>This report was generated automatically by 5G Election Guru. All data is for <?php echo htmlspecialchars($ward_name); ?> Ward.</p>
        <p>© <?php echo date('Y'); ?> 5G Election Guru. All rights reserved.</p>
    </div>

    <script>
        window.print();
    </script>
</body>
</html>