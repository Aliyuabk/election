<?php
// ============================================================
// WARD COORDINATOR - ASSIGN PARTY AGENTS (COMPLETE UPDATE)
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Start session
SessionManager::start();

// Check if user is logged in
if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

// Only Ward coordinator can access
$user_role_level = SessionManager::get('role_level');
if ($user_role_level !== 'ward') {
    header('Location: ../client-admin/');
    exit();
}

// Get user data from session
$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$ward_id = SessionManager::get('ward_id');
$lga_id = SessionManager::get('lga_id');
$state_id = SessionManager::get('state_id');

// Get database connection
$db = getDB();

// ============================================================
// FIX: Ensure ward_id is properly set
// ============================================================
if (empty($ward_id)) {
    try {
        $stmt = $db->prepare("SELECT ward_id, lga_id, state_id FROM users WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$user_id, $tenant_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && !empty($user['ward_id'])) {
            $ward_id = $user['ward_id'];
            $lga_id = $user['lga_id'] ?? $lga_id;
            $state_id = $user['state_id'] ?? $state_id;
            
            SessionManager::set('ward_id', $ward_id);
            SessionManager::set('lga_id', $lga_id);
            SessionManager::set('state_id', $state_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching ward_id: " . $e->getMessage());
    }
}

// ============================================================
// FETCH WARD NAME
// ============================================================
$ward_name = 'Unknown Ward';
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
// FUNCTION: Ensure active election exists
// ============================================================
function ensureActiveElection($db, $tenant_id, $ward_id, $user_id) {
    try {
        $stmt = $db->prepare("
            SELECT id FROM elections 
            WHERE tenant_id = ? AND status = 'active' 
            AND JSON_CONTAINS(wards_json, JSON_QUOTE(?))
            LIMIT 1
        ");
        $stmt->execute([$tenant_id, $ward_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result['id'];
        }
        
        $db->beginTransaction();
        
        $stmt = $db->prepare("
            INSERT INTO elections (
                tenant_id, name, type, cycle, election_date, 
                status, wards_json, created_by, created_at, updated_at
            ) VALUES (?, 'Default Active Election', 'governorship', '2031', 
                DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'active', JSON_ARRAY(?), ?, NOW(), NOW()
            )
        ");
        $stmt->execute([$tenant_id, $ward_id, $user_id]);
        $election_id = $db->lastInsertId();
        
        $db->commit();
        return $election_id;
        
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Error ensuring active election: " . $e->getMessage());
        throw $e;
    }
}

// ============================================================
// FETCH UNASSIGNED PARTY AGENTS
// ============================================================
$unassigned_agents = [];
$assigned_agents = [];
$polling_units = [];

try {
    // Get unassigned party agents (role_id = 10)
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.user_code,
            u.full_name,
            u.email,
            u.phone,
            u.status,
            u.created_at,
            u.photograph_url
        FROM users u
        WHERE u.tenant_id = ? 
        AND u.ward_id = ?
        AND u.deleted_at IS NULL
        AND u.role_id = 10
        AND (u.pu_id IS NULL OR u.pu_id = 0)
        AND u.status = 'active'
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $unassigned_agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get assigned party agents
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.user_code,
            u.full_name,
            u.email,
            u.phone,
            u.status,
            u.pu_id,
            u.photograph_url,
            pu.name as pu_name,
            pu.code as pu_code
        FROM users u
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        WHERE u.tenant_id = ? 
        AND u.ward_id = ?
        AND u.deleted_at IS NULL
        AND u.role_id = 10
        AND u.pu_id IS NOT NULL
        AND u.pu_id > 0
        AND u.status = 'active'
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $assigned_agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get polling units
    $stmt = $db->prepare("
        SELECT 
            pu.id,
            pu.name,
            pu.code,
            pu.registered_voters,
            pu.is_active,
            pu.ward_id,
            (SELECT COUNT(*) FROM users u 
             WHERE u.pu_id = pu.id AND u.role_id = 10 AND u.status = 'active' AND u.deleted_at IS NULL) as assigned_count
        FROM polling_units pu
        WHERE pu.ward_id = ? AND pu.is_active = 1
        ORDER BY pu.name ASC
    ");
    $stmt->execute([$ward_id]);
    $polling_units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching data: " . $e->getMessage());
}

// ============================================================
// HANDLE ASSIGNMENT
// ============================================================
$success_message = '';
$error_message = '';
$show_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_party_agent') {
    $agent_id = isset($_POST['agent_id']) ? (int)$_POST['agent_id'] : 0;
    $pu_id = isset($_POST['pu_id']) ? (int)$_POST['pu_id'] : 0;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $party = isset($_POST['party']) ? trim($_POST['party']) : '';
    
    // CSRF Protection
    $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    $session_token = SessionManager::get('csrf_token');
    
    if (empty($csrf_token) || $csrf_token !== $session_token) {
        $error_message = 'Security validation failed. Please try again.';
    } elseif ($agent_id <= 0 || $pu_id <= 0) {
        $error_message = 'Please select both an agent and a polling unit.';
    } else {
        try {
            $db->beginTransaction();
            
            // Verify agent exists
            $stmt = $db->prepare("
                SELECT u.id, u.full_name, u.pu_id, u.status, u.role_id
                FROM users u
                WHERE u.id = ? AND u.tenant_id = ? AND u.ward_id = ? AND u.deleted_at IS NULL
            ");
            $stmt->execute([$agent_id, $tenant_id, $ward_id]);
            $agent = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$agent) {
                throw new Exception('Agent not found or does not belong to your ward.');
            }
            
            if ($agent['role_id'] != 10) {
                throw new Exception('Selected user is not a party agent.');
            }
            
            if ($agent['status'] !== 'active') {
                throw new Exception('Agent is not active.');
            }
            
            if (!empty($agent['pu_id']) && $agent['pu_id'] > 0) {
                if (!isset($_POST['confirm_reassign']) || $_POST['confirm_reassign'] !== '1') {
                    throw new Exception('reassign_required');
                }
            }
            
            // Verify polling unit
            $stmt = $db->prepare("
                SELECT id, name, ward_id, is_active 
                FROM polling_units 
                WHERE id = ? AND is_active = 1
            ");
            $stmt->execute([$pu_id]);
            $pu = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$pu) {
                throw new Exception('Polling unit not found or inactive.');
            }
            
            if ($pu['ward_id'] != $ward_id) {
                throw new Exception("Polling unit belongs to ward {$pu['ward_id']}, but you are assigned to ward $ward_id.");
            }
            
            $election_id = ensureActiveElection($db, $tenant_id, $ward_id, $user_id);
            
            // Update user's PU assignment
            $stmt = $db->prepare("UPDATE users SET pu_id = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$pu_id, $agent_id, $tenant_id]);
            
            // Mark old assignments
            $stmt = $db->prepare("
                UPDATE agent_assignments 
                SET status = 'reassigned' 
                WHERE user_id = ? AND status = 'active'
            ");
            $stmt->execute([$agent_id]);
            
            // Create assignment
            $stmt = $db->prepare("
                INSERT INTO agent_assignments (
                    tenant_id, election_id, user_id, pu_id, ward_id, lga_id, state_id,
                    assignment_type, status, assigned_by, notes, assigned_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'party_agent', 'active', ?, ?, NOW())
            ");
            
            $stmt->execute([
                $tenant_id,
                $election_id,
                $agent_id,
                $pu_id,
                $ward_id,
                $lga_id,
                $state_id,
                $user_id,
                $notes
            ]);
            
            logActivity($user_id, 'party_agent_assigned', "Assigned party agent: {$agent['full_name']} (ID: $agent_id) to PU: {$pu['name']} (ID: $pu_id)", 'user', $agent_id);
            
            $db->commit();
            $success_message = "Party agent assigned successfully to {$pu['name']}!";
            $show_success = true;
            
        } catch (Exception $e) {
            $db->rollBack();
            
            if ($e->getMessage() === 'reassign_required') {
                $error_message = 'reassign_required';
                $reassign_agent_id = $agent_id;
                $reassign_pu_id = $pu_id;
            } else {
                $error_message = "Error: " . $e->getMessage();
                error_log("Party agent assignment error: " . $e->getMessage());
            }
        }
    }
}

// Generate CSRF token
$csrf_token = bin2hex(random_bytes(32));
SessionManager::set('csrf_token', $csrf_token);

$page_title = 'Assign Party Agents';
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

.assign-form {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px;
    margin-bottom: 20px;
}
.assign-form .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
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
    max-height: 80px;
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
.agent-list .list-body {
    max-height: 300px;
    overflow-y: auto;
}
.agent-list .list-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 16px;
    border-bottom: 1px solid var(--gray-100);
    font-size: 0.82rem;
    cursor: pointer;
    transition: var(--transition);
}
.agent-list .list-item:hover {
    background: var(--gray-50);
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
.agent-list .list-item .badge.unassigned { background: #FEF3C7; color: #F59E0B; }
.agent-list .list-item .badge.assigned { background: #ECFDF5; color: #10B981; }

.alert {
    padding: 12px 16px;
    border-radius: var(--radius);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-success {
    background: #ECFDF5;
    border: 1px solid #D1FAE5;
    color: #065F46;
}
.alert-danger {
    background: #FEF2F2;
    border: 1px solid #FEE2E2;
    color: #991B1B;
}
.alert i {
    font-size: 1.1rem;
}

@media (max-width: 1024px) {
    .assign-form .form-row {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 768px) {
    .assign-form .form-row {
        grid-template-columns: 1fr;
    }
    .agents-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="assign-header">
            <div>
                <h2><i class="fas fa-flag"></i> Assign Party Agents</h2>
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

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Assignment Form -->
        <div class="assign-form">
            <form method="POST" action="" id="assignForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="agent_id"><i class="fas fa-user"></i> Select Party Agent</label>
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
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn-primary" style="width:100%;">
                            <i class="fas fa-check"></i> Assign Party Agent
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
            <div class="agent-list">
                <div class="list-header">
                    <span><i class="fas fa-user-plus"></i> Unassigned Party Agents</span>
                    <span class="count"><?php echo count($unassigned_agents); ?></span>
                </div>
                <div class="list-body">
                    <?php if (count($unassigned_agents) > 0): ?>
                        <?php foreach ($unassigned_agents as $agent): ?>
                            <div class="list-item" onclick="selectAgent(<?php echo $agent['id']; ?>)">
                                <div class="info">
                                    <div class="name"><?php echo htmlspecialchars($agent['full_name']); ?></div>
                                    <div class="sub"><?php echo htmlspecialchars($agent['user_code']); ?></div>
                                </div>
                                <span class="badge unassigned">Unassigned</span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align:center;padding:20px;color:var(--gray-400);">
                            All party agents are assigned.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="agent-list">
                <div class="list-header">
                    <span><i class="fas fa-user-check"></i> Assigned Party Agents</span>
                    <span class="count"><?php echo count($assigned_agents); ?></span>
                </div>
                <div class="list-body">
                    <?php if (count($assigned_agents) > 0): ?>
                        <?php foreach ($assigned_agents as $agent): ?>
                            <div class="list-item" onclick="selectAgent(<?php echo $agent['id']; ?>)">
                                <div class="info">
                                    <div class="name"><?php echo htmlspecialchars($agent['full_name']); ?></div>
                                    <div class="sub"><?php echo htmlspecialchars($agent['pu_name'] ?? 'N/A'); ?></div>
                                </div>
                                <span class="badge assigned">Assigned</span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align:center;padding:20px;color:var(--gray-400);">
                            No party agents assigned yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
function selectAgent(agentId) {
    document.getElementById('agent_id').value = agentId;
    document.getElementById('agent_id').style.borderColor = '#3B82F6';
    document.getElementById('agent_id').style.boxShadow = '0 0 0 3px rgba(59, 130, 246, 0.1)';
    setTimeout(() => {
        document.getElementById('agent_id').style.borderColor = '';
        document.getElementById('agent_id').style.boxShadow = '';
    }, 2000);
}

document.getElementById('assignForm').addEventListener('submit', function(e) {
    const agentId = document.getElementById('agent_id').value;
    const puId = document.getElementById('pu_id').value;
    
    if (!agentId || !puId) {
        e.preventDefault();
        alert('Please select both an agent and a polling unit.');
        return false;
    }
    
    const agentSelect = document.getElementById('agent_id');
    const selectedOption = agentSelect.options[agentSelect.selectedIndex];
    if (selectedOption && selectedOption.text.includes('→')) {
        return confirm('This agent is already assigned. Do you want to reassign them?');
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