<?php
// ============================================================
// WARD COORDINATOR - SUBMIT EC8B
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'ward') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Ward Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$ward_id = SessionManager::get('ward_id');

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
$ec8b_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($ec8b_id <= 0) {
    header('Location: ec8b-history.php');
    exit();
}

// Get EC8B details
$ec8b = null;
try {
    $stmt = $db->prepare("
        SELECT r.*, e.name as election_name, w.name as ward_name
        FROM results_ec8b r
        JOIN elections e ON r.election_id = e.id
        JOIN wards w ON r.ward_id = w.id
        WHERE r.id = ? AND r.tenant_id = ? AND r.ward_id = ?
    ");
    $stmt->execute([$ec8b_id, $tenant_id, $ward_id]);
    $ec8b = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching EC8B: " . $e->getMessage());
}

if (!$ec8b) {
    header('Location: ec8b-history.php');
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm = $_POST['confirm'] ?? '';
    
    if ($confirm !== 'yes') {
        $error = 'Please confirm submission.';
    } else {
        try {
            $stmt = $db->prepare("
                UPDATE results_ec8b 
                SET status = 'pending'
                WHERE id = ? AND tenant_id = ? AND status = 'pending'
            ");
            $stmt->execute([$ec8b_id, $tenant_id]);
            
            logActivity($user_id, 'ec8b_submitted', 
                "Submitted EC8B form ID: $ec8b_id for verification",
                'results_ec8b', $ec8b_id
            );
            
            $message = "EC8B form submitted successfully for verification!";
            
            // Refresh data
            $stmt = $db->prepare("SELECT * FROM results_ec8b WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$ec8b_id, $tenant_id]);
            $ec8b = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Redirect after success
            header('Location: ec8b-history.php?submitted=1');
            exit();
            
        } catch (Exception $e) {
            $error = 'Failed to submit EC8B: ' . $e->getMessage();
            error_log("EC8B submit error: " . $e->getMessage());
        }
    }
}

$page_title = 'Submit EC8B';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.submit-container {
    max-width: 600px;
    margin: 0 auto;
}

.submit-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 28px;
}

.submit-card .card-title {
    font-size: 0.85rem;
    font-weight: 600;
    margin: 0 0 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-700);
}

.submit-card .card-title i {
    color: #10B981;
    margin-right: 6px;
}

.ec8b-info {
    background: var(--gray-50);
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 16px;
}

.ec8b-info .label {
    font-size: 0.6rem;
    color: var(--gray-500);
    display: block;
}

.ec8b-info .value {
    font-weight: 500;
    color: var(--gray-800);
    font-size: 0.85rem;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.55rem;
    padding: 2px 10px;
    border-radius: 8px;
    font-weight: 600;
}

.status-badge .dot {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    display: inline-block;
}

.status-badge.pending { background: #FFFBEB; color: #92400E; }
.status-badge.pending .dot { background: #F59E0B; }

.info-box {
    background: #F0F9FF;
    border: 1px solid #BAE6FD;
    border-radius: 8px;
    padding: 12px 16px;
    margin: 16px 0;
    font-size: 0.8rem;
    color: #0369A1;
}

.info-box i {
    margin-right: 6px;
}

.checklist {
    list-style: none;
    padding: 0;
    margin: 12px 0;
}

.checklist li {
    padding: 6px 0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.82rem;
    color: var(--gray-700);
}

.checklist li i {
    color: #10B981;
    font-size: 0.9rem;
}

.checklist li .pending {
    color: #F59E0B;
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

.btn-group {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-group button {
    padding: 10px 24px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.btn-group .btn-submit {
    background: #10B981;
    color: white;
}

.btn-group .btn-submit:hover {
    background: #059669;
}

.btn-group .btn-submit:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-group .btn-cancel {
    background: var(--gray-100);
    color: var(--gray-700);
    text-decoration: none;
    padding: 10px 24px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-group .btn-cancel:hover {
    background: var(--gray-200);
}

@media (max-width: 768px) {
    .submit-card {
        padding: 16px 18px;
    }
    .btn-group {
        flex-direction: column;
    }
    .btn-group button,
    .btn-group .btn-cancel {
        width: 100%;
        justify-content: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="submit-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-paper-plane"></i> Submit EC8B</h1>
                    <p class="subtitle">
                        <i class="fas fa-file-alt"></i> 
                        EC8B #<?php echo $ec8b_id; ?> - <?php echo htmlspecialchars($ec8b['election_name']); ?>
                    </p>
                </div>
                <div>
                    <span class="status-badge <?php echo $ec8b['status']; ?>">
                        <span class="dot"></span>
                        <?php echo ucfirst($ec8b['status']); ?>
                    </span>
                </div>
                <div class="actions">
                    <a href="ec8b-history.php" class="btn-secondary-sm">
                        <i class="fas fa-history"></i> Back to History
                    </a>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="submit-card">
                <div class="card-title"><i class="fas fa-info-circle"></i> EC8B Details</div>
                
                <div class="ec8b-info">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
                        <div>
                            <span class="label">Election</span>
                            <span class="value"><?php echo htmlspecialchars($ec8b['election_name']); ?></span>
                        </div>
                        <div>
                            <span class="label">Ward</span>
                            <span class="value"><?php echo htmlspecialchars($ec8b['ward_name']); ?></span>
                        </div>
                        <div>
                            <span class="label">Valid Votes</span>
                            <span class="value"><?php echo number_format($ec8b['valid_votes']); ?></span>
                        </div>
                        <div>
                            <span class="label">Rejected Votes</span>
                            <span class="value"><?php echo number_format($ec8b['rejected_votes']); ?></span>
                        </div>
                        <div style="grid-column: span 2;">
                            <span class="label">Total Votes</span>
                            <span class="value"><?php echo number_format($ec8b['total_votes']); ?></span>
                        </div>
                    </div>
                </div>

                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <strong>Before submitting:</strong> Please verify that all polling unit results (EC8A) have been 
                    collected and the ward collation is complete and accurate.
                </div>

                <div class="checklist">
                    <li><i class="fas fa-check-circle"></i> All EC8A forms submitted</li>
                    <li><i class="fas fa-check-circle"></i> Votes correctly aggregated</li>
                    <li><i class="fas fa-check-circle"></i> No mismatches in vote totals</li>
                    <li><i class="fas fa-check-circle"></i> EC8B form completed</li>
                    <li><i class="fas fa-check-circle"></i> Supporting documents attached</li>
                </div>

                <?php if ($ec8b['mismatch_alert']): ?>
                    <div style="margin:12px 0;padding:10px 14px;background:#FEF2F2;border:1px solid #FECACA;border-radius:8px;color:#991B1B;font-size:0.8rem;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> There is a mismatch in the vote totals. Please review and correct before submitting.
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="info-box" style="background:#FEF2F2;border-color:#FECACA;color:#991B1B;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Confirmation:</strong> Submitting this EC8B will send it for verification by the LGA Coordinator.
                    </div>

                    <div class="btn-group">
                        <a href="ec8b-history.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" name="confirm" value="yes" class="btn-submit" 
                                <?php echo $ec8b['mismatch_alert'] ? 'disabled' : ''; ?>
                                onclick="return confirm('Submit this EC8B form for verification?')">
                            <i class="fas fa-paper-plane"></i> Submit EC8B
                        </button>
                    </div>
                    <?php if ($ec8b['mismatch_alert']): ?>
                        <div style="margin-top:8px;font-size:0.7rem;color:#EF4444;">
                            <i class="fas fa-info-circle"></i> Please fix the mismatch before submitting.
                        </div>
                    <?php endif; ?>
                </form>
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