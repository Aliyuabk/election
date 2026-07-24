<?php
// ============================================================
// WARD COORDINATOR - SUBMIT EC8B
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
    
    if ($ec8b['status'] !== 'pending') {
        header('Location: ec8b-history.php?error=already_submitted');
        exit();
    }
    
} catch (Exception $e) {
    error_log("Error fetching EC8B: " . $e->getMessage());
    header('Location: ec8b-history.php?error=db');
    exit();
}

// ============================================================
// PARSE DATA
// ============================================================
$party_votes = json_decode($ec8b['party_votes_json'] ?? '{}', true);
$total_valid = array_sum($party_votes);

// ============================================================
// HANDLE SUBMISSION
// ============================================================
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit') {
    $confirm = isset($_POST['confirm']) ? $_POST['confirm'] : '';
    
    if ($confirm !== 'yes') {
        $error_message = "Please confirm submission.";
    } else {
        try {
            $stmt = $db->prepare("
                UPDATE results_ec8b 
                SET status = 'verified', verified_by = ?, created_at = NOW() 
                WHERE id = ? AND tenant_id = ? AND ward_id = ?
            ");
            $stmt->execute([$user_id, $ec8b_id, $tenant_id, $ward_id]);
            
            logActivity($user_id, 'ec8b_submitted', "Submitted EC8B ID: $ec8b_id", 'results_ec8b', $ec8b_id);
            
            $success_message = "EC8B submitted successfully!";
            header('Location: ec8b-details.php?id=' . $ec8b_id . '&success=' . urlencode($success_message));
            exit();
            
        } catch (Exception $e) {
            $error_message = "Error submitting EC8B: " . $e->getMessage();
            error_log("EC8B submission error: " . $e->getMessage());
        }
    }
}

$page_title = 'Submit EC8B';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.submit-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.submit-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.submit-header h2 i {
    color: #10B981;
}

.ec8b-preview {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
    margin-bottom: 20px;
}
.ec8b-preview .preview-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px 16px;
}
.ec8b-preview .preview-item {
    font-size: 0.85rem;
    padding: 4px 0;
    border-bottom: 1px solid var(--gray-100);
}
.ec8b-preview .preview-item .label {
    color: var(--gray-500);
    font-weight: 500;
}
.ec8b-preview .preview-item .value {
    color: var(--gray-800);
}

.party-votes-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin: 12px 0;
}
.party-vote-item {
    display: flex;
    justify-content: space-between;
    padding: 6px 12px;
    background: var(--gray-50);
    border-radius: 4px;
    font-size: 0.85rem;
}
.party-vote-item .party {
    font-weight: 500;
}
.party-vote-item .votes {
    font-weight: 600;
}
.party-vote-item.total {
    background: #EFF6FF;
    font-weight: 700;
    grid-column: 1/-1;
}

.confirm-box {
    background: #ECFDF5;
    border: 1px solid #D1FAE5;
    border-radius: var(--radius);
    padding: 20px;
    margin-bottom: 20px;
    text-align: center;
}
.confirm-box .icon {
    font-size: 3rem;
    color: #10B981;
    margin-bottom: 12px;
}
.confirm-box h3 {
    color: #065F46;
    margin: 0 0 8px;
}
.confirm-box p {
    color: #065F46;
    font-size: 0.95rem;
    margin: 0;
}

.submit-form {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
}
.submit-form .form-group {
    margin-bottom: 16px;
}
.submit-form .form-group label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--gray-700);
    cursor: pointer;
}
.submit-form .form-group input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #10B981;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 16px;
}
.form-actions .btn-success {
    background: #10B981;
    color: white;
    border-color: #10B981;
}
.form-actions .btn-success:hover {
    background: #059669;
    border-color: #059669;
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
    .ec8b-preview .preview-grid {
        grid-template-columns: 1fr;
    }
    .party-votes-grid {
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
        <div class="submit-header">
            <div>
                <h2><i class="fas fa-paper-plane"></i> Submit EC8B</h2>
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
            <!-- Preview -->
            <div class="ec8b-preview">
                <h3 style="margin:0 0 12px;font-size:0.95rem;">
                    <i class="fas fa-file-alt"></i> EC8B Form Preview
                </h3>
                <div class="preview-grid">
                    <div class="preview-item">
                        <span class="label">Form ID</span><br>
                        <span class="value">#<?php echo $ec8b['id']; ?></span>
                    </div>
                    <div class="preview-item">
                        <span class="label">Status</span><br>
                        <span class="value">Pending</span>
                    </div>
                    <div class="preview-item">
                        <span class="label">Ward</span><br>
                        <span class="value"><?php echo htmlspecialchars($ward_name); ?></span>
                    </div>
                    <div class="preview-item">
                        <span class="label">Created</span><br>
                        <span class="value"><?php echo date('M d, Y H:i', strtotime($ec8b['created_at'])); ?></span>
                    </div>
                </div>
                
                <h4 style="font-size:0.85rem;margin:12px 0 8px;">Party Votes</h4>
                <div class="party-votes-grid">
                    <?php if (!empty($party_votes)): ?>
                        <?php foreach ($party_votes as $party => $votes): ?>
                            <div class="party-vote-item">
                                <span class="party"><?php echo htmlspecialchars($party); ?></span>
                                <span class="votes"><?php echo number_format($votes); ?></span>
                            </div>
                        <?php endforeach; ?>
                        <div class="party-vote-item total">
                            <span class="party">Total Valid Votes</span>
                            <span class="votes"><?php echo number_format($total_valid); ?></span>
                        </div>
                        <div class="party-vote-item total" style="background:#FEF2F2;">
                            <span class="party">Rejected Votes</span>
                            <span class="votes"><?php echo number_format($ec8b['rejected_votes'] ?? 0); ?></span>
                        </div>
                        <div class="party-vote-item total" style="background:#F5F3FF;">
                            <span class="party">Total Votes</span>
                            <span class="votes"><?php echo number_format($ec8b['total_votes'] ?? 0); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Confirmation -->
            <div class="confirm-box">
                <div class="icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3>Submit EC8B Form</h3>
                <p>Confirm that all information is correct and submit the EC8B form for verification.</p>
            </div>

            <!-- Submit Form -->
            <div class="submit-form">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="submit">
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="confirm" value="yes" required>
                            I confirm that all information is accurate and complete.
                        </label>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-success" onclick="return confirm('Are you sure you want to submit this EC8B form?')">
                            <i class="fas fa-paper-plane"></i> Submit EC8B
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
                <p style="color:var(--gray-500);">The EC8B form does not exist or has already been submitted.</p>
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