<?php
// ============================================================
// WARD COORDINATOR - APPROVE SUBMISSION
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
            pu.code as pu_code,
            w.name as ward_name
        FROM results_ec8a r
        JOIN users u ON r.agent_id = u.id
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN wards w ON r.ward_id = w.id
        WHERE r.id = ? AND r.tenant_id = ? AND r.status = 'pending'
    ");
    $stmt->execute([$result_id, $tenant_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        header('Location: verify-results.php?error=notfound');
        exit();
    }
    
    // Check if result belongs to this ward
    if ($result['ward_id'] != $ward_id) {
        header('Location: verify-results.php?error=unauthorized');
        exit();
    }
    
} catch (Exception $e) {
    error_log("Error fetching result: " . $e->getMessage());
    header('Location: verify-results.php?error=db');
    exit();
}

// ============================================================
// PARSE PARTY VOTES
// ============================================================
$party_votes = json_decode($result['party_votes_json'] ?? '{}', true);
$total_valid = array_sum($party_votes);

// ============================================================
// HANDLE APPROVAL
// ============================================================
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
    
    try {
        switch ($action) {
            case 'approve':
                $stmt = $db->prepare("
                    UPDATE results_ec8a 
                    SET status = 'approved', verified_by = ?, verified_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$user_id, $result_id]);
                $success_message = "Result approved successfully!";
                logActivity($user_id, 'result_approved', "Approved EC8A result ID: $result_id", 'results_ec8a', $result_id);
                break;
                
            case 'reject':
                if (empty($remarks)) {
                    throw new Exception("Please provide a reason for rejection.");
                }
                $stmt = $db->prepare("
                    UPDATE results_ec8a 
                    SET status = 'rejected', verified_by = ?, verified_at = NOW(), 
                        rejection_reason = ?
                    WHERE id = ?
                ");
                $stmt->execute([$user_id, $remarks, $result_id]);
                $success_message = "Result rejected.";
                logActivity($user_id, 'result_rejected', "Rejected EC8A result ID: $result_id", 'results_ec8a', $result_id);
                break;
                
            case 'request_correction':
                if (empty($remarks)) {
                    throw new Exception("Please provide correction details.");
                }
                $stmt = $db->prepare("
                    UPDATE results_ec8a 
                    SET status = 'pending', rejection_reason = CONCAT('Correction requested: ', ?)
                    WHERE id = ?
                ");
                $stmt->execute([$remarks, $result_id]);
                $success_message = "Correction requested successfully.";
                logActivity($user_id, 'result_correction_requested', "Requested correction for EC8A result ID: $result_id", 'results_ec8a', $result_id);
                break;
                
            default:
                throw new Exception("Invalid action.");
        }
        
        header('Location: verify-results.php?success=' . urlencode($success_message));
        exit();
        
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
        error_log("Result approval error: " . $e->getMessage());
    }
}

$page_title = 'Approve Submission';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.approve-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.approve-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.approve-header h2 i {
    color: var(--primary);
}

.approve-content {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 16px;
}
.approve-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px;
    margin-bottom: 16px;
}
.approve-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--gray-200);
}
.approve-card .card-header h3 {
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

.approve-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.approve-actions .btn-action {
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
.approve-actions .btn-action.approve { background: #D1FAE5; color: #065F46; }
.approve-actions .btn-action.approve:hover { background: #A7F3D0; }
.approve-actions .btn-action.reject { background: #FEE2E2; color: #991B1B; }
.approve-actions .btn-action.reject:hover { background: #FECACA; }
.approve-actions .btn-action.correct { background: #DBEAFE; color: #1E40AF; }
.approve-actions .btn-action.correct:hover { background: #BFDBFE; }

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

.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 2000;
    justify-content: center;
    align-items: center;
}
.modal-overlay.active {
    display: flex;
}
.modal-content {
    background: white;
    border-radius: var(--radius);
    padding: 24px;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}
.modal-content h3 {
    margin: 0 0 16px;
}
.modal-content .form-group {
    margin-bottom: 12px;
}
.modal-content .form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 4px;
}
.modal-content .form-group textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    resize: vertical;
    min-height: 80px;
    font-family: inherit;
}
.modal-content .form-actions {
    display: flex;
    gap: 8px;
    margin-top: 16px;
}

@media (max-width: 768px) {
    .approve-content {
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
        <div class="approve-header">
            <div>
                <h2><i class="fas fa-check-circle"></i> Approve Submission</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($result['ward_name'] ?? ''); ?> Ward • <?php echo htmlspecialchars($result['pu_name'] ?? ''); ?>
                </p>
            </div>
            <div>
                <a href="verify-results.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Verification
                </a>
            </div>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($result): ?>
            <div class="approve-content">
                <!-- Left Column - Result Details -->
                <div>
                    <div class="approve-card">
                        <div class="card-header">
                            <h3><i class="fas fa-info-circle"></i> Submission Details</h3>
                            <span class="status-badge" style="background:#FEF3C7;color:#92400E;font-size:0.6rem;padding:2px 10px;border-radius:20px;">Pending</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Polling Unit</span>
                            <span class="value"><?php echo htmlspecialchars($result['pu_name']); ?> (<?php echo htmlspecialchars($result['pu_code']); ?>)</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Agent</span>
                            <span class="value"><?php echo htmlspecialchars($result['agent_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Registered Voters</span>
                            <span class="value"><?php echo number_format($result['registered_voters'] ?? 0); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Submitted</span>
                            <span class="value"><?php echo date('M d, Y H:i', strtotime($result['created_at'])); ?></span>
                        </div>
                    </div>

                    <div class="approve-card">
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
                                    <span class="votes"><?php echo number_format($result['rejected_votes'] ?? 0); ?></span>
                                </div>
                                <div class="party-vote-item total" style="background:#F5F3FF;">
                                    <span class="party">Total Votes</span>
                                    <span class="votes"><?php echo number_format($result['total_votes_cast'] ?? 0); ?></span>
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
                    <div class="approve-card">
                        <div class="card-header">
                            <h3><i class="fas fa-tasks"></i> Actions</h3>
                        </div>
                        
                        <div class="approve-actions">
                            <button onclick="openActionModal('approve')" class="btn-action approve">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button onclick="openActionModal('reject')" class="btn-action reject">
                                <i class="fas fa-times"></i> Reject
                            </button>
                            <button onclick="openActionModal('request_correction')" class="btn-action correct">
                                <i class="fas fa-edit"></i> Request Correction
                            </button>
                        </div>
                    </div>

                    <div class="approve-card">
                        <div class="card-header">
                            <h3><i class="fas fa-arrow-left"></i> Navigation</h3>
                        </div>
                        <a href="verify-results.php" class="btn-secondary" style="width:100%;text-align:center;display:block;">
                            <i class="fas fa-arrow-left"></i> Back to Verification
                        </a>
                        <a href="result-details.php?id=<?php echo $result_id; ?>" class="btn-secondary" style="width:100%;text-align:center;display:block;margin-top:8px;">
                            <i class="fas fa-info-circle"></i> View Full Details
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
        <h3 id="modalTitle">Action on Submission</h3>
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
        'approve': 'Approve Submission',
        'reject': 'Reject Submission',
        'request_correction': 'Request Correction'
    };
    document.getElementById('modalTitle').textContent = titles[action] || 'Action on Submission';
    
    const submitLabels = {
        'approve': 'Approve',
        'reject': 'Reject',
        'request_correction': 'Request Correction'
    };
    document.getElementById('modalSubmitBtn').innerHTML = `<i class="fas fa-check"></i> ${submitLabels[action] || 'Confirm'}`;
    
    const remarksRequired = document.getElementById('remarksRequired');
    if (action === 'reject' || action === 'request_correction') {
        remarksRequired.style.display = 'inline';
        document.getElementById('modalRemarks').required = true;
        document.getElementById('modalRemarks').placeholder = action === 'reject' ? 
            'Please provide a reason for rejection...' : 
            'Please provide correction details...';
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