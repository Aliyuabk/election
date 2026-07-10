<?php
// ============================================================
// STATE COORDINATOR - VERIFY EC8A
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

// Get state name
$state_name = 'Unknown State';
if (!empty($state_id)) {
    $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
    $stmt->execute([$state_id]);
    $state = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_name = $state['name'] ?? 'Unknown State';
}

// Fetch result details
$result = null;
if ($result_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT 
                r.*,
                pu.name as pu_name,
                pu.code as pu_code,
                pu.gps_lat,
                pu.gps_lng,
                w.name as ward_name,
                l.name as lga_name,
                l.id as lga_id,
                s.name as state_name,
                u.first_name as agent_first_name,
                u.last_name as agent_last_name,
                u.email as agent_email,
                u.phone as agent_phone,
                e.name as election_name,
                e.type as election_type
            FROM results_ec8a r
            JOIN polling_units pu ON r.pu_id = pu.id
            JOIN wards w ON pu.ward_id = w.id
            JOIN lgas l ON w.lga_id = l.id
            JOIN states s ON l.state_id = s.id
            LEFT JOIN users u ON r.agent_id = u.id
            LEFT JOIN elections e ON r.election_id = e.id
            WHERE r.id = ? AND r.tenant_id = ?
        ");
        $stmt->execute([$result_id, $tenant_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching result: " . $e->getMessage());
    }
}

if (!$result) {
    header('Location: result-verification.php');
    exit();
}

// Get party votes
$party_votes = [];
if (!empty($result['party_votes_json'])) {
    $party_votes = json_decode($result['party_votes_json'], true) ?: [];
}

// Handle verification
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');
    
    if ($action === 'verify') {
        try {
            $stmt = $db->prepare("
                UPDATE results_ec8a 
                SET status = 'verified', 
                    verified_by = ?, 
                    verified_at = NOW(),
                    remarks = CONCAT(COALESCE(remarks, ''), '\n', ?)
                WHERE id = ?
            ");
            $stmt->execute([$user_id, 'Verified by State Coordinator on ' . date('Y-m-d H:i:s'), $result_id]);
            
            logActivity($user_id, 'ec8a_verified', 
                "Verified EC8A result ID: $result_id for PU: {$result['pu_name']}",
                'results_ec8a', $result_id
            );
            
            $message = 'EC8A result verified successfully!';
        } catch (Exception $e) {
            $error = 'Failed to verify result: ' . $e->getMessage();
        }
    } elseif ($action === 'reject') {
        if (empty($rejection_reason)) {
            $error = 'Please provide a reason for rejection.';
        } else {
            try {
                $stmt = $db->prepare("
                    UPDATE results_ec8a 
                    SET status = 'rejected', 
                        rejection_reason = ?,
                        verified_by = ?, 
                        verified_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$rejection_reason, $user_id, $result_id]);
                
                logActivity($user_id, 'ec8a_rejected', 
                    "Rejected EC8A result ID: $result_id for PU: {$result['pu_name']} - Reason: $rejection_reason",
                    'results_ec8a', $result_id
                );
                
                $message = 'EC8A result rejected successfully.';
            } catch (Exception $e) {
                $error = 'Failed to reject result: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'flag') {
        try {
            $stmt = $db->prepare("
                UPDATE results_ec8a 
                SET status = 'flagged', 
                    remarks = CONCAT(COALESCE(remarks, ''), '\n', 'Flagged: ', ?),
                    verified_by = ?, 
                    verified_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$remarks, $user_id, $result_id]);
            
            logActivity($user_id, 'ec8a_flagged', 
                "Flagged EC8A result ID: $result_id for PU: {$result['pu_name']} - Reason: $remarks",
                'results_ec8a', $result_id
            );
            
            $message = 'EC8A result flagged for review.';
        } catch (Exception $e) {
            $error = 'Failed to flag result: ' . $e->getMessage();
        }
    }
}

$page_title = 'Verify EC8A';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.result-container {
    max-width: 900px;
    margin: 0 auto;
}

.result-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 28px;
    margin-bottom: 16px;
}

.result-card .card-title {
    font-size: 0.85rem;
    font-weight: 600;
    margin: 0 0 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-700);
}

.result-card .card-title i {
    color: var(--primary);
    margin-right: 6px;
}

.detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.detail-item {
    font-size: 0.8rem;
}

.detail-item .label {
    color: var(--gray-500);
    font-size: 0.65rem;
    display: block;
}

.detail-item .value {
    font-weight: 500;
    color: var(--gray-800);
}

.party-votes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 8px;
}

.party-vote-item {
    background: var(--gray-50);
    border-radius: 8px;
    padding: 8px 12px;
    text-align: center;
}

.party-vote-item .party {
    font-weight: 600;
    font-size: 0.75rem;
    color: var(--gray-700);
}

.party-vote-item .votes {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--primary);
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
.status-badge.verified { background: #EFF6FF; color: #1E40AF; }
.status-badge.verified .dot { background: #3B82F6; }
.status-badge.approved { background: #ECFDF5; color: #065F46; }
.status-badge.approved .dot { background: #10B981; }
.status-badge.rejected { background: #FEF2F2; color: #991B1B; }
.status-badge.rejected .dot { background: #EF4444; }
.status-badge.flagged { background: #F5F3FF; color: #5B21B6; }
.status-badge.flagged .dot { background: #8B5CF6; }

.action-form {
    margin-top: 12px;
}

.action-form .form-group {
    margin-bottom: 12px;
}

.action-form .form-group label {
    display: block;
    font-weight: 600;
    font-size: 0.8rem;
    color: var(--gray-700);
    margin-bottom: 4px;
}

.action-form .form-group textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    resize: vertical;
    min-height: 80px;
    transition: var(--transition);
}

.action-form .form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.action-buttons button {
    padding: 10px 24px;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.82rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.action-buttons .btn-verify {
    background: #3B82F6;
    color: white;
}

.action-buttons .btn-verify:hover {
    background: #2563EB;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.action-buttons .btn-approve {
    background: #10B981;
    color: white;
}

.action-buttons .btn-approve:hover {
    background: #059669;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.action-buttons .btn-reject {
    background: #EF4444;
    color: white;
}

.action-buttons .btn-reject:hover {
    background: #DC2626;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.action-buttons .btn-flag {
    background: #8B5CF6;
    color: white;
}

.action-buttons .btn-flag:hover {
    background: #7C3AED;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
}

.action-buttons .btn-back {
    background: var(--gray-100);
    color: var(--gray-700);
    text-decoration: none;
    padding: 10px 24px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.82rem;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.action-buttons .btn-back:hover {
    background: var(--gray-200);
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

@media (max-width: 768px) {
    .result-card {
        padding: 16px 18px;
    }
    .detail-grid {
        grid-template-columns: 1fr;
    }
    .party-votes-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .action-buttons {
        flex-direction: column;
    }
    .action-buttons button,
    .action-buttons .btn-back {
        width: 100%;
        justify-content: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="result-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-file-alt"></i> Verify EC8A</h1>
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
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Result Information -->
            <div class="result-card">
                <div class="card-title"><i class="fas fa-info-circle"></i> Result Information</div>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="label">Polling Unit</span>
                        <span class="value"><?php echo htmlspecialchars($result['pu_name']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="label">PU Code</span>
                        <span class="value"><?php echo htmlspecialchars($result['pu_code']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="label">LGA</span>
                        <span class="value"><?php echo htmlspecialchars($result['lga_name']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Ward</span>
                        <span class="value"><?php echo htmlspecialchars($result['ward_name']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Election</span>
                        <span class="value"><?php echo htmlspecialchars($result['election_name'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Election Type</span>
                        <span class="value"><?php echo ucfirst($result['election_type'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Submitted By</span>
                        <span class="value"><?php echo htmlspecialchars($result['agent_first_name'] ?? '') . ' ' . htmlspecialchars($result['agent_last_name'] ?? ''); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Submitted At</span>
                        <span class="value"><?php echo date('M j, Y g:i A', strtotime($result['created_at'])); ?></span>
                    </div>
                </div>
            </div>

            <!-- Vote Details -->
            <div class="result-card">
                <div class="card-title"><i class="fas fa-vote-yea"></i> Vote Details</div>
                <div class="party-votes-grid">
                    <?php foreach ($party_votes as $party => $votes): ?>
                        <div class="party-vote-item">
                            <div class="party"><?php echo htmlspecialchars($party); ?></div>
                            <div class="votes"><?php echo number_format($votes); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin-top:12px;padding-top:12px;border-top:1px solid var(--gray-200);">
                    <div class="detail-item">
                        <span class="label">Valid Votes</span>
                        <span class="value"><?php echo number_format($result['valid_votes']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Rejected Votes</span>
                        <span class="value"><?php echo number_format($result['rejected_votes']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Total Votes</span>
                        <span class="value"><?php echo number_format($result['total_votes_cast']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Registered Voters</span>
                        <span class="value"><?php echo number_format($result['registered_voters']); ?></span>
                    </div>
                </div>

                <?php if ($result['mismatch_alert']): ?>
                    <div style="margin-top:12px;background:#FEF2F2;border:1px solid #FECACA;border-radius:8px;padding:10px 14px;color:#991B1B;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Mismatch Alert:</strong> The total votes don't match the sum of party votes.
                        <?php if (!empty($result['mismatch_details_json'])): ?>
                            <div style="font-size:0.75rem;margin-top:4px;">
                                <?php 
                                    $details = json_decode($result['mismatch_details_json'], true);
                                    if ($details) {
                                        echo htmlspecialchars($details['message'] ?? 'Details not available');
                                    }
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Remarks -->
            <?php if (!empty($result['remarks'])): ?>
                <div class="result-card">
                    <div class="card-title"><i class="fas fa-comment"></i> Remarks</div>
                    <p style="font-size:0.82rem;color:var(--gray-700);white-space:pre-wrap;"><?php echo htmlspecialchars($result['remarks']); ?></p>
                </div>
            <?php endif; ?>

            <!-- Actions -->
            <?php if ($result['status'] === 'pending' || $result['status'] === 'verified'): ?>
                <div class="result-card">
                    <div class="card-title"><i class="fas fa-tasks"></i> Verification Actions</div>
                    
                    <form method="POST" action="" class="action-form">
                        <?php if ($result['status'] === 'pending'): ?>
                            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
                                <button type="submit" name="action" value="verify" class="btn-verify">
                                    <i class="fas fa-check"></i> Verify Result
                                </button>
                                <button type="button" class="btn-flag" onclick="toggleRejection()">
                                    <i class="fas fa-flag"></i> Flag for Review
                                </button>
                                <button type="button" class="btn-reject" onclick="toggleRejection()">
                                    <i class="fas fa-times"></i> Reject Result
                                </button>
                            </div>
                            
                            <div id="rejectionSection" style="display:none;">
                                <div class="form-group">
                                    <label for="rejection_reason">Reason <span class="required">*</span></label>
                                    <textarea name="rejection_reason" id="rejection_reason" placeholder="Please provide a detailed reason..."></textarea>
                                </div>
                                <div style="display:flex;gap:10px;">
                                    <button type="submit" name="action" value="reject" class="btn-reject">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                    <button type="submit" name="action" value="flag" class="btn-flag">
                                        <i class="fas fa-flag"></i> Flag
                                    </button>
                                    <button type="button" onclick="toggleRejection()" class="btn-back">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($result['status'] === 'verified'): ?>
                            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                                <button type="submit" name="action" value="approve" class="btn-approve">
                                    <i class="fas fa-check-double"></i> Approve Result
                                </button>
                                <button type="button" class="btn-flag" onclick="toggleRejection()">
                                    <i class="fas fa-flag"></i> Flag for Review
                                </button>
                                <button type="button" class="btn-reject" onclick="toggleRejection()">
                                    <i class="fas fa-times"></i> Reject Result
                                </button>
                            </div>
                            
                            <div id="rejectionSection" style="display:none;margin-top:12px;">
                                <div class="form-group">
                                    <label for="rejection_reason">Reason <span class="required">*</span></label>
                                    <textarea name="rejection_reason" id="rejection_reason" placeholder="Please provide a detailed reason..."></textarea>
                                </div>
                                <div style="display:flex;gap:10px;">
                                    <button type="submit" name="action" value="reject" class="btn-reject">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                    <button type="submit" name="action" value="flag" class="btn-flag">
                                        <i class="fas fa-flag"></i> Flag
                                    </button>
                                    <button type="button" onclick="toggleRejection()" class="btn-back">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Back Button -->
            <div style="margin-top:12px;">
                <a href="result-verification.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Verification
                </a>
            </div>
        </div>
    </div>
</main>

<script>
function toggleRejection() {
    var section = document.getElementById('rejectionSection');
    if (section) {
        section.style.display = section.style.display === 'none' ? 'block' : 'none';
    }
}

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