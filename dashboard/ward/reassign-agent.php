<?php
// ============================================================
// WARD COORDINATOR - REASSIGN AGENT
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
// GET AGENT ID FROM URL
// ============================================================
$agent_id = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : 0;
$agent = null;
$current_pu = null;

if ($agent_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT 
                u.id,
                u.full_name,
                u.user_code,
                u.email,
                u.phone,
                u.status,
                u.pu_id,
                pu.name as current_pu_name,
                pu.code as current_pu_code
            FROM users u
            LEFT JOIN polling_units pu ON u.pu_id = pu.id
            WHERE u.id = ? AND u.tenant_id = ? AND u.ward_id = ?
            AND u.deleted_at IS NULL
            AND EXISTS (SELECT 1 FROM roles r WHERE r.id = u.role_id AND r.level = 'pu_agent')
        ");
        $stmt->execute([$agent_id, $tenant_id, $ward_id]);
        $agent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($agent && !empty($agent['pu_id'])) {
            $current_pu = $agent['pu_id'];
        }
    } catch (Exception $e) {
        error_log("Error fetching agent: " . $e->getMessage());
    }
}

// ============================================================
// HANDLE REASSIGNMENT
// ============================================================
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $agent_id = isset($_POST['agent_id']) ? (int)$_POST['agent_id'] : 0;
    $new_pu_id = isset($_POST['new_pu_id']) ? (int)$_POST['new_pu_id'] : 0;
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    
    if ($agent_id > 0 && $new_pu_id > 0) {
        try {
            // Start transaction
            $db->beginTransaction();
            
            // Update user's PU assignment
            $stmt = $db->prepare("
                UPDATE users 
                SET pu_id = ?, updated_at = NOW() 
                WHERE id = ? AND tenant_id = ? AND ward_id = ?
            ");
            $stmt->execute([$new_pu_id, $agent_id, $tenant_id, $ward_id]);
            
            // Update agent assignment
            $stmt = $db->prepare("
                UPDATE agent_assignments 
                SET pu_id = ?, status = 'reassigned', updated_at = NOW() 
                WHERE user_id = ? AND status = 'active'
            ");
            $stmt->execute([$new_pu_id, $agent_id]);
            
            // Create new assignment record
            $stmt = $db->prepare("
                INSERT INTO agent_assignments (
                    tenant_id, election_id, user_id, pu_id, ward_id, lga_id, state_id,
                    assignment_type, status, assigned_by, notes, assigned_at
                ) SELECT 
                    ?, election_id, user_id, ?, ward_id, lga_id, state_id,
                    assignment_type, 'active', ?, ?, NOW()
                FROM agent_assignments 
                WHERE user_id = ? AND status = 'reassigned'
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([$tenant_id, $new_pu_id, $user_id, $reason, $agent_id]);
            
            // Log activity
            logActivity($user_id, 'agent_reassigned', "Reassigned agent ID: $agent_id to PU: $new_pu_id", 'user', $agent_id);
            
            $db->commit();
            $success_message = "Agent reassigned successfully.";
            
            // Refresh agent data
            $stmt = $db->prepare("
                SELECT 
                    u.id,
                    u.full_name,
                    u.user_code,
                    u.email,
                    u.phone,
                    u.status,
                    u.pu_id,
                    pu.name as current_pu_name,
                    pu.code as current_pu_code
                FROM users u
                LEFT JOIN polling_units pu ON u.pu_id = pu.id
                WHERE u.id = ? AND u.tenant_id = ? AND u.ward_id = ?
            ");
            $stmt->execute([$agent_id, $tenant_id, $ward_id]);
            $agent = $stmt->fetch(PDO::FETCH_ASSOC);
            $current_pu = $agent['pu_id'] ?? null;
            
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = "Error reassigning agent: " . $e->getMessage();
            error_log("Agent reassignment error: " . $e->getMessage());
        }
    } else {
        $error_message = "Please select a new polling unit for this agent.";
    }
}

// ============================================================
// FETCH POLLING UNITS (excluding current PU)
// ============================================================
$polling_units = [];
try {
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
    error_log("Error fetching polling units: " . $e->getMessage());
}

$page_title = 'Reassign Agent';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.reassign-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.reassign-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.reassign-header h2 i {
    color: var(--primary);
}

.agent-info-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
    margin-bottom: 20px;
}
.agent-info-card .agent-details {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 12px 24px;
    align-items: center;
}
.agent-info-card .agent-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: var(--gray-200);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gray-600);
}
.agent-info-card .agent-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}
.agent-info-card .agent-meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 4px 16px;
}
.agent-info-card .agent-meta .item {
    font-size: 0.82rem;
}
.agent-info-card .agent-meta .item strong {
    color: var(--gray-600);
    font-weight: 500;
}
.agent-info-card .agent-meta .item .current-pu {
    font-weight: 600;
    color: var(--primary);
}

.reassign-form {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px;
}
.reassign-form .form-group {
    margin-bottom: 16px;
}
.reassign-form .form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 4px;
}
.reassign-form .form-group select,
.reassign-form .form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    background: white;
}
.reassign-form .form-group textarea {
    resize: vertical;
    min-height: 80px;
}
.reassign-form .form-group .helper {
    font-size: 0.7rem;
    color: var(--gray-400);
    margin-top: 4px;
}
.reassign-form .form-actions {
    display: flex;
    gap: 12px;
    margin-top: 16px;
}

.pu-list-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 8px;
    max-height: 300px;
    overflow-y: auto;
    padding: 4px;
}
.pu-option {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 14px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    cursor: pointer;
    transition: var(--transition);
}
.pu-option:hover {
    border-color: var(--primary);
    background: #EFF6FF;
}
.pu-option.selected {
    border-color: var(--primary);
    background: #EFF6FF;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
}
.pu-option .pu-info .name {
    font-weight: 500;
    font-size: 0.82rem;
}
.pu-option .pu-info .code {
    font-size: 0.65rem;
    color: var(--gray-400);
}
.pu-option .pu-meta {
    text-align: right;
    font-size: 0.65rem;
}
.pu-option .pu-meta .voters {
    color: var(--gray-500);
}
.pu-option .pu-meta .agents {
    color: var(--gray-400);
}

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
.alert-warning {
    background: #FFFBEB;
    border: 1px solid #FEF3C7;
    color: #92400E;
}
.alert i {
    font-size: 1.1rem;
}

@media (max-width: 768px) {
    .agent-info-card .agent-details {
        grid-template-columns: 1fr;
        text-align: center;
    }
    .agent-info-card .agent-avatar {
        margin: 0 auto;
    }
    .agent-info-card .agent-meta {
        grid-template-columns: 1fr;
    }
    .pu-list-grid {
        grid-template-columns: 1fr;
    }
    .reassign-form .form-actions {
        flex-direction: column;
    }
    .reassign-form .form-actions button {
        width: 100%;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="reassign-header">
            <div>
                <h2><i class="fas fa-exchange-alt"></i> Reassign Agent</h2>
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

        <?php if ($agent): ?>
            <!-- Success/Error Messages -->
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

            <!-- Agent Info -->
            <div class="agent-info-card">
                <div class="agent-details">
                    <div class="agent-avatar">
                        <?php echo strtoupper(substr($agent['full_name'] ?? 'U', 0, 2)); ?>
                    </div>
                    <div>
                        <h3 style="margin:0 0 4px;"><?php echo htmlspecialchars($agent['full_name']); ?></h3>
                        <div class="agent-meta">
                            <div class="item"><strong>Code:</strong> <?php echo htmlspecialchars($agent['user_code'] ?? 'N/A'); ?></div>
                            <div class="item"><strong>Email:</strong> <?php echo htmlspecialchars($agent['email'] ?? 'N/A'); ?></div>
                            <div class="item"><strong>Phone:</strong> <?php echo htmlspecialchars($agent['phone'] ?? 'N/A'); ?></div>
                            <div class="item"><strong>Status:</strong> <span class="status-badge <?php echo $agent['status'] ?? 'pending'; ?>"><?php echo ucfirst($agent['status'] ?? 'Pending'); ?></span></div>
                            <?php if (!empty($agent['current_pu_name'])): ?>
                                <div class="item">
                                    <strong>Current PU:</strong> 
                                    <span class="current-pu"><?php echo htmlspecialchars($agent['current_pu_name']); ?></span>
                                    <span style="color:var(--gray-400);font-size:0.7rem;">(<?php echo htmlspecialchars($agent['current_pu_code'] ?? ''); ?>)</span>
                                </div>
                            <?php else: ?>
                                <div class="item">
                                    <strong>Current PU:</strong> 
                                    <span style="color:var(--gray-400);">Not Assigned</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($agent['current_pu_name'])): ?>
                <!-- Reassignment Form -->
                <div class="reassign-form">
                    <form method="POST" action="" id="reassignForm">
                        <input type="hidden" name="agent_id" value="<?php echo $agent['id']; ?>">
                        
                        <div class="form-group">
                            <label><i class="fas fa-flag-checkered"></i> Select New Polling Unit</label>
                            <div class="pu-list-grid">
                                <?php foreach ($polling_units as $pu):
                                    $is_current = ($pu['id'] == $current_pu);
                                ?>
                                    <div class="pu-option <?php echo $is_current ? 'selected' : ''; ?>" 
                                         data-pu-id="<?php echo $pu['id']; ?>"
                                         onclick="selectPU(<?php echo $pu['id']; ?>)">
                                        <div class="pu-info">
                                            <div class="name"><?php echo htmlspecialchars($pu['name']); ?></div>
                                            <div class="code"><?php echo htmlspecialchars($pu['code']); ?></div>
                                        </div>
                                        <div class="pu-meta">
                                            <div class="voters"><?php echo number_format($pu['registered_voters']); ?> voters</div>
                                            <div class="agents"><?php echo $pu['assigned_agents']; ?> agents</div>
                                            <?php if ($is_current): ?>
                                                <div style="color:#10B981;font-size:0.6rem;font-weight:600;">Current</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="helper">Click on a polling unit to select it for reassignment</div>
                        </div>
                        
                        <input type="hidden" name="new_pu_id" id="selected_pu_id" value="<?php echo $current_pu; ?>">
                        
                        <div class="form-group">
                            <label for="reason"><i class="fas fa-sticky-note"></i> Reason for Reassignment</label>
                            <textarea name="reason" id="reason" placeholder="Provide a reason for reassigning this agent..." rows="3"></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-primary" id="submitBtn">
                                <i class="fas fa-exchange-alt"></i> Confirm Reassignment
                            </button>
                            <a href="manage-pu-agents.php" class="btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle"></i> 
                    This agent is not currently assigned to any polling unit. 
                    <a href="assign-agents.php?agent_id=<?php echo $agent['id']; ?>" style="font-weight:600;">Assign them now →</a>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div style="text-align:center;padding:60px 20px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);">
                <i class="fas fa-user-tie" style="font-size:4rem;color:var(--gray-300);"></i>
                <h4 style="margin:16px 0 8px;">Agent Not Found</h4>
                <p style="color:var(--gray-500);">The agent you're looking for does not exist or is not in your ward.</p>
                <a href="manage-pu-agents.php" class="btn-primary-sm" style="display:inline-block;margin-top:12px;">
                    <i class="fas fa-arrow-left"></i> Back to Agents
                </a>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
// Select PU
function selectPU(puId) {
    // Update hidden input
    document.getElementById('selected_pu_id').value = puId;
    
    // Update UI
    document.querySelectorAll('.pu-option').forEach(function(el) {
        el.classList.remove('selected');
        if (el.dataset.puId == puId) {
            el.classList.add('selected');
        }
    });
    
    // Enable submit button
    document.getElementById('submitBtn').disabled = false;
}

// Validate form
document.getElementById('reassignForm').addEventListener('submit', function(e) {
    const puId = document.getElementById('selected_pu_id').value;
    
    if (!puId || puId == '0') {
        e.preventDefault();
        alert('Please select a new polling unit for this agent.');
        return false;
    }
    
    const currentPuId = <?php echo $current_pu ?? 0; ?>;
    if (puId == currentPuId) {
        e.preventDefault();
        alert('You must select a different polling unit than the current one.');
        return false;
    }
    
    return confirm('Are you sure you want to reassign this agent to the selected polling unit?');
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