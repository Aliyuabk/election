<?php
// ============================================================
// SENATORIAL COORDINATOR - FILTER BY LGA
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
// GET SELECTED LGA
// ============================================================
$selected_lga = isset($_GET['lga_id']) ? (int)$_GET['lga_id'] : 0;

// ============================================================
// GET ALL LGAS FOR FILTER
// ============================================================
$lgas = [];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("SELECT id, name FROM lgas WHERE id IN ($lga_list) AND is_active = 1 ORDER BY name ASC");
        $stmt->execute();
        $lgas = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching LGAs: " . $e->getMessage());
}

// ============================================================
// GET SELECTED LGA DATA
// ============================================================
$lga_data = null;
if ($selected_lga > 0) {
    try {
        $stmt = $db->prepare("
            SELECT 
                l.id, l.name, l.code,
                COUNT(DISTINCT w.id) as ward_count,
                COUNT(DISTINCT pu.id) as pu_count,
                COUNT(DISTINCT u.id) as coordinator_count,
                COUNT(DISTINCT CASE WHEN u.role_id = (SELECT id FROM roles WHERE level = 'pu_agent' LIMIT 1) THEN u.id END) as agent_count,
                COUNT(DISTINCT CASE WHEN r.id IS NOT NULL THEN pu.id END) as results_submitted,
                COUNT(DISTINCT CASE WHEN r.status = 'verified' THEN pu.id END) as results_verified,
                SUM(pu.registered_voters) as total_voters
            FROM lgas l
            LEFT JOIN wards w ON w.lga_id = l.id AND w.is_active = 1
            LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
            LEFT JOIN users u ON u.lga_id = l.id AND u.status = 'active'
            LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
            WHERE l.id = ?
            GROUP BY l.id, l.name, l.code
        ");
        $stmt->execute([$tenant_id, $selected_lga]);
        $lga_data = $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error fetching LGA data: " . $e->getMessage());
    }
}

// ============================================================
// GET WARDS IN SELECTED LGA
// ============================================================
$wards = [];
if ($selected_lga > 0) {
    try {
        $stmt = $db->prepare("
            SELECT w.id, w.name, w.code,
                   COUNT(DISTINCT pu.id) as pu_count,
                   COUNT(DISTINCT u.id) as coordinator_count
            FROM wards w
            LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
            LEFT JOIN users u ON u.ward_id = w.id AND u.status = 'active'
            WHERE w.lga_id = ? AND w.is_active = 1
            GROUP BY w.id, w.name, w.code
            ORDER BY w.name ASC
        ");
        $stmt->execute([$selected_lga]);
        $wards = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching wards: " . $e->getMessage());
    }
}

$page_title = 'Filter by LGA';
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
}
.filter-section select {
    padding: 10px 16px;
    border: 1.5px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.9rem;
    font-family: 'Inter', sans-serif;
    background: white;
    min-width: 200px;
    cursor: pointer;
}
.filter-section select:focus {
    outline: none;
    border-color: var(--primary);
}
.filter-section .btn-filter {
    padding: 10px 24px;
    border: none;
    border-radius: 10px;
    background: var(--primary);
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
    font-family: 'Inter', sans-serif;
    cursor: pointer;
    transition: var(--transition);
}
.filter-section .btn-filter:hover {
    background: var(--primary-dark);
}

.lga-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px;
    margin-bottom: 20px;
}
.lga-card .lga-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 16px;
}
.lga-card .lga-header h3 {
    font-size: 1.1rem;
    font-weight: 700;
    margin: 0;
}
.lga-card .lga-header .code {
    font-size: 0.8rem;
    color: var(--gray-400);
    background: var(--gray-100);
    padding: 2px 12px;
    border-radius: 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}
.stat-card {
    background: var(--gray-50);
    border-radius: 10px;
    padding: 14px 18px;
    text-align: center;
}
.stat-card .stat-number {
    font-size: 1.3rem;
    font-weight: 700;
}
.stat-card .stat-label {
    font-size: 0.7rem;
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

.ward-list .ward-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 14px;
    border-bottom: 1px solid var(--gray-100);
}
.ward-list .ward-item:last-child {
    border-bottom: none;
}
.ward-list .ward-item .ward-name {
    font-weight: 500;
}
.ward-list .ward-item .ward-code {
    font-size: 0.7rem;
    color: var(--gray-400);
}
.ward-list .ward-item .ward-stats {
    font-size: 0.75rem;
    color: var(--gray-500);
}
.ward-list .ward-item .ward-stats span {
    margin-left: 8px;
}

.progress-bar {
    height: 8px;
    background: var(--gray-200);
    border-radius: 4px;
    overflow: hidden;
    margin-top: 8px;
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
    .filter-section {
        flex-direction: column;
    }
    .filter-section select {
        min-width: unset;
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
                    <i class="fas fa-filter" style="color:var(--primary);margin-right:8px;"></i> 
                    Filter by LGA
                    <small><?php echo htmlspecialchars($district_name); ?> - <?php echo htmlspecialchars($state_name); ?></small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="monitor-district.php" class="btn btn-secondary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--gray-100);color:var(--gray-600);border:none;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="" style="display:flex;gap:12px;flex-wrap:wrap;flex:1;">
                <select name="lga_id" required>
                    <option value="">Select LGA...</option>
                    <?php foreach ($lgas as $lga): ?>
                        <option value="<?php echo $lga['id']; ?>" <?php echo ($selected_lga == $lga['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($lga['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-filter">
                    <i class="fas fa-filter"></i> Apply Filter
                </button>
            </form>
        </div>

        <?php if ($selected_lga > 0 && $lga_data): ?>
            <!-- LGA Card -->
            <div class="lga-card">
                <div class="lga-header">
                    <h3><?php echo htmlspecialchars($lga_data['name']); ?></h3>
                    <span class="code"><?php echo htmlspecialchars($lga_data['code']); ?></span>
                </div>

                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card blue">
                        <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                        <div class="stat-number"><?php echo number_format($lga_data['ward_count'] ?? 0); ?></div>
                        <div class="stat-label">Wards</div>
                    </div>
                    <div class="stat-card green">
                        <div class="stat-icon"><i class="fas fa-flag-checkered"></i></div>
                        <div class="stat-number"><?php echo number_format($lga_data['pu_count'] ?? 0); ?></div>
                        <div class="stat-label">Polling Units</div>
                    </div>
                    <div class="stat-card purple">
                        <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
                        <div class="stat-number"><?php echo number_format($lga_data['coordinator_count'] ?? 0); ?></div>
                        <div class="stat-label">Coordinators</div>
                    </div>
                    <div class="stat-card orange">
                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                        <div class="stat-number"><?php echo number_format($lga_data['agent_count'] ?? 0); ?></div>
                        <div class="stat-label">Agents</div>
                    </div>
                    <div class="stat-card teal">
                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                        <div class="stat-number"><?php echo number_format($lga_data['total_voters'] ?? 0); ?></div>
                        <div class="stat-label">Registered Voters</div>
                    </div>
                </div>

                <!-- Progress -->
                <?php 
                    $total_pus = $lga_data['pu_count'] ?? 0;
                    $submitted = $lga_data['results_submitted'] ?? 0;
                    $verified = $lga_data['results_verified'] ?? 0;
                    $coverage = $total_pus > 0 ? round(($submitted / $total_pus) * 100, 1) : 0;
                    $color = $coverage >= 80 ? 'green' : ($coverage >= 50 ? 'blue' : ($coverage >= 30 ? 'yellow' : 'red'));
                ?>
                <div style="margin-top:12px;">
                    <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:4px;">
                        <span>Progress</span>
                        <span><strong><?php echo $coverage; ?>%</strong> (<?php echo number_format($submitted); ?>/<?php echo number_format($total_pus); ?> submitted)</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill <?php echo $color; ?>" style="width:<?php echo min($coverage, 100); ?>%;"></div>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:0.75rem;color:var(--gray-400);margin-top:4px;">
                        <span><?php echo number_format($verified); ?> verified</span>
                        <span><?php echo number_format($total_pus - $submitted); ?> pending</span>
                    </div>
                </div>
            </div>

            <!-- Wards in this LGA -->
            <div class="section-card" style="background:white;border-radius:var(--radius);border:1px solid var(--gray-200);padding:20px;">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                    <h3 style="font-size:0.95rem;font-weight:600;margin:0;">
                        <i class="fas fa-layer-group" style="color:var(--primary);margin-right:6px;"></i>
                        Wards in <?php echo htmlspecialchars($lga_data['name']); ?>
                    </h3>
                    <span style="font-size:0.75rem;color:var(--gray-400);"><?php echo count($wards); ?> wards</span>
                </div>
                <?php if (count($wards) > 0): ?>
                    <div class="ward-list">
                        <?php foreach ($wards as $ward): ?>
                            <div class="ward-item">
                                <div>
                                    <span class="ward-name"><?php echo htmlspecialchars($ward['name']); ?></span>
                                    <span class="ward-code"><?php echo htmlspecialchars($ward['code']); ?></span>
                                </div>
                                <div class="ward-stats">
                                    <span><i class="fas fa-flag-checkered"></i> <?php echo $ward['pu_count'] ?? 0; ?></span>
                                    <span><i class="fas fa-user-tie"></i> <?php echo $ward['coordinator_count'] ?? 0; ?></span>
                                    <a href="view-ward-details.php?id=<?php echo $ward['id']; ?>" style="font-size:0.7rem;color:var(--primary);text-decoration:none;margin-left:8px;">
                                        View <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-layer-group"></i>
                        <p>No wards found in this LGA.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif ($selected_lga > 0): ?>
            <div class="empty-state">
                <i class="fas fa-map-marker-alt"></i>
                <p>LGA not found or no data available.</p>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-filter"></i>
                <h3>Select an LGA</h3>
                <p>Choose an LGA from the dropdown above to view detailed information.</p>
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