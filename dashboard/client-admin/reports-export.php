<?php
// ============================================================
// REPORTS EXPORT - CLIENT ADMIN (PROFESSIONAL UI)
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
$report_type = isset($_GET['type']) ? trim($_GET['type']) : '';
$format = isset($_GET['format']) ? trim($_GET['format']) : 'pdf';

if (empty($report_type)) {
    header('Location: reports.php');
    exit();
}

// ============================================================
// FETCH DATA BASED ON REPORT TYPE
// ============================================================
$data = [];
$headers = [];
$title = '';

try {
    switch ($report_type) {
        case 'users':
            $title = 'User Report';
            $stmt = $db->prepare("
                SELECT u.id, u.user_code, u.first_name, u.last_name, u.email, u.phone,
                       r.name as role_name, u.status, u.last_login_at, u.created_at
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.tenant_id = ?
                ORDER BY u.created_at DESC
            ");
            $stmt->execute([$tenant_id]);
            $data = $stmt->fetchAll();
            $headers = ['ID', 'User Code', 'First Name', 'Last Name', 'Email', 'Phone', 'Role', 'Status', 'Last Login', 'Created At'];
            break;
            
        case 'agents':
            $title = 'Agent Report';
            $stmt = $db->prepare("
                SELECT u.id, u.user_code, u.first_name, u.last_name, u.email, u.phone,
                       r.name as role_name, u.status,
                       (SELECT COUNT(*) FROM agent_assignments WHERE user_id = u.id AND status IN ('active', 'pending')) as assignments
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.tenant_id = ? AND r.level IN ('pu_agent', 'party_agent', 'volunteer', 'observer')
                ORDER BY u.created_at DESC
            ");
            $stmt->execute([$tenant_id]);
            $data = $stmt->fetchAll();
            $headers = ['ID', 'User Code', 'First Name', 'Last Name', 'Email', 'Phone', 'Role', 'Status', 'Active Assignments'];
            break;
            
        case 'elections':
            $title = 'Election Report';
            $stmt = $db->prepare("
                SELECT e.id, e.name, e.type, e.cycle, e.election_date, e.status,
                       u.full_name as created_by
                FROM elections e
                LEFT JOIN users u ON e.created_by = u.id
                WHERE e.tenant_id = ? AND e.deleted_at IS NULL
                ORDER BY e.election_date DESC
            ");
            $stmt->execute([$tenant_id]);
            $data = $stmt->fetchAll();
            $headers = ['ID', 'Name', 'Type', 'Cycle', 'Election Date', 'Status', 'Created By'];
            break;
            
        case 'incidents':
            $title = 'Incident Report';
            $stmt = $db->prepare("
                SELECT i.id, i.title, i.incident_type, i.severity, i.status,
                       i.is_panic, i.created_at,
                       s.name as state_name, l.name as lga_name, w.name as ward_name,
                       u.full_name as reporter_name
                FROM incidents i
                LEFT JOIN users u ON i.reporter_id = u.id
                LEFT JOIN states s ON i.state_id = s.id
                LEFT JOIN lgas l ON i.lga_id = l.id
                LEFT JOIN wards w ON i.ward_id = w.id
                WHERE i.tenant_id = ?
                ORDER BY i.created_at DESC
            ");
            $stmt->execute([$tenant_id]);
            $data = $stmt->fetchAll();
            $headers = ['ID', 'Title', 'Type', 'Severity', 'Status', 'Panic', 'Created At', 'State', 'LGA', 'Ward', 'Reporter'];
            break;
            
        case 'financial':
            $title = 'Financial Report';
            // Budgets
            $stmt = $db->prepare("
                SELECT b.id, b.name, b.total_amount, b.status, b.start_date, b.end_date,
                       (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE budget_id = b.id AND status != 'rejected') as spent,
                       e.name as election_name
                FROM budgets b
                LEFT JOIN elections e ON b.election_id = e.id
                WHERE b.tenant_id = ?
                ORDER BY b.created_at DESC
            ");
            $stmt->execute([$tenant_id]);
            $budgets = $stmt->fetchAll();
            
            // Total expenses
            $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE tenant_id = ? AND status != 'rejected'");
            $stmt->execute([$tenant_id]);
            $total_expenses = $stmt->fetch()['total'] ?? 0;
            
            $data = [
                'summary' => [
                    'total_budget' => array_sum(array_column($budgets, 'total_amount')),
                    'total_spent' => $total_expenses,
                    'budget_count' => count($budgets)
                ],
                'budgets' => $budgets
            ];
            $headers = ['Budget Name', 'Total Amount', 'Spent', 'Remaining', 'Status', 'Election', 'Start Date', 'End Date'];
            break;
            
        case 'polling_units':
            $title = 'Polling Unit Report';
            $stmt = $db->prepare("
                SELECT pu.id, pu.code, pu.name, pu.registered_voters,
                       w.name as ward_name, l.name as lga_name, s.name as state_name,
                       pu.network_quality, pu.is_rural, pu.is_active
                FROM polling_units pu
                LEFT JOIN wards w ON pu.ward_id = w.id
                LEFT JOIN lgas l ON w.lga_id = l.id
                LEFT JOIN states s ON l.state_id = s.id
                WHERE pu.is_active = 1
                ORDER BY s.name, l.name, w.name, pu.name
            ");
            $stmt->execute();
            $data = $stmt->fetchAll();
            $headers = ['ID', 'Code', 'Name', 'Voters', 'Ward', 'LGA', 'State', 'Network', 'Rural', 'Active'];
            break;
            
        case 'candidates':
            $title = 'Candidate Report';
            $stmt = $db->prepare("
                SELECT c.id, c.full_name, c.position,
                       p.name as party_name, p.acronym as party_acronym,
                       e.name as election_name, c.is_active
                FROM candidates c
                LEFT JOIN political_parties p ON c.party_id = p.id
                LEFT JOIN elections e ON c.election_id = e.id
                WHERE c.tenant_id = ?
                ORDER BY e.election_date DESC, c.position
            ");
            $stmt->execute([$tenant_id]);
            $data = $stmt->fetchAll();
            $headers = ['ID', 'Full Name', 'Position', 'Party', 'Acronym', 'Election', 'Active'];
            break;
            
        default:
            throw new Exception('Invalid report type.');
    }
} catch (Exception $e) {
    $_SESSION['export_error'] = $e->getMessage();
    header('Location: reports.php');
    exit();
}

// ============================================================
// GENERATE EXPORT FILE
// ============================================================
$filename = strtolower(str_replace(' ', '_', $title)) . '_' . date('Y-m-d');

if ($format === 'pdf') {
    // Generate PDF
    require_once '../../includes/vendor/autoload.php';
    
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('5G Election Guru');
    $pdf->SetTitle($title);
    $pdf->SetSubject('Report');
    $pdf->SetKeywords('report, election, ' . $report_type);
    
    $pdf->SetHeaderData('', 0, $title, 'Generated on ' . date('F j, Y'));
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->AddPage();
    
    // Generate HTML table
    $html = '<h1>' . $title . '</h1>';
    $html .= '<p>Generated on: ' . date('F j, Y g:i A') . '</p>';
    $html .= '<table border="1" cellpadding="4" style="font-size:8pt;">';
    $html .= '<thead><tr>';
    foreach ($headers as $header) {
        $html .= '<th style="background-color:#f0f0f0;font-weight:bold;">' . htmlspecialchars($header) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    
    if ($report_type === 'financial') {
        // Special handling for financial report
        $html .= '<tr><td colspan="8" style="background-color:#e8f4f8;font-weight:bold;">Summary</td></tr>';
        $html .= '<tr>';
        $html .= '<td colspan="2"><strong>Total Budget:</strong> ₦' . number_format($data['summary']['total_budget']) . '</td>';
        $html .= '<td colspan="2"><strong>Total Spent:</strong> ₦' . number_format($data['summary']['total_spent']) . '</td>';
        $html .= '<td colspan="2"><strong>Total Budgets:</strong> ' . $data['summary']['budget_count'] . '</td>';
        $html .= '<td colspan="2"><strong>Utilization:</strong> ' . ($data['summary']['total_budget'] > 0 ? round(($data['summary']['total_spent'] / $data['summary']['total_budget']) * 100, 1) : 0) . '%</td>';
        $html .= '</tr>';
        $html .= '<tr><td colspan="8" style="background-color:#e8f4f8;font-weight:bold;">Budget Details</td></tr>';
        
        foreach ($data['budgets'] as $row) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($row['name']) . '</td>';
            $html .= '<td>₦' . number_format($row['total_amount']) . '</td>';
            $html .= '<td>₦' . number_format($row['spent']) . '</td>';
            $html .= '<td>₦' . number_format($row['total_amount'] - $row['spent']) . '</td>';
            $html .= '<td>' . ucfirst($row['status']) . '</td>';
            $html .= '<td>' . htmlspecialchars($row['election_name'] ?? 'N/A') . '</td>';
            $html .= '<td>' . date('M j, Y', strtotime($row['start_date'])) . '</td>';
            $html .= '<td>' . (!empty($row['end_date']) ? date('M j, Y', strtotime($row['end_date'])) : 'Ongoing') . '</td>';
            $html .= '</tr>';
        }
    } else {
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($row as $key => $value) {
                if (is_array($value)) {
                    $html .= '<td>' . json_encode($value) . '</td>';
                } else {
                    $html .= '<td>' . htmlspecialchars((string)$value) . '</td>';
                }
            }
            $html .= '</tr>';
        }
    }
    
    $html .= '</tbody></table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Output PDF
    $pdf->Output($filename . '.pdf', 'D');
    exit();
    
} elseif ($format === 'excel') {
    // Generate Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo '<html><head><meta charset="UTF-8"></head><body>';
    echo '<h1>' . $title . '</h1>';
    echo '<p>Generated on: ' . date('F j, Y g:i A') . '</p>';
    echo '<table border="1" cellpadding="4">';
    echo '<thead><tr>';
    foreach ($headers as $header) {
        echo '<th style="background-color:#4472C4;color:#ffffff;">' . htmlspecialchars($header) . '</th>';
    }
    echo '</tr></thead><tbody>';
    
    if ($report_type === 'financial') {
        echo '<tr><td colspan="8" style="background-color:#e8f4f8;font-weight:bold;">Summary</td></tr>';
        echo '<tr>';
        echo '<td colspan="2"><strong>Total Budget:</strong> ₦' . number_format($data['summary']['total_budget']) . '</td>';
        echo '<td colspan="2"><strong>Total Spent:</strong> ₦' . number_format($data['summary']['total_spent']) . '</td>';
        echo '<td colspan="2"><strong>Total Budgets:</strong> ' . $data['summary']['budget_count'] . '</td>';
        echo '<td colspan="2"><strong>Utilization:</strong> ' . ($data['summary']['total_budget'] > 0 ? round(($data['summary']['total_spent'] / $data['summary']['total_budget']) * 100, 1) : 0) . '%</td>';
        echo '</tr>';
        echo '<tr><td colspan="8" style="background-color:#e8f4f8;font-weight:bold;">Budget Details</td></tr>';
        
        foreach ($data['budgets'] as $row) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['name']) . '</td>';
            echo '<td>₦' . number_format($row['total_amount']) . '</td>';
            echo '<td>₦' . number_format($row['spent']) . '</td>';
            echo '<td>₦' . number_format($row['total_amount'] - $row['spent']) . '</td>';
            echo '<td>' . ucfirst($row['status']) . '</td>';
            echo '<td>' . htmlspecialchars($row['election_name'] ?? 'N/A') . '</td>';
            echo '<td>' . date('M j, Y', strtotime($row['start_date'])) . '</td>';
            echo '<td>' . (!empty($row['end_date']) ? date('M j, Y', strtotime($row['end_date'])) : 'Ongoing') . '</td>';
            echo '</tr>';
        }
    } else {
        foreach ($data as $row) {
            echo '<tr>';
            foreach ($row as $value) {
                if (is_array($value)) {
                    echo '<td>' . json_encode($value) . '</td>';
                } else {
                    echo '<td>' . htmlspecialchars((string)$value) . '</td>';
                }
            }
            echo '</tr>';
        }
    }
    
    echo '</tbody></table>';
    echo '</body></html>';
    exit();
    
} elseif ($format === 'csv') {
    // Generate CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);
    
    if ($report_type === 'financial') {
        // Add summary row
        fputcsv($output, ['Summary']);
        fputcsv($output, ['Total Budget', 'Total Spent', 'Total Budgets', 'Utilization']);
        fputcsv($output, [
            $data['summary']['total_budget'],
            $data['summary']['total_spent'],
            $data['summary']['budget_count'],
            ($data['summary']['total_budget'] > 0 ? round(($data['summary']['total_spent'] / $data['summary']['total_budget']) * 100, 1) : 0) . '%'
        ]);
        fputcsv($output, []);
        fputcsv($output, ['Budget Details']);
        fputcsv($output, ['Name', 'Total Amount', 'Spent', 'Remaining', 'Status', 'Election', 'Start Date', 'End Date']);
        
        foreach ($data['budgets'] as $row) {
            fputcsv($output, [
                $row['name'],
                $row['total_amount'],
                $row['spent'],
                $row['total_amount'] - $row['spent'],
                $row['status'],
                $row['election_name'] ?? 'N/A',
                $row['start_date'],
                $row['end_date'] ?? 'Ongoing'
            ]);
        }
    } else {
        foreach ($data as $row) {
            $row_data = [];
            foreach ($row as $value) {
                $row_data[] = is_array($value) ? json_encode($value) : (string)$value;
            }
            fputcsv($output, $row_data);
        }
    }
    
    fclose($output);
    exit();
} else {
    header('Location: reports.php');
    exit();
}
?>