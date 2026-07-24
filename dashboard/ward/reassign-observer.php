<?php
// ============================================================
// WARD COORDINATOR - REASSIGN OBSERVER
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
// GET OBSERVER ID
// ============================================================
$observer_id = isset($_GET['observer_id']) ? (int)$_GET['observer_id'] : 0;

if ($observer_id <= 0) {
    header('Location: assign-observers.php');
    exit();
}

// ============================================================
// FETCH OBSERVER DETAILS
// ============================================================
$observer = null;
$current_pu = null;
$error_message = '';

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
        AND EXISTS (SELECT 1 FROM roles r WHERE r.id = u.role_id AND r.level = 'observer')
    ");
    $stmt->execute([$observer_id, $tenant_id, $ward_id]);
    $observer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$observer) {
        header('Location: assign-observers.php?error=notfound');
        exit();
    }
    
    if (empty($observer['pu_id'])) {
        header('Location: assign-observers.php?error=not_assigned');
        exit();
    }
    $current_pu = $observer['pu_id'];
    
} catch (Exception $e) {
    error_log("Error fetching observer: " . $e->getMessage());
    header('Location: assign-observers.php?error=db');
    exit();
}

// ============================================================
// FETCH POLLING UNITS
// ============================================================
$polling_units = [];
try {
    $stmt = $db->prepare("
        SELECT 
            pu.id,
            pu.name,
            pu.code,
            pu.registered_voters
        FROM polling_units pu
        WHERE pu.ward_id = ? AND pu.is_active = 1
        ORDER BY pu.name ASC
    ");
    $stmt->execute([$ward_id]);
    $polling_units = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching polling units: " . $e->getMessage());
}

// ============================================================
// HANDLE REASSIGNMENT
// ============================================================
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $observer_id = isset($_POST['observer_id']) ? (int)$_POST['observer_id'] : 0;
    $new_pu_id = isset($_POST['new_pu_id']) ? (int)$_POST['new_pu_id'] : 0;
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    
    if ($observer_id > 0 && $new_pu_id > 0) {
        try {
            $db->beginTransaction();
            
            // Update user's PU assignment
            $stmt = $db->prepare("UPDATE users SET pu_id = ? WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$new_pu_id, $observer_id, $tenant_id]);
            
            // Update existing assignment
            $stmt = $db->prepare("
                UPDATE agent_assignments 
                SET status = 'reassigned' 
                WHERE user_id = ? AND status = 'active'
            ");
            $stmt->execute([$observer_id]);
            
            // Get active election
            $election_stmt = $db->prepare("
                SELECT id FROM elections 
                WHERE tenant_id = ? AND status = 'active' 
                AND JSON_CONTAINS(wards_json, JSON_QUOTE(?))
                LIMIT 1
            ");
            $election_stmt->execute([$tenant_id, $ward_id]);
            $election = $election_stmt->fetch(PDO::FETCH_ASSOC);
            $election_id = $election ? $election['id'] : null;
            
            // Create new assignment
            $stmt = $db->prepare("
                INSERT INTO agent_assignments (
                    tenant_id, election_id, user_id, pu_id, ward_id, lga_id, state_id,
                    assignment_type, status, assigned_by, notes, assigned_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'observer', 'active', ?, ?, NOW())
            ");
            
            $stmt->execute([
                $tenant_id,
                $election_id,
                $observer_id,
                $new_pu_id,
                $ward_id,
                $lga_id,
                $state_id,
                $user_id,
                $reason
            ]);
            
            logActivity($user_id, 'observer_reassigned', "Reassigned observer ID: $observer_id to PU: $new_pu_id", 'user', $observer_id);
            
            $db->commit();
            $success_message = "Observer reassigned successfully!";
            header('Location: assign-observers.php?success=' . urlencode($success_message));
            exit();
            
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = "Error reassigning observer: " . $e->getMessage();
            error_log("Observer reassign error: " . $e->getMessage());
        }
    } else {
        $error_message = "Please select a new polling unit for this observer.";
    }
}

$page_title = 'Reassign Observer';
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

.agent-info {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px;
    margin-bottom: 20px;
}
.agent-info .info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 8px 16px;
}
.agent-info .info-grid .item {
    font-size: 0.85rem;
    padding: 4px 0;
}
.agent-info .info-grid .item .label {
    color: var(--gray-500);
    font-weight: 500;
}
.agent-info .info-grid .item .value {
    color: var(--gray-800);
}

.reassign-form {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
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
    font-family: inherit;
}

.pu-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 8px;
    max-height: 250px;
    overflow-y: auto;
    padding: 4px;
}
.pu-option {
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    cursor: pointer;
    transition: var(--transition);
    font-size: 0.82rem;
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
.pu-option .pu-name {
    font-weight: 500;
}
.pu-option .pu-code {
    font-size: 0.65rem;
    color: var(--gray-400);
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 16px;
}

.alert {
    padding: 12px 16px;
    border-radius: var(--radius);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-danger {
    background: #FEF2F2;
    border: 1px solid #FEE2E2;
    color: #991B1B;
}
.alert i {
    font-size: 1.1rem;
}

@media (max-width: 768px) {
    .agent-info .info-grid {
        grid-template-columns: 1fr;
    }
    .pu-grid {
        grid-template-columns: 1fr;
    }
    .form-actions {
        flex-direction: column;
    }
    .form-actions button,
    .form-actions a {
        width: 100%;
        text-align: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="reassign-header">
            <div>
                <h2><i class="fas fa-exchange-alt"></i> Reassign Party Agent</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward
                </p>
            </div>
            <div>
                <a href="assign-party-agents.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($agent): ?>
            <!-- Agent Information -->
            <div class="agent-info">
                <h3 style="margin:0 0 12px;font-size:0.95rem;">
                    <i class="fas fa-user"></i> Agent Information
                </h3>
                <div class="info-grid">
                    <div class="item">
                        <span class="label">Name</span><br>
                        <span class="value"><?php echo htmlspecialchars($agent['full_name']); ?></span>
                    </div>
                    <div class="item">
                        <span class="label">Code</span><br>
                        <span class="value"><?php echo htmlspecialchars($agent['user_code']); ?></span>
                    </div>
                    <div class="item">
                        <span class="label">Current PU</span><br>
                        <span class="value">
                            <strong><?php echo htmlspecialchars($agent['current_pu_name']); ?></strong>
                            (<?php echo htmlspecialchars($agent['current_pu_code']); ?>)
                        </span>
                    </div>
                    <div class="item">
                        <span class="label">Status</span><br>
                        <span class="value"><?php echo ucfirst($agent['status']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Reassign Form -->
            <div class="reassign-form">
                <form method="POST" action="" id="reassignForm">
                    <input type="hidden" name="agent_id" value="<?php echo $agent['id']; ?>">
                    <input type="hidden" name="new_pu_id" id="selected_pu_id" value="">
                    
                    <div class="form-group">
                        <label>Select New Polling Unit <span class="required">*</span></label>
                        <div class="pu-grid">
                            <?php foreach ($polling_units as $pu): 
                                $is_current = ($pu['id'] == $current_pu);
                            ?>
                                <div class="pu-option <?php echo $is_current ? 'selected' : ''; ?>" 
                                     data-pu-id="<?php echo $pu['id']; ?>"
                                     onclick="selectPU(<?php echo $pu['id']; ?>)">
                                    <div class="pu-name"><?php echo htmlspecialchars($pu['name']); ?></div>
                                    <div class="pu-code"><?php echo htmlspecialchars($pu['code']); ?></div>
                                    <?php if ($is_current): ?>
                                        <div style="font-size:0.6rem;color:#10B981;font-weight:600;">Current</div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Reason for Reassignment</label>
                        <textarea name="reason" placeholder="Provide a reason for reassigning this party agent..." rows="3"></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary" id="submitBtn" disabled>
                            <i class="fas fa-exchange-alt"></i> Reassign
                        </button>
                        <a href="assign-party-agents.php" class="btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>

        <?php else: ?>
            <div style="text-align:center;padding:60px 20px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);">
                <i class="fas fa-user" style="font-size:4rem;color:var(--gray-300);"></i>
                <h4 style="margin:16px 0 8px;">Agent Not Found</h4>
                <p style="color:var(--gray-500);">The party agent you're trying to reassign does not exist.</p>
                <a href="assign-party-agents.php" class="btn-primary-sm" style="display:inline-block;margin-top:12px;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
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

document.getElementById('reassignForm').addEventListener('submit', function(e) {
    const puId = document.getElementById('selected_pu_id').value;
    
    if (!puId) {
        e.preventDefault();
        alert('Please select a new polling unit for this party agent.');
        return false;
    }
    
    const currentPuId = <?php echo $current_pu ?? 0; ?>;
    if (puId == currentPuId) {
        e.preventDefault();
        alert('You must select a different polling unit than the current one.');
        return false;
    }
    
    return confirm('Are you sure you want to reassign this party agent?');
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