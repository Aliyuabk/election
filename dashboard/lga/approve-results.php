<?php
// ============================================================
// LGA COORDINATOR - APPROVE RESULTS
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'lga') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'LGA Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$lga_id = SessionManager::get('lga_id');

if (empty($lga_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT lga_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['lga_id'])) {
            $lga_id = $user['lga_id'];
            SessionManager::set('lga_id', $lga_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching lga_id: " . $e->getMessage());
    }
}

$db = getDB();

// Get LGA name
$lga_name = 'LGA';
try {
    if ($lga_id) {
        $stmt = $db->prepare("SELECT name FROM lgas WHERE id = ?");
        $stmt->execute([$lga_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $lga_name = $result['name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching LGA: " . $e->getMessage());
}

// Get pending results for approval (EC8A)
$pending_results = [];
$stats = ['total' => 0, 'by_ward' => []];

try {
    $stmt = $db->prepare("
        SELECT 
            r.id,
            r.pu_id,
            r.valid_votes,
            r.total_votes_cast,
            r.created_at,
            pu.name as pu_name,
            pu.code as pu_code,
            w.name as ward_name,
            w.id as ward_id,
            e.name as election_name,
            u.first_name as agent_first_name,
            u.last_name as agent_last_name
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        JOIN elections e ON r.election_id = e.id
        LEFT JOIN users u ON r.agent_id = u.id
        WHERE r.tenant_id = ? 
        AND w.lga_id = ?
        AND r.status IN ('pending', 'verified')
        ORDER BY r.created_at ASC
    ");
    $stmt->execute([$tenant_id, $lga_id]);
    $pending_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stats['total'] = count($pending_results);
    
    foreach ($pending_results as $result) {
        $ward = $result['ward_name'];
        if (!isset($stats['by_ward'][$ward])) {
            $stats['by_ward'][$ward] = 0;
        }
        $stats['by_ward'][$ward]++;
    }
} catch (Exception $e) {
    error_log("Error fetching pending results: " . $e->getMessage());
}

// Handle single approval
$message = '';
$error = '';
$result_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($result_id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'approve') {
            $stmt = $db->prepare("
                UPDATE results_ec8a 
                SET status = 'approved', 
                    verified_by = ?, 
                    verified_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$user_id, $result_id, $tenant_id]);
            
            logActivity($user_id, 'result_approved', "Approved result ID: $result_id", 'results_ec8a', $result_id);
            $message = "Result approved successfully!";
        } elseif ($action === 'reject') {
            $reason = trim($_POST['reason'] ?? 'No reason provided');
            $stmt = $db->prepare("
                UPDATE results_ec8a 
                SET status = 'rejected', 
                    rejection_reason = ?,
                    verified_by = ?, 
                    verified_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$reason, $user_id, $result_id, $tenant_id]);
            
            logActivity($user_id, 'result_rejected', "Rejected result ID: $result_id - Reason: $reason", 'results_ec8a', $result_id);
            $message = "Result rejected successfully!";
        }
        
        // Refresh the list
        $stmt = $db->prepare("
            SELECT 
                r.id,
                r.pu_id,
                r.valid_votes,
                r.total_votes_cast,
                r.created_at,
                pu.name as pu_name,
                pu.code as pu_code,
                w.name as ward_name,
                w.id as ward_id,
                e.name as election_name,
                u.first_name as agent_first_name,
                u.last_name as agent_last_name
            FROM results_ec8a r
            JOIN polling_units pu ON r.pu_id = pu.id
            JOIN wards w ON pu.ward_id = w.id
            JOIN elections e ON r.election_id = e.id
            LEFT JOIN users u ON r.agent_id = u.id
            WHERE r.tenant_id = ? 
            AND w.lga_id = ?
            AND r.status IN ('pending', 'verified')
            ORDER BY r.created_at ASC
        ");
        $stmt->execute([$tenant_id, $lga_id]);
        $pending_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stats['total'] = count($pending_results);
        
    } catch (Exception $e) {
        $error = 'Failed to process: ' . $e->getMessage();
    }
}

$page_title = 'Approve Results';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.approval-container {
    max-width: 900px;
    margin: 0 auto;
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
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
    font-size: 1.4rem;
    font-weight: 700;
    color: #F59E0B;
}

.stats-row .stat-box .label {
    font-size: 0.6rem;
    color: var(--gray-500);
    text-transform: uppercase;
}

.result-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px 20px;
    margin-bottom: 12px;
    transition: var(--transition);
}

.result-card:hover {
    border-color: var(--primary);
}

.result-card .result-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 8px;
}

.result-card .result-header .pu-name {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--gray-800);
}

.result-card .result-header .pu-code {
    font-size: 0.6rem;
    color: var(--gray-400);
}

.result-card .result-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 8px;
    margin: 8px 0;
    padding: 8px 0;
    border-top: 1px solid var(--gray-100);
    border-bottom: 1px solid var(--gray-100);
}

.result-card .result-details .detail-item {
    font-size: 0.7rem;
    color: var(--gray-600);
}

.result-card .result-details .detail-item .value {
    font-weight: 600;
    color: var(--gray-800);
}

.result-card .result-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 8px;
}

.result-card .result-actions button {
    padding: 6px 16px;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.75rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.result-card .result-actions .btn-approve {
    background: #10B981;
    color: white;
}

.result-card .result-actions .btn-approve:hover {
    background: #059669;
}

.result-card .result-actions .btn-reject {
    background: #EF4444;
    color: white;
}

.result-card .result-actions .btn-reject:hover {
    background: #DC2626;
}

.result-card .result-actions .btn-review {
    background: #EFF6FF;
    color: #3B82F6;
}

.result-card .result-actions .btn-review:hover {
    background: #DBEAFE;
}

.result-card .reject-form {
    display: none;
    margin-top: 8px;
    padding: 10px 14px;
    background: var(--gray-50);
    border-radius: 8px;
}

.result-card .reject-form.active {
    display: block;
}

.result-card .reject-form textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: 6px;
    font-size: 0.8rem;
    font-family: 'Inter', sans-serif;
    resize: vertical;
    min-height: 60px;
}

.result-card .reject-form .btn-confirm {
    margin-top: 6px;
    padding: 6px 16px;
    background: #EF4444;
    color: white;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.75rem;
    cursor: pointer;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}

.empty-state i {
    font-size: 3rem;
    color: var(--gray-300);
    display: block;
    margin-bottom: 12px;
}

.empty-state h3 {
    color: var(--gray-600);
    margin: 0;
}

.empty-state p {
    color: var(--gray-400);
    margin-top: 6px;
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
    .result-card .result-header {
        flex-direction: column;
    }
    .result-card .result-details {
        grid-template-columns: 1fr 1fr;
    }
    .result-card .result-actions {
        flex-direction: column;
    }
    .result-card .result-actions button {
        width: 100%;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="approval-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-check-double"></i> Approve Results</h1>
                    <p class="subtitle">
                        <i class="fas fa-map-marker-alt"></i> 
                        <?php echo htmlspecialchars($lga_name); ?> LGA - Approve Pending Results
                    </p>
                </div>
                <div class="actions">
                    <a href="submitted-results.php" class="btn-secondary-sm">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
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

            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-box">
                    <div class="number"><?php echo $stats['total']; ?></div>
                    <div class="label">Pending Approval</div>
                </div>
                <?php foreach ($stats['by_ward'] as $ward => $count): ?>
                    <div class="stat-box">
                        <div class="number" style="font-size:1rem;"><?php echo $count; ?></div>
                        <div class="label"><?php echo htmlspecialchars($ward); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Results List -->
            <?php if (!empty($pending_results)): ?>
                <?php foreach ($pending_results as $result): ?>
                    <div class="result-card" id="result-<?php echo $result['id']; ?>">
                        <div class="result-header">
                            <div>
                                <div class="pu-name">
                                    <i class="fas fa-flag-checkered" style="color:var(--primary);"></i>
                                    <?php echo htmlspecialchars($result['pu_name']); ?>
                                </div>
                                <div class="pu-code">
                                    Code: <?php echo htmlspecialchars($result['pu_code']); ?>
                                    <span style="margin:0 6px;">•</span>
                                    <?php echo htmlspecialchars($result['ward_name']); ?>
                                    <span style="margin:0 6px;">•</span>
                                    <?php echo htmlspecialchars($result['election_name']); ?>
                                </div>
                            </div>
                            <div style="font-size:0.7rem;color:var(--gray-400);">
                                Submitted: <?php echo date('M j, Y g:i A', strtotime($result['created_at'])); ?>
                            </div>
                        </div>

                        <div class="result-details">
                            <div class="detail-item">
                                <div class="value"><?php echo number_format($result['valid_votes']); ?></div>
                                <div>Valid Votes</div>
                            </div>
                            <div class="detail-item">
                                <div class="value"><?php echo number_format($result['total_votes_cast']); ?></div>
                                <div>Total Votes</div>
                            </div>
                            <div class="detail-item">
                                <div class="value"><?php echo htmlspecialchars($result['agent_first_name'] ?? '') . ' ' . htmlspecialchars($result['agent_last_name'] ?? ''); ?></div>
                                <div>Agent</div>
                            </div>
                        </div>

                        <div class="result-actions">
                            <form method="POST" action="" style="display:inline;">
                                <input type="hidden" name="action" value="approve" />
                                <input type="hidden" name="result_id" value="<?php echo $result['id']; ?>" />
                                <button type="submit" class="btn-approve" onclick="return confirm('Approve this result?')">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                            </form>
                            
                            <button class="btn-reject" onclick="toggleReject(<?php echo $result['id']; ?>)">
                                <i class="fas fa-times"></i> Reject
                            </button>
                            
                            <a href="view-result.php?id=<?php echo $result['id']; ?>" class="btn-review" style="padding:6px 16px;border-radius:6px;font-size:0.75rem;font-weight:600;text-decoration:none;background:#EFF6FF;color:#3B82F6;display:inline-flex;align-items:center;gap:4px;">
                                <i class="fas fa-eye"></i> Review
                            </a>
                        </div>

                        <div class="reject-form" id="reject-form-<?php echo $result['id']; ?>">
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="reject" />
                                <input type="hidden" name="result_id" value="<?php echo $result['id']; ?>" />
                                <textarea name="reason" placeholder="Reason for rejection..." required></textarea>
                                <div style="display:flex;gap:8px;margin-top:6px;">
                                    <button type="submit" class="btn-confirm">
                                        <i class="fas fa-times"></i> Confirm Rejection
                                    </button>
                                    <button type="button" class="btn-review" onclick="toggleReject(<?php echo $result['id']; ?>)" style="padding:6px 16px;border-radius:6px;font-size:0.75rem;font-weight:600;border:none;cursor:pointer;background:#EFF6FF;color:#3B82F6;">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-double"></i>
                    <h3>No Pending Results</h3>
                    <p>All results in <?php echo htmlspecialchars($lga_name); ?> have been approved.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
function toggleReject(id) {
    var form = document.getElementById('reject-form-' + id);
    if (form) {
        form.classList.toggle('active');
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