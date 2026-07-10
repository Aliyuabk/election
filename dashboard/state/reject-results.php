<?php
// ============================================================
// STATE COORDINATOR - REJECT RESULTS
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'state') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'State Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$state_id = SessionManager::get('state_id');

if (empty($state_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT state_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['state_id'])) {
            $state_id = $user['state_id'];
            SessionManager::set('state_id', $state_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching state_id: " . $e->getMessage());
    }
}

$db = getDB();
$result_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($result_id <= 0) {
    header('Location: result-verification.php');
    exit();
}

// Get result details
$result = null;
try {
    $stmt = $db->prepare("
        SELECT r.*, pu.name as pu_name, pu.code as pu_code, e.name as election_name
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN elections e ON r.election_id = e.id
        WHERE r.id = ? AND r.tenant_id = ?
    ");
    $stmt->execute([$result_id, $tenant_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching result: " . $e->getMessage());
}

if (!$result) {
    header('Location: result-verification.php');
    exit();
}

$message = '';
$error = '';

// Handle rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = trim($_POST['reason'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($reason)) {
        $error = 'Please provide a reason for rejection.';
    } else {
        try {
            $stmt = $db->prepare("
                UPDATE results_ec8a 
                SET status = 'rejected', 
                    rejection_reason = ?,
                    remarks = CONCAT(COALESCE(remarks, ''), '\n', 'Rejected: ', ?),
                    verified_by = ?, 
                    verified_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$reason, $notes, $user_id, $result_id, $tenant_id]);
            
            logActivity($user_id, 'ec8a_rejected', 
                "Rejected EC8A result ID: $result_id for PU: {$result['pu_name']} - Reason: $reason",
                'results_ec8a', $result_id
            );
            
            $message = 'Result rejected successfully.';
        } catch (Exception $e) {
            $error = 'Failed to reject result: ' . $e->getMessage();
        }
    }
}

$page_title = 'Reject Result';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.reject-container {
    max-width: 600px;
    margin: 0 auto;
}

.reject-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 28px;
    margin-bottom: 16px;
}

.reject-card .card-title {
    font-size: 0.85rem;
    font-weight: 600;
    margin: 0 0 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-700);
}

.reject-card .card-title i {
    color: #EF4444;
    margin-right: 6px;
}

.result-info {
    background: var(--gray-50);
    border-radius: 10px;
    padding: 14px 18px;
    margin-bottom: 16px;
}

.result-info .label {
    font-size: 0.65rem;
    color: var(--gray-500);
    display: block;
}

.result-info .value {
    font-weight: 500;
    color: var(--gray-800);
}

.warning-box {
    background: #FEF2F2;
    border: 1px solid #FECACA;
    border-radius: 10px;
    padding: 14px 18px;
    margin-bottom: 16px;
    color: #991B1B;
}

.warning-box i {
    margin-right: 6px;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    font-weight: 600;
    font-size: 0.8rem;
    color: var(--gray-700);
    margin-bottom: 4px;
}

.form-group label .required {
    color: #EF4444;
    margin-left: 2px;
}

.form-group textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    resize: vertical;
    min-height: 100px;
    transition: var(--transition);
}

.form-group textarea:focus {
    outline: none;
    border-color: #EF4444;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.06);
}

.alert {
    padding: 12px 16px;
    border-radius: 10px;
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
    margin-top: 8px;
}

.btn-reject {
    padding: 10px 32px;
    background: #EF4444;
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.btn-reject:hover {
    background: #DC2626;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.btn-cancel {
    padding: 10px 32px;
    background: var(--gray-100);
    color: var(--gray-700);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    text-decoration: none;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-cancel:hover {
    background: var(--gray-200);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.65rem;
    padding: 4px 14px;
    border-radius: 12px;
    font-weight: 600;
}

.status-badge .dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    display: inline-block;
}

.status-badge.pending { background: #FFFBEB; color: #92400E; }
.status-badge.pending .dot { background: #F59E0B; }

@media (max-width: 768px) {
    .reject-card {
        padding: 16px 18px;
    }
    .btn-group {
        flex-direction: column;
    }
    .btn-group button,
    .btn-group a {
        width: 100%;
        justify-content: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="reject-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-times-circle" style="color:#EF4444;"></i> Reject Result</h1>
                    <p class="subtitle">
                        <i class="fas fa-map-pin"></i> 
                        <?php echo htmlspecialchars($result['pu_name']); ?> - 
                        <?php echo htmlspecialchars($result['election_name'] ?? 'N/A'); ?>
                    </p>
                </div>
                <div>
                    <span class="status-badge <?php echo $result['status']; ?>">
                        <span class="dot"></span>
                        <?php echo ucfirst($result['status']); ?>
                    </span>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
                <div style="margin-top:12px;">
                    <a href="result-verification.php" class="btn-cancel">
                        <i class="fas fa-arrow-left"></i> Back to Verification
                    </a>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!$message): ?>
                <div class="reject-card">
                    <div class="card-title"><i class="fas fa-exclamation-triangle"></i> Reject Result</div>

                    <div class="warning-box">
                        <i class="fas fa-exclamation-circle"></i>
                        <strong>Warning:</strong> Rejecting this result will require the agent to resubmit.
                    </div>

                    <div class="result-info">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                            <div>
                                <span class="label">Polling Unit</span>
                                <span class="value"><?php echo htmlspecialchars($result['pu_name']); ?></span>
                            </div>
                            <div>
                                <span class="label">PU Code</span>
                                <span class="value"><?php echo htmlspecialchars($result['pu_code']); ?></span>
                            </div>
                            <div>
                                <span class="label">Election</span>
                                <span class="value"><?php echo htmlspecialchars($result['election_name'] ?? 'N/A'); ?></span>
                            </div>
                            <div>
                                <span class="label">Valid Votes</span>
                                <span class="value"><?php echo number_format($result['valid_votes']); ?></span>
                            </div>
                            <div>
                                <span class="label">Submitted At</span>
                                <span class="value"><?php echo date('M j, Y g:i A', strtotime($result['created_at'])); ?></span>
                            </div>
                            <div>
                                <span class="label">Status</span>
                                <span class="status-badge <?php echo $result['status']; ?>" style="font-size:0.65rem;">
                                    <span class="dot"></span>
                                    <?php echo ucfirst($result['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Reason for Rejection <span class="required">*</span></label>
                            <textarea name="reason" required placeholder="Please provide a detailed reason for rejecting this result..."></textarea>
                        </div>

                        <div class="form-group">
                            <label>Additional Notes (Optional)</label>
                            <textarea name="notes" placeholder="Add any additional notes or instructions for the agent..."></textarea>
                        </div>

                        <div class="btn-group">
                            <a href="verify-ec8a.php?id=<?php echo $result_id; ?>" class="btn-cancel">
                                <i class="fas fa-arrow-left"></i> Back
                            </a>
                            <button type="submit" class="btn-reject">
                                <i class="fas fa-times"></i> Reject Result
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
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