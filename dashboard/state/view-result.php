<?php
// ============================================================
// STATE COORDINATOR - VIEW RESULT DETAILS
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
$type = isset($_GET['type']) ? $_GET['type'] : 'ec8a';

if ($result_id <= 0) {
    header('Location: result-verification.php');
    exit();
}

// Get state name
$state_name = 'Unknown State';
if (!empty($state_id)) {
    $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
    $stmt->execute([$state_id]);
    $state = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_name = $state['name'] ?? 'Unknown State';
}

// Fetch result based on type
$result = null;
$result_type = $type;

try {
    if ($type === 'ec8a') {
        $stmt = $db->prepare("
            SELECT 
                r.*,
                pu.name as pu_name,
                pu.code as pu_code,
                pu.gps_lat,
                pu.gps_lng,
                pu.address as pu_address,
                pu.registered_voters,
                pu.accredited_voters,
                w.name as ward_name,
                w.code as ward_code,
                l.name as lga_name,
                l.id as lga_id,
                s.name as state_name,
                e.name as election_name,
                e.type as election_type,
                e.election_date,
                u.first_name as agent_first_name,
                u.last_name as agent_last_name,
                u.email as agent_email,
                u.phone as agent_phone,
                vu.first_name as verifier_first_name,
                vu.last_name as verifier_last_name
            FROM results_ec8a r
            JOIN polling_units pu ON r.pu_id = pu.id
            JOIN wards w ON pu.ward_id = w.id
            JOIN lgas l ON w.lga_id = l.id
            JOIN states s ON l.state_id = s.id
            JOIN elections e ON r.election_id = e.id
            LEFT JOIN users u ON r.agent_id = u.id
            LEFT JOIN users vu ON r.verified_by = vu.id
            WHERE r.id = ? AND r.tenant_id = ?
        ");
        $stmt->execute([$result_id, $tenant_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif ($type === 'ec8b') {
        $stmt = $db->prepare("
            SELECT 
                r.*,
                w.name as ward_name,
                w.code as ward_code,
                l.name as lga_name,
                l.id as lga_id,
                s.name as state_name,
                e.name as election_name,
                e.type as election_type,
                e.election_date,
                u.first_name as coordinator_first_name,
                u.last_name as coordinator_last_name,
                u.email as coordinator_email,
                u.phone as coordinator_phone,
                vu.first_name as verifier_first_name,
                vu.last_name as verifier_last_name
            FROM results_ec8b r
            JOIN wards w ON r.ward_id = w.id
            JOIN lgas l ON w.lga_id = l.id
            JOIN states s ON l.state_id = s.id
            JOIN elections e ON r.election_id = e.id
            LEFT JOIN users u ON r.coordinator_id = u.id
            LEFT JOIN users vu ON r.verified_by = vu.id
            WHERE r.id = ? AND r.tenant_id = ?
        ");
        $stmt->execute([$result_id, $tenant_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error fetching result: " . $e->getMessage());
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

$page_title = 'View Result';
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

.detail-item .value .status-badge {
    font-size: 0.6rem;
    padding: 2px 12px;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.6rem;
    padding: 3px 12px;
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

.party-votes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 10px;
}

.party-vote-item {
    background: var(--gray-50);
    border-radius: 8px;
    padding: 10px 14px;
    text-align: center;
    border: 1px solid var(--gray-200);
}

.party-vote-item .party {
    font-weight: 600;
    font-size: 0.75rem;
    color: var(--gray-700);
}

.party-vote-item .votes {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--primary);
}

.vote-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--gray-200);
}

.vote-summary .summary-item {
    text-align: center;
    padding: 8px;
    background: var(--gray-50);
    border-radius: 8px;
}

.vote-summary .summary-item .number {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--gray-800);
}

.vote-summary .summary-item .label {
    font-size: 0.6rem;
    color: var(--gray-500);
}

.alert-box {
    padding: 12px 16px;
    border-radius: 8px;
    margin-top: 12px;
}

.alert-box.warning {
    background: #FEF2F2;
    border: 1px solid #FECACA;
    color: #991B1B;
}

.alert-box.info {
    background: #F0F9FF;
    border: 1px solid #BAE6FD;
    color: #0369A1;
}

.alert-box i {
    margin-right: 6px;
}

.action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.action-buttons a {
    padding: 8px 20px;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 500;
    text-decoration: none;
    transition: var(--transition);
}

.action-buttons .btn-verify {
    background: #3B82F6;
    color: white;
}

.action-buttons .btn-verify:hover {
    background: #2563EB;
}

.action-buttons .btn-approve {
    background: #10B981;
    color: white;
}

.action-buttons .btn-approve:hover {
    background: #059669;
}

.action-buttons .btn-reject {
    background: #EF4444;
    color: white;
}

.action-buttons .btn-reject:hover {
    background: #DC2626;
}

.action-buttons .btn-correction {
    background: #F59E0B;
    color: white;
}

.action-buttons .btn-correction:hover {
    background: #D97706;
}

.action-buttons .btn-back {
    background: var(--gray-100);
    color: var(--gray-700);
}

.action-buttons .btn-back:hover {
    background: var(--gray-200);
}

.photo-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
    margin-top: 8px;
}

.photo-container .photo-item {
    background: var(--gray-50);
    border-radius: 8px;
    padding: 8px;
    text-align: center;
    border: 1px solid var(--gray-200);
}

.photo-container .photo-item img {
    max-width: 100%;
    max-height: 200px;
    border-radius: 4px;
}

.photo-container .photo-item .photo-label {
    font-size: 0.6rem;
    color: var(--gray-500);
    margin-top: 4px;
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
    .vote-summary {
        grid-template-columns: 1fr 1fr;
    }
    .action-buttons {
        flex-direction: column;
    }
    .action-buttons a {
        width: 100%;
        text-align: center;
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
                    <h1><i class="fas fa-eye"></i> View Result</h1>
                    <p class="subtitle">
                        <i class="fas fa-map-pin"></i> 
                        <?php if ($type === 'ec8a'): ?>
                            <?php echo htmlspecialchars($result['pu_name']); ?> - 
                        <?php else: ?>
                            <?php echo htmlspecialchars($result['ward_name']); ?> Ward - 
                        <?php endif; ?>
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

            <!-- Result Information -->
            <div class="result-card">
                <div class="card-title"><i class="fas fa-info-circle"></i> Result Information</div>
                <div class="detail-grid">
                    <?php if ($type === 'ec8a'): ?>
                        <div class="detail-item">
                            <span class="label">Polling Unit</span>
                            <span class="value"><?php echo htmlspecialchars($result['pu_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">PU Code</span>
                            <span class="value"><?php echo htmlspecialchars($result['pu_code']); ?></span>
                        </div>
                    <?php else: ?>
                        <div class="detail-item">
                            <span class="label">Ward</span>
                            <span class="value"><?php echo htmlspecialchars($result['ward_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Ward Code</span>
                            <span class="value"><?php echo htmlspecialchars($result['ward_code']); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="detail-item">
                        <span class="label">LGA</span>
                        <span class="value"><?php echo htmlspecialchars($result['lga_name']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="label">State</span>
                        <span class="value"><?php echo htmlspecialchars($result['state_name']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Election</span>
                        <span class="value"><?php echo htmlspecialchars($result['election_name']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Election Type</span>
                        <span class="value"><?php echo ucfirst(htmlspecialchars($result['election_type'] ?? 'N/A')); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Election Date</span>
                        <span class="value"><?php echo date('M j, Y', strtotime($result['election_date'])); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Submitted At</span>
                        <span class="value"><?php echo date('M j, Y g:i A', strtotime($result['created_at'])); ?></span>
                    </div>
                    <?php if ($type === 'ec8a'): ?>
                        <div class="detail-item">
                            <span class="label">Agent</span>
                            <span class="value"><?php echo htmlspecialchars($result['agent_first_name'] ?? '') . ' ' . htmlspecialchars($result['agent_last_name'] ?? ''); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Agent Contact</span>
                            <span class="value"><?php echo htmlspecialchars($result['agent_phone'] ?? 'N/A'); ?></span>
                        </div>
                    <?php else: ?>
                        <div class="detail-item">
                            <span class="label">Coordinator</span>
                            <span class="value"><?php echo htmlspecialchars($result['coordinator_first_name'] ?? '') . ' ' . htmlspecialchars($result['coordinator_last_name'] ?? ''); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Coordinator Contact</span>
                            <span class="value"><?php echo htmlspecialchars($result['coordinator_phone'] ?? 'N/A'); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($result['verified_by'])): ?>
                        <div class="detail-item">
                            <span class="label">Verified By</span>
                            <span class="value"><?php echo htmlspecialchars($result['verifier_first_name'] ?? '') . ' ' . htmlspecialchars($result['verifier_last_name'] ?? ''); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Verified At</span>
                            <span class="value"><?php echo date('M j, Y g:i A', strtotime($result['verified_at'])); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Vote Details -->
            <div class="result-card">
                <div class="card-title"><i class="fas fa-vote-yea"></i> Vote Details</div>
                
                <?php if (!empty($party_votes)): ?>
                    <div class="party-votes-grid">
                        <?php foreach ($party_votes as $party => $votes): ?>
                            <div class="party-vote-item">
                                <div class="party"><?php echo htmlspecialchars($party); ?></div>
                                <div class="votes"><?php echo number_format($votes); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="vote-summary">
                    <div class="summary-item">
                        <div class="number"><?php echo number_format($result['valid_votes']); ?></div>
                        <div class="label">Valid Votes</div>
                    </div>
                    <div class="summary-item">
                        <div class="number"><?php echo number_format($result['rejected_votes']); ?></div>
                        <div class="label">Rejected Votes</div>
                    </div>
                    <div class="summary-item">
                        <div class="number"><?php echo number_format($result['total_votes'] ?? $result['total_votes_cast'] ?? 0); ?></div>
                        <div class="label">Total Votes</div>
                    </div>
                    <?php if (!empty($result['registered_voters'])): ?>
                        <div class="summary-item">
                            <div class="number"><?php echo number_format($result['registered_voters']); ?></div>
                            <div class="label">Registered Voters</div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($result['mismatch_alert']) && $result['mismatch_alert'] == 1): ?>
                    <div class="alert-box warning">
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

            <?php if (!empty($result['rejection_reason'])): ?>
                <div class="result-card">
                    <div class="card-title"><i class="fas fa-times-circle" style="color:#EF4444;"></i> Rejection Reason</div>
                    <p style="font-size:0.82rem;color:#991B1B;"><?php echo htmlspecialchars($result['rejection_reason']); ?></p>
                </div>
            <?php endif; ?>

            <!-- Photo Evidence -->
            <?php if (!empty($result['photo_url'])): ?>
                <div class="result-card">
                    <div class="card-title"><i class="fas fa-image"></i> Photo Evidence</div>
                    <div class="photo-container">
                        <div class="photo-item">
                            <img src="<?php echo htmlspecialchars($result['photo_url']); ?>" alt="Result Photo" />
                            <div class="photo-label">Result Form Photo</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="result-card">
                <div class="card-title"><i class="fas fa-tasks"></i> Actions</div>
                <div class="action-buttons">
                    <?php if ($result['status'] === 'pending'): ?>
                        <?php if ($type === 'ec8a'): ?>
                            <a href="verify-ec8a.php?id=<?php echo $result_id; ?>" class="btn-verify">
                                <i class="fas fa-check"></i> Verify EC8A
                            </a>
                        <?php else: ?>
                            <a href="verify-ec8b.php?id=<?php echo $result_id; ?>" class="btn-verify">
                                <i class="fas fa-check"></i> Verify EC8B
                            </a>
                        <?php endif; ?>
                        <a href="reject-results.php?id=<?php echo $result_id; ?>&type=<?php echo $type; ?>" class="btn-reject">
                            <i class="fas fa-times"></i> Reject
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($result['status'] === 'verified'): ?>
                        <a href="approve-results.php?id=<?php echo $result_id; ?>" class="btn-approve">
                            <i class="fas fa-check-double"></i> Approve
                        </a>
                        <a href="reject-results.php?id=<?php echo $result_id; ?>&type=<?php echo $type; ?>" class="btn-reject">
                            <i class="fas fa-times"></i> Reject
                        </a>
                        <a href="request-correction.php?id=<?php echo $result_id; ?>" class="btn-correction">
                            <i class="fas fa-edit"></i> Request Correction
                        </a>
                    <?php endif; ?>
                    
                    <a href="result-verification.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Back to Verification
                    </a>
                </div>
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