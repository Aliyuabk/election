<?php
// ============================================================
// FEDERAL CONSTITUENCY COORDINATOR - VIEW WARDS
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

$constituency_id = SessionManager::get('federal_constituency_id');
$tenant_id = SessionManager::get('tenant_id');
$db = getDB();

// Get LGA IDs from constituency
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
    $lga_ids = [];
}

$lga_list = !empty($lga_ids) ? implode(',', array_map('intval', $lga_ids)) : '0';

// Get filter
$lga_filter = isset($_GET['lga']) ? (int)$_GET['lga'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get LGAs for filter
$lgas = [];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("SELECT id, name FROM lgas WHERE id IN ($lga_list) ORDER BY name ASC");
        $stmt->execute();
        $lgas = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching LGAs: " . $e->getMessage());
}

// Get wards
$wards = [];
$total_wards = 0;
try {
    $where = ["w.is_active = 1"];
    $params = [];
    
    if ($lga_filter > 0) {
        $where[] = "w.lga_id = ?";
        $params[] = $lga_filter;
    } elseif ($lga_list !== '0') {
        $where[] = "w.lga_id IN ($lga_list)";
    } else {
        $where[] = "1=0";
    }
    
    if (!empty($search)) {
        $where[] = "(w.name LIKE ? OR w.code LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_clause = implode(" AND ", $where);
    
    $stmt = $db->prepare("
        SELECT 
            w.*,
            l.name as lga_name,
            COUNT(DISTINCT pu.id) as pu_count,
            (SELECT COUNT(*) FROM users u 
             WHERE u.ward_id = w.id AND u.tenant_id = ? AND u.status = 'active') as agent_count
        FROM wards w
        JOIN lgas l ON w.lga_id = l.id
        LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
        WHERE $where_clause
        GROUP BY w.id
        ORDER BY l.name ASC, w.name ASC
    ");
    $stmt->execute(array_merge($params, [$tenant_id]));
    $wards = $stmt->fetchAll();
    $total_wards = count($wards);
} catch (Exception $e) {
    error_log("Error fetching wards: " . $e->getMessage());
}

$page_title = 'View Wards';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.filter-section {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px 20px;
    margin-bottom: 24px;
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}
.filter-section select,
.filter-section input {
    padding: 8px 14px;
    border: 1.5px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.85rem;
    background: white;
}
.filter-section select:focus,
.filter-section input:focus {
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
}
.filter-section .btn-reset {
    padding: 8px 18px;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    background: white;
    color: var(--gray-600);
    font-weight: 500;
    font-size: 0.8rem;
    text-decoration: none;
}

.wards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}
.ward-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 18px 20px;
    transition: var(--transition);
}
.ward-card:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow);
}
.ward-card .ward-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}
.ward-card .ward-header .ward-name {
    font-weight: 600;
    font-size: 0.95rem;
}
.ward-card .ward-header .ward-code {
    font-size: 0.7rem;
    color: var(--gray-400);
    background: var(--gray-100);
    padding: 2px 10px;
    border-radius: 12px;
}
.ward-card .ward-lga {
    font-size: 0.8rem;
    color: var(--gray-500);
    margin-top: 2px;
}
.ward-card .ward-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin-top: 12px;
}
.ward-card .ward-stats .stat {
    text-align: center;
    padding: 6px;
    background: var(--gray-50);
    border-radius: 6px;
}
.ward-card .ward-stats .stat .number {
    font-weight: 700;
    font-size: 1rem;
}
.ward-card .ward-stats .stat .label {
    font-size: 0.55rem;
    color: var(--gray-500);
    text-transform: uppercase;
}
.ward-card .ward-actions {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--gray-100);
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.ward-card .ward-actions a {
    font-size: 0.7rem;
    padding: 4px 12px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
}
.ward-card .ward-actions .btn-view {
    background: var(--primary);
    color: white;
}
.ward-card .ward-actions .btn-view:hover {
    background: var(--primary-dark);
}
.ward-card .ward-actions .btn-pus {
    background: var(--gray-100);
    color: var(--gray-600);
}
.ward-card .ward-actions .btn-pus:hover {
    background: var(--gray-200);
}

.results-summary {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px 20px;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}
.results-summary .count {
    font-weight: 600;
    color: var(--gray-700);
}
.results-summary .count span {
    color: var(--primary);
}

@media (max-width: 768px) {
    .filter-section {
        flex-direction: column;
        align-items: stretch;
    }
    .wards-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <h2 style="font-size:1.3rem;font-weight:700;margin-bottom:4px;">
            <i class="fas fa-layer-group" style="color:var(--primary);"></i> View Wards
        </h2>
        <p style="color:var(--gray-500);font-size:0.9rem;margin-bottom:20px;">
            All wards in your federal constituency.
        </p>

        <!-- Filter -->
        <div class="filter-section">
            <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;flex:1;align-items:center;">
                <select name="lga">
                    <option value="">All LGAs</option>
                    <?php foreach ($lgas as $lga): ?>
                        <option value="<?php echo $lga['id']; ?>" <?php echo ($lga_filter == $lga['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($lga['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="search" placeholder="Search wards..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
                <a href="view-wards.php" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
            </form>
        </div>

        <!-- Results -->
        <div class="results-summary">
            <div class="count"><span><?php echo number_format($total_wards); ?></span> wards found</div>
            <?php if (!empty($search)): ?>
                <div style="font-size:0.85rem;color:var(--gray-500);">
                    Search: "<strong><?php echo htmlspecialchars($search); ?></strong>"
                </div>
            <?php endif; ?>
        </div>

        <!-- Wards Grid -->
        <div class="wards-grid">
            <?php if (count($wards) > 0): ?>
                <?php foreach ($wards as $ward): ?>
                    <div class="ward-card">
                        <div class="ward-header">
                            <div>
                                <div class="ward-name"><?php echo htmlspecialchars($ward['name']); ?></div>
                                <div class="ward-lga">
                                    <i class="fas fa-map-marker-alt" style="font-size:0.7rem;"></i>
                                    <?php echo htmlspecialchars($ward['lga_name']); ?>
                                </div>
                            </div>
                            <span class="ward-code"><?php echo htmlspecialchars($ward['code'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="ward-stats">
                            <div class="stat">
                                <div class="number"><?php echo number_format($ward['pu_count'] ?? 0); ?></div>
                                <div class="label">PUs</div>
                            </div>
                            <div class="stat">
                                <div class="number"><?php echo number_format($ward['agent_count'] ?? 0); ?></div>
                                <div class="label">Agents</div>
                            </div>
                            <div class="stat">
                                <div class="number"><?php echo number_format($ward['registered_voters'] ?? 0); ?></div>
                                <div class="label">Voters</div>
                            </div>
                        </div>
                        <div class="ward-actions">
                            <a href="ward-details.php?id=<?php echo $ward['id']; ?>" class="btn-view">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                            <a href="monitor-pus.php?ward=<?php echo $ward['id']; ?>" class="btn-pus">
                                <i class="fas fa-flag-checkered"></i> PUs
                            </a>
                            <a href="coordinators.php?ward=<?php echo $ward['id']; ?>" class="btn-pus">
                                <i class="fas fa-user-tie"></i> Coordinators
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--gray-500);">
                    <i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                    <p>No wards found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
// Sidebar toggle (same as previous)
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