<?php
// ============================================================
// WARD COORDINATOR - FILTER AGENTS
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'ward') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Ward Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$ward_id = SessionManager::get('ward_id');

if (empty($ward_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT ward_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['ward_id'])) {
            $ward_id = $user['ward_id'];
            SessionManager::set('ward_id', $ward_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching ward_id: " . $e->getMessage());
    }
}

$db = getDB();

// Get filters
$pu_filter = isset($_GET['pu_id']) ? (int)$_GET['pu_id'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get ward name
$ward_name = 'Ward';
try {
    if ($ward_id) {
        $stmt = $db->prepare("SELECT name FROM wards WHERE id = ?");
        $stmt->execute([$ward_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $ward_name = $result['name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching ward: " . $e->getMessage());
}

// Get polling units for filter
$polling_units = [];
try {
    $stmt = $db->prepare("SELECT id, name, code FROM polling_units WHERE ward_id = ? AND is_active = 1 ORDER BY name ASC");
    $stmt->execute([$ward_id]);
    $polling_units = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching polling units: " . $e->getMessage());
}

// Fetch filtered agents
$agents = [];
$stats = ['total' => 0, 'active' => 0, 'suspended' => 0, 'pending' => 0];

try {
    $sql = "
        SELECT 
            u.id,
            u.user_code,
            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            u.status,
            u.created_at,
            u.last_login_at,
            pu.id as pu_id,
            pu.name as pu_name,
            pu.code as pu_code,
            (SELECT COUNT(*) FROM user_sessions us WHERE us.user_id = u.id AND us.is_active = 1 AND us.last_activity_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)) as is_online,
            (SELECT COUNT(*) FROM results_ec8a ra WHERE ra.agent_id = u.id AND ra.status IN ('verified', 'approved')) as verified_results,
            (SELECT COUNT(*) FROM results_ec8a ra WHERE ra.agent_id = u.id AND ra.status = 'pending') as pending_results
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        WHERE u.tenant_id = ?
        AND u.ward_id = ?
        AND u.deleted_at IS NULL
        AND r.level = 'pu_agent'
    ";
    $params = [$tenant_id, $ward_id];
    
    if ($pu_filter > 0) {
        $sql .= " AND u.pu_id = ?";
        $params[] = $pu_filter;
    }
    
    if (!empty($status_filter)) {
        $sql .= " AND u.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($search)) {
        $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.user_code LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $sql .= " ORDER BY pu.name ASC, u.first_name ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($agents as $agent) {
        $stats['total']++;
        $stats[$agent['status']] = ($stats[$agent['status']] ?? 0) + 1;
    }
} catch (Exception $e) {
    error_log("Error fetching agents: " . $e->getMessage());
}

$page_title = 'Filter Agents';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.filter-container {
    max-width: 900px;
    margin: 0 auto;
}

.filter-box {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 18px 22px;
    margin-bottom: 20px;
}

.filter-box .filter-row {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: flex-end;
}

.filter-box .filter-group {
    flex: 1;
    min-width: 150px;
}

.filter-box .filter-group label {
    display: block;
    font-weight: 600;
    font-size: 0.7rem;
    color: var(--gray-600);
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.filter-box .filter-group select,
.filter-box .filter-group input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.8rem;
    font-family: 'Inter', sans-serif;
    background: white;
}

.filter-box .filter-group select:focus,
.filter-box .filter-group input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.filter-box .btn-filter {
    padding: 8px 20px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.filter-box .btn-filter:hover {
    background: var(--primary-dark);
}

.filter-box .btn-clear {
    padding: 8px 20px;
    background: var(--gray-100);
    color: var(--gray-700);
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.8rem;
    text-decoration: none;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.filter-box .btn-clear:hover {
    background: var(--gray-200);
}

.results-count {
    font-size: 0.85rem;
    color: var(--gray-500);
    margin-bottom: 16px;
}

.results-count strong {
    color: var(--gray-700);
}

.agent-item {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 12px 16px;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: var(--transition);
}

.agent-item:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow-hover);
}

.agent-item .avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.8rem;
    flex-shrink: 0;
}

.agent-item .info {
    flex: 1;
}

.agent-item .info .name {
    font-weight: 600;
    font-size: 0.82rem;
    color: var(--gray-800);
}

.agent-item .info .pu {
    font-size: 0.65rem;
    color: var(--primary);
}

.agent-item .info .details {
    font-size: 0.65rem;
    color: var(--gray-500);
}

.agent-item .badges {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.5rem;
    padding: 2px 8px;
    border-radius: 8px;
    font-weight: 600;
}

.status-badge .dot {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    display: inline-block;
}

.status-badge.active { background: #ECFDF5; color: #065F46; }
.status-badge.active .dot { background: #10B981; }
.status-badge.suspended { background: #FEF2F2; color: #991B1B; }
.status-badge.suspended .dot { background: #EF4444; }
.status-badge.pending { background: #FFFBEB; color: #92400E; }
.status-badge.pending .dot { background: #F59E0B; }

.status-badge.online { background: #ECFDF5; color: #065F46; }
.status-badge.online .dot { background: #10B981; animation: pulse-dot 1.5s ease-in-out infinite; }
.status-badge.offline { background: #F3F4F6; color: #6B7280; }
.status-badge.offline .dot { background: #9CA3AF; }

@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(0.8); }
}

.agent-item .actions a {
    padding: 3px 10px;
    border-radius: 4px;
    font-size: 0.6rem;
    font-weight: 500;
    text-decoration: none;
    transition: var(--transition);
}

.agent-item .actions .btn-view {
    background: var(--primary);
    color: white;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
}

.empty-state i {
    font-size: 3rem;
    color: var(--gray-300);
    display: block;
    margin-bottom: 12px;
}

.empty-state h4 {
    color: var(--gray-600);
    margin: 0;
}

.empty-state p {
    color: var(--gray-400);
    font-size: 0.85rem;
    margin-top: 4px;
}

@media (max-width: 768px) {
    .filter-box .filter-row {
        flex-direction: column;
    }
    .filter-box .filter-group {
        width: 100%;
        min-width: unset;
    }
    .filter-box .btn-filter,
    .filter-box .btn-clear {
        width: 100%;
        text-align: center;
    }
    .agent-item {
        flex-direction: column;
        text-align: center;
    }
    .agent-item .badges {
        justify-content: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="filter-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-filter"></i> Filter Agents</h1>
                    <p class="subtitle">
                        <i class="fas fa-layer-group"></i> 
                        <?php echo htmlspecialchars($ward_name); ?> Ward - Filter PU Agents
                    </p>
                </div>
                <div class="actions">
                    <a href="manage-pu-agents.php" class="btn-secondary-sm">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>

            <!-- Filter Form -->
            <div class="filter-box">
                <form method="GET" action="">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Polling Unit</label>
                            <select name="pu_id">
                                <option value="0">All PUs</option>
                                <?php foreach ($polling_units as $pu): ?>
                                    <option value="<?php echo $pu['id']; ?>" <?php echo $pu_filter == $pu['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($pu['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>Search</label>
                            <input type="text" name="search" placeholder="Name, email, phone..." value="<?php echo htmlspecialchars($search); ?>" />
                        </div>
                        
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <button type="submit" class="btn-filter">
                                <i class="fas fa-filter"></i> Apply
                            </button>
                            <a href="filter-agents.php" class="btn-clear">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Results -->
            <div class="results-count">
                <strong><?php echo count($agents); ?></strong> agent<?php echo count($agents) !== 1 ? 's' : ''; ?> found
            </div>

            <?php if (!empty($agents)): ?>
                <?php foreach ($agents as $agent): 
                    $full_name = $agent['first_name'] . ' ' . $agent['last_name'];
                    $initials = strtoupper(substr($agent['first_name'], 0, 1) . substr($agent['last_name'], 0, 1));
                    $online_status = $agent['is_online'] > 0 ? 'online' : 'offline';
                ?>
                    <div class="agent-item">
                        <div class="avatar"><?php echo $initials; ?></div>
                        
                        <div class="info">
                            <div class="name"><?php echo htmlspecialchars($full_name); ?></div>
                            <div class="pu">
                                <?php if ($agent['pu_id']): ?>
                                    <i class="fas fa-flag-checkered"></i> <?php echo htmlspecialchars($agent['pu_name']); ?>
                                    <span style="font-size:0.55rem;color:var(--gray-400);">(<?php echo htmlspecialchars($agent['pu_code']); ?>)</span>
                                <?php else: ?>
                                    <span style="color:var(--gray-400);">Unassigned</span>
                                <?php endif; ?>
                            </div>
                            <div class="details">
                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($agent['email'] ?? 'N/A'); ?>
                                <span style="margin:0 4px;">•</span>
                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($agent['phone'] ?? 'N/A'); ?>
                            </div>
                        </div>
                        
                        <div class="badges">
                            <span class="status-badge <?php echo $agent['status']; ?>">
                                <span class="dot"></span> <?php echo ucfirst($agent['status']); ?>
                            </span>
                            <span class="status-badge <?php echo $online_status; ?>">
                                <span class="dot"></span> <?php echo $online_status === 'online' ? 'Online' : 'Offline'; ?>
                            </span>
                            <?php if ($agent['verified_results'] > 0): ?>
                                <span style="font-size:0.6rem;color:#10B981;">
                                    <i class="fas fa-check"></i> <?php echo number_format($agent['verified_results']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="actions">
                            <a href="agent-profile.php?id=<?php echo $agent['id']; ?>" class="btn-view">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h4>No Agents Found</h4>
                    <p>No agents match the selected filters.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
// Same sidebar scripts as index.php
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});

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
</script>
</body>
</html>