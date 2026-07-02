<?php
// ============================================================
// TENANT EXPORT - MULTI-FORMAT
// ============================================================
// Supports: CSV, Excel (XLSX), and PDF formats
// ============================================================

require_once 'includes/db.php';

// Get database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Get parameters
$search = $_GET['search'] ?? '';
$filter_plan = $_GET['plan'] ?? '';
$filter_status = $_GET['status'] ?? '';
$format = $_GET['format'] ?? 'csv';

// Build query
$query = "SELECT 
            t.id,
            t.name,
            t.slug,
            t.type,
            t.subscription_plan,
            t.subscription_status,
            t.contact_email,
            t.contact_phone,
            t.address,
            t.logo_url,
            t.max_users,
            t.max_agents,
            t.primary_color,
            t.secondary_color,
            DATE_FORMAT(t.created_at, '%Y-%m-%d %H:%i:%s') as created_at,
            DATE_FORMAT(t.updated_at, '%Y-%m-%d %H:%i:%s') as updated_at,
            DATE_FORMAT(t.subscription_end, '%Y-%m-%d') as subscription_end,
            (SELECT COUNT(*) FROM users WHERE tenant_id = t.id AND deleted_at IS NULL) as user_count,
            (SELECT COUNT(*) FROM elections WHERE tenant_id = t.id AND deleted_at IS NULL) as election_count,
            (SELECT COUNT(*) FROM subscriptions WHERE tenant_id = t.id) as subscription_count,
            (SELECT COUNT(*) FROM invoices WHERE tenant_id = t.id AND status = 'paid') as invoice_count,
            (SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE tenant_id = t.id AND status = 'paid') as total_revenue
          FROM tenants t
          WHERE t.deleted_at IS NULL";

$params = [];

// Apply filters
if ($search) {
    $query .= " AND (t.name LIKE ? OR t.slug LIKE ? OR t.contact_email LIKE ? OR t.contact_phone LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($filter_plan) {
    $query .= " AND t.subscription_plan = ?";
    $params[] = $filter_plan;
}

if ($filter_status) {
    $query .= " AND t.subscription_status = ?";
    $params[] = $filter_status;
}

$query .= " ORDER BY t.created_at DESC";

// Execute query
$stmt = $conn->prepare($query);
$stmt->execute($params);
$tenants = $stmt->fetchAll();

// Prepare data with labels
$plan_labels = ['free' => 'Free', 'basic' => 'Basic', 'standard' => 'Standard', 'premium' => 'Premium', 'enterprise' => 'Enterprise'];
$status_labels = ['trial' => 'Trial', 'active' => 'Active', 'suspended' => 'Suspended', 'expired' => 'Expired', 'cancelled' => 'Cancelled'];
$type_labels = [
    'political_party' => 'Political Party',
    'candidate' => 'Candidate',
    'ngo' => 'NGO',
    'observer_group' => 'Observer Group',
    'cso' => 'CSO',
    'research_institution' => 'Research Institution'
];

$export_data = [];
foreach ($tenants as $tenant) {
    $export_data[] = [
        'ID' => $tenant['id'],
        'Organization Name' => $tenant['name'],
        'Slug' => $tenant['slug'],
        'Type' => $type_labels[$tenant['type']] ?? ucfirst(str_replace('_', ' ', $tenant['type'])),
        'Subscription Plan' => $plan_labels[$tenant['subscription_plan']] ?? ucfirst($tenant['subscription_plan']),
        'Status' => $status_labels[$tenant['subscription_status']] ?? ucfirst($tenant['subscription_status']),
        'Contact Email' => $tenant['contact_email'] ?? '',
        'Contact Phone' => $tenant['contact_phone'] ?? '',
        'Address' => $tenant['address'] ?? '',
        'Max Users' => $tenant['max_users'],
        'Max Agents' => $tenant['max_agents'],
        'Primary Color' => $tenant['primary_color'],
        'Secondary Color' => $tenant['secondary_color'],
        'Created At' => $tenant['created_at'],
        'Updated At' => $tenant['updated_at'],
        'Subscription End' => $tenant['subscription_end'] ?? '',
        'Total Users' => $tenant['user_count'],
        'Total Elections' => $tenant['election_count'],
        'Subscriptions' => $tenant['subscription_count'],
        'Paid Invoices' => $tenant['invoice_count'],
        'Total Revenue (NGN)' => number_format($tenant['total_revenue'], 2),
        'Logo URL' => $tenant['logo_url'] ?? ''
    ];
}

// ============================================================
// EXPORT BASED ON FORMAT
// ============================================================
switch ($format) {
    case 'csv':
        exportCSV($export_data);
        break;
    case 'excel':
        exportExcel($export_data);
        break;
    case 'pdf':
        exportPDF($export_data);
        break;
    default:
        exportCSV($export_data);
}

// ============================================================
// EXPORT FUNCTIONS
// ============================================================

function exportCSV($data) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tenants_export_' . date('Y-m-d_His') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}

function exportExcel($data) {
    // Check if PHPExcel or PhpSpreadsheet is available
    if (class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        // Use PhpSpreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set headers
        if (!empty($data)) {
            $headers = array_keys($data[0]);
            foreach ($headers as $col => $header) {
                $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
                $sheet->getColumnDimensionByColumn($col + 1)->setAutoSize(true);
            }
            
            // Set data
            foreach ($data as $rowIndex => $row) {
                foreach ($row as $colIndex => $value) {
                    $sheet->setCellValueByColumnAndRow($colIndex + 1, $rowIndex + 2, $value);
                }
            }
            
            // Style header
            $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 11],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F0FE']]
            ]);
        }
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="tenants_export_' . date('Y-m-d_His') . '.xlsx"');
        $writer->save('php://output');
        exit;
    } else {
        // Fallback to CSV if PhpSpreadsheet not available
        exportCSV($data);
    }
}

function exportPDF($data) {
    // Check if TCPDF or Dompdf is available
    if (class_exists('TCPDF')) {
        // Use TCPDF
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('5G Election Guru');
        $pdf->SetAuthor('Super Admin');
        $pdf->SetTitle('Tenants Export');
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();
        
        // Set header
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Tenants Export - ' . date('Y-m-d H:i'), 0, 1, 'C');
        $pdf->Ln(5);
        
        // Set table header
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(232, 240, 254);
        
        if (!empty($data)) {
            $headers = array_keys($data[0]);
            $col_widths = array_fill(0, count($headers), 30);
            
            // Adjust column widths for better display
            $col_widths = [10, 30, 20, 20, 18, 15, 30, 18, 35, 12, 12, 15, 15, 25, 25, 18, 12, 15, 15, 15, 20, 40];
            
            // Print header
            foreach ($headers as $i => $header) {
                $pdf->Cell($col_widths[$i] ?? 25, 8, $header, 1, 0, 'C', true);
            }
            $pdf->Ln();
            
            // Print data
            $pdf->SetFont('helvetica', '', 7);
            foreach ($data as $row) {
                $i = 0;
                foreach ($row as $value) {
                    $pdf->Cell($col_widths[$i] ?? 25, 6, substr($value, 0, 20), 1, 0, 'L');
                    $i++;
                }
                $pdf->Ln();
            }
        }
        
        $pdf->Output('tenants_export_' . date('Y-m-d_His') . '.pdf', 'D');
        exit;
    } else {
        // Fallback to CSV if PDF library not available
        exportCSV($data);
    }
}

// Log the export activity
try {
    $logQuery = "INSERT INTO activity_logs (user_id, activity_type, description, ip_address, created_at) 
                 VALUES (?, 'export', 'Exported tenant data: format=' . ?, ' . $format . ', search=' . ?, plan=' . ?, status=' . ?, ?, NOW())";
    $logStmt = $conn->prepare($logQuery);
    $logStmt->execute([
        $_SESSION['user_id'] ?? 1,
        $format,
        $search,
        $filter_plan,
        $filter_status,
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);
} catch (Exception $e) {
    // Silent fail for logging
}
?>