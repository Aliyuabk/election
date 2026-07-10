<?php
// ============================================================
// STATE COORDINATOR - APPROVE RESULTS
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

// Handle single result approval
$message = '';
$error = '';

if ($result_id > 0) {
    // Get result details
    try {
        $stmt = $db->prepare("
            SELECT r.*, pu.name as pu_name, e.name as election_name
            FROM results_ec8a r
            JOIN polling_units pu ON r.pu_id = pu.id
            JOIN elections e ON r.election_id = e.id
            WHERE r.id = ? AND r.tenant_id = ?
        ");
        $stmt->execute([$result_id, $tenant_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching result: " . $e->getMessage());
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve') {
        $notes = trim($_POST['notes'] ?? '');
        
        try {
            $stmt = $db->prepare("
                UPDATE results_ec8a 
                SET status = 'approved', 
                    verified_by = ?, 
                    verified_at = NOW(),
                    remarks = CONCAT(COALESCE(remarks, ''), '\n', 'Approved: ', ?)
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$user_id, $notes, $result_id, $tenant_id]);
            
            logActivity($user_id, 'ec8a_approved', 
                "Approved EC8A result ID: $result_id for PU: {$result['pu_name']}",
                'results_ec8a', $result_id
            );
            
            $message = 'Result approved successfully!';
        } catch (Exception $e) {
            $error = 'Failed to approve result: ' . $e->getMessage();
        }
    }
}

// Fetch verified results pending approval
$pending_results = [];
$stats = ['total' => 0, 'by_election' => []];

try {
    $sql = "
        SELECT 
            r.id,
            r.pu_id,
            r.valid_votes,
            r.total_votes_cast,
            r.created_at,
            pu.name as pu_name,
            pu.code as pu_code,
            w.name as ward_name,
            l.name as lga_name,
            e.id as election_id,
            e.name as election_name,
            e.type as election_type,
            u.first_name as agent_first_name,
            u.last_name as agent_last_name
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        JOIN elections e ON r.election_id = e.id
        LEFT JOIN users u ON r.agent_id = u.id
        WHERE r.tenant_id = ? 
        AND l.state_id = ?
        AND r.status = 'verified'
    ";
    
    $params = [$tenant_id, $state_id];
    
    if ($election_filter > 0) {
        $sql .= " AND r.election_id = ?";
        $params[] = $election_filter;
    }
    
    $sql .= " ORDER BY r.created_at ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $pending_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stats['total'] = count($pending_results);
    
    foreach ($pending_results as $result) {
        $election_key = $result['election_id'];
        if (!isset($stats['by_election'][$election_key])) {
            $stats['by_election'][$election_key] = [
                'name' => $result['election_name'],
                'count' => 0
            ];
        }
        $stats['by_election'][$election_key]['count']++;
    }
} catch (Exception $e) {
    error_log("Error fetching pending results: " . $e->getMessage());
}

$page_title = 'Approve Results';
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
    min-width: 180px;
}

.filter-bar select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.filter-bar .filter-info {
    font-size: 0.75rem;
    color: var(--gray-500);
    margin-left: auto;
}

.approval-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}

.approval-stat {
    background: white;
    border-radius: var(--radius);
    padding: 14px 16px;
    border: 1px solid var(--gray-200);
    text-align: center;
}

.approval-stat .number {
    font-size: 1.5rem;
    font-weight: 700;
    color: #3B82F6;
}

.approval-stat .label {
    font-size: 0.65rem;
    color: var(--gray-500);
}

.approval-stat .sub {
    font-size: 0.6rem;
    color: var(--gray-400);
    margin-top: 2px;
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

.results-table .btn-approve {
    padding: 4px 14px;
    border-radius: 6px;
    font-size: 0.65rem;
    font-weight: 500;
    text-decoration: none;
    background: #10B981;
    color: white;
    border: none;
    cursor: pointer;
    transition: var(--transition);
}

.results-table .btn-approve:hover {
    background: #059669;
}

.results-table .btn-view {
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 0.65rem;
    font-weight: 500;
    text-decoration: none;
    background: var(--gray-100);
    color: var(--gray-700);
    transition: var(--transition);
}

.results-table .btn-view:hover {
    background: var(--gray-200);
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

/* Modal Styles */
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-overlay.active {
    display: flex;
}

.modal {
    background: white;
    border-radius: var(--radius);
    padding: 28px 32px;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal .modal-title {
    font-size: 1.1rem;
    font-weight: 700;
    margin: 0 0 4px;
}

.modal .modal-subtitle {
    font-size: 0.8rem;
    color: var(--gray-500);
    margin: 0 0 16px;
}

.modal .form-group {
    margin-bottom: 14px;
}

.modal .form-group label {
    display: block;
    font-weight: 600;
    font-size: 0.8rem;
    color: var(--gray-700);
    margin-bottom: 4px;
}

.modal .form-group textarea {
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

.modal .form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.modal .btn-group {
    display: flex;
    gap: 10px;
    margin-top: 8px;
}

.modal .btn-group .btn-approve {
    padding: 10px 24px;
    background: #10B981;
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
    flex: 1;
}

.modal .btn-group .btn-approve:hover {
    background: #059669;
}

.modal .btn-group .btn-cancel {
    padding: 10px 24px;
    background: var(--gray-100);
    color: var(--gray-700);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.modal .btn-group .btn-cancel:hover {
    background: var(--gray-200);
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
    .approval-stats {
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
    .modal {
        padding: 20px;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="welcome-section">
            <div>
                <h1><i class="fas fa-check-double"></i> Approve Results</h1>
                <p class="subtitle">
                    <i class="fas fa-flag"></i> 
                    <?php echo htmlspecialchars($state_name); ?> State - Approve Verified Results
                </p>
            </div>
        </div>

        <!-- Stats -->
        <div class="approval-stats">
            <div class="approval-stat">
                <div class="number"><?php echo number_format($stats['total']); ?></div>
                <div class="label">Pending Approval</div>
            </div>
            <?php foreach ($stats['by_election'] as $election): ?>
                <div class="approval-stat">
                    <div class="number" style="font-size:1.2rem;"><?php echo number_format($election['count']); ?></div>
                    <div class="label"><?php echo htmlspecialchars($election['name']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Filter -->
        <div class="filter-bar">
            <select id="electionFilter" onchange="window.location.href='?election_id='+this.value">
                <option value="0">All Elections</option>
                <?php foreach ($elections as $e): ?>
                    <option value="<?php echo $e['id']; ?>" <?php echo $election_filter == $e['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($e['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <span class="filter-info">
                <i class="fas fa-list"></i> <?php echo number_format($stats['total']); ?> results pending approval
            </span>
        </div>

        <!-- Results Table -->
        <div class="results-table-container">
            <table class="results-table">
                <thead>
                    <tr>
                        <th>PU</th>
                        <th>LGA</th>
                        <th>Ward</th>
                        <th>Election</th>
                        <th>Agent</th>
                        <th>Valid Votes</th>
                        <th>Total Votes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_results as $result): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($result['pu_name']); ?></strong>
                                <div style="font-size:0.6rem;color:var(--gray-400);"><?php echo htmlspecialchars($result['pu_code']); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($result['lga_name']); ?></td>
                            <td><?php echo htmlspecialchars($result['ward_name']); ?></td>
                            <td><?php echo htmlspecialchars($result['election_name']); ?></td>
                            <td><?php echo htmlspecialchars($result['agent_first_name'] ?? '') . ' ' . htmlspecialchars($result['agent_last_name'] ?? ''); ?></td>
                            <td><?php echo number_format($result['valid_votes']); ?></td>
                            <td><?php echo number_format($result['total_votes_cast']); ?></td>
                            <td>
                                <button class="btn-approve" onclick="openApproveModal(<?php echo $result['id']; ?>, '<?php echo htmlspecialchars($result['pu_name']); ?>')">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <a href="verify-ec8a.php?id=<?php echo $result['id']; ?>" class="btn-view">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($pending_results)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-check-double"></i>
                                    <h4>No Results Pending Approval</h4>
                                    <p>All verified results have been approved or there are no results to approve.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Approval Modal -->
<div class="modal-overlay" id="approveModal">
    <div class="modal">
        <h3 class="modal-title"><i class="fas fa-check-circle" style="color:#10B981;"></i> Approve Result</h3>
        <p class="modal-subtitle">Approve result for <strong id="modalPuName"></strong></p>
        
        <form method="POST" action="" id="approveForm">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="result_id" id="modalResultId">
            
            <div class="form-group">
                <label for="notes">Approval Notes (Optional)</label>
                <textarea name="notes" id="notes" placeholder="Add any notes about this approval..."></textarea>
            </div>
            
            <div class="btn-group">
                <button type="button" class="btn-cancel" onclick="closeApproveModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn-approve">
                    <i class="fas fa-check"></i> Approve Result
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openApproveModal(id, puName) {
    document.getElementById('modalResultId').value = id;
    document.getElementById('modalPuName').textContent = puName;
    document.getElementById('approveModal').classList.add('active');
    document.getElementById('approveForm').action = 'approve-results.php?id=' + id;
}

function closeApproveModal() {
    document.getElementById('approveModal').classList.remove('active');
}

// Close modal on overlay click
document.getElementById('approveModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeApproveModal();
    }
});

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