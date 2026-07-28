<?php
// ============================================================
// SENATORIAL COORDINATOR - REPORTS
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
// GET REPORTS
// ============================================================
$reports = [];
try {
    $stmt = $db->prepare("
        SELECT r.*, u.full_name as generated_by_name
        FROM reports r
        LEFT JOIN users u ON r.generated_by = u.id
        WHERE r.tenant_id = ? 
        ORDER BY r.generated_at DESC
        LIMIT 50
    ");
    $stmt->execute([$tenant_id]);
    $reports = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching reports: " . $e->getMessage());
}

// ============================================================
// GET REPORT TYPES
// ============================================================
$report_types = [
    'progress' => ['label' => 'Election Progress Report', 'icon' => 'fa-chart-line', 'color' => 'blue'],
    'constituency' => ['label' => 'Federal Constituency Report', 'icon' => 'fa-building', 'color' => 'purple'],
    'lga' => ['label' => 'LGA Report', 'icon' => 'fa-map-marker-alt', 'color' => 'green'],
    'ward' => ['label' => 'Ward Report', 'icon' => 'fa-layer-group', 'color' => 'orange'],
    'pu' => ['label' => 'Polling Unit Report', 'icon' => 'fa-flag-checkered', 'color' => 'teal'],
    'results_summary' => ['label' => 'Result Summary Report', 'icon' => 'fa-file-alt', 'color' => 'blue'],
    'incident' => ['label' => 'Incident Report', 'icon' => 'fa-exclamation-triangle', 'color' => 'red'],
    'personnel' => ['label' => 'Personnel Performance Report', 'icon' => 'fa-user-chart', 'color' => 'purple']
];

// ============================================================
// GET SUMMARY STATISTICS
// ============================================================
$stats = [
    'total_reports' => count($reports),
    'generated_today' => 0,
    'pdf_count' => 0,
    'excel_count' => 0,
    'csv_count' => 0
];

foreach ($reports as $report) {
    if (date('Y-m-d', strtotime($report['generated_at'])) === date('Y-m-d')) {
        $stats['generated_today']++;
    }
    if ($report['format'] === 'pdf') $stats['pdf_count']++;
    elseif ($report['format'] === 'excel') $stats['excel_count']++;
    elseif ($report['format'] === 'csv') $stats['csv_count']++;
}

$page_title = 'Reports';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
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

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.stat-card {
    background: white;
    border-radius: var(--radius);
    padding: 18px 20px;
    border: 1px solid var(--gray-200);
    text-align: center;
}
.stat-card .stat-number {
    font-size: 1.5rem;
    font-weight: 700;
}
.stat-card .stat-label {
    font-size: 0.75rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.stat-card .stat-icon {
    font-size: 1.2rem;
    margin-bottom: 4px;
}
.stat-card.blue .stat-number { color: #2563EB; }
.stat-card.blue .stat-icon { color: #2563EB; }
.stat-card.green .stat-number { color: #059669; }
.stat-card.green .stat-icon { color: #059669; }
.stat-card.purple .stat-number { color: #7C3AED; }
.stat-card.purple .stat-icon { color: #7C3AED; }
.stat-card.orange .stat-number { color: #EA580C; }
.stat-card.orange .stat-icon { color: #EA580C; }

.report-types-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.report-type-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
    text-decoration: none;
    color: var(--gray-700);
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 16px;
}
.report-type-card:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow);
    transform: translateY(-2px);
}
.report-type-card .type-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    flex-shrink: 0;
}
.report-type-card .type-icon.blue { background: #DBEAFE; color: #2563EB; }
.report-type-card .type-icon.purple { background: #EDE9FE; color: #7C3AED; }
.report-type-card .type-icon.green { background: #D1FAE5; color: #059669; }
.report-type-card .type-icon.orange { background: #FFEDD5; color: #EA580C; }
.report-type-card .type-icon.teal { background: #CCFBF1; color: #0D9488; }
.report-type-card .type-icon.red { background: #FEE2E2; color: #DC2626; }
.report-type-card .type-icon.yellow { background: #FEF3C7; color: #D97706; }
.report-type-card .type-info .type-name {
    font-weight: 600;
    font-size: 0.9rem;
}
.report-type-card .type-info .type-desc {
    font-size: 0.75rem;
    color: var(--gray-500);
}

.section-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
}
.section-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.section-card .card-header h3 {
    font-size: 0.95rem;
    font-weight: 600;
    margin: 0;
}
.section-card .card-header a {
    font-size: 0.75rem;
    color: var(--primary);
    text-decoration: none;
}

.table-wrap {
    overflow-x: auto;
}
.table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}
.table th {
    text-align: left;
    padding: 10px 12px;
    background: var(--gray-50);
    font-weight: 600;
    color: var(--gray-600);
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

.format-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
}
.format-badge.pdf { background: #FEE2E2; color: #DC2626; }
.format-badge.excel { background: #D1FAE5; color: #059669; }
.format-badge.csv { background: #DBEAFE; color: #2563EB; }
.format-badge.html { background: #EDE9FE; color: #7C3AED; }

.btn-sm {
    padding: 4px 12px;
    border-radius: 6px;
    background: var(--primary);
    color: white;
    text-decoration: none;
    font-size: 0.7rem;
    border: none;
}
.btn-sm:hover {
    background: var(--primary-dark);
    color: white;
}
.btn-sm.outline {
    background: transparent;
    border: 1px solid var(--gray-300);
    color: var(--gray-600);
}
.btn-sm.outline:hover {
    background: var(--gray-50);
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: var(--gray-500);
}
.empty-state i {
    font-size: 3rem;
    display: block;
    margin-bottom: 12px;
    color: var(--gray-300);
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .report-types-grid {
        grid-template-columns: 1fr;
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
                    <i class="fas fa-file-alt" style="color:var(--primary);margin-right:8px;"></i> 
                    Reports
                    <small><?php echo htmlspecialchars($district_name); ?> - <?php echo htmlspecialchars($state_name); ?></small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="generate-report.php" class="btn btn-primary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--primary);color:white;border:none;">
                    <i class="fas fa-plus"></i> Generate New Report
                </a>
                <a href="monitor-district.php" class="btn btn-secondary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--gray-100);color:var(--gray-600);border:none;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_reports']); ?></div>
                <div class="stat-label">Total Reports</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                <div class="stat-number"><?php echo number_format($stats['generated_today']); ?></div>
                <div class="stat-label">Generated Today</div>
            </div>
            <div class="stat-card purple">
                <div class="stat-icon"><i class="fas fa-file-pdf"></i></div>
                <div class="stat-number"><?php echo number_format($stats['pdf_count']); ?></div>
                <div class="stat-label">PDF Reports</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-icon"><i class="fas fa-file-excel"></i></div>
                <div class="stat-number"><?php echo number_format($stats['excel_count']); ?></div>
                <div class="stat-label">Excel Reports</div>
            </div>
        </div>

        <!-- Report Types -->
        <div class="report-types-grid">
            <?php foreach ($report_types as $key => $type): ?>
                <a href="generate-report.php?type=<?php echo $key; ?>" class="report-type-card">
                    <div class="type-icon <?php echo $type['color']; ?>">
                        <i class="fas <?php echo $type['icon']; ?>"></i>
                    </div>
                    <div class="type-info">
                        <div class="type-name"><?php echo $type['label']; ?></div>
                        <div class="type-desc">Click to generate</div>
                    </div>
                    <i class="fas fa-chevron-right" style="margin-left:auto;color:var(--gray-300);"></i>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Recent Reports -->
        <div class="section-card">
            <div class="card-header">
                <h3><i class="fas fa-clock" style="color:var(--primary);margin-right:6px;"></i> Recent Reports</h3>
                <a href="reports-history.php">View All →</a>
            </div>
            <?php if (count($reports) > 0): ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Report Name</th>
                                <th>Type</th>
                                <th>Format</th>
                                <th>Generated By</th>
                                <th>Date</th>
                                <th>Size</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach (array_slice($reports, 0, 10) as $report): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($report['name']); ?></strong>
                                    </td>
                                    <td>
                                        <?php 
                                        $type_label = $report_types[$report['type']]['label'] ?? ucfirst($report['type']);
                                        echo htmlspecialchars($type_label);
                                        ?>
                                    </td>
                                    <td>
                                        <span class="format-badge <?php echo $report['format']; ?>">
                                            <?php echo strtoupper($report['format']); ?>
                                        </span>
                                    </td>
                                    <td style="font-size:0.75rem;">
                                        <?php echo htmlspecialchars($report['generated_by_name'] ?? 'System'); ?>
                                    </td>
                                    <td style="font-size:0.75rem;color:var(--gray-500);">
                                        <?php echo date('M j, Y g:i A', strtotime($report['generated_at'])); ?>
                                    </td>
                                    <td style="font-size:0.75rem;color:var(--gray-500);">
                                        <?php 
                                        if ($report['file_size']) {
                                            echo number_format($report['file_size'] / 1024, 1) . ' KB';
                                        } else {
                                            echo '—';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div style="display:flex;gap:4px;">
                                            <?php if ($report['file_url']): ?>
                                                <a href="<?php echo $report['file_url']; ?>" target="_blank" class="btn-sm">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="report-details.php?id=<?php echo $report['id']; ?>" class="btn-sm outline">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($report['is_scheduled']): ?>
                                                <span class="format-badge" style="background:#DBEAFE;color:#2563EB;font-size:0.6rem;">
                                                    <i class="fas fa-clock"></i> Scheduled
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <p>No reports generated yet.</p>
                    <p style="font-size:0.8rem;margin-top:4px;">Click "Generate New Report" to create your first report.</p>
                </div>
            <?php endif; ?>
        </div>
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