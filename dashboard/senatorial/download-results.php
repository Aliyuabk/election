<?php
// ============================================================
// SENATORIAL COORDINATOR - DOWNLOAD RESULTS SUMMARY
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

if (SessionManager::get('role_level') !== 'senatorial') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$senatorial_id = SessionManager::get('senatorial_id');
$state_id = SessionManager::get('state_id');
$tenant_id = SessionManager::get('tenant_id');

$db = getDB();

// ============================================================
// GET SENATORIAL DISTRICT NAME
// ============================================================
$district_name = 'Senatorial District';
$state_name = 'State';
try {
    if ($senatorial_id) {
        $stmt = $db->prepare("
            SELECT s.name as state_name, sd.name as district_name 
            FROM senatorial_districts sd 
            JOIN states s ON sd.state_id = s.id 
            WHERE sd.id = ?
        ");
        $stmt->execute([$senatorial_id]);
        $result = $stmt->fetch();
        if ($result) {
            $district_name = $result['district_name'];
            $state_name = $result['state_name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching district: " . $e->getMessage());
}

// ============================================================
// GET LGA IDs FROM SENATORIAL DISTRICT
// ============================================================
$lga_ids = [];
try {
    if ($senatorial_id) {
        $stmt = $db->prepare("SELECT lgas_json FROM senatorial_districts WHERE id = ?");
        $stmt->execute([$senatorial_id]);
        $lgas_json = $stmt->fetchColumn();
        
        if ($lgas_json) {
            $lga_names = json_decode($lgas_json, true) ?: [];
            
            if (!empty($lga_names)) {
                $placeholders = implode(',', array_fill(0, count($lga_names), '?'));
                $stmt = $db->prepare("SELECT id FROM lgas WHERE name IN ($placeholders) AND state_id = ? AND is_active = 1");
                $stmt->execute(array_merge($lga_names, [$state_id]));
                $lga_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
        }
    }
} catch (Exception $e) {
    error_log("Error getting LGA IDs: " . $e->getMessage());
    $lga_ids = [];
}

$lga_list = !empty($lga_ids) ? implode(',', array_map('intval', $lga_ids)) : '0';

// ============================================================
// GET FILTERS
// ============================================================
$format = isset($_GET['format']) ? $_GET['format'] : '';
$election_id = isset($_GET['election']) ? (int)$_GET['election'] : 0;
$lga_filter = isset($_GET['lga']) ? (int)$_GET['lga'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// ============================================================
// BUILD QUERY
// ============================================================
$where_conditions = ["r.tenant_id = ?"];
$params = [$tenant_id];

if ($election_id > 0) {
    $where_conditions[] = "r.election_id = ?";
    $params[] = $election_id;
}

if ($lga_filter > 0) {
    $where_conditions[] = "w.lga_id = ?";
    $params[] = $lga_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "r.status = ?";
    $params[] = $status_filter;
}

if ($lga_list !== '0') {
    $where_conditions[] = "w.lga_id IN ($lga_list)";
} else {
    $where_conditions[] = "1=0";
}

$where_clause = implode(" AND ", $where_conditions);

// ============================================================
// GET DATA
// ============================================================
$results = [];
try {
    $query = "
        SELECT 
            r.id,
            r.pu_name,
            r.pu_code,
            r.registered_voters,
            r.accredited_voters,
            r.valid_votes,
            r.rejected_votes,
            r.total_votes_cast,
            r.status,
            r.created_at,
            r.verified_at,
            r.party_votes_json,
            w.name as ward_name,
            l.name as lga_name,
            e.name as election_name,
            u.full_name as agent_name,
            v.full_name as verified_by_name
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        JOIN elections e ON r.election_id = e.id
        LEFT JOIN users u ON r.agent_id = u.id
        LEFT JOIN users v ON r.verified_by = v.id
        WHERE $where_clause
        ORDER BY l.name ASC, w.name ASC, r.pu_name ASC
        LIMIT 10000
    ";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching download data: " . $e->getMessage());
    $results = [];
}

// ============================================================
// CHECK IF DOWNLOAD REQUESTED
// ============================================================
if (!empty($format) && !empty($results)) {
    // ============================================================
    // CSV OUTPUT
    // ============================================================
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="results_summary_' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-cache, must-revalidate');
        
        $output = fopen('php://output', 'w');
        
        // UTF-8 BOM for Excel compatibility
        fwrite($output, "\xEF\xBB\xBF");
        
        // Headers
        fputcsv($output, [
            'PU Name',
            'PU Code',
            'Ward',
            'LGA',
            'Election',
            'Registered Voters',
            'Accredited Voters',
            'Valid Votes',
            'Rejected Votes',
            'Total Votes Cast',
            'Status',
            'Agent',
            'Verified By',
            'Submitted At',
            'Verified At'
        ]);
        
        // Data
        foreach ($results as $row) {
            fputcsv($output, [
                $row['pu_name'],
                $row['pu_code'],
                $row['ward_name'],
                $row['lga_name'],
                $row['election_name'],
                $row['registered_voters'],
                $row['accredited_voters'],
                $row['valid_votes'],
                $row['rejected_votes'],
                $row['total_votes_cast'],
                $row['status'],
                $row['agent_name'],
                $row['verified_by_name'],
                $row['created_at'],
                $row['verified_at']
            ]);
        }
        
        fclose($output);
        exit();
    }
    
    // ============================================================
    // EXCEL OUTPUT (using simple HTML table with xls extension)
    // ============================================================
    if ($format === 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="results_summary_' . date('Y-m-d') . '.xls"');
        header('Cache-Control: no-cache, must-revalidate');
        
        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
        echo '<head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Results</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head>';
        echo '<body>';
        echo '<table border="1" cellpadding="5">';
        
        // Headers
        echo '<tr style="background:#0F4C81;color:white;font-weight:bold;">';
        echo '<th>PU Name</th>';
        echo '<th>PU Code</th>';
        echo '<th>Ward</th>';
        echo '<th>LGA</th>';
        echo '<th>Election</th>';
        echo '<th>Registered Voters</th>';
        echo '<th>Accredited Voters</th>';
        echo '<th>Valid Votes</th>';
        echo '<th>Rejected Votes</th>';
        echo '<th>Total Votes Cast</th>';
        echo '<th>Status</th>';
        echo '<th>Agent</th>';
        echo '<th>Verified By</th>';
        echo '<th>Submitted At</th>';
        echo '<th>Verified At</th>';
        echo '</tr>';
        
        // Data
        foreach ($results as $row) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['pu_name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['pu_code']) . '</td>';
            echo '<td>' . htmlspecialchars($row['ward_name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['lga_name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['election_name']) . '</td>';
            echo '<td>' . $row['registered_voters'] . '</td>';
            echo '<td>' . $row['accredited_voters'] . '</td>';
            echo '<td>' . $row['valid_votes'] . '</td>';
            echo '<td>' . $row['rejected_votes'] . '</td>';
            echo '<td>' . $row['total_votes_cast'] . '</td>';
            echo '<td>' . $row['status'] . '</td>';
            echo '<td>' . htmlspecialchars($row['agent_name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['verified_by_name']) . '</td>';
            echo '<td>' . $row['created_at'] . '</td>';
            echo '<td>' . $row['verified_at'] . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        echo '</body></html>';
        exit();
    }
    
    // ============================================================
    // PDF OUTPUT (using HTML with print styles)
    // ============================================================
    if ($format === 'pdf') {
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="results_summary_' . date('Y-m-d') . '.html"');
        header('Cache-Control: no-cache, must-revalidate');
        
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Results Summary - <?php echo date('Y-m-d'); ?></title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 11px; padding: 20px; }
                h1 { font-size: 18px; color: #0F4C81; margin-bottom: 4px; }
                .subtitle { color: #666; font-size: 13px; margin-bottom: 16px; }
                table { width: 100%; border-collapse: collapse; margin-top: 12px; }
                th { background: #0F4C81; color: white; padding: 8px 6px; text-align: left; font-size: 10px; }
                td { padding: 6px; border-bottom: 1px solid #ddd; }
                tr:nth-child(even) td { background: #f9f9f9; }
                .footer { margin-top: 20px; font-size: 10px; color: #999; text-align: center; border-top: 1px solid #ddd; padding-top: 12px; }
                @media print {
                    body { padding: 10px; }
                    th { background: #0F4C81 !important; color: white !important; }
                }
            </style>
        </head>
        <body>
            <h1>Election Results Summary</h1>
            <div class="subtitle">
                <?php echo htmlspecialchars($district_name); ?> - <?php echo htmlspecialchars($state_name); ?>
                <span style="margin-left:20px;">Generated: <?php echo date('F d, Y g:i A'); ?></span>
                <span style="margin-left:20px;">Total Results: <?php echo count($results); ?></span>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>PU Name</th>
                        <th>Code</th>
                        <th>Ward</th>
                        <th>LGA</th>
                        <th>Election</th>
                        <th>Reg Voters</th>
                        <th>Valid</th>
                        <th>Rejected</th>
                        <th>Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['pu_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['pu_code']); ?></td>
                            <td><?php echo htmlspecialchars($row['ward_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['lga_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['election_name']); ?></td>
                            <td style="text-align:center;"><?php echo number_format($row['registered_voters']); ?></td>
                            <td style="text-align:center;"><?php echo number_format($row['valid_votes']); ?></td>
                            <td style="text-align:center;"><?php echo number_format($row['rejected_votes']); ?></td>
                            <td style="text-align:center;"><?php echo number_format($row['total_votes_cast']); ?></td>
                            <td><?php echo ucfirst($row['status']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="footer">
                &copy; <?php echo date('Y'); ?> Election Monitoring System. All rights reserved.
                <br>Generated on <?php echo date('F d, Y g:i A'); ?>
            </div>
        </body>
        </html>
        <?php
        exit();
    }
}

// ============================================================
// HTML PAGE WITH DOWNLOAD OPTIONS
// ============================================================
$page_title = 'Download Results';
include '../includes/base.php';
include '../includes/sidebar.php';

// Get elections and LGAs for filters
$elections = [];
$lgas = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM elections WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY election_date DESC");
    $stmt->execute([$tenant_id]);
    $elections = $stmt->fetchAll();
    
    if ($lga_list !== '0') {
        $stmt = $db->prepare("SELECT id, name FROM lgas WHERE id IN ($lga_list) AND is_active = 1 ORDER BY name ASC");
        $stmt->execute();
        $lgas = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching filters: " . $e->getMessage());
}

// Calculate summary stats
$total_results = count($results);
$verified_count = 0;
$pending_count = 0;
$total_votes = 0;

foreach ($results as $r) {
    if ($r['status'] === 'verified') $verified_count++;
    if ($r['status'] === 'pending') $pending_count++;
    $total_votes += (int)($r['total_votes_cast'] ?? 0);
}
?>

<style>
/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.page-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
}
.page-header h2 small {
    font-size: 0.8rem;
    font-weight: 400;
    color: var(--gray-500);
    display: block;
    margin-top: 2px;
}

/* Download Card */
.download-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px;
    margin-bottom: 20px;
}
.download-card h3 {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 8px;
}
.download-card .sub-text {
    color: var(--gray-500);
    font-size: 0.85rem;
    margin-bottom: 16px;
}
.download-options {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}
.download-btn {
    padding: 12px 24px;
    border-radius: 10px;
    border: 1.5px solid var(--gray-200);
    background: white;
    text-decoration: none;
    color: var(--gray-700);
    font-weight: 500;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: all 0.2s ease;
    cursor: pointer;
}
.download-btn:hover {
    border-color: var(--primary);
    background: var(--gray-50);
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.download-btn i {
    font-size: 1.2rem;
}
.download-btn .icon-csv { color: #10B981; }
.download-btn .icon-excel { color: #059669; }
.download-btn .icon-pdf { color: #DC2626; }
.download-btn .file-size {
    font-size: 0.7rem;
    color: var(--gray-400);
    font-weight: 400;
}

/* Filter Section */
.filter-section {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    background: white;
    padding: 16px 20px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    align-items: center;
}
.filter-section select {
    padding: 8px 14px;
    border: 1.5px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.8rem;
    background: white;
    min-width: 150px;
    transition: border-color 0.2s;
}
.filter-section select:focus {
    outline: none;
    border-color: var(--primary);
}
.filter-section .btn-filter {
    padding: 8px 20px;
    border: none;
    border-radius: 8px;
    background: var(--primary);
    color: white;
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    transition: background 0.2s;
}
.filter-section .btn-filter:hover {
    background: #1D4ED8;
}
.filter-section .btn-reset {
    padding: 8px 16px;
    border: 1.5px solid var(--gray-200);
    border-radius: 8px;
    background: white;
    color: var(--gray-600);
    font-weight: 500;
    font-size: 0.8rem;
    text-decoration: none;
    transition: all 0.2s;
}
.filter-section .btn-reset:hover {
    border-color: var(--gray-400);
    background: var(--gray-50);
}

/* Summary Stats */
.summary-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}
.summary-stat {
    background: var(--gray-50);
    border-radius: 8px;
    padding: 12px;
    text-align: center;
}
.summary-stat .number {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--gray-800);
}
.summary-stat .label {
    font-size: 0.65rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Table */
.table-wrap {
    overflow-x: auto;
}
.table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}
.table th {
    text-align: left;
    padding: 10px 12px;
    background: var(--gray-50);
    font-weight: 600;
    color: var(--gray-600);
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--gray-200);
}
.table td {
    padding: 10px 12px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}
.table tr:hover td {
    background: var(--gray-50);
}
.status-badge {
    display: inline-block;
    padding: 2px 12px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
}
.status-badge.pending { background: #FEF3C7; color: #D97706; }
.status-badge.verified { background: #D1FAE5; color: #059669; }
.status-badge.flagged { background: #FEE2E2; color: #DC2626; }
.status-badge.rejected { background: #FEE2E2; color: #DC2626; }

/* Preview Card */
.preview-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
}
.preview-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.preview-card .card-header h3 {
    font-size: 0.95rem;
    font-weight: 600;
    margin: 0;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--gray-500);
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}
.empty-state i {
    font-size: 2.5rem;
    color: var(--gray-300);
    margin-bottom: 12px;
}
.empty-state h3 {
    font-size: 1.1rem;
    color: var(--gray-700);
    margin: 0 0 8px 0;
}

/* Responsive */
@media (max-width: 768px) {
    .filter-section {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-section select {
        min-width: unset;
        width: 100%;
    }
    .download-options {
        flex-direction: column;
    }
    .download-btn {
        justify-content: center;
    }
    .summary-stats {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-download" style="color:var(--primary);margin-right:8px;"></i> 
                    Download Results
                    <small><?php echo htmlspecialchars($district_name); ?> - <?php echo htmlspecialchars($state_name); ?></small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="view-results.php" class="btn btn-secondary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--gray-100);color:var(--gray-600);border:none;">
                    <i class="fas fa-arrow-left"></i> Back to Results
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <form method="GET" action="" style="display:flex;gap:12px;flex-wrap:wrap;flex:1;align-items:center;">
                <select name="election">
                    <option value="">All Elections</option>
                    <?php foreach ($elections as $e): ?>
                        <option value="<?php echo $e['id']; ?>" <?php echo ($election_id == $e['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($e['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="lga">
                    <option value="">All LGAs</option>
                    <?php foreach ($lgas as $lga): ?>
                        <option value="<?php echo $lga['id']; ?>" <?php echo ($lga_filter == $lga['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($lga['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo ($status_filter === 'pending') ? 'selected' : ''; ?>>Pending</option>
                    <option value="verified" <?php echo ($status_filter === 'verified') ? 'selected' : ''; ?>>Verified</option>
                    <option value="flagged" <?php echo ($status_filter === 'flagged') ? 'selected' : ''; ?>>Flagged</option>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
                <?php if ($election_id > 0 || $lga_filter > 0 || !empty($status_filter)): ?>
                    <a href="download-results.php" class="btn-reset"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (empty($results)): ?>
            <!-- Empty State -->
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <h3>No Results Found</h3>
                <p>There are no results matching your filter criteria. Try adjusting your filters.</p>
                <a href="download-results.php" style="display:inline-block;margin-top:12px;padding:8px 20px;background:var(--primary);color:white;border-radius:8px;text-decoration:none;font-weight:500;">
                    <i class="fas fa-undo"></i> Reset Filters
                </a>
            </div>
        <?php else: ?>
            <!-- Summary Stats -->
            <div class="summary-stats">
                <div class="summary-stat">
                    <div class="number"><?php echo number_format($total_results); ?></div>
                    <div class="label">Total Results</div>
                </div>
                <div class="summary-stat">
                    <div class="number"><?php echo number_format($verified_count); ?></div>
                    <div class="label">Verified</div>
                </div>
                <div class="summary-stat">
                    <div class="number"><?php echo number_format($pending_count); ?></div>
                    <div class="label">Pending</div>
                </div>
                <div class="summary-stat">
                    <div class="number"><?php echo number_format($total_votes); ?></div>
                    <div class="label">Total Votes</div>
                </div>
            </div>

            <!-- Download Options -->
            <div class="download-card">
                <h3><i class="fas fa-file-download" style="color:var(--primary);"></i> Download Options</h3>
                <p class="sub-text">
                    <?php echo number_format($total_results); ?> results ready for download.
                    <?php if ($total_results > 1000): ?>
                        <span style="color:#D97706;font-weight:500;">
                            <i class="fas fa-info-circle"></i> Large dataset (<?php echo number_format($total_results); ?> rows)
                        </span>
                    <?php endif; ?>
                </p>
                <div class="download-options">
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['format' => 'csv'])); ?>" class="download-btn" id="downloadCSV">
                        <i class="fas fa-file-csv icon-csv"></i>
                        CSV
                        <span class="file-size">(Comma Separated)</span>
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['format' => 'excel'])); ?>" class="download-btn" id="downloadExcel">
                        <i class="fas fa-file-excel icon-excel"></i>
                        Excel
                        <span class="file-size">(.xls)</span>
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['format' => 'pdf'])); ?>" class="download-btn" id="downloadPDF">
                        <i class="fas fa-file-pdf icon-pdf"></i>
                        PDF
                        <span class="file-size">(Printable)</span>
                    </a>
                </div>
            </div>

            <!-- Preview -->
            <div class="preview-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-table" style="color:var(--primary);margin-right:6px;"></i> 
                        Data Preview
                        <span style="font-weight:400;font-size:0.7rem;color:var(--gray-400);">
                            (Showing first 20 rows)
                        </span>
                    </h3>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>PU Name</th>
                                <th>Code</th>
                                <th>Ward</th>
                                <th>LGA</th>
                                <th>Election</th>
                                <th>Votes</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach (array_slice($results, 0, 20) as $row): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['pu_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['pu_code']); ?></td>
                                    <td><?php echo htmlspecialchars($row['ward_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['lga_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['election_name']); ?></td>
                                    <td><?php echo number_format($row['total_votes_cast'] ?? 0); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $row['status'] ?? 'pending'; ?>">
                                            <?php echo ucfirst($row['status'] ?? 'Pending'); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($total_results > 20): ?>
                                <tr>
                                    <td colspan="8" style="text-align:center;color:var(--gray-400);font-size:0.8rem;padding:12px;">
                                        <i class="fas fa-ellipsis-h"></i>
                                        ... and <?php echo number_format($total_results - 20); ?> more rows. 
                                        Download the full dataset using the buttons above.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
// ============================================================
// SIDEBAR TOGGLE
// ============================================================
var sidebar = document.getElementById('sidebar');
var sidebarToggle = document.getElementById('sidebarToggle');
var sidebarOverlay = document.getElementById('sidebarOverlay');
var dashboardHeader = document.getElementById('dashboardHeader');

function toggleSidebar() {
    sidebar.classList.toggle('open');
    sidebarOverlay.classList.toggle('active');
    updateHeaderPosition();
}

function updateHeaderPosition() {
    if (window.innerWidth > 768) {
        dashboardHeader.style.left = '260px';
    } else if (sidebar.classList.contains('open')) {
        dashboardHeader.style.left = '280px';
    } else {
        dashboardHeader.style.left = '0';
    }
}

if (sidebarToggle) {
    sidebarToggle.addEventListener('click', toggleSidebar);
}
if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', toggleSidebar);
}

window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        sidebar.classList.remove('open');
        sidebarOverlay.classList.remove('active');
        dashboardHeader.style.left = '260px';
    } else if (!sidebar.classList.contains('open')) {
        dashboardHeader.style.left = '0';
    }
});

document.querySelectorAll('.dropdown-toggle').forEach(function(toggle) {
    toggle.addEventListener('click', function(e) {
        e.preventDefault();
        var dropdownId = this.dataset.dropdown;
        var dropdown = document.getElementById(dropdownId);
        var chevron = this.querySelector('.chevron');
        if (dropdown) {
            dropdown.classList.toggle('open');
            if (chevron) chevron.classList.toggle('open');
        }
    });
});

var profileBtn = document.getElementById('profileBtn');
var profileMenu = document.getElementById('profileMenu');

if (profileBtn && profileMenu) {
    profileBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        profileMenu.classList.toggle('active');
    });
    document.addEventListener('click', function(e) {
        if (!profileBtn.contains(e.target) && !profileMenu.contains(e.target)) {
            profileMenu.classList.remove('active');
        }
    });
}

// Remove preloader - immediate hide
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { 
            preloader.style.display = 'none'; 
        }, 300);
    }
});

// Ensure preloader is hidden even if load event already fired
document.addEventListener('DOMContentLoaded', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        setTimeout(function() {
            preloader.classList.add('hidden');
            setTimeout(function() { 
                preloader.style.display = 'none'; 
            }, 300);
        }, 500);
    }
});

// Show loading state on download buttons
document.querySelectorAll('.download-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        var originalHtml = this.innerHTML;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        this.style.opacity = '0.7';
        this.style.pointerEvents = 'none';
        
        // Reset after a delay (in case of browser navigation)
        setTimeout(function() {
            btn.innerHTML = originalHtml;
            btn.style.opacity = '1';
            btn.style.pointerEvents = 'auto';
        }, 30000);
    });
});
</script>
</body>
</html>