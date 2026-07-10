<?php
// ============================================================
// LGA COORDINATOR - REVIEW EC8B
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

// Get EC8B results for review
$ec8b_results = [];
$stats = [
    'total' => 0,
    'pending' => 0,
    'verified' => 0,
    'rejected' => 0,
    'mismatch_alerts' => 0
];

try {
    $stmt = $db->prepare("
        SELECT 
            r.*,
            w.name as ward_name,
            w.code as ward_code,
            l.name as lga_name,
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
        WHERE r.tenant_id = ? AND l.id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$tenant_id, $lga_id]);
    $ec8b_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($ec8b_results as $result) {
        $stats['total']++;
        $status = $result['status'] ?? 'pending';
        $stats[$status] = ($stats[$status] ?? 0) + 1;
        if ($result['mismatch_alert'] == 1) {
            $stats['mismatch_alerts']++;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching EC8B results: " . $e->getMessage());
}

$page_title = 'Review EC8B';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
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
.stats-row .stat-box .number.mismatch { color: #DC2626; }

.stats-row .stat-box .label {
    font-size: 0.6rem;
    color: var(--gray-500);
    text-transform: uppercase;
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

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.55rem;
    padding: 2px 10px;
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
.status-badge.verified { background: #ECFDF5; color: #065F46; }
.status-badge.verified .dot { background: #10B981; }
.status-badge.rejected { background: #FEF2F2; color: #991B1B; }
.status-badge.rejected .dot { background: #EF4444; }

.action-buttons {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
}

.action-buttons a {
    padding: 3px 10px;
    border-radius: 4px;
    font-size: 0.6rem;
    font-weight: 500;
    text-decoration: none;
    transition: var(--transition);
}

.action-buttons .btn-review {
    background: #EFF6FF;
    color: #3B82F6;
}

.action-buttons .btn-review:hover {
    background: #DBEAFE;
}

.action-buttons .btn-verify {
    background: #10B981;
    color: white;
}

.action-buttons .btn-verify:hover {
    background: #059669;
}

.action-buttons .btn-reject {
    background: #FEF2F2;
    color: #DC2626;
}

.action-buttons .btn-reject:hover {
    background: #FEE2E2;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
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

@media (max-width: 768px) {
    .stats-row {
        grid-template-columns: repeat(3, 1fr);
    }
    .results-table-container {
        overflow-x: auto;
    }
    .results-table {
        font-size: 0.7rem;
    }
    .results-table th,
    .results-table td {
        padding: 6px 8px;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="welcome-section">
            <div>
                <h1><i class="fas fa-file-alt"></i> Review EC8B</h1>
                <p class="subtitle">
                    <i class="fas fa-map-marker-alt"></i> 
                    <?php echo htmlspecialchars($lga_name); ?> LGA - Review Ward-Level Results
                </p>
            </div>
            <div class="actions">
                <a href="submitted-results.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Results
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="number total"><?php echo $stats['total']; ?></div>
                <div class="label">Total EC8B</div>
            </div>
            <div class="stat-box">
                <div class="number pending"><?php echo $stats['pending']; ?></div>
                <div class="label">Pending</div>
            </div>
            <div class="stat-box">
                <div class="number verified"><?php echo $stats['verified']; ?></div>
                <div class="label">Verified</div>
            </div>
            <div class="stat-box">
                <div class="number rejected"><?php echo $stats['rejected']; ?></div>
                <div class="label">Rejected</div>
            </div>
            <div class="stat-box">
                <div class="number mismatch"><?php echo $stats['mismatch_alerts']; ?></div>
                <div class="label">Mismatch Alerts</div>
            </div>
        </div>

        <!-- Results Table -->
        <div class="results-table-container">
            <table class="results-table">
                <thead>
                    <tr>
                        <th>Ward</th>
                        <th>Election</th>
                        <th>Coordinator</th>
                        <th>EC8A Count</th>
                        <th>Valid Votes</th>
                        <th>Total Votes</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ec8b_results as $result): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($result['ward_name']); ?></strong>
                                <div style="font-size:0.6rem;color:var(--gray-400);"><?php echo htmlspecialchars($result['ward_code']); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($result['election_name']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($result['coordinator_first_name'] ?? '') . ' ' . htmlspecialchars($result['coordinator_last_name'] ?? ''); ?>
                            </td>
                            <td>
                                <?php echo number_format($result['ec8a_count']); ?>/<?php echo number_format($result['total_pus']); ?>
                            </td>
                            <td><?php echo number_format($result['valid_votes']); ?></td>
                            <td><?php echo number_format($result['total_votes']); ?></td>
                            <td>
                                <span class="status-badge <?php echo $result['status']; ?>">
                                    <span class="dot"></span>
                                    <?php echo ucfirst($result['status']); ?>
                                </span>
                                <?php if ($result['mismatch_alert'] == 1): ?>
                                    <span style="font-size:0.5rem;color:#DC2626;background:#FEF2F2;padding:1px 6px;border-radius:4px;display:inline-block;margin-top:2px;">
                                        ⚠ Mismatch
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="view-ec8b.php?id=<?php echo $result['id']; ?>" class="btn-review">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if ($result['status'] === 'pending'): ?>
                                        <a href="approve-ec8b.php?id=<?php echo $result['id']; ?>" class="btn-verify">
                                            <i class="fas fa-check"></i> Verify
                                        </a>
                                        <a href="reject-ec8b.php?id=<?php echo $result['id']; ?>" class="btn-reject" onclick="return confirm('Reject this EC8B?')">
                                            <i class="fas fa-times"></i> Reject
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($ec8b_results)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-file-alt"></i>
                                    <h4>No EC8B Results Found</h4>
                                    <p>No EC8B results have been submitted in <?php echo htmlspecialchars($lga_name); ?> yet.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
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