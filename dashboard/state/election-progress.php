<?php
// ============================================================
// STATE COORDINATOR - ELECTION PROGRESS
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
$election_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get state name
$state_name = 'Unknown State';
if (!empty($state_id)) {
    $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
    $stmt->execute([$state_id]);
    $state = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_name = $state['name'] ?? 'Unknown State';
}

// Get election details
$election = null;
if ($election_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT e.*, u.full_name as created_by_name
            FROM elections e
            LEFT JOIN users u ON e.created_by = u.id
            WHERE e.id = ? AND e.tenant_id = ? AND e.deleted_at IS NULL
        ");
        $stmt->execute([$election_id, $tenant_id]);
        $election = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching election: " . $e->getMessage());
    }
}

// If no election selected, show list of elections
if (!$election) {
    // Fetch recent elections for progress tracking
    $elections = [];
    try {
        $stmt = $db->prepare("
            SELECT id, name, type, status, election_date
            FROM elections
            WHERE tenant_id = ? AND deleted_at IS NULL
            AND (states_json LIKE ? OR states_json IS NULL OR states_json = '[]')
            ORDER BY election_date DESC
            LIMIT 10
        ");
        $stmt->execute([$tenant_id, '%"' . $state_id . '"%']);
        $elections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching elections list: " . $e->getMessage());
    }
    
    // If only one election, select it
    if (count($elections) === 1) {
        header('Location: election-progress.php?id=' . $elections[0]['id']);
        exit();
    }
}

// If election is selected, get progress data
$progress_data = [];
$lga_progress = [];
$total_pus = 0;
$reported_pus = 0;
$verified_pus = 0;

if ($election) {
    try {
        // Get total PUs and progress per LGA
        $stmt = $db->prepare("
            SELECT 
                l.id as lga_id,
                l.name as lga_name,
                COUNT(DISTINCT pu.id) as total_pus,
                COUNT(DISTINCT r.pu_id) as reported_pus,
                COUNT(DISTINCT CASE WHEN r.status IN ('verified', 'approved') THEN r.pu_id END) as verified_pus,
                COUNT(DISTINCT CASE WHEN r.status = 'pending' THEN r.pu_id END) as pending_pus,
                (SELECT COUNT(*) FROM incidents i WHERE i.lga_id = l.id AND i.election_id = ? AND i.status IN ('reported', 'acknowledged', 'investigating')) as active_incidents
            FROM lgas l
            LEFT JOIN wards w ON w.lga_id = l.id
            LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
            LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.election_id = ? AND r.tenant_id = ?
            WHERE l.state_id = ? AND l.is_active = 1
            GROUP BY l.id, l.name
            ORDER BY l.name ASC
        ");
        $stmt->execute([$election_id, $election_id, $tenant_id, $state_id]);
        $lga_progress = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate totals
        foreach ($lga_progress as $lga) {
            $total_pus += $lga['total_pus'];
            $reported_pus += $lga['reported_pus'];
            $verified_pus += $lga['verified_pus'];
        }
        
        // Get result submission timeline
        $stmt = $db->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as submissions,
                COUNT(DISTINCT pu_id) as unique_pus
            FROM results_ec8a
            WHERE election_id = ? AND tenant_id = ?
            GROUP BY DATE(created_at)
            ORDER BY date ASC
            LIMIT 30
        ");
        $stmt->execute([$election_id, $tenant_id]);
        $timeline_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get incident statistics
        $stmt = $db->prepare("
            SELECT 
                incident_type,
                severity,
                COUNT(*) as count
            FROM incidents
            WHERE election_id = ? AND state_id = ?
            GROUP BY incident_type, severity
        ");
        $stmt->execute([$election_id, $state_id]);
        $incident_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format timeline for chart
        $timeline_labels = [];
        $timeline_values = [];
        foreach ($timeline_data as $item) {
            $timeline_labels[] = date('M j', strtotime($item['date']));
            $timeline_values[] = $item['submissions'];
        }
        
    } catch (Exception $e) {
        error_log("Error fetching progress data: " . $e->getMessage());
    }
}

$election_types = [
    'presidential' => 'Presidential',
    'governorship' => 'Governorship',
    'senatorial' => 'Senatorial',
    'house_of_reps' => 'House of Reps',
    'house_of_assembly' => 'House of Assembly',
    'lga_chairman' => 'LGA Chairman',
    'councillorship' => 'Councillorship',
    'party_primary' => 'Party Primary',
    'internal_party' => 'Internal Party'
];

$page_title = 'Election Progress';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.progress-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

.progress-stat {
    background: white;
    border-radius: var(--radius);
    padding: 16px 18px;
    border: 1px solid var(--gray-200);
    text-align: center;
}

.progress-stat .number {
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--gray-800);
}

.progress-stat .label {
    font-size: 0.7rem;
    color: var(--gray-500);
}

.progress-stat .sub {
    font-size: 0.6rem;
    color: var(--gray-400);
    margin-top: 2px;
}

.progress-stat .number.green { color: #10B981; }
.progress-stat .number.yellow { color: #F59E0B; }
.progress-stat .number.red { color: #EF4444; }
.progress-stat .number.blue { color: #3B82F6; }
.progress-stat .number.purple { color: #8B5CF6; }

.lga-progress-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 14px;
    margin-top: 16px;
}

.lga-progress-card {
    background: white;
    border-radius: var(--radius);
    padding: 16px 18px;
    border: 1px solid var(--gray-200);
    transition: var(--transition);
}

.lga-progress-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-hover);
}

.lga-progress-card .lga-name {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--gray-800);
}

.lga-progress-card .lga-stats {
    display: flex;
    gap: 12px;
    margin: 8px 0;
    flex-wrap: wrap;
}

.lga-progress-card .lga-stats .stat {
    font-size: 0.7rem;
    color: var(--gray-600);
}

.lga-progress-card .lga-stats .stat .value {
    font-weight: 600;
    color: var(--gray-800);
}

.lga-progress-card .progress-bar {
    height: 6px;
    background: var(--gray-200);
    border-radius: 4px;
    overflow: hidden;
    margin: 6px 0;
}

.lga-progress-card .progress-bar .fill {
    height: 100%;
    background: var(--primary);
    border-radius: 4px;
    transition: width 0.8s ease;
}

.lga-progress-card .progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 0.6rem;
    color: var(--gray-500);
}

.lga-progress-card .lga-incidents {
    font-size: 0.65rem;
    color: #EF4444;
    margin-top: 4px;
}

.election-selector {
    background: white;
    border-radius: var(--radius);
    padding: 16px 20px;
    border: 1px solid var(--gray-200);
    margin-bottom: 20px;
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}

.election-selector select {
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.8rem;
    font-family: 'Inter', sans-serif;
    background: white;
    min-width: 200px;
}

.election-selector select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.election-selector .election-info {
    font-size: 0.8rem;
    color: var(--gray-500);
}

.election-selector .election-info strong {
    color: var(--gray-800);
}

.chart-container {
    background: white;
    border-radius: var(--radius);
    padding: 18px 20px;
    border: 1px solid var(--gray-200);
    margin-top: 16px;
}

.chart-container .chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.chart-container .chart-header h4 {
    font-size: 0.85rem;
    font-weight: 600;
    margin: 0;
}

.chart-container canvas {
    max-height: 200px;
}

@media (max-width: 768px) {
    .progress-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    .lga-progress-grid {
        grid-template-columns: 1fr;
    }
    .election-selector {
        flex-direction: column;
        align-items: stretch;
    }
    .election-selector select {
        width: 100%;
        min-width: unset;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="welcome-section">
            <div>
                <h1><i class="fas fa-chart-line"></i> Election Progress</h1>
                <p class="subtitle">
                    <i class="fas fa-flag"></i> 
                    <?php echo htmlspecialchars($state_name); ?> State - Track Election Progress
                </p>
            </div>
            <div class="actions">
                <a href="elections.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Elections
                </a>
            </div>
        </div>

        <?php if ($election): ?>
            <!-- Election Selector -->
            <div class="election-selector">
                <select id="electionSelect" onchange="window.location.href='election-progress.php?id='+this.value">
                    <?php foreach ($elections as $e): ?>
                        <option value="<?php echo $e['id']; ?>" <?php echo $e['id'] == $election_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($e['name']); ?> (<?php echo $election_types[$e['type']] ?? $e['type']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="election-info">
                    <strong><?php echo htmlspecialchars($election['name']); ?></strong>
                    <span style="margin:0 6px;">•</span>
                    <?php echo $election_types[$election['type']] ?? ucfirst($election['type']); ?>
                    <span style="margin:0 6px;">•</span>
                    <span class="status-badge <?php echo $election['status']; ?>" style="font-size:0.6rem;">
                        <span class="dot"></span>
                        <?php echo ucfirst($election['status']); ?>
                    </span>
                    <span style="margin:0 6px;">•</span>
                    <?php echo date('M j, Y', strtotime($election['election_date'])); ?>
                </div>
            </div>

            <!-- Progress Stats -->
            <div class="progress-stats">
                <div class="progress-stat">
                    <div class="number"><?php echo number_format($total_pus); ?></div>
                    <div class="label">Total Polling Units</div>
                </div>
                <div class="progress-stat">
                    <div class="number yellow"><?php echo number_format($reported_pus); ?></div>
                    <div class="label">Reported PUs</div>
                    <div class="sub"><?php echo $total_pus > 0 ? round(($reported_pus / $total_pus) * 100, 1) : 0; ?>% of total</div>
                </div>
                <div class="progress-stat">
                    <div class="number green"><?php echo number_format($verified_pus); ?></div>
                    <div class="label">Verified PUs</div>
                    <div class="sub"><?php echo $reported_pus > 0 ? round(($verified_pus / $reported_pus) * 100, 1) : 0; ?>% of reported</div>
                </div>
                <div class="progress-stat">
                    <div class="number red"><?php echo number_format($reported_pus - $verified_pus); ?></div>
                    <div class="label">Pending Verification</div>
                </div>
            </div>

            <!-- LGA Progress -->
            <h4 style="font-size:0.9rem;font-weight:600;margin:16px 0 8px;">
                <i class="fas fa-map-marker-alt" style="color:var(--primary);"></i> LGA-wise Progress
            </h4>
            <div class="lga-progress-grid">
                <?php foreach ($lga_progress as $lga): 
                    $reporting_rate = $lga['total_pus'] > 0 ? round(($lga['reported_pus'] / $lga['total_pus']) * 100, 1) : 0;
                    $verification_rate = $lga['reported_pus'] > 0 ? round(($lga['verified_pus'] / $lga['reported_pus']) * 100, 1) : 0;
                ?>
                    <div class="lga-progress-card">
                        <div class="lga-name"><?php echo htmlspecialchars($lga['lga_name']); ?></div>
                        <div class="lga-stats">
                            <span class="stat">Total: <span class="value"><?php echo number_format($lga['total_pus']); ?></span></span>
                            <span class="stat">Reported: <span class="value"><?php echo number_format($lga['reported_pus']); ?></span></span>
                            <span class="stat">Verified: <span class="value"><?php echo number_format($lga['verified_pus']); ?></span></span>
                        </div>
                        <div class="progress-bar">
                            <div class="fill" style="width: <?php echo $reporting_rate; ?>%;"></div>
                        </div>
                        <div class="progress-label">
                            <span>Reporting Rate</span>
                            <span><?php echo $reporting_rate; ?>%</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:0.6rem;color:var(--gray-500);margin-top:2px;">
                            <span>Verification: <?php echo $verification_rate; ?>%</span>
                            <?php if ($lga['active_incidents'] > 0): ?>
                                <span class="lga-incidents">
                                    <i class="fas fa-exclamation-triangle"></i> <?php echo $lga['active_incidents']; ?> incidents
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Timeline Chart -->
            <?php if (!empty($timeline_data)): ?>
            <div class="chart-container">
                <div class="chart-header">
                    <h4><i class="fas fa-calendar-alt" style="color:var(--primary);"></i> Submission Timeline</h4>
                    <span style="font-size:0.65rem;color:var(--gray-400);">Last 30 days</span>
                </div>
                <canvas id="timelineChart"></canvas>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- No election selected - show list -->
            <div style="background:white;border-radius:var(--radius);padding:30px;border:1px solid var(--gray-200);text-align:center;">
                <i class="fas fa-vote-yea" style="font-size:3rem;color:var(--gray-300);display:block;margin-bottom:12px;"></i>
                <h3 style="color:var(--gray-600);margin:0;">Select an Election</h3>
                <p style="color:var(--gray-400);margin-top:6px;">Choose an election to view progress details</p>
                
                <?php if (!empty($elections)): ?>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-top:16px;text-align:left;">
                        <?php foreach ($elections as $e): ?>
                            <a href="election-progress.php?id=<?php echo $e['id']; ?>" style="display:block;padding:14px 18px;background:var(--gray-50);border-radius:10px;text-decoration:none;color:var(--gray-700);transition:var(--transition);border:1px solid var(--gray-200);">
                                <div style="font-weight:600;font-size:0.85rem;"><?php echo htmlspecialchars($e['name']); ?></div>
                                <div style="font-size:0.7rem;color:var(--gray-500);">
                                    <?php echo $election_types[$e['type']] ?? ucfirst($e['type']); ?>
                                    <span style="margin:0 4px;">•</span>
                                    <?php echo date('M j, Y', strtotime($e['election_date'])); ?>
                                </div>
                                <div style="margin-top:4px;">
                                    <span class="status-badge <?php echo $e['status']; ?>" style="font-size:0.55rem;">
                                        <span class="dot"></span> <?php echo ucfirst($e['status']); ?>
                                    </span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color:var(--gray-400);margin-top:12px;">No elections found for <?php echo htmlspecialchars($state_name); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php if ($election && !empty($timeline_data)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Timeline Chart
var ctx = document.getElementById('timelineChart').getContext('2d');
var timelineChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($timeline_labels); ?>,
        datasets: [{
            label: 'Results Submitted',
            data: <?php echo json_encode($timeline_values); ?>,
            backgroundColor: 'rgba(59, 130, 246, 0.6)',
            borderColor: 'rgba(59, 130, 246, 1)',
            borderWidth: 1,
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    font: { size: 10 }
                }
            },
            x: {
                ticks: {
                    font: { size: 9 },
                    maxRotation: 45,
                    minRotation: 0
                }
            }
        }
    }
});
</script>
<?php endif; ?>

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