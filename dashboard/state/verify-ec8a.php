<?php
// ============================================================
// STATE COORDINATOR - VERIFY EC8A
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

// Only state coordinator can access
if (SessionManager::get('role_level') !== 'state') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'State Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$state_id = SessionManager::get('state_id');

// If state_id is not set in session, try to get it from user record
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

// ============================================================
// GENERATE CSRF TOKEN
// ============================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ============================================================
// GET RESULT ID
// ============================================================
$result_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($result_id <= 0) {
    header('Location: result-verification.php');
    exit();
}

// ============================================================
// FETCH RESULT DETAILS
// ============================================================
$result = null;
$error = '';
$success = '';

try {
    $stmt = $db->prepare("
        SELECT 
            r.*,
            u.first_name as agent_first,
            u.last_name as agent_last,
            u.phone as agent_phone,
            u.email as agent_email,
            pu.name as pu_name,
            pu.code as pu_code,
            pu.address as pu_address,
            pu.registered_voters,
            w.name as ward_name,
            l.name as lga_name,
            s.name as state_name,
            e.name as election_name,
            e.type as election_type,
            e.election_date
        FROM results_ec8a r
        JOIN users u ON r.agent_id = u.id
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        JOIN states s ON l.state_id = s.id
        JOIN elections e ON r.election_id = e.id
        WHERE r.id = ? AND r.tenant_id = ?
    ");
    $stmt->execute([$result_id, $tenant_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        header('Location: result-verification.php');
        exit();
    }
    
    // Decode party votes
    $party_votes = json_decode($result['party_votes_json'], true);
    if (!is_array($party_votes)) {
        $party_votes = [];
    }
    
    // Calculate totals
    $total_party_votes = array_sum($party_votes);
    $total_votes_cast = $result['valid_votes'] + $result['rejected_votes'];
    
} catch (Exception $e) {
    error_log("Error fetching result: " . $e->getMessage());
    $error = "Error loading result details.";
}

// ============================================================
// HANDLE VERIFICATION ACTION
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Security validation failed. Please try again.';
    } else {
        $action = $_POST['action'];
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
        
        try {
            if ($action === 'verify') {
                $stmt = $db->prepare("
                    UPDATE results_ec8a 
                    SET status = 'verified', 
                        verified_by = ?, 
                        verified_at = NOW() 
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmt->execute([$user_id, $result_id, $tenant_id]);
                
                logActivity($user_id, 'result_verified', "Verified EC8A result ID: $result_id");
                $success = "Result has been verified successfully!";
                
            } elseif ($action === 'reject') {
                if (empty($reason)) {
                    $error = "Please provide a reason for rejection.";
                } else {
                    $stmt = $db->prepare("
                        UPDATE results_ec8a 
                        SET status = 'rejected', 
                            rejection_reason = ?,
                            verified_by = ?, 
                            verified_at = NOW() 
                        WHERE id = ? AND tenant_id = ?
                    ");
                    $stmt->execute([$reason, $user_id, $result_id, $tenant_id]);
                    
                    logActivity($user_id, 'result_rejected', "Rejected EC8A result ID: $result_id. Reason: $reason");
                    $success = "Result has been rejected.";
                }
                
            } elseif ($action === 'flag') {
                if (empty($reason)) {
                    $error = "Please provide a reason for flagging.";
                } else {
                    $stmt = $db->prepare("
                        UPDATE results_ec8a 
                        SET status = 'flagged',
                            rejection_reason = ?,
                            verified_by = ?, 
                            verified_at = NOW() 
                        WHERE id = ? AND tenant_id = ?
                    ");
                    $stmt->execute([$reason, $user_id, $result_id, $tenant_id]);
                    
                    logActivity($user_id, 'result_flagged', "Flagged EC8A result ID: $result_id. Reason: $reason");
                    $success = "Result has been flagged for review.";
                }
            }
            
            // Refresh result data
            if (empty($error)) {
                $stmt = $db->prepare("
                    SELECT 
                        r.*,
                        u.first_name as agent_first,
                        u.last_name as agent_last,
                        u.phone as agent_phone,
                        u.email as agent_email,
                        pu.name as pu_name,
                        pu.code as pu_code,
                        pu.address as pu_address,
                        pu.registered_voters,
                        w.name as ward_name,
                        l.name as lga_name,
                        s.name as state_name,
                        e.name as election_name,
                        e.type as election_type,
                        e.election_date
                    FROM results_ec8a r
                    JOIN users u ON r.agent_id = u.id
                    JOIN polling_units pu ON r.pu_id = pu.id
                    JOIN wards w ON pu.ward_id = w.id
                    JOIN lgas l ON w.lga_id = l.id
                    JOIN states s ON l.state_id = s.id
                    JOIN elections e ON r.election_id = e.id
                    WHERE r.id = ? AND r.tenant_id = ?
                ");
                $stmt->execute([$result_id, $tenant_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    $party_votes = json_decode($result['party_votes_json'], true);
                    if (!is_array($party_votes)) {
                        $party_votes = [];
                    }
                    $total_party_votes = array_sum($party_votes);
                    $total_votes_cast = $result['valid_votes'] + $result['rejected_votes'];
                }
            }
            
        } catch (Exception $e) {
            error_log("Error updating result: " . $e->getMessage());
            $error = "Error updating result: " . $e->getMessage();
        }
    }
}

include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.page-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.page-header h2 small {
    font-size: 0.8rem;
    font-weight: 400;
    color: var(--gray-500);
    display: block;
    margin-top: 2px;
}

.btn-secondary-sm {
    padding: 8px 20px;
    background: var(--gray-100);
    color: var(--gray-700);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}
.btn-secondary-sm:hover {
    background: var(--gray-200);
}

.result-container {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow);
}

.result-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}
.result-header .title {
    font-size: 1.1rem;
    font-weight: 700;
}
.result-header .title i {
    color: var(--primary);
    margin-right: 8px;
}

.badge-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
}
.badge-status .dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
}
.badge-status.pending { background: #FFFBEB; color: #92400E; }
.badge-status.pending .dot { background: #F59E0B; }
.badge-status.verified { background: #ECFDF5; color: #065F46; }
.badge-status.verified .dot { background: #10B981; }
.badge-status.rejected { background: #FEF2F2; color: #991B1B; }
.badge-status.rejected .dot { background: #EF4444; }
.badge-status.flagged { background: #F5F3FF; color: #5B21B6; }
.badge-status.flagged .dot { background: #8B5CF6; }
.badge-status.approved { background: #ECFDF5; color: #065F46; }
.badge-status.approved .dot { background: #10B981; }

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 16px 24px;
    padding: 20px 24px;
    background: var(--gray-50);
}
.info-grid .info-item {
    display: flex;
    flex-direction: column;
}
.info-grid .info-item .label {
    font-size: 0.7rem;
    color: var(--gray-400);
    font-weight: 500;
}
.info-grid .info-item .value {
    font-size: 0.9rem;
    color: var(--gray-800);
    font-weight: 500;
}

.votes-section {
    padding: 20px 24px;
    border-top: 1px solid var(--gray-200);
}
.votes-section .section-title {
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 12px;
}
.votes-section .section-title i {
    color: var(--primary);
    margin-right: 6px;
}

.party-votes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 8px;
}
.party-vote-item {
    display: flex;
    justify-content: space-between;
    padding: 6px 12px;
    background: var(--gray-50);
    border-radius: 6px;
    font-size: 0.85rem;
}
.party-vote-item .party-name {
    font-weight: 500;
}
.party-vote-item .vote-count {
    font-weight: 600;
    color: var(--gray-800);
}

.totals-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid var(--gray-200);
}
.total-item {
    text-align: center;
}
.total-item .number {
    font-size: 1.4rem;
    font-weight: 700;
}
.total-item .label {
    font-size: 0.7rem;
    color: var(--gray-500);
}
.total-item .number.valid { color: #10B981; }
.total-item .number.rejected { color: #EF4444; }
.total-item .number.total { color: #3B82F6; }

.actions-section {
    padding: 20px 24px;
    border-top: 1px solid var(--gray-200);
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}
.actions-section .btn {
    padding: 10px 24px;
    border-radius: 10px;
    border: none;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.actions-section .btn-primary {
    background: #10B981;
    color: white;
}
.actions-section .btn-primary:hover {
    background: #059669;
}
.actions-section .btn-danger {
    background: #EF4444;
    color: white;
}
.actions-section .btn-danger:hover {
    background: #DC2626;
}
.actions-section .btn-warning {
    background: #F59E0B;
    color: white;
}
.actions-section .btn-warning:hover {
    background: #D97706;
}
.actions-section .btn-secondary {
    background: var(--gray-100);
    color: var(--gray-600);
}
.actions-section .btn-secondary:hover {
    background: var(--gray-200);
}

.error-message {
    background: #FEF2F2;
    color: #DC2626;
    padding: 14px 18px;
    border-radius: 10px;
    font-size: 0.85rem;
    margin-bottom: 16px;
    border: 1px solid #FECACA;
    display: flex;
    align-items: flex-start;
    gap: 12px;
}
.error-message i {
    margin-top: 2px;
    font-size: 1.1rem;
}
.success-message {
    background: #ECFDF5;
    color: #065F46;
    padding: 14px 18px;
    border-radius: 10px;
    font-size: 0.85rem;
    margin-bottom: 16px;
    border: 1px solid #A7F3D0;
    display: flex;
    align-items: flex-start;
    gap: 12px;
}
.success-message i {
    margin-top: 2px;
    font-size: 1.1rem;
}

.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.4);
    z-index: 300;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.modal-overlay.active { display: flex; }
.modal {
    background: white;
    border-radius: var(--radius);
    max-width: 500px;
    width: 100%;
    padding: 28px 32px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.15);
    animation: modalIn 0.25s ease;
}
@keyframes modalIn {
    from { transform: scale(0.95) translateY(10px); opacity: 0; }
    to { transform: scale(1) translateY(0); opacity: 1; }
}
.modal .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}
.modal .modal-header h3 {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--gray-800);
}
.modal .modal-header .close-btn {
    background: none;
    border: none;
    font-size: 1.4rem;
    color: var(--gray-400);
    cursor: pointer;
    transition: var(--transition);
    padding: 0 4px;
}
.modal .modal-header .close-btn:hover {
    color: var(--gray-600);
}
.modal .modal-body {
    margin-bottom: 20px;
}
.modal .modal-body textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-family: 'Inter', sans-serif;
    font-size: 0.85rem;
    min-height: 80px;
    resize: vertical;
}
.modal .modal-body textarea:focus {
    outline: none;
    border-color: var(--primary);
}
.modal .modal-footer {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}
.modal .modal-footer .btn {
    padding: 8px 20px;
    border-radius: 8px;
    border: none;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}
.modal .modal-footer .btn-secondary {
    background: var(--gray-100);
    color: var(--gray-600);
}
.modal .modal-footer .btn-secondary:hover {
    background: var(--gray-200);
}
.modal .modal-footer .btn-danger {
    background: #EF4444;
    color: white;
}
.modal .modal-footer .btn-danger:hover {
    background: #DC2626;
}
.modal .modal-footer .btn-warning {
    background: #F59E0B;
    color: white;
}
.modal .modal-footer .btn-warning:hover {
    background: #D97706;
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .result-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .info-grid {
        grid-template-columns: 1fr;
    }
    .actions-section {
        flex-direction: column;
    }
    .actions-section .btn {
        width: 100%;
        justify-content: center;
    }
    .party-votes-grid {
        grid-template-columns: 1fr 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-file-alt" style="color:var(--primary);margin-right:8px;"></i>
                    Verify EC8A Form
                    <small><?php echo htmlspecialchars($result['pu_name'] ?? 'Polling Unit'); ?> - Result Verification</small>
                </h2>
            </div>
            <div>
                <a href="result-verification.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Results
                </a>
            </div>
        </div>

        <!-- Messages -->
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <div><?php echo $success; ?></div>
            </div>
        <?php endif; ?>

        <?php if ($result): ?>
            <!-- Result Container -->
            <div class="result-container">
                <!-- Header -->
                <div class="result-header">
                    <div>
                        <div class="title">
                            <i class="fas fa-file-alt"></i> EC8A Result Sheet
                        </div>
                        <div style="font-size:0.85rem;color:var(--gray-500);margin-top:2px;">
                            <?php echo htmlspecialchars($result['election_name']); ?> - <?php echo date('F j, Y', strtotime($result['election_date'])); ?>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <span class="badge-status <?php echo $result['status']; ?>">
                            <span class="dot"></span>
                            <?php echo ucfirst($result['status']); ?>
                        </span>
                        <?php if ($result['status'] === 'rejected' && !empty($result['rejection_reason'])): ?>
                            <span style="font-size:0.7rem;color:var(--gray-400);">
                                Reason: <?php echo htmlspecialchars($result['rejection_reason']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Info Grid -->
                <div class="info-grid">
                    <div class="info-item">
                        <span class="label">Polling Unit</span>
                        <span class="value"><?php echo htmlspecialchars($result['pu_name']); ?></span>
                        <span style="font-size:0.75rem;color:var(--gray-400);">Code: <?php echo htmlspecialchars($result['pu_code']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Location</span>
                        <span class="value"><?php echo htmlspecialchars($result['lga_name']); ?></span>
                        <span style="font-size:0.75rem;color:var(--gray-400);">Ward: <?php echo htmlspecialchars($result['ward_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Agent</span>
                        <span class="value"><?php echo htmlspecialchars($result['agent_first'] . ' ' . $result['agent_last']); ?></span>
                        <span style="font-size:0.75rem;color:var(--gray-400);"><?php echo htmlspecialchars($result['agent_phone'] ?? ''); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Submitted</span>
                        <span class="value"><?php echo date('F j, Y g:i A', strtotime($result['created_at'])); ?></span>
                        <?php if ($result['verified_at']): ?>
                            <span style="font-size:0.75rem;color:var(--gray-400);">
                                Verified: <?php echo date('F j, Y g:i A', strtotime($result['verified_at'])); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="info-item">
                        <span class="label">Registered Voters</span>
                        <span class="value"><?php echo number_format($result['registered_voters'] ?? 0); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Accredited Voters</span>
                        <span class="value"><?php echo number_format($result['accredited_voters'] ?? 0); ?></span>
                    </div>
                </div>

                <!-- Votes Section -->
                <div class="votes-section">
                    <div class="section-title">
                        <i class="fas fa-vote-yea"></i> Party Votes
                    </div>
                    
                    <?php if (count($party_votes) > 0): ?>
                        <div class="party-votes-grid">
                            <?php foreach ($party_votes as $party => $votes): ?>
                                <div class="party-vote-item">
                                    <span class="party-name"><?php echo htmlspecialchars($party); ?></span>
                                    <span class="vote-count"><?php echo number_format($votes); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align:center;padding:20px;color:var(--gray-400);">
                            No party votes recorded.
                        </div>
                    <?php endif; ?>

                    <!-- Totals -->
                    <div class="totals-grid">
                        <div class="total-item">
                            <div class="number valid"><?php echo number_format($result['valid_votes'] ?? 0); ?></div>
                            <div class="label">Valid Votes</div>
                        </div>
                        <div class="total-item">
                            <div class="number rejected"><?php echo number_format($result['rejected_votes'] ?? 0); ?></div>
                            <div class="label">Rejected Votes</div>
                        </div>
                        <div class="total-item">
                            <div class="number total"><?php echo number_format($total_votes_cast ?? 0); ?></div>
                            <div class="label">Total Votes Cast</div>
                        </div>
                        <?php if ($result['total_votes_cast'] ?? 0): ?>
                            <div class="total-item">
                                <div class="number" style="color:#8B5CF6;"><?php echo number_format($result['total_votes_cast'] ?? 0); ?></div>
                                <div class="label">Declared Total</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Actions -->
                <?php if ($result['status'] === 'pending'): ?>
                    <div class="actions-section">
                        <form method="POST" action="" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="action" value="verify">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check"></i> Verify Result
                            </button>
                        </form>
                        
                        <button onclick="openRejectModal()" class="btn btn-danger">
                            <i class="fas fa-times"></i> Reject
                        </button>
                        
                        <button onclick="openFlagModal()" class="btn btn-warning">
                            <i class="fas fa-flag"></i> Flag for Review
                        </button>
                    </div>
                <?php elseif ($result['status'] === 'verified'): ?>
                    <div class="actions-section">
                        <button onclick="openFlagModal()" class="btn btn-warning">
                            <i class="fas fa-flag"></i> Flag for Review
                        </button>
                        <a href="result-verification.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                    </div>
                <?php elseif ($result['status'] === 'rejected' || $result['status'] === 'flagged'): ?>
                    <div class="actions-section">
                        <a href="result-verification.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- ============================================================
REJECT MODAL
============================================================ -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Reject Result</h3>
            <button class="close-btn" onclick="closeModal('rejectModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" value="reject">
            <div class="modal-body">
                <p style="color:var(--gray-600);margin-bottom:12px;">Please provide a reason for rejecting this result:</p>
                <textarea name="reason" id="rejectReason" placeholder="Enter reason for rejection..." required></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">Reject Result</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================
FLAG MODAL
============================================================ -->
<div class="modal-overlay" id="flagModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Flag Result for Review</h3>
            <button class="close-btn" onclick="closeModal('flagModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" value="flag">
            <div class="modal-body">
                <p style="color:var(--gray-600);margin-bottom:12px;">Please provide a reason for flagging this result:</p>
                <textarea name="reason" id="flagReason" placeholder="Enter reason for flagging..." required></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('flagModal')">Cancel</button>
                <button type="submit" class="btn btn-warning">Flag Result</button>
            </div>
        </form>
    </div>
</div>

<script>
// ============================================================
// PRELOADER
// ============================================================
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() {
            preloader.style.display = 'none';
        }, 600);
    }
});

// ============================================================
// SIDEBAR TOGGLE
// ============================================================
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

// ============================================================
// SIDEBAR DROPDOWNS
// ============================================================
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

// ============================================================
// PROFILE DROPDOWN
// ============================================================
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

// ============================================================
// MODAL FUNCTIONS
// ============================================================
function openRejectModal() {
    document.getElementById('rejectModal').classList.add('active');
}

function openFlagModal() {
    document.getElementById('flagModal').classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// Close modal on background click
document.querySelectorAll('.modal-overlay').forEach(function(modal) {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});
</script>
</body>
</html>