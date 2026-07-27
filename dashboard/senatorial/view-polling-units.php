<?php
// ============================================================
// SENATORIAL COORDINATOR - VIEW POLLING UNITS
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
// GET LGAS AND WARDS FOR FILTERS
// ============================================================
$lgas = [];
$wards = [];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("SELECT id, name FROM lgas WHERE id IN ($lga_list) AND is_active = 1 ORDER BY name ASC");
        $stmt->execute();
        $lgas = $stmt->fetchAll();
        
        $stmt = $db->prepare("SELECT id, name, lga_id FROM wards WHERE lga_id IN ($lga_list) AND is_active = 1 ORDER BY name ASC");
        $stmt->execute();
        $wards = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching filters: " . $e->getMessage());
}

// ============================================================
// GET POLLING UNITS WITH DETAILS
// ============================================================
$polling_units = [];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("
            SELECT pu.id, pu.name, pu.code, pu.ward_id, pu.registered_voters,
                   w.name as ward_name, w.lga_id,
                   l.name as lga_name,
                   r.id as result_id,
                   r.status as result_status,
                   r.created_at as result_submitted_at,
                   u.full_name as agent_name,
                   c.id as checkin_id,
                   c.checkin_type,
                   c.created_at as checkin_time
            FROM polling_units pu
            JOIN wards w ON pu.ward_id = w.id
            JOIN lgas l ON w.lga_id = l.id
            LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
            LEFT JOIN users u ON r.agent_id = u.id
            LEFT JOIN agent_checkins c ON c.pu_id = pu.id AND c.checkin_type = 'arrival' AND c.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            WHERE l.id IN ($lga_list) AND pu.is_active = 1
            ORDER BY l.name ASC, w.name ASC, pu.name ASC
        ");
        $stmt->execute([$tenant_id]);
        $polling_units = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching polling units: " . $e->getMessage());
    $polling_units = [];
}

// ============================================================
// GET STATISTICS
// ============================================================
$stats = [
    'total' => count($polling_units),
    'submitted' => 0,
    'verified' => 0,
    'pending' => 0,
    'flagged' => 0,
    'checked_in' => 0,
    'not_checked_in' => 0,
    'total_voters' => 0
];

foreach ($polling_units as $pu) {
    $stats['total_voters'] += $pu['registered_voters'] ?? 0;
    
    if ($pu['result_id']) {
        $stats['submitted']++;
        if ($pu['result_status'] === 'verified') $stats['verified']++;
        elseif ($pu['result_status'] === 'flagged') $stats['flagged']++;
    } else {
        $stats['pending']++;
    }
    
    if ($pu['checkin_id']) {
        $stats['checked_in']++;
    } else {
        $stats['not_checked_in']++;
    }
}

$page_title = 'Polling Units';
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
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
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
    font-size: 1.5rem;
    margin-bottom: 4px;
}
.stat-card.blue .stat-number { color: #2563EB; }
.stat-card.blue .stat-icon { color: #2563EB; }
.stat-card.green .stat-number { color: #059669; }
.stat-card.green .stat-icon { color: #059669; }
.stat-card.yellow .stat-number { color: #D97706; }
.stat-card.yellow .stat-icon { color: #D97706; }
.stat-card.purple .stat-number { color: #7C3AED; }
.stat-card.purple .stat-icon { color: #7C3AED; }
.stat-card.orange .stat-number { color: #EA580C; }
.stat-card.orange .stat-icon { color: #EA580C; }
.stat-card.teal .stat-number { color: #0D9488; }
.stat-card.teal .stat-icon { color: #0D9488; }
.stat-card.red .stat-number { color: #DC2626; }
.stat-card.red .stat-icon { color: #DC2626; }

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

.status-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
}
.status-badge.verified { background: #D1FAE5; color: #059669; }
.status-badge.pending { background: #FEF3C7; color: #D97706; }
.status-badge.flagged { background: #FEE2E2; color: #DC2626; }
.status-badge.rejected { background: #FEE2E2; color: #DC2626; }
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
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-flag-checkered" style="color:var(--primary);margin-right:8px;"></i> 
                    Polling Units
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
                <div class="stat-icon"><i class="fas fa-flag-checkered"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
                <div class="stat-label">Total PUs</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['verified']); ?></div>
                <div class="stat-label">Verified</div>
            </div>
            <div class="stat-card yellow">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo number_format($stats['pending']); ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-icon"><i class="fas fa-flag"></i></div>
                <div class="stat-number"><?php echo number_format($stats['flagged']); ?></div>
                <div class="stat-label">Flagged</div>
            </div>
            <div class="stat-card teal">
                <div class="stat-icon"><i class="fas fa-sign-in-alt"></i></div>
                <div class="stat-number"><?php echo number_format($stats['checked_in']); ?></div>
                <div class="stat-label">Checked In</div>
            </div>
            <div class="stat-card purple">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_voters']); ?></div>
                <div class="stat-label">Registered Voters</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="section-card" style="margin-bottom:20px;">
            <div class="card-header">
                <h3><i class="fas fa-filter"></i> Filters</h3>
            </div>
            <div class="filters-row">
                <select id="filterLGA" onchange="loadWardsForFilter(); applyFilters();">
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
                    <option value="verified">Verified</option>
                    <option value="pending">Pending</option>
                    <option value="flagged">Flagged</option>
                </select>
                <input type="text" id="searchInput" placeholder="Search PUs..." onkeyup="applyFilters()">
            </div>
        </div>

        <!-- Table -->
        <div class="section-card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Polling Units List</h3>
                <span style="font-size:0.75rem;color:var(--gray-400);">Showing <?php echo count($polling_units); ?> polling units</span>
            </div>
            <div class="table-wrap">
                <table class="table" id="puTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Ward</th>
                            <th>LGA</th>
                            <th>Voters</th>
                            <th>Status</th>
                            <th>Check-in</th>
                            <th>Agent</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($polling_units) > 0): ?>
                            <?php $i = 1; foreach ($polling_units as $pu): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($pu['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($pu['code']); ?></td>
                                    <td><?php echo htmlspecialchars($pu['ward_name']); ?></td>
                                    <td><?php echo htmlspecialchars($pu['lga_name']); ?></td>
                                    <td><?php echo number_format($pu['registered_voters'] ?? 0); ?></td>
                                    <td>
                                        <?php if ($pu['result_id']): ?>
                                            <span class="status-badge <?php echo $pu['result_status'] ?? 'pending'; ?>">
                                                <?php echo ucfirst($pu['result_status'] ?? 'Submitted'); ?>
                                            </span>
                                            <?php if ($pu['result_submitted_at']): ?>
                                                <br><span style="font-size:0.6rem;color:var(--gray-400);">
                                                    <?php echo date('M j, H:i', strtotime($pu['result_submitted_at'])); ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="status-badge pending">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($pu['checkin_id']): ?>
                                            <span class="status-badge checked-in">Checked In</span>
                                            <?php if ($pu['checkin_time']): ?>
                                                <br><span style="font-size:0.6rem;color:var(--gray-400);">
                                                    <?php echo date('M j, H:i', strtotime($pu['checkin_time'])); ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="status-badge not-checked">Not Checked</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:0.7rem;color:var(--gray-600);">
                                        <?php echo htmlspecialchars($pu['agent_name'] ?? '—'); ?>
                                    </td>
                                    <td>
                                        <a href="pu-details.php?id=<?php echo $pu['id']; ?>" class="btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <p>No polling units found in this senatorial district.</p>
                                        <p style="font-size:0.8rem;margin-top:4px;">Please add polling units to see them here.</p>
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
    var lga = document.getElementById('filterLGA').value;
    var ward = document.getElementById('filterWard').value;
    var status = document.getElementById('filterStatus').value;
    var search = document.getElementById('searchInput').value.toLowerCase();
    var rows = document.querySelectorAll('#puTable tbody tr');
    
    rows.forEach(function(row) {
        var show = true;
        var cells = row.querySelectorAll('td');
        if (cells.length < 10) return;
        
        var rowLga = cells[4]?.textContent?.trim() || '';
        var rowWard = cells[3]?.textContent?.trim() || '';
        var rowStatus = cells[6]?.textContent?.trim().toLowerCase() || '';
        var rowName = cells[1]?.textContent?.trim().toLowerCase() || '';
        var rowCode = cells[2]?.textContent?.trim().toLowerCase() || '';
        
        if (lga && rowLga !== lga) show = false;
        if (ward && rowWard !== ward) show = false;
        if (status && !rowStatus.includes(status)) show = false;
        if (search && !rowName.includes(search) && !rowCode.includes(search)) show = false;
        
        row.style.display = show ? '' : 'none';
    });
}

function loadWardsForFilter() {
    var lgaId = document.getElementById('filterLGA').value;
    var wardSelect = document.getElementById('filterWard');
    
    if (!lgaId) {
        wardSelect.innerHTML = '<option value="">All Wards</option>';
        return;
    }
    
    wardSelect.innerHTML = '<option value="">Loading...</option>';
    
    fetch('ajax/get_wards.php?lga_id=' + lgaId)
        .then(function(response) { return response.json(); })
        .then(function(data) {
            wardSelect.innerHTML = '<option value="">All Wards</option>';
            if (data && data.length > 0) {
                data.forEach(function(ward) {
                    var option = document.createElement('option');
                    option.value = ward.id;
                    option.textContent = ward.name;
                    wardSelect.appendChild(option);
                });
            }
        })
        .catch(function() {
            wardSelect.innerHTML = '<option value="">All Wards</option>';
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