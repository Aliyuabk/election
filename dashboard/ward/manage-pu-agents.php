<?php
// ============================================================
// WARD COORDINATOR - MANAGE PU AGENTS
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
$lga_id = SessionManager::get('lga_id');
$state_id = SessionManager::get('state_id');
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
// HANDLE AGENT ACTIONS (Suspend, Activate, Delete)
// ============================================================
$action_message = '';
$action_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $agent_id = isset($_POST['agent_id']) ? (int)$_POST['agent_id'] : 0;
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($agent_id > 0 && !empty($action)) {
        try {
            switch ($action) {
                case 'suspend':
                    $stmt = $db->prepare("UPDATE users SET status = 'suspended', updated_at = NOW() WHERE id = ? AND tenant_id = ? AND ward_id = ?");
                    $stmt->execute([$agent_id, $tenant_id, $ward_id]);
                    if ($stmt->rowCount() > 0) {
                        $action_message = "Agent suspended successfully.";
                        $action_type = 'success';
                        logActivity($user_id, 'agent_suspended', "Suspended agent ID: $agent_id", 'user', $agent_id);
                    }
                    break;
                    
                case 'activate':
                    $stmt = $db->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ? AND tenant_id = ? AND ward_id = ?");
                    $stmt->execute([$agent_id, $tenant_id, $ward_id]);
                    if ($stmt->rowCount() > 0) {
                        $action_message = "Agent activated successfully.";
                        $action_type = 'success';
                        logActivity($user_id, 'agent_activated', "Activated agent ID: $agent_id", 'user', $agent_id);
                    }
                    break;
                    
                case 'delete':
                    // Soft delete
                    $stmt = $db->prepare("UPDATE users SET deleted_at = NOW(), status = 'archived', updated_at = NOW() WHERE id = ? AND tenant_id = ? AND ward_id = ?");
                    $stmt->execute([$agent_id, $tenant_id, $ward_id]);
                    if ($stmt->rowCount() > 0) {
                        $action_message = "Agent deleted successfully.";
                        $action_type = 'success';
                        logActivity($user_id, 'agent_deleted', "Deleted agent ID: $agent_id", 'user', $agent_id);
                    }
                    break;
                    
                default:
                    $action_message = "Invalid action.";
                    $action_type = 'error';
            }
        } catch (Exception $e) {
            $action_message = "Error performing action: " . $e->getMessage();
            $action_type = 'error';
            error_log("Agent action error: " . $e->getMessage());
        }
    }
}

// ============================================================
// FETCH AGENTS WITH PAGINATION AND FILTERS
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$pu_filter = isset($_GET['pu_id']) ? (int)$_GET['pu_id'] : 0;

$agents = [];
$total_agents = 0;

try {
    // Build query conditions
    $conditions = "u.tenant_id = ? AND u.ward_id = ? AND u.deleted_at IS NULL";
    $params = [$tenant_id, $ward_id];
    
    // Role condition - PU Agents only
    $conditions .= " AND EXISTS (SELECT 1 FROM roles r WHERE r.id = u.role_id AND r.level = 'pu_agent')";
    
    if (!empty($search)) {
        $conditions .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
        $search_param = "%$search%";
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
    
    // Get total count
    $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM users u WHERE $conditions");
    $count_stmt->execute($params);
    $total_agents = (int)$count_stmt->fetchColumn();
    
    // Get agents with pagination
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
            (SELECT COUNT(*) FROM results_ec8a r WHERE r.agent_id = u.id) as total_submissions,
            (SELECT COUNT(*) FROM results_ec8a r WHERE r.agent_id = u.id AND r.status = 'verified') as verified_submissions,
            (SELECT COUNT(*) FROM results_ec8a r WHERE r.agent_id = u.id AND r.status = 'pending') as pending_submissions,
            (SELECT COUNT(*) FROM agent_assignments aa WHERE aa.user_id = u.id AND aa.status = 'active') as active_assignments,
            (SELECT COUNT(*) FROM user_sessions us WHERE us.user_id = u.id AND us.is_active = 1 AND us.last_activity_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)) as is_online
        FROM users u
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        WHERE $conditions
        ORDER BY u.full_name ASC
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching agents: " . $e->getMessage());
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

// ============================================================
// FETCH AGENT STATISTICS
// ============================================================
$agent_stats = [
    'total' => 0,
    'active' => 0,
    'suspended' => 0,
    'online' => 0,
    'assigned' => 0,
    'unassigned' => 0
];

try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended,
            SUM(CASE WHEN pu_id IS NOT NULL THEN 1 ELSE 0 END) as assigned,
            SUM(CASE WHEN pu_id IS NULL THEN 1 ELSE 0 END) as unassigned
        FROM users 
        WHERE tenant_id = ? AND ward_id = ? AND deleted_at IS NULL
        AND EXISTS (SELECT 1 FROM roles r WHERE r.id = users.role_id AND r.level = 'pu_agent')
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $stats_result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $agent_stats['total'] = (int)($stats_result['total'] ?? 0);
    $agent_stats['active'] = (int)($stats_result['active'] ?? 0);
    $agent_stats['suspended'] = (int)($stats_result['suspended'] ?? 0);
    $agent_stats['assigned'] = (int)($stats_result['assigned'] ?? 0);
    $agent_stats['unassigned'] = (int)($stats_result['unassigned'] ?? 0);
    
    // Online agents
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT u.id) as online
        FROM users u
        INNER JOIN user_sessions us ON u.id = us.user_id
        WHERE u.tenant_id = ? AND u.ward_id = ? AND u.deleted_at IS NULL
        AND us.is_active = 1 
        AND us.last_activity_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        AND EXISTS (SELECT 1 FROM roles r WHERE r.id = u.role_id AND r.level = 'pu_agent')
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $agent_stats['online'] = (int)$stmt->fetchColumn();
    
} catch (Exception $e) {
    error_log("Error fetching agent stats: " . $e->getMessage());
}

$page_title = 'Manage PU Agents';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.agents-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.agents-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.agents-header h2 i {
    color: var(--primary);
}
.agents-header .actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
.stat-mini {
    background: white;
    padding: 12px 16px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    text-align: center;
}
.stat-mini .number {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--gray-800);
}
.stat-mini .label {
    font-size: 0.65rem;
    color: var(--gray-500);
    font-weight: 500;
}
.stat-mini .number.green { color: #10B981; }
.stat-mini .number.red { color: #EF4444; }
.stat-mini .number.blue { color: #3B82F6; }
.stat-mini .number.purple { color: #8B5CF6; }
.stat-mini .number.orange { color: #F59E0B; }

.filter-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 16px;
    align-items: center;
}
.filter-bar .search-box {
    flex: 1;
    min-width: 200px;
    position: relative;
}
.filter-bar .search-box input {
    width: 100%;
    padding: 8px 12px 8px 36px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    background: white;
}
.filter-bar .search-box i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray-400);
}
.filter-bar select {
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    background: white;
    min-width: 140px;
}

.agents-table {
    width: 100%;
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
}
.agents-table table {
    width: 100%;
    border-collapse: collapse;
}
.agents-table th {
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
.agents-table td {
    padding: 10px 14px;
    font-size: 0.82rem;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}
.agents-table tr:last-child td {
    border-bottom: none;
}
.agents-table tr:hover {
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
.agent-actions .btn-sm.view:hover { background: #DBEAFE; }
.agent-actions .btn-sm.assign { background: #ECFDF5; color: #10B981; }
.agent-actions .btn-sm.assign:hover { background: #D1FAE5; }
.agent-actions .btn-sm.suspend { background: #FEF2F2; color: #EF4444; }
.agent-actions .btn-sm.suspend:hover { background: #FEE2E2; }
.agent-actions .btn-sm.activate { background: #FFFBEB; color: #F59E0B; }
.agent-actions .btn-sm.activate:hover { background: #FEF3C7; }
.agent-actions .btn-sm.delete { background: #FEF2F2; color: #DC2626; }
.agent-actions .btn-sm.delete:hover { background: #FEE2E2; }

.pagination {
    display: flex;
    justify-content: center;
    gap: 6px;
    padding: 16px 0;
}
.pagination a, .pagination span {
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 0.8rem;
    text-decoration: none;
    color: var(--gray-600);
    border: 1px solid var(--gray-200);
}
.pagination a:hover {
    background: var(--gray-50);
    border-color: var(--gray-300);
}
.pagination .active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
.pagination .disabled {
    opacity: 0.5;
    pointer-events: none;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--gray-500);
}
.empty-state i {
    font-size: 3rem;
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
    .stats-row {
        grid-template-columns: repeat(3, 1fr);
    }
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar .search-box {
        min-width: unset;
    }
    .agents-table {
        overflow-x: auto;
    }
    .agents-table table {
        min-width: 800px;
    }
    .agents-header {
        flex-direction: column;
        align-items: stretch;
    }
}

@media (max-width: 480px) {
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="agents-header">
            <div>
                <h2><i class="fas fa-user-tie"></i> Manage PU Agents</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward - <?php echo number_format($agent_stats['total']); ?> Agents
                </p>
            </div>
            <div class="actions">
                <a href="assign-agents.php" class="btn-primary-sm">
                    <i class="fas fa-user-plus"></i> Assign Agent
                </a>
                <a href="search-agents.php" class="btn-secondary-sm">
                    <i class="fas fa-search"></i> Search
                </a>
            </div>
        </div>

        <!-- Action Message -->
        <?php if (!empty($action_message)): ?>
            <div class="alert alert-<?php echo $action_type === 'success' ? 'success' : 'danger'; ?>" style="margin-bottom:16px;">
                <i class="fas fa-<?php echo $action_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($action_message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-mini">
                <div class="number blue"><?php echo number_format($agent_stats['total']); ?></div>
                <div class="label">Total Agents</div>
            </div>
            <div class="stat-mini">
                <div class="number green"><?php echo number_format($agent_stats['active']); ?></div>
                <div class="label">Active</div>
            </div>
            <div class="stat-mini">
                <div class="number red"><?php echo number_format($agent_stats['suspended']); ?></div>
                <div class="label">Suspended</div>
            </div>
            <div class="stat-mini">
                <div class="number purple"><?php echo number_format($agent_stats['online']); ?></div>
                <div class="label">Online Now</div>
            </div>
            <div class="stat-mini">
                <div class="number blue"><?php echo number_format($agent_stats['assigned']); ?></div>
                <div class="label">Assigned to PU</div>
            </div>
            <div class="stat-mini">
                <div class="number orange"><?php echo number_format($agent_stats['unassigned']); ?></div>
                <div class="label">Unassigned</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search by name, email or phone..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <select id="statusFilter">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
            </select>
            <select id="puFilter">
                <option value="0" <?php echo $pu_filter === 0 ? 'selected' : ''; ?>>All Polling Units</option>
                <?php foreach ($polling_units as $pu): ?>
                    <option value="<?php echo $pu['id']; ?>" <?php echo $pu_filter === (int)$pu['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($pu['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button onclick="applyFilters()" class="btn-secondary-sm">
                <i class="fas fa-filter"></i> Apply
            </button>
            <button onclick="resetFilters()" class="btn-secondary-sm" style="background: var(--gray-100);">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>

        <!-- Agents Table -->
        <div class="agents-table">
            <?php if (count($agents) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width:50px;">Avatar</th>
                            <th>Agent</th>
                            <th>Contact</th>
                            <th>Polling Unit</th>
                            <th>Submissions</th>
                            <th>Status</th>
                            <th style="width:180px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($agents as $agent): 
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
                                    <div>Total: <?php echo number_format($agent['total_submissions'] ?? 0); ?></div>
                                    <div style="font-size:0.65rem;">
                                        <span style="color:#10B981;">✓ <?php echo number_format($agent['verified_submissions'] ?? 0); ?></span>
                                        <span style="color:#F59E0B;">⏳ <?php echo number_format($agent['pending_submissions'] ?? 0); ?></span>
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
                                        
                                        <?php if (($agent['status'] ?? '') === 'active'): ?>
                                            <button onclick="confirmAction(<?php echo $agent['id']; ?>, 'suspend')" class="btn-sm suspend" title="Suspend">
                                                <i class="fas fa-pause"></i>
                                            </button>
                                        <?php elseif (($agent['status'] ?? '') === 'suspended'): ?>
                                            <button onclick="confirmAction(<?php echo $agent['id']; ?>, 'activate')" class="btn-sm activate" title="Activate">
                                                <i class="fas fa-play"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button onclick="confirmAction(<?php echo $agent['id']; ?>, 'delete')" class="btn-sm delete" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php 
                $total_pages = ceil($total_agents / $limit);
                if ($total_pages > 1): 
                ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&pu_id=<?php echo $pu_filter; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&pu_id=<?php echo $pu_filter; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&pu_id=<?php echo $pu_filter; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-tie"></i>
                    <h4>No Agents Found</h4>
                    <p>No PU agents have been assigned to this ward yet.</p>
                    <a href="assign-agents.php" class="btn-primary-sm" style="display:inline-block;margin-top:12px;">
                        <i class="fas fa-user-plus"></i> Assign First Agent
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
// Apply filters
function applyFilters() {
    const search = document.getElementById('searchInput').value;
    const status = document.getElementById('statusFilter').value;
    const pu = document.getElementById('puFilter').value;
    window.location.href = `?search=${encodeURIComponent(search)}&status=${status}&pu_id=${pu}`;
}

// Reset filters
function resetFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('statusFilter').value = 'all';
    document.getElementById('puFilter').value = '0';
    window.location.href = '?';
}

// Confirm action (Suspend/Activate/Delete)
function confirmAction(agentId, action) {
    const actionLabels = {
        'suspend': 'suspend',
        'activate': 'activate',
        'delete': 'delete'
    };
    
    const confirmMessages = {
        'suspend': 'Are you sure you want to suspend this agent? They will not be able to access the system.',
        'activate': 'Are you sure you want to activate this agent? They will regain access to the system.',
        'delete': 'Are you sure you want to delete this agent? This action can be reversed.'
    };
    
    if (confirm(confirmMessages[action] || 'Are you sure?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const agentInput = document.createElement('input');
        agentInput.type = 'hidden';
        agentInput.name = 'agent_id';
        agentInput.value = agentId;
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = action;
        
        form.appendChild(agentInput);
        form.appendChild(actionInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Enter key on search
document.getElementById('searchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        applyFilters();
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