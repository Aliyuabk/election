<?php
// ============================================================
// CITIZEN PORTAL - PUBLISHED RESULTS
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/functions.php';

$page_title = 'Published Results';
$current_page = 'results';

$db = getDB();

// Get filters
$election_id = isset($_GET['election']) ? (int)$_GET['election'] : 0;
$state_id = isset($_GET['state']) ? (int)$_GET['state'] : 0;
$lga_id = isset($_GET['lga']) ? (int)$_GET['lga'] : 0;
$ward_id = isset($_GET['ward']) ? (int)$_GET['ward'] : 0;
$pu_id = isset($_GET['pu']) ? (int)$_GET['pu'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

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

// Get states
$states = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM states WHERE is_active = 1 ORDER BY name ASC");
    $stmt->execute();
    $states = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching states: " . $e->getMessage());
}

// Get LGAs based on state
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

// Get wards based on LGA
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

// Get polling units based on ward
$polling_units = [];
if ($ward_id > 0) {
    try {
        $stmt = $db->prepare("SELECT id, name, code FROM polling_units WHERE ward_id = ? AND is_active = 1 ORDER BY name ASC");
        $stmt->execute([$ward_id]);
        $polling_units = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching polling units: " . $e->getMessage());
    }
}

// Build query for results
$results = [];
$total_results = 0;
$total_votes = 0;
$total_valid = 0;
$total_rejected = 0;

try {
    $where = ["pr.is_published = 1"];
    $params = [];
    
    if ($election_id > 0) {
        $where[] = "pr.election_id = ?";
        $params[] = $election_id;
    }
    if ($state_id > 0) {
        $where[] = "pr.state_id = ?";
        $params[] = $state_id;
    }
    if ($lga_id > 0) {
        $where[] = "pr.lga_id = ?";
        $params[] = $lga_id;
    }
    if ($ward_id > 0) {
        $where[] = "pr.ward_id = ?";
        $params[] = $ward_id;
    }
    if ($pu_id > 0) {
        $where[] = "pr.pu_id = ?";
        $params[] = $pu_id;
    }
    if (!empty($search)) {
        $where[] = "(pu.name LIKE ? OR pu.code LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_clause = implode(" AND ", $where);
    
    // Get count
    $count_sql = "SELECT COUNT(*) FROM public_results pr 
                  LEFT JOIN polling_units pu ON pr.pu_id = pu.id 
                  WHERE " . $where_clause;
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_results = (int)$stmt->fetchColumn();
    
    // Get results
    $params[] = $limit;
    $params[] = $offset;
    
    $sql = "
        SELECT 
            pr.*,
            pu.name as pu_name,
            pu.code as pu_code,
            w.name as ward_name,
            l.name as lga_name,
            s.name as state_name,
            e.name as election_name,
            e.type as election_type,
            u.full_name as published_by_name
        FROM public_results pr
        LEFT JOIN polling_units pu ON pr.pu_id = pu.id
        LEFT JOIN wards w ON pr.ward_id = w.id
        LEFT JOIN lgas l ON pr.lga_id = l.id
        LEFT JOIN states s ON pr.state_id = s.id
        LEFT JOIN elections e ON pr.election_id = e.id
        LEFT JOIN users u ON pr.published_by = u.id
        WHERE " . $where_clause . "
        ORDER BY pr.published_at DESC
        LIMIT ? OFFSET ?
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
    
    // Get totals
    if (!empty($results)) {
        foreach ($results as $r) {
            $total_votes += (int)($r['total_votes'] ?? 0);
            $total_valid += (int)($r['valid_votes'] ?? 0);
            $total_rejected += (int)($r['rejected_votes'] ?? 0);
        }
    }
} catch (Exception $e) {
    error_log("Error fetching results: " . $e->getMessage());
}

include '../includes/public-header.php';
?>

<style>
.filter-section {
    background: white;
    border-radius: 14px;
    padding: 20px;
    border: 1px solid #E5E7EB;
    margin-bottom: 24px;
}
.filter-section .filter-title {
    font-weight: 700;
    margin-bottom: 12px;
    font-size: 0.95rem;
}
.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
}
.filter-grid select,
.filter-grid input {
    padding: 10px 14px;
    border: 1.5px solid #E5E7EB;
    border-radius: 10px;
    font-size: 0.85rem;
    width: 100%;
    background: white;
}
.filter-grid select:focus,
.filter-grid input:focus {
    outline: none;
    border-color: #0F4C81;
}
.filter-actions {
    display: flex;
    gap: 10px;
    margin-top: 12px;
    flex-wrap: wrap;
}
.btn-filter {
    padding: 10px 24px;
    background: #0F4C81;
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.3s;
}
.btn-filter:hover {
    background: #1a6db5;
}
.btn-reset {
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
.btn-reset:hover {
    background: #E5E7EB;
}

.results-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
.result-stat {
    background: white;
    border-radius: 12px;
    padding: 12px 14px;
    text-align: center;
    border: 1px solid #E5E7EB;
}
.result-stat .stat-number {
    font-size: 1.2rem;
    font-weight: 700;
}
.result-stat .stat-label {
    font-size: 0.6rem;
    color: #6B7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.result-stat .stat-number.blue { color: #0F4C81; }
.result-stat .stat-number.green { color: #10B981; }
.result-stat .stat-number.red { color: #EF4444; }

.result-card {
    background: white;
    border-radius: 14px;
    padding: 18px;
    border: 1px solid #E5E7EB;
    margin-bottom: 14px;
    transition: box-shadow 0.2s;
}
.result-card:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
}
.result-card .result-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    margin-bottom: 10px;
}
.result-card .result-header .pu-name {
    font-weight: 700;
    font-size: 0.95rem;
}
.result-card .result-header .pu-code {
    color: #6B7280;
    font-size: 0.75rem;
}
.result-card .result-header .election-name {
    font-size: 0.75rem;
    color: #6B7280;
}
.result-card .result-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
    gap: 8px;
    margin: 8px 0;
}
.result-card .result-details .detail-item {
    background: #F8FAFC;
    border-radius: 8px;
    padding: 6px 10px;
    text-align: center;
}
.result-card .result-details .detail-item .value {
    font-weight: 700;
    font-size: 0.85rem;
}
.result-card .result-details .detail-item .label {
    font-size: 0.55rem;
    color: #6B7280;
    text-transform: uppercase;
}
.result-card .party-votes {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
}
.result-card .party-votes .party-tag {
    background: #F3F4F6;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 0.7rem;
}
.result-card .party-votes .party-tag .votes {
    font-weight: 600;
    color: #0F4C81;
}
.result-card .result-footer {
    margin-top: 8px;
    font-size: 0.65rem;
    color: #9CA3AF;
    display: flex;
    gap: 14px;
    flex-wrap: wrap;
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
    padding: 40px 20px;
    color: #6B7280;
}
.empty-state i {
    font-size: 2.5rem;
    color: #D1D5DB;
    margin-bottom: 10px;
}
.empty-state h3 {
    font-size: 1rem;
    color: #1F2937;
    margin-bottom: 6px;
}

@media (max-width: 768px) {
    .filter-grid {
        grid-template-columns: 1fr;
    }
    .result-card .result-header {
        flex-direction: column;
        gap: 4px;
    }
    .results-stats {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<div class="container">
    <h1 style="font-size:1.4rem;font-weight:700;margin-bottom:6px;">
        <i class="fas fa-file-alt" style="color:#0F4C81;"></i> Published Results
    </h1>
    <p style="color:#6B7280;margin-bottom:20px;font-size:0.9rem;">
        Official election results published for public viewing.
    </p>

    <!-- Filters -->
    <div class="filter-section">
        <div class="filter-title"><i class="fas fa-filter"></i> Filter Results</div>
        <form method="GET" action="">
            <div class="filter-grid">
                <select name="election">
                    <option value="">All Elections</option>
                    <?php foreach ($elections as $e): ?>
                        <option value="<?php echo $e['id']; ?>" <?php echo ($election_id == $e['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($e['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
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
                <select name="pu" id="pu_select">
                    <option value="">All Polling Units</option>
                    <?php foreach ($polling_units as $pu): ?>
                        <option value="<?php echo $pu['id']; ?>" <?php echo ($pu_id == $pu['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pu['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="search" placeholder="Search by name or code..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Apply Filters</button>
                <a href="published-results.php" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
            </div>
        </form>
    </div>

    <!-- Stats -->
    <?php if (!empty($results)): ?>
        <div class="results-stats">
            <div class="result-stat">
                <div class="stat-number blue"><?php echo number_format($total_results); ?></div>
                <div class="stat-label">Results</div>
            </div>
            <div class="result-stat">
                <div class="stat-number blue"><?php echo number_format($total_votes); ?></div>
                <div class="stat-label">Total Votes</div>
            </div>
            <div class="result-stat">
                <div class="stat-number green"><?php echo number_format($total_valid); ?></div>
                <div class="stat-label">Valid</div>
            </div>
            <div class="result-stat">
                <div class="stat-number red"><?php echo number_format($total_rejected); ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Results -->
    <?php if (!empty($results)): ?>
        <?php foreach ($results as $result): ?>
            <div class="result-card">
                <div class="result-header">
                    <div>
                        <div class="pu-name">
                            <?php echo htmlspecialchars($result['pu_name'] ?? 'Polling Unit'); ?>
                            <span class="pu-code">(<?php echo htmlspecialchars($result['pu_code'] ?? 'N/A'); ?>)</span>
                        </div>
                        <div class="election-name">
                            <?php echo htmlspecialchars($result['election_name'] ?? 'Election'); ?>
                            <span style="text-transform:capitalize;margin-left:6px;background:#F3F4F6;padding:1px 8px;border-radius:12px;font-size:0.65rem;">
                                <?php echo str_replace('_', ' ', $result['election_type'] ?? ''); ?>
                            </span>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-weight:600;font-size:0.8rem;color:#0F4C81;">
                            <?php echo htmlspecialchars($result['lga_name'] ?? ''); ?>
                        </div>
                        <div style="font-size:0.65rem;color:#6B7280;">
                            <?php echo htmlspecialchars($result['ward_name'] ?? ''); ?>
                        </div>
                    </div>
                </div>

                <?php 
                $party_votes = [];
                if (!empty($result['party_votes_json'])) {
                    $party_votes = json_decode($result['party_votes_json'], true) ?: [];
                }
                ?>
                <?php if (!empty($party_votes)): ?>
                    <div class="party-votes">
                        <?php foreach ($party_votes as $party => $votes): ?>
                            <span class="party-tag">
                                <?php echo htmlspecialchars($party); ?>: 
                                <span class="votes"><?php echo number_format((int)$votes); ?></span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="result-details">
                    <div class="detail-item">
                        <div class="value"><?php echo number_format((int)($result['valid_votes'] ?? 0)); ?></div>
                        <div class="label">Valid</div>
                    </div>
                    <div class="detail-item">
                        <div class="value"><?php echo number_format((int)($result['rejected_votes'] ?? 0)); ?></div>
                        <div class="label">Rejected</div>
                    </div>
                    <div class="detail-item">
                        <div class="value"><?php echo number_format((int)($result['total_votes'] ?? 0)); ?></div>
                        <div class="label">Total</div>
                    </div>
                    <?php if (((float)($result['turnout_percentage'] ?? 0)) > 0): ?>
                        <div class="detail-item">
                            <div class="value"><?php echo (float)$result['turnout_percentage']; ?>%</div>
                            <div class="label">Turnout</div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="result-footer">
                    <span>Published: <?php echo date('M d, Y g:i A', strtotime($result['published_at'] ?? 'now')); ?></span>
                    <?php if ($result['published_by_name']): ?>
                        <span>By: <?php echo htmlspecialchars($result['published_by_name']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <!-- Pagination -->
        <?php if ($total_results > $limit): ?>
            <div class="pagination">
                <?php
                $total_pages = ceil($total_results / $limit);
                $query_params = $_GET;
                unset($query_params['page']);
                $base_url = '?' . http_build_query($query_params);
                
                if ($page > 1): ?>
                    <a href="<?php echo $base_url; ?>&page=<?php echo $page - 1; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="<?php echo $base_url; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="<?php echo $base_url; ?>&page=<?php echo $page + 1; ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-file-alt"></i>
            <h3>No Published Results Found</h3>
            <p>Try adjusting your filters or check back later for published results.</p>
            <a href="published-results.php" style="display:inline-block;margin-top:10px;padding:10px 24px;background:#0F4C81;color:white;border-radius:10px;text-decoration:none;font-weight:600;">
                <i class="fas fa-undo"></i> Reset Filters
            </a>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/public-footer.php'; ?>