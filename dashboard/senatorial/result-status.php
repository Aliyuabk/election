<?php
// ============================================================
// SENATORIAL COORDINATOR - RESULT STATUS
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'senatorial') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$senatorial_id = SessionManager::get('senatorial_id');
$state_id = SessionManager::get('state_id');
$tenant_id = SessionManager::get('tenant_id');

$db = getDB();

// ============================================================
// GET SENATORIAL DISTRICT NAME
// ============================================================
$district_name = 'Senatorial District';
$state_name = 'State';
try {
    if ($senatorial_id) {
        $stmt = $db->prepare("
            SELECT s.name as state_name, sd.name as district_name 
            FROM senatorial_districts sd 
            JOIN states s ON sd.state_id = s.id 
            WHERE sd.id = ?
        ");
        $stmt->execute([$senatorial_id]);
        $result = $stmt->fetch();
        if ($result) {
            $district_name = $result['district_name'];
            $state_name = $result['state_name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching district: " . $e->getMessage());
}

// ============================================================
// GET LGA IDs FROM SENATORIAL DISTRICT
// ============================================================
$lga_ids = [];
try {
    if ($senatorial_id) {
        $stmt = $db->prepare("SELECT lgas_json FROM senatorial_districts WHERE id = ?");
        $stmt->execute([$senatorial_id]);
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
    $lga_ids = [];
}

$lga_list = !empty($lga_ids) ? implode(',', array_map('intval', $lga_ids)) : '0';

// ============================================================
// GET STATUS STATISTICS
// ============================================================
$status_stats = [];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("
            SELECT 
                r.status,
                COUNT(*) as count,
                SUM(r.total_votes_cast) as total_votes
            FROM results_ec8a r
            JOIN polling_units pu ON r.pu_id = pu.id
            JOIN wards w ON pu.ward_id = w.id
            WHERE r.tenant_id = ? AND w.lga_id IN ($lga_list)
            GROUP BY r.status
        ");
        $stmt->execute([$tenant_id]);
        $status_stats = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching status stats: " . $e->getMessage());
}

// ============================================================
// GET RECENT STATUS CHANGES
// ============================================================
$recent_changes = [];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("
            SELECT 
                r.id, r.pu_name, r.pu_code,
                r.status, r.created_at, r.verified_at,
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
            WHERE r.tenant_id = ? AND w.lga_id IN ($lga_list)
            ORDER BY r.updated_at DESC
            LIMIT 50
        ");
        $stmt->execute([$tenant_id]);
        $recent_changes = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching recent changes: " . $e->getMessage());
}

// ============================================================
// GET STATUS SUMMARY
// ============================================================
$status_summary = [
    'total' => 0,
    'verified' => 0,
    'pending' => 0,
    'flagged' => 0,
    'rejected' => 0
];

foreach ($status_stats as $stat) {
    $status_summary['total'] += $stat['count'];
    if ($stat['status'] === 'verified') $status_summary['verified'] = $stat['count'];
    elseif ($stat['status'] === 'pending') $status_summary['pending'] = $stat['count'];
    elseif ($stat['status'] === 'flagged') $status_summary['flagged'] = $stat['count'];
    elseif ($stat['status'] === 'rejected') $status_summary['rejected'] = $stat['count'];
}

$status_summary['verification_rate'] = $status_summary['total'] > 0 ? round(($status_summary['verified'] / $status_summary['total']) * 100, 1) : 0;

$page_title = 'Result Status';
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
}
.page-header h2 small {
    font-size: 0.8rem;
    font-weight: 400;
    color: var(--gray-500);
    display: block;
    margin-top: 2px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.stat-card {
    background: white;
    border-radius: var(--radius);
    padding: 18px 20px;
    border: 1px solid var(--gray-200);
    text-align: center;
}
.stat-card .stat-number {
    font-size: 1.5rem;
    font-weight: 700;
}
.stat-card .stat-label {
    font-size: 0.75rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.stat-card .stat-icon {
    font-size: 1.2rem;
    margin-bottom: 4px;
}
.stat-card .stat-change {
    font-size: 0.7rem;
    margin-top: 4px;
    color: var(--gray-400);
}
.stat-card.blue .stat-number { color: #2563EB; }
.stat-card.blue .stat-icon { color: #2563EB; }
.stat-card.green .stat-number { color: #059669; }
.stat-card.green .stat-icon { color: #059669; }
.stat-card.yellow .stat-number { color: #D97706; }
.stat-card.yellow .stat-icon { color: #D97706; }
.stat-card.orange .stat-number { color: #EA580C; }
.stat-card.orange .stat-icon { color: #EA580C; }
.stat-card.red .stat-number { color: #DC2626; }
.stat-card.red .stat-icon { color: #DC2626; }

.section-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
}
.section-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.section-card .card-header h3 {
    font-size: 0.95rem;
    font-weight: 600;
    margin: 0;
}

.table-wrap {
    overflow-x: auto;
}
.table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}
.table th {
    text-align: left;
    padding: 10px 12px;
    background: var(--gray-50);
    font-weight: 600;
    color: var(--gray-600);
    border-bottom: 2px solid var(--gray-200);
}
.table td {
    padding: 10px 12px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}
.table tr:hover td {
    background: var(--gray-50);
}

.status-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
}
.status-badge.verified { background: #D1FAE5; color: #059669; }
.status-badge.pending { background: #FEF3C7; color: #D97706; }
.status-badge.flagged { background: #FEE2E2; color: #DC2626; }
.status-badge.rejected { background: #FEE2E2; color: #DC2626; }

.btn-sm {
    padding: 4px 12px;
    border-radius: 6px;
    background: var(--primary);
    color: white;
    text-decoration: none;
    font-size: 0.7rem;
    border: none;
}
.btn-sm:hover {
    background: var(--primary-dark);
    color: white;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: var(--gray-500);
}
.empty-state i {
    font-size: 3rem;
    display: block;
    margin-bottom: 12px;
    color: var(--gray-300);
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
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
                    <i class="fas fa-check-circle" style="color:var(--primary);margin-right:8px;"></i> 
                    Result Status
                    <small><?php echo htmlspecialchars($district_name); ?> - <?php echo htmlspecialchars($state_name); ?></small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="view-results.php" class="btn btn-secondary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--gray-100);color:var(--gray-600);border:none;">
                    <i class="fas fa-arrow-left"></i> Back to Results
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                <div class="stat-number"><?php echo number_format($status_summary['total']); ?></div>
                <div class="stat-label">Total Results</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php echo number_format($status_summary['verified']); ?></div>
                <div class="stat-label">Verified</div>
                <div class="stat-change"><?php echo $status_summary['verification_rate']; ?>% of total</div>
            </div>
            <div class="stat-card yellow">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo number_format($status_summary['pending']); ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-icon"><i class="fas fa-flag"></i></div>
                <div class="stat-number"><?php echo number_format($status_summary['flagged']); ?></div>
                <div class="stat-label">Flagged</div>
            </div>
            <div class="stat-card red">
                <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                <div class="stat-number"><?php echo number_format($status_summary['rejected']); ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>

        <!-- Recent Status Changes -->
        <div class="section-card">
            <div class="card-header">
                <h3><i class="fas fa-clock" style="color:var(--primary);margin-right:6px;"></i> Recent Status Changes</h3>
                <span style="font-size:0.75rem;color:var(--gray-400);">Latest <?php echo count($recent_changes); ?> updates</span>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Polling Unit</th>
                            <th>Ward</th>
                            <th>LGA</th>
                            <th>Status</th>
                            <th>Agent</th>
                            <th>Verified By</th>
                            <th>Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recent_changes) > 0): ?>
                            <?php $i = 1; foreach ($recent_changes as $change): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($change['pu_name']); ?></strong>
                                        <br><span style="font-size:0.6rem;color:var(--gray-400);"><?php echo htmlspecialchars($change['pu_code']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($change['ward_name']); ?></td>
                                    <td><?php echo htmlspecialchars($change['lga_name']); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $change['status'] ?? 'pending'; ?>">
                                            <?php echo ucfirst($change['status'] ?? 'Pending'); ?>
                                        </span>
                                    </td>
                                    <td style="font-size:0.7rem;color:var(--gray-600);">
                                        <?php echo htmlspecialchars($change['agent_name'] ?? '—'); ?>
                                    </td>
                                    <td style="font-size:0.7rem;color:var(--gray-600);">
                                        <?php echo htmlspecialchars($change['verified_by_name'] ?? '—'); ?>
                                    </td>
                                    <td style="font-size:0.7rem;color:var(--gray-500);">
                                        <?php echo date('M j, Y', strtotime($change['created_at'] ?? 'now')); ?>
                                        <br><span style="font-size:0.6rem;color:var(--gray-400);">
                                            <?php echo date('g:i A', strtotime($change['created_at'] ?? 'now')); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="result-details.php?id=<?php echo $change['id']; ?>" class="btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <p>No result status changes found.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
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