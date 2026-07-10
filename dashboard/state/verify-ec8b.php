<?php
// ============================================================
// STATE COORDINATOR - VERIFY EC8B (WARD RESULTS)
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
$election_filter = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;
$lga_filter = isset($_GET['lga_id']) ? (int)$_GET['lga_id'] : 0;

// Get state name
$state_name = 'Unknown State';
if (!empty($state_id)) {
    $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
    $stmt->execute([$state_id]);
    $state = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_name = $state['name'] ?? 'Unknown State';
}

// Get elections for filter
$elections = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, type, status 
        FROM elections 
        WHERE tenant_id = ? AND deleted_at IS NULL
        AND (states_json LIKE ? OR states_json IS NULL OR states_json = '[]')
        ORDER BY election_date DESC
    ");
    $stmt->execute([$tenant_id, '%"' . $state_id . '"%']);
    $elections = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching elections: " . $e->getMessage());
}

// Get LGAs for filter
$lgas = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM lgas WHERE state_id = ? AND is_active = 1 ORDER BY name ASC");
    $stmt->execute([$state_id]);
    $lgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching LGAs: " . $e->getMessage());
}

// Handle single EC8B verification
$message = '';
$error = '';
$result = null;

if ($result_id > 0) {
    // Get EC8B result details
    try {
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
                u.first_name as coordinator_first_name,
                u.last_name as coordinator_last_name,
                u.email as coordinator_email
            FROM results_ec8b r
            JOIN wards w ON r.ward_id = w.id
            JOIN lgas l ON w.lga_id = l.id
            JOIN states s ON l.state_id = s.id
            JOIN elections e ON r.election_id = e.id
            LEFT JOIN users u ON r.coordinator_id = u.id
            WHERE r.id = ? AND r.tenant_id = ?
        ");
        $stmt->execute([$result_id, $tenant_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching EC8B result: " . $e->getMessage());
    }

    if (!$result) {
        header('Location: verify-ec8b.php');
        exit();
    }

    // Handle verification action
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $remarks = trim($_POST['remarks'] ?? '');
        
        if ($action === 'verify') {
            try {
                $stmt = $db->prepare("
                    UPDATE results_ec8b 
                    SET status = 'verified', 
                        verified_by = ?, 
                        created_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$user_id, $result_id]);
                
                logActivity($user_id, 'ec8b_verified', 
                    "Verified EC8B result ID: $result_id for Ward: {$result['ward_name']}",
                    'results_ec8b', $result_id
                );
                
                $message = 'EC8B result verified successfully!';
            } catch (Exception $e) {
                $error = 'Failed to verify EC8B: ' . $e->getMessage();
            }
        } elseif ($action === 'reject') {
            $rejection_reason = trim($_POST['rejection_reason'] ?? '');
            if (empty($rejection_reason)) {
                $error = 'Please provide a reason for rejection.';
            } else {
                try {
                    $stmt = $db->prepare("
                        UPDATE results_ec8b 
                        SET status = 'rejected', 
                            verified_by = ?,
                            created_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$user_id, $result_id]);
                    
                    logActivity($user_id, 'ec8b_rejected', 
                        "Rejected EC8B result ID: $result_id for Ward: {$result['ward_name']} - Reason: $rejection_reason",
                        'results_ec8b', $result_id
                    );
                    
                    $message = 'EC8B result rejected successfully.';
                } catch (Exception $e) {
                    $error = 'Failed to reject EC8B: ' . $e->getMessage();
                }
            }
        }
    }
}

// Fetch pending EC8B results
$pending_results = [];
$stats = [
    'total' => 0,
    'pending' => 0,
    'verified' => 0,
    'rejected' => 0,
    'by_lga' => []
];

try {
    $sql = "
        SELECT 
            r.id,
            r.ward_id,
            r.valid_votes,
            r.total_votes,
            r.status,
            r.created_at,
            w.name as ward_name,
            w.code as ward_code,
            l.id as lga_id,
            l.name as lga_name,
            e.id as election_id,
            e.name as election_name,
            u.first_name as coordinator_first_name,
            u.last_name as coordinator_last_name,
            (SELECT COUNT(*) FROM results_ec8a ra 
             JOIN polling_units pu ON ra.pu_id = pu.id 
             WHERE pu.ward_id = w.id AND ra.election_id = r.election_id 
             AND ra.status IN ('verified', 'approved')) as ec8a_count,
            (SELECT COUNT(*) FROM polling_units pu 
             WHERE pu.ward_id = w.id AND pu.is_active = 1) as total_pus
        FROM results_ec8b r
        JOIN wards w ON r.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        JOIN elections e ON r.election_id = e.id
        LEFT JOIN users u ON r.coordinator_id = u.id
        WHERE r.tenant_id = ? AND l.state_id = ?
    ";
    
    $params = [$tenant_id, $state_id];
    
    if ($election_filter > 0) {
        $sql .= " AND r.election_id = ?";
        $params[] = $election_filter;
    }
    
    if ($lga_filter > 0) {
        $sql .= " AND l.id = ?";
        $params[] = $lga_filter;
    }
    
    $sql .= " ORDER BY r.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $pending_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate stats
    foreach ($pending_results as $res) {
        $stats['total']++;
        $status = $res['status'] ?? 'pending';
        $stats[$status] = ($stats[$status] ?? 0) + 1;
        
        $lga_key = $res['lga_id'];
        if (!isset($stats['by_lga'][$lga_key])) {
            $stats['by_lga'][$lga_key] = [
                'name' => $res['lga_name'],
                'total' => 0,
                'pending' => 0,
                'verified' => 0
            ];
        }
        $stats['by_lga'][$lga_key]['total']++;
        if ($status === 'pending') {
            $stats['by_lga'][$lga_key]['pending']++;
        } elseif ($status === 'verified') {
            $stats['by_lga'][$lga_key]['verified']++;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching EC8B results: " . $e->getMessage());
}

$page_title = 'Verify EC8B';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.filter-bar {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
    align-items: center;
    background: white;
    padding: 16px 20px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}

.filter-bar select {
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.8rem;
    font-family: 'Inter', sans-serif;
    background: white;
    min-width: 160px;
}

.filter-bar select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.filter-bar .btn-filter {
    padding: 8px 24px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.filter-bar .btn-filter:hover {
    background: var(--primary-dark);
}

.filter-bar .filter-info {
    font-size: 0.75rem;
    color: var(--gray-500);
    margin-left: auto;
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}

.stats-row .stat-box {
    background: white;
    border-radius: 10px;
    padding: 12px 16px;
    border: 1px solid var(--gray-200);
    text-align: center;
}

.stats-row .stat-box .number {
    font-size: 1.3rem;
    font-weight: 700;
}

.stats-row .stat-box .number.total { color: #3B82F6; }
.stats-row .stat-box .number.pending { color: #F59E0B; }
.stats-row .stat-box .number.verified { color: #10B981; }
.stats-row .stat-box .number.rejected { color: #EF4444; }

.stats-row .stat-box .label {
    font-size: 0.65rem;
    color: var(--gray-500);
}

.results-table-container {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
}

.results-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}

.results-table th {
    background: var(--gray-50);
    padding: 10px 14px;
    text-align: left;
    font-weight: 600;
    color: var(--gray-700);
    border-bottom: 1px solid var(--gray-200);
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.results-table td {
    padding: 10px 14px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}

.results-table tr:hover td {
    background: var(--gray-50);
}

.results-table .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.55rem;
    padding: 2px 10px;
    border-radius: 12px;
    font-weight: 600;
}

.results-table .status-badge .dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    display: inline-block;
}

.results-table .status-badge.pending { background: #FFFBEB; color: #92400E; }
.results-table .status-badge.pending .dot { background: #F59E0B; }
.results-table .status-badge.verified { background: #ECFDF5; color: #065F46; }
.results-table .status-badge.verified .dot { background: #10B981; }
.results-table .status-badge.rejected { background: #FEF2F2; color: #991B1B; }
.results-table .status-badge.rejected .dot { background: #EF4444; }
.results-table .status-badge.flagged { background: #F5F3FF; color: #5B21B6; }
.results-table .status-badge.flagged .dot { background: #8B5CF6; }

.results-table .action-buttons {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
}

.results-table .action-buttons a,
.results-table .action-buttons button {
    padding: 3px 10px;
    border-radius: 4px;
    font-size: 0.6rem;
    font-weight: 500;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: var(--transition);
}

.results-table .action-buttons .btn-verify {
    background: #3B82F6;
    color: white;
}

.results-table .action-buttons .btn-verify:hover {
    background: #2563EB;
}

.results-table .action-buttons .btn-view {
    background: var(--gray-100);
    color: var(--gray-700);
}

.results-table .action-buttons .btn-view:hover {
    background: var(--gray-200);
}

.results-table .action-buttons .btn-reject {
    background: #FEF2F2;
    color: #DC2626;
}

.results-table .action-buttons .btn-reject:hover {
    background: #FEE2E2;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state i {
    font-size: 3rem;
    color: var(--gray-300);
    display: block;
    margin-bottom: 12px;
}

.empty-state h4 {
    color: var(--gray-600);
    margin: 0;
}

.empty-state p {
    color: var(--gray-400);
    font-size: 0.85rem;
    margin-top: 4px;
}

/* Detail View Styles */
.detail-container {
    max-width: 800px;
    margin: 0 auto;
}

.detail-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 28px;
    margin-bottom: 16px;
}

.detail-card .card-title {
    font-size: 0.85rem;
    font-weight: 600;
    margin: 0 0 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-700);
}

.detail-card .card-title i {
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
}

.action-buttons .btn-reject {
    background: #EF4444;
    color: white;
}

.action-buttons .btn-reject:hover {
    background: #DC2626;
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
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar select {
        width: 100%;
        min-width: unset;
    }
    .filter-bar .filter-info {
        margin-left: 0;
        text-align: center;
    }
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
    .results-table-container {
        overflow-x: auto;
    }
    .results-table {
        font-size: 0.7rem;
    }
    .results-table th,
    .results-table td {
        padding: 6px 10px;
    }
    .detail-grid {
        grid-template-columns: 1fr;
    }
    .detail-card {
        padding: 16px 18px;
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
        <?php if ($result_id > 0 && $result): ?>
            <!-- Detail View -->
            <div class="detail-container">
                <!-- Page Header -->
                <div class="welcome-section">
                    <div>
                        <h1><i class="fas fa-file-alt"></i> Verify EC8B</h1>
                        <p class="subtitle">
                            <i class="fas fa-map-pin"></i> 
                            <?php echo htmlspecialchars($result['ward_name']); ?> Ward - 
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
                <div class="detail-card">
                    <div class="card-title"><i class="fas fa-info-circle"></i> Result Information</div>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="label">Ward</span>
                            <span class="value"><?php echo htmlspecialchars($result['ward_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Ward Code</span>
                            <span class="value"><?php echo htmlspecialchars($result['ward_code']); ?></span>
                        </div>
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
                            <span class="label">Submitted By</span>
                            <span class="value"><?php echo htmlspecialchars($result['coordinator_first_name'] ?? '') . ' ' . htmlspecialchars($result['coordinator_last_name'] ?? ''); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Vote Details -->
                <div class="detail-card">
                    <div class="card-title"><i class="fas fa-vote-yea"></i> Vote Details</div>
                    <?php 
                        $party_votes = [];
                        if (!empty($result['party_votes_json'])) {
                            $party_votes = json_decode($result['party_votes_json'], true) ?: [];
                        }
                    ?>
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
                            <span class="value"><?php echo number_format($result['total_votes']); ?></span>
                        </div>
                    </div>

                    <?php if ($result['mismatch_alert']): ?>
                        <div style="margin-top:12px;background:#FEF2F2;border:1px solid #FECACA;border-radius:8px;padding:10px 14px;color:#991B1B;">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Mismatch Alert:</strong> The total votes don't match the sum of party votes.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Actions -->
                <?php if ($result['status'] === 'pending'): ?>
                    <div class="detail-card">
                        <div class="card-title"><i class="fas fa-tasks"></i> Verification Actions</div>
                        
                        <form method="POST" action="" class="action-form">
                            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
                                <button type="submit" name="action" value="verify" class="btn-verify">
                                    <i class="fas fa-check"></i> Verify EC8B
                                </button>
                                <button type="button" class="btn-reject" onclick="toggleRejection()">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </div>
                            
                            <div id="rejectionSection" style="display:none;">
                                <div class="form-group">
                                    <label for="rejection_reason">Reason for Rejection <span class="required">*</span></label>
                                    <textarea name="rejection_reason" id="rejection_reason" placeholder="Please provide a detailed reason..."></textarea>
                                </div>
                                <div style="display:flex;gap:10px;">
                                    <button type="submit" name="action" value="reject" class="btn-reject">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                    <button type="button" onclick="toggleRejection()" class="btn-back">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <div style="margin-top:12px;">
                    <a href="verify-ec8b.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>

        <?php else: ?>
            <!-- List View -->
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-file-alt"></i> Verify EC8B</h1>
                    <p class="subtitle">
                        <i class="fas fa-flag"></i> 
                        <?php echo htmlspecialchars($state_name); ?> State - Verify Ward-Level Results
                    </p>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="stats-row">
                <div class="stat-box">
                    <div class="number total"><?php echo number_format($stats['total']); ?></div>
                    <div class="label">Total EC8B</div>
                </div>
                <div class="stat-box">
                    <div class="number pending"><?php echo number_format($stats['pending'] ?? 0); ?></div>
                    <div class="label">Pending</div>
                </div>
                <div class="stat-box">
                    <div class="number verified"><?php echo number_format($stats['verified'] ?? 0); ?></div>
                    <div class="label">Verified</div>
                </div>
                <div class="stat-box">
                    <div class="number rejected"><?php echo number_format($stats['rejected'] ?? 0); ?></div>
                    <div class="label">Rejected</div>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <select id="electionFilter" onchange="applyFilters()">
                    <option value="0">All Elections</option>
                    <?php foreach ($elections as $e): ?>
                        <option value="<?php echo $e['id']; ?>" <?php echo $election_filter == $e['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($e['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select id="lgaFilter" onchange="applyFilters()">
                    <option value="0">All LGAs</option>
                    <?php foreach ($lgas as $l): ?>
                        <option value="<?php echo $l['id']; ?>" <?php echo $lga_filter == $l['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($l['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button class="btn-filter" onclick="applyFilters()">
                    <i class="fas fa-filter"></i> Apply
                </button>

                <span class="filter-info">
                    <i class="fas fa-list"></i> <?php echo number_format($stats['total']); ?> results found
                </span>
            </div>

            <!-- Results Table -->
            <div class="results-table-container">
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Ward</th>
                            <th>LGA</th>
                            <th>Election</th>
                            <th>Coordinator</th>
                            <th>EC8A Count</th>
                            <th>Valid Votes</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_results as $res): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($res['ward_name']); ?></strong>
                                    <div style="font-size:0.6rem;color:var(--gray-400);"><?php echo htmlspecialchars($res['ward_code']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($res['lga_name']); ?></td>
                                <td><?php echo htmlspecialchars($res['election_name']); ?></td>
                                <td><?php echo htmlspecialchars($res['coordinator_first_name'] ?? '') . ' ' . htmlspecialchars($res['coordinator_last_name'] ?? ''); ?></td>
                                <td><?php echo number_format($res['ec8a_count']) . '/' . number_format($res['total_pus']); ?></td>
                                <td><?php echo number_format($res['valid_votes']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $res['status']; ?>">
                                        <span class="dot"></span>
                                        <?php echo ucfirst($res['status']); ?>
                                    </span>
                                </td>
                                <td style="font-size:0.7rem;color:var(--gray-500);">
                                    <?php echo date('M j, Y', strtotime($res['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($res['status'] === 'pending'): ?>
                                            <a href="verify-ec8b.php?id=<?php echo $res['id']; ?>" class="btn-verify">
                                                <i class="fas fa-check"></i> Verify
                                            </a>
                                        <?php endif; ?>
                                        <a href="verify-ec8b.php?id=<?php echo $res['id']; ?>" class="btn-view">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($pending_results)): ?>
                            <tr>
                                <td colspan="9">
                                    <div class="empty-state">
                                        <i class="fas fa-file-alt"></i>
                                        <h4>No EC8B Results Found</h4>
                                        <p>No EC8B results have been submitted for <?php echo htmlspecialchars($state_name); ?> yet.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
function applyFilters() {
    var election = document.getElementById('electionFilter').value;
    var lga = document.getElementById('lgaFilter').value;
    
    var url = window.location.pathname;
    var params = [];
    if (election) params.push('election_id=' + election);
    if (lga) params.push('lga_id=' + lga);
    if (params.length) url += '?' + params.join('&');
    window.location.href = url;
}

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