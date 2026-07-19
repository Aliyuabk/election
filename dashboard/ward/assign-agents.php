<?php
// ============================================================
// WARD COORDINATOR - ASSIGN AGENTS TO POLLING UNITS (FIXED)
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
// HANDLE AGENT ASSIGNMENT (FIXED)
// ============================================================
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $agent_id = isset($_POST['agent_id']) ? (int)$_POST['agent_id'] : 0;
    $pu_id = isset($_POST['pu_id']) ? (int)$_POST['pu_id'] : 0;
    $assignment_type = isset($_POST['assignment_type']) ? $_POST['assignment_type'] : 'data_agent';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    if ($agent_id > 0 && $pu_id > 0) {
        try {
            // Start transaction
            $db->beginTransaction();
            
            // Check if agent is already assigned to a PU
            $stmt = $db->prepare("SELECT pu_id FROM users WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$agent_id, $tenant_id]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($current && !empty($current['pu_id'])) {
                // Agent already assigned - update user's PU
                $stmt = $db->prepare("UPDATE users SET pu_id = ? WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$pu_id, $agent_id, $tenant_id]);
                
                // Update existing assignment - set status to 'reassigned' and create new one
                $stmt = $db->prepare("
                    UPDATE agent_assignments 
                    SET status = 'reassigned' 
                    WHERE user_id = ? AND status = 'active'
                ");
                $stmt->execute([$agent_id]);
                
                $success_message = "Agent reassigned to polling unit successfully.";
            } else {
                // Assign agent to PU
                $stmt = $db->prepare("UPDATE users SET pu_id = ? WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$pu_id, $agent_id, $tenant_id]);
                
                $success_message = "Agent assigned to polling unit successfully.";
            }
            
            // Get current election
            $election_stmt = $db->prepare("
                SELECT id FROM elections 
                WHERE tenant_id = ? AND status = 'active' 
                AND JSON_CONTAINS(wards_json, JSON_QUOTE(?))
                LIMIT 1
            ");
            $election_stmt->execute([$tenant_id, $ward_id]);
            $election = $election_stmt->fetch(PDO::FETCH_ASSOC);
            $election_id = $election ? $election['id'] : null;
            
            // Create NEW assignment record (always insert new)
            $stmt = $db->prepare("
                INSERT INTO agent_assignments (
                    tenant_id, election_id, user_id, pu_id, ward_id, lga_id, state_id,
                    assignment_type, status, assigned_by, notes, assigned_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, NOW())
            ");
            
            $stmt->execute([
                $tenant_id,
                $election_id,
                $agent_id,
                $pu_id,
                $ward_id,
                $lga_id,
                $state_id,
                $assignment_type,
                $user_id,
                $notes
            ]);
            
            // Log activity
            logActivity($user_id, 'agent_assigned', "Assigned agent ID: $agent_id to PU: $pu_id", 'user', $agent_id);
            
            $db->commit();
            
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = "Error assigning agent: " . $e->getMessage();
            error_log("Agent assignment error: " . $e->getMessage());
        }
    } else {
        $error_message = "Please select both an agent and a polling unit.";
    }
}

// ============================================================
// FETCH UNASSIGNED AGENTS
// ============================================================
$unassigned_agents = [];
$assigned_agents = [];
$polling_units = [];

try {
    // Get unassigned agents (PU Agents without PU assignment)
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.user_code,
            u.full_name,
            u.email,
            u.phone,
            u.status,
            u.created_at
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.tenant_id = ? 
        AND u.ward_id = ?
        AND u.deleted_at IS NULL
        AND r.level = 'pu_agent'
        AND (u.pu_id IS NULL OR u.pu_id = 0)
        AND u.status = 'active'
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $unassigned_agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get assigned agents (for reassign)
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.user_code,
            u.full_name,
            u.email,
            u.phone,
            u.status,
            u.pu_id,
            pu.name as pu_name,
            pu.code as pu_code
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        WHERE u.tenant_id = ? 
        AND u.ward_id = ?
        AND u.deleted_at IS NULL
        AND r.level = 'pu_agent'
        AND u.pu_id IS NOT NULL
        AND u.pu_id > 0
        AND u.status = 'active'
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $assigned_agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get polling units without assigned agents or with capacity
    $stmt = $db->prepare("
        SELECT 
            pu.id,
            pu.name,
            pu.code,
            pu.registered_voters,
            COUNT(DISTINCT u.id) as assigned_agents
        FROM polling_units pu
        LEFT JOIN users u ON u.pu_id = pu.id AND u.status = 'active' AND u.deleted_at IS NULL
        WHERE pu.ward_id = ? AND pu.is_active = 1
        GROUP BY pu.id, pu.name, pu.code, pu.registered_voters
        ORDER BY pu.name ASC
    ");
    $stmt->execute([$ward_id]);
    $polling_units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching data: " . $e->getMessage());
}

// ============================================================
// FETCH AGENT STATISTICS
// ============================================================
$agent_stats = [
    'total' => 0,
    'assigned' => 0,
    'unassigned' => 0
];

try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN pu_id IS NOT NULL AND pu_id > 0 THEN 1 ELSE 0 END) as assigned,
            SUM(CASE WHEN pu_id IS NULL OR pu_id = 0 THEN 1 ELSE 0 END) as unassigned
        FROM users 
        WHERE tenant_id = ? AND ward_id = ? AND deleted_at IS NULL AND status = 'active'
        AND EXISTS (SELECT 1 FROM roles r WHERE r.id = users.role_id AND r.level = 'pu_agent')
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $agent_stats['total'] = (int)($stats['total'] ?? 0);
    $agent_stats['assigned'] = (int)($stats['assigned'] ?? 0);
    $agent_stats['unassigned'] = (int)($stats['unassigned'] ?? 0);
    
} catch (Exception $e) {
    error_log("Error fetching agent stats: " . $e->getMessage());
}

$page_title = 'Assign Agents';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.assign-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.assign-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.assign-header h2 i {
    color: var(--primary);
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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
}
.stat-mini .number.green { color: #10B981; }
.stat-mini .number.blue { color: #3B82F6; }
.stat-mini .number.orange { color: #F59E0B; }
.stat-mini .label {
    font-size: 0.65rem;
    color: var(--gray-500);
    font-weight: 500;
}

.assign-form {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px;
    margin-bottom: 20px;
}
.assign-form .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr auto;
    gap: 16px;
    align-items: end;
}
.assign-form .form-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.assign-form .form-group label {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--gray-700);
}
.assign-form .form-group select,
.assign-form .form-group input,
.assign-form .form-group textarea {
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    background: white;
    width: 100%;
}
.assign-form .form-group textarea {
    resize: vertical;
    min-height: 38px;
    max-height: 100px;
}

.agents-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.agent-list {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
}
.agent-list .list-header {
    background: var(--gray-50);
    padding: 10px 16px;
    font-weight: 600;
    font-size: 0.8rem;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.agent-list .list-header .count {
    font-weight: 400;
    color: var(--gray-500);
    font-size: 0.7rem;
}
.agent-list .list-body {
    max-height: 400px;
    overflow-y: auto;
}
.agent-list .list-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 16px;
    border-bottom: 1px solid var(--gray-100);
    font-size: 0.82rem;
}
.agent-list .list-item:last-child {
    border-bottom: none;
}
.agent-list .list-item .info {
    flex: 1;
}
.agent-list .list-item .info .name {
    font-weight: 500;
}
.agent-list .list-item .info .sub {
    font-size: 0.7rem;
    color: var(--gray-500);
}
.agent-list .list-item .badge {
    font-size: 0.6rem;
    padding: 2px 10px;
    border-radius: 20px;
    font-weight: 500;
}
.agent-list .list-item .badge.assigned { background: #ECFDF5; color: #10B981; }
.agent-list .list-item .badge.unassigned { background: #FEF3C7; color: #F59E0B; }
.agent-list .list-item .badge.active { background: #ECFDF5; color: #10B981; }
.agent-list .list-item .badge.suspended { background: #FEF2F2; color: #EF4444; }

.agent-list .empty {
    text-align: center;
    padding: 30px 16px;
    color: var(--gray-400);
    font-size: 0.85rem;
}
.agent-list .empty i {
    font-size: 2rem;
    display: block;
    margin-bottom: 10px;
}

.pu-list {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    padding: 4px;
}
.pu-item {
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    cursor: pointer;
    transition: var(--transition);
    font-size: 0.78rem;
    text-align: center;
}
.pu-item:hover {
    border-color: var(--primary);
    background: #EFF6FF;
}
.pu-item.selected {
    border-color: var(--primary);
    background: #EFF6FF;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
}
.pu-item .pu-name {
    font-weight: 500;
}
.pu-item .pu-code {
    font-size: 0.6rem;
    color: var(--gray-400);
}
.pu-item .pu-voters {
    font-size: 0.6rem;
    color: var(--gray-500);
}

@media (max-width: 1024px) {
    .assign-form .form-row {
        grid-template-columns: 1fr 1fr;
    }
    .agents-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .assign-form .form-row {
        grid-template-columns: 1fr;
    }
    .assign-form .form-group {
        width: 100%;
    }
    .pu-list {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 480px) {
    .pu-list {
        grid-template-columns: 1fr;
    }
    .stats-row {
        grid-template-columns: 1fr 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="assign-header">
            <div>
                <h2><i class="fas fa-user-plus"></i> Assign Agents to Polling Units</h2>
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

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-mini">
                <div class="number blue"><?php echo number_format($agent_stats['total']); ?></div>
                <div class="label">Total Agents</div>
            </div>
            <div class="stat-mini">
                <div class="number green"><?php echo number_format($agent_stats['assigned']); ?></div>
                <div class="label">Assigned</div>
            </div>
            <div class="stat-mini">
                <div class="number orange"><?php echo number_format($agent_stats['unassigned']); ?></div>
                <div class="label">Unassigned</div>
            </div>
            <div class="stat-mini">
                <div class="number blue"><?php echo number_format(count($polling_units)); ?></div>
                <div class="label">Polling Units</div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success" style="margin-bottom:16px;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" style="margin-bottom:16px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Assignment Form -->
        <div class="assign-form">
            <form method="POST" action="" id="assignForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="agent_id"><i class="fas fa-user"></i> Select Agent</label>
                        <select name="agent_id" id="agent_id" required>
                            <option value="">-- Select Agent --</option>
                            <optgroup label="Unassigned Agents">
                                <?php foreach ($unassigned_agents as $agent): ?>
                                    <option value="<?php echo $agent['id']; ?>">
                                        <?php echo htmlspecialchars($agent['full_name']); ?> (<?php echo htmlspecialchars($agent['user_code']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Assigned Agents (Reassign)">
                                <?php foreach ($assigned_agents as $agent): ?>
                                    <option value="<?php echo $agent['id']; ?>">
                                        <?php echo htmlspecialchars($agent['full_name']); ?> → <?php echo htmlspecialchars($agent['pu_name'] ?? 'N/A'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="pu_id"><i class="fas fa-flag-checkered"></i> Select Polling Unit</label>
                        <select name="pu_id" id="pu_id" required>
                            <option value="">-- Select PU --</option>
                            <?php foreach ($polling_units as $pu): ?>
                                <option value="<?php echo $pu['id']; ?>">
                                    <?php echo htmlspecialchars($pu['name']); ?> (<?php echo htmlspecialchars($pu['code']); ?>)
                                    <?php if ($pu['assigned_agents'] > 0): ?>
                                        - <?php echo $pu['assigned_agents']; ?> agent(s)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="assignment_type"><i class="fas fa-tag"></i> Assignment Type</label>
                        <select name="assignment_type" id="assignment_type">
                            <option value="data_agent">Data Agent</option>
                            <option value="party_agent">Party Agent</option>
                            <option value="volunteer">Volunteer</option>
                            <option value="observer">Observer</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn-primary" style="width:100%;">
                            <i class="fas fa-check"></i> Assign Agent
                        </button>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top:12px;">
                    <label for="notes"><i class="fas fa-sticky-note"></i> Notes (Optional)</label>
                    <textarea name="notes" id="notes" placeholder="Add any notes about this assignment..." rows="2"></textarea>
                </div>
            </form>
        </div>

        <!-- Agents Lists -->
        <div class="agents-grid">
            <!-- Unassigned Agents -->
            <div class="agent-list">
                <div class="list-header">
                    <span><i class="fas fa-user-plus"></i> Unassigned Agents</span>
                    <span class="count"><?php echo count($unassigned_agents); ?></span>
                </div>
                <div class="list-body">
                    <?php if (count($unassigned_agents) > 0): ?>
                        <?php foreach ($unassigned_agents as $agent): ?>
                            <div class="list-item" onclick="selectAgent(<?php echo $agent['id']; ?>)">
                                <div class="info">
                                    <div class="name"><?php echo htmlspecialchars($agent['full_name']); ?></div>
                                    <div class="sub">
                                        <?php echo htmlspecialchars($agent['user_code']); ?> • 
                                        <?php echo htmlspecialchars($agent['email'] ?? 'No email'); ?>
                                    </div>
                                </div>
                                <span class="badge unassigned">Unassigned</span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty">
                            <i class="fas fa-check-circle" style="color:#10B981;"></i>
                            All agents are assigned to polling units.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Assigned Agents -->
            <div class="agent-list">
                <div class="list-header">
                    <span><i class="fas fa-user-check"></i> Assigned Agents</span>
                    <span class="count"><?php echo count($assigned_agents); ?></span>
                </div>
                <div class="list-body">
                    <?php if (count($assigned_agents) > 0): ?>
                        <?php foreach ($assigned_agents as $agent): ?>
                            <div class="list-item" onclick="selectAgent(<?php echo $agent['id']; ?>)">
                                <div class="info">
                                    <div class="name"><?php echo htmlspecialchars($agent['full_name']); ?></div>
                                    <div class="sub">
                                        <?php echo htmlspecialchars($agent['user_code']); ?> → 
                                        <strong><?php echo htmlspecialchars($agent['pu_name'] ?? 'N/A'); ?></strong>
                                    </div>
                                </div>
                                <span class="badge assigned">Assigned</span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty">
                            <i class="fas fa-users" style="color:var(--gray-400);"></i>
                            No agents have been assigned yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Select agent from list
function selectAgent(agentId) {
    document.getElementById('agent_id').value = agentId;
    document.getElementById('agent_id').style.borderColor = '#3B82F6';
    document.getElementById('agent_id').style.boxShadow = '0 0 0 3px rgba(59, 130, 246, 0.1)';
    
    // Flash effect
    const select = document.getElementById('agent_id');
    select.style.transition = 'all 0.3s';
    setTimeout(() => {
        select.style.borderColor = '';
        select.style.boxShadow = '';
    }, 2000);
}

// Validate form
document.getElementById('assignForm').addEventListener('submit', function(e) {
    const agentId = document.getElementById('agent_id').value;
    const puId = document.getElementById('pu_id').value;
    
    if (!agentId || !puId) {
        e.preventDefault();
        let msg = 'Please select ';
        if (!agentId && !puId) {
            msg += 'both an agent and a polling unit.';
        } else if (!agentId) {
            msg += 'an agent.';
        } else {
            msg += 'a polling unit.';
        }
        alert(msg);
        return false;
    }
    
    // Confirm reassignment if agent is already assigned
    const agentSelect = document.getElementById('agent_id');
    const selectedOption = agentSelect.options[agentSelect.selectedIndex];
    if (selectedOption && selectedOption.text.includes('→')) {
        return confirm('This agent is already assigned to a polling unit. Do you want to reassign them?');
    }
    
    return true;
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