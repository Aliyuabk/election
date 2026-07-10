<?php
// ============================================================
// LGA COORDINATOR - VIEW POLLING UNIT RESULTS
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
$pu_id = isset($_GET['pu_id']) ? (int)$_GET['pu_id'] : 0;

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

if ($pu_id <= 0) {
    header('Location: polling-units.php');
    exit();
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

// Get PU details
$pu = null;
try {
    $stmt = $db->prepare("
        SELECT 
            pu.*,
            w.name as ward_name,
            w.id as ward_id,
            l.name as lga_name
        FROM polling_units pu
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        WHERE pu.id = ? AND l.id = ?
    ");
    $stmt->execute([$pu_id, $lga_id]);
    $pu = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching PU: " . $e->getMessage());
}

if (!$pu) {
    header('Location: polling-units.php');
    exit();
}

// Get results for this PU
$results = [];
$summary = [
    'total' => 0,
    'pending' => 0,
    'verified' => 0,
    'approved' => 0,
    'rejected' => 0
];

try {
    $stmt = $db->prepare("
        SELECT 
            r.*,
            u.first_name as agent_first_name,
            u.last_name as agent_last_name,
            u.email as agent_email,
            u.phone as agent_phone,
            e.name as election_name,
            vu.first_name as verifier_first_name,
            vu.last_name as verifier_last_name
        FROM results_ec8a r
        LEFT JOIN users u ON r.agent_id = u.id
        LEFT JOIN elections e ON r.election_id = e.id
        LEFT JOIN users vu ON r.verified_by = vu.id
        WHERE r.pu_id = ? AND r.tenant_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$pu_id, $tenant_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $r) {
        $summary['total']++;
        $status = $r['status'] ?? 'pending';
        $summary[$status] = ($summary[$status] ?? 0) + 1;
    }
} catch (Exception $e) {
    error_log("Error fetching results: " . $e->getMessage());
}

// Get party votes for the latest result
$party_votes = [];
$latest_result = null;
if (!empty($results)) {
    $latest_result = $results[0];
    if (!empty($latest_result['party_votes_json'])) {
        $party_votes = json_decode($latest_result['party_votes_json'], true) ?: [];
    }
}

// Get agents assigned to this PU
$agents = [];
try {
    $stmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.status
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.pu_id = ? AND r.level = 'pu_agent' AND u.deleted_at IS NULL
        ORDER BY u.first_name ASC
    ");
    $stmt->execute([$pu_id]);
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching agents: " . $e->getMessage());
}

$page_title = 'PU Results';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.pu-container {
    max-width: 1000px;
    margin: 0 auto;
}

.pu-header {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px 24px;
    margin-bottom: 20px;
}

.pu-header .pu-name {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--gray-800);
}

.pu-header .pu-details {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    font-size: 0.8rem;
    color: var(--gray-500);
    margin-top: 4px;
}

.pu-header .pu-details span i {
    margin-right: 4px;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}

.summary-card {
    background: white;
    border-radius: var(--radius);
    padding: 10px 12px;
    border: 1px solid var(--gray-200);
    text-align: center;
}

.summary-card .number {
    font-size: 1.2rem;
    font-weight: 700;
}

.summary-card .number.total { color: #3B82F6; }
.summary-card .number.pending { color: #F59E0B; }
.summary-card .number.verified { color: #8B5CF6; }
.summary-card .number.approved { color: #10B981; }
.summary-card .number.rejected { color: #EF4444; }

.summary-card .label {
    font-size: 0.55rem;
    color: var(--gray-500);
    text-transform: uppercase;
}

.results-table-container {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    margin-bottom: 20px;
}

.results-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}

.results-table th {
    background: var(--gray-50);
    padding: 8px 10px;
    text-align: left;
    font-weight: 600;
    color: var(--gray-700);
    border-bottom: 1px solid var(--gray-200);
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.results-table td {
    padding: 8px 10px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}

.results-table tr:hover td {
    background: var(--gray-50);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.5rem;
    padding: 2px 8px;
    border-radius: 8px;
    font-weight: 600;
}

.status-badge .dot {
    width: 4px;
    height: 4px;
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

.party-votes {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px 20px;
    margin-bottom: 20px;
}

.party-votes .title {
    font-weight: 600;
    font-size: 0.85rem;
    margin-bottom: 10px;
    color: var(--gray-700);
}

.party-votes .title i {
    color: var(--primary);
    margin-right: 6px;
}

.party-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 8px;
}

.party-item {
    background: var(--gray-50);
    border-radius: 6px;
    padding: 8px 12px;
    text-align: center;
    border: 1px solid var(--gray-200);
}

.party-item .party {
    font-weight: 600;
    font-size: 0.7rem;
    color: var(--gray-700);
}

.party-item .votes {
    font-size: 1rem;
    font-weight: 700;
    color: var(--primary);
}

.agents-container {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px 20px;
}

.agents-container .title {
    font-weight: 600;
    font-size: 0.85rem;
    margin-bottom: 10px;
    color: var(--gray-700);
}

.agents-container .title i {
    color: var(--primary);
    margin-right: 6px;
}

.agent-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
    border-bottom: 1px solid var(--gray-100);
    font-size: 0.8rem;
}

.agent-item:last-child {
    border-bottom: none;
}

.agent-item .agent-info .name {
    font-weight: 500;
}

.agent-item .agent-info .details {
    font-size: 0.65rem;
    color: var(--gray-500);
}

.empty-state {
    text-align: center;
    padding: 30px 20px;
    color: var(--gray-400);
}

.empty-state i {
    font-size: 2rem;
    display: block;
    margin-bottom: 8px;
}

@media (max-width: 768px) {
    .pu-header .pu-details {
        flex-direction: column;
        gap: 4px;
    }
    .summary-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    .results-table-container {
        overflow-x: auto;
    }
    .results-table {
        font-size: 0.7rem;
    }
    .party-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="pu-container">
            <!-- PU Header -->
            <div class="pu-header">
                <div class="pu-name">
                    <i class="fas fa-flag-checkered" style="color:var(--primary);"></i>
                    <?php echo htmlspecialchars($pu['name']); ?>
                </div>
                <div class="pu-details">
                    <span><i class="fas fa-code"></i> Code: <?php echo htmlspecialchars($pu['code']); ?></span>
                    <span><i class="fas fa-layer-group"></i> Ward: <?php echo htmlspecialchars($pu['ward_name']); ?></span>
                    <span><i class="fas fa-map-marker-alt"></i> LGA: <?php echo htmlspecialchars($pu['lga_name']); ?></span>
                    <span><i class="fas fa-users"></i> Voters: <?php echo number_format($pu['registered_voters']); ?></span>
                    <?php if ($pu['gps_lat'] && $pu['gps_lng']): ?>
                        <span><i class="fas fa-map-pin"></i> GPS: <?php echo number_format($pu['gps_lat'], 6); ?>, <?php echo number_format($pu['gps_lng'], 6); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Summary Stats -->
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="number total"><?php echo $summary['total']; ?></div>
                    <div class="label">Total Results</div>
                </div>
                <div class="summary-card">
                    <div class="number pending"><?php echo $summary['pending']; ?></div>
                    <div class="label">Pending</div>
                </div>
                <div class="summary-card">
                    <div class="number verified"><?php echo $summary['verified']; ?></div>
                    <div class="label">Verified</div>
                </div>
                <div class="summary-card">
                    <div class="number approved"><?php echo $summary['approved']; ?></div>
                    <div class="label">Approved</div>
                </div>
                <div class="summary-card">
                    <div class="number rejected"><?php echo $summary['rejected']; ?></div>
                    <div class="label">Rejected</div>
                </div>
            </div>

            <!-- Party Votes (Latest Result) -->
            <?php if (!empty($party_votes) && $latest_result): ?>
                <div class="party-votes">
                    <div class="title">
                        <i class="fas fa-vote-yea"></i> Latest Result - <?php echo date('M j, Y g:i A', strtotime($latest_result['created_at'])); ?>
                        <span class="status-badge <?php echo $latest_result['status']; ?>" style="margin-left:8px;">
                            <span class="dot"></span>
                            <?php echo ucfirst($latest_result['status']); ?>
                        </span>
                    </div>
                    <div class="party-grid">
                        <?php foreach ($party_votes as $party => $votes): ?>
                            <div class="party-item">
                                <div class="party"><?php echo htmlspecialchars($party); ?></div>
                                <div class="votes"><?php echo number_format($votes); ?></div>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($latest_result['valid_votes'] > 0): ?>
                            <div class="party-item" style="background:#F0F9FF;border-color:#BAE6FD;">
                                <div class="party" style="color:#0369A1;">Valid Votes</div>
                                <div class="votes" style="color:#0369A1;"><?php echo number_format($latest_result['valid_votes']); ?></div>
                            </div>
                            <div class="party-item" style="background:#FEF2F2;border-color:#FECACA;">
                                <div class="party" style="color:#991B1B;">Rejected</div>
                                <div class="votes" style="color:#991B1B;"><?php echo number_format($latest_result['rejected_votes']); ?></div>
                            </div>
                            <div class="party-item" style="background:#F3F4F6;border-color:#D1D5DB;">
                                <div class="party" style="color:#6B7280;">Total</div>
                                <div class="votes" style="color:#6B7280;"><?php echo number_format($latest_result['total_votes_cast']); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($latest_result['remarks'])): ?>
                        <div style="margin-top:8px;font-size:0.75rem;color:var(--gray-500);">
                            <i class="fas fa-comment"></i> <?php echo htmlspecialchars($latest_result['remarks']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Results Table -->
            <div class="results-table-container">
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Election</th>
                            <th>Agent</th>
                            <th>Valid Votes</th>
                            <th>Total Votes</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $result): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($result['election_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($result['agent_first_name'] ?? '') . ' ' . htmlspecialchars($result['agent_last_name'] ?? ''); ?>
                                </td>
                                <td><?php echo number_format($result['valid_votes']); ?></td>
                                <td><?php echo number_format($result['total_votes_cast']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $result['status']; ?>">
                                        <span class="dot"></span>
                                        <?php echo ucfirst($result['status']); ?>
                                    </span>
                                </td>
                                <td style="font-size:0.7rem;color:var(--gray-500);">
                                    <?php echo date('M j, Y g:i A', strtotime($result['created_at'])); ?>
                                </td>
                                <td>
                                    <a href="view-result.php?id=<?php echo $result['id']; ?>" style="padding:2px 10px;border-radius:4px;font-size:0.6rem;font-weight:500;text-decoration:none;background:var(--gray-100);color:var(--gray-700);transition:var(--transition);">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($results)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="fas fa-file-alt"></i>
                                        <p>No results have been submitted for this polling unit.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Agents -->
            <div class="agents-container">
                <div class="title">
                    <i class="fas fa-users"></i> Assigned Agents
                    <span style="font-size:0.7rem;color:var(--gray-400);font-weight:400;margin-left:8px;">
                        (<?php echo count($agents); ?> total)
                    </span>
                </div>
                <?php if (!empty($agents)): ?>
                    <?php foreach ($agents as $agent): ?>
                        <div class="agent-item">
                            <div class="agent-info">
                                <div class="name">
                                    <?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']); ?>
                                    <span class="status-badge <?php echo $agent['status']; ?>" style="margin-left:6px;font-size:0.5rem;">
                                        <span class="dot"></span>
                                        <?php echo ucfirst($agent['status']); ?>
                                    </span>
                                </div>
                                <div class="details">
                                    <?php if (!empty($agent['email'])): ?>
                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($agent['email']); ?>
                                    <?php endif; ?>
                                    <?php if (!empty($agent['phone'])): ?>
                                        <span style="margin-left:8px;"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($agent['phone']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <a href="agent-profile.php?id=<?php echo $agent['id']; ?>" style="padding:2px 10px;border-radius:4px;font-size:0.6rem;font-weight:500;text-decoration:none;background:var(--primary);color:white;transition:var(--transition);">
                                <i class="fas fa-id-card"></i> Profile
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state" style="padding:12px;">
                        <p style="font-size:0.8rem;">No agents assigned to this polling unit.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Back Button -->
            <div style="margin-top:16px;">
                <a href="polling-units.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Polling Units
                </a>
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