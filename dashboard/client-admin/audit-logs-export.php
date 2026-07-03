<?php
// ============================================================
// AUDIT LOGS EXPORT - CLIENT ADMIN (PROFESSIONAL UI)
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Start session and check login
SessionManager::start();

// Redirect if not logged in
if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

// Check role - only client_admin can access this page
if (SessionManager::get('role_level') !== 'client_admin') {
    header('Location: ../client-admin/');
    exit();
}

// Get database connection
$db = getDB();

// Get user info
$user_id = SessionManager::get('user_id');
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');
$tenant_id = SessionManager::get('tenant_id');

// ============================================================
// GET PARAMETERS
// ============================================================
$format = isset($_GET['format']) ? trim($_GET['format']) : 'pdf';
$action_filter = isset($_GET['action']) ? trim($_GET['action']) : '';
$severity_filter = isset($_GET['severity']) ? trim($_GET['severity']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ============================================================
// BUILD QUERY
// ============================================================
$where_conditions = ["al.tenant_id = ?"];
$params = [$tenant_id];

if (!empty($search)) {
    $where_conditions[] = "(al.action LIKE ? OR al.entity_type LIKE ? OR al.user_agent LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($action_filter)) {
    $where_conditions[] = "al.action = ?";
    $params[] = $action_filter;
}

if (!empty($severity_filter)) {
    $where_conditions[] = "al.severity = ?";
    $params[] = $severity_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(al.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(al.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Fetch logs
$sql = "
    SELECT al.*, 
           u.full_name as user_name, u.email as user_email
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    $where_clause
    ORDER BY al.created_at DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// ============================================================
// CHECK IF DATA EXISTS
// ============================================================
if (empty($logs)) {
    $_SESSION['export_error'] = 'No audit logs found for the selected criteria.';
    header('Location: audit-logs.php');
    exit();
}

// ============================================================
// GENERATE FILENAME
// ============================================================
$filename = 'audit_logs_' . date('Y-m-d_H-i');

// ============================================================
// EXPORT BASED ON FORMAT
// ============================================================
if ($format === 'pdf') {
    // ============================================================
    // PDF EXPORT
    // ============================================================
    try {
        require_once '../../includes/vendor/autoload.php';
        
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('5G Election Guru');
        $pdf->SetTitle('Audit Logs Report');
        $pdf->SetSubject('Audit Logs');
        
        $pdf->SetHeaderData('', 0, 'Audit Logs Report', 'Generated on ' . date('F j, Y g:i A'));
        $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->AddPage();
        
        // Summary
        $html = '<h1>Audit Logs Report</h1>';
        $html .= '<p><strong>Generated:</strong> ' . date('F j, Y g:i A') . '</p>';
        $html .= '<p><strong>Total Logs:</strong> ' . count($logs) . '</p>';
        
        // Filter summary
        $filters = [];
        if (!empty($search)) $filters[] = 'Search: ' . $search;
        if (!empty($action_filter)) $filters[] = 'Action: ' . ucfirst($action_filter);
        if (!empty($severity_filter)) $filters[] = 'Severity: ' . ucfirst($severity_filter);
        if (!empty($date_from)) $filters[] = 'From: ' . $date_from;
        if (!empty($date_to)) $filters[] = 'To: ' . $date_to;
        if (!empty($filters)) {
            $html .= '<p><strong>Filters:</strong> ' . implode(' | ', $filters) . '</p>';
        }
        
        $html .= '<br>';
        
        // Table
        $html .= '<table border="1" cellpadding="4" style="font-size:7pt;">';
        $html .= '<thead><tr>';
        $html .= '<th style="background-color:#4472C4;color:#ffffff;width:30px;">#</th>';
        $html .= '<th style="background-color:#4472C4;color:#ffffff;">User</th>';
        $html .= '<th style="background-color:#4472C4;color:#ffffff;">Action</th>';
        $html .= '<th style="background-color:#4472C4;color:#ffffff;">Entity</th>';
        $html .= '<th style="background-color:#4472C4;color:#ffffff;">Description</th>';
        $html .= '<th style="background-color:#4472C4;color:#ffffff;">Severity</th>';
        $html .= '<th style="background-color:#4472C4;color:#ffffff;">IP Address</th>';
        $html .= '<th style="background-color:#4472C4;color:#ffffff;">Date/Time</th>';
        $html .= '</tr></thead><tbody>';
        
        foreach ($logs as $index => $log) {
            $html .= '<tr>';
            $html .= '<td>' . ($index + 1) . '</td>';
            $html .= '<td>' . htmlspecialchars($log['user_name'] ?? 'System') . '</td>';
            $html .= '<td>' . ucfirst($log['action']) . '</td>';
            $html .= '<td>' . htmlspecialchars($log['entity_type'] ?? 'N/A') . '</td>';
            $html .= '<td>' . htmlspecialchars(substr($log['description'] ?? '', 0, 100)) . '</td>';
            $html .= '<td>' . ucfirst($log['severity']) . '</td>';
            $html .= '<td>' . htmlspecialchars($log['ip_address'] ?? 'N/A') . '</td>';
            $html .= '<td>' . date('M j, Y g:i A', strtotime($log['created_at'])) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Output PDF
        $pdf->Output($filename . '.pdf', 'D');
        exit();
        
    } catch (Exception $e) {
        $_SESSION['export_error'] = 'PDF Export Error: ' . $e->getMessage();
        header('Location: audit-logs.php');
        exit();
    }
    
} elseif ($format === 'excel') {
    // ============================================================
    // EXCEL EXPORT
    // ============================================================
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo '<html><head><meta charset="UTF-8">';
    echo '<style>
        th { background-color: #4472C4; color: #ffffff; font-weight: bold; padding: 8px; }
        td { padding: 6px; }
        .severity-info { color: #1E40AF; }
        .severity-warning { color: #92400E; }
        .severity-error { color: #991B1B; }
        .severity-critical { color: #991B1B; font-weight: bold; }
    </style>';
    echo '</head><body>';
    
    echo '<h1>Audit Logs Report</h1>';
    echo '<p><strong>Generated:</strong> ' . date('F j, Y g:i A') . '</p>';
    echo '<p><strong>Total Logs:</strong> ' . count($logs) . '</p>';
    
    // Filters
    $filters = [];
    if (!empty($search)) $filters[] = 'Search: ' . $search;
    if (!empty($action_filter)) $filters[] = 'Action: ' . ucfirst($action_filter);
    if (!empty($severity_filter)) $filters[] = 'Severity: ' . ucfirst($severity_filter);
    if (!empty($date_from)) $filters[] = 'From: ' . $date_from;
    if (!empty($date_to)) $filters[] = 'To: ' . $date_to;
    if (!empty($filters)) {
        echo '<p><strong>Filters:</strong> ' . implode(' | ', $filters) . '</p>';
    }
    
    echo '<br>';
    echo '<table border="1" cellpadding="4">';
    echo '<thead><tr>';
    echo '<th>#</th>';
    echo '<th>User</th>';
    echo '<th>Action</th>';
    echo '<th>Entity</th>';
    echo '<th>Description</th>';
    echo '<th>Severity</th>';
    echo '<th>IP Address</th>';
    echo '<th>Date/Time</th>';
    echo '</tr></thead><tbody>';
    
    foreach ($logs as $index => $log) {
        echo '<tr>';
        echo '<td>' . ($index + 1) . '</td>';
        echo '<td>' . htmlspecialchars($log['user_name'] ?? 'System') . '</td>';
        echo '<td>' . ucfirst($log['action']) . '</td>';
        echo '<td>' . htmlspecialchars($log['entity_type'] ?? 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($log['description'] ?? 'N/A') . '</td>';
        echo '<td class="severity-' . $log['severity'] . '">' . ucfirst($log['severity']) . '</td>';
        echo '<td>' . htmlspecialchars($log['ip_address'] ?? 'N/A') . '</td>';
        echo '<td>' . date('M j, Y g:i A', strtotime($log['created_at'])) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    echo '</body></html>';
    exit();
    
} elseif ($format === 'csv') {
    // ============================================================
    // CSV EXPORT
    // ============================================================
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fwrite($output, "\xEF\xBB\xBF");
    
    // Headers
    fputcsv($output, ['#', 'User', 'Action', 'Entity', 'Description', 'Severity', 'IP Address', 'Date/Time']);
    
    // Data
    foreach ($logs as $index => $log) {
        fputcsv($output, [
            $index + 1,
            $log['user_name'] ?? 'System',
            ucfirst($log['action']),
            $log['entity_type'] ?? 'N/A',
            $log['description'] ?? 'N/A',
            ucfirst($log['severity']),
            $log['ip_address'] ?? 'N/A',
            date('M j, Y g:i A', strtotime($log['created_at']))
        ]);
    }
    
    fclose($output);
    exit();
    
} elseif ($format === 'json') {
    // ============================================================
    // JSON EXPORT
    // ============================================================
    header('Content-Type: application/json');
    header('Content-Disposition: attachment;filename="' . $filename . '.json"');
    
    $export_data = [
        'generated_at' => date('Y-m-d H:i:s'),
        'total_logs' => count($logs),
        'filters' => [
            'search' => $search,
            'action' => $action_filter,
            'severity' => $severity_filter,
            'date_from' => $date_from,
            'date_to' => $date_to
        ],
        'logs' => []
    ];
    
    foreach ($logs as $log) {
        $export_data['logs'][] = [
            'id' => $log['id'],
            'user' => $log['user_name'] ?? 'System',
            'user_email' => $log['user_email'] ?? '',
            'action' => $log['action'],
            'entity_type' => $log['entity_type'],
            'entity_id' => $log['entity_id'],
            'description' => $log['description'],
            'severity' => $log['severity'],
            'ip_address' => $log['ip_address'],
            'device_id' => $log['device_id'],
            'created_at' => $log['created_at']
        ];
    }
    
    echo json_encode($export_data, JSON_PRETTY_PRINT);
    exit();
    
} else {
    // Invalid format
    header('Location: audit-logs.php');
    exit();
}
?>