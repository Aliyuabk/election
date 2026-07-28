<?php
// ============================================================
// SENATORIAL COORDINATOR - FEDERAL CONSTITUENCY DETAILS
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

$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$state_id = SessionManager::get('state_id');
$db = getDB();

// Get FC ID from URL
$fc_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($fc_id <= 0) {
    header('Location: monitor-district.php');
    exit();
}

// ============================================================
// GET FEDERAL CONSTITUENCY DETAILS
// ============================================================
$fc = null;
try {
    $stmt = $db->prepare("
        SELECT 
            fc.*,
            s.name as state_name,
            s.id as state_id
        FROM federal_constituencies fc
        JOIN states s ON fc.state_id = s.id
        WHERE fc.id = ? AND fc.is_active = 1
    ");
    $stmt->execute([$fc_id]);
    $fc = $stmt->fetch();
} catch (Exception $e) {
    error_log("Error fetching FC: " . $e->getMessage());
}

if (!$fc) {
    header('Location: monitor-district.php');
    exit();
}

// ============================================================
// PARSE LGAS FROM JSON
// ============================================================
$lga_names = [];
if (!empty($fc['lgas_json'])) {
    $lga_names = json_decode($fc['lgas_json'], true) ?: [];
}

// Get LGA IDs from names
$lga_ids = [];
if (!empty($lga_names)) {
    try {
        $placeholders = implode(',', array_fill(0, count($lga_names), '?'));
        $stmt = $db->prepare("SELECT id, name FROM lgas WHERE name IN ($placeholders) AND state_id = ? AND is_active = 1");
        $stmt->execute(array_merge($lga_names, [$fc['state_id']]));
        $lga_ids = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {
        error_log("Error getting LGA IDs: " . $e->getMessage());
    }
}

$lga_list = !empty($lga_ids) ? implode(',', array_map('intval', array_keys($lga_ids))) : '0';

// ============================================================
// GET STATISTICS
// ============================================================
$stats = [
    'total_lgas' => count($lga_ids),
    'total_wards' => 0,
    'total_pus' => 0,
    'total_agents' => 0,
    'total_results' => 0,
    'verified_results' => 0,
    'total_incidents' => 0
];

try {
    if ($lga_list !== '0') {
        // Wards and PUs
        $stmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT w.id) as total_wards,
                COUNT(DISTINCT pu.id) as total_pus
            FROM lgas l
            LEFT JOIN wards w ON w.lga_id = l.id AND w.is_active = 1
            LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
            WHERE l.id IN ($lga_list)
        ");
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['total_wards'] = (int)($result['total_wards'] ?? 0);
        $stats['total_pus'] = (int)($result['total_pus'] ?? 0);
        
        // Agents
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT u.id) as total_agents
            FROM users u
            JOIN roles r ON u.role_id = r.id
            JOIN polling_units pu ON u.pu_id = pu.id
            JOIN wards w ON pu.ward_id = w.id
            WHERE u.tenant_id = ? AND u.status = 'active'
            AND r.level IN ('pu_agent', 'party_agent', 'observer')
            AND w.lga_id IN ($lga_list)
        ");
        $stmt->execute([$tenant_id]);
        $stats['total_agents'] = (int)$stmt->fetchColumn();
        
        // Results
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_results,
                SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified_results
            FROM results_ec8a r
            JOIN polling_units pu ON r.pu_id = pu.id
            JOIN wards w ON pu.ward_id = w.id
            WHERE r.tenant_id = ? AND w.lga_id IN ($lga_list)
        ");
        $stmt->execute([$tenant_id]);
        $result = $stmt->fetch();
        $stats['total_results'] = (int)($result['total_results'] ?? 0);
        $stats['verified_results'] = (int)($result['verified_results'] ?? 0);
        
        // Incidents
        $stmt = $db->prepare("
            SELECT COUNT(*) as total_incidents
            FROM incidents i
            WHERE i.tenant_id = ? AND i.lga_id IN ($lga_list)
        ");
        $stmt->execute([$tenant_id]);
        $stats['total_incidents'] = (int)$stmt->fetchColumn();
    }
} catch (Exception $e) {
    error_log("Error fetching stats: " . $e->getMessage());
}

// ============================================================
// GET LGA LIST WITH DETAILS
// ============================================================
$lgas = [];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("
            SELECT 
                l.*,
                COUNT(DISTINCT w.id) as ward_count,
                COUNT(DISTINCT pu.id) as pu_count,
                (SELECT COUNT(*) FROM users u 
                 JOIN polling_units pu2 ON u.pu_id = pu2.id 
                 JOIN wards w2 ON pu2.ward_id = w2.id 
                 WHERE w2.lga_id = l.id AND u.tenant_id = ? AND u.status = 'active') as agent_count
            FROM lgas l
            LEFT JOIN wards w ON w.lga_id = l.id AND w.is_active = 1
            LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
            WHERE l.id IN ($lga_list)
            GROUP BY l.id
            ORDER BY l.name ASC
        ");
        $stmt->execute([$tenant_id]);
        $lgas = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching LGAs: " . $e->getMessage());
}

// ============================================================
// GET RECENT RESULTS
// ============================================================
$recent_results = [];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("
            SELECT 
                r.*,
                pu.name as pu_name,
                pu.code as pu_code,
                w.name as ward_name,
                u.full_name as agent_name,
                e.name as election_name
            FROM results_ec8a r
            JOIN polling_units pu ON r.pu_id = pu.id
            JOIN wards w ON pu.ward_id = w.id
            LEFT JOIN users u ON r.agent_id = u.id
            LEFT JOIN elections e ON r.election_id = e.id
            WHERE r.tenant_id = ? AND w.lga_id IN ($lga_list)
            ORDER BY r.created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$tenant_id]);
        $recent_results = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching recent results: " . $e->getMessage());
}

$page_title = 'Federal Constituency Details';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.fc-header {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 30px;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 16px;
}
.fc-header .header-left h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.fc-header .header-left .subtitle {
    color: var(--gray-500);
    font-size: 0.85rem;
    margin-top: 4px;
}
.fc-header .header-left .subtitle i {
    margin: 0 4px;
    font-size: 0.6rem;
    color: var(--gray-300);
}
.fc-header .header-right .badge-status {
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}
.badge-status.active { background: #D1FAE5; color: #059669; }

.stats-grid-fc {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    margin-bottom: 24px;
}

.fc-tabs {
    display: flex;
    gap: 4px;
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 4px;
    margin-bottom: 24px;
    overflow-x: auto;
}
.fc-tabs .tab-btn {
    padding: 10px 20px;
    border: none;
    background: transparent;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--gray-600);
    cursor: pointer;
    transition: var(--transition);
    white-space: nowrap;
}
.fc-tabs .tab-btn:hover {
    background: var(--gray-50);
    color: var(--gray-800);
}
.fc-tabs .tab-btn.active {
    background: var(--primary);
    color: white;
}
.fc-tabs .tab-btn .count {
    display: inline-block;
    background: var(--gray-200);
    color: var(--gray-600);
    padding: 0 8px;
    border-radius: 10px;
    font-size: 0.7rem;
    margin-left: 4px;
}
.fc-tabs .tab-btn.active .count {
    background: rgba(255,255,255,0.3);
    color: white;
}

.tab-content {
    display: none;
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
}
.tab-content.active {
    display: block;
}

.table-responsive {
    overflow-x: auto;
}
.table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}
.table th {
    text-align: left;
    padding: 10px 12px;
    background: var(--gray-50);
    font-weight: 600;
    color: var(--gray-600);
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
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
.lga-link {
    color: var(--primary);
    text-decoration: none;
}
.lga-link:hover {
    text-decoration: underline;
}
.status-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 6px;
}
.status-dot.verified { background: #10B981; }
.status-dot.pending { background: #F59E0B; }
.status-dot.rejected { background: #EF4444; }

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--gray-500);
}
.empty-state i {
    font-size: 2.5rem;
    color: var(--gray-300);
    margin-bottom: 12px;
}

@media (max-width: 768px) {
    .fc-header {
        flex-direction: column;
    }
    .stats-grid-fc {
        grid-template-columns: repeat(2, 1fr);
    }
    .fc-tabs .tab-btn {
        padding: 8px 14px;
        font-size: 0.75rem;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- FC Header -->
        <div class="fc-header">
            <div class="header-left">
                <h2>
                    <i class="fas fa-university" style="color:var(--primary);"></i>
                    <?php echo htmlspecialchars($fc['name'] ?? 'Federal Constituency'); ?>
                </h2>
                <div class="subtitle">
                    <?php echo htmlspecialchars($fc['code'] ?? 'N/A'); ?>
                    <i class="fas fa-chevron-right"></i>
                    <?php echo htmlspecialchars($fc['state_name'] ?? 'N/A'); ?>
                    <i class="fas fa-chevron-right"></i>
                    <?php echo count($lga_ids); ?> LGAs
                </div>
            </div>
            <div class="header-right">
                <span class="badge-status active">
                    <i class="fas fa-check-circle"></i> Active
                </span>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid-fc">
            <div class="stat-card-small">
                <div class="number"><?php echo number_format($stats['total_lgas']); ?></div>
                <div class="label">LGAs</div>
            </div>
            <div class="stat-card-small">
                <div class="number"><?php echo number_format($stats['total_wards']); ?></div>
                <div class="label">Wards</div>
            </div>
            <div class="stat-card-small">
                <div class="number"><?php echo number_format($stats['total_pus']); ?></div>
                <div class="label">Polling Units</div>
            </div>
            <div class="stat-card-small">
                <div class="number"><?php echo number_format($stats['total_agents']); ?></div>
                <div class="label">Agents</div>
            </div>
            <div class="stat-card-small">
                <div class="number"><?php echo number_format($stats['total_results']); ?></div>
                <div class="label">Results</div>
            </div>
            <div class="stat-card-small">
                <div class="number"><?php echo number_format($stats['verified_results']); ?></div>
                <div class="label">Verified</div>
            </div>
            <div class="stat-card-small">
                <div class="number"><?php echo number_format($stats['total_incidents']); ?></div>
                <div class="label">Incidents</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="fc-tabs">
            <button class="tab-btn active" data-tab="lgas">
                <i class="fas fa-building"></i> LGAs
                <span class="count"><?php echo count($lgas); ?></span>
            </button>
            <button class="tab-btn" data-tab="results">
                <i class="fas fa-file-alt"></i> Recent Results
                <span class="count"><?php echo count($recent_results); ?></span>
            </button>
        </div>

        <!-- Tab: LGAs -->
        <div class="tab-content active" id="tab-lgas">
            <?php if (count($lgas) > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>LGA Name</th>
                                <th>Wards</th>
                                <th>Polling Units</th>
                                <th>Agents</th>
                                <th>Registered Voters</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lgas as $lga): ?>
                                <tr>
                                    <td>
                                        <a href="view-lga-details.php?id=<?php echo $lga['id']; ?>" class="lga-link">
                                            <?php echo htmlspecialchars($lga['name']); ?>
                                        </a>
                                        <div style="font-size:0.7rem;color:var(--gray-400);">
                                            Code: <?php echo htmlspecialchars($lga['code'] ?? 'N/A'); ?>
                                        </div>
                                    </td>
                                    <td><?php echo number_format($lga['ward_count'] ?? 0); ?></td>
                                    <td><?php echo number_format($lga['pu_count'] ?? 0); ?></td>
                                    <td><?php echo number_format($lga['agent_count'] ?? 0); ?></td>
                                    <td><?php echo number_format($lga['registered_voters'] ?? 0); ?></td>
                                    <td>
                                        <a href="view-lga-details.php?id=<?php echo $lga['id']; ?>" 
                                           style="color:var(--primary);text-decoration:none;font-size:0.8rem;">
                                            View Details <i class="fas fa-arrow-right"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-building"></i>
                    <p>No LGAs found in this federal constituency.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab: Results -->
        <div class="tab-content" id="tab-results">
            <?php if (count($recent_results) > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>PU</th>
                                <th>Ward</th>
                                <th>Election</th>
                                <th>Valid Votes</th>
                                <th>Status</th>
                                <th>Submitted At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_results as $result): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:500;"><?php echo htmlspecialchars($result['pu_name'] ?? 'N/A'); ?></div>
                                        <div style="font-size:0.7rem;color:var(--gray-400);">
                                            <?php echo htmlspecialchars($result['pu_code'] ?? ''); ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($result['ward_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($result['election_name'] ?? 'N/A'); ?>
                                        <div style="font-size:0.7rem;color:var(--gray-400);text-transform:capitalize;">
                                            <?php echo str_replace('_', ' ', $result['election_type'] ?? ''); ?>
                                        </div>
                                    </td>
                                    <td><?php echo number_format($result['valid_votes'] ?? 0); ?></td>
                                    <td>
                                        <span class="status-dot <?php echo $result['status'] ?? 'pending'; ?>"></span>
                                        <?php echo ucfirst($result['status'] ?? 'Pending'); ?>
                                    </td>
                                    <td style="font-size:0.8rem;color:var(--gray-500);">
                                        <?php echo !empty($result['created_at']) ? date('M d, Y g:i A', strtotime($result['created_at'])) : 'N/A'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <p>No results submitted in this federal constituency.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
// Tab switching
document.querySelectorAll('.tab-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-btn').forEach(function(b) {
            b.classList.remove('active');
        });
        document.querySelectorAll('.tab-content').forEach(function(c) {
            c.classList.remove('active');
        });
        this.classList.add('active');
        var tabId = this.dataset.tab;
        document.getElementById('tab-' + tabId).classList.add('active');
    });
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