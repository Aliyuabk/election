<?php
// ============================================================
// SENATORIAL COORDINATOR - VIEW AGENTS
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/agent-functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'senatorial') {
    header('Location: ../client-admin/');
    exit();
}

$user_id = SessionManager::get('user_id');
$user_name = SessionManager::get('user_name', 'Coordinator');
$senatorial_id = SessionManager::get('senatorial_id');
$tenant_id = SessionManager::get('tenant_id');

$db = getDB();

// Get Senatorial District name
$district_name = 'Senatorial District';
try {
    if ($senatorial_id) {
        $stmt = $db->prepare("SELECT name FROM senatorial_districts WHERE id = ?");
        $stmt->execute([$senatorial_id]);
        $district_name = $stmt->fetchColumn() ?: 'Senatorial District';
    }
} catch (Exception $e) {
    $district_name = 'Senatorial District';
}

// Get all agents in this senatorial district
$agents = getAgentsBySenatorialDistrict($senatorial_id);

// Get statistics
$stats = [
    'total' => count($agents),
    'party_agents' => 0,
    'pu_agents' => 0,
    'volunteers' => 0,
    'observers' => 0,
    'active' => 0,
    'pending' => 0,
    'suspended' => 0
];

foreach ($agents as $agent) {
    $role = $agent['role_level'] ?? '';
    if ($role === 'party_agent') $stats['party_agents']++;
    elseif ($role === 'pu_agent') $stats['pu_agents']++;
    elseif ($role === 'volunteer') $stats['volunteers']++;
    elseif ($role === 'observer') $stats['observers']++;
    
    $status = $agent['status'] ?? '';
    if ($status === 'active') $stats['active']++;
    elseif ($status === 'pending') $stats['pending']++;
    elseif ($status === 'suspended') $stats['suspended']++;
}

// Get agents grouped by role
$grouped_agents = [
    'party_agent' => [],
    'pu_agent' => [],
    'volunteer' => [],
    'observer' => []
];

foreach ($agents as $agent) {
    $role = $agent['role_level'] ?? '';
    if (isset($grouped_agents[$role])) {
        $grouped_agents[$role][] = $agent;
    }
}

$page_title = 'View Agents';
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
    font-size: 1.5rem;
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
.stat-card.red .stat-number { color: #DC2626; }
.stat-card.red .stat-icon { color: #DC2626; }
.stat-card.teal .stat-number { color: #0D9488; }
.stat-card.teal .stat-icon { color: #0D9488; }

.role-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.role-tab {
    padding: 8px 20px;
    border-radius: 8px;
    border: 1px solid var(--gray-200);
    background: white;
    cursor: pointer;
    font-weight: 500;
    font-size: 0.85rem;
    transition: var(--transition);
}
.role-tab:hover {
    border-color: var(--primary);
}
.role-tab.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
.role-tab .badge {
    display: inline-block;
    background: var(--gray-200);
    color: var(--gray-600);
    border-radius: 20px;
    padding: 0px 8px;
    font-size: 0.7rem;
    margin-left: 4px;
}
.role-tab.active .badge {
    background: rgba(255,255,255,0.2);
    color: white;
}

.agent-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px 20px;
    margin-bottom: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    transition: var(--transition);
}
.agent-card:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow-sm);
}

.agent-info {
    display: flex;
    align-items: center;
    gap: 14px;
}
.agent-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
    color: white;
    flex-shrink: 0;
}
.agent-avatar.party { background: #7C3AED; }
.agent-avatar.pu { background: #2563EB; }
.agent-avatar.volunteer { background: #059669; }
.agent-avatar.observer { background: #EA580C; }

.agent-details .name {
    font-weight: 600;
    font-size: 0.9rem;
}
.agent-details .role {
    font-size: 0.75rem;
    color: var(--gray-500);
}
.agent-details .location {
    font-size: 0.7rem;
    color: var(--gray-400);
}

.agent-meta {
    display: flex;
    gap: 16px;
    align-items: center;
    flex-wrap: wrap;
}
.agent-meta .status-badge {
    display: inline-block;
    padding: 2px 12px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
}
.agent-meta .status-badge.active { background: #D1FAE5; color: #059669; }
.agent-meta .status-badge.pending { background: #FEF3C7; color: #D97706; }
.agent-meta .status-badge.suspended { background: #FEE2E2; color: #DC2626; }

.agent-actions {
    display: flex;
    gap: 8px;
}
.agent-actions .btn-sm {
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 0.7rem;
    text-decoration: none;
    border: none;
    cursor: pointer;
}
.agent-actions .btn-sm.primary {
    background: var(--primary);
    color: white;
}
.agent-actions .btn-sm.secondary {
    background: var(--gray-100);
    color: var(--gray-600);
}
.agent-actions .btn-sm.danger {
    background: #FEE2E2;
    color: #DC2626;
}

.agent-section {
    display: none;
}
.agent-section.active {
    display: block;
}

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
    .agent-card {
        flex-direction: column;
        align-items: stretch;
    }
    .agent-meta {
        justify-content: flex-start;
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
                    <i class="fas fa-users" style="color:var(--primary);margin-right:8px;"></i> 
                    Agents Management
                    <small><?php echo htmlspecialchars($district_name); ?> - Senatorial District</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="users-create.php" class="btn btn-primary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--primary);color:white;border:none;">
                    <i class="fas fa-user-plus"></i> Add Agent
                </a>
                <a href="monitor-district.php" class="btn btn-secondary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--gray-100);color:var(--gray-600);border:none;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
                <div class="stat-label">Total Agents</div>
            </div>
            <div class="stat-card purple">
                <div class="stat-icon"><i class="fas fa-flag"></i></div>
                <div class="stat-number"><?php echo number_format($stats['party_agents']); ?></div>
                <div class="stat-label">Party Agents</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                <div class="stat-number"><?php echo number_format($stats['pu_agents']); ?></div>
                <div class="stat-label">PU Agents</div>
            </div>
            <div class="stat-card teal">
                <div class="stat-icon"><i class="fas fa-hands-helping"></i></div>
                <div class="stat-number"><?php echo number_format($stats['volunteers']); ?></div>
                <div class="stat-label">Volunteers</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-icon"><i class="fas fa-eye"></i></div>
                <div class="stat-number"><?php echo number_format($stats['observers']); ?></div>
                <div class="stat-label">Observers</div>
            </div>
            <div class="stat-card red">
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['suspended']); ?></div>
                <div class="stat-label">Suspended</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-row">
            <select id="filterStatus" onchange="filterAgents()">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="pending">Pending</option>
                <option value="suspended">Suspended</option>
            </select>
            <select id="filterRole" onchange="filterAgents()">
                <option value="">All Roles</option>
                <option value="party_agent">Party Agent</option>
                <option value="pu_agent">PU Agent</option>
                <option value="volunteer">Volunteer</option>
                <option value="observer">Observer</option>
            </select>
            <select id="filterLGA" onchange="filterAgents()">
                <option value="">All LGAs</option>
                <?php
                // Get LGAs in this senatorial district
                try {
                    $lga_ids = [];
                    $stmt = $db->prepare("SELECT lgas_json FROM senatorial_districts WHERE id = ?");
                    $stmt->execute([$senatorial_id]);
                    $lgas_json = $stmt->fetchColumn();
                    if ($lgas_json) {
                        $lga_ids = json_decode($lgas_json, true) ?: [];
                    }
                    if (!empty($lga_ids)) {
                        $lga_list = implode(',', array_map('intval', $lga_ids));
                        $stmt = $db->prepare("SELECT id, name FROM lgas WHERE id IN ($lga_list) ORDER BY name ASC");
                        $stmt->execute();
                        $lgas = $stmt->fetchAll();
                        foreach ($lgas as $lga) {
                            echo '<option value="' . $lga['id'] . '">' . htmlspecialchars($lga['name']) . '</option>';
                        }
                    }
                } catch (Exception $e) {}
                ?>
            </select>
            <input type="text" id="searchInput" placeholder="Search agents..." onkeyup="filterAgents()">
        </div>

        <!-- Role Tabs -->
        <div class="role-tabs">
            <button class="role-tab active" data-role="all" onclick="switchTab('all')">
                <i class="fas fa-users"></i> All
                <span class="badge"><?php echo $stats['total']; ?></span>
            </button>
            <button class="role-tab" data-role="party_agent" onclick="switchTab('party_agent')">
                <i class="fas fa-flag"></i> Party Agents
                <span class="badge"><?php echo $stats['party_agents']; ?></span>
            </button>
            <button class="role-tab" data-role="pu_agent" onclick="switchTab('pu_agent')">
                <i class="fas fa-user-check"></i> PU Agents
                <span class="badge"><?php echo $stats['pu_agents']; ?></span>
            </button>
            <button class="role-tab" data-role="volunteer" onclick="switchTab('volunteer')">
                <i class="fas fa-hands-helping"></i> Volunteers
                <span class="badge"><?php echo $stats['volunteers']; ?></span>
            </button>
            <button class="role-tab" data-role="observer" onclick="switchTab('observer')">
                <i class="fas fa-eye"></i> Observers
                <span class="badge"><?php echo $stats['observers']; ?></span>
            </button>
        </div>

        <!-- Agent Lists -->
        <?php foreach (['all', 'party_agent', 'pu_agent', 'volunteer', 'observer'] as $role): ?>
            <div class="agent-section <?php echo $role === 'all' ? 'active' : ''; ?>" id="section-<?php echo $role; ?>">
                <?php 
                $agents_to_show = $role === 'all' ? $agents : ($grouped_agents[$role] ?? []);
                if (count($agents_to_show) > 0): 
                ?>
                    <?php foreach ($agents_to_show as $agent): 
                        $role_class = $agent['role_level'] ?? 'party';
                        $role_display = [
                            'party_agent' => 'Party Agent',
                            'pu_agent' => 'PU Agent',
                            'volunteer' => 'Volunteer',
                            'observer' => 'Observer'
                        ][$role_class] ?? 'Agent';
                    ?>
                        <div class="agent-card" data-role="<?php echo $role_class; ?>" data-status="<?php echo $agent['status'] ?? 'active'; ?>" data-lga="<?php echo $agent['lga_id'] ?? 0; ?>">
                            <div class="agent-info">
                                <div class="agent-avatar <?php echo $role_class; ?>">
                                    <?php echo strtoupper(substr($agent['first_name'] ?? '', 0, 1) . substr($agent['last_name'] ?? '', 0, 1)); ?>
                                </div>
                                <div class="agent-details">
                                    <div class="name">
                                        <?php echo htmlspecialchars($agent['first_name'] ?? '') . ' ' . htmlspecialchars($agent['last_name'] ?? ''); ?>
                                    </div>
                                    <div class="role">
                                        <i class="fas <?php echo $role_class === 'party_agent' ? 'fa-flag' : ($role_class === 'pu_agent' ? 'fa-user-check' : ($role_class === 'volunteer' ? 'fa-hands-helping' : 'fa-eye')); ?>"></i>
                                        <?php echo $role_display; ?>
                                    </div>
                                    <div class="location">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php 
                                        $location = [];
                                        if ($agent['lga_name']) $location[] = $agent['lga_name'];
                                        if ($agent['ward_name']) $location[] = $agent['ward_name'];
                                        if ($agent['pu_name']) $location[] = $agent['pu_name'];
                                        echo htmlspecialchars(implode(' → ', $location) ?: 'No location set');
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <div class="agent-meta">
                                <span class="status-badge <?php echo $agent['status'] ?? 'pending'; ?>">
                                    <?php echo ucfirst($agent['status'] ?? 'Pending'); ?>
                                </span>
                                <?php if ($agent['email']): ?>
                                    <span style="font-size:0.7rem;color:var(--gray-400);">
                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($agent['email']); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($agent['phone']): ?>
                                    <span style="font-size:0.7rem;color:var(--gray-400);">
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($agent['phone']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="agent-actions">
                                <a href="agent-details.php?id=<?php echo $agent['id']; ?>" class="btn-sm primary">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="agent-edit.php?id=<?php echo $agent['id']; ?>" class="btn-sm secondary">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="agent-assignments.php?id=<?php echo $agent['id']; ?>" class="btn-sm secondary">
                                    <i class="fas fa-clipboard-list"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>No <?php echo $role === 'all' ? 'agents' : $role_display ?? 'agents'; ?> found in this senatorial district.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<script>
// ============================================================
// TAB SWITCHING
// ============================================================
function switchTab(role) {
    // Update tabs
    document.querySelectorAll('.role-tab').forEach(function(tab) {
        tab.classList.toggle('active', tab.dataset.role === role);
    });
    
    // Update sections
    document.querySelectorAll('.agent-section').forEach(function(section) {
        section.classList.toggle('active', section.id === 'section-' + role);
    });
}

// ============================================================
// FILTER AGENTS
// ============================================================
function filterAgents() {
    var status = document.getElementById('filterStatus').value;
    var role = document.getElementById('filterRole').value;
    var lga = document.getElementById('filterLGA').value;
    var search = document.getElementById('searchInput').value.toLowerCase();
    
    var cards = document.querySelectorAll('.agent-card');
    
    cards.forEach(function(card) {
        var show = true;
        var cardStatus = card.dataset.status || '';
        var cardRole = card.dataset.role || '';
        var cardLga = card.dataset.lga || '';
        var cardText = card.textContent.toLowerCase();
        
        if (status && cardStatus !== status) show = false;
        if (role && cardRole !== role) show = false;
        if (lga && cardLga !== lga) show = false;
        if (search && !cardText.includes(search)) show = false;
        
        card.style.display = show ? '' : 'none';
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