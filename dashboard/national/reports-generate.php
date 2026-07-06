<?php
// ============================================================
// NATIONAL COORDINATOR - GENERATE REPORTS
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

// Only national coordinator can access
if (SessionManager::get('role_level') !== 'national') {
    header('Location: ../client-admin/');
    exit();
}

$tenant_id = SessionManager::get('tenant_id');

// Get parameters
$report_type = isset($_GET['type']) ? $_GET['type'] : 'national';
$format = isset($_GET['format']) ? $_GET['format'] : 'pdf';
$state_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$states = isset($_GET['states']) ? $_GET['states'] : [];

if (is_string($states)) {
    $states = array_filter(explode(',', $states));
}
$states = array_map('intval', $states);

$db = getDB();

// ============================================================
// BUILD REPORT DATA BASED ON TYPE
// ============================================================
$report_data = [];
$report_title = '';
$filename = '';

switch ($report_type) {
    case 'national':
        $report_title = 'National Election Report';
        $filename = 'national_election_report';
        
        // Fetch national statistics
        try {
            // Total States
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM states WHERE is_active = 1");
            $stmt->execute();
            $total_states = $stmt->fetch();
            
            // Total LGAs
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM lgas WHERE is_active = 1");
            $stmt->execute();
            $total_lgas = $stmt->fetch();
            
            // Total PUs
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM polling_units WHERE is_active = 1");
            $stmt->execute();
            $total_pus = $stmt->fetch();
            
            // Total Results
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM results_ec8a WHERE tenant_id = ?");
            $stmt->execute([$tenant_id]);
            $total_results = $stmt->fetch();
            
            // Verified Results
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM results_ec8a WHERE tenant_id = ? AND status = 'verified'");
            $stmt->execute([$tenant_id]);
            $verified_results = $stmt->fetch();
            
            // Total Incidents
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM incidents WHERE tenant_id = ?");
            $stmt->execute([$tenant_id]);
            $total_incidents = $stmt->fetch();
            
            // Critical Incidents
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM incidents WHERE tenant_id = ? AND severity = 'critical'");
            $stmt->execute([$tenant_id]);
            $critical_incidents = $stmt->fetch();
            
            // State-wise breakdown
            $stmt = $db->prepare("
                SELECT 
                    s.name as state_name,
                    COUNT(DISTINCT l.id) as lga_count,
                    COUNT(DISTINCT pu.id) as pu_count,
                    COUNT(r.id) as result_count,
                    SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) as verified_count
                FROM states s
                LEFT JOIN lgas l ON l.state_id = s.id
                LEFT JOIN wards w ON w.lga_id = l.id
                LEFT JOIN polling_units pu ON pu.ward_id = w.id
                LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
                WHERE s.is_active = 1
                GROUP BY s.id
                ORDER BY s.name ASC
            ");
            $stmt->execute([$tenant_id]);
            $state_breakdown = $stmt->fetchAll();
            
            $report_data = [
                'generated_at' => date('Y-m-d H:i:s'),
                'total_states' => $total_states['count'] ?? 0,
                'total_lgas' => $total_lgas['count'] ?? 0,
                'total_pus' => $total_pus['count'] ?? 0,
                'total_results' => $total_results['count'] ?? 0,
                'verified_results' => $verified_results['count'] ?? 0,
                'total_incidents' => $total_incidents['count'] ?? 0,
                'critical_incidents' => $critical_incidents['count'] ?? 0,
                'state_breakdown' => $state_breakdown
            ];
        } catch (Exception $e) {
            error_log("Report generation error: " . $e->getMessage());
            $report_data = ['error' => $e->getMessage()];
        }
        break;
        
    case 'results':
        $report_title = 'Result Submission Report';
        $filename = 'result_submission_report';
        
        try {
            // EC8A Stats
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'flagged' THEN 1 ELSE 0 END) as flagged
                FROM results_ec8a
                WHERE tenant_id = ?
            ");
            $stmt->execute([$tenant_id]);
            $ec8a_stats = $stmt->fetch();
            
            // EC8B Stats
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM results_ec8b WHERE tenant_id = ?");
            $stmt->execute([$tenant_id]);
            $ec8b_count = $stmt->fetch();
            
            // EC8C Stats
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM results_ec8c WHERE tenant_id = ?");
            $stmt->execute([$tenant_id]);
            $ec8c_count = $stmt->fetch();
            
            // EC8D Stats
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM results_ec8d WHERE tenant_id = ?");
            $stmt->execute([$tenant_id]);
            $ec8d_count = $stmt->fetch();
            
            // EC8E Stats
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM results_ec8e WHERE tenant_id = ?");
            $stmt->execute([$tenant_id]);
            $ec8e_count = $stmt->fetch();
            
            $report_data = [
                'generated_at' => date('Y-m-d H:i:s'),
                'ec8a' => $ec8a_stats,
                'ec8b_count' => $ec8b_count['count'] ?? 0,
                'ec8c_count' => $ec8c_count['count'] ?? 0,
                'ec8d_count' => $ec8d_count['count'] ?? 0,
                'ec8e_count' => $ec8e_count['count'] ?? 0
            ];
        } catch (Exception $e) {
            error_log("Report generation error: " . $e->getMessage());
            $report_data = ['error' => $e->getMessage()];
        }
        break;
        
    case 'state':
        $report_title = 'State Performance Report';
        $filename = 'state_performance_report_' . $state_id;
        
        if ($state_id <= 0) {
            $report_data = ['error' => 'No state selected'];
            break;
        }
        
        try {
            // State details
            $stmt = $db->prepare("SELECT name, capital FROM states WHERE id = ?");
            $stmt->execute([$state_id]);
            $state_info = $stmt->fetch();
            
            if (!$state_info) {
                $report_data = ['error' => 'State not found'];
                break;
            }
            
            // LGA breakdown
            $stmt = $db->prepare("
                SELECT 
                    l.name as lga_name,
                    COUNT(DISTINCT pu.id) as pu_count,
                    COUNT(r.id) as result_count,
                    SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) as verified_count
                FROM lgas l
                LEFT JOIN wards w ON w.lga_id = l.id
                LEFT JOIN polling_units pu ON pu.ward_id = w.id
                LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
                WHERE l.state_id = ?
                GROUP BY l.id
                ORDER BY l.name ASC
            ");
            $stmt->execute([$tenant_id, $state_id]);
            $lga_breakdown = $stmt->fetchAll();
            
            $report_data = [
                'generated_at' => date('Y-m-d H:i:s'),
                'state_name' => $state_info['name'],
                'capital' => $state_info['capital'] ?? 'N/A',
                'lga_breakdown' => $lga_breakdown
            ];
        } catch (Exception $e) {
            error_log("Report generation error: " . $e->getMessage());
            $report_data = ['error' => $e->getMessage()];
        }
        break;
        
    default:
        $report_data = ['error' => 'Invalid report type'];
}

// ============================================================
// GENERATE OUTPUT BASED ON FORMAT
// ============================================================
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');

// Write headers
if (isset($report_data['error'])) {
    fputcsv($output, ['Error', $report_data['error']]);
    fclose($output);
    exit();
}

// Write report based on type
switch ($report_type) {
    case 'national':
        fputcsv($output, ['National Election Report']);
        fputcsv($output, ['Generated:', $report_data['generated_at']]);
        fputcsv($output, []);
        fputcsv($output, ['Summary Statistics']);
        fputcsv($output, ['Total States', $report_data['total_states']]);
        fputcsv($output, ['Total LGAs', $report_data['total_lgas']]);
        fputcsv($output, ['Total Polling Units', $report_data['total_pus']]);
        fputcsv($output, ['Total Results', $report_data['total_results']]);
        fputcsv($output, ['Verified Results', $report_data['verified_results']]);
        fputcsv($output, ['Total Incidents', $report_data['total_incidents']]);
        fputcsv($output, ['Critical Incidents', $report_data['critical_incidents']]);
        fputcsv($output, []);
        fputcsv($output, ['State Breakdown']);
        fputcsv($output, ['State', 'LGAs', 'Polling Units', 'Results', 'Verified']);
        
        foreach ($report_data['state_breakdown'] as $state) {
            fputcsv($output, [
                $state['state_name'],
                $state['lga_count'] ?? 0,
                $state['pu_count'] ?? 0,
                $state['result_count'] ?? 0,
                $state['verified_count'] ?? 0
            ]);
        }
        break;
        
    case 'results':
        fputcsv($output, ['Result Submission Report']);
        fputcsv($output, ['Generated:', $report_data['generated_at']]);
        fputcsv($output, []);
        fputcsv($output, ['EC8 Form Statistics']);
        fputcsv($output, ['EC8A - Total', $report_data['ec8a']['total'] ?? 0]);
        fputcsv($output, ['EC8A - Verified', $report_data['ec8a']['verified'] ?? 0]);
        fputcsv($output, ['EC8A - Pending', $report_data['ec8a']['pending'] ?? 0]);
        fputcsv($output, ['EC8A - Flagged', $report_data['ec8a']['flagged'] ?? 0]);
        fputcsv($output, ['EC8B - Total', $report_data['ec8b_count']]);
        fputcsv($output, ['EC8C - Total', $report_data['ec8c_count']]);
        fputcsv($output, ['EC8D - Total', $report_data['ec8d_count']]);
        fputcsv($output, ['EC8E - Total', $report_data['ec8e_count']]);
        break;
        
    case 'state':
        fputcsv($output, ['State Performance Report']);
        fputcsv($output, ['Generated:', $report_data['generated_at']]);
        fputcsv($output, []);
        fputcsv($output, ['State:', $report_data['state_name']]);
        fputcsv($output, ['Capital:', $report_data['capital']]);
        fputcsv($output, []);
        fputcsv($output, ['LGA Breakdown']);
        fputcsv($output, ['LGA', 'Polling Units', 'Results', 'Verified']);
        
        foreach ($report_data['lga_breakdown'] as $lga) {
            fputcsv($output, [
                $lga['lga_name'],
                $lga['pu_count'] ?? 0,
                $lga['result_count'] ?? 0,
                $lga['verified_count'] ?? 0
            ]);
        }
        break;
}

fclose($output);
exit();
?>