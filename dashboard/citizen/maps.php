<?php
// ============================================================
// CITIZEN PORTAL - INTERACTIVE MAPS
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/functions.php';

$page_title = 'Interactive Maps';
$current_page = 'maps';

$db = getDB();

// Get data for map
$states_data = [];
$results_data = [];

try {
    // Get states with coordinates
    $stmt = $db->prepare("
        SELECT id, name, code, gps_lat, gps_lng, registered_voters 
        FROM states 
        WHERE is_active = 1 AND gps_lat IS NOT NULL AND gps_lng IS NOT NULL
        ORDER BY name ASC
    ");
    $stmt->execute();
    $states_data = $stmt->fetchAll();
    
    // Get published results by state
    $stmt = $db->prepare("
        SELECT 
            s.id as state_id,
            s.name as state_name,
            COUNT(pr.id) as result_count,
            SUM(pr.total_votes) as total_votes,
            SUM(pr.valid_votes) as valid_votes,
            SUM(pr.rejected_votes) as rejected_votes
        FROM states s
        LEFT JOIN public_results pr ON pr.state_id = s.id AND pr.is_published = 1
        WHERE s.is_active = 1
        GROUP BY s.id, s.name
        ORDER BY s.name ASC
    ");
    $stmt->execute();
    $results_data = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error fetching map data: " . $e->getMessage());
}

include '../includes/public-header.php';
?>

<style>
.map-container {
    background: white;
    border-radius: 14px;
    border: 1px solid #E5E7EB;
    padding: 20px;
    margin-bottom: 24px;
}
.map-container .map-title {
    font-weight: 700;
    font-size: 1rem;
    margin-bottom: 12px;
}
.map-wrapper {
    height: 500px;
    background: #F3F4F6;
    border-radius: 10px;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}
.map-placeholder {
    text-align: center;
    color: #9CA3AF;
}
.map-placeholder i {
    font-size: 4rem;
    display: block;
    margin-bottom: 12px;
    color: #D1D5DB;
}
.map-placeholder .map-note {
    font-size: 0.85rem;
    max-width: 400px;
}

.stats-grid-map {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 14px;
    margin-bottom: 24px;
}
.stat-card-map {
    background: white;
    border-radius: 12px;
    padding: 16px;
    border: 1px solid #E5E7EB;
    text-align: center;
}
.stat-card-map .stat-number {
    font-size: 1.3rem;
    font-weight: 700;
}
.stat-card-map .stat-number.blue { color: #0F4C81; }
.stat-card-map .stat-number.green { color: #10B981; }
.stat-card-map .stat-number.purple { color: #7C3AED; }
.stat-card-map .stat-label {
    font-size: 0.65rem;
    color: #6B7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.state-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 12px;
}
.state-item {
    background: white;
    border-radius: 10px;
    padding: 14px 16px;
    border: 1px solid #E5E7EB;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: box-shadow 0.2s;
}
.state-item:hover {
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
}
.state-item .state-name {
    font-weight: 600;
    font-size: 0.9rem;
}
.state-item .state-votes {
    text-align: right;
}
.state-item .state-votes .votes {
    font-weight: 700;
    font-size: 0.95rem;
    color: #0F4C81;
}
.state-item .state-votes .label {
    font-size: 0.65rem;
    color: #6B7280;
}
.state-item .state-progress {
    width: 80px;
}
.state-item .state-progress .bar {
    height: 6px;
    background: #E5E7EB;
    border-radius: 4px;
    overflow: hidden;
}
.state-item .state-progress .bar .fill {
    height: 100%;
    background: #0F4C81;
    border-radius: 4px;
    transition: width 0.5s ease;
}

.map-legend {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    margin-top: 12px;
    padding: 12px 16px;
    background: #F8FAFC;
    border-radius: 10px;
}
.map-legend .legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.8rem;
    color: #6B7280;
}
.map-legend .legend-item .color-box {
    width: 20px;
    height: 12px;
    border-radius: 4px;
}
.map-legend .legend-item .color-box.high { background: #0F4C81; }
.map-legend .legend-item .color-box.medium { background: #3B82F6; }
.map-legend .legend-item .color-box.low { background: #93C5FD; }

@media (max-width: 768px) {
    .map-wrapper {
        height: 300px;
    }
    .state-list {
        grid-template-columns: 1fr;
    }
    .stats-grid-map {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<div class="container">
    <h1 style="font-size:1.4rem;font-weight:700;margin-bottom:6px;">
        <i class="fas fa-map" style="color:#0F4C81;"></i> Interactive Maps
    </h1>
    <p style="color:#6B7280;margin-bottom:24px;font-size:0.9rem;">
        Explore election data and results across Nigeria.
    </p>

    <!-- Stats -->
    <div class="stats-grid-map">
        <div class="stat-card-map">
            <div class="stat-number blue"><?php echo number_format(count($states_data)); ?></div>
            <div class="stat-label">States with Data</div>
        </div>
        <div class="stat-card-map">
            <div class="stat-number green"><?php 
                $total_results = 0;
                foreach ($results_data as $r) {
                    $total_results += (int)($r['result_count'] ?? 0);
                }
                echo number_format($total_results);
            ?></div>
            <div class="stat-label">Published Results</div>
        </div>
        <div class="stat-card-map">
            <div class="stat-number purple"><?php 
                $total_votes = 0;
                foreach ($results_data as $r) {
                    $total_votes += (int)($r['total_votes'] ?? 0);
                }
                echo number_format($total_votes);
            ?></div>
            <div class="stat-label">Total Votes</div>
        </div>
        <div class="stat-card-map">
            <div class="stat-number blue"><?php 
                $total_valid = 0;
                foreach ($results_data as $r) {
                    $total_valid += (int)($r['valid_votes'] ?? 0);
                }
                echo number_format($total_valid);
            ?></div>
            <div class="stat-label">Valid Votes</div>
        </div>
    </div>

    <!-- Map -->
    <div class="map-container">
        <div class="map-title">
            <i class="fas fa-globe-africa" style="color:#0F4C81;"></i> Election Results Map
        </div>
        <div class="map-wrapper" id="mapWrapper">
            <div class="map-placeholder">
                <i class="fas fa-map-marked-alt"></i>
                <h3>Interactive Map</h3>
                <p class="map-note">
                    <strong>Google Maps integration coming soon.</strong><br>
                    Click on a state below to view detailed results.
                </p>
                <div style="margin-top:12px;display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
                    <span style="padding:4px 14px;background:#0F4C81;color:white;border-radius:20px;font-size:0.7rem;">
                        <i class="fas fa-circle" style="font-size:0.5rem;"></i> High Results
                    </span>
                    <span style="padding:4px 14px;background:#3B82F6;color:white;border-radius:20px;font-size:0.7rem;">
                        <i class="fas fa-circle" style="font-size:0.5rem;"></i> Medium Results
                    </span>
                    <span style="padding:4px 14px;background:#93C5FD;color:white;border-radius:20px;font-size:0.7rem;">
                        <i class="fas fa-circle" style="font-size:0.5rem;"></i> Low Results
                    </span>
                </div>
            </div>
        </div>
        <div class="map-legend">
            <span class="legend-item">
                <span class="color-box high"></span> High (80%+)
            </span>
            <span class="legend-item">
                <span class="color-box medium"></span> Medium (50-80%)
            </span>
            <span class="legend-item">
                <span class="color-box low"></span> Low (&lt;50%)
            </span>
            <span class="legend-item">
                <i class="fas fa-circle" style="color:#10B981;font-size:0.6rem;"></i> Published Results
            </span>
        </div>
    </div>

    <!-- State List -->
    <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">
        <i class="fas fa-list" style="color:#0F4C81;"></i> States Overview
    </h3>
    <div class="state-list">
        <?php if (!empty($results_data)): ?>
            <?php 
            $max_votes = 0;
            foreach ($results_data as $r) {
                if ((int)($r['total_votes'] ?? 0) > $max_votes) {
                    $max_votes = (int)($r['total_votes'] ?? 0);
                }
            }
            $max_votes = $max_votes > 0 ? $max_votes : 1;
            ?>
            <?php foreach ($results_data as $state): 
                $total_votes = (int)($state['total_votes'] ?? 0);
                $percentage = $max_votes > 0 ? round(($total_votes / $max_votes) * 100, 1) : 0;
            ?>
                <div class="state-item">
                    <div class="state-name">
                        <?php echo htmlspecialchars($state['state_name'] ?? 'Unknown'); ?>
                        <span style="font-size:0.7rem;color:#6B7280;margin-left:6px;">
                            (<?php echo number_format((int)($state['result_count'] ?? 0)); ?> results)
                        </span>
                    </div>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div class="state-votes">
                            <div class="votes"><?php echo number_format($total_votes); ?></div>
                            <div class="label">Votes</div>
                        </div>
                        <div class="state-progress">
                            <div class="bar">
                                <div class="fill" style="width:<?php echo min($percentage, 100); ?>%;"></div>
                            </div>
                            <div style="font-size:0.6rem;color:#6B7280;text-align:right;margin-top:2px;">
                                <?php echo $percentage; ?>%
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:#6B7280;font-size:0.9rem;">No state data available.</p>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/public-footer.php'; ?>