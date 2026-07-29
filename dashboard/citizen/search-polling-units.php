<?php
// ============================================================
// CITIZEN PORTAL - SEARCH POLLING UNITS
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/functions.php';

$page_title = 'Search Polling Units';
$current_page = 'search';

$db = getDB();

// Get search parameters
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$state_id = isset($_GET['state']) ? (int)$_GET['state'] : 0;
$lga_id = isset($_GET['lga']) ? (int)$_GET['lga'] : 0;
$ward_id = isset($_GET['ward']) ? (int)$_GET['ward'] : 0;

// Get states for filter
$states = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM states WHERE is_active = 1 ORDER BY name ASC");
    $stmt->execute();
    $states = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching states: " . $e->getMessage());
}

// Get LGAs for filter
$lgas = [];
if ($state_id > 0) {
    try {
        $stmt = $db->prepare("SELECT id, name FROM lgas WHERE state_id = ? AND is_active = 1 ORDER BY name ASC");
        $stmt->execute([$state_id]);
        $lgas = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching LGAs: " . $e->getMessage());
    }
}

// Get wards for filter
$wards = [];
if ($lga_id > 0) {
    try {
        $stmt = $db->prepare("SELECT id, name FROM wards WHERE lga_id = ? AND is_active = 1 ORDER BY name ASC");
        $stmt->execute([$lga_id]);
        $wards = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching wards: " . $e->getMessage());
    }
}

// Build search query
$polling_units = [];
$total_results = 0;

try {
    $where = ["pu.is_active = 1"];
    $params = [];
    
    if (!empty($search_query)) {
        $where[] = "(pu.name LIKE ? OR pu.code LIKE ?)";
        $params[] = "%$search_query%";
        $params[] = "%$search_query%";
    }
    if ($state_id > 0) {
        $where[] = "l.state_id = ?";
        $params[] = $state_id;
    }
    if ($lga_id > 0) {
        $where[] = "pu.lga_id = ?";
        $params[] = $lga_id;
    }
    if ($ward_id > 0) {
        $where[] = "pu.ward_id = ?";
        $params[] = $ward_id;
    }
    
    $where_clause = implode(" AND ", $where);
    
    // Get count
    $count_sql = "SELECT COUNT(*) FROM polling_units pu 
                  JOIN wards w ON pu.ward_id = w.id 
                  JOIN lgas l ON w.lga_id = l.id 
                  WHERE " . $where_clause;
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_results = (int)$stmt->fetchColumn();
    
    // Get results with pagination
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    $params[] = $limit;
    $params[] = $offset;
    
    $sql = "
        SELECT 
            pu.id,
            pu.name,
            pu.code,
            pu.address,
            pu.gps_lat,
            pu.gps_lng,
            pu.registered_voters,
            pu.is_rural,
            pu.network_quality,
            w.name as ward_name,
            w.id as ward_id,
            l.name as lga_name,
            l.id as lga_id,
            s.name as state_name,
            s.id as state_id
        FROM polling_units pu
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        JOIN states s ON l.state_id = s.id
        WHERE " . $where_clause . "
        ORDER BY s.name ASC, l.name ASC, w.name ASC, pu.name ASC
        LIMIT ? OFFSET ?
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $polling_units = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error searching polling units: " . $e->getMessage());
}

include '../includes/public-header.php';
?>

<style>
.search-section {
    background: white;
    border-radius: 14px;
    padding: 24px;
    border: 1px solid #E5E7EB;
    margin-bottom: 24px;
}
.search-section .search-title {
    font-weight: 700;
    font-size: 1rem;
    margin-bottom: 12px;
}
.search-form {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.search-form input[type="text"] {
    flex: 1;
    min-width: 200px;
    padding: 12px 18px;
    border: 1.5px solid #E5E7EB;
    border-radius: 10px;
    font-size: 0.95rem;
}
.search-form input[type="text"]:focus {
    outline: none;
    border-color: #0F4C81;
}
.search-form button {
    padding: 12px 28px;
    background: #0F4C81;
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.3s;
}
.search-form button:hover {
    background: #1a6db5;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
    margin-top: 12px;
}
.filter-grid select {
    padding: 10px 14px;
    border: 1.5px solid #E5E7EB;
    border-radius: 10px;
    font-size: 0.85rem;
    width: 100%;
    background: white;
}
.filter-grid select:focus {
    outline: none;
    border-color: #0F4C81;
}

.results-stats {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 16px;
    padding: 12px 0;
    border-bottom: 1px solid #E5E7EB;
}
.results-stats .count {
    font-weight: 600;
    color: #0F4C81;
}
.results-stats .count span {
    font-weight: 700;
    font-size: 1.1rem;
}

.pu-card {
    background: white;
    border-radius: 12px;
    padding: 16px 20px;
    border: 1px solid #E5E7EB;
    margin-bottom: 12px;
    transition: box-shadow 0.2s;
}
.pu-card:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
}
.pu-card .pu-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 8px;
}
.pu-card .pu-header .pu-name {
    font-weight: 700;
    font-size: 0.95rem;
}
.pu-card .pu-header .pu-code {
    color: #6B7280;
    font-size: 0.8rem;
    background: #F3F4F6;
    padding: 2px 10px;
    border-radius: 12px;
}
.pu-card .pu-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 8px;
    margin-top: 8px;
    font-size: 0.82rem;
}
.pu-card .pu-details .detail {
    color: #6B7280;
}
.pu-card .pu-details .detail strong {
    color: #1F2937;
}
.pu-card .pu-actions {
    margin-top: 10px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.pu-card .pu-actions a {
    padding: 6px 16px;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
}
.pu-card .pu-actions .btn-view {
    background: #0F4C81;
    color: white;
}
.pu-card .pu-actions .btn-view:hover {
    background: #1a6db5;
}
.pu-card .pu-actions .btn-map {
    background: #F3F4F6;
    color: #6B7280;
}
.pu-card .pu-actions .btn-map:hover {
    background: #E5E7EB;
}
.pu-card .pu-location {
    font-size: 0.75rem;
    color: #9CA3AF;
    margin-top: 6px;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 6px;
    margin-top: 20px;
    flex-wrap: wrap;
}
.pagination a,
.pagination span {
    padding: 8px 14px;
    border: 1px solid #E5E7EB;
    border-radius: 8px;
    text-decoration: none;
    color: #6B7280;
    font-size: 0.8rem;
    transition: all 0.2s;
}
.pagination a:hover {
    background: #0F4C81;
    color: white;
    border-color: #0F4C81;
}
.pagination .active {
    background: #0F4C81;
    color: white;
    border-color: #0F4C81;
}
.pagination .disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6B7280;
}
.empty-state i {
    font-size: 3rem;
    color: #D1D5DB;
    margin-bottom: 12px;
}
.empty-state h3 {
    font-size: 1.1rem;
    color: #1F2937;
    margin-bottom: 6px;
}

.status-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 0.65rem;
    font-weight: 600;
}
.status-badge.rural { background: #DBEAFE; color: #2563EB; }
.status-badge.urban { background: #D1FAE5; color: #059669; }

@media (max-width: 768px) {
    .search-form {
        flex-direction: column;
    }
    .search-form button {
        width: 100%;
        justify-content: center;
    }
    .filter-grid {
        grid-template-columns: 1fr;
    }
    .pu-card .pu-header {
        flex-direction: column;
    }
    .results-stats {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<div class="container">
    <h1 style="font-size:1.4rem;font-weight:700;margin-bottom:6px;">
        <i class="fas fa-search" style="color:#0F4C81;"></i> Search Polling Units
    </h1>
    <p style="color:#6B7280;margin-bottom:20px;font-size:0.9rem;">
        Find polling units by name, code, or location.
    </p>

    <!-- Search Section -->
    <div class="search-section">
        <div class="search-title">
            <i class="fas fa-filter"></i> Search &amp; Filter
        </div>
        <form method="GET" action="">
            <div class="search-form">
                <input type="text" name="q" placeholder="🔍 Search by name or code..." 
                       value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit"><i class="fas fa-search"></i> Search</button>
            </div>
            <div class="filter-grid">
                <select name="state" id="state_select">
                    <option value="">All States</option>
                    <?php foreach ($states as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo ($state_id == $s['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="lga" id="lga_select">
                    <option value="">All LGAs</option>
                    <?php foreach ($lgas as $l): ?>
                        <option value="<?php echo $l['id']; ?>" <?php echo ($lga_id == $l['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($l['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="ward" id="ward_select">
                    <option value="">All Wards</option>
                    <?php foreach ($wards as $w): ?>
                        <option value="<?php echo $w['id']; ?>" <?php echo ($ward_id == $w['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($w['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <!-- Results -->
    <?php if (!empty($polling_units)): ?>
        <div class="results-stats">
            <div class="count">
                <i class="fas fa-list"></i> 
                <span><?php echo number_format($total_results); ?></span> polling units found
            </div>
            <?php if (!empty($search_query)): ?>
                <div style="font-size:0.8rem;color:#6B7280;">
                    Search: "<strong><?php echo htmlspecialchars($search_query); ?></strong>"
                </div>
            <?php endif; ?>
        </div>

        <?php foreach ($polling_units as $pu): ?>
            <div class="pu-card">
                <div class="pu-header">
                    <div>
                        <div class="pu-name">
                            <?php echo htmlspecialchars($pu['name']); ?>
                        </div>
                        <div class="pu-code">
                            <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($pu['code']); ?>
                            <span style="margin-left:8px;">
                                <span class="status-badge <?php echo ($pu['is_rural'] ?? 0) ? 'rural' : 'urban'; ?>">
                                    <?php echo ($pu['is_rural'] ?? 0) ? 'Rural' : 'Urban'; ?>
                                </span>
                            </span>
                            <?php if (!empty($pu['network_quality'])): ?>
                                <span style="margin-left:6px;font-size:0.65rem;color:#6B7280;">
                                    <i class="fas fa-signal"></i> <?php echo strtoupper($pu['network_quality']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="text-align:right;font-size:0.8rem;color:#6B7280;">
                        <div><?php echo htmlspecialchars($pu['ward_name']); ?></div>
                        <div style="font-size:0.7rem;"><?php echo htmlspecialchars($pu['lga_name']); ?></div>
                        <div style="font-size:0.65rem;color:#9CA3AF;"><?php echo htmlspecialchars($pu['state_name']); ?></div>
                    </div>
                </div>

                <div class="pu-details">
                    <div class="detail">
                        <strong>Registered Voters:</strong> 
                        <?php echo number_format((int)($pu['registered_voters'] ?? 0)); ?>
                    </div>
                    <?php if (!empty($pu['address'])): ?>
                        <div class="detail">
                            <strong>Address:</strong> <?php echo htmlspecialchars($pu['address']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($pu['gps_lat']) && !empty($pu['gps_lng'])): ?>
                        <div class="detail">
                            <strong>GPS:</strong> 
                            <?php echo $pu['gps_lat']; ?>, <?php echo $pu['gps_lng']; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="pu-actions">
                    <a href="pu-details.php?id=<?php echo $pu['id']; ?>" class="btn-view">
                        <i class="fas fa-eye"></i> View Details
                    </a>
                    <?php if (!empty($pu['gps_lat']) && !empty($pu['gps_lng'])): ?>
                        <a href="https://maps.google.com/?q=<?php echo $pu['gps_lat']; ?>,<?php echo $pu['gps_lng']; ?>" 
                           target="_blank" class="btn-map">
                            <i class="fas fa-map-marker-alt"></i> Map
                        </a>
                    <?php endif; ?>
                    <a href="published-results.php?pu=<?php echo $pu['id']; ?>" class="btn-map">
                        <i class="fas fa-file-alt"></i> Results
                    </a>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Pagination -->
        <?php if ($total_results > 20): ?>
            <div class="pagination">
                <?php
                $total_pages = ceil($total_results / 20);
                $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $query_params = $_GET;
                unset($query_params['page']);
                $base_url = '?' . http_build_query($query_params);
                
                if ($current_page > 1): ?>
                    <a href="<?php echo $base_url; ?>&page=<?php echo $current_page - 1; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                
                <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                    <?php if ($i == $current_page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="<?php echo $base_url; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($current_page < $total_pages): ?>
                    <a href="<?php echo $base_url; ?>&page=<?php echo $current_page + 1; ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-search"></i>
            <h3>No Polling Units Found</h3>
            <p>Try adjusting your search terms or filters.</p>
            <?php if (!empty($search_query) || $state_id > 0 || $lga_id > 0 || $ward_id > 0): ?>
                <a href="search-polling-units.php" style="display:inline-block;margin-top:10px;padding:10px 24px;background:#0F4C81;color:white;border-radius:10px;text-decoration:none;font-weight:600;">
                    <i class="fas fa-undo"></i> Reset Filters
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Dynamic dropdowns
document.getElementById('state_select').addEventListener('change', function() {
    var stateId = this.value;
    var currentUrl = window.location.href.split('?')[0];
    var params = new URLSearchParams(window.location.search);
    if (stateId) {
        params.set('state', stateId);
    } else {
        params.delete('state');
    }
    params.delete('lga');
    params.delete('ward');
    window.location.href = currentUrl + '?' + params.toString();
});

document.getElementById('lga_select').addEventListener('change', function() {
    var lgaId = this.value;
    var currentUrl = window.location.href.split('?')[0];
    var params = new URLSearchParams(window.location.search);
    if (lgaId) {
        params.set('lga', lgaId);
    } else {
        params.delete('lga');
    }
    params.delete('ward');
    window.location.href = currentUrl + '?' + params.toString();
});

document.getElementById('ward_select').addEventListener('change', function() {
    var wardId = this.value;
    var currentUrl = window.location.href.split('?')[0];
    var params = new URLSearchParams(window.location.search);
    if (wardId) {
        params.set('ward', wardId);
    } else {
        params.delete('ward');
    }
    window.location.href = currentUrl + '?' + params.toString();
});
</script>

<?php include '../includes/public-footer.php'; ?>