<?php
// ============================================================
// WARD COORDINATOR - POLLING UNIT DETAILS
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
// GET PU ID
// ============================================================
$pu_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($pu_id <= 0) {
    header('Location: polling-units.php');
    exit();
}

// ============================================================
// FETCH PU DETAILS
// ============================================================
$pu = null;
$stats = [];
$agents = [];

try {
    // Get PU details
    $stmt = $db->prepare("
        SELECT 
            pu.*,
            w.name as ward_name,
            l.name as lga_name,
            s.name as state_name
        FROM polling_units pu
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        JOIN states s ON l.state_id = s.id
        WHERE pu.id = ? AND pu.ward_id = ?
    ");
    $stmt->execute([$pu_id, $ward_id]);
    $pu = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pu) {
        header('Location: polling-units.php?error=notfound');
        exit();
    }
    
    // Get statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT u.id) as total_agents,
            COUNT(DISTINCT CASE WHEN u.status = 'active' THEN u.id END) as active_agents,
            COUNT(DISTINCT r.id) as total_submissions,
            SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending,
            COUNT(DISTINCT i.id) as total_incidents,
            SUM(CASE WHEN i.status IN ('reported', 'investigating') THEN 1 ELSE 0 END) as active_incidents
        FROM polling_units pu
        LEFT JOIN users u ON u.pu_id = pu.id AND u.deleted_at IS NULL
        LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
        LEFT JOIN incidents i ON i.pu_id = pu.id
        WHERE pu.id = ?
    ");
    $stmt->execute([$tenant_id, $pu_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get agents
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.user_code,
            u.email,
            u.phone,
            u.status,
            r.name as role_name
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.pu_id = ? AND u.deleted_at IS NULL
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$pu_id]);
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching PU details: " . $e->getMessage());
    header('Location: polling-units.php?error=db');
    exit();
}

$page_title = 'Polling Unit Details';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.detail-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.detail-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.detail-header h2 i {
    color: var(--primary);
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
.info-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px;
}
.info-card .label {
    font-size: 0.7rem;
    color: var(--gray-500);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.info-card .value {
    font-size: 1rem;
    font-weight: 600;
    margin-top: 4px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}
.stat-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 12px 14px;
    text-align: center;
}
.stat-card .number {
    font-size: 1.3rem;
    font-weight: 700;
}
.stat-card .number.green { color: #10B981; }
.stat-card .number.blue { color: #3B82F6; }
.stat-card .number.orange { color: #F59E0B; }
.stat-card .number.red { color: #EF4444; }
.stat-card .label {
    font-size: 0.65rem;
    color: var(--gray-500);
    font-weight: 500;
}

.agents-table {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
}
.agents-table table {
    width: 100%;
    border-collapse: collapse;
}
.agents-table th {
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
.agents-table td {
    padding: 10px 14px;
    font-size: 0.82rem;
    border-bottom: 1px solid var(--gray-100);
}
.agents-table tr:hover td {
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
.status-badge.pending { background: #FFFBEB; color: #F59E0B; }

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

.back-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.82rem;
    text-decoration: none;
    color: var(--gray-700);
    transition: var(--transition);
}
.back-btn:hover {
    background: var(--gray-50);
}

@media (max-width: 768px) {
    .info-grid {
        grid-template-columns: 1fr 1fr;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="detail-header">
            <div>
                <h2><i class="fas fa-flag-checkered"></i> Polling Unit Details</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($pu['ward_name'] ?? ''); ?> Ward
                </p>
            </div>
            <div>
                <a href="polling-units.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <?php if ($pu): ?>
            <!-- Information -->
            <div class="info-grid">
                <div class="info-card">
                    <div class="label">PU Name</div>
                    <div class="value"><?php echo htmlspecialchars($pu['name']); ?></div>
                </div>
                <div class="info-card">
                    <div class="label">Code</div>
                    <div class="value"><?php echo htmlspecialchars($pu['code']); ?></div>
                </div>
                <div class="info-card">
                    <div class="label">Ward</div>
                    <div class="value"><?php echo htmlspecialchars($pu['ward_name']); ?></div>
                </div>
                <div class="info-card">
                    <div class="label">LGA</div>
                    <div class="value"><?php echo htmlspecialchars($pu['lga_name']); ?></div>
                </div>
                <div class="info-card">
                    <div class="label">State</div>
                    <div class="value"><?php echo htmlspecialchars($pu['state_name']); ?></div>
                </div>
                <div class="info-card">
                    <div class="label">Type</div>
                    <div class="value"><?php echo ($pu['is_rural'] ?? 0) ? 'Rural' : 'Urban'; ?></div>
                </div>
                <div class="info-card">
                    <div class="label">Status</div>
                    <div class="value"><?php echo ($pu['is_active'] ?? 0) ? 'Active' : 'Inactive'; ?></div>
                </div>
                <?php if (!empty($pu['gps_lat']) && !empty($pu['gps_lng'])): ?>
                    <div class="info-card">
                        <div class="label">GPS Location</div>
                        <div class="value"><?php echo round($pu['gps_lat'], 6); ?>, <?php echo round($pu['gps_lng'], 6); ?></div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="number blue"><?php echo number_format($pu['registered_voters'] ?? 0); ?></div>
                    <div class="label">Registered Voters</div>
                </div>
                <div class="stat-card">
                    <div class="number blue"><?php echo number_format($stats['total_agents'] ?? 0); ?></div>
                    <div class="label">Total Agents</div>
                </div>
                <div class="stat-card">
                    <div class="number green"><?php echo number_format($stats['active_agents'] ?? 0); ?></div>
                    <div class="label">Active Agents</div>
                </div>
                <div class="stat-card">
                    <div class="number green"><?php echo number_format($stats['verified'] ?? 0); ?></div>
                    <div class="label">Verified Results</div>
                </div>
                <div class="stat-card">
                    <div class="number orange"><?php echo number_format($stats['pending'] ?? 0); ?></div>
                    <div class="label">Pending Results</div>
                </div>
                <div class="stat-card">
                    <div class="number red"><?php echo number_format($stats['total_incidents'] ?? 0); ?></div>
                    <div class="label">Total Incidents</div>
                </div>
            </div>

            <!-- Agents -->
            <div class="agents-table">
                <h3 style="padding:12px 16px;margin:0;font-size:0.9rem;border-bottom:1px solid var(--gray-200);">
                    <i class="fas fa-user-tie"></i> Assigned Agents
                    <span style="font-weight:400;font-size:0.75rem;color:var(--gray-400);margin-left:8px;">
                        <?php echo count($agents); ?> agents
                    </span>
                </h3>
                <?php if (count($agents) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Code</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($agents as $agent): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($agent['full_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($agent['user_code']); ?></td>
                                    <td><?php echo htmlspecialchars($agent['email'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($agent['phone'] ?? 'N/A'); ?></td>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $agent['role_name'] ?? 'Agent')); ?></td>
                                    <td><span class="status-badge <?php echo $agent['status']; ?>"><?php echo ucfirst($agent['status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>No agents assigned to this polling unit.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
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