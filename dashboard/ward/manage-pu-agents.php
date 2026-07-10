<?php
// ============================================================
// WARD COORDINATOR - MANAGE PU AGENTS
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

// Get PU agents for this ward
$agents = [];
$stats = [
    'total' => 0,
    'active' => 0,
    'suspended' => 0,
    'pending' => 0,
    'online' => 0,
    'by_pu' => []
];

try {
    $stmt = $db->prepare("
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
            (SELECT COUNT(*) FROM results_ec8a ra WHERE ra.agent_id = u.id AND ra.status = 'pending') as pending_results,
            (SELECT COUNT(*) FROM incidents i WHERE i.reporter_id = u.id) as incidents_reported
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        WHERE u.tenant_id = ? 
        AND u.ward_id = ? 
        AND r.level = 'pu_agent'
        AND u.deleted_at IS NULL
        ORDER BY pu.name ASC, u.first_name ASC
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($agents as $agent) {
        $stats['total']++;
        $stats[$agent['status']] = ($stats[$agent['status']] ?? 0) + 1;
        if ($agent['is_online'] > 0) {
            $stats['online']++;
        }
        if ($agent['pu_id']) {
            $pu_key = $agent['pu_id'];
            if (!isset($stats['by_pu'][$pu_key])) {
                $stats['by_pu'][$pu_key] = [
                    'name' => $agent['pu_name'],
                    'count' => 0
                ];
            }
            $stats['by_pu'][$pu_key]['count']++;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching agents: " . $e->getMessage());
}

$page_title = 'Manage PU Agents';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.agent-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
    margin-top: 16px;
}

.agent-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px 18px;
    transition: var(--transition);
}

.agent-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-hover);
}

.agent-card .card-top {
    display: flex;
    align-items: center;
    gap: 12px;
}

.agent-card .avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.agent-card .info .name {
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--gray-800);
}

.agent-card .info .pu {
    font-size: 0.7rem;
    color: var(--primary);
    font-weight: 500;
}

.agent-card .info .pu i {
    margin-right: 4px;
}

.agent-card .badges {
    display: flex;
    gap: 4px;
    margin: 6px 0;
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

.agent-card .stats {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 4px;
    margin: 8px 0;
    padding: 6px 0;
    border-top: 1px solid var(--gray-100);
    border-bottom: 1px solid var(--gray-100);
}

.agent-card .stats .stat-item {
    text-align: center;
}

.agent-card .stats .stat-item .number {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--gray-800);
}

.agent-card .stats .stat-item .label {
    font-size: 0.5rem;
    color: var(--gray-500);
}

.agent-card .actions {
    display: flex;
    gap: 4px;
    margin-top: 8px;
    flex-wrap: wrap;
}

.agent-card .actions a {
    padding: 3px 10px;
    border-radius: 4px;
    font-size: 0.6rem;
    font-weight: 500;
    text-decoration: none;
    transition: var(--transition);
}

.agent-card .actions .btn-profile {
    background: var(--primary);
    color: white;
}

.agent-card .actions .btn-profile:hover {
    background: var(--primary-dark);
}

.agent-card .actions .btn-assign {
    background: #EFF6FF;
    color: #3B82F6;
}

.agent-card .actions .btn-assign:hover {
    background: #DBEAFE;
}

.agent-card .actions .btn-suspend {
    background: #FEF2F2;
    color: #DC2626;
}

.agent-card .actions .btn-suspend:hover {
    background: #FEE2E2;
}

.agent-card .actions .btn-activate {
    background: #ECFDF5;
    color: #10B981;
}

.agent-card .actions .btn-activate:hover {
    background: #D1FAE5;
}

.agent-card .actions .btn-reset {
    background: #FFFBEB;
    color: #D97706;
}

.agent-card .actions .btn-reset:hover {
    background: #FEF3C7;
}

.empty-state {
    grid-column: 1/-1;
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}

.empty-state i {
    font-size: 3rem;
    color: var(--gray-300);
    display: block;
    margin-bottom: 12px;
}

.empty-state h3 {
    color: var(--gray-600);
    margin: 0;
}

.empty-state p {
    color: var(--gray-400);
    margin-top: 6px;
}

.summary-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}

.summary-stat {
    background: white;
    border-radius: 10px;
    padding: 10px 12px;
    border: 1px solid var(--gray-200);
    text-align: center;
}

.summary-stat .number {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--gray-800);
}

.summary-stat .label {
    font-size: 0.6rem;
    color: var(--gray-500);
}

@media (max-width: 768px) {
    .agent-grid {
        grid-template-columns: 1fr;
    }
    .summary-stats {
        grid-template-columns: repeat(3, 1fr);
    }
    .agent-card .stats {
        grid-template-columns: 1fr 1fr 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="welcome-section">
            <div>
                <h1><i class="fas fa-user-tie"></i> Manage PU Agents</h1>
                <p class="subtitle">
                    <i class="fas fa-layer-group"></i> 
                    <?php echo htmlspecialchars($ward_name); ?> Ward - Manage Polling Unit Agents
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

        <!-- Summary Stats -->
        <div class="summary-stats">
            <div class="summary-stat">
                <div class="number"><?php echo number_format($stats['total']); ?></div>
                <div class="label">Total Agents</div>
            </div>
            <div class="summary-stat">
                <div class="number" style="color:#10B981;"><?php echo number_format($stats['active']); ?></div>
                <div class="label">Active</div>
            </div>
            <div class="summary-stat">
                <div class="number" style="color:#EF4444;"><?php echo number_format($stats['suspended']); ?></div>
                <div class="label">Suspended</div>
            </div>
            <div class="summary-stat">
                <div class="number" style="color:#F59E0B;"><?php echo number_format($stats['pending']); ?></div>
                <div class="label">Pending</div>
            </div>
            <div class="summary-stat">
                <div class="number" style="color:#3B82F6;"><?php echo number_format($stats['online']); ?></div>
                <div class="label">Online</div>
            </div>
            <div class="summary-stat">
                <div class="number" style="color:#8B5CF6;"><?php echo number_format(count($stats['by_pu'])); ?></div>
                <div class="label">PUs Covered</div>
            </div>
        </div>

        <!-- Agents Grid -->
        <div class="agent-grid">
            <?php foreach ($agents as $agent): 
                $full_name = $agent['first_name'] . ' ' . $agent['last_name'];
                $initials = strtoupper(substr($agent['first_name'], 0, 1) . substr($agent['last_name'], 0, 1));
                $online_status = $agent['is_online'] > 0 ? 'online' : 'offline';
                $online_label = $agent['is_online'] > 0 ? 'Online' : 'Offline';
            ?>
                <div class="agent-card">
                    <div class="card-top">
                        <div class="avatar"><?php echo $initials; ?></div>
                        <div class="info">
                            <div class="name"><?php echo htmlspecialchars($full_name); ?></div>
                            <div class="pu">
                                <?php if ($agent['pu_id']): ?>
                                    <i class="fas fa-flag-checkered"></i> <?php echo htmlspecialchars($agent['pu_name']); ?>
                                    <span style="font-size:0.6rem;color:var(--gray-400);">(<?php echo htmlspecialchars($agent['pu_code']); ?>)</span>
                                <?php else: ?>
                                    <span style="color:var(--gray-400);">Unassigned</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:0.6rem;color:var(--gray-400);">
                                <?php echo htmlspecialchars($agent['user_code']); ?>
                            </div>
                        </div>
                    </div>

                    <div class="badges">
                        <span class="status-badge <?php echo $agent['status']; ?>">
                            <span class="dot"></span> <?php echo ucfirst($agent['status']); ?>
                        </span>
                        <span class="status-badge <?php echo $online_status; ?>">
                            <span class="dot"></span> <?php echo $online_label; ?>
                        </span>
                    </div>

                    <div class="stats">
                        <div class="stat-item">
                            <div class="number" style="color:#10B981;"><?php echo number_format($agent['verified_results']); ?></div>
                            <div class="label">Verified</div>
                        </div>
                        <div class="stat-item">
                            <div class="number" style="color:#F59E0B;"><?php echo number_format($agent['pending_results']); ?></div>
                            <div class="label">Pending</div>
                        </div>
                        <div class="stat-item">
                            <div class="number" style="color:#EF4444;"><?php echo number_format($agent['incidents_reported']); ?></div>
                            <div class="label">Incidents</div>
                        </div>
                    </div>

                    <div style="font-size:0.65rem;color:var(--gray-400);margin:4px 0;">
                        <?php if (!empty($agent['email'])): ?>
                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($agent['email']); ?>
                        <?php endif; ?>
                        <?php if (!empty($agent['phone'])): ?>
                            <span style="margin-left:6px;"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($agent['phone']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="actions">
                        <a href="agent-profile.php?id=<?php echo $agent['id']; ?>" class="btn-profile">
                            <i class="fas fa-id-card"></i> Profile
                        </a>
                        <?php if (!$agent['pu_id']): ?>
                            <a href="assign-agents.php?agent_id=<?php echo $agent['id']; ?>" class="btn-assign">
                                <i class="fas fa-user-plus"></i> Assign
                            </a>
                        <?php else: ?>
                            <a href="reassign-agent.php?agent_id=<?php echo $agent['id']; ?>" class="btn-assign">
                                <i class="fas fa-exchange-alt"></i> Reassign
                            </a>
                        <?php endif; ?>
                        <?php if ($agent['status'] === 'active'): ?>
                            <a href="suspend-agent.php?id=<?php echo $agent['id']; ?>" class="btn-suspend" onclick="return confirm('Suspend this agent?')">
                                <i class="fas fa-pause"></i> Suspend
                            </a>
                        <?php elseif ($agent['status'] === 'suspended'): ?>
                            <a href="activate-agent.php?id=<?php echo $agent['id']; ?>" class="btn-activate">
                                <i class="fas fa-play"></i> Activate
                            </a>
                        <?php endif; ?>
                        <a href="reset-agent-password.php?id=<?php echo $agent['id']; ?>" class="btn-reset">
                            <i class="fas fa-key"></i> Reset
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($agents)): ?>
                <div class="empty-state">
                    <i class="fas fa-user-tie"></i>
                    <h3>No Agents Found</h3>
                    <p>No PU agents have been assigned to <?php echo htmlspecialchars($ward_name); ?> yet.</p>
                    <a href="assign-agents.php" class="btn-primary-sm" style="margin-top:12px;">
                        <i class="fas fa-user-plus"></i> Assign Agent
                    </a>
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