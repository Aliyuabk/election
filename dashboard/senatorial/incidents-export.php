<?php
// ============================================================
// SENATORIAL COORDINATOR - EXPORT INCIDENT REPORT
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

$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');

$db = getDB();

// ============================================================
// GET PARAMETERS
// ============================================================
$format = isset($_GET['format']) ? $_GET['format'] : 'csv';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$severity_filter = isset($_GET['severity']) ? $_GET['severity'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// ============================================================
// BUILD QUERY
// ============================================================
$where_conditions = ["tenant_id = ?"];
$params = [$tenant_id];

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if (!empty($severity_filter)) {
    $where_conditions[] = "severity = ?";
    $params[] = $severity_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = implode(" AND ", $where_conditions);

// ============================================================
// GET INCIDENTS
// ============================================================
$incidents = [];
try {
    $query = "
        SELECT 
            i.*,
            u.full_name as reporter_name,
            pu.name as pu_name,
            w.name as ward_name,
            l.name as lga_name,
            v.full_name as assigned_to_name,
            r.full_name as resolved_by_name
        FROM incidents i
        LEFT JOIN users u ON i.reporter_id = u.id
        LEFT JOIN polling_units pu ON i.pu_id = pu.id
        LEFT JOIN wards w ON i.ward_id = w.id
        LEFT JOIN lgas l ON i.lga_id = l.id
        LEFT JOIN users v ON i.assigned_to = v.id
        LEFT JOIN users r ON i.resolved_by = r.id
        WHERE $where_clause
        ORDER BY i.created_at DESC
    ";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $incidents = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching incidents for export: " . $e->getMessage());
}

// ============================================================
// GENERATE CSV
// ============================================================
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="incident_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Headers
    fputcsv($output, [
        'ID',
        'Title',
        'Type',
        'Severity',
        'Status',
        'LGA',
        'Ward',
        'Polling Unit',
        'Reported By',
        'Assigned To',
        'Resolved By',
        'Description',
        'Resolution Notes',
        'Reported At',
        'Resolved At'
    ]);
    
    // Data
    foreach ($incidents as $incident) {
        fputcsv($output, [
            $incident['id'],
            $incident['title'],
            $incident['incident_type'],
            $incident['severity'],
            $incident['status'],
            $incident['lga_name'] ?? '',
            $incident['ward_name'] ?? '',
            $incident['pu_name'] ?? '',
            $incident['reporter_name'] ?? '',
            $incident['assigned_to_name'] ?? '',
            $incident['resolved_by_name'] ?? '',
            $incident['description'],
            $incident['resolution_notes'] ?? '',
            $incident['created_at'],
            $incident['resolved_at'] ?? ''
        ]);
    }
    
    fclose($output);
    exit();
}

// Redirect to incidents page if format not supported
header('Location: incidents.php');
exit();
?>