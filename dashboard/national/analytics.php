<?php
// ============================================================
// NATIONAL COORDINATOR - ELECTION ANALYTICS
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

// Only national coordinator can access
if (SessionManager::get('role_level') !== 'national') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');

// Get election ID
$election_id = isset($_GET['election']) ? intval($_GET['election']) : 0;

$db = getDB();

// ============================================================
// FETCH ELECTIONS FOR FILTER
// ============================================================
$elections = [];
try {
    $stmt = $db->prepare("SELECT id, name, status FROM elections WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY election_date DESC");
    $stmt->execute([$tenant_id]);
    $elections = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $elections = [];
}

// ============================================================
// FETCH ELECTION DATA
// ============================================================
$election_name = 'All Elections';
$stats = [
    'total_votes' => 0,
    'verified_votes' => 0,
    'pending_votes' => 0,
    'total_pus' => 0,
    'reporting_pus' => 0,
    'total_incidents' => 0,
    'critical_incidents' => 0,
    'total_states' => 0,
    'states_reporting' => 0,
    'total_lgas' => 0,
    'lgas_reporting' => 0,
    'turnout' => 0,
    'reporting_percent' => 0,
    'by_state' => [],
    'by_party' => [],
    'trend' => [],
    'daily_submissions' => []
];

try {
    // Get election name
    if ($election_id > 0) {
        $stmt = $db->prepare("SELECT name FROM elections WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$election_id, $tenant_id]);
        $election_name = $stmt->fetchColumn() ?: 'Election';
    }
    
    // Build location filters
    $state_ids = [];
    $lga_ids = [];
    $ward_ids = [];
    $pu_ids = [];
    
    if ($election_id > 0) {
        $stmt = $db->prepare("SELECT states_json, lgas_json, wards_json, pus_json FROM elections WHERE id = ?");
        $stmt->execute([$election_id]);
        $locations = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($locations) {
            $state_ids = json_decode($locations['states_json'] ?? '[]', true);
            $lga_ids = json_decode($locations['lgas_json'] ?? '[]', true);
            $ward_ids = json_decode($locations['wards_json'] ?? '[]', true);
            $pu_ids = json_decode($locations['pus_json'] ?? '[]', true);
        }
    }
    
    // Get state list
    $state_placeholders = !empty($state_ids) ? implode(',', array_fill(0, count($state_ids), '?')) : '';
    
    // Total votes
    $query = "SELECT SUM(total_votes_cast) as total, SUM(valid_votes) as valid, SUM(CASE WHEN status = 'verified' THEN total_votes_cast ELSE 0 END) as verified, SUM(CASE WHEN status = 'pending' THEN total_votes_cast ELSE 0 END) as pending FROM results_ec8a WHERE tenant_id = ?";
    $params = [$tenant_id];
    
    if (!empty($pu_ids)) {
        $placeholders = implode(',', array_fill(0, count($pu_ids), '?'));
        $query .= " AND pu_id IN ($placeholders)";
        $params = array_merge($params, $pu_ids);
    } elseif (!empty($ward_ids)) {
        $placeholders = implode(',', array_fill(0, count($ward_ids), '?'));
        $query .= " AND ward_id IN ($placeholders)";
        $params = array_merge($params, $ward_ids);
    } elseif (!empty($lga_ids)) {
        $placeholders = implode(',', array_fill(0, count($lga_ids), '?'));
        $query .= " AND lga_id IN ($placeholders)";
        $params = array_merge($params, $lga_ids);
    } elseif (!empty($state_ids)) {
        $placeholders = implode(',', array_fill(0, count($state_ids), '?'));
        $query .= " AND state_id IN ($placeholders)";
        $params = array_merge($params, $state_ids);
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stats['total_votes'] = $result['total'] ?? 0;
    $stats['verified_votes'] = $result['verified'] ?? 0;
    $stats['pending_votes'] = $result['pending'] ?? 0;
    
    // Total PUs
    $query = "SELECT COUNT(*) as count FROM polling_units pu JOIN wards w ON pu.ward_id = w.id JOIN lgas l ON w.lga_id = l.id JOIN states s ON l.state_id = s.id WHERE 1=1";
    $params = [];
    
    if (!empty($pu_ids)) {
        $placeholders = implode(',', array_fill(0, count($pu_ids), '?'));
        $query .= " AND pu.id IN ($placeholders)";
        $params = $pu_ids;
    } elseif (!empty($ward_ids)) {
        $placeholders = implode(',', array_fill(0, count($ward_ids), '?'));
        $query .= " AND pu.ward_id IN ($placeholders)";
        $params = $ward_ids;
    } elseif (!empty($lga_ids)) {
        $placeholders = implode(',', array_fill(0, count($lga_ids), '?'));
        $query .= " AND w.lga_id IN ($placeholders)";
        $params = $lga_ids;
    } elseif (!empty($state_ids)) {
        $placeholders = implode(',', array_fill(0, count($state_ids), '?'));
        $query .= " AND l.state_id IN ($placeholders)";
        $params = $state_ids;
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $stats['total_pus'] = $stmt->fetchColumn() ?: 0;
    
    // Reporting PUs
    $query = "SELECT COUNT(DISTINCT pu_id) as count FROM results_ec8a WHERE tenant_id = ?";
    $params = [$tenant_id];
    
    if (!empty($pu_ids)) {
        $placeholders = implode(',', array_fill(0, count($pu_ids), '?'));
        $query .= " AND pu_id IN ($placeholders)";
        $params = array_merge($params, $pu_ids);
    } elseif (!empty($ward_ids)) {
        $placeholders = implode(',', array_fill(0, count($ward_ids), '?'));
        $query .= " AND ward_id IN ($placeholders)";
        $params = array_merge($params, $ward_ids);
    } elseif (!empty($lga_ids)) {
        $placeholders = implode(',', array_fill(0, count($lga_ids), '?'));
        $query .= " AND lga_id IN ($placeholders)";
        $params = array_merge($params, $lga_ids);
    } elseif (!empty($state_ids)) {
        $placeholders = implode(',', array_fill(0, count($state_ids), '?'));
        $query .= " AND state_id IN ($placeholders)";
        $params = array_merge($params, $state_ids);
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $stats['reporting_pus'] = $stmt->fetchColumn() ?: 0;
    
    $stats['reporting_percent'] = $stats['total_pus'] > 0 ? round(($stats['reporting_pus'] / $stats['total_pus']) * 100) : 0;
    
    // Incidents
    $query = "SELECT COUNT(*) as total, SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical FROM incidents WHERE tenant_id = ?";
    $params = [$tenant_id];
    
    if (!empty($state_ids)) {
        $placeholders = implode(',', array_fill(0, count($state_ids), '?'));
        $query .= " AND state_id IN ($placeholders)";
        $params = array_merge($params, $state_ids);
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_incidents'] = $result['total'] ?? 0;
    $stats['critical_incidents'] = $result['critical'] ?? 0;
    
    // States reporting
    $query = "SELECT COUNT(DISTINCT r.state_id) as count FROM results_ec8a r WHERE r.tenant_id = ?";
    $params = [$tenant_id];
    
    if (!empty($state_ids)) {
        $placeholders = implode(',', array_fill(0, count($state_ids), '?'));
        $query .= " AND r.state_id IN ($placeholders)";
        $params = array_merge($params, $state_ids);
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $stats['states_reporting'] = $stmt->fetchColumn() ?: 0;
    $stats['total_states'] = count($state_ids) ?: $stats['states_reporting'];
    
    // By state
    $query = "
        SELECT 
            s.name as state_name,
            COUNT(r.id) as total,
            SUM(r.total_votes_cast) as votes,
            SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) as verified_count
        FROM results_ec8a r
        JOIN states s ON r.state_id = s.id
        WHERE r.tenant_id = ?
    ";
    $params = [$tenant_id];
    
    if (!empty($state_ids)) {
        $placeholders = implode(',', array_fill(0, count($state_ids), '?'));
        $query .= " AND r.state_id IN ($placeholders)";
        $params = array_merge($params, $state_ids);
    }
    
    $query .= " GROUP BY s.id ORDER BY votes DESC LIMIT 10";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $stats['by_state'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Daily submissions (last 7 days)
    $query = "
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as count,
            SUM(total_votes_cast) as votes
        FROM results_ec8a
        WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ";
    $params = [$tenant_id];
    
    if (!empty($state_ids)) {
        $placeholders = implode(',', array_fill(0, count($state_ids), '?'));
        $query .= " AND state_id IN ($placeholders)";
        $params = array_merge($params, $state_ids);
    }
    
    $query .= " GROUP BY DATE(created_at) ORDER BY date ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $stats['daily_submissions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Party votes summary
    $party_votes = [];
    $query = "SELECT party_votes_json FROM results_ec8a WHERE tenant_id = ? AND party_votes_json IS NOT NULL AND party_votes_json != ''";
    $params = [$tenant_id];
    
    if (!empty($state_ids)) {
        $placeholders = implode(',', array_fill(0, count($state_ids), '?'));
        $query .= " AND state_id IN ($placeholders)";
        $params = array_merge($params, $state_ids);
    }
    
    $query .= " LIMIT 1000";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $party_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($party_results as $row) {
        $votes = json_decode($row['party_votes_json'], true);
        if (is_array($votes)) {
            foreach ($votes as $party => $count) {
                if (!isset($party_votes[$party])) {
                    $party_votes[$party] = 0;
                }
                $party_votes[$party] += intval($count);
            }
        }
    }
    arsort($party_votes);
    $stats['by_party'] = array_slice($party_votes, 0, 10, true);
    
    // Calculate turnout (simplified)
    $stats['turnout'] = $stats['total_pus'] > 0 ? round(($stats['total_votes'] / ($stats['total_pus'] * 500)) * 100) : 0;
    
} catch (Exception $e) {
    error_log("Analytics Error: " . $e->getMessage());
}

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Analytics';
$page_subtitle = $election_id > 0 ? $election_name : 'All Elections';
?>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Breadcrumb -->
        <div class="welcome-section">
            <div class="breadcrumb">
                <i class="fas fa-home"></i>
                <a href="../national/index.php" style="text-decoration:none;color:var(--gray-500);">Dashboard</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);">Analytics</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <i class="fas fa-chart-pie" style="color:var(--primary);"></i>
                        Election Analytics
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-chart-line"></i> 
                        <?php echo htmlspecialchars($election_name); ?>
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <form method="GET" action="" style="display:flex;gap:8px;align-items:center;">
                        <select name="election" class="form-select" style="padding:6px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.8rem;background:white;">
                            <option value="">All Elections</option>
                            <?php foreach ($elections as $e): ?>
                                <option value="<?php echo $e['id']; ?>" <?php echo $election_id == $e['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(substr($e['name'], 0, 30)) . (strlen($e['name']) > 30 ? '...' : ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn-primary" style="padding:6px 16px;background:var(--primary);color:white;border:none;border-radius:8px;font-weight:600;font-size:0.75rem;cursor:pointer;transition:var(--transition);">
                            <i class="fas fa-filter"></i> Apply
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-vote-yea"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_votes']); ?></div>
                <div class="stat-label">Total Votes</div>
                <div class="stat-change">Cast so far</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['verified_votes']); ?></div>
                <div class="stat-label">Verified Votes</div>
                <div class="stat-change up"><i class="fas fa-check"></i> Approved</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo number_format($stats['pending_votes']); ?></div>
                <div class="stat-label">Pending Votes</div>
                <div class="stat-change down"><i class="fas fa-hourglass-half"></i> Awaiting</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-flag-checkered"></i></div>
                <div class="stat-number"><?php echo $stats['reporting_percent']; ?>%</div>
                <div class="stat-label">Reporting Rate</div>
                <div class="stat-change <?php echo $stats['reporting_percent'] >= 80 ? 'up' : 'down'; ?>">
                    <?php echo number_format($stats['reporting_pus']); ?>/<?php echo number_format($stats['total_pus']); ?> PUs
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_incidents']); ?></div>
                <div class="stat-label">Incidents</div>
                <div class="stat-change down"><i class="fas fa-clock"></i> <?php echo $stats['critical_incidents']; ?> critical</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon teal"><i class="fas fa-percent"></i></div>
                <div class="stat-number"><?php echo $stats['turnout']; ?>%</div>
                <div class="stat-label">Voter Turnout</div>
                <div class="stat-change <?php echo $stats['turnout'] >= 50 ? 'up' : 'down'; ?>">
                    Estimated turnout
                </div>
            </div>
        </div>

        <!-- Charts Grid -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
            <!-- Reporting Progress -->
            <div style="background:white;border-radius:var(--radius);padding:16px 20px;border:1px solid var(--gray-200);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <h4 style="font-size:0.85rem;font-weight:600;margin:0;">
                        <i class="fas fa-chart-pie" style="color:var(--primary);margin-right:6px;"></i>
                        Reporting Progress
                    </h4>
                </div>
                <div style="height:200px;">
                    <canvas id="reportingChart"></canvas>
                </div>
            </div>

            <!-- Party Votes -->
            <div style="background:white;border-radius:var(--radius);padding:16px 20px;border:1px solid var(--gray-200);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <h4 style="font-size:0.85rem;font-weight:600;margin:0;">
                        <i class="fas fa-chart-bar" style="color:var(--primary);margin-right:6px;"></i>
                        Party Votes Summary
                    </h4>
                </div>
                <div style="height:200px;">
                    <canvas id="partyChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Daily Trend -->
        <div style="background:white;border-radius:var(--radius);padding:16px 20px;border:1px solid var(--gray-200);margin-bottom:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <h4 style="font-size:0.85rem;font-weight:600;margin:0;">
                    <i class="fas fa-chart-line" style="color:var(--primary);margin-right:6px;"></i>
                    Daily Submissions (Last 7 Days)
                </h4>
            </div>
            <div style="height:250px;">
                <canvas id="trendChart"></canvas>
            </div>
        </div>

        <!-- State Performance -->
        <?php if (count($stats['by_state']) > 0): ?>
        <div style="background:white;border-radius:var(--radius);padding:16px 20px;border:1px solid var(--gray-200);margin-bottom:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <h4 style="font-size:0.85rem;font-weight:600;margin:0;">
                    <i class="fas fa-flag" style="color:var(--primary);margin-right:6px;"></i>
                    State Performance
                </h4>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px;">
                <?php foreach ($stats['by_state'] as $state): 
                    $progress = $stats['total_pus'] > 0 ? round(($state['verified_count'] / max(1, $state['total'])) * 100) : 0;
                    $color = $progress >= 80 ? '#10B981' : ($progress >= 50 ? '#F59E0B' : '#EF4444');
                ?>
                    <div style="background:var(--gray-50);border-radius:8px;padding:12px 16px;border:1px solid var(--gray-200);">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                            <span style="font-weight:600;font-size:0.85rem;"><?php echo htmlspecialchars($state['state_name']); ?></span>
                            <span style="font-weight:600;font-size:0.8rem;color:<?php echo $color; ?>;"><?php echo number_format($state['votes']); ?></span>
                        </div>
                        <div style="width:100%;height:6px;background:var(--gray-200);border-radius:4px;overflow:hidden;">
                            <div style="width:<?php echo $progress; ?>%;height:100%;background:<?php echo $color; ?>;border-radius:4px;"></div>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:0.6rem;color:var(--gray-400);margin-top:4px;">
                            <span><?php echo $state['verified_count']; ?> verified</span>
                            <span><?php echo $progress; ?>%</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
            <a href="reports.php?type=analytics&election=<?php echo $election_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-file-pdf" style="color:var(--danger);"></i>
                <span>Export Analytics Report</span>
            </a>
            <a href="live-results.php?id=<?php echo $election_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-broadcast-tower" style="color:var(--danger);"></i>
                <span>Live Results</span>
            </a>
            <a href="result-verification.php?election=<?php echo $election_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-check-double" style="color:var(--secondary);"></i>
                <span>Verify Results</span>
            </a>
            <a href="election-progress.php?id=<?php echo $election_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-chart-line" style="color:var(--primary);"></i>
                <span>View Progress</span>
            </a>
        </div>
    </div>
</main>

<style>
.stat-icon.teal { background: #CCFBF1; color: #0D9488; }
.quick-action-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-hover); border-color: var(--primary); }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    div[style*="grid-template-columns:1fr 1fr;gap:20px;"] { grid-template-columns: 1fr !important; }
    div[style*="grid-template-columns:repeat(auto-fill,minmax(250px,1fr))"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// ============================================================
// REPORTING CHART
// ============================================================
const reportingCtx = document.getElementById('reportingChart').getContext('2d');
new Chart(reportingCtx, {
    type: 'doughnut',
    data: {
        labels: ['Reporting PUs', 'Not Reporting PUs'],
        datasets: [{
            data: [
                <?php echo $stats['reporting_pus']; ?>,
                <?php echo max(0, $stats['total_pus'] - $stats['reporting_pus']); ?>
            ],
            backgroundColor: ['#10B981', '#F1F5F9'],
            borderWidth: 2,
            borderColor: 'white'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    usePointStyle: true,
                    pointStyle: 'circle',
                    padding: 12,
                    font: { size: 11 }
                }
            }
        },
        cutout: '65%'
    }
});

// ============================================================
// PARTY CHART
// ============================================================
const partyCtx = document.getElementById('partyChart').getContext('2d');
const partyData = <?php 
    $party_labels = array_keys($stats['by_party']);
    $party_counts = array_values($stats['by_party']);
    $party_colors = ['#3B82F6', '#10B981', '#8B5CF6', '#F59E0B', '#EF4444', '#EC4899', '#14B8A6', '#F97316', '#6366F1', '#84CC16'];
    echo json_encode(['labels' => $party_labels, 'data' => $party_counts, 'colors' => array_slice($party_colors, 0, count($party_labels))]);
?>;

new Chart(partyCtx, {
    type: 'bar',
    data: {
        labels: partyData.labels || ['No Data'],
        datasets: [{
            label: 'Votes',
            data: partyData.data || [0],
            backgroundColor: partyData.colors || ['#6B7280'],
            borderColor: partyData.colors || ['#6B7280'],
            borderWidth: 1,
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.04)' },
                ticks: { font: { size: 10 } }
            },
            x: {
                grid: { display: false },
                ticks: { 
                    font: { size: 9 },
                    maxRotation: 45
                }
            }
        }
    }
});

// ============================================================
// TREND CHART
// ============================================================
const trendCtx = document.getElementById('trendChart').getContext('2d');
const trendData = <?php 
    $dates = [];
    $counts = [];
    $votes = [];
    foreach ($stats['daily_submissions'] as $day) {
        $dates[] = date('M j', strtotime($day['date']));
        $counts[] = $day['count'];
        $votes[] = $day['votes'];
    }
    echo json_encode(['dates' => $dates, 'counts' => $counts, 'votes' => $votes]);
?>;

new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: trendData.dates || ['No Data'],
        datasets: [
            {
                label: 'Submissions',
                data: trendData.counts || [0],
                borderColor: '#3B82F6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#3B82F6',
                yAxisID: 'y'
            },
            {
                label: 'Votes',
                data: trendData.votes || [0],
                borderColor: '#10B981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#10B981',
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    usePointStyle: true,
                    pointStyle: 'circle',
                    padding: 12,
                    font: { size: 11 }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.04)' },
                ticks: { font: { size: 10 } },
                position: 'left'
            },
            y1: {
                beginAtZero: true,
                grid: { display: false },
                ticks: { font: { size: 10 } },
                position: 'right'
            },
            x: {
                grid: { display: false },
                ticks: { font: { size: 10 } }
            }
        }
    }
});

// ============================================================
// SIDEBAR TOGGLE, DROPDOWNS, PROFILE, SEARCH
// ============================================================
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