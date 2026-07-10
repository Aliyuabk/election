<?php
// ============================================================
// LGA COORDINATOR - ASSIGN AGENT TO POLLING UNIT
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'lga') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'LGA Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$lga_id = SessionManager::get('lga_id');
$pu_id = isset($_GET['pu_id']) ? (int)$_GET['pu_id'] : 0;

if (empty($lga_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT lga_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['lga_id'])) {
            $lga_id = $user['lga_id'];
            SessionManager::set('lga_id', $lga_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching lga_id: " . $e->getMessage());
    }
}

if ($pu_id <= 0) {
    header('Location: polling-units.php');
    exit();
}

$db = getDB();

// Get LGA name
$lga_name = 'LGA';
try {
    if ($lga_id) {
        $stmt = $db->prepare("SELECT name FROM lgas WHERE id = ?");
        $stmt->execute([$lga_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $lga_name = $result['name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching LGA: " . $e->getMessage());
}

// Get PU details
$pu = null;
try {
    $stmt = $db->prepare("
        SELECT pu.*, w.name as ward_name, w.id as ward_id
        FROM polling_units pu
        JOIN wards w ON pu.ward_id = w.id
        WHERE pu.id = ? AND w.lga_id = ?
    ");
    $stmt->execute([$pu_id, $lga_id]);
    $pu = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching PU: " . $e->getMessage());
}

if (!$pu) {
    header('Location: polling-units.php');
    exit();
}

// Get available agents (users with pu_agent role not assigned to any PU)
$available_agents = [];
try {
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.user_code,
            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            u.status
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE r.level = 'pu_agent'
        AND u.tenant_id = ?
        AND u.lga_id = ?
        AND u.deleted_at IS NULL
        AND u.status = 'active'
        AND (u.pu_id IS NULL OR u.pu_id = 0 OR u.pu_id = ?)
        ORDER BY u.first_name ASC
    ");
    $stmt->execute([$tenant_id, $lga_id, $pu_id]);
    $available_agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching available agents: " . $e->getMessage());
}

// Get currently assigned agents for this PU
$assigned_agents = [];
try {
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.user_code,
            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            u.status
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE r.level = 'pu_agent'
        AND u.tenant_id = ?
        AND u.pu_id = ?
        AND u.deleted_at IS NULL
        AND u.status = 'active'
        ORDER BY u.first_name ASC
    ");
    $stmt->execute([$tenant_id, $pu_id]);
    $assigned_agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching assigned agents: " . $e->getMessage());
}

$message = '';
$error = '';

// Handle form submission - Assign agent
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $agent_id = isset($_POST['agent_id']) ? (int)$_POST['agent_id'] : 0;
    
    if ($action === 'assign' && $agent_id > 0) {
        try {
            // Check if agent is already assigned to another PU
            $stmt = $db->prepare("SELECT pu_id FROM users WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$agent_id, $tenant_id]);
            $agent = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($agent && !empty($agent['pu_id']) && $agent['pu_id'] != $pu_id) {
                $error = 'This agent is already assigned to another polling unit.';
            } else {
                // Assign agent to PU
                $stmt = $db->prepare("UPDATE users SET pu_id = ? WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$pu_id, $agent_id, $tenant_id]);
                
                logActivity($user_id, 'agent_assigned', 
                    "Assigned agent ID: $agent_id to PU: {$pu['name']} (ID: $pu_id)",
                    'users', $agent_id
                );
                
                $message = "Agent assigned successfully!";
                
                // Refresh lists
                $stmt = $db->prepare("
                    SELECT 
                        u.id,
                        u.user_code,
                        u.first_name,
                        u.last_name,
                        u.email,
                        u.phone,
                        u.status
                    FROM users u
                    JOIN roles r ON u.role_id = r.id
                    WHERE r.level = 'pu_agent'
                    AND u.tenant_id = ?
                    AND u.lga_id = ?
                    AND u.deleted_at IS NULL
                    AND u.status = 'active'
                    AND (u.pu_id IS NULL OR u.pu_id = 0 OR u.pu_id = ?)
                    ORDER BY u.first_name ASC
                ");
                $stmt->execute([$tenant_id, $lga_id, $pu_id]);
                $available_agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $stmt = $db->prepare("
                    SELECT 
                        u.id,
                        u.user_code,
                        u.first_name,
                        u.last_name,
                        u.email,
                        u.phone,
                        u.status
                    FROM users u
                    JOIN roles r ON u.role_id = r.id
                    WHERE r.level = 'pu_agent'
                    AND u.tenant_id = ?
                    AND u.pu_id = ?
                    AND u.deleted_at IS NULL
                    AND u.status = 'active'
                    ORDER BY u.first_name ASC
                ");
                $stmt->execute([$tenant_id, $pu_id]);
                $assigned_agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            $error = 'Failed to assign agent: ' . $e->getMessage();
        }
    } elseif ($action === 'remove') {
        $agent_id = isset($_POST['agent_id']) ? (int)$_POST['agent_id'] : 0;
        
        if ($agent_id > 0) {
            try {
                $stmt = $db->prepare("UPDATE users SET pu_id = NULL WHERE id = ? AND tenant_id = ? AND pu_id = ?");
                $stmt->execute([$agent_id, $tenant_id, $pu_id]);
                
                logActivity($user_id, 'agent_removed', 
                    "Removed agent ID: $agent_id from PU: {$pu['name']}",
                    'users', $agent_id
                );
                
                $message = "Agent removed successfully!";
                
                // Refresh lists
                $stmt = $db->prepare("
                    SELECT 
                        u.id,
                        u.user_code,
                        u.first_name,
                        u.last_name,
                        u.email,
                        u.phone,
                        u.status
                    FROM users u
                    JOIN roles r ON u.role_id = r.id
                    WHERE r.level = 'pu_agent'
                    AND u.tenant_id = ?
                    AND u.lga_id = ?
                    AND u.deleted_at IS NULL
                    AND u.status = 'active'
                    AND (u.pu_id IS NULL OR u.pu_id = 0 OR u.pu_id = ?)
                    ORDER BY u.first_name ASC
                ");
                $stmt->execute([$tenant_id, $lga_id, $pu_id]);
                $available_agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $stmt = $db->prepare("
                    SELECT 
                        u.id,
                        u.user_code,
                        u.first_name,
                        u.last_name,
                        u.email,
                        u.phone,
                        u.status
                    FROM users u
                    JOIN roles r ON u.role_id = r.id
                    WHERE r.level = 'pu_agent'
                    AND u.tenant_id = ?
                    AND u.pu_id = ?
                    AND u.deleted_at IS NULL
                    AND u.status = 'active'
                    ORDER BY u.first_name ASC
                ");
                $stmt->execute([$tenant_id, $pu_id]);
                $assigned_agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $error = 'Failed to remove agent: ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'Assign Agent';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.assign-container {
    max-width: 800px;
    margin: 0 auto;
}

.pu-header {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 18px 22px;
    margin-bottom: 20px;
}

.pu-header .pu-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--gray-800);
}

.pu-header .pu-details {
    font-size: 0.8rem;
    color: var(--gray-500);
    margin-top: 4px;
}

.pu-header .pu-details i {
    margin-right: 4px;
}

.section-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px 24px;
    margin-bottom: 16px;
}

.section-card .section-title {
    font-size: 0.85rem;
    font-weight: 600;
    margin: 0 0 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-700);
}

.section-card .section-title i {
    color: var(--primary);
    margin-right: 6px;
}

.alert {
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 0.85rem;
    margin-bottom: 16px;
}

.alert-success {
    background: #ECFDF5;
    color: #065F46;
    border: 1px solid #A7F3D0;
}

.alert-error {
    background: #FEF2F2;
    color: #991B1B;
    border: 1px solid #FECACA;
}

.alert i {
    margin-right: 6px;
}

.agent-list {
    display: grid;
    gap: 8px;
}

.agent-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 14px;
    background: var(--gray-50);
    border-radius: 8px;
    border: 1px solid var(--gray-200);
    transition: var(--transition);
}

.agent-item:hover {
    border-color: var(--primary);
}

.agent-item .agent-info .name {
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--gray-800);
}

.agent-item .agent-info .details {
    font-size: 0.7rem;
    color: var(--gray-500);
}

.agent-item .agent-info .details i {
    margin-right: 4px;
}

.agent-item .actions {
    display: flex;
    gap: 6px;
}

.agent-item .actions button {
    padding: 4px 14px;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.7rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.agent-item .actions .btn-assign {
    background: #3B82F6;
    color: white;
}

.agent-item .actions .btn-assign:hover {
    background: #2563EB;
}

.agent-item .actions .btn-remove {
    background: #EF4444;
    color: white;
}

.agent-item .actions .btn-remove:hover {
    background: #DC2626;
}

.agent-item .actions .btn-assign:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.empty-state {
    text-align: center;
    padding: 30px 20px;
    color: var(--gray-400);
}

.empty-state i {
    font-size: 2rem;
    display: block;
    margin-bottom: 8px;
}

.empty-state p {
    margin: 0;
}

.btn-back {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 20px;
    background: var(--gray-100);
    color: var(--gray-700);
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.82rem;
    text-decoration: none;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.btn-back:hover {
    background: var(--gray-200);
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

@media (max-width: 768px) {
    .section-card {
        padding: 14px 16px;
    }
    .agent-item {
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
        text-align: center;
    }
    .agent-item .actions {
        justify-content: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="assign-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-user-plus"></i> Assign Agent</h1>
                    <p class="subtitle">
                        <i class="fas fa-map-marker-alt"></i> 
                        <?php echo htmlspecialchars($lga_name); ?> LGA - Assign Agent to Polling Unit
                    </p>
                </div>
                <div class="actions">
                    <a href="pu-agents.php?pu_id=<?php echo $pu_id; ?>" class="btn-secondary-sm">
                        <i class="fas fa-arrow-left"></i> Back to Agents
                    </a>
                </div>
            </div>

            <!-- PU Info -->
            <div class="pu-header">
                <div class="pu-name">
                    <i class="fas fa-flag-checkered" style="color:var(--primary);"></i>
                    <?php echo htmlspecialchars($pu['name']); ?>
                </div>
                <div class="pu-details">
                    <i class="fas fa-code"></i> <?php echo htmlspecialchars($pu['code']); ?>
                    <span style="margin:0 6px;">•</span>
                    <i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($pu['ward_name']); ?>
                    <span style="margin:0 6px;">•</span>
                    <i class="fas fa-users"></i> <?php echo number_format($pu['registered_voters']); ?> voters
                    <span style="margin:0 6px;">•</span>
                    <span class="status-badge active">
                        <span class="dot"></span> <?php echo count($assigned_agents); ?> agent(s) assigned
                    </span>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Currently Assigned Agents -->
            <div class="section-card">
                <div class="section-title">
                    <i class="fas fa-users"></i> Currently Assigned Agents
                    <span style="font-size:0.7rem;color:var(--gray-400);font-weight:400;margin-left:8px;">
                        (<?php echo count($assigned_agents); ?>)
                    </span>
                </div>
                
                <?php if (!empty($assigned_agents)): ?>
                    <div class="agent-list">
                        <?php foreach ($assigned_agents as $agent): ?>
                            <div class="agent-item">
                                <div class="agent-info">
                                    <div class="name">
                                        <?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']); ?>
                                        <span class="status-badge <?php echo $agent['status']; ?>" style="margin-left:6px;">
                                            <span class="dot"></span> <?php echo ucfirst($agent['status']); ?>
                                        </span>
                                    </div>
                                    <div class="details">
                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($agent['email'] ?? 'N/A'); ?>
                                        <span style="margin-left:8px;">
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($agent['phone'] ?? 'N/A'); ?>
                                        </span>
                                        <span style="margin-left:8px;">
                                            <i class="fas fa-code"></i> <?php echo htmlspecialchars($agent['user_code']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="actions">
                                    <form method="POST" action="" onsubmit="return confirm('Remove this agent from this polling unit?')">
                                        <input type="hidden" name="action" value="remove" />
                                        <input type="hidden" name="agent_id" value="<?php echo $agent['id']; ?>" />
                                        <button type="submit" class="btn-remove">
                                            <i class="fas fa-user-minus"></i> Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>No agents currently assigned to this polling unit.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Available Agents -->
            <div class="section-card">
                <div class="section-title">
                    <i class="fas fa-user-plus"></i> Available Agents
                    <span style="font-size:0.7rem;color:var(--gray-400);font-weight:400;margin-left:8px;">
                        (<?php echo count($available_agents); ?> available)
                    </span>
                </div>
                
                <?php if (!empty($available_agents)): ?>
                    <div class="agent-list">
                        <?php foreach ($available_agents as $agent): ?>
                            <div class="agent-item">
                                <div class="agent-info">
                                    <div class="name">
                                        <?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']); ?>
                                        <span class="status-badge <?php echo $agent['status']; ?>" style="margin-left:6px;">
                                            <span class="dot"></span> <?php echo ucfirst($agent['status']); ?>
                                        </span>
                                    </div>
                                    <div class="details">
                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($agent['email'] ?? 'N/A'); ?>
                                        <span style="margin-left:8px;">
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($agent['phone'] ?? 'N/A'); ?>
                                        </span>
                                        <span style="margin-left:8px;">
                                            <i class="fas fa-code"></i> <?php echo htmlspecialchars($agent['user_code']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="actions">
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="assign" />
                                        <input type="hidden" name="agent_id" value="<?php echo $agent['id']; ?>" />
                                        <button type="submit" class="btn-assign">
                                            <i class="fas fa-user-plus"></i> Assign
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-user-plus"></i>
                        <p>No available agents to assign. All agents are already assigned.</p>
                        <p style="font-size:0.75rem;margin-top:4px;">
                            <a href="create-agent.php" class="btn-primary-sm" style="margin-top:8px;">
                                <i class="fas fa-user-plus"></i> Create New Agent
                            </a>
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Back Button -->
            <div style="margin-top:16px;">
                <a href="pu-agents.php?pu_id=<?php echo $pu_id; ?>" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Agents
                </a>
            </div>
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