<?php
// ============================================================
// SENATORIAL COORDINATOR - CHECK-IN STATUS
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
// GET CHECK-IN DATA
// ============================================================
$checkins = [];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("
            SELECT 
                c.id as checkin_id,
                c.pu_id,
                c.agent_id,
                c.checkin_type,
                c.gps_lat,
                c.gps_lng,
                c.created_at as checkin_time,
                pu.name as pu_name,
                pu.code as pu_code,
                w.name as ward_name,
                l.name as lga_name,
                u.full_name as agent_name,
                u.phone as agent_phone,
                u.email as agent_email,
                r.id as result_id,
                r.status as result_status
            FROM agent_checkins c
            JOIN polling_units pu ON c.pu_id = pu.id
            JOIN wards w ON pu.ward_id = w.id
            JOIN lgas l ON w.lga_id = l.id
            JOIN users u ON c.agent_id = u.id
            LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
            WHERE l.id IN ($lga_list) AND c.checkin_type = 'arrival'
            ORDER BY c.created_at DESC
            LIMIT 100
        ");
        $stmt->execute([$tenant_id]);
        $checkins = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching check-ins: " . $e->getMessage());
    $checkins = [];
}

// ============================================================
// GET STATISTICS
// ============================================================
$stats = [
    'total_checkins' => count($checkins),
    'today_checkins' => 0,
    'with_results' => 0,
    'without_results' => 0,
    'by_lga' => []
];

foreach ($checkins as $c) {
    if (date('Y-m-d', strtotime($c['checkin_time'])) === date('Y-m-d')) {
        $stats['today_checkins']++;
    }
    
    if ($c['result_id']) {
        $stats['with_results']++;
    } else {
        $stats['without_results']++;
    }
    
    $lga_name = $c['lga_name'];
    if (!isset($stats['by_lga'][$lga_name])) {
        $stats['by_lga'][$lga_name] = 0;
    }
    $stats['by_lga'][$lga_name]++;
}

// ============================================================
// GET LGAS FOR FILTER
// ============================================================
$lgas = [];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("SELECT id, name FROM lgas WHERE id IN ($lga_list) AND is_active = 1 ORDER BY name ASC");
        $stmt->execute();
        $lgas = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching LGAs: " . $e->getMessage());
}

$page_title = 'Check-in Status';
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
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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
.stat-card.blue .stat-number { color: #2563EB; }
.stat-card.blue .stat-icon { color: #2563EB; }
.stat-card.green .stat-number { color: #059669; }
.stat-card.green .stat-icon { color: #059669; }
.stat-card.purple .stat-number { color: #7C3AED; }
.stat-card.purple .stat-icon { color: #7C3AED; }
.stat-card.orange .stat-number { color: #EA580C; }
.stat-card.orange .stat-icon { color: #EA580C; }
.stat-card.teal .stat-number { color: #0D9488; }
.stat-card.teal .stat-icon { color: #0D9488; }

.section-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
    margin-bottom: 20px;
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
.section-card .card-header a {
    font-size: 0.75rem;
    color: var(--primary);
    text-decoration: none;
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
.status-badge.checked-in { background: #D1FAE5; color: #059669; }
.status-badge.has-result { background: #DBEAFE; color: #2563EB; }
.status-badge.no-result { background: #FEF3C7; color: #D97706; }

.chip {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.65rem;
    background: var(--gray-100);
    color: var(--gray-600);
}

.filters-row {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}
.filters-row select,
.filters-row input {
    padding: 8px 14px;
    border: 1.5px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.8rem;
    background: white;
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
                    <i class="fas fa-sign-in-alt" style="color:var(--primary);margin-right:8px;"></i> 
                    Agent Check-in Status
                    <small><?php echo htmlspecialchars($district_name); ?> - <?php echo htmlspecialchars($state_name); ?></small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="monitor-district.php" class="btn btn-secondary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--gray-100);color:var(--gray-600);border:none;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_checkins']); ?></div>
                <div class="stat-label">Total Check-ins</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                <div class="stat-number"><?php echo number_format($stats['today_checkins']); ?></div>
                <div class="stat-label">Today's Check-ins</div>
            </div>
            <div class="stat-card purple">
                <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                <div class="stat-number"><?php echo number_format($stats['with_results']); ?></div>
                <div class="stat-label">With Results</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo number_format($stats['without_results']); ?></div>
                <div class="stat-label">No Results Yet</div>
            </div>
        </div>

        <!-- Check-in by LGA -->
        <div class="section-card">
            <div class="card-header">
                <h3><i class="fas fa-map-marker-alt" style="color:var(--primary);margin-right:6px;"></i> Check-in by LGA</h3>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                <?php if (count($stats['by_lga']) > 0): ?>
                    <?php foreach ($stats['by_lga'] as $lga_name => $count): ?>
                        <div class="chip" style="background:#EDE9FE;color:#7C3AED;padding:6px 16px;">
                            <?php echo htmlspecialchars($lga_name); ?>: <?php echo $count; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color:var(--gray-500);padding:8px 0;">No check-in data available</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Check-in List -->
        <div class="section-card">
            <div class="card-header">
                <h3><i class="fas fa-list" style="color:var(--primary);margin-right:6px;"></i> Recent Check-ins</h3>
                <span style="font-size:0.75rem;color:var(--gray-400);">Latest <?php echo count($checkins); ?> check-ins</span>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Agent</th>
                            <th>PU</th>
                            <th>Ward</th>
                            <th>LGA</th>
                            <th>Check-in Time</th>
                            <th>Status</th>
                            <th>Location</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($checkins) > 0): ?>
                            <?php $i = 1; foreach ($checkins as $c): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($c['agent_name']); ?></strong>
                                        <br><span style="font-size:0.65rem;color:var(--gray-400);"><?php echo htmlspecialchars($c['agent_phone'] ?? ''); ?></span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($c['pu_name']); ?>
                                        <br><span style="font-size:0.65rem;color:var(--gray-400);"><?php echo htmlspecialchars($c['pu_code']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($c['ward_name']); ?></td>
                                    <td><?php echo htmlspecialchars($c['lga_name']); ?></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($c['checkin_time'])); ?></td>
                                    <td>
                                        <span class="status-badge checked-in">Checked In</span>
                                        <?php if ($c['result_id']): ?>
                                            <br><span class="status-badge has-result" style="margin-top:2px;">Has Result</span>
                                        <?php else: ?>
                                            <br><span class="status-badge no-result" style="margin-top:2px;">No Result</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($c['gps_lat'] && $c['gps_lng']): ?>
                                            <a href="https://maps.google.com/?q=<?php echo $c['gps_lat']; ?>,<?php echo $c['gps_lng']; ?>" target="_blank" style="font-size:0.7rem;color:var(--primary);text-decoration:none;">
                                                <i class="fas fa-map-pin"></i> View
                                            </a>
                                        <?php else: ?>
                                            <span style="font-size:0.7rem;color:var(--gray-400);">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center;padding:30px;color:var(--gray-500);">
                                    <i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
                                    No check-in records found.
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