<?php
// ============================================================
// LGA COORDINATOR - APPROVAL HISTORY
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

// Filters
$ward_filter = isset($_GET['ward_id']) ? (int)$_GET['ward_id'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Get wards for filter
$wards = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM wards WHERE lga_id = ? AND is_active = 1 ORDER BY name ASC");
    $stmt->execute([$lga_id]);
    $wards = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching wards: " . $e->getMessage());
}

// Fetch approval history
$history = [];
$stats = ['total' => 0, 'approved' => 0, 'rejected' => 0, 'flagged' => 0];

try {
    $sql = "
        SELECT 
            r.id,
            r.pu_id,
            r.valid_votes,
            r.total_votes_cast,
            r.status,
            r.created_at,
            r.verified_at,
            r.rejection_reason,
            pu.name as pu_name,
            pu.code as pu_code,
            w.name as ward_name,
            w.id as ward_id,
            e.name as election_name,
            u.first_name as agent_first_name,
            u.last_name as agent_last_name,
            vu.first_name as verifier_first_name,
            vu.last_name as verifier_last_name
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        JOIN elections e ON r.election_id = e.id
        LEFT JOIN users u ON r.agent_id = u.id
        LEFT JOIN users vu ON r.verified_by = vu.id
        WHERE r.tenant_id = ? AND w.lga_id = ?
        AND r.status IN ('approved', 'rejected', 'flagged')
    ";
    $params = [$tenant_id, $lga_id];
    
    if ($ward_filter > 0) {
        $sql .= " AND w.id = ?";
        $params[] = $ward_filter;
    }
    
    if (!empty($status_filter)) {
        $sql .= " AND r.status = ?";
        $params[] = $status_filter;
    }
    
    $sql .= " ORDER BY r.verified_at DESC LIMIT 100";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($history as $item) {
        $stats['total']++;
        $stats[$item['status']] = ($stats[$item['status']] ?? 0) + 1;
    }
} catch (Exception $e) {
    error_log("Error fetching approval history: " . $e->getMessage());
}

$page_title = 'Approval History';
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
    padding: 12px 18px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}

.filter-bar select {
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.8rem;
    font-family: 'Inter', sans-serif;
    background: white;
    min-width: 150px;
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

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}

.stats-row .stat-box {
    background: white;
    border-radius: 8px;
    padding: 10px 12px;
    border: 1px solid var(--gray-200);
    text-align: center;
}

.stats-row .stat-box .number {
    font-size: 1.1rem;
    font-weight: 700;
}

.stats-row .stat-box .number.approved { color: #10B981; }
.stats-row .stat-box .number.rejected { color: #EF4444; }
.stats-row .stat-box .number.flagged { color: #8B5CF6; }

.stats-row .stat-box .label {
    font-size: 0.55rem;
    color: var(--gray-500);
    text-transform: uppercase;
}

.history-table-container {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
}

.history-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8rem;
}

.history-table th {
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

.history-table td {
    padding: 8px 10px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}

.history-table tr:hover td {
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

.status-badge.approved { background: #ECFDF5; color: #065F46; }
.status-badge.approved .dot { background: #10B981; }
.status-badge.rejected { background: #FEF2F2; color: #991B1B; }
.status-badge.rejected .dot { background: #EF4444; }
.status-badge.flagged { background: #F5F3FF; color: #5B21B6; }
.status-badge.flagged .dot { background: #8B5CF6; }

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
        grid-template-columns: repeat(3, 1fr);
    }
    .history-table-container {
        overflow-x: auto;
    }
    .history-table {
        font-size: 0.7rem;
    }
    .history-table th,
    .history-table td {
        padding: 4px 6px;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="welcome-section">
            <div>
                <h1><i class="fas fa-history"></i> Approval History</h1>
                <p class="subtitle">
                    <i class="fas fa-map-marker-alt"></i> 
                    <?php echo htmlspecialchars($lga_name); ?> LGA - Result Approval History
                </p>
            </div>
            <div class="actions">
                <a href="submitted-results.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="number" style="color:#3B82F6;"><?php echo $stats['total']; ?></div>
                <div class="label">Total</div>
            </div>
            <div class="stat-box">
                <div class="number approved"><?php echo $stats['approved']; ?></div>
                <div class="label">Approved</div>
            </div>
            <div class="stat-box">
                <div class="number rejected"><?php echo $stats['rejected']; ?></div>
                <div class="label">Rejected</div>
            </div>
            <div class="stat-box">
                <div class="number flagged"><?php echo $stats['flagged']; ?></div>
                <div class="label">Flagged</div>
            </div>
        </div>

        <!-- Filter -->
        <div class="filter-bar">
            <select id="wardFilter" onchange="applyFilters()">
                <option value="0">All Wards</option>
                <?php foreach ($wards as $w): ?>
                    <option value="<?php echo $w['id']; ?>" <?php echo $ward_filter == $w['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($w['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select id="statusFilter" onchange="applyFilters()">
                <option value="">All Status</option>
                <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                <option value="flagged" <?php echo $status_filter === 'flagged' ? 'selected' : ''; ?>>Flagged</option>
            </select>

            <button class="btn-filter" onclick="applyFilters()" style="padding:8px 24px;background:var(--primary);color:white;border:none;border-radius:8px;font-weight:600;font-size:0.8rem;cursor:pointer;">
                <i class="fas fa-filter"></i> Apply
            </button>

            <span class="filter-info">
                <i class="fas fa-list"></i> <?php echo $stats['total']; ?> records found
            </span>
        </div>

        <!-- History Table -->
        <div class="history-table-container">
            <table class="history-table">
                <thead>
                    <tr>
                        <th>PU</th>
                        <th>Ward</th>
                        <th>Election</th>
                        <th>Agent</th>
                        <th>Votes</th>
                        <th>Status</th>
                        <th>Verified By</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $item): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($item['pu_name']); ?></strong>
                                <div style="font-size:0.55rem;color:var(--gray-400);"><?php echo htmlspecialchars($item['pu_code']); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($item['ward_name']); ?></td>
                            <td><?php echo htmlspecialchars($item['election_name'] ?? 'N/A'); ?></td>
                            <td>
                                <?php echo htmlspecialchars($item['agent_first_name'] ?? '') . ' ' . htmlspecialchars($item['agent_last_name'] ?? ''); ?>
                            </td>
                            <td><?php echo number_format($item['valid_votes']); ?></td>
                            <td>
                                <span class="status-badge <?php echo $item['status']; ?>">
                                    <span class="dot"></span>
                                    <?php echo ucfirst($item['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($item['verifier_first_name'] ?? '') . ' ' . htmlspecialchars($item['verifier_last_name'] ?? ''); ?>
                            </td>
                            <td style="font-size:0.65rem;color:var(--gray-500);">
                                <?php echo date('M j, Y g:i A', strtotime($item['verified_at'] ?? $item['created_at'])); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($history)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-history"></i>
                                    <h4>No History Found</h4>
                                    <p>No approval history found in <?php echo htmlspecialchars($lga_name); ?>.</p>
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
function applyFilters() {
    var ward = document.getElementById('wardFilter').value;
    var status = document.getElementById('statusFilter').value;
    
    var url = window.location.pathname;
    var params = [];
    if (ward && ward !== '0') params.push('ward_id=' + ward);
    if (status) params.push('status=' + status);
    if (params.length) url += '?' + params.join('&');
    window.location.href = url;
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