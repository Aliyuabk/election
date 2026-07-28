<?php
// ============================================================
// SENATORIAL COORDINATOR - PERSONNEL PERFORMANCE REPORT
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
// GET FILTERS
// ============================================================
$election_id = isset($_GET['election']) ? (int)$_GET['election'] : 0;
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';

// ============================================================
// GET ELECTIONS
// ============================================================
$elections = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, type, election_date, status 
        FROM elections 
        WHERE tenant_id = ? AND deleted_at IS NULL
        ORDER BY election_date DESC
    ");
    $stmt->execute([$tenant_id]);
    $elections = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching elections: " . $e->getMessage());
}

// ============================================================
// GET PERSONNEL DATA
// ============================================================
$personnel = [];
$summary = [
    'total' => 0,
    'federal_constituency' => 0,
    'lga' => 0,
    'ward' => 0,
    'pu_agent' => 0,
    'party_agent' => 0,
    'volunteer' => 0,
    'observer' => 0,
    'active' => 0,
    'suspended' => 0,
    'pending' => 0
];

try {
    $role_condition = !empty($role_filter) ? "AND r.level = ?" : "";
    $params = [$tenant_id];
    if (!empty($role_filter)) {
        $params[] = $role_filter;
    }
    
    $query = "
        SELECT 
            u.id, u.first_name, u.last_name, u.email, u.phone,
            u.status, u.created_at, u.last_login_at,
            r.name as role_name, r.level as role_level,
            l.name as lga_name,
            w.name as ward_name,
            pu.name as pu_name,
            COUNT(DISTINCT a.id) as activity_count,
            COUNT(DISTINCT r2.id) as result_count
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN lgas l ON u.lga_id = l.id
        LEFT JOIN wards w ON u.ward_id = w.id
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        LEFT JOIN activity_logs a ON a.user_id = u.id
        LEFT JOIN results_ec8a r2 ON r2.agent_id = u.id
        WHERE u.tenant_id = ? AND u.status IN ('active', 'suspended', 'pending')
        AND r.level IN ('federal_constituency', 'lga', 'ward', 'pu_agent', 'party_agent', 'volunteer', 'observer')
        $role_condition
        GROUP BY u.id, u.first_name, u.last_name, u.email, u.phone,
                 u.status, u.created_at, u.last_login_at,
                 r.name, r.level, l.name, w.name, pu.name
        ORDER BY r.level, u.last_name, u.first_name
    ";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $personnel = $stmt->fetchAll();
    
    foreach ($personnel as $p) {
        $summary['total']++;
        $role = $p['role_level'];
        if (isset($summary[$role])) {
            $summary[$role]++;
        }
        if ($p['status'] === 'active') $summary['active']++;
        elseif ($p['status'] === 'suspended') $summary['suspended']++;
        elseif ($p['status'] === 'pending') $summary['pending']++;
    }
} catch (Exception $e) {
    error_log("Error fetching personnel data: " . $e->getMessage());
}

// Get role types for filter
$role_types = [
    'federal_constituency' => 'Federal Constituency Coordinators',
    'lga' => 'LGA Coordinators',
    'ward' => 'Ward Coordinators',
    'pu_agent' => 'PU Agents',
    'party_agent' => 'Party Agents',
    'volunteer' => 'Volunteers',
    'observer' => 'Observers'
];

$page_title = 'Personnel Performance Report';
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

.filter-section {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
    flex-wrap: wrap;
    background: white;
    padding: 16px 20px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}
.filter-section select {
    padding: 8px 14px;
    border: 1.5px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.8rem;
    background: white;
    min-width: 200px;
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
    transition: var(--transition);
}
.filter-section .btn-filter:hover {
    background: var(--primary-dark);
}
.filter-section .btn-print {
    padding: 8px 20px;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    background: white;
    color: var(--gray-600);
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    transition: var(--transition);
}
.filter-section .btn-print:hover {
    background: var(--gray-50);
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
.stat-card.teal .stat-number { color: #0D9488; }
.stat-card.teal .stat-icon { color: #0D9488; }
.stat-card.yellow .stat-number { color: #D97706; }
.stat-card.yellow .stat-icon { color: #D97706; }
.stat-card.red .stat-number { color: #DC2626; }
.stat-card.red .stat-icon { color: #DC2626; }

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
.status-badge.active { background: #D1FAE5; color: #059669; }
.status-badge.suspended { background: #FEE2E2; color: #DC2626; }
.status-badge.pending { background: #FEF3C7; color: #D97706; }

.role-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
}
.role-badge.federal_constituency { background: #EDE9FE; color: #7C3AED; }
.role-badge.lga { background: #DBEAFE; color: #2563EB; }
.role-badge.ward { background: #D1FAE5; color: #059669; }
.role-badge.pu_agent { background: #FEF3C7; color: #D97706; }
.role-badge.party_agent { background: #FFEDD5; color: #EA580C; }
.role-badge.volunteer { background: #CCFBF1; color: #0D9488; }
.role-badge.observer { background: #E0E7FF; color: #4F46E5; }

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

@media print {
    .filter-section, .page-header .btn, .sidebar, .dashboard-header { display: none !important; }
    .main-content { margin-left: 0 !important; }
    .stats-grid { break-inside: avoid; }
    .section-card { break-inside: avoid; border: 1px solid #ddd !important; }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .filter-section {
        flex-direction: column;
    }
    .filter-section select {
        min-width: unset;
        width: 100%;
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
                    <i class="fas fa-user-chart" style="color:var(--primary);margin-right:8px;"></i> 
                    Personnel Performance Report
                    <small><?php echo htmlspecialchars($district_name); ?> - <?php echo htmlspecialchars($state_name); ?></small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button onclick="window.print()" class="btn btn-secondary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--gray-100);color:var(--gray-600);border:none;">
                    <i class="fas fa-print"></i> Print
                </button>
                <a href="reports.php" class="btn btn-secondary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--gray-100);color:var(--gray-600);border:none;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Filter -->
        <div class="filter-section">
            <form method="GET" action="" style="display:flex;gap:12px;flex-wrap:wrap;flex:1;align-items:center;">
                <select name="role">
                    <option value="">All Roles</option>
                    <?php foreach ($role_types as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo ($role_filter === $key) ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Apply</button>
                <a href="report-personnel.php" class="btn-filter" style="background:var(--gray-100);color:var(--gray-600);">Reset</a>
            </form>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo number_format($summary['total']); ?></div>
                <div class="stat-label">Total Personnel</div>
            </div>
            <div class="stat-card purple">
                <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
                <div class="stat-number"><?php echo number_format($summary['federal_constituency']); ?></div>
                <div class="stat-label">Federal Constituency</div>
            </div>
            <div class="stat-card teal">
                <div class="stat-icon"><i class="fas fa-map-marker-alt"></i></div>
                <div class="stat-number"><?php echo number_format($summary['lga']); ?></div>
                <div class="stat-label">LGA Coordinators</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                <div class="stat-number"><?php echo number_format($summary['ward']); ?></div>
                <div class="stat-label">Ward Coordinators</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-icon"><i class="fas fa-flag-checkered"></i></div>
                <div class="stat-number"><?php echo number_format($summary['pu_agent']); ?></div>
                <div class="stat-label">PU Agents</div>
            </div>
            <div class="stat-card yellow">
                <div class="stat-icon"><i class="fas fa-flag"></i></div>
                <div class="stat-number"><?php echo number_format($summary['party_agent']); ?></div>
                <div class="stat-label">Party Agents</div>
            </div>
            <div class="stat-card red">
                <div class="stat-icon"><i class="fas fa-pause"></i></div>
                <div class="stat-number"><?php echo number_format($summary['suspended']); ?></div>
                <div class="stat-label">Suspended</div>
            </div>
        </div>

        <!-- Personnel Table -->
        <div class="section-card">
            <div class="card-header">
                <h3><i class="fas fa-list" style="color:var(--primary);margin-right:6px;"></i> Personnel List</h3>
                <span style="font-size:0.75rem;color:var(--gray-400);"><?php echo count($personnel); ?> personnel found</span>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Jurisdiction</th>
                            <th>Status</th>
                            <th>Activities</th>
                            <th>Results</th>
                            <th>Last Login</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($personnel) > 0): ?>
                            <?php $i = 1; foreach ($personnel as $p): 
                                $jurisdiction = [];
                                if ($p['lga_name']) $jurisdiction[] = $p['lga_name'];
                                if ($p['ward_name']) $jurisdiction[] = $p['ward_name'];
                                if ($p['pu_name']) $jurisdiction[] = $p['pu_name'];
                                $jurisdiction_text = !empty($jurisdiction) ? implode(' → ', $jurisdiction) : '—';
                            ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></strong>
                                        <br><span style="font-size:0.6rem;color:var(--gray-400);"><?php echo htmlspecialchars($p['email'] ?? ''); ?></span>
                                    </td>
                                    <td>
                                        <span class="role-badge <?php echo $p['role_level']; ?>">
                                            <?php echo htmlspecialchars($p['role_name']); ?>
                                        </span>
                                    </td>
                                    <td style="font-size:0.7rem;color:var(--gray-600);">
                                        <?php echo htmlspecialchars($jurisdiction_text); ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $p['status']; ?>">
                                            <?php echo ucfirst($p['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($p['activity_count'] ?? 0); ?></td>
                                    <td><?php echo number_format($p['result_count'] ?? 0); ?></td>
                                    <td style="font-size:0.7rem;color:var(--gray-500);">
                                        <?php echo $p['last_login_at'] ? date('M j, Y', strtotime($p['last_login_at'])) : '—'; ?>
                                    </td>
                                    <td>
                                        <a href="../client-admin/user-details.php?id=<?php echo $p['id']; ?>" class="btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9">
                                    <div class="empty-state">
                                        <i class="fas fa-users"></i>
                                        <p>No personnel found.</p>
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