<?php
// ============================================================
// WARD COORDINATOR - RESULT DETAILS
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

try {
    $stmt = $db->prepare("
        SELECT 
            r.*,
            u.full_name as agent_name,
            u.user_code as agent_code,
            u.email as agent_email,
            u.phone as agent_phone,
            u.photograph_url as agent_photo,
            pu.name as pu_name,
            pu.code as pu_code,
            pu.registered_voters,
            pu.address as pu_address,
            pu.gps_lat,
            pu.gps_lng,
            w.name as ward_name,
            l.name as lga_name,
            s.name as state_name,
            verified_user.full_name as verified_by_name,
            verified_user.user_code as verified_by_code
        FROM results_ec8a r
        JOIN users u ON r.agent_id = u.id
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN wards w ON r.ward_id = w.id
        JOIN lgas l ON r.lga_id = l.id
        JOIN states s ON r.state_id = s.id
        LEFT JOIN users verified_user ON r.verified_by = verified_user.id
        WHERE r.id = ? AND r.tenant_id = ?
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
    error_log("Error fetching result details: " . $e->getMessage());
    header('Location: verify-results.php?error=db');
    exit();
}

// ============================================================
// PARSE PARTY VOTES
// ============================================================
$party_votes = json_decode($result['party_votes_json'] ?? '{}', true);
$total_valid = array_sum($party_votes);

// ============================================================
// HANDLE VERIFICATION ACTION
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
                    UPDATE results_ec8a 
                    SET status = 'verified', verified_by = ?, verified_at = NOW(), 
                        rejection_reason = NULL
                    WHERE id = ?
                ");
                $stmt->execute([$user_id, $result_id]);
                $success_message = "Result verified successfully.";
                logActivity($user_id, 'result_verified', "Verified EC8A result ID: $result_id", 'results_ec8a', $result_id);
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
                
            case 'flag':
                if (empty($remarks)) {
                    throw new Exception("Please provide a reason for flagging.");
                }
                $stmt = $db->prepare("
                    UPDATE results_ec8a 
                    SET status = 'flagged', verified_by = ?, verified_at = NOW(), 
                        rejection_reason = ?
                    WHERE id = ?
                ");
                $stmt->execute([$user_id, $remarks, $result_id]);
                $success_message = "Result flagged for review.";
                logActivity($user_id, 'result_flagged', "Flagged EC8A result ID: $result_id", 'results_ec8a', $result_id);
                break;
                
            default:
                throw new Exception("Invalid action.");
        }
        
        // Refresh result data
        $stmt = $db->prepare("
            SELECT 
                r.*,
                u.full_name as agent_name,
                u.user_code as agent_code,
                u.email as agent_email,
                u.phone as agent_phone,
                u.photograph_url as agent_photo,
                pu.name as pu_name,
                pu.code as pu_code,
                pu.registered_voters,
                pu.address as pu_address,
                pu.gps_lat,
                pu.gps_lng,
                w.name as ward_name,
                l.name as lga_name,
                s.name as state_name,
                verified_user.full_name as verified_by_name,
                verified_user.user_code as verified_by_code
            FROM results_ec8a r
            JOIN users u ON r.agent_id = u.id
            JOIN polling_units pu ON r.pu_id = pu.id
            JOIN wards w ON r.ward_id = w.id
            JOIN lgas l ON r.lga_id = l.id
            JOIN states s ON r.state_id = s.id
            LEFT JOIN users verified_user ON r.verified_by = verified_user.id
            WHERE r.id = ? AND r.tenant_id = ?
        ");
        $stmt->execute([$result_id, $tenant_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
        error_log("Result action error: " . $e->getMessage());
    }
}

$page_title = 'Result Details';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.result-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.result-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.result-header h2 i {
    color: var(--primary);
}

.status-badge {
    font-size: 0.7rem;
    padding: 4px 14px;
    border-radius: 20px;
    font-weight: 500;
    display: inline-block;
}
.status-badge.pending { background: #FEF3C7; color: #92400E; }
.status-badge.verified { background: #D1FAE5; color: #065F46; }
.status-badge.rejected { background: #FEE2E2; color: #991B1B; }
.status-badge.flagged { background: #FEF3C7; color: #92400E; }

.detail-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 16px;
}
.detail-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px;
    margin-bottom: 16px;
}
.detail-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--gray-200);
}
.detail-card .card-header h3 {
    font-size: 0.9rem;
    font-weight: 600;
    margin: 0;
}
.detail-card .card-header .badge {
    font-size: 0.6rem;
    padding: 2px 10px;
    border-radius: 20px;
    font-weight: 500;
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

.actions-section {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 12px;
}
.actions-section .btn-sm {
    padding: 6px 16px;
    font-size: 0.8rem;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.actions-section .btn-sm.verify { background: #D1FAE5; color: #065F46; }
.actions-section .btn-sm.reject { background: #FEE2E2; color: #991B1B; }
.actions-section .btn-sm.flag { background: #FEF3C7; color: #92400E; }
.actions-section .btn-sm.back { background: #E5E7EB; color: #374151; }

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
}
.modal-content .form-actions {
    display: flex;
    gap: 8px;
    margin-top: 16px;
}

@media (max-width: 1024px) {
    .detail-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
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
    .actions-section {
        flex-direction: column;
    }
    .actions-section .btn-sm {
        width: 100%;
        justify-content: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="result-header">
            <div>
                <h2><i class="fas fa-file-alt"></i> Result Details</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($result['pu_name'] ?? 'Unknown PU'); ?> • 
                    <?php echo htmlspecialchars($result['ward_name'] ?? ''); ?>
                </p>
            </div>
            <div>
                <span class="status-badge <?php echo $result['status'] ?? 'pending'; ?>">
                    <i class="fas fa-circle" style="font-size:0.4rem;"></i>
                    <?php echo ucfirst($result['status'] ?? 'Pending'); ?>
                </span>
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

        <div class="detail-grid">
            <!-- Left Column -->
            <div>
                <!-- Result Information -->
                <div class="detail-card">
                    <div class="card-header">
                        <h3><i class="fas fa-info-circle"></i> Result Information</h3>
                    </div>
                    <div class="info-row">
                        <span class="label">Result ID</span>
                        <span class="value">#<?php echo $result['id']; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Polling Unit</span>
                        <span class="value">
                            <?php echo htmlspecialchars($result['pu_name']); ?>
                            (<?php echo htmlspecialchars($result['pu_code']); ?>)
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="label">Ward</span>
                        <span class="value"><?php echo htmlspecialchars($result['ward_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">LGA</span>
                        <span class="value"><?php echo htmlspecialchars($result['lga_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">State</span>
                        <span class="value"><?php echo htmlspecialchars($result['state_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Registered Voters</span>
                        <span class="value"><?php echo number_format($result['registered_voters'] ?? 0); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Submitted By</span>
                        <span class="value">
                            <?php echo htmlspecialchars($result['agent_name']); ?>
                            (<?php echo htmlspecialchars($result['agent_code']); ?>)
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="label">Submitted At</span>
                        <span class="value"><?php echo date('M d, Y H:i:s', strtotime($result['created_at'])); ?></span>
                    </div>
                    <?php if ($result['verified_by_name']): ?>
                        <div class="info-row">
                            <span class="label">Verified By</span>
                            <span class="value">
                                <?php echo htmlspecialchars($result['verified_by_name']); ?>
                                (<?php echo htmlspecialchars($result['verified_by_code']); ?>)
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="label">Verified At</span>
                            <span class="value"><?php echo date('M d, Y H:i:s', strtotime($result['verified_at'])); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($result['rejection_reason'])): ?>
                        <div class="info-row" style="color:#EF4444;">
                            <span class="label">Remarks</span>
                            <span class="value"><?php echo htmlspecialchars($result['rejection_reason']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Party Votes -->
                <div class="detail-card">
                    <div class="card-header">
                        <h3><i class="fas fa-vote-yea"></i> Party Votes</h3>
                        <span class="badge" style="background:var(--gray-100);">
                            <?php echo count($party_votes); ?> parties
                        </span>
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
                                <span class="party">Total Votes Cast</span>
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

            <!-- Right Column -->
            <div>
                <!-- Agent Information -->
                <div class="detail-card">
                    <div class="card-header">
                        <h3><i class="fas fa-user"></i> Agent Information</h3>
                    </div>
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                        <div style="width:50px;height:50px;border-radius:50%;background:var(--gray-200);display:flex;align-items:center;justify-content:center;font-size:1.2rem;font-weight:700;color:var(--gray-600);overflow:hidden;">
                            <?php if (!empty($result['agent_photo'])): ?>
                                <img src="<?php echo htmlspecialchars($result['agent_photo']); ?>" style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?>
                                <?php echo strtoupper(substr($result['agent_name'] ?? 'U', 0, 2)); ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div style="font-weight:600;font-size:0.95rem;"><?php echo htmlspecialchars($result['agent_name']); ?></div>
                            <div style="font-size:0.75rem;color:var(--gray-500);"><?php echo htmlspecialchars($result['agent_code']); ?></div>
                        </div>
                    </div>
                    <div class="info-row">
                        <span class="label">Email</span>
                        <span class="value"><?php echo htmlspecialchars($result['agent_email'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Phone</span>
                        <span class="value"><?php echo htmlspecialchars($result['agent_phone'] ?? 'N/A'); ?></span>
                    </div>
                    <div style="margin-top:10px;">
                        <a href="agent-profile.php?id=<?php echo $result['agent_id']; ?>" class="btn-secondary-sm" style="width:100%;text-align:center;">
                            <i class="fas fa-user"></i> View Agent Profile
                        </a>
                    </div>
                </div>

                <!-- Actions -->
                <?php if ($result['status'] === 'pending'): ?>
                    <div class="detail-card">
                        <div class="card-header">
                            <h3><i class="fas fa-tasks"></i> Actions</h3>
                        </div>
                        <div class="actions-section">
                            <button onclick="openActionModal('verify')" class="btn-sm verify">
                                <i class="fas fa-check"></i> Verify
                            </button>
                            <button onclick="openActionModal('reject')" class="btn-sm reject">
                                <i class="fas fa-times"></i> Reject
                            </button>
                            <button onclick="openActionModal('flag')" class="btn-sm flag">
                                <i class="fas fa-flag"></i> Flag
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Navigation -->
                <div class="detail-card">
                    <div class="card-header">
                        <h3><i class="fas fa-arrow-left"></i> Navigation</h3>
                    </div>
                    <div class="actions-section">
                        <a href="verify-results.php" class="btn-sm back" style="width:100%;justify-content:center;">
                            <i class="fas fa-arrow-left"></i> Back to Verification
                        </a>
                        <?php if ($result['status'] !== 'pending'): ?>
                            <a href="approval-history.php" class="btn-sm back" style="width:100%;justify-content:center;background:#EFF6FF;color:#3B82F6;">
                                <i class="fas fa-history"></i> View Approval History
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Action Modal -->
<div class="modal-overlay" id="actionModal">
    <div class="modal-content">
        <h3 id="modalTitle">Action on Result</h3>
        <form method="POST" action="">
            <input type="hidden" name="action" id="modalAction">
            
            <div class="form-group">
                <label for="modalRemarks">Remarks <span id="remarksRequired" style="color:#EF4444;">*</span></label>
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
        'verify': 'Verify Result',
        'reject': 'Reject Result',
        'flag': 'Flag Result for Review'
    };
    document.getElementById('modalTitle').textContent = titles[action] || 'Action on Result';
    
    const submitLabels = {
        'verify': 'Verify',
        'reject': 'Reject',
        'flag': 'Flag'
    };
    document.getElementById('modalSubmitBtn').innerHTML = `<i class="fas fa-check"></i> ${submitLabels[action] || 'Confirm'}`;
    
    // Show required for reject and flag
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