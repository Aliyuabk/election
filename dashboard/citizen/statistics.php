<?php
// ============================================================
// CITIZEN PORTAL - STATISTICS
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/functions.php';

$page_title = 'Statistics';
$current_page = 'statistics';

$db = getDB();

// Get election for filtering
$election_id = isset($_GET['election']) ? (int)$_GET['election'] : 0;

// Get elections
$elections = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, type, election_date, status 
        FROM elections 
        WHERE status IN ('active', 'closed')
        ORDER BY election_date DESC
    ");
    $stmt->execute();
    $elections = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching elections: " . $e->getMessage());
}

// Get statistics data
$stats = [
    'total_votes' => 0,
    'valid_votes' => 0,
    'rejected_votes' => 0,
    'total_results' => 0,
    'states_count' => 0,
    'lgas_count' => 0,
    'wards_count' => 0,
    'pus_count' => 0,
    'candidates_count' => 0,
    'parties_count' => 0,
    'turnout_rate' => 0,
    'party_votes' => []
];

$state_results = [];
$lga_results = [];
$ward_results = [];
$party_data = [];

try {
    // Get basic counts
    $stmt = $db->prepare("
        SELECT 
            (SELECT COUNT(*) FROM states WHERE is_active = 1) as states,
            (SELECT COUNT(*) FROM lgas WHERE is_active = 1) as lgas,
            (SELECT COUNT(*) FROM wards WHERE is_active = 1) as wards,
            (SELECT COUNT(*) FROM polling_units WHERE is_active = 1) as pus,
            (SELECT COUNT(*) FROM candidates WHERE is_active = 1) as candidates,
            (SELECT COUNT(*) FROM political_parties WHERE is_active = 1) as parties
    ");
    $stmt->execute();
    $counts = $stmt->fetch();
    $stats['states_count'] = (int)($counts['states'] ?? 0);
    $stats['lgas_count'] = (int)($counts['lgas'] ?? 0);
    $stats['wards_count'] = (int)($counts['wards'] ?? 0);
    $stats['pus_count'] = (int)($counts['pus'] ?? 0);
    $stats['candidates_count'] = (int)($counts['candidates'] ?? 0);
    $stats['parties_count'] = (int)($counts['parties'] ?? 0);
    
    // Get published results stats
    $where = "pr.is_published = 1";
    $params = [];
    if ($election_id > 0) {
        $where .= " AND pr.election_id = ?";
        $params[] = $election_id;
    }
    
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_results,
            COALESCE(SUM(pr.total_votes), 0) as total_votes,
            COALESCE(SUM(pr.valid_votes), 0) as valid_votes,
            COALESCE(SUM(pr.rejected_votes), 0) as rejected_votes
        FROM public_results pr
        WHERE $where
    ");
    $stmt->execute($params);
    $result = $stmt->fetch();
    $stats['total_results'] = (int)($result['total_results'] ?? 0);
    $stats['total_votes'] = (int)($result['total_votes'] ?? 0);
    $stats['valid_votes'] = (int)($result['valid_votes'] ?? 0);
    $stats['rejected_votes'] = (int)($result['rejected_votes'] ?? 0);
    
    // Get party votes
    $stmt = $db->prepare("
        SELECT pr.party_votes_json
        FROM public_results pr
        WHERE $where
    ");
    $stmt->execute($params);
    $party_results = $stmt->fetchAll();
    
    foreach ($party_results as $row) {
        if (!empty($row['party_votes_json'])) {
            $votes = json_decode($row['party_votes_json'], true) ?: [];
            foreach ($votes as $party => $count) {
                if (!isset($party_data[$party])) {
                    $party_data[$party] = 0;
                }
                $party_data[$party] += (int)$count;
            }
        }
    }
    arsort($party_data);
    $stats['party_votes'] = $party_data;
    
    // Get state results
    $stmt = $db->prepare("
        SELECT 
            s.name as state_name,
            COUNT(pr.id) as result_count,
            COALESCE(SUM(pr.total_votes), 0) as total_votes
        FROM states s
        LEFT JOIN public_results pr ON pr.state_id = s.id AND pr.is_published = 1
        WHERE s.is_active = 1
        GROUP BY s.id, s.name
        ORDER BY s.name ASC
    ");
    $stmt->execute();
    $state_results = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error fetching statistics: " . $e->getMessage());
}

include '../includes/public-header.php';
?>

<style>
.filter-bar {
    background: white;
    border-radius: 14px;
    padding: 16px 20px;
    border: 1px solid #E5E7EB;
    margin-bottom: 24px;
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}
.filter-bar select {
    padding: 10px 14px;
    border: 1.5px solid #E5E7EB;
    border-radius: 10px;
    font-size: 0.85rem;
    background: white;
    min-width: 200px;
}
.filter-bar select:focus {
    outline: none;
    border-color: #0F4C81;
}
.filter-bar .btn-filter {
    padding: 10px 24px;
    background: #0F4C81;
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.3s;
}
.filter-bar .btn-filter:hover {
    background: #1a6db5;
}
.filter-bar .btn-reset {
    padding: 10px 24px;
    background: #F3F4F6;
    color: #6B7280;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.3s;
    text-decoration: none;
}
.filter-bar .btn-reset:hover {
    background: #E5E7EB;
}

.stats-grid-big {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.stat-card-big {
    background: white;
    border-radius: 14px;
    padding: 20px;
    border: 1px solid #E5E7EB;
    text-align: center;
}
.stat-card-big .stat-icon {
    font-size: 1.8rem;
    margin-bottom: 4px;
    display: block;
}
.stat-card-big .stat-number {
    font-size: 1.6rem;
    font-weight: 700;
}
.stat-card-big .stat-label {
    font-size: 0.7rem;
    color: #6B7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.stat-card-big .stat-number.blue { color: #0F4C81; }
.stat-card-big .stat-number.green { color: #10B981; }
.stat-card-big .stat-number.purple { color: #7C3AED; }
.stat-card-big .stat-number.orange { color: #EA580C; }
.stat-card-big .stat-number.red { color: #EF4444; }

.charts-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 24px;
}
.chart-card {
    background: white;
    border-radius: 14px;
    padding: 20px;
    border: 1px solid #E5E7EB;
}
.chart-card .chart-title {
    font-weight: 700;
    font-size: 0.95rem;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.chart-card .chart-title i {
    color: #0F4C81;
}
.chart-wrapper {
    height: 250px;
    position: relative;
}
.chart-wrapper canvas {
    width: 100% !important;
    height: 100% !important;
}

.party-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.party-tag {
    background: #F3F4F6;
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    gap: 8px;
}
.party-tag .party-name {
    font-weight: 500;
}
.party-tag .party-votes {
    font-weight: 700;
    color: #0F4C81;
}

@media (max-width: 768px) {
    .charts-grid {
        grid-template-columns: 1fr;
    }
    .stats-grid-big {
        grid-template-columns: repeat(2, 1fr);
    }
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar select {
        min-width: unset;
        width: 100%;
    }
}
</style>

<div class="container">
    <h1 style="font-size:1.4rem;font-weight:700;margin-bottom:6px;">
        <i class="fas fa-chart-bar" style="color:#0F4C81;"></i> Statistics
    </h1>
    <p style="color:#6B7280;margin-bottom:24px;font-size:0.9rem;">
        Comprehensive election statistics and data visualizations.
    </p>

    <!-- Filter -->
    <div class="filter-bar">
        <form method="GET" action="" style="display:flex;gap:12px;flex-wrap:wrap;flex:1;align-items:center;">
            <select name="election">
                <option value="">All Elections</option>
                <?php foreach ($elections as $e): ?>
                    <option value="<?php echo $e['id']; ?>" <?php echo ($election_id == $e['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($e['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
            <a href="statistics.php" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
        </form>
    </div>

    <!-- Stats -->
    <div class="stats-grid-big">
        <div class="stat-card-big">
            <span class="stat-icon">🗳️</span>
            <div class="stat-number blue"><?php echo number_format($stats['total_votes']); ?></div>
            <div class="stat-label">Total Votes Cast</div>
        </div>
        <div class="stat-card-big">
            <span class="stat-icon">✅</span>
            <div class="stat-number green"><?php echo number_format($stats['valid_votes']); ?></div>
            <div class="stat-label">Valid Votes</div>
        </div>
        <div class="stat-card-big">
            <span class="stat-icon">❌</span>
            <div class="stat-number red"><?php echo number_format($stats['rejected_votes']); ?></div>
            <div class="stat-label">Rejected Votes</div>
        </div>
        <div class="stat-card-big">
            <span class="stat-icon">📊</span>
            <div class="stat-number purple"><?php echo number_format($stats['total_results']); ?></div>
            <div class="stat-label">Published Results</div>
        </div>
        <div class="stat-card-big">
            <span class="stat-icon">👤</span>
            <div class="stat-number blue"><?php echo number_format($stats['candidates_count']); ?></div>
            <div class="stat-label">Candidates</div>
        </div>
        <div class="stat-card-big">
            <span class="stat-icon">🎯</span>
            <div class="stat-number green"><?php echo number_format($stats['parties_count']); ?></div>
            <div class="stat-label">Political Parties</div>
        </div>
    </div>

    <!-- Charts -->
    <div class="charts-grid">
        <!-- Party Votes Chart -->
        <div class="chart-card">
            <div class="chart-title">
                <i class="fas fa-flag"></i> Party Vote Distribution
            </div>
            <div class="chart-wrapper">
                <canvas id="partyChart"></canvas>
            </div>
        </div>

        <!-- State Results Chart -->
        <div class="chart-card">
            <div class="chart-title">
                <i class="fas fa-map-marker-alt"></i> Results by State
            </div>
            <div class="chart-wrapper">
                <canvas id="stateChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Party Summary -->
    <?php if (!empty($stats['party_votes'])): ?>
        <div style="background:white;border-radius:14px;padding:20px;border:1px solid #E5E7EB;margin-bottom:24px;">
            <h4 style="font-size:0.95rem;font-weight:700;margin-bottom:12px;">
                <i class="fas fa-flag" style="color:#0F4C81;"></i> Party Vote Summary
            </h4>
            <div class="party-list">
                <?php $total_party_votes = array_sum($stats['party_votes']); ?>
                <?php foreach ($stats['party_votes'] as $party => $votes): ?>
                    <span class="party-tag">
                        <span class="party-name"><?php echo htmlspecialchars($party); ?></span>
                        <span class="party-votes"><?php echo number_format($votes); ?></span>
                        <span style="font-size:0.65rem;color:#6B7280;">
                            (<?php echo $total_party_votes > 0 ? round(($votes / $total_party_votes) * 100, 1) : 0; ?>%)
                        </span>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- State Results Table -->
    <div style="background:white;border-radius:14px;padding:20px;border:1px solid #E5E7EB;">
        <h4 style="font-size:0.95rem;font-weight:700;margin-bottom:12px;">
            <i class="fas fa-table" style="color:#0F4C81;"></i> State Results Summary
        </h4>
        <?php if (!empty($state_results)): ?>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                    <thead>
                        <tr style="background:#F8FAFC;">
                            <th style="padding:10px 12px;text-align:left;font-weight:600;color:#6B7280;border-bottom:2px solid #E5E7EB;">State</th>
                            <th style="padding:10px 12px;text-align:center;font-weight:600;color:#6B7280;border-bottom:2px solid #E5E7EB;">Results</th>
                            <th style="padding:10px 12px;text-align:right;font-weight:600;color:#6B7280;border-bottom:2px solid #E5E7EB;">Total Votes</th>
                            <th style="padding:10px 12px;text-align:right;font-weight:600;color:#6B7280;border-bottom:2px solid #E5E7EB;">% of Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $grand_total = 0;
                        foreach ($state_results as $sr) {
                            $grand_total += (int)($sr['total_votes'] ?? 0);
                        }
                        $grand_total = $grand_total > 0 ? $grand_total : 1;
                        ?>
                        <?php foreach ($state_results as $state): 
                            $votes = (int)($state['total_votes'] ?? 0);
                            $percentage = round(($votes / $grand_total) * 100, 1);
                        ?>
                            <tr style="border-bottom:1px solid #F3F4F6;">
                                <td style="padding:10px 12px;font-weight:500;">
                                    <?php echo htmlspecialchars($state['state_name'] ?? 'Unknown'); ?>
                                </td>
                                <td style="padding:10px 12px;text-align:center;">
                                    <?php echo number_format((int)($state['result_count'] ?? 0)); ?>
                                </td>
                                <td style="padding:10px 12px;text-align:right;font-weight:600;color:#0F4C81;">
                                    <?php echo number_format($votes); ?>
                                </td>
                                <td style="padding:10px 12px;text-align:right;">
                                    <?php echo $percentage; ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr style="background:#F8FAFC;font-weight:700;">
                            <td style="padding:10px 12px;">Total</td>
                            <td style="padding:10px 12px;text-align:center;"><?php echo number_format($stats['total_results']); ?></td>
                            <td style="padding:10px 12px;text-align:right;color:#0F4C81;"><?php echo number_format($grand_total); ?></td>
                            <td style="padding:10px 12px;text-align:right;">100%</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="color:#6B7280;font-size:0.9rem;padding:20px;text-align:center;">
                No state data available.
            </p>
        <?php endif; ?>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// ============================================================
// PARTY CHART
// ============================================================
const partyData = <?php echo json_encode(array_slice($stats['party_votes'], 0, 10)); ?>;
const partyLabels = Object.keys(partyData);
const partyValues = Object.values(partyData);

const partyColors = [
    '#0F4C81', '#10B981', '#7C3AED', '#EA580C', '#EF4444',
    '#F59E0B', '#3B82F6', '#8B5CF6', '#EC4899', '#14B8A6'
];

const ctx1 = document.getElementById('partyChart').getContext('2d');
new Chart(ctx1, {
    type: 'doughnut',
    data: {
        labels: partyLabels.length > 0 ? partyLabels : ['No Data'],
        datasets: [{
            data: partyValues.length > 0 ? partyValues : [1],
            backgroundColor: partyLabels.length > 0 ? partyColors.slice(0, partyLabels.length) : ['#E5E7EB'],
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
                    padding: 10,
                    font: { size: 11 }
                }
            }
        },
        cutout: '60%'
    }
});

// ============================================================
// STATE CHART
// ============================================================
const stateData = <?php echo json_encode($state_results); ?>;
const stateLabels = stateData.map(item => item.state_name || 'Unknown');
const stateValues = stateData.map(item => parseInt(item.total_votes) || 0);

const ctx2 = document.getElementById('stateChart').getContext('2d');
new Chart(ctx2, {
    type: 'bar',
    data: {
        labels: stateLabels.length > 0 ? stateLabels : ['No Data'],
        datasets: [{
            label: 'Total Votes',
            data: stateValues.length > 0 ? stateValues : [0],
            backgroundColor: 'rgba(15, 76, 129, 0.7)',
            borderColor: '#0F4C81',
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
                grid: { color: 'rgba(0,0,0,0.05)' },
                ticks: { 
                    font: { size: 10 },
                    callback: function(value) {
                        if (value >= 1000000) return (value / 1000000) + 'M';
                        if (value >= 1000) return (value / 1000) + 'K';
                        return value;
                    }
                }
            },
            x: {
                grid: { display: false },
                ticks: { 
                    font: { size: 9 },
                    maxRotation: 45,
                    minRotation: 30
                }
            }
        }
    }
});
</script>

<?php include '../includes/public-footer.php'; ?>