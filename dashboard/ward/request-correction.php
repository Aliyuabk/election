<?php
// ============================================================
// WARD COORDINATOR - REQUEST CORRECTION
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
// GET RESULT ID
// ============================================================
$result_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($result_id <= 0) {
    header('Location: verify-results.php');
    exit();
}

// ============================================================
// FETCH RESULT DETAILS
// ============================================================
$result = null;
$error_message = '';

try {
    $stmt = $db->prepare("
        SELECT 
            r.*,
            u.full_name as agent_name,
            pu.name as pu_name,
            pu.code as pu_code
        FROM results_ec8a r
        JOIN users u ON r.agent_id = u.id
        JOIN polling_units pu ON r.pu_id = pu.id
        WHERE r.id = ? AND r.tenant_id = ? AND r.status IN ('pending', 'rejected')
    ");
    $stmt->execute([$result_id, $tenant_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        header('Location: verify-results.php?error=notfound');
        exit();
    }
    
} catch (Exception $e) {
    error_log("Error fetching result: " . $e->getMessage());
    header('Location: verify-results.php?error=db');
    exit();
}

// ============================================================
// HANDLE CORRECTION REQUEST
// ============================================================
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correction_details = isset($_POST['correction_details']) ? trim($_POST['correction_details']) : '';
    
    if (empty($correction_details)) {
        $error_message = "Please provide correction details.";
    } else {
        try {
            $stmt = $db->prepare("
                UPDATE results_ec8a 
                SET status = 'pending', rejection_reason = CONCAT('Correction requested: ', ?),
                    verified_by = NULL, verified_at = NULL
                WHERE id = ?
            ");
            $stmt->execute([$correction_details, $result_id]);
            
            logActivity($user_id, 'result_correction_requested', "Requested correction for EC8A result ID: $result_id - Details: $correction_details", 'results_ec8a', $result_id);
            
            $success_message = "Correction requested successfully!";
            header('Location: verify-results.php?success=' . urlencode($success_message));
            exit();
            
        } catch (Exception $e) {
            $error_message = "Error requesting correction: " . $e->getMessage();
            error_log("Correction request error: " . $e->getMessage());
        }
    }
}

$page_title = 'Request Correction';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.correction-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.correction-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.correction-header h2 i {
    color: #3B82F6;
}

.result-info {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px;
    margin-bottom: 20px;
}
.result-info .info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 8px 16px;
}
.result-info .info-grid .item {
    font-size: 0.85rem;
    padding: 4px 0;
}
.result-info .info-grid .item .label {
    color: var(--gray-500);
    font-weight: 500;
}
.result-info .info-grid .item .value {
    color: var(--gray-800);
}

.correction-form {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
}
.correction-form .form-group {
    margin-bottom: 16px;
}
.correction-form .form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 4px;
}
.correction-form .form-group label .required {
    color: #EF4444;
}
.correction-form .form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    resize: vertical;
    min-height: 80px;
    font-family: inherit;
}
.correction-form .form-group .helper {
    font-size: 0.7rem;
    color: var(--gray-400);
    margin-top: 4px;
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
    .result-info .info-grid {
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
        <div class="correction-header">
            <div>
                <h2><i class="fas fa-edit"></i> Request Correction</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward
                </p>
            </div>
            <div>
                <a href="verify-results.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($result): ?>
            <!-- Result Information -->
            <div class="result-info">
                <h3 style="margin:0 0 12px;font-size:0.95rem;">
                    <i class="fas fa-info-circle"></i> Submission Details
                </h3>
                <div class="info-grid">
                    <div class="item">
                        <span class="label">Polling Unit</span><br>
                        <span class="value"><?php echo htmlspecialchars($result['pu_name']); ?></span>
                    </div>
                    <div class="item">
                        <span class="label">Code</span><br>
                        <span class="value"><?php echo htmlspecialchars($result['pu_code']); ?></span>
                    </div>
                    <div class="item">
                        <span class="label">Agent</span><br>
                        <span class="value"><?php echo htmlspecialchars($result['agent_name']); ?></span>
                    </div>
                    <div class="item">
                        <span class="label">Status</span><br>
                        <span class="value">
                            <span style="color:<?php echo $result['status'] === 'rejected' ? '#EF4444' : '#F59E0B'; ?>;">
                                <?php echo ucfirst($result['status']); ?>
                            </span>
                        </span>
                    </div>
                    <div class="item">
                        <span class="label">Submitted</span><br>
                        <span class="value"><?php echo date('M d, Y H:i', strtotime($result['created_at'])); ?></span>
                    </div>
                    <?php if (!empty($result['rejection_reason'])): ?>
                        <div class="item" style="grid-column:1/-1;color:#EF4444;">
                            <span class="label">Previous Feedback</span><br>
                            <span class="value"><?php echo htmlspecialchars($result['rejection_reason']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Correction Form -->
            <div class="correction-form">
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Correction Details <span class="required">*</span></label>
                        <textarea name="correction_details" id="correction_details" 
                                  placeholder="Please specify what corrections are needed and provide guidance to the agent..." 
                                  required></textarea>
                        <div class="helper">
                            <i class="fas fa-info-circle"></i> 
                            Be specific about what needs to be corrected. The agent will see these instructions.
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-primary" onclick="return confirm('Request correction for this submission?')">
                            <i class="fas fa-edit"></i> Request Correction
                        </button>
                        <a href="verify-results.php" class="btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>

        <?php else: ?>
            <div style="text-align:center;padding:60px 20px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);">
                <i class="fas fa-file-alt" style="font-size:4rem;color:var(--gray-300);"></i>
                <h4 style="margin:16px 0 8px;">Submission Not Found</h4>
                <p style="color:var(--gray-500);">The submission you're trying to request correction for does not exist.</p>
                <a href="verify-results.php" class="btn-primary-sm" style="display:inline-block;margin-top:12px;">
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