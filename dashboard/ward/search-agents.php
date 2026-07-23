<?php
// ============================================================
// WARD COORDINATOR - SEARCH AGENTS
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
// HANDLE SEARCH
// ============================================================
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$search_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$pu_filter = isset($_GET['pu_id']) ? (int)$_GET['pu_id'] : 0;

$results = [];
$total_results = 0;

if (!empty($search_query) || $status_filter !== 'all' || $pu_filter > 0) {
    try {
        // Build query conditions
        $conditions = "u.tenant_id = ? AND u.ward_id = ? AND u.deleted_at IS NULL";
        $params = [$tenant_id, $ward_id];
        
        // Role condition - PU Agents only
        $conditions .= " AND EXISTS (SELECT 1 FROM roles r WHERE r.id = u.role_id AND r.level = 'pu_agent')";
        
        // Search by name, email, phone, or code
        if (!empty($search_query)) {
            $conditions .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.user_code LIKE ?)";
            $search_param = "%$search_query%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        if ($status_filter !== 'all') {
            $conditions .= " AND u.status = ?";
            $params[] = $status_filter;
        }
        
        if ($pu_filter > 0) {
            $conditions .= " AND u.pu_id = ?";
            $params[] = $pu_filter;
        }
        
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
                (SELECT COUNT(*) FROM results_ec8a r2 WHERE r2.agent_id = u.id) as submissions,
                (SELECT COUNT(*) FROM results_ec8a r2 WHERE r2.agent_id = u.id AND r2.status = 'verified') as verified,
                (SELECT COUNT(*) FROM user_sessions us WHERE us.user_id = u.id AND us.is_active = 1 AND us.last_activity_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)) as is_online
            FROM users u
            LEFT JOIN polling_units pu ON u.pu_id = pu.id
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE $conditions
            ORDER BY u.full_name ASC
            LIMIT 100
        ");
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_results = count($results);
        
    } catch (Exception $e) {
        error_log("Error searching agents: " . $e->getMessage());
    }
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

$page_title = 'Search Agents';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.search-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.search-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.search-header h2 i {
    color: var(--primary);
}

.search-box {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
    margin-bottom: 20px;
}
.search-box .search-row {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}
.search-box .search-row .search-input {
    flex: 1;
    min-width: 200px;
    position: relative;
}
.search-box .search-row .search-input input {
    width: 100%;
    padding: 10px 12px 10px 36px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.9rem;
    background: white;
}
.search-box .search-row .search-input i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray-400);
}
.search-box .search-row select {
    padding: 10px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.9rem;
    background: white;
    min-width: 140px;
}
.search-box .search-row button {
    padding: 10px 20px;
}

.search-stats {
    font-size: 0.85rem;
    color: var(--gray-500);
    margin-bottom: 16px;
}

.results-table {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
}
.results-table table {
    width: 100%;
    border-collapse: collapse;
}
.results-table th {
    background: var(--gray-50);
    padding: 10px 14px;
    text-align: left;
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--gray-600);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border-bottom: 1px solid var(--gray-200);
}
.results-table td {
    padding: 10px 14px;
    font-size: 0.82rem;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}
.results-table tr:last-child td {
    border-bottom: none;
}
.results-table tr:hover {
    background: var(--gray-50);
}

.agent-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--gray-200);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.8rem;
    color: var(--gray-600);
    flex-shrink: 0;
}
.agent-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}
.agent-avatar.online {
    border: 2px solid #10B981;
}
.agent-avatar.offline {
    border: 2px solid var(--gray-300);
}

.status-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 500;
}
.status-badge.active { background: #ECFDF5; color: #10B981; }
.status-badge.suspended { background: #FEF2F2; color: #EF4444; }
.status-badge.pending { background: #FFFBEB; color: #F59E0B; }
.status-badge.archived { background: var(--gray-100); color: var(--gray-500); }

.agent-actions {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
}
.agent-actions .btn-sm {
    padding: 4px 8px;
    font-size: 0.7rem;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.agent-actions .btn-sm.view { background: #EFF6FF; color: #3B82F6; }
.agent-actions .btn-sm.assign { background: #ECFDF5; color: #10B981; }
.agent-actions .btn-sm.profile { background: #F5F3FF; color: #8B5CF6; }

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--gray-500);
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
    .search-box .search-row {
        flex-direction: column;
        align-items: stretch;
    }
    .search-box .search-row .search-input {
        min-width: unset;
    }
    .results-table {
        overflow-x: auto;
    }
    .results-table table {
        min-width: 700px;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="search-header">
            <div>
                <h2><i class="fas fa-search"></i> Search Agents</h2>
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

        <!-- Search Box -->
        <div class="search-box">
            <form method="GET" action="" id="searchForm">
                <div class="search-row">
                    <div class="search-input">
                        <i class="fas fa-search"></i>
                        <input type="text" name="q" id="searchQuery" placeholder="Search by name, email, phone or code..." 
                               value="<?php echo htmlspecialchars($search_query); ?>" autofocus>
                    </div>
                    <select name="status">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    </select>
                    <select name="pu_id">
                        <option value="0" <?php echo $pu_filter === 0 ? 'selected' : ''; ?>>All PUs</option>
                        <?php foreach ($polling_units as $pu): ?>
                            <option value="<?php echo $pu['id']; ?>" <?php echo $pu_filter === (int)$pu['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($pu['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-primary-sm">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if (!empty($search_query) || $status_filter !== 'all' || $pu_filter > 0): ?>
                        <a href="search-agents.php" class="btn-secondary-sm" style="background: var(--gray-100);">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if (!empty($search_query) || $status_filter !== 'all' || $pu_filter > 0): ?>
            <!-- Results Stats -->
            <div class="search-stats">
                Found <strong><?php echo number_format($total_results); ?></strong> result(s) 
                <?php if (!empty($search_query)): ?>
                    for "<strong><?php echo htmlspecialchars($search_query); ?></strong>"
                <?php endif; ?>
            </div>

            <!-- Results Table -->
            <?php if (count($results) > 0): ?>
                <div class="results-table">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:50px;">Avatar</th>
                                <th>Agent</th>
                                <th>Contact</th>
                                <th>Polling Unit</th>
                                <th>Submissions</th>
                                <th>Status</th>
                                <th style="width:140px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $agent): 
                                $is_online = (int)($agent['is_online'] ?? 0) > 0;
                                $initial = strtoupper(substr($agent['full_name'] ?? 'U', 0, 2));
                                $avatar = !empty($agent['photograph_url']) ? $agent['photograph_url'] : '';
                            ?>
                                <tr>
                                    <td>
                                        <div class="agent-avatar <?php echo $is_online ? 'online' : 'offline'; ?>">
                                            <?php if ($avatar): ?>
                                                <img src="<?php echo htmlspecialchars($avatar); ?>" alt="<?php echo htmlspecialchars($agent['full_name']); ?>">
                                            <?php else: ?>
                                                <?php echo $initial; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight:500;"><?php echo htmlspecialchars($agent['full_name'] ?? 'N/A'); ?></div>
                                        <div style="font-size:0.65rem;color:var(--gray-400);">
                                            <?php echo htmlspecialchars($agent['user_code'] ?? ''); ?>
                                            <?php if ($is_online): ?>
                                                <span style="color:#10B981;margin-left:6px;">
                                                    <i class="fas fa-circle" style="font-size:0.4rem;"></i> Online
                                                </span>
                                            <?php else: ?>
                                                <span style="color:var(--gray-400);margin-left:6px;">
                                                    <i class="fas fa-circle" style="font-size:0.4rem;"></i> Offline
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size:0.78rem;">
                                            <?php if (!empty($agent['email'])): ?>
                                                <div><i class="fas fa-envelope" style="font-size:0.6rem;color:var(--gray-400);width:16px;"></i> <?php echo htmlspecialchars($agent['email']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($agent['phone'])): ?>
                                                <div><i class="fas fa-phone" style="font-size:0.6rem;color:var(--gray-400);width:16px;"></i> <?php echo htmlspecialchars($agent['phone']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($agent['pu_name'])): ?>
                                            <div style="font-size:0.78rem;">
                                                <strong><?php echo htmlspecialchars($agent['pu_name']); ?></strong>
                                                <div style="font-size:0.6rem;color:var(--gray-400);"><?php echo htmlspecialchars($agent['pu_code'] ?? ''); ?></div>
                                            </div>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400);font-size:0.75rem;">Not Assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:0.78rem;">
                                        <div>Total: <?php echo number_format($agent['submissions'] ?? 0); ?></div>
                                        <div style="font-size:0.65rem;">
                                            <span style="color:#10B981;">✓ <?php echo number_format($agent['verified'] ?? 0); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $agent['status'] ?? 'pending'; ?>">
                                            <?php echo ucfirst($agent['status'] ?? 'Pending'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="agent-actions">
                                            <a href="agent-profile.php?id=<?php echo $agent['id']; ?>" class="btn-sm view" title="View Profile">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (empty($agent['pu_id'])): ?>
                                                <a href="assign-agents.php?agent_id=<?php echo $agent['id']; ?>" class="btn-sm assign" title="Assign to PU">
                                                    <i class="fas fa-user-plus"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="reassign-agent.php?agent_id=<?php echo $agent['id']; ?>" class="btn-sm assign" title="Reassign">
                                                    <i class="fas fa-exchange-alt"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="agent-performance.php?id=<?php echo $agent['id']; ?>" class="btn-sm profile" title="Performance">
                                                <i class="fas fa-chart-bar"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h4>No Results Found</h4>
                    <p>No agents match your search criteria. Try adjusting your search terms.</p>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-search" style="color:var(--gray-300);"></i>
                <h4>Search for Agents</h4>
                <p>Enter a search term above to find agents in your ward.</p>
                <p style="font-size:0.8rem;color:var(--gray-400);">
                    You can search by name, email, phone number, or agent code.
                </p>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
// Auto-submit on enter
document.getElementById('searchQuery').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        document.getElementById('searchForm').submit();
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