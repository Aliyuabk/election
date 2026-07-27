<?php
// ============================================================
// SENATORIAL COORDINATOR - MONITOR SENATORIAL DISTRICT
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
$user_id = SessionManager::get('user_id');
$senatorial_id = SessionManager::get('senatorial_id');
$state_id = SessionManager::get('state_id');
$tenant_id = SessionManager::get('tenant_id');

$db = getDB();

// Get Senatorial District name
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
    $district_name = 'Senatorial District';
    $state_name = 'State';
}

// Get LGAs in this senatorial district
$lga_ids = [];
try {
    $stmt = $db->prepare("SELECT lgas_json FROM senatorial_districts WHERE id = ?");
    $stmt->execute([$senatorial_id]);
    $lgas_json = $stmt->fetchColumn();
    if ($lgas_json) {
        $lga_ids = json_decode($lgas_json, true) ?: [];
    }
} catch (Exception $e) {
    $lga_ids = [];
}

$lga_list = !empty($lga_ids) ? implode(',', array_map('intval', $lga_ids)) : '0';

// Get Federal Constituencies
$federal_constituencies = [];
try {
    if (!empty($lga_ids)) {
        $stmt = $db->prepare("
            SELECT DISTINCT fc.id, fc.name, fc.code,
                   COUNT(DISTINCT l.id) as lga_count,
                   COUNT(DISTINCT w.id) as ward_count,
                   COUNT(DISTINCT pu.id) as pu_count
            FROM federal_constituencies fc
            LEFT JOIN lgas l ON l.state_id = fc.state_id
            LEFT JOIN wards w ON w.lga_id = l.id
            LEFT JOIN polling_units pu ON pu.ward_id = w.id
            WHERE fc.state_id = ? AND fc.is_active = 1
            GROUP BY fc.id, fc.name, fc.code
            ORDER BY fc.name ASC
        ");
        $stmt->execute([$state_id]);
        $federal_constituencies = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $federal_constituencies = [];
}

// Get LGAs
$lgas = [];
try {
    if (!empty($lga_ids)) {
        $stmt = $db->prepare("
            SELECT l.id, l.name, l.code,
                   COUNT(DISTINCT w.id) as ward_count,
                   COUNT(DISTINCT pu.id) as pu_count,
                   COUNT(DISTINCT u.id) as coordinator_count
            FROM lgas l
            LEFT JOIN wards w ON w.lga_id = l.id
            LEFT JOIN polling_units pu ON pu.ward_id = w.id
            LEFT JOIN users u ON u.lga_id = l.id AND u.role_id = (SELECT id FROM roles WHERE level = 'lga' LIMIT 1)
            WHERE l.id IN ($lga_list) AND l.is_active = 1
            GROUP BY l.id, l.name, l.code
            ORDER BY l.name ASC
        ");
        $stmt->execute();
        $lgas = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $lgas = [];
}

// Get Wards
$wards = [];
try {
    if (!empty($lga_ids)) {
        $stmt = $db->prepare("
            SELECT w.id, w.name, w.code, w.lga_id,
                   l.name as lga_name,
                   COUNT(DISTINCT pu.id) as pu_count
            FROM wards w
            JOIN lgas l ON w.lga_id = l.id
            LEFT JOIN polling_units pu ON pu.ward_id = w.id
            WHERE l.id IN ($lga_list) AND w.is_active = 1
            GROUP BY w.id, w.name, w.code, w.lga_id, l.name
            ORDER BY w.name ASC
            LIMIT 50
        ");
        $stmt->execute();
        $wards = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $wards = [];
}

// Get Polling Units
$polling_units = [];
try {
    if (!empty($lga_ids)) {
        $stmt = $db->prepare("
            SELECT pu.id, pu.name, pu.code, pu.ward_id,
                   w.name as ward_name, l.name as lga_name,
                   pu.registered_voters,
                   CASE WHEN r.id IS NOT NULL THEN 'submitted' ELSE 'pending' END as result_status
            FROM polling_units pu
            JOIN wards w ON pu.ward_id = w.id
            JOIN lgas l ON w.lga_id = l.id
            LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
            WHERE l.id IN ($lga_list) AND pu.is_active = 1
            ORDER BY pu.name ASC
            LIMIT 50
        ");
        $stmt->execute([$tenant_id]);
        $polling_units = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $polling_units = [];
}

// Get Coordinator Activities
$coordinator_activities = [];
try {
    $stmt = $db->prepare("
        SELECT a.*, u.full_name as user_name, u.photograph_url,
               r.name as role_name
        FROM activity_logs a
        JOIN users u ON a.user_id = u.id
        JOIN roles r ON u.role_id = r.id
        WHERE a.tenant_id = ? 
        AND r.level IN ('federal_constituency', 'lga', 'ward')
        ORDER BY a.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$tenant_id]);
    $coordinator_activities = $stmt->fetchAll();
} catch (Exception $e) {
    $coordinator_activities = [];
}

// Get Check-in Status
$checkin_status = [];
try {
    if (!empty($lga_ids)) {
        $stmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT pu.id) as total_pus,
                SUM(CASE WHEN c.id IS NOT NULL THEN 1 ELSE 0 END) as checked_in,
                SUM(CASE WHEN c.id IS NULL THEN 1 ELSE 0 END) as not_checked_in
            FROM polling_units pu
            JOIN wards w ON pu.ward_id = w.id
            LEFT JOIN agent_checkins c ON c.pu_id = pu.id AND c.checkin_type = 'arrival'
            WHERE w.lga_id IN ($lga_list) AND pu.is_active = 1
        ");
        $stmt->execute();
        $checkin_status = $stmt->fetch();
    }
} catch (Exception $e) {
    $checkin_status = ['total_pus' => 0, 'checked_in' => 0, 'not_checked_in' => 0];
}

// Get Election Progress
$election_progress = [];
try {
    if (!empty($lga_ids)) {
        $stmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT pu.id) as total_pus,
                COUNT(DISTINCT CASE WHEN r.id IS NOT NULL THEN pu.id END) as results_submitted,
                COUNT(DISTINCT CASE WHEN r.status = 'verified' THEN pu.id END) as results_verified
            FROM polling_units pu
            JOIN wards w ON pu.ward_id = w.id
            LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
            WHERE w.lga_id IN ($lga_list) AND pu.is_active = 1
        ");
        $stmt->execute([$tenant_id]);
        $election_progress = $stmt->fetch();
    }
} catch (Exception $e) {
    $election_progress = ['total_pus' => 0, 'results_submitted' => 0, 'results_verified' => 0];
}

$page_title = 'Monitor Senatorial District';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
/* Monitor District Styles */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
.stat-card .stat-change {
    font-size: 0.7rem;
    margin-top: 4px;
    color: var(--gray-400);
}
.stat-card .stat-icon {
    font-size: 1.5rem;
    margin-bottom: 8px;
}
.stat-icon.blue { color: #2563EB; }
.stat-icon.green { color: #059669; }
.stat-icon.purple { color: #7C3AED; }
.stat-icon.orange { color: #EA580C; }
.stat-icon.red { color: #DC2626; }
.stat-icon.teal { color: #0D9488; }

.section-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
    margin-bottom: 20px;
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
.status-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
}
.status-badge.active { background: #DBEAFE; color: #2563EB; }
.status-badge.pending { background: #FEF3C7; color: #D97706; }
.status-badge.submitted { background: #D1FAE5; color: #059669; }
.status-badge.verified { background: #D1FAE5; color: #059669; }
.status-badge.checked-in { background: #D1FAE5; color: #059669; }
.status-badge.not-checked { background: #FEE2E2; color: #DC2626; }

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

.grid-2col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

@media (max-width: 768px) {
    .grid-2col {
        grid-template-columns: 1fr;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
            <div>
                <h2 style="font-size:1.3rem;font-weight:700;">
                    <i class="fas fa-university" style="color:var(--primary);margin-right:8px;"></i> 
                    Monitor Senatorial District
                    <small style="font-size:0.8rem;font-weight:400;color:var(--gray-500);display:block;margin-top:2px;">
                        <?php echo htmlspecialchars($district_name); ?> - <?php echo htmlspecialchars($state_name); ?>
                    </small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="view-federal-constituencies.php" class="btn btn-primary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--primary);color:white;border:none;">
                    <i class="fas fa-building"></i> View Constituencies
                </a>
                <a href="view-lgas.php" class="btn btn-secondary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--gray-100);color:var(--gray-600);border:none;">
                    <i class="fas fa-map-marker-alt"></i> View LGAs
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-building"></i></div>
                <div class="stat-number"><?php echo number_format(count($federal_constituencies)); ?></div>
                <div class="stat-label">Federal Constituencies</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-map-marker-alt"></i></div>
                <div class="stat-number"><?php echo number_format(count($lgas)); ?></div>
                <div class="stat-label">LGAs</div>
                <div class="stat-change"><?php echo number_format(array_sum(array_column($lgas, 'ward_count'))); ?> wards</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon teal"><i class="fas fa-flag-checkered"></i></div>
                <div class="stat-number"><?php echo number_format($election_progress['total_pus'] ?? 0); ?></div>
                <div class="stat-label">Polling Units</div>
                <div class="stat-change"><?php echo number_format($election_progress['results_submitted'] ?? 0); ?> submitted</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php 
                    $total = $election_progress['total_pus'] ?? 1;
                    $submitted = $election_progress['results_submitted'] ?? 0;
                    echo number_format(($submitted / $total) * 100, 1);
                ?>%</div>
                <div class="stat-label">Progress</div>
                <div class="stat-change"><?php echo number_format($election_progress['results_verified'] ?? 0); ?> verified</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-user-check"></i></div>
                <div class="stat-number"><?php echo number_format($checkin_status['checked_in'] ?? 0); ?></div>
                <div class="stat-label">Checked In</div>
                <div class="stat-change"><?php echo number_format($checkin_status['not_checked_in'] ?? 0); ?> not checked</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo number_format($election_progress['total_pus'] - $election_progress['results_submitted'] ?? 0); ?></div>
                <div class="stat-label">Pending Results</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="section-card">
            <div class="card-header">
                <h3><i class="fas fa-filter"></i> Filters</h3>
            </div>
            <div class="filters-row">
                <select id="filterConstituency" onchange="applyFilters()">
                    <option value="">All Federal Constituencies</option>
                    <?php foreach ($federal_constituencies as $fc): ?>
                        <option value="<?php echo $fc['id']; ?>"><?php echo htmlspecialchars($fc['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="filterLGA" onchange="applyFilters()">
                    <option value="">All LGAs</option>
                    <?php foreach ($lgas as $lga): ?>
                        <option value="<?php echo $lga['id']; ?>"><?php echo htmlspecialchars($lga['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="filterWard" onchange="applyFilters()">
                    <option value="">All Wards</option>
                    <?php foreach ($wards as $ward): ?>
                        <option value="<?php echo $ward['id']; ?>"><?php echo htmlspecialchars($ward['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="filterStatus" onchange="applyFilters()">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="submitted">Submitted</option>
                    <option value="verified">Verified</option>
                </select>
                <input type="text" id="searchInput" placeholder="Search..." onkeyup="applyFilters()">
            </div>
        </div>

        <!-- Polling Units Table -->
        <div class="section-card">
            <div class="card-header">
                <h3><i class="fas fa-flag-checkered"></i> Polling Units Status</h3>
                <a href="view-polling-units.php">View All →</a>
            </div>
            <div class="table-wrap">
                <table class="table" id="puTable">
                    <thead>
                        <tr>
                            <th>PU Name</th>
                            <th>Code</th>
                            <th>Ward</th>
                            <th>LGA</th>
                            <th>Voters</th>
                            <th>Status</th>
                            <th>Check-in</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($polling_units) > 0): ?>
                            <?php foreach ($polling_units as $pu): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($pu['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($pu['code']); ?></td>
                                    <td><?php echo htmlspecialchars($pu['ward_name']); ?></td>
                                    <td><?php echo htmlspecialchars($pu['lga_name']); ?></td>
                                    <td><?php echo number_format($pu['registered_voters'] ?? 0); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $pu['result_status'] ?? 'pending'; ?>">
                                            <?php echo ucfirst($pu['result_status'] ?? 'pending'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo ($pu['checkin_status'] ?? 'not-checked'); ?>">
                                            <?php echo ucfirst($pu['checkin_status'] ?? 'Not Checked'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="pu-details.php?id=<?php echo $pu['id']; ?>" class="btn btn-sm" style="padding:4px 12px;border-radius:6px;background:var(--primary);color:white;text-decoration:none;font-size:0.7rem;">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center;padding:30px;color:var(--gray-500);">
                                    <i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
                                    No polling units found in this senatorial district.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Two Column Layout -->
        <div class="grid-2col">
            <!-- Federal Constituencies -->
            <div class="section-card">
                <div class="card-header">
                    <h3><i class="fas fa-building"></i> Federal Constituencies</h3>
                    <a href="view-federal-constituencies.php">View All →</a>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>LGAs</th>
                                <th>Wards</th>
                                <th>PUs</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($federal_constituencies) > 0): ?>
                                <?php foreach (array_slice($federal_constituencies, 0, 5) as $fc): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($fc['name']); ?></td>
                                        <td><?php echo $fc['lga_count'] ?? 0; ?></td>
                                        <td><?php echo $fc['ward_count'] ?? 0; ?></td>
                                        <td><?php echo $fc['pu_count'] ?? 0; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" style="text-align:center;color:var(--gray-500);">No federal constituencies</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Coordinator Activities -->
            <div class="section-card">
                <div class="card-header">
                    <h3><i class="fas fa-clock"></i> Coordinator Activities</h3>
                    <a href="coordinator-activities.php">View All →</a>
                </div>
                <?php if (count($coordinator_activities) > 0): ?>
                    <?php foreach (array_slice($coordinator_activities, 0, 6) as $activity): ?>
                        <div style="display:flex;align-items:flex-start;gap:12px;padding:8px 0;border-bottom:1px solid var(--gray-100);">
                            <div style="width:32px;height:32px;border-radius:50%;background:var(--gray-100);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:0.7rem;font-weight:600;color:var(--gray-600);">
                                <?php echo strtoupper(substr($activity['user_name'] ?? 'U', 0, 2)); ?>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="font-size:0.82rem;font-weight:500;color:var(--gray-700);">
                                    <?php echo htmlspecialchars($activity['user_name'] ?? 'Unknown'); ?>
                                    <span style="font-weight:400;color:var(--gray-400);font-size:0.7rem;">
                                        (<?php echo htmlspecialchars($activity['role_name'] ?? 'Coordinator'); ?>)
                                    </span>
                                </div>
                                <div style="font-size:0.78rem;color:var(--gray-500);" class="text-truncate">
                                    <?php echo htmlspecialchars($activity['description'] ?? ''); ?>
                                </div>
                                <div style="font-size:0.65rem;color:var(--gray-400);">
                                    <?php echo date('M j, Y g:i A', strtotime($activity['created_at'] ?? 'now')); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color:var(--gray-500);padding:16px 0;text-align:center;">No recent activities</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
// ============================================================
// FILTER FUNCTIONALITY
// ============================================================
function applyFilters() {
    var constituency = document.getElementById('filterConstituency').value;
    var lga = document.getElementById('filterLGA').value;
    var ward = document.getElementById('filterWard').value;
    var status = document.getElementById('filterStatus').value;
    var search = document.getElementById('searchInput').value.toLowerCase();
    
    var rows = document.querySelectorAll('#puTable tbody tr');
    
    rows.forEach(function(row) {
        var show = true;
        var cells = row.querySelectorAll('td');
        
        if (cells.length < 8) return;
        
        // Get values
        var rowLga = cells[3]?.textContent?.trim() || '';
        var rowWard = cells[2]?.textContent?.trim() || '';
        var rowStatus = cells[5]?.textContent?.trim().toLowerCase() || '';
        var rowName = cells[0]?.textContent?.trim().toLowerCase() || '';
        var rowCode = cells[1]?.textContent?.trim().toLowerCase() || '';
        
        // Apply filters
        if (lga && rowLga !== lga) show = false;
        if (ward && rowWard !== ward) show = false;
        if (status && rowStatus !== status) show = false;
        if (search && !rowName.includes(search) && !rowCode.includes(search)) show = false;
        
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

// ============================================================
// SIDEBAR DROPDOWNS
// ============================================================
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

// ============================================================
// PROFILE DROPDOWN
// ============================================================
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

// ============================================================
// PRELOADER
// ============================================================
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