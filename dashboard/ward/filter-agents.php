<?php
// ============================================================
// WARD COORDINATOR - FILTER AGENTS
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

// Only Ward coordinator can access
if (SessionManager::get('role_level') !== 'ward') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$ward_id = SessionManager::get('ward_id');
$tenant_id = SessionManager::get('tenant_id');

// If ward_id is not set in session, try to get it from user record
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

// ============================================================
// FETCH WARD NAME
// ============================================================
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
    error_log("Error fetching ward name: " . $e->getMessage());
}

// ============================================================
// FETCH FILTER OPTIONS
// ============================================================
$filter_options = [
    'statuses' => ['active', 'suspended', 'pending', 'archived'],
    'roles' => ['pu_agent', 'party_agent', 'observer', 'volunteer'],
    'pu_assignment' => ['assigned', 'unassigned']
];

// ============================================================
// HANDLE FILTER APPLICATION
// ============================================================
$filters = [
    'status' => isset($_GET['status']) ? $_GET['status'] : 'all',
    'role' => isset($_GET['role']) ? $_GET['role'] : 'all',
    'pu_assignment' => isset($_GET['pu_assignment']) ? $_GET['pu_assignment'] : 'all',
    'pu_id' => isset($_GET['pu_id']) ? (int)$_GET['pu_id'] : 0,
    'sort_by' => isset($_GET['sort_by']) ? $_GET['sort_by'] : 'name',
    'sort_order' => isset($_GET['sort_order']) ? $_GET['sort_order'] : 'ASC',
    'date_from' => isset($_GET['date_from']) ? $_GET['date_from'] : '',
    'date_to' => isset($_GET['date_to']) ? $_GET['date_to'] : '',
    'search' => isset($_GET['search']) ? trim($_GET['search']) : ''
];

$results = [];
$total_results = 0;

try {
    // Build query conditions
    $conditions = "u.tenant_id = ? AND u.ward_id = ? AND u.deleted_at IS NULL";
    $params = [$tenant_id, $ward_id];
    
    // Status filter
    if ($filters['status'] !== 'all') {
        $conditions .= " AND u.status = ?";
        $params[] = $filters['status'];
    }
    
    // Role filter
    if ($filters['role'] !== 'all') {
        $conditions .= " AND EXISTS (SELECT 1 FROM roles r WHERE r.id = u.role_id AND r.level = ?)";
        $params[] = $filters['role'];
    }
    
    // PU assignment filter
    if ($filters['pu_assignment'] === 'assigned') {
        $conditions .= " AND u.pu_id IS NOT NULL AND u.pu_id > 0";
    } elseif ($filters['pu_assignment'] === 'unassigned') {
        $conditions .= " AND (u.pu_id IS NULL OR u.pu_id = 0)";
    }
    
    // Specific PU filter
    if ($filters['pu_id'] > 0) {
        $conditions .= " AND u.pu_id = ?";
        $params[] = $filters['pu_id'];
    }
    
    // Date range filter
    if (!empty($filters['date_from'])) {
        $conditions .= " AND DATE(u.created_at) >= ?";
        $params[] = $filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $conditions .= " AND DATE(u.created_at) <= ?";
        $params[] = $filters['date_to'];
    }
    
    // Search filter
    if (!empty($filters['search'])) {
        $conditions .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.user_code LIKE ?)";
        $search_param = "%{$filters['search']}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // Sorting
    $sort_map = [
        'name' => 'u.full_name',
        'code' => 'u.user_code',
        'status' => 'u.status',
        'created' => 'u.created_at',
        'last_login' => 'u.last_login_at'
    ];
    $sort_column = $sort_map[$filters['sort_by']] ?? 'u.full_name';
    $sort_order = $filters['sort_order'] === 'DESC' ? 'DESC' : 'ASC';
    
    // Get results
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.user_code,
            u.full_name,
            u.email,
            u.phone,
            u.status,
            u.created_at,
            u.last_login_at,
            u.photograph_url,
            u.pu_id,
            pu.name as pu_name,
            pu.code as pu_code,
            r.name as role_name,
            r.level as role_level,
            (SELECT COUNT(*) FROM results_ec8a r2 WHERE r2.agent_id = u.id) as submissions,
            (SELECT COUNT(*) FROM results_ec8a r2 WHERE r2.agent_id = u.id AND r2.status = 'verified') as verified,
            (SELECT COUNT(*) FROM user_sessions us WHERE us.user_id = u.id AND us.is_active = 1 AND us.last_activity_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)) as is_online,
            (SELECT COUNT(*) FROM agent_assignments aa WHERE aa.user_id = u.id AND aa.status = 'active') as active_assignments
        FROM users u
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE $conditions
        ORDER BY $sort_column $sort_order
        LIMIT 200
    ");
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_results = count($results);
    
} catch (Exception $e) {
    error_log("Error filtering agents: " . $e->getMessage());
}

// ============================================================
// FETCH POLLING UNITS FOR FILTER
// ============================================================
$polling_units = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, code FROM polling_units 
        WHERE ward_id = ? AND is_active = 1 
        ORDER BY name ASC
    ");
    $stmt->execute([$ward_id]);
    $polling_units = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching polling units: " . $e->getMessage());
}

$page_title = 'Filter Agents';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.filter-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.filter-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.filter-header h2 i {
    color: var(--primary);
}

.filter-panel {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
    margin-bottom: 20px;
}
.filter-panel .filter-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
}
.filter-panel .filter-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.filter-panel .filter-group label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--gray-600);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.filter-panel .filter-group select,
.filter-panel .filter-group input {
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    background: white;
    width: 100%;
}
.filter-panel .filter-actions {
    display: flex;
    gap: 8px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--gray-200);
}

.results-stats {
    font-size: 0.85rem;
    color: var(--gray-500);
    margin-bottom: 16px;
}
.results-stats strong {
    color: var(--gray-700);
}

.results-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
}
.agent-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px;
    transition: var(--transition);
}
.agent-card:hover {
    box-shadow: var(--shadow-hover);
}
.agent-card .card-top {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
}
.agent-card .avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--gray-200);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1rem;
    color: var(--gray-600);
    flex-shrink: 0;
}
.agent-card .avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}
.agent-card .avatar.online {
    border: 2px solid #10B981;
}
.agent-card .avatar.offline {
    border: 2px solid var(--gray-300);
}
.agent-card .card-info {
    flex: 1;
    min-width: 0;
}
.agent-card .card-info .name {
    font-weight: 600;
    font-size: 0.95rem;
}
.agent-card .card-info .code {
    font-size: 0.7rem;
    color: var(--gray-400);
}
.agent-card .card-info .role {
    font-size: 0.7rem;
    color: var(--primary);
    font-weight: 500;
}
.agent-card .card-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4px 12px;
    font-size: 0.78rem;
    color: var(--gray-600);
    padding: 8px 0;
    border-top: 1px solid var(--gray-100);
    border-bottom: 1px solid var(--gray-100);
}
.agent-card .card-details .item {
    display: flex;
    align-items: center;
    gap: 4px;
}
.agent-card .card-details .item i {
    font-size: 0.6rem;
    color: var(--gray-400);
    width: 16px;
}
.agent-card .card-actions {
    display: flex;
    gap: 6px;
    margin-top: 8px;
    flex-wrap: wrap;
}
.agent-card .card-actions .btn-sm {
    padding: 4px 10px;
    font-size: 0.7rem;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.agent-card .card-actions .btn-sm.view { background: #EFF6FF; color: #3B82F6; }
.agent-card .card-actions .btn-sm.assign { background: #ECFDF5; color: #10B981; }
.agent-card .card-actions .btn-sm.profile { background: #F5F3FF; color: #8B5CF6; }

.status-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.6rem;
    font-weight: 500;
}
.status-badge.active { background: #ECFDF5; color: #10B981; }
.status-badge.suspended { background: #FEF2F2; color: #EF4444; }
.status-badge.pending { background: #FFFBEB; color: #F59E0B; }
.status-badge.archived { background: var(--gray-100); color: var(--gray-500); }

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--gray-500);
    grid-column: 1/-1;
}
.empty-state i {
    font-size: 4rem;
    color: var(--gray-300);
    margin-bottom: 16px;
}
.empty-state h4 {
    margin: 0 0 8px;
    color: var(--gray-700);
}
.empty-state p {
    margin: 0;
    font-size: 0.9rem;
}

@media (max-width: 768px) {
    .filter-panel .filter-row {
        grid-template-columns: 1fr;
    }
    .results-grid {
        grid-template-columns: 1fr;
    }
    .filter-panel .filter-actions {
        flex-wrap: wrap;
    }
    .filter-panel .filter-actions button,
    .filter-panel .filter-actions a {
        flex: 1;
        text-align: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="filter-header">
            <div>
                <h2><i class="fas fa-filter"></i> Filter Agents</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward
                </p>
            </div>
            <div>
                <a href="manage-pu-agents.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Agents
                </a>
            </div>
        </div>

        <!-- Filter Panel -->
        <div class="filter-panel">
            <form method="GET" action="" id="filterForm">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="all" <?php echo $filters['status'] === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="suspended" <?php echo $filters['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="archived" <?php echo $filters['status'] === 'archived' ? 'selected' : ''; ?>>Archived</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Role</label>
                        <select name="role">
                            <option value="all" <?php echo $filters['role'] === 'all' ? 'selected' : ''; ?>>All Roles</option>
                            <option value="pu_agent" <?php echo $filters['role'] === 'pu_agent' ? 'selected' : ''; ?>>PU Agent</option>
                            <option value="party_agent" <?php echo $filters['role'] === 'party_agent' ? 'selected' : ''; ?>>Party Agent</option>
                            <option value="observer" <?php echo $filters['role'] === 'observer' ? 'selected' : ''; ?>>Observer</option>
                            <option value="volunteer" <?php echo $filters['role'] === 'volunteer' ? 'selected' : ''; ?>>Volunteer</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>PU Assignment</label>
                        <select name="pu_assignment">
                            <option value="all" <?php echo $filters['pu_assignment'] === 'all' ? 'selected' : ''; ?>>All</option>
                            <option value="assigned" <?php echo $filters['pu_assignment'] === 'assigned' ? 'selected' : ''; ?>>Assigned to PU</option>
                            <option value="unassigned" <?php echo $filters['pu_assignment'] === 'unassigned' ? 'selected' : ''; ?>>Not Assigned</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Polling Unit</label>
                        <select name="pu_id">
                            <option value="0" <?php echo $filters['pu_id'] === 0 ? 'selected' : ''; ?>>All PUs</option>
                            <?php foreach ($polling_units as $pu): ?>
                                <option value="<?php echo $pu['id']; ?>" <?php echo $filters['pu_id'] === (int)$pu['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($pu['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="filter-row" style="margin-top:12px;">
                    <div class="filter-group">
                        <label>Date From</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Date To</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Sort By</label>
                        <select name="sort_by">
                            <option value="name" <?php echo $filters['sort_by'] === 'name' ? 'selected' : ''; ?>>Name</option>
                            <option value="code" <?php echo $filters['sort_by'] === 'code' ? 'selected' : ''; ?>>Code</option>
                            <option value="status" <?php echo $filters['sort_by'] === 'status' ? 'selected' : ''; ?>>Status</option>
                            <option value="created" <?php echo $filters['sort_by'] === 'created' ? 'selected' : ''; ?>>Created Date</option>
                            <option value="last_login" <?php echo $filters['sort_by'] === 'last_login' ? 'selected' : ''; ?>>Last Login</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Sort Order</label>
                        <select name="sort_order">
                            <option value="ASC" <?php echo $filters['sort_order'] === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                            <option value="DESC" <?php echo $filters['sort_order'] === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-row" style="margin-top:12px;">
                    <div class="filter-group" style="grid-column:1/-1;">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Search by name, email, phone or code..." 
                               value="<?php echo htmlspecialchars($filters['search']); ?>">
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn-primary-sm">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="filter-agents.php" class="btn-secondary-sm" style="background: var(--gray-100);">
                        <i class="fas fa-undo"></i> Reset All
                    </a>
                </div>
            </form>
        </div>

        <!-- Results -->
        <?php if (!empty($filters['status']) || !empty($filters['role']) || !empty($filters['pu_assignment']) || !empty($filters['search']) || $filters['pu_id'] > 0 || !empty($filters['date_from']) || !empty($filters['date_to'])): ?>
            <div class="results-stats">
                Found <strong><?php echo number_format($total_results); ?></strong> agent(s) matching your filters
            </div>

            <div class="results-grid">
                <?php if (count($results) > 0): ?>
                    <?php foreach ($results as $agent): 
                        $is_online = (int)($agent['is_online'] ?? 0) > 0;
                        $initial = strtoupper(substr($agent['full_name'] ?? 'U', 0, 2));
                        $avatar = !empty($agent['photograph_url']) ? $agent['photograph_url'] : '';
                    ?>
                        <div class="agent-card">
                            <div class="card-top">
                                <div class="avatar <?php echo $is_online ? 'online' : 'offline'; ?>">
                                    <?php if ($avatar): ?>
                                        <img src="<?php echo htmlspecialchars($avatar); ?>" alt="<?php echo htmlspecialchars($agent['full_name']); ?>">
                                    <?php else: ?>
                                        <?php echo $initial; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="card-info">
                                    <div class="name"><?php echo htmlspecialchars($agent['full_name'] ?? 'N/A'); ?></div>
                                    <div class="code"><?php echo htmlspecialchars($agent['user_code'] ?? ''); ?></div>
                                    <div class="role"><?php echo ucfirst(str_replace('_', ' ', $agent['role_level'] ?? 'PU Agent')); ?></div>
                                </div>
                                <span class="status-badge <?php echo $agent['status'] ?? 'pending'; ?>">
                                    <?php echo ucfirst($agent['status'] ?? 'Pending'); ?>
                                </span>
                            </div>
                            
                            <div class="card-details">
                                <div class="item"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($agent['email'] ?? 'No email'); ?></div>
                                <div class="item"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($agent['phone'] ?? 'No phone'); ?></div>
                                <div class="item"><i class="fas fa-flag-checkered"></i> <?php echo htmlspecialchars($agent['pu_name'] ?? 'Unassigned'); ?></div>
                                <div class="item"><i class="fas fa-file-alt"></i> <?php echo number_format($agent['submissions'] ?? 0); ?> submissions</div>
                                <div class="item"><i class="fas fa-check-circle" style="color:#10B981;"></i> <?php echo number_format($agent['verified'] ?? 0); ?> verified</div>
                                <div class="item"><i class="fas fa-user-check"></i> <?php echo number_format($agent['active_assignments'] ?? 0); ?> assignments</div>
                            </div>
                            
                            <div class="card-actions">
                                <a href="agent-profile.php?id=<?php echo $agent['id']; ?>" class="btn-sm view">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if (empty($agent['pu_id'])): ?>
                                    <a href="assign-agents.php?agent_id=<?php echo $agent['id']; ?>" class="btn-sm assign">
                                        <i class="fas fa-user-plus"></i> Assign
                                    </a>
                                <?php else: ?>
                                    <a href="reassign-agent.php?agent_id=<?php echo $agent['id']; ?>" class="btn-sm assign">
                                        <i class="fas fa-exchange-alt"></i> Reassign
                                    </a>
                                <?php endif; ?>
                                <a href="agent-performance.php?id=<?php echo $agent['id']; ?>" class="btn-sm profile">
                                    <i class="fas fa-chart-bar"></i> Performance
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-filter"></i>
                        <h4>No Results Found</h4>
                        <p>No agents match your filter criteria. Try adjusting your filters.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="empty-state" style="background:white;border-radius:var(--radius);border:1px solid var(--gray-200);padding:60px 20px;">
                <i class="fas fa-filter" style="color:var(--gray-300);"></i>
                <h4>Apply Filters</h4>
                <p>Use the filter panel above to find agents in your ward.</p>
                <p style="font-size:0.8rem;color:var(--gray-400);">
                    You can filter by status, role, PU assignment, date range, and more.
                </p>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
// Auto-submit on enter for search
document.querySelector('input[name="search"]').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        document.getElementById('filterForm').submit();
    }
});

// Preloader
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});

// Sidebar toggle
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

// Sidebar dropdowns
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

// Profile dropdown
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