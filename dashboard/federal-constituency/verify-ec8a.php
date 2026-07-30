<?php
// ============================================================
// FEDERAL CONSTITUENCY COORDINATOR - VERIFY EC8A
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'federal_constituency') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$constituency_id = SessionManager::get('federal_constituency_id');
$state_id = SessionManager::get('state_id');
$tenant_id = SessionManager::get('tenant_id');

$db = getDB();

// ============================================================
// GET LGA IDs
// ============================================================
$lga_ids = [];
try {
    if ($constituency_id) {
        $stmt = $db->prepare("SELECT lgas_json FROM federal_constituencies WHERE id = ?");
        $stmt->execute([$constituency_id]);
        $lgas_json = $stmt->fetchColumn();
        
        if ($lgas_json) {
            $lga_names = json_decode($lgas_json, true) ?: [];
            
            if (!empty($lga_names)) {
                $placeholders = implode(',', array_fill(0, count($lga_names), '?'));
                $stmt = $db->prepare("SELECT id FROM lgas WHERE name IN ($placeholders) AND state_id = ? AND is_active = 1");
                $stmt->execute(array_merge($lga_names, [$state_id]));
                $lga_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
        }
    }
} catch (Exception $e) {
    error_log("Error getting LGA IDs: " . $e->getMessage());
}

$lga_list = !empty($lga_ids) ? implode(',', array_map('intval', $lga_ids)) : '0';

// ============================================================
// GET FILTERS
// ============================================================
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'pending';
$lga_filter = isset($_GET['lga']) ? (int)$_GET['lga'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ============================================================
// GET LGAS FOR FILTER
// ============================================================
$lgas = [];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("SELECT id, name FROM lgas WHERE id IN ($lga_list) ORDER BY name ASC");
        $stmt->execute();
        $lgas = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching LGAs: " . $e->getMessage());
}

// ============================================================
// GET EC8A SUBMISSIONS
// ============================================================
$submissions = [];
$total = 0;
try {
    $where = ["r.tenant_id = ?"];
    $params = [$tenant_id];
    
    if ($lga_list !== '0') {
        if ($lga_filter > 0) {
            $where[] = "w.lga_id = ?";
            $params[] = $lga_filter;
        } else {
            $where[] = "w.lga_id IN ($lga_list)";
        }
    } else {
        $where[] = "1=0";
    }
    
    if (!empty($status_filter)) {
        $where[] = "r.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($search)) {
        $where[] = "(pu.name LIKE ? OR pu.code LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_clause = implode(" AND ", $where);
    
    $stmt = $db->prepare("
        SELECT 
            r.*,
            pu.name as pu_name,
            pu.code as pu_code,
            w.name as ward_name,
            l.name as lga_name,
            u.full_name as agent_name,
            v.full_name as verified_by_name
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        LEFT JOIN users u ON r.agent_id = u.id
        LEFT JOIN users v ON r.verified_by = v.id
        WHERE $where_clause
        ORDER BY r.created_at DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    $submissions = $stmt->fetchAll();
    $total = count($submissions);
} catch (Exception $e) {
    error_log("Error fetching EC8A submissions: " . $e->getMessage());
}

// ============================================================
// HANDLE VERIFICATION ACTION
// ============================================================
$action_error = '';
$action_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $result_id = (int)$_POST['result_id'];
    $action = $_POST['action'];
    $remarks = trim($_POST['remarks'] ?? '');
    
    try {
        if ($action === 'approve') {
            $status = 'verified';
            $stmt = $db->prepare("
                UPDATE results_ec8a SET 
                    status = 'verified',
                    verified_by = ?,
                    verified_at = NOW(),
                    remarks = ?
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$user_id, $remarks, $result_id, $tenant_id]);
            $action_success = 'Result approved successfully!';
            logActivity($user_id, 'ec8a_verified', "Verified EC8A result ID: $result_id", 'results_ec8a', $result_id);
        } elseif ($action === 'reject') {
            $stmt = $db->prepare("
                UPDATE results_ec8a SET 
                    status = 'rejected',
                    rejection_reason = ?,
                    verified_by = ?,
                    verified_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$remarks, $user_id, $result_id, $tenant_id]);
            $action_success = 'Result rejected.';
            logActivity($user_id, 'ec8a_rejected', "Rejected EC8A result ID: $result_id", 'results_ec8a', $result_id);
        } elseif ($action === 'flag') {
            $stmt = $db->prepare("
                UPDATE results_ec8a SET 
                    status = 'flagged',
                    remarks = ?,
                    verified_by = ?,
                    verified_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$remarks, $user_id, $result_id, $tenant_id]);
            $action_success = 'Result flagged for review.';
            logActivity($user_id, 'ec8a_flagged', "Flagged EC8A result ID: $result_id", 'results_ec8a', $result_id);
        } elseif ($action === 'request_correction') {
            $stmt = $db->prepare("
                UPDATE results_ec8a SET 
                    status = 'pending',
                    remarks = CONCAT(IFNULL(remarks, ''), '\nCorrection requested: ', ?),
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$remarks, $result_id, $tenant_id]);
            $action_success = 'Correction requested.';
            logActivity($user_id, 'ec8a_correction', "Requested correction for EC8A result ID: $result_id", 'results_ec8a', $result_id);
        }
    } catch (Exception $e) {
        $action_error = 'Error: ' . $e->getMessage();
        error_log("EC8A verification error: " . $e->getMessage());
    }
}

$page_title = 'Verify EC8A';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.filter-section {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 14px 18px;
    margin-bottom: 20px;
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}
.filter-section select,
.filter-section input {
    padding: 8px 14px;
    border: 1.5px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.85rem;
    background: white;
}
.filter-section select:focus,
.filter-section input:focus {
    outline: none;
    border-color: var(--primary);
}
.filter-section .btn-filter {
    padding: 8px 20px;
    border: none;
    border-radius: 8px;
    background: var(--primary);
    color: white;
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
}
.filter-section .btn-reset {
    padding: 8px 18px;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    background: white;
    color: var(--gray-600);
    font-weight: 500;
    font-size: 0.8rem;
    text-decoration: none;
}

.results-summary {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 12px 20px;
    margin-bottom: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    font-size: 0.85rem;
}
.results-summary .count {
    font-weight: 600;
    color: var(--gray-700);
}
.results-summary .count span {
    color: var(--primary);
}

.submission-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 18px 20px;
    margin-bottom: 14px;
}
.submission-card .submission-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 8px;
}
.submission-card .submission-header .pu-info {
    font-weight: 600;
    font-size: 0.95rem;
}
.submission-card .submission-header .pu-info .code {
    font-weight: 400;
    font-size: 0.8rem;
    color: var(--gray-400);
    margin-left: 8px;
}
.submission-card .submission-meta {
    display: flex;
    gap: 16px;
    font-size: 0.75rem;
    color: var(--gray-500);
    margin-top: 4px;
    flex-wrap: wrap;
}
.submission-card .submission-meta i {
    margin-right: 4px;
}
.submission-card .submission-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 10px;
    margin: 12px 0;
    background: var(--gray-50);
    padding: 12px;
    border-radius: 8px;
}
.submission-card .submission-details .detail {
    text-align: center;
}
.submission-card .submission-details .detail .number {
    font-weight: 700;
    font-size: 1.1rem;
}
.submission-card .submission-details .detail .label {
    font-size: 0.6rem;
    color: var(--gray-500);
    text-transform: uppercase;
}
.submission-card .submission-actions {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--gray-100);
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.submission-card .submission-actions .btn {
    padding: 6px 16px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: var(--transition);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.submission-card .submission-actions .btn-approve {
    background: #10B981;
    color: white;
}
.submission-card .submission-actions .btn-approve:hover {
    background: #059669;
}
.submission-card .submission-actions .btn-reject {
    background: #EF4444;
    color: white;
}
.submission-card .submission-actions .btn-reject:hover {
    background: #DC2626;
}
.submission-card .submission-actions .btn-flag {
    background: #F59E0B;
    color: white;
}
.submission-card .submission-actions .btn-flag:hover {
    background: #D97706;
}
.submission-card .submission-actions .btn-correction {
    background: #3B82F6;
    color: white;
}
.submission-card .submission-actions .btn-correction:hover {
    background: #2563EB;
}
.submission-card .submission-actions .btn-view {
    background: var(--gray-100);
    color: var(--gray-600);
}
.submission-card .submission-actions .btn-view:hover {
    background: var(--gray-200);
}

.status-badge {
    display: inline-block;
    padding: 2px 12px;
    border-radius: 12px;
    font-size: 0.65rem;
    font-weight: 600;
}
.status-badge.pending { background: #FEF3C7; color: #D97706; }
.status-badge.verified { background: #D1FAE5; color: #059669; }
.status-badge.flagged { background: #FEE2E2; color: #DC2626; }
.status-badge.rejected { background: #FEE2E2; color: #DC2626; }

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--gray-500);
}
.empty-state i {
    font-size: 3rem;
    color: var(--gray-300);
    display: block;
    margin-bottom: 12px;
}
.empty-state h3 {
    font-size: 1.1rem;
    color: var(--gray-700);
    margin-bottom: 4px;
}

.alert {
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 0.85rem;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-success {
    background: #ECFDF5;
    color: #065F46;
    border: 1px solid #A7F3D0;
}
.alert-error {
    background: #FEF2F2;
    color: #DC2626;
    border: 1px solid #FECACA;
}

.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}
.modal-overlay.active { display: flex; }
.modal-box {
    background: white;
    border-radius: var(--radius);
    padding: 24px 30px;
    max-width: 500px;
    width: 90%;
}
.modal-box h3 {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 12px;
}
.modal-box .modal-body { margin-bottom: 16px; }
.modal-box .modal-body textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid var(--gray-200);
    border-radius: 8px;
    font-family: 'Inter', sans-serif;
    font-size: 0.85rem;
    min-height: 80px;
    resize: vertical;
}
.modal-box .modal-body textarea:focus {
    outline: none;
    border-color: var(--primary);
}
.modal-box .modal-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}
.modal-box .modal-actions .btn {
    padding: 8px 20px;
    border-radius: 8px;
    border: none;
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
}
.modal-box .modal-actions .btn-primary {
    background: var(--primary);
    color: white;
}
.modal-box .modal-actions .btn-secondary {
    background: var(--gray-100);
    color: var(--gray-600);
}

@media (max-width: 768px) {
    .filter-section {
        flex-direction: column;
        align-items: stretch;
    }
    .submission-card .submission-details {
        grid-template-columns: repeat(2, 1fr);
    }
    .submission-card .submission-actions {
        flex-direction: column;
    }
    .submission-card .submission-actions .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <h2 style="font-size:1.3rem;font-weight:700;margin-bottom:4px;">
            <i class="fas fa-check-double" style="color:var(--primary);"></i> Verify EC8A Submissions
        </h2>
        <p style="color:var(--gray-500);font-size:0.9rem;margin-bottom:20px;">
            Review and verify Polling Unit result submissions.
        </p>

        <?php if (!empty($action_error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo htmlspecialchars($action_error); ?></div>
            </div>
        <?php endif; ?>
        <?php if (!empty($action_success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div><?php echo htmlspecialchars($action_success); ?></div>
            </div>
        <?php endif; ?>

        <!-- Filter -->
        <div class="filter-section">
            <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;flex:1;align-items:center;">
                <select name="status">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo ($status_filter === 'pending') ? 'selected' : ''; ?>>Pending</option>
                    <option value="verified" <?php echo ($status_filter === 'verified') ? 'selected' : ''; ?>>Verified</option>
                    <option value="flagged" <?php echo ($status_filter === 'flagged') ? 'selected' : ''; ?>>Flagged</option>
                    <option value="rejected" <?php echo ($status_filter === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                </select>
                <select name="lga">
                    <option value="">All LGAs</option>
                    <?php foreach ($lgas as $lga): ?>
                        <option value="<?php echo $lga['id']; ?>" <?php echo ($lga_filter == $lga['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($lga['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="search" placeholder="Search PU..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
                <a href="verify-ec8a.php" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
            </form>
        </div>

        <!-- Results -->
        <div class="results-summary">
            <div class="count"><span><?php echo number_format($total); ?></span> submissions found</div>
            <?php if ($total >= 100): ?>
                <div style="font-size:0.75rem;color:var(--gray-400);">
                    <i class="fas fa-info-circle"></i> Showing first 100
                </div>
            <?php endif; ?>
        </div>

        <!-- Submissions -->
        <?php if (count($submissions) > 0): ?>
            <?php foreach ($submissions as $sub): ?>
                <div class="submission-card">
                    <div class="submission-header">
                        <div>
                            <div class="pu-info">
                                <?php echo htmlspecialchars($sub['pu_name']); ?>
                                <span class="code">(<?php echo htmlspecialchars($sub['pu_code']); ?>)</span>
                            </div>
                            <div class="submission-meta">
                                <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($sub['ward_name']); ?>, <?php echo htmlspecialchars($sub['lga_name']); ?></span>
                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($sub['agent_name'] ?? 'Unknown'); ?></span>
                                <span><i class="fas fa-clock"></i> <?php echo date('M d, Y g:i A', strtotime($sub['created_at'])); ?></span>
                                <span><span class="status-badge <?php echo $sub['status']; ?>"><?php echo ucfirst($sub['status']); ?></span></span>
                            </div>
                        </div>
                    </div>

                    <?php 
                    $party_votes = [];
                    if (!empty($sub['party_votes_json'])) {
                        $party_votes = json_decode($sub['party_votes_json'], true) ?: [];
                    }
                    ?>
                    <?php if (!empty($party_votes)): ?>
                        <div style="display:flex;flex-wrap:wrap;gap:6px;margin:6px 0;">
                            <?php foreach ($party_votes as $party => $votes): ?>
                                <span style="background:#F3F4F6;padding:2px 12px;border-radius:12px;font-size:0.75rem;">
                                    <?php echo htmlspecialchars($party); ?>: 
                                    <strong><?php echo number_format((int)$votes); ?></strong>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="submission-details">
                        <div class="detail">
                            <div class="number"><?php echo number_format($sub['valid_votes'] ?? 0); ?></div>
                            <div class="label">Valid</div>
                        </div>
                        <div class="detail">
                            <div class="number"><?php echo number_format($sub['rejected_votes'] ?? 0); ?></div>
                            <div class="label">Rejected</div>
                        </div>
                        <div class="detail">
                            <div class="number"><?php echo number_format($sub['total_votes_cast'] ?? 0); ?></div>
                            <div class="label">Total Votes</div>
                        </div>
                        <div class="detail">
                            <div class="number"><?php echo number_format($sub['registered_voters'] ?? 0); ?></div>
                            <div class="label">Registered</div>
                        </div>
                    </div>

                    <?php if ($sub['status'] === 'pending'): ?>
                        <div class="submission-actions">
                            <button class="btn btn-approve" onclick="openModal(<?php echo $sub['id']; ?>, 'approve')">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button class="btn btn-flag" onclick="openModal(<?php echo $sub['id']; ?>, 'flag')">
                                <i class="fas fa-flag"></i> Flag
                            </button>
                            <button class="btn btn-correction" onclick="openModal(<?php echo $sub['id']; ?>, 'request_correction')">
                                <i class="fas fa-edit"></i> Request Correction
                            </button>
                            <button class="btn btn-reject" onclick="openModal(<?php echo $sub['id']; ?>, 'reject')">
                                <i class="fas fa-times"></i> Reject
                            </button>
                            <a href="ec8a-details.php?id=<?php echo $sub['id']; ?>" class="btn btn-view">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="submission-actions">
                            <a href="ec8a-details.php?id=<?php echo $sub['id']; ?>" class="btn btn-view">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                            <?php if ($sub['status'] === 'flagged'): ?>
                                <button class="btn btn-approve" onclick="openModal(<?php echo $sub['id']; ?>, 'approve')">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>No Submissions Found</h3>
                <p>No EC8A submissions match your filter criteria.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Action Modal -->
<div class="modal-overlay" id="actionModal">
    <div class="modal-box">
        <h3 id="modalTitle">Action</h3>
        <form method="POST">
            <input type="hidden" name="result_id" id="modalResultId">
            <input type="hidden" name="action" id="modalAction">
            <div class="modal-body">
                <label style="font-weight:600;font-size:0.85rem;display:block;margin-bottom:4px;">Remarks</label>
                <textarea name="remarks" id="modalRemarks" placeholder="Enter remarks..."></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(resultId, action) {
    document.getElementById('modalResultId').value = resultId;
    document.getElementById('modalAction').value = action;
    document.getElementById('modalTitle').textContent = action.replace('_', ' ').toUpperCase();
    document.getElementById('modalRemarks').value = '';
    document.getElementById('actionModal').classList.add('active');
}

function closeModal() {
    document.getElementById('actionModal').classList.remove('active');
}

document.getElementById('actionModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// Sidebar toggle (standard)
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

window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});
</script>
</body>
</html>