<?php
// ============================================================
// FEDERAL CONSTITUENCY COORDINATOR - CONSTITUENCY REPORT
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'federal_constituency') {
    header('Location: ../client-admin/');
    exit();
}

$user_id = SessionManager::get('user_id');
$constituency_id = SessionManager::get('federal_constituency_id');
$tenant_id = SessionManager::get('tenant_id');
$db = getDB();

// Get LGA IDs
$lga_ids = [];
try {
    $stmt = $db->prepare("SELECT lgas_json FROM federal_constituencies WHERE id = ?");
    $stmt->execute([$constituency_id]);
    $lgas_json = $stmt->fetchColumn();
    if ($lgas_json) {
        $lga_ids = json_decode($lgas_json, true) ?: [];
    }
} catch (Exception $e) {
    error_log("Error fetching LGA IDs: " . $e->getMessage());
}

$lga_list = !empty($lga_ids) ? implode(',', array_map('intval', $lga_ids)) : '0';

// Get filters
$format = isset($_GET['format']) ? $_GET['format'] : 'html';

// Get report data
$report_data = [];
$summary = [
    'total_lgas' => 0,
    'total_wards' => 0,
    'total_pus' => 0,
    'total_results' => 0,
    'verified_results' => 0,
    'pending_results' => 0,
    'flagged_results' => 0,
    'total_incidents' => 0,
    'total_coordinators' => 0,
    'total_agents' => 0
];

try {
    // Get summary
    if ($lga_list !== '0') {
        $stmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT l.id) as total_lgas,
                COUNT(DISTINCT w.id) as total_wards,
                COUNT(DISTINCT pu.id) as total_pus,
                (SELECT COUNT(*) FROM users u 
                 JOIN roles r ON u.role_id = r.id 
                 WHERE u.tenant_id = ? AND u.status = 'active' AND r.level IN ('lga', 'ward')) as total_coordinators,
                (SELECT COUNT(*) FROM users u 
                 JOIN roles r ON u.role_id = r.id 
                 JOIN polling_units pu2 ON u.pu_id = pu2.id 
                 JOIN wards w2 ON pu2.ward_id = w2.id 
                 WHERE u.tenant_id = ? AND u.status = 'active' AND r.level = 'pu_agent' AND w2.lga_id IN ($lga_list)) as total_agents
            FROM lgas l
            LEFT JOIN wards w ON w.lga_id = l.id AND w.is_active = 1
            LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
            WHERE l.id IN ($lga_list)
        ");
        $stmt->execute([$tenant_id, $tenant_id]);
        $summary = $stmt->fetch();
        
        // Get results stats
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_results,
                SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified_results,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_results,
                SUM(CASE WHEN status = 'flagged' THEN 1 ELSE 0 END) as flagged_results
            FROM results_ec8a r
            JOIN polling_units pu ON r.pu_id = pu.id
            JOIN wards w ON pu.ward_id = w.id
            WHERE r.tenant_id = ? AND w.lga_id IN ($lga_list)
        ");
        $stmt->execute([$tenant_id]);
        $result_stats = $stmt->fetch();
        $summary['total_results'] = (int)($result_stats['total_results'] ?? 0);
        $summary['verified_results'] = (int)($result_stats['verified_results'] ?? 0);
        $summary['pending_results'] = (int)($result_stats['pending_results'] ?? 0);
        $summary['flagged_results'] = (int)($result_stats['flagged_results'] ?? 0);
        
        // Get incidents
        $stmt = $db->prepare("
            SELECT COUNT(*) as total_incidents
            FROM incidents 
            WHERE tenant_id = ? AND lga_id IN ($lga_list)
        ");
        $stmt->execute([$tenant_id]);
        $summary['total_incidents'] = (int)$stmt->fetchColumn();
        
        // Get LGA breakdown
        $stmt = $db->prepare("
            SELECT 
                l.id,
                l.name,
                COUNT(DISTINCT w.id) as ward_count,
                COUNT(DISTINCT pu.id) as pu_count,
                (SELECT COUNT(*) FROM results_ec8a r 
                 JOIN polling_units pu2 ON r.pu_id = pu2.id 
                 JOIN wards w2 ON pu2.ward_id = w2.id 
                 WHERE w2.lga_id = l.id AND r.tenant_id = ?) as result_count,
                (SELECT COUNT(*) FROM incidents i WHERE i.lga_id = l.id AND i.tenant_id = ?) as incident_count
            FROM lgas l
            LEFT JOIN wards w ON w.lga_id = l.id AND w.is_active = 1
            LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
            WHERE l.id IN ($lga_list)
            GROUP BY l.id
            ORDER BY l.name ASC
        ");
        $stmt->execute([$tenant_id, $tenant_id]);
        $report_data = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching report data: " . $e->getMessage());
}

// Handle export
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="constituency_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    
    fputcsv($output, [
        'LGA', 'Wards', 'Polling Units', 'Results', 'Incidents'
    ]);
    
    foreach ($report_data as $row) {
        fputcsv($output, [
            $row['name'],
            $row['ward_count'] ?? 0,
            $row['pu_count'] ?? 0,
            $row['result_count'] ?? 0,
            $row['incident_count'] ?? 0
        ]);
    }
    fclose($output);
    exit();
}

if ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="constituency_report_' . date('Y-m-d') . '.xls"');
    
    echo '<html><head><meta charset="UTF-8"><title>Constituency Report</title></head><body>';
    echo '<table border="1" cellpadding="5">';
    echo '<tr style="background:#0F4C81;color:white;font-weight:bold;">';
    echo '<th>LGA</th><th>Wards</th><th>Polling Units</th><th>Results</th><th>Incidents</th>';
    echo '</tr>';
    foreach ($report_data as $row) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['name']) . '</td>';
        echo '<td>' . ($row['ward_count'] ?? 0) . '</td>';
        echo '<td>' . ($row['pu_count'] ?? 0) . '</td>';
        echo '<td>' . ($row['result_count'] ?? 0) . '</td>';
        echo '<td>' . ($row['incident_count'] ?? 0) . '</td>';
        echo '</tr>';
    }
    echo '</table></body></html>';
    exit();
}

$page_title = 'Constituency Report';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.report-header {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px 24px;
    margin-bottom: 24px;
}
.report-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.report-header .subtitle {
    color: var(--gray-500);
    font-size: 0.85rem;
    margin-top: 4px;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 14px;
    margin-bottom: 24px;
}
.summary-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px 18px;
    text-align: center;
}
.summary-card .number {
    font-size: 1.4rem;
    font-weight: 700;
}
.summary-card .label {
    font-size: 0.7rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.summary-card.blue .number { color: #2563EB; }
.summary-card.green .number { color: #059669; }
.summary-card.purple .number { color: #7C3AED; }
.summary-card.orange .number { color: #EA580C; }
.summary-card.teal .number { color: #0D9488; }
.summary-card.red .number { color: #DC2626; }

.export-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}
.export-actions .btn {
    padding: 8px 18px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.8rem;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.export-actions .btn-csv {
    background: #10B981;
    color: white;
}
.export-actions .btn-csv:hover {
    background: #059669;
}
.export-actions .btn-excel {
    background: #059669;
    color: white;
}
.export-actions .btn-excel:hover {
    background: #047857;
}
.export-actions .btn-pdf {
    background: #DC2626;
    color: white;
}
.export-actions .btn-pdf:hover {
    background: #B91C1C;
}
.export-actions .btn-print {
    background: var(--gray-100);
    color: var(--gray-600);
}
.export-actions .btn-print:hover {
    background: var(--gray-200);
}

.table-wrap {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
}
.table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}
.table th {
    text-align: left;
    padding: 12px 16px;
    background: var(--gray-50);
    font-weight: 600;
    color: var(--gray-600);
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--gray-200);
}
.table td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}
.table tr:hover td {
    background: var(--gray-50);
}
.table .lga-name {
    font-weight: 500;
}

@media (max-width: 768px) {
    .summary-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .export-actions {
        flex-direction: column;
    }
    .export-actions .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Header -->
        <div class="report-header">
            <h2>
                <i class="fas fa-file-alt" style="color:var(--primary);"></i> 
                Constituency Report
            </h2>
            <div class="subtitle">
                Summary of election activities in your federal constituency
            </div>
        </div>

        <!-- Summary -->
        <div class="summary-grid">
            <div class="summary-card blue">
                <div class="number"><?php echo number_format($summary['total_lgas'] ?? 0); ?></div>
                <div class="label">LGAs</div>
            </div>
            <div class="summary-card purple">
                <div class="number"><?php echo number_format($summary['total_wards'] ?? 0); ?></div>
                <div class="label">Wards</div>
            </div>
            <div class="summary-card teal">
                <div class="number"><?php echo number_format($summary['total_pus'] ?? 0); ?></div>
                <div class="label">Polling Units</div>
            </div>
            <div class="summary-card green">
                <div class="number"><?php echo number_format($summary['verified_results'] ?? 0); ?></div>
                <div class="label">Verified Results</div>
            </div>
            <div class="summary-card orange">
                <div class="number"><?php echo number_format($summary['pending_results'] ?? 0); ?></div>
                <div class="label">Pending</div>
            </div>
            <div class="summary-card red">
                <div class="number"><?php echo number_format($summary['total_incidents'] ?? 0); ?></div>
                <div class="label">Incidents</div>
            </div>
        </div>

        <!-- Export -->
        <div class="export-actions">
            <a href="reports-constituency.php?format=csv" class="btn btn-csv">
                <i class="fas fa-file-csv"></i> Export CSV
            </a>
            <a href="reports-constituency.php?format=excel" class="btn btn-excel">
                <i class="fas fa-file-excel"></i> Export Excel
            </a>
            <button onclick="window.print()" class="btn btn-print">
                <i class="fas fa-print"></i> Print
            </button>
        </div>

        <!-- Table -->
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>LGA</th>
                        <th>Wards</th>
                        <th>Polling Units</th>
                        <th>Results</th>
                        <th>Incidents</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($report_data) > 0): ?>
                        <?php $i = 1; foreach ($report_data as $row): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td class="lga-name"><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo number_format($row['ward_count'] ?? 0); ?></td>
                                <td><?php echo number_format($row['pu_count'] ?? 0); ?></td>
                                <td><?php echo number_format($row['result_count'] ?? 0); ?></td>
                                <td><?php echo number_format($row['incident_count'] ?? 0); ?></td>
                                <td>
                                    <a href="view-lga-details.php?id=<?php echo $row['id']; ?>" style="color:var(--primary);text-decoration:none;font-size:0.8rem;">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center;padding:40px;color:var(--gray-500);">
                                No data available
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
// Sidebar toggle (standard)
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

window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});
</script>
</body>
</html>