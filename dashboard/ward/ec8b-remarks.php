<?php
// ============================================================
// WARD COORDINATOR - ADD REMARKS TO EC8B
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
// GET EC8B ID
// ============================================================
$ec8b_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($ec8b_id <= 0) {
    header('Location: ec8b-history.php');
    exit();
}

// ============================================================
// FETCH EC8B DETAILS
// ============================================================
$ec8b = null;
$error_message = '';

try {
    $stmt = $db->prepare("
        SELECT * FROM results_ec8b 
        WHERE id = ? AND tenant_id = ? AND ward_id = ?
    ");
    $stmt->execute([$ec8b_id, $tenant_id, $ward_id]);
    $ec8b = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ec8b) {
        header('Location: ec8b-history.php?error=notfound');
        exit();
    }
    
} catch (Exception $e) {
    error_log("Error fetching EC8B: " . $e->getMessage());
    header('Location: ec8b-history.php?error=db');
    exit();
}

// ============================================================
// HANDLE REMARKS
// ============================================================
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
    
    if (empty($remarks)) {
        $error_message = "Please enter remarks.";
    } else {
        try {
            $stmt = $db->prepare("
                UPDATE results_ec8b 
                SET remarks = CONCAT(COALESCE(remarks, ''), '\n', ?) 
                WHERE id = ? AND tenant_id = ? AND ward_id = ?
            ");
            $stmt->execute([$remarks, $ec8b_id, $tenant_id, $ward_id]);
            
            logActivity($user_id, 'ec8b_remarks_added', "Added remarks to EC8B ID: $ec8b_id", 'results_ec8b', $ec8b_id);
            
            $success_message = "Remarks added successfully!";
            header('Location: ec8b-details.php?id=' . $ec8b_id . '&success=' . urlencode($success_message));
            exit();
            
        } catch (Exception $e) {
            $error_message = "Error adding remarks: " . $e->getMessage();
            error_log("EC8B remarks error: " . $e->getMessage());
        }
    }
}

$page_title = 'Add Remarks to EC8B';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.remarks-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.remarks-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.remarks-header h2 i {
    color: var(--primary);
}

.remarks-section {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px;
}
.remarks-section .form-group {
    margin-bottom: 16px;
}
.remarks-section .form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 4px;
}
.remarks-section .form-group label .required {
    color: #EF4444;
}
.remarks-section .form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    resize: vertical;
    min-height: 120px;
    font-family: inherit;
}

.ec8b-info {
    background: var(--gray-50);
    border-radius: var(--radius);
    padding: 16px;
    margin-bottom: 20px;
}
.ec8b-info .info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 8px 16px;
}
.ec8b-info .info-grid .item {
    font-size: 0.85rem;
    padding: 4px 0;
}
.ec8b-info .info-grid .item .label {
    color: var(--gray-500);
    font-weight: 500;
}
.ec8b-info .info-grid .item .value {
    color: var(--gray-800);
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
    .ec8b-info .info-grid {
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
        <div class="remarks-header">
            <div>
                <h2><i class="fas fa-comment"></i> Add Remarks to EC8B</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward • Form #<?php echo $ec8b_id; ?>
                </p>
            </div>
            <div>
                <a href="ec8b-details.php?id=<?php echo $ec8b_id; ?>" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($ec8b): ?>
            <!-- EC8B Information -->
            <div class="ec8b-info">
                <div class="info-grid">
                    <div class="item">
                        <span class="label">Form ID</span><br>
                        <span class="value">#<?php echo $ec8b['id']; ?></span>
                    </div>
                    <div class="item">
                        <span class="label">Status</span><br>
                        <span class="value"><?php echo ucfirst($ec8b['status']); ?></span>
                    </div>
                    <div class="item">
                        <span class="label">Created</span><br>
                        <span class="value"><?php echo date('M d, Y H:i', strtotime($ec8b['created_at'])); ?></span>
                    </div>
                    <?php if (!empty($ec8b['remarks'])): ?>
                        <div class="item" style="grid-column:1/-1;">
                            <span class="label">Existing Remarks</span><br>
                            <span class="value" style="white-space:pre-wrap;"><?php echo htmlspecialchars($ec8b['remarks']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Remarks Form -->
            <div class="remarks-section">
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Remarks <span class="required">*</span></label>
                        <textarea name="remarks" id="remarks" placeholder="Add your remarks about this EC8B form..." required></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-comment"></i> Add Remarks
                        </button>
                        <a href="ec8b-details.php?id=<?php echo $ec8b_id; ?>" class="btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>

        <?php else: ?>
            <div style="text-align:center;padding:60px 20px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);">
                <i class="fas fa-file-alt" style="font-size:4rem;color:var(--gray-300);"></i>
                <h4 style="margin:16px 0 8px;">Form Not Found</h4>
                <p style="color:var(--gray-500);">The EC8B form does not exist.</p>
                <a href="ec8b-history.php" class="btn-primary-sm" style="display:inline-block;margin-top:12px;">
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