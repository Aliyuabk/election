<?php
// ============================================================
// WARD COORDINATOR - REMOVE OBSERVER
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
// GET OBSERVER ID
// ============================================================
$observer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($observer_id <= 0) {
    header('Location: assign-observers.php');
    exit();
}

// ============================================================
// FETCH OBSERVER DETAILS
// ============================================================
$observer = null;
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
            pu.name as pu_name,
            pu.code as pu_code
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
    
} catch (Exception $e) {
    error_log("Error fetching observer: " . $e->getMessage());
    header('Location: assign-observers.php?error=db');
    exit();
}

// ============================================================
// HANDLE REMOVAL
// ============================================================
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    
    if ($action === 'remove') {
        if (empty($reason)) {
            $error_message = "Please provide a reason for removal.";
        } else {
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("
                    UPDATE users 
                    SET pu_id = NULL, updated_at = NOW() 
                    WHERE id = ? AND tenant_id = ? AND ward_id = ?
                ");
                $stmt->execute([$observer_id, $tenant_id, $ward_id]);
                
                $stmt = $db->prepare("
                    UPDATE agent_assignments 
                    SET status = 'completed', completed_at = NOW() 
                    WHERE user_id = ? AND status = 'active'
                ");
                $stmt->execute([$observer_id]);
                
                logActivity($user_id, 'observer_removed', "Removed observer ID: $observer_id from PU - Reason: $reason", 'user', $observer_id);
                
                $db->commit();
                $success_message = "Observer assignment removed successfully!";
                header('Location: assign-observers.php?success=' . urlencode($success_message));
                exit();
                
            } catch (Exception $e) {
                $db->rollBack();
                $error_message = "Error removing observer: " . $e->getMessage();
                error_log("Observer removal error: " . $e->getMessage());
            }
        }
    }
}

$page_title = 'Remove Observer';
include '../includes/base.php';
include '../includes/sidebar.php';
?>


<style>
.remove-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.remove-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.remove-header h2 i {
    color: #EF4444;
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

.confirm-box {
    background: #FEF2F2;
    border: 1px solid #FEE2E2;
    border-radius: var(--radius);
    padding: 20px;
    margin-bottom: 20px;
    text-align: center;
}
.confirm-box .icon {
    font-size: 3rem;
    color: #EF4444;
    margin-bottom: 12px;
}
.confirm-box h3 {
    color: #991B1B;
    margin: 0 0 8px;
}
.confirm-box p {
    color: #7F1D1D;
    font-size: 0.95rem;
    margin: 0;
}

.remove-form {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
}
.remove-form .form-group {
    margin-bottom: 16px;
}
.remove-form .form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 4px;
}
.remove-form .form-group label .required {
    color: #EF4444;
}
.remove-form .form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    resize: vertical;
    min-height: 80px;
    font-family: inherit;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 16px;
}
.form-actions .btn-danger {
    background: #EF4444;
    color: white;
    border-color: #EF4444;
}
.form-actions .btn-danger:hover {
    background: #DC2626;
    border-color: #DC2626;
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
        <div class="remove-header">
            <div>
                <h2><i class="fas fa-user-minus"></i> Remove Party Agent</h2>
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
                            <strong><?php echo htmlspecialchars($agent['pu_name']); ?></strong>
                            (<?php echo htmlspecialchars($agent['pu_code']); ?>)
                        </span>
                    </div>
                    <div class="item">
                        <span class="label">Status</span><br>
                        <span class="value"><?php echo ucfirst($agent['status']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Confirmation -->
            <div class="confirm-box">
                <div class="icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3>⚠️ Confirm Removal</h3>
                <p>Are you sure you want to remove this party agent from their assigned polling unit?</p>
            </div>

            <!-- Remove Form -->
            <div class="remove-form">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="remove">
                    
                    <div class="form-group">
                        <label>Reason for Removal <span class="required">*</span></label>
                        <textarea name="reason" id="reason" placeholder="Please provide a reason for removing this party agent's assignment..." required></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-danger" onclick="return confirm('Are you sure you want to remove this party agent from their assigned polling unit?')">
                            <i class="fas fa-user-minus"></i> Remove Assignment
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
                <p style="color:var(--gray-500);">The party agent you're trying to remove does not exist.</p>
                <a href="assign-party-agents.php" class="btn-primary-sm" style="display:inline-block;margin-top:12px;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
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