<?php
// ============================================================
// WARD COORDINATOR - ASSIGN OBSERVERS
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
// FETCH UNASSIGNED OBSERVERS
// ============================================================
$unassigned_observers = [];
$assigned_observers = [];
$polling_units = [];

try {
    // Get unassigned observers
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
        AND r.level = 'observer'
        AND (u.pu_id IS NULL OR u.pu_id = 0)
        AND u.status = 'active'
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $unassigned_observers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get assigned observers
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
        AND r.level = 'observer'
        AND u.pu_id IS NOT NULL
        AND u.pu_id > 0
        AND u.status = 'active'
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $assigned_observers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get polling units
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
    error_log("Error fetching data: " . $e->getMessage());
}

// ============================================================
// HANDLE ASSIGNMENT
// ============================================================
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $observer_id = isset($_POST['observer_id']) ? (int)$_POST['observer_id'] : 0;
    $pu_id = isset($_POST['pu_id']) ? (int)$_POST['pu_id'] : 0;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    if ($observer_id > 0 && $pu_id > 0) {
        try {
            $db->beginTransaction();
            
            // Update user's PU assignment
            $stmt = $db->prepare("UPDATE users SET pu_id = ? WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$pu_id, $observer_id, $tenant_id]);
            
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
            
            // Create assignment record
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
                $pu_id,
                $ward_id,
                $lga_id,
                $state_id,
                $user_id,
                $notes
            ]);
            
            logActivity($user_id, 'observer_assigned', "Assigned observer ID: $observer_id to PU: $pu_id", 'user', $observer_id);
            
            $db->commit();
            $success_message = "Observer assigned successfully.";
            
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = "Error assigning observer: " . $e->getMessage();
            error_log("Observer assignment error: " . $e->getMessage());
        }
    } else {
        $error_message = "Please select both an observer and a polling unit.";
    }
}

$page_title = 'Assign Observers';
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

.observers-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.observer-list {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
}
.observer-list .list-header {
    background: var(--gray-50);
    padding: 10px 16px;
    font-weight: 600;
    font-size: 0.8rem;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.observer-list .list-body {
    max-height: 300px;
    overflow-y: auto;
}
.observer-list .list-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 16px;
    border-bottom: 1px solid var(--gray-100);
    font-size: 0.82rem;
    cursor: pointer;
    transition: var(--transition);
}
.observer-list .list-item:hover {
    background: var(--gray-50);
}
.observer-list .list-item .info {
    flex: 1;
}
.observer-list .list-item .info .name {
    font-weight: 500;
}
.observer-list .list-item .info .sub {
    font-size: 0.7rem;
    color: var(--gray-500);
}
.observer-list .list-item .badge {
    font-size: 0.6rem;
    padding: 2px 10px;
    border-radius: 20px;
    font-weight: 500;
}
.observer-list .list-item .badge.unassigned { background: #FEF3C7; color: #F59E0B; }
.observer-list .list-item .badge.assigned { background: #ECFDF5; color: #10B981; }

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
    .observers-grid {
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
                <h2><i class="fas fa-eye"></i> Assign Observers</h2>
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
                        <label for="observer_id"><i class="fas fa-user"></i> Select Observer</label>
                        <select name="observer_id" id="observer_id" required>
                            <option value="">-- Select Observer --</option>
                            <optgroup label="Unassigned Observers">
                                <?php foreach ($unassigned_observers as $observer): ?>
                                    <option value="<?php echo $observer['id']; ?>">
                                        <?php echo htmlspecialchars($observer['full_name']); ?> (<?php echo htmlspecialchars($observer['user_code']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Assigned Observers (Reassign)">
                                <?php foreach ($assigned_observers as $observer): ?>
                                    <option value="<?php echo $observer['id']; ?>">
                                        <?php echo htmlspecialchars($observer['full_name']); ?> → <?php echo htmlspecialchars($observer['pu_name'] ?? 'N/A'); ?>
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
                            <i class="fas fa-check"></i> Assign Observer
                        </button>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top:12px;">
                    <label for="notes"><i class="fas fa-sticky-note"></i> Notes (Optional)</label>
                    <textarea name="notes" id="notes" placeholder="Add any notes about this assignment..." rows="2"></textarea>
                </div>
            </form>
        </div>

        <!-- Observers Lists -->
        <div class="observers-grid">
            <div class="observer-list">
                <div class="list-header">
                    <span><i class="fas fa-user-plus"></i> Unassigned Observers</span>
                    <span class="count"><?php echo count($unassigned_observers); ?></span>
                </div>
                <div class="list-body">
                    <?php if (count($unassigned_observers) > 0): ?>
                        <?php foreach ($unassigned_observers as $observer): ?>
                            <div class="list-item" onclick="selectObserver(<?php echo $observer['id']; ?>)">
                                <div class="info">
                                    <div class="name"><?php echo htmlspecialchars($observer['full_name']); ?></div>
                                    <div class="sub"><?php echo htmlspecialchars($observer['user_code']); ?></div>
                                </div>
                                <span class="badge unassigned">Unassigned</span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align:center;padding:20px;color:var(--gray-400);">
                            All observers are assigned.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="observer-list">
                <div class="list-header">
                    <span><i class="fas fa-user-check"></i> Assigned Observers</span>
                    <span class="count"><?php echo count($assigned_observers); ?></span>
                </div>
                <div class="list-body">
                    <?php if (count($assigned_observers) > 0): ?>
                        <?php foreach ($assigned_observers as $observer): ?>
                            <div class="list-item" onclick="selectObserver(<?php echo $observer['id']; ?>)">
                                <div class="info">
                                    <div class="name"><?php echo htmlspecialchars($observer['full_name']); ?></div>
                                    <div class="sub"><?php echo htmlspecialchars($observer['pu_name'] ?? 'N/A'); ?></div>
                                </div>
                                <span class="badge assigned">Assigned</span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align:center;padding:20px;color:var(--gray-400);">
                            No observers assigned yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
function selectObserver(observerId) {
    document.getElementById('observer_id').value = observerId;
    document.getElementById('observer_id').style.borderColor = '#3B82F6';
    document.getElementById('observer_id').style.boxShadow = '0 0 0 3px rgba(59, 130, 246, 0.1)';
    setTimeout(() => {
        document.getElementById('observer_id').style.borderColor = '';
        document.getElementById('observer_id').style.boxShadow = '';
    }, 2000);
}

document.getElementById('assignForm').addEventListener('submit', function(e) {
    const observerId = document.getElementById('observer_id').value;
    const puId = document.getElementById('pu_id').value;
    
    if (!observerId || !puId) {
        e.preventDefault();
        alert('Please select both an observer and a polling unit.');
        return false;
    }
    
    const observerSelect = document.getElementById('observer_id');
    const selectedOption = observerSelect.options[observerSelect.selectedIndex];
    if (selectedOption && selectedOption.text.includes('→')) {
        return confirm('This observer is already assigned. Do you want to reassign them?');
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
</html>assign-volunteers.php
