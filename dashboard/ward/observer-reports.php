<?php
// ============================================================
// WARD COORDINATOR - OBSERVER REPORTS
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
// FETCH OBSERVERS AND REPORTS
// ============================================================
$observers = [];
$summary = [];

try {
    // Get all observers with stats
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.user_code,
            u.email,
            u.phone,
            u.status,
            u.created_at,
            pu.name as pu_name,
            pu.code as pu_code,
            (SELECT COUNT(*) FROM incidents i WHERE i.reporter_id = u.id) as incidents_reported,
            (SELECT COUNT(*) FROM incidents i WHERE i.reporter_id = u.id AND i.status = 'resolved') as incidents_resolved,
            (SELECT COUNT(*) FROM user_sessions us WHERE us.user_id = u.id AND us.is_active = 1 AND us.last_activity_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)) as is_online
        FROM users u
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        WHERE u.tenant_id = ? AND u.ward_id = ? AND u.deleted_at IS NULL
        AND EXISTS (SELECT 1 FROM roles r WHERE r.id = u.role_id AND r.level = 'observer')
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $observers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $summary['total'] = count($observers);
    $summary['active'] = count(array_filter($observers, function($a) { return $a['status'] === 'active'; }));
    $summary['online'] = count(array_filter($observers, function($a) { return (int)($a['is_online'] ?? 0) > 0; }));
    $summary['total_incidents'] = array_sum(array_column($observers, 'incidents_reported'));
    $summary['resolved_incidents'] = array_sum(array_column($observers, 'incidents_resolved'));
    
} catch (Exception $e) {
    error_log("Error fetching observers: " . $e->getMessage());
}

$page_title = 'Observer Reports';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.report-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.report-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.report-header h2 i {
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
.stat-mini .number.orange { color: #F59E0B; }
.stat-mini .number.purple { color: #8B5CF6; }
.stat-mini .label {
    font-size: 0.6rem;
    color: var(--gray-500);
    font-weight: 500;
}

.report-table {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
}
.report-table table {
    width: 100%;
    border-collapse: collapse;
}
.report-table th {
    background: var(--gray-50);
    padding: 10px 14px;
    text-align: left;
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--gray-600);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border-bottom: 1px solid var(--gray-200);
}
.report-table td {
    padding: 10px 14px;
    font-size: 0.82rem;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}
.report-table tr:hover td {
    background: var(--gray-50);
}

.status-badge {
    font-size: 0.6rem;
    padding: 2px 10px;
    border-radius: 20px;
    font-weight: 500;
}
.status-badge.active { background: #ECFDF5; color: #10B981; }
.status-badge.suspended { background: #FEF2F2; color: #EF4444; }

.export-buttons {
    display: flex;
    gap: 8px;
}
.export-buttons .btn-sm {
    padding: 4px 12px;
    font-size: 0.7rem;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.export-buttons .btn-sm.pdf { background: #FEE2E2; color: #991B1B; }
.export-buttons .btn-sm.excel { background: #D1FAE5; color: #065F46; }

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--gray-400);
}
.empty-state i {
    font-size: 2rem;
    display: block;
    margin-bottom: 8px;
}

@media (max-width: 768px) {
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
    .report-table {
        overflow-x: auto;
    }
    .report-table table {
        min-width: 700px;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="report-header">
            <div>
                <h2><i class="fas fa-eye"></i> Observer Reports</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward
                </p>
            </div>
            <div class="export-buttons">
                <a href="export-pdf.php?type=observers&ward_id=<?php echo $ward_id; ?>" class="btn-sm pdf">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
                <a href="export-excel.php?type=observers&ward_id=<?php echo $ward_id; ?>" class="btn-sm excel">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
                <a href="assign-observers.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-mini">
                <div class="number blue"><?php echo number_format($summary['total']); ?></div>
                <div class="label">Total Observers</div>
            </div>
            <div class="stat-mini">
                <div class="number green"><?php echo number_format($summary['active']); ?></div>
                <div class="label">Active</div>
            </div>
            <div class="stat-mini">
                <div class="number purple"><?php echo number_format($summary['online']); ?></div>
                <div class="label">Online Now</div>
            </div>
            <div class="stat-mini">
                <div class="number orange"><?php echo number_format($summary['total_incidents']); ?></div>
                <div class="label">Incidents Reported</div>
            </div>
            <div class="stat-mini">
                <div class="number green"><?php echo number_format($summary['resolved_incidents']); ?></div>
                <div class="label">Resolved</div>
            </div>
        </div>

        <!-- Observers Table -->
        <div class="report-table">
            <?php if (count($observers) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Observer</th>
                            <th>PU</th>
                            <th>Status</th>
                            <th>Incidents</th>
                            <th>Resolved</th>
                            <th>Online</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($observers as $observer): 
                            $is_online = (int)($observer['is_online'] ?? 0) > 0;
                        ?>
                            <tr>
                                <td>
                                    <div style="font-weight:500;"><?php echo htmlspecialchars($observer['full_name']); ?></div>
                                    <div style="font-size:0.65rem;color:var(--gray-400);">
                                        <?php echo htmlspecialchars($observer['user_code']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($observer['pu_name'])): ?>
                                        <div><?php echo htmlspecialchars($observer['pu_name']); ?></div>
                                        <div style="font-size:0.6rem;color:var(--gray-400);"><?php echo htmlspecialchars($observer['pu_code'] ?? ''); ?></div>
                                    <?php else: ?>
                                        <span style="color:var(--gray-400);font-size:0.75rem;">Not Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $observer['status']; ?>">
                                        <?php echo ucfirst($observer['status']); ?>
                                    </span>
                                </td>
                                <td><span style="color:#EF4444;"><?php echo number_format($observer['incidents_reported'] ?? 0); ?></span></td>
                                <td><span style="color:#10B981;"><?php echo number_format($observer['incidents_resolved'] ?? 0); ?></span></td>
                                <td>
                                    <?php if ($is_online): ?>
                                        <span style="color:#10B981;"><i class="fas fa-circle" style="font-size:0.4rem;"></i> Online</span>
                                    <?php else: ?>
                                        <span style="color:var(--gray-400);"><i class="fas fa-circle" style="font-size:0.4rem;"></i> Offline</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-eye"></i>
                    <p>No observers found in this ward.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
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