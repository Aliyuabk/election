<?php
// ============================================================
// WARD COORDINATOR - VERIFY RESULTS
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
$lga_id = SessionManager::get('lga_id');
$state_id = SessionManager::get('state_id');
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
// FETCH WARD NAME
// ============================================================
$ward_name = 'Ward';
try {
    if ($ward_id) {
        $stmt = $db->prepare("SELECT name FROM wards WHERE id = ?");
        $stmt->execute([$ward_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $ward_name = $result['name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching ward name: " . $e->getMessage());
}

// ============================================================
// HANDLE VERIFICATION ACTIONS
// ============================================================
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result_id = isset($_POST['result_id']) ? (int)$_POST['result_id'] : 0;
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
    
    if ($result_id > 0) {
        try {
            $db->beginTransaction();
            
            // Get result details
            $stmt = $db->prepare("
                SELECT r.*, pu.ward_id 
                FROM results_ec8a r
                JOIN polling_units pu ON r.pu_id = pu.id
                WHERE r.id = ? AND r.tenant_id = ? AND pu.ward_id = ?
            ");
            $stmt->execute([$result_id, $tenant_id, $ward_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                throw new Exception("Result not found or not in your ward.");
            }
            
            switch ($action) {
                case 'verify':
                    $stmt = $db->prepare("
                        UPDATE results_ec8a 
                        SET status = 'verified', verified_by = ?, verified_at = NOW(), remarks = CONCAT(COALESCE(remarks, ''), '\n', ?)
                        WHERE id = ?
                    ");
                    $stmt->execute([$user_id, "Verified by Ward Coordinator: " . $remarks, $result_id]);
                    $success_message = "Result verified successfully.";
                    logActivity($user_id, 'result_verified', "Verified EC8A result ID: $result_id", 'results_ec8a', $result_id);
                    break;
                    
                case 'reject':
                    if (empty($remarks)) {
                        throw new Exception("Please provide a reason for rejection.");
                    }
                    $stmt = $db->prepare("
                        UPDATE results_ec8a 
                        SET status = 'rejected', rejection_reason = ?, verified_by = ?, verified_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$remarks, $user_id, $result_id]);
                    $success_message = "Result rejected.";
                    logActivity($user_id, 'result_rejected', "Rejected EC8A result ID: $result_id - Reason: $remarks", 'results_ec8a', $result_id);
                    break;
                    
                case 'flag':
                    if (empty($remarks)) {
                        throw new Exception("Please provide a reason for flagging.");
                    }
                    $stmt = $db->prepare("
                        UPDATE results_ec8a 
                        SET status = 'flagged', rejection_reason = ?, verified_by = ?, verified_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$remarks, $user_id, $result_id]);
                    $success_message = "Result flagged for review.";
                    logActivity($user_id, 'result_flagged', "Flagged EC8A result ID: $result_id - Reason: $remarks", 'results_ec8a', $result_id);
                    break;
                    
                case 'request_correction':
                    if (empty($remarks)) {
                        throw new Exception("Please provide correction details.");
                    }
                    $stmt = $db->prepare("
                        UPDATE results_ec8a 
                        SET status = 'pending', rejection_reason = CONCAT('Correction requested: ', ?), verified_by = ?, verified_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$remarks, $user_id, $result_id]);
                    $success_message = "Correction requested successfully.";
                    logActivity($user_id, 'result_correction_requested', "Requested correction for EC8A result ID: $result_id", 'results_ec8a', $result_id);
                    break;
                    
                default:
                    throw new Exception("Invalid action.");
            }
            
            $db->commit();
            
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = "Error: " . $e->getMessage();
            error_log("Result verification error: " . $e->getMessage());
        }
    }
}

// ============================================================
// FETCH RESULTS FOR VERIFICATION
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$status_filter = isset($_GET['status']) ? $_GET['status'] : 'pending';
$pu_filter = isset($_GET['pu_id']) ? (int)$_GET['pu_id'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$results = [];
$total_results = 0;

try {
    // Build query conditions
    $conditions = "r.tenant_id = ? AND pu.ward_id = ?";
    $params = [$tenant_id, $ward_id];
    
    if ($status_filter !== 'all') {
        $conditions .= " AND r.status = ?";
        $params[] = $status_filter;
    }
    
    if ($pu_filter > 0) {
        $conditions .= " AND r.pu_id = ?";
        $params[] = $pu_filter;
    }
    
    if (!empty($search)) {
        $conditions .= " AND (r.pu_name LIKE ? OR r.pu_code LIKE ? OR u.full_name LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // Get total count
    $count_stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        WHERE $conditions
    ");
    $count_stmt->execute($params);
    $total_results = (int)$count_stmt->fetchColumn();
    
    // Get results
    $stmt = $db->prepare("
        SELECT 
            r.*,
            u.full_name as agent_name,
            u.phone as agent_phone,
            pu.name as pu_name,
            pu.code as pu_code,
            pu.registered_voters,
            verified_user.full_name as verified_by_name
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN users u ON r.agent_id = u.id
        LEFT JOIN users verified_user ON r.verified_by = verified_user.id
        WHERE $conditions
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching results: " . $e->getMessage());
}

// ============================================================
// FETCH POLLING UNITS FOR FILTER
// ============================================================
$polling_units = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, code FROM polling_units 
        WHERE ward_id = ? AND is_active = 1 
        ORDER BY name ASC
    ");
    $stmt->execute([$ward_id]);
    $polling_units = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching polling units: " . $e->getMessage());
}

// ============================================================
// FETCH VERIFICATION STATISTICS
// ============================================================
$verification_stats = [
    'total' => 0,
    'pending' => 0,
    'verified' => 0,
    'rejected' => 0,
    'flagged' => 0
];

try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'flagged' THEN 1 ELSE 0 END) as flagged
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        WHERE r.tenant_id = ? AND pu.ward_id = ?
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $verification_stats['total'] = (int)($stats['total'] ?? 0);
    $verification_stats['pending'] = (int)($stats['pending'] ?? 0);
    $verification_stats['verified'] = (int)($stats['verified'] ?? 0);
    $verification_stats['rejected'] = (int)($stats['rejected'] ?? 0);
    $verification_stats['flagged'] = (int)($stats['flagged'] ?? 0);
    
} catch (Exception $e) {
    error_log("Error fetching verification stats: " . $e->getMessage());
}

$page_title = 'Verify Results';
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

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}
.stat-mini {
    background: white;
    padding: 10px 14px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    text-align: center;
}
.stat-mini .number {
    font-size: 1.2rem;
    font-weight: 700;
}
.stat-mini .number.blue { color: #3B82F6; }
.stat-mini .number.green { color: #10B981; }
.stat-mini .number.red { color: #EF4444; }
.stat-mini .number.orange { color: #F59E0B; }
.stat-mini .label {
    font-size: 0.6rem;
    color: var(--gray-500);
    font-weight: 500;
}

.filter-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 16px;
    align-items: center;
}
.filter-bar .search-box {
    flex: 1;
    min-width: 180px;
    position: relative;
}
.filter-bar .search-box input {
    width: 100%;
    padding: 8px 12px 8px 36px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    background: white;
}
.filter-bar .search-box i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray-400);
}
.filter-bar select {
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    background: white;
    min-width: 140px;
}

.result-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px;
    margin-bottom: 12px;
    transition: var(--transition);
}
.result-card:hover {
    box-shadow: var(--shadow-hover);
}
.result-card .result-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 8px;
}
.result-card .result-title {
    font-weight: 600;
    font-size: 0.95rem;
}
.result-card .result-title .pu-code {
    font-weight: 400;
    font-size: 0.7rem;
    color: var(--gray-400);
}
.result-card .result-meta {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    font-size: 0.75rem;
    color: var(--gray-500);
}
.result-card .result-meta i {
    width: 14px;
}
.result-card .result-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 4px 12px;
    font-size: 0.82rem;
    color: var(--gray-600);
    margin: 8px 0;
    padding: 8px 12px;
    background: var(--gray-50);
    border-radius: 6px;
}
.result-card .result-details .item {
    display: flex;
    justify-content: space-between;
    padding: 2px 0;
}
.result-card .result-details .item .label {
    color: var(--gray-500);
}
.result-card .result-details .item .value {
    font-weight: 500;
}
.result-card .result-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid var(--gray-100);
}
.result-card .result-actions .btn-sm {
    padding: 4px 14px;
    font-size: 0.7rem;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.result-card .result-actions .btn-sm.verify { background: #D1FAE5; color: #065F46; }
.result-card .result-actions .btn-sm.reject { background: #FEE2E2; color: #991B1B; }
.result-card .result-actions .btn-sm.flag { background: #FEF3C7; color: #92400E; }
.result-card .result-actions .btn-sm.correct { background: #DBEAFE; color: #1E40AF; }
.result-card .result-actions .btn-sm.view { background: #EFF6FF; color: #3B82F6; }

.status-badge {
    font-size: 0.6rem;
    padding: 2px 10px;
    border-radius: 20px;
    font-weight: 500;
}
.status-badge.pending { background: #FEF3C7; color: #92400E; }
.status-badge.verified { background: #D1FAE5; color: #065F46; }
.status-badge.rejected { background: #FEE2E2; color: #991B1B; }
.status-badge.flagged { background: #FEF3C7; color: #92400E; }
.status-badge.approved { background: #D1FAE5; color: #065F46; }

.pagination {
    display: flex;
    justify-content: center;
    gap: 6px;
    padding: 16px 0;
}
.pagination a, .pagination span {
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 0.8rem;
    text-decoration: none;
    color: var(--gray-600);
    border: 1px solid var(--gray-200);
}
.pagination a:hover {
    background: var(--gray-50);
    border-color: var(--gray-300);
}
.pagination .active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
.pagination .disabled {
    opacity: 0.5;
    pointer-events: none;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--gray-500);
}
.empty-state i {
    font-size: 4rem;
    color: var(--gray-300);
    margin-bottom: 16px;
}
.empty-state h4 {
    margin: 0 0 8px;
    color: var(--gray-700);
}
.empty-state p {
    margin: 0;
    font-size: 0.9rem;
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

@media (max-width: 768px) {
    .stats-row {
        grid-template-columns: repeat(3, 1fr);
    }
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar .search-box {
        min-width: unset;
    }
    .result-card .result-details {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="verify-header">
            <div>
                <h2><i class="fas fa-check-double"></i> Verify Results</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward • 
                    <?php echo number_format($verification_stats['pending']); ?> pending verification
                </p>
            </div>
            <div>
                <a href="approval-history.php" class="btn-secondary-sm">
                    <i class="fas fa-history"></i> Approval History
                </a>
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

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-mini">
                <div class="number blue"><?php echo number_format($verification_stats['total']); ?></div>
                <div class="label">Total</div>
            </div>
            <div class="stat-mini">
                <div class="number orange"><?php echo number_format($verification_stats['pending']); ?></div>
                <div class="label">Pending</div>
            </div>
            <div class="stat-mini">
                <div class="number green"><?php echo number_format($verification_stats['verified']); ?></div>
                <div class="label">Verified</div>
            </div>
            <div class="stat-mini">
                <div class="number red"><?php echo number_format($verification_stats['rejected']); ?></div>
                <div class="label">Rejected</div>
            </div>
            <div class="stat-mini">
                <div class="number orange"><?php echo number_format($verification_stats['flagged']); ?></div>
                <div class="label">Flagged</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search by PU name, code or agent..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <select id="statusFilter">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="verified" <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                <option value="flagged" <?php echo $status_filter === 'flagged' ? 'selected' : ''; ?>>Flagged</option>
            </select>
            <select id="puFilter">
                <option value="0" <?php echo $pu_filter === 0 ? 'selected' : ''; ?>>All Polling Units</option>
                <?php foreach ($polling_units as $pu): ?>
                    <option value="<?php echo $pu['id']; ?>" <?php echo $pu_filter === (int)$pu['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($pu['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button onclick="applyFilters()" class="btn-secondary-sm">
                <i class="fas fa-filter"></i> Apply
            </button>
            <button onclick="resetFilters()" class="btn-secondary-sm" style="background: var(--gray-100);">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>

        <!-- Results List -->
        <?php if (count($results) > 0): ?>
            <?php foreach ($results as $result): 
                $party_votes = json_decode($result['party_votes_json'] ?? '{}', true);
                $total_votes = array_sum($party_votes);
            ?>
                <div class="result-card">
                    <div class="result-top">
                        <div>
                            <div class="result-title">
                                <?php echo htmlspecialchars($result['pu_name']); ?>
                                <span class="pu-code">(<?php echo htmlspecialchars($result['pu_code']); ?>)</span>
                            </div>
                            <div class="result-meta">
                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($result['agent_name']); ?></span>
                                <span><i class="fas fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($result['created_at'])); ?></span>
                                <span><i class="fas fa-users"></i> <?php echo number_format($result['registered_voters'] ?? 0); ?> voters</span>
                                <?php if ($result['verified_by_name']): ?>
                                    <span><i class="fas fa-check-circle" style="color:#10B981;"></i> Verified by <?php echo htmlspecialchars($result['verified_by_name']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="status-badge <?php echo $result['status']; ?>">
                            <?php echo ucfirst($result['status']); ?>
                        </span>
                    </div>

                    <div class="result-details">
                        <div class="item">
                            <span class="label">Total Valid Votes</span>
                            <span class="value"><?php echo number_format($result['valid_votes'] ?? 0); ?></span>
                        </div>
                        <div class="item">
                            <span class="label">Rejected Votes</span>
                            <span class="value"><?php echo number_format($result['rejected_votes'] ?? 0); ?></span>
                        </div>
                        <div class="item">
                            <span class="label">Total Votes Cast</span>
                            <span class="value"><?php echo number_format($result['total_votes_cast'] ?? 0); ?></span>
                        </div>
                        <?php if (!empty($party_votes)): ?>
                            <div class="item" style="grid-column:1/-1;border-top:1px solid var(--gray-200);padding-top:4px;">
                                <span class="label">Party Votes</span>
                                <span class="value">
                                    <?php 
                                    $party_strings = [];
                                    foreach ($party_votes as $party => $votes) {
                                        if ($votes > 0) {
                                            $party_strings[] = $party . ': ' . number_format($votes);
                                        }
                                    }
                                    echo implode(' | ', $party_strings);
                                    ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($result['rejection_reason'])): ?>
                            <div class="item" style="grid-column:1/-1;color:#EF4444;">
                                <span class="label">Note</span>
                                <span class="value"><?php echo htmlspecialchars($result['rejection_reason']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="result-actions">
                        <?php if ($result['status'] === 'pending'): ?>
                            <button onclick="openActionModal(<?php echo $result['id']; ?>, 'verify')" class="btn-sm verify">
                                <i class="fas fa-check"></i> Verify
                            </button>
                            <button onclick="openActionModal(<?php echo $result['id']; ?>, 'reject')" class="btn-sm reject">
                                <i class="fas fa-times"></i> Reject
                            </button>
                            <button onclick="openActionModal(<?php echo $result['id']; ?>, 'flag')" class="btn-sm flag">
                                <i class="fas fa-flag"></i> Flag
                            </button>
                            <button onclick="openActionModal(<?php echo $result['id']; ?>, 'request_correction')" class="btn-sm correct">
                                <i class="fas fa-edit"></i> Request Correction
                            </button>
                        <?php else: ?>
                            <a href="result-details.php?id=<?php echo $result['id']; ?>" class="btn-sm view">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                            <?php if ($result['status'] === 'rejected' || $result['status'] === 'flagged'): ?>
                                <button onclick="openActionModal(<?php echo $result['id']; ?>, 'verify')" class="btn-sm verify">
                                    <i class="fas fa-undo"></i> Override & Verify
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php 
            $total_pages = ceil($total_results / $limit);
            if ($total_pages > 1): 
            ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&pu_id=<?php echo $pu_filter; ?>&search=<?php echo urlencode($search); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&pu_id=<?php echo $pu_filter; ?>&search=<?php echo urlencode($search); ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&pu_id=<?php echo $pu_filter; ?>&search=<?php echo urlencode($search); ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-double"></i>
                <h4>No Results Found</h4>
                <p>No results match your filters.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Action Modal -->
<div class="modal-overlay" id="actionModal">
    <div class="modal-content">
        <h3 id="modalTitle">Action on Result</h3>
        <form method="POST" action="">
            <input type="hidden" name="result_id" id="modalResultId">
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
// Apply filters
function applyFilters() {
    const search = document.getElementById('searchInput').value;
    const status = document.getElementById('statusFilter').value;
    const pu = document.getElementById('puFilter').value;
    window.location.href = `?search=${encodeURIComponent(search)}&status=${status}&pu_id=${pu}`;
}

// Reset filters
function resetFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('statusFilter').value = 'pending';
    document.getElementById('puFilter').value = '0';
    window.location.href = '?status=pending';
}

// Enter key on search
document.getElementById('searchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        applyFilters();
    }
});

// Open action modal
function openActionModal(resultId, action) {
    document.getElementById('modalResultId').value = resultId;
    document.getElementById('modalAction').value = action;
    
    const titles = {
        'verify': 'Verify Result',
        'reject': 'Reject Result',
        'flag': 'Flag Result for Review',
        'request_correction': 'Request Correction'
    };
    document.getElementById('modalTitle').textContent = titles[action] || 'Action on Result';
    
    const submitLabels = {
        'verify': 'Verify',
        'reject': 'Reject',
        'flag': 'Flag',
        'request_correction': 'Request Correction'
    };
    document.getElementById('modalSubmitBtn').innerHTML = `<i class="fas fa-check"></i> ${submitLabels[action] || 'Confirm'}`;
    
    // Show required for reject, flag, and correction
    const remarksRequired = document.getElementById('remarksRequired');
    const remarksLabel = document.querySelector('#actionModal .form-group label');
    if (action === 'reject' || action === 'flag' || action === 'request_correction') {
        remarksRequired.style.display = 'inline';
        document.getElementById('modalRemarks').required = true;
        if (action === 'reject') {
            document.getElementById('modalRemarks').placeholder = 'Please provide a reason for rejection...';
        } else if (action === 'flag') {
            document.getElementById('modalRemarks').placeholder = 'Please provide a reason for flagging...';
        } else {
            document.getElementById('modalRemarks').placeholder = 'Please provide correction details...';
        }
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