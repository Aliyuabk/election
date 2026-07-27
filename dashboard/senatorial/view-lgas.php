<?php
// ============================================================
// SENATORIAL COORDINATOR - VIEW LGAs
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
// GET LGAS WITH DETAILS
// ============================================================
$lgas = [];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("
            SELECT l.id, l.name, l.code,
                   COUNT(DISTINCT w.id) as ward_count,
                   COUNT(DISTINCT pu.id) as pu_count,
                   COUNT(DISTINCT u.id) as coordinator_count,
                   COUNT(DISTINCT CASE WHEN u.role_id = (SELECT id FROM roles WHERE level = 'pu_agent' LIMIT 1) THEN u.id END) as agent_count,
                   COUNT(DISTINCT CASE WHEN u.role_id = (SELECT id FROM roles WHERE level = 'party_agent' LIMIT 1) THEN u.id END) as party_agent_count
            FROM lgas l
            LEFT JOIN wards w ON w.lga_id = l.id AND w.is_active = 1
            LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
            LEFT JOIN users u ON u.lga_id = l.id AND u.status = 'active'
            WHERE l.id IN ($lga_list) AND l.is_active = 1
            GROUP BY l.id, l.name, l.code
            ORDER BY l.name ASC
        ");
        $stmt->execute();
        $lgas = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching LGAs: " . $e->getMessage());
    $lgas = [];
}

// ============================================================
// GET ELECTION PROGRESS BY LGA
// ============================================================
$lga_progress = [];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("
            SELECT 
                l.id as lga_id,
                l.name as lga_name,
                COUNT(DISTINCT pu.id) as total_pus,
                COUNT(DISTINCT CASE WHEN r.id IS NOT NULL THEN pu.id END) as results_submitted,
                COUNT(DISTINCT CASE WHEN r.status = 'verified' THEN pu.id END) as results_verified
            FROM lgas l
            LEFT JOIN wards w ON w.lga_id = l.id AND w.is_active = 1
            LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
            LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
            WHERE l.id IN ($lga_list)
            GROUP BY l.id, l.name
            ORDER BY l.name ASC
        ");
        $stmt->execute([$tenant_id]);
        $lga_progress = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching LGA progress: " . $e->getMessage());
    $lga_progress = [];
}

$page_title = 'LGAs';
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
}
.stat-card .stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gray-800);
}
.stat-card .stat-label {
    font-size: 0.75rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.stat-card .stat-icon {
    font-size: 1.5rem;
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
.stat-card.teal .stat-number { color: #0D9488; }
.stat-card.teal .stat-icon { color: #0D9488; }

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

.progress-bar {
    height: 6px;
    background: var(--gray-200);
    border-radius: 4px;
    overflow: hidden;
    margin-top: 4px;
}
.progress-bar .progress-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}
.progress-bar .progress-fill.green { background: #10B981; }
.progress-bar .progress-fill.blue { background: #3B82F6; }
.progress-bar .progress-fill.yellow { background: #F59E0B; }
.progress-bar .progress-fill.red { background: #EF4444; }

.status-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
}
.status-badge.active { background: #DBEAFE; color: #2563EB; }
.status-badge.pending { background: #FEF3C7; color: #D97706; }
.status-badge.completed { background: #D1FAE5; color: #059669; }

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

.filters-row {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}
.filters-row select,
.filters-row input {
    padding: 8px 14px;
    border: 1.5px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.8rem;
    background: white;
}

@media (max-width: 768px) {
    .stats-grid {
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
                    <i class="fas fa-map-marker-alt" style="color:var(--primary);margin-right:8px;"></i> 
                    Local Government Areas (LGAs)
                    <small><?php echo htmlspecialchars($district_name); ?> - <?php echo htmlspecialchars($state_name); ?></small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="monitor-district.php" class="btn btn-secondary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--gray-100);color:var(--gray-600);border:none;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-icon"><i class="fas fa-map-marker-alt"></i></div>
                <div class="stat-number"><?php echo number_format(count($lgas)); ?></div>
                <div class="stat-label">Total LGAs</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                <div class="stat-number"><?php echo number_format(array_sum(array_column($lgas, 'ward_count'))); ?></div>
                <div class="stat-label">Total Wards</div>
            </div>
            <div class="stat-card purple">
                <div class="stat-icon"><i class="fas fa-flag-checkered"></i></div>
                <div class="stat-number"><?php echo number_format(array_sum(array_column($lgas, 'pu_count'))); ?></div>
                <div class="stat-label">Total Polling Units</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
                <div class="stat-number"><?php echo number_format(array_sum(array_column($lgas, 'coordinator_count'))); ?></div>
                <div class="stat-label">Coordinators</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="section-card" style="margin-bottom:20px;">
            <div class="card-header">
                <h3><i class="fas fa-filter"></i> Filters</h3>
            </div>
            <div class="filters-row">
                <input type="text" id="searchInput" placeholder="Search LGAs..." onkeyup="applyFilters()">
                <select id="filterStatus" onchange="applyFilters()">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="pending">Pending</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div class="section-card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> LGAs List</h3>
                <span style="font-size:0.75rem;color:var(--gray-400);">Showing <?php echo count($lgas); ?> LGAs</span>
            </div>
            <div class="table-wrap">
                <table class="table" id="lgaTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Wards</th>
                            <th>Polling Units</th>
                            <th>Coordinators</th>
                            <th>Agents</th>
                            <th>Progress</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($lgas) > 0): ?>
                            <?php $i = 1; foreach ($lgas as $lga): 
                                // Find progress for this LGA
                                $progress = ['total_pus' => 0, 'results_submitted' => 0, 'results_verified' => 0];
                                foreach ($lga_progress as $p) {
                                    if ($p['lga_id'] == $lga['id']) {
                                        $progress = $p;
                                        break;
                                    }
                                }
                                $coverage = $progress['total_pus'] > 0 ? round(($progress['results_submitted'] / $progress['total_pus']) * 100, 1) : 0;
                                $color = $coverage >= 80 ? 'green' : ($coverage >= 50 ? 'blue' : ($coverage >= 30 ? 'yellow' : 'red'));
                                $status = $coverage >= 80 ? 'completed' : ($coverage > 0 ? 'active' : 'pending');
                            ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($lga['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($lga['code']); ?></td>
                                    <td><?php echo number_format($lga['ward_count'] ?? 0); ?></td>
                                    <td><?php echo number_format($lga['pu_count'] ?? 0); ?></td>
                                    <td><?php echo number_format($lga['coordinator_count'] ?? 0); ?></td>
                                    <td><?php echo number_format(($lga['agent_count'] ?? 0) + ($lga['party_agent_count'] ?? 0)); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $status; ?>">
                                            <?php echo $coverage; ?>%
                                        </span>
                                        <div class="progress-bar">
                                            <div class="progress-fill <?php echo $color; ?>" style="width:<?php echo min($coverage, 100); ?>%"></div>
                                        </div>
                                        <span style="font-size:0.65rem;color:var(--gray-400);">
                                            <?php echo number_format($progress['results_submitted']); ?>/<?php echo number_format($progress['total_pus']); ?> submitted
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view-lga-details.php?id=<?php echo $lga['id']; ?>" class="btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <p>No LGAs found in this senatorial district.</p>
                                        <p style="font-size:0.8rem;margin-top:4px;">Please add LGAs to see them here.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
// ============================================================
// FILTER FUNCTIONALITY
// ============================================================
function applyFilters() {
    var search = document.getElementById('searchInput').value.toLowerCase();
    var status = document.getElementById('filterStatus').value;
    var rows = document.querySelectorAll('#lgaTable tbody tr');
    
    rows.forEach(function(row) {
        var show = true;
        var cells = row.querySelectorAll('td');
        if (cells.length < 9) return;
        
        var rowName = cells[1]?.textContent?.trim().toLowerCase() || '';
        var rowStatus = cells[7]?.textContent?.trim().toLowerCase() || '';
        
        if (search && !rowName.includes(search)) {
            show = false;
        }
        if (status && !rowStatus.includes(status)) {
            show = false;
        }
        
        row.style.display = show ? '' : 'none';
    });
}

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