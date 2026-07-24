<?php
// ============================================================
// WARD COORDINATOR - VERIFY EC8B
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
        SELECT 
            e.*,
            u.full_name as coordinator_name,
            w.name as ward_name,
            l.name as lga_name,
            s.name as state_name
        FROM results_ec8b e
        JOIN users u ON e.coordinator_id = u.id
        JOIN wards w ON e.ward_id = w.id
        JOIN lgas l ON e.lga_id = l.id
        JOIN states s ON e.state_id = s.id
        WHERE e.id = ? AND e.tenant_id = ? AND e.ward_id = ?
    ");
    $stmt->execute([$ec8b_id, $tenant_id, $ward_id]);
    $ec8b = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ec8b) {
        header('Location: ec8b-history.php?error=notfound');
        exit();
    }
    
    if ($ec8b['status'] !== 'pending') {
        header('Location: ec8b-history.php?error=already_verified');
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
// HANDLE VERIFICATION
// ============================================================
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
    
    try {
        switch ($action) {
            case 'verify':
                $stmt = $db->prepare("
                    UPDATE results_ec8b 
                    SET status = 'verified', verified_by = ?, created_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$user_id, $ec8b_id]);
                $success_message = "EC8B verified successfully!";
                logActivity($user_id, 'ec8b_verified', "Verified EC8B ID: $ec8b_id", 'results_ec8b', $ec8b_id);
                break;
                
            case 'reject':
                if (empty($remarks)) {
                    throw new Exception("Please provide a reason for rejection.");
                }
                $stmt = $db->prepare("
                    UPDATE results_ec8b 
                    SET status = 'rejected', verified_by = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$user_id, $ec8b_id]);
                $success_message = "EC8B rejected.";
                logActivity($user_id, 'ec8b_rejected', "Rejected EC8B ID: $ec8b_id - Reason: $remarks", 'results_ec8b', $ec8b_id);
                break;
                
            case 'flag':
                if (empty($remarks)) {
                    throw new Exception("Please provide a reason for flagging.");
                }
                $stmt = $db->prepare("
                    UPDATE results_ec8b 
                    SET status = 'flagged', verified_by = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$user_id, $ec8b_id]);
                $success_message = "EC8B flagged for review.";
                logActivity($user_id, 'ec8b_flagged', "Flagged EC8B ID: $ec8b_id - Reason: $remarks", 'results_ec8b', $ec8b_id);
                break;
                
            default:
                throw new Exception("Invalid action.");
        }
        
        // Redirect after success
        header('Location: ec8b-details.php?id=' . $ec8b_id . '&success=' . urlencode($success_message));
        exit();
        
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
        error_log("EC8B verification error: " . $e->getMessage());
    }
}

$page_title = 'Verify EC8B';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.verify-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.verify-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.verify-header h2 i {
    color: var(--primary);
}

.verify-content {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 16px;
}
.verify-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px;
    margin-bottom: 16px;
}
.verify-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--gray-200);
}
.verify-card .card-header h3 {
    font-size: 0.9rem;
    font-weight: 600;
    margin: 0;
}

.info-row {
    display: flex;
    padding: 6px 0;
    font-size: 0.85rem;
    border-bottom: 1px solid var(--gray-100);
}
.info-row:last-child {
    border-bottom: none;
}
.info-row .label {
    width: 140px;
    color: var(--gray-500);
    font-weight: 500;
    flex-shrink: 0;
}
.info-row .value {
    flex: 1;
    color: var(--gray-800);
}

.party-votes-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
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

.verify-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.verify-actions .btn-action {
    padding: 10px 16px;
    border-radius: var(--radius);
    border: none;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
    justify-content: center;
    transition: var(--transition);
}
.verify-actions .btn-action.verify { background: #D1FAE5; color: #065F46; }
.verify-actions .btn-action.verify:hover { background: #A7F3D0; }
.verify-actions .btn-action.reject { background: #FEE2E2; color: #991B1B; }
.verify-actions .btn-action.reject:hover { background: #FECACA; }
.verify-actions .btn-action.flag { background: #FEF3C7; color: #92400E; }
.verify-actions .btn-action.flag:hover { background: #FDE68A; }

.verify-form {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--gray-200);
}
.verify-form .form-group {
    margin-bottom: 12px;
}
.verify-form .form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 4px;
}
.verify-form .form-group textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    resize: vertical;
    min-height: 80px;
    font-family: inherit;
}
.verify-form .form-actions {
    display: flex;
    gap: 8px;
}

.status-badge {
    font-size: 0.7rem;
    padding: 4px 14px;
    border-radius: 20px;
    font-weight: 500;
    display: inline-block;
}
.status-badge.pending { background: #FEF3C7; color: #92400E; }

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
    .verify-content {
        grid-template-columns: 1fr;
    }
    .info-row {
        flex-direction: column;
        padding: 8px 0;
    }
    .info-row .label {
        width: 100%;
        font-size: 0.75rem;
    }
    .party-votes-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="verify-header">
            <div>
                <h2><i class="fas fa-check-double"></i> Verify EC8B</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ec8b['ward_name'] ?? ''); ?> Ward • Form #<?php echo $ec8b_id; ?>
                </p>
            </div>
            <div>
                <a href="ec8b-history.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to History
                </a>
            </div>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($ec8b): ?>
            <div class="verify-content">
                <!-- Left Column - Form Details -->
                <div>
                    <div class="verify-card">
                        <div class="card-header">
                            <h3><i class="fas fa-info-circle"></i> Form Information</h3>
                            <span class="status-badge pending">Pending</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Form ID</span>
                            <span class="value">#<?php echo $ec8b['id']; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Ward</span>
                            <span class="value"><?php echo htmlspecialchars($ec8b['ward_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">LGA</span>
                            <span class="value"><?php echo htmlspecialchars($ec8b['lga_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">State</span>
                            <span class="value"><?php echo htmlspecialchars($ec8b['state_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Coordinator</span>
                            <span class="value"><?php echo htmlspecialchars($ec8b['coordinator_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Created</span>
                            <span class="value"><?php echo date('M d, Y H:i', strtotime($ec8b['created_at'])); ?></span>
                        </div>
                        <?php if ($ec8b['mismatch_alert'] ?? 0): ?>
                            <div class="info-row" style="color:#EF4444;background:#FEF2F2;padding:8px 12px;border-radius:4px;margin-top:8px;">
                                <span class="label">⚠️ Mismatch Alert</span>
                                <span class="value">Calculated total does not match entered total</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="verify-card">
                        <div class="card-header">
                            <h3><i class="fas fa-vote-yea"></i> Party Votes</h3>
                        </div>
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
                            <?php else: ?>
                                <div style="grid-column:1/-1;text-align:center;color:var(--gray-400);padding:12px;">
                                    No party votes recorded
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Actions -->
                <div>
                    <div class="verify-card">
                        <div class="card-header">
                            <h3><i class="fas fa-tasks"></i> Verification Actions</h3>
                        </div>
                        
                        <div class="verify-actions">
                            <button onclick="openActionModal('verify')" class="btn-action verify">
                                <i class="fas fa-check"></i> Verify EC8B
                            </button>
                            <button onclick="openActionModal('reject')" class="btn-action reject">
                                <i class="fas fa-times"></i> Reject
                            </button>
                            <button onclick="openActionModal('flag')" class="btn-action flag">
                                <i class="fas fa-flag"></i> Flag for Review
                            </button>
                        </div>
                    </div>

                    <div class="verify-card">
                        <div class="card-header">
                            <h3><i class="fas fa-arrow-left"></i> Navigation</h3>
                        </div>
                        <a href="ec8b-history.php" class="btn-secondary" style="width:100%;text-align:center;display:block;">
                            <i class="fas fa-arrow-left"></i> Back to History
                        </a>
                        <a href="ec8b-details.php?id=<?php echo $ec8b_id; ?>" class="btn-secondary" style="width:100%;text-align:center;display:block;margin-top:8px;">
                            <i class="fas fa-info-circle"></i> View Details
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Action Modal -->
<div class="modal-overlay" id="actionModal">
    <div class="modal-content">
        <h3 id="modalTitle">Action on EC8B</h3>
        <form method="POST" action="">
            <input type="hidden" name="action" id="modalAction">
            
            <div class="form-group">
                <label for="modalRemarks">Remarks <span id="remarksRequired" style="color:#EF4444;display:none;">*</span></label>
                <textarea name="remarks" id="modalRemarks" placeholder="Add remarks about this action..." rows="4"></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-primary" id="modalSubmitBtn">
                    <i class="fas fa-check"></i> Confirm
                </button>
                <button type="button" class="btn-secondary" onclick="closeModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Open action modal
function openActionModal(action) {
    document.getElementById('modalAction').value = action;
    
    const titles = {
        'verify': 'Verify EC8B',
        'reject': 'Reject EC8B',
        'flag': 'Flag EC8B for Review'
    };
    document.getElementById('modalTitle').textContent = titles[action] || 'Action on EC8B';
    
    const submitLabels = {
        'verify': 'Verify',
        'reject': 'Reject',
        'flag': 'Flag'
    };
    document.getElementById('modalSubmitBtn').innerHTML = `<i class="fas fa-check"></i> ${submitLabels[action] || 'Confirm'}`;
    
    const remarksRequired = document.getElementById('remarksRequired');
    if (action === 'reject' || action === 'flag') {
        remarksRequired.style.display = 'inline';
        document.getElementById('modalRemarks').required = true;
        document.getElementById('modalRemarks').placeholder = action === 'reject' ? 
            'Please provide a reason for rejection...' : 
            'Please provide a reason for flagging...';
    } else {
        remarksRequired.style.display = 'none';
        document.getElementById('modalRemarks').required = false;
        document.getElementById('modalRemarks').placeholder = 'Add optional remarks...';
    }
    
    document.getElementById('modalRemarks').value = '';
    document.getElementById('actionModal').classList.add('active');
}

// Close modal
function closeModal() {
    document.getElementById('actionModal').classList.remove('active');
}

// Close modal on overlay click
document.getElementById('actionModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
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