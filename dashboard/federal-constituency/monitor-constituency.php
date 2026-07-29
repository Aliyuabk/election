<?php
// ============================================================
// FEDERAL CONSTITUENCY COORDINATOR - MONITOR CONSTITUENCY
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'federal_constituency') {
    header('Location: ../client-admin/');
    exit();
}

$user_id = SessionManager::get('user_id');
$constituency_id = SessionManager::get('federal_constituency_id');
$state_id = SessionManager::get('state_id');
$tenant_id = SessionManager::get('tenant_id');

$db = getDB();

// ============================================================
// GET CONSTITUENCY DETAILS
// ============================================================
$constituency = null;
$lga_ids = [];
try {
    $stmt = $db->prepare("
        SELECT fc.*, s.name as state_name
        FROM federal_constituencies fc
        JOIN states s ON fc.state_id = s.id
        WHERE fc.id = ?
    ");
    $stmt->execute([$constituency_id]);
    $constituency = $stmt->fetch();
    
    if ($constituency && !empty($constituency['lgas_json'])) {
        $lga_ids = json_decode($constituency['lgas_json'], true) ?: [];
    }
} catch (Exception $e) {
    error_log("Error fetching constituency: " . $e->getMessage());
}

$lga_list = !empty($lga_ids) ? implode(',', array_map('intval', $lga_ids)) : '0';

// ============================================================
// GET SUB-PAGE DATA
// ============================================================
$sub_page = isset($_GET['sub']) ? $_GET['sub'] : 'overview';
$lga_id = isset($_GET['lga']) ? (int)$_GET['lga'] : 0;
$ward_id = isset($_GET['ward']) ? (int)$_GET['ward'] : 0;
$pu_id = isset($_GET['pu']) ? (int)$_GET['pu'] : 0;

// Get LGAs
$lgas = [];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("
            SELECT 
                l.id,
                l.name,
                l.code,
                COUNT(DISTINCT w.id) as ward_count,
                COUNT(DISTINCT pu.id) as pu_count,
                (SELECT COUNT(*) FROM users u 
                 JOIN roles r ON u.role_id = r.id 
                 WHERE u.lga_id = l.id AND u.tenant_id = ? AND u.status = 'active' AND r.level = 'lga') as coordinator_count,
                (SELECT COUNT(*) FROM results_ec8a r 
                 JOIN polling_units pu2 ON r.pu_id = pu2.id 
                 JOIN wards w2 ON pu2.ward_id = w2.id 
                 WHERE w2.lga_id = l.id) as result_count
            FROM lgas l
            LEFT JOIN wards w ON w.lga_id = l.id AND w.is_active = 1
            LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
            WHERE l.id IN ($lga_list) AND l.is_active = 1
            GROUP BY l.id
            ORDER BY l.name ASC
        ");
        $stmt->execute([$tenant_id]);
        $lgas = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching LGAs: " . $e->getMessage());
}

// Get Wards for sub-page
$wards = [];
if ($sub_page === 'wards' || $sub_page === 'ward-details') {
    try {
        $where = [];
        $params = [];
        if ($lga_id > 0) {
            $where[] = "w.lga_id = ?";
            $params[] = $lga_id;
        } elseif ($lga_list !== '0') {
            $where[] = "w.lga_id IN ($lga_list)";
        } else {
            $where[] = "1=0";
        }
        $where_clause = implode(" AND ", $where);
        
        $stmt = $db->prepare("
            SELECT 
                w.*,
                l.name as lga_name,
                COUNT(DISTINCT pu.id) as pu_count,
                (SELECT COUNT(*) FROM users u 
                 WHERE u.ward_id = w.id AND u.tenant_id = ? AND u.status = 'active') as agent_count
            FROM wards w
            JOIN lgas l ON w.lga_id = l.id
            LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
            WHERE $where_clause
            GROUP BY w.id
            ORDER BY w.name ASC
            LIMIT 50
        ");
        $stmt->execute(array_merge($params, [$tenant_id]));
        $wards = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching wards: " . $e->getMessage());
    }
}

// Get Polling Units for sub-page
$polling_units = [];
if ($sub_page === 'pus' || $sub_page === 'pu-details') {
    try {
        $where = [];
        $params = [];
        if ($ward_id > 0) {
            $where[] = "pu.ward_id = ?";
            $params[] = $ward_id;
        } elseif ($lga_id > 0) {
            $where[] = "w.lga_id = ?";
            $params[] = $lga_id;
        } elseif ($lga_list !== '0') {
            $where[] = "w.lga_id IN ($lga_list)";
        } else {
            $where[] = "1=0";
        }
        $where_clause = implode(" AND ", $where);
        
        $stmt = $db->prepare("
            SELECT 
                pu.*,
                w.name as ward_name,
                l.name as lga_name,
                (SELECT COUNT(*) FROM agent_assignments aa WHERE aa.pu_id = pu.id AND aa.status = 'active') as agent_count,
                (SELECT COUNT(*) FROM results_ec8a r WHERE r.pu_id = pu.id AND r.tenant_id = ?) as result_count,
                (SELECT status FROM results_ec8a r WHERE r.pu_id = pu.id AND r.tenant_id = ? ORDER BY r.created_at DESC LIMIT 1) as last_result_status
            FROM polling_units pu
            JOIN wards w ON pu.ward_id = w.id
            JOIN lgas l ON w.lga_id = l.id
            WHERE $where_clause AND pu.is_active = 1
            ORDER BY w.name ASC, pu.name ASC
            LIMIT 50
        ");
        $stmt->execute(array_merge($params, [$tenant_id, $tenant_id]));
        $polling_units = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching polling units: " . $e->getMessage());
    }
}

// Get single LGA details
$lga_detail = null;
if ($lga_id > 0 && $sub_page === 'lga-details') {
    try {
        $stmt = $db->prepare("
            SELECT 
                l.*,
                s.name as state_name,
                COUNT(DISTINCT w.id) as ward_count,
                COUNT(DISTINCT pu.id) as pu_count
            FROM lgas l
            JOIN states s ON l.state_id = s.id
            LEFT JOIN wards w ON w.lga_id = l.id AND w.is_active = 1
            LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
            WHERE l.id = ?
            GROUP BY l.id
        ");
        $stmt->execute([$lga_id]);
        $lga_detail = $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error fetching LGA details: " . $e->getMessage());
    }
}

// Get single Ward details
$ward_detail = null;
if ($ward_id > 0 && $sub_page === 'ward-details') {
    try {
        $stmt = $db->prepare("
            SELECT 
                w.*,
                l.name as lga_name,
                COUNT(DISTINCT pu.id) as pu_count
            FROM wards w
            JOIN lgas l ON w.lga_id = l.id
            LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
            WHERE w.id = ?
            GROUP BY w.id
        ");
        $stmt->execute([$ward_id]);
        $ward_detail = $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error fetching ward details: " . $e->getMessage());
    }
}

// Get single PU details
$pu_detail = null;
if ($pu_id > 0 && $sub_page === 'pu-details') {
    try {
        $stmt = $db->prepare("
            SELECT 
                pu.*,
                w.name as ward_name,
                l.name as lga_name,
                (SELECT COUNT(*) FROM agent_assignments aa WHERE aa.pu_id = pu.id AND aa.status = 'active') as agent_count,
                (SELECT COUNT(*) FROM results_ec8a r WHERE r.pu_id = pu.id AND r.tenant_id = ?) as result_count
            FROM polling_units pu
            JOIN wards w ON pu.ward_id = w.id
            JOIN lgas l ON w.lga_id = l.id
            WHERE pu.id = ?
        ");
        $stmt->execute([$tenant_id, $pu_id]);
        $pu_detail = $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error fetching PU details: " . $e->getMessage());
    }
}

// ============================================================
// GET STATISTICS
// ============================================================
$stats = [
    'total_lgas' => count($lgas),
    'total_wards' => 0,
    'total_pus' => 0,
    'total_coordinators' => 0,
    'total_results' => 0,
    'verified_results' => 0
];

foreach ($lgas as $lga) {
    $stats['total_wards'] += (int)($lga['ward_count'] ?? 0);
    $stats['total_pus'] += (int)($lga['pu_count'] ?? 0);
    $stats['total_coordinators'] += (int)($lga['coordinator_count'] ?? 0);
    $stats['total_results'] += (int)($lga['result_count'] ?? 0);
}

// Get PU status
$pu_status = ['total' => 0, 'verified' => 0, 'pending' => 0];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending
            FROM polling_units pu
            JOIN wards w ON pu.ward_id = w.id
            LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
            WHERE w.lga_id IN ($lga_list) AND pu.is_active = 1
        ");
        $stmt->execute([$tenant_id]);
        $pu_status = $stmt->fetch();
    }
} catch (Exception $e) {
    error_log("Error fetching PU status: " . $e->getMessage());
}

$page_title = 'Monitor Constituency';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.constituency-header {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px 24px;
    margin-bottom: 20px;
}
.constituency-header .header-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 12px;
}
.constituency-header h2 {
    font-size: 1.2rem;
    font-weight: 700;
    margin: 0;
}
.constituency-header .subtitle {
    color: var(--gray-500);
    font-size: 0.85rem;
    margin-top: 2px;
}
.constituency-header .stats-row {
    display: flex;
    gap: 20px;
    margin-top: 10px;
    flex-wrap: wrap;
}
.constituency-header .stats-row .stat-item {
    font-size: 0.85rem;
    color: var(--gray-600);
}
.constituency-header .stats-row .stat-item .number {
    font-weight: 700;
    color: var(--primary);
}

/* Sub-navigation */
.sub-nav {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 6px;
    margin-bottom: 20px;
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
    overflow-x: auto;
}
.sub-nav a {
    padding: 8px 18px;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--gray-600);
    text-decoration: none;
    transition: var(--transition);
    white-space: nowrap;
}
.sub-nav a:hover {
    background: var(--gray-50);
}
.sub-nav a.active {
    background: var(--primary);
    color: white;
}
.sub-nav a .badge {
    background: var(--gray-200);
    color: var(--gray-600);
    padding: 0 8px;
    border-radius: 10px;
    font-size: 0.65rem;
    margin-left: 4px;
}
.sub-nav a.active .badge {
    background: rgba(255,255,255,0.25);
    color: white;
}

/* Content styles */
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 16px;
}
.section-header h3 {
    font-size: 1rem;
    font-weight: 600;
    margin: 0;
}

.lga-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
}
.lga-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 18px 20px;
    transition: var(--transition);
    cursor: pointer;
}
.lga-card:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow);
}
.lga-card .lga-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}
.lga-card .lga-header .lga-name {
    font-weight: 600;
    font-size: 0.95rem;
}
.lga-card .lga-header .lga-code {
    font-size: 0.7rem;
    color: var(--gray-400);
    background: var(--gray-100);
    padding: 2px 10px;
    border-radius: 12px;
}
.lga-card .lga-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
    margin-top: 12px;
}
.lga-card .lga-stats .stat {
    text-align: center;
    padding: 6px;
    background: var(--gray-50);
    border-radius: 6px;
}
.lga-card .lga-stats .stat .number {
    font-weight: 700;
    font-size: 1rem;
}
.lga-card .lga-stats .stat .label {
    font-size: 0.55rem;
    color: var(--gray-500);
    text-transform: uppercase;
}
.lga-card .lga-actions {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid var(--gray-100);
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.lga-card .lga-actions a {
    font-size: 0.7rem;
    padding: 4px 12px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
}
.lga-card .lga-actions .btn-view {
    background: var(--primary);
    color: white;
}
.lga-card .lga-actions .btn-view:hover {
    background: var(--primary-dark);
}
.lga-card .lga-actions .btn-details {
    background: var(--gray-100);
    color: var(--gray-600);
}
.lga-card .lga-actions .btn-details:hover {
    background: var(--gray-200);
}

.ward-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 14px;
}
.ward-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 14px 16px;
    transition: var(--transition);
    cursor: pointer;
}
.ward-card:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow);
}
.ward-card .ward-name {
    font-weight: 600;
    font-size: 0.9rem;
}
.ward-card .ward-lga {
    font-size: 0.75rem;
    color: var(--gray-500);
}
.ward-card .ward-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 6px;
    margin-top: 10px;
}
.ward-card .ward-stats .stat {
    text-align: center;
    padding: 4px;
    background: var(--gray-50);
    border-radius: 4px;
}
.ward-card .ward-stats .stat .number {
    font-weight: 700;
    font-size: 0.9rem;
}
.ward-card .ward-stats .stat .label {
    font-size: 0.5rem;
    color: var(--gray-500);
    text-transform: uppercase;
}
.ward-card .ward-actions {
    margin-top: 10px;
    display: flex;
    gap: 6px;
}
.ward-card .ward-actions a {
    font-size: 0.65rem;
    padding: 3px 10px;
    border-radius: 4px;
    text-decoration: none;
    font-weight: 500;
}
.ward-card .ward-actions .btn-view {
    background: var(--primary);
    color: white;
}
.ward-card .ward-actions .btn-pus {
    background: var(--gray-100);
    color: var(--gray-600);
}

.pu-table-wrap {
    overflow-x: auto;
}
.pu-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}
.pu-table th {
    text-align: left;
    padding: 10px 12px;
    background: var(--gray-50);
    font-weight: 600;
    color: var(--gray-600);
    font-size: 0.7rem;
    text-transform: uppercase;
    border-bottom: 2px solid var(--gray-200);
}
.pu-table td {
    padding: 10px 12px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}
.pu-table tr:hover td {
    background: var(--gray-50);
}
.status-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 0.65rem;
    font-weight: 600;
}
.status-badge.verified { background: #D1FAE5; color: #059669; }
.status-badge.pending { background: #FEF3C7; color: #D97706; }
.status-badge.no-result { background: #F3F4F6; color: #6B7280; }

.detail-content {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
}
.detail-content .detail-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.detail-content .detail-item .label {
    font-size: 0.7rem;
    color: var(--gray-400);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.detail-content .detail-item .value {
    font-size: 0.95rem;
    font-weight: 500;
}

@media (max-width: 768px) {
    .lga-grid {
        grid-template-columns: 1fr;
    }
    .ward-grid {
        grid-template-columns: 1fr;
    }
    .detail-content .detail-row {
        grid-template-columns: 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Constituency Header -->
        <div class="constituency-header">
            <div class="header-row">
                <div>
                    <h2>
                        <i class="fas fa-building" style="color:var(--primary);"></i>
                        <?php echo htmlspecialchars($constituency['name'] ?? 'Federal Constituency'); ?>
                    </h2>
                    <div class="subtitle">
                        <?php echo htmlspecialchars($constituency['state_name'] ?? 'State'); ?>
                        <span style="margin-left:10px;color:var(--gray-300);">|</span>
                        Code: <?php echo htmlspecialchars($constituency['code'] ?? 'N/A'); ?>
                    </div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:0.8rem;color:var(--gray-500);">
                        Submission Rate
                        <span style="font-size:1.1rem;font-weight:700;color:var(--primary);display:block;">
                            <?php 
                            $total_pu = $pu_status['total'] ?? 1;
                            $verified_pu = $pu_status['verified'] ?? 0;
                            echo number_format(($verified_pu / max($total_pu, 1)) * 100, 1);
                            ?>%
                        </span>
                    </div>
                </div>
            </div>
            <div class="stats-row">
                <div class="stat-item"><span class="number"><?php echo $stats['total_lgas']; ?></span> LGAs</div>
                <div class="stat-item"><span class="number"><?php echo $stats['total_wards']; ?></span> Wards</div>
                <div class="stat-item"><span class="number"><?php echo $stats['total_pus']; ?></span> PUs</div>
                <div class="stat-item"><span class="number"><?php echo $stats['total_coordinators']; ?></span> Coordinators</div>
                <div class="stat-item"><span class="number"><?php echo $stats['total_results']; ?></span> Results</div>
            </div>
        </div>

        <!-- Sub Navigation -->
        <div class="sub-nav">
            <a href="monitor-constituency.php?sub=overview" class="<?php echo $sub_page === 'overview' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Overview
            </a>
            <a href="monitor-constituency.php?sub=wards" class="<?php echo $sub_page === 'wards' || $sub_page === 'ward-details' ? 'active' : ''; ?>">
                <i class="fas fa-layer-group"></i> Wards
                <span class="badge"><?php echo $stats['total_wards']; ?></span>
            </a>
            <a href="monitor-constituency.php?sub=pus" class="<?php echo $sub_page === 'pus' || $sub_page === 'pu-details' ? 'active' : ''; ?>">
                <i class="fas fa-flag-checkered"></i> Polling Units
                <span class="badge"><?php echo $stats['total_pus']; ?></span>
            </a>
            <?php if ($lga_id > 0): ?>
                <a href="monitor-constituency.php?sub=lga-details&lga=<?php echo $lga_id; ?>" class="active">
                    <i class="fas fa-map-marker-alt"></i> LGA Details
                </a>
            <?php endif; ?>
            <?php if ($ward_id > 0): ?>
                <a href="monitor-constituency.php?sub=ward-details&ward=<?php echo $ward_id; ?>" class="active">
                    <i class="fas fa-layer-group"></i> Ward Details
                </a>
            <?php endif; ?>
            <?php if ($pu_id > 0): ?>
                <a href="monitor-constituency.php?sub=pu-details&pu=<?php echo $pu_id; ?>" class="active">
                    <i class="fas fa-flag-checkered"></i> PU Details
                </a>
            <?php endif; ?>
        </div>

        <!-- Content -->
        <?php if ($sub_page === 'overview'): ?>
            <!-- LGA Grid -->
            <div class="section-header">
                <h3><i class="fas fa-map-marker-alt" style="color:var(--primary);"></i> LGAs in Constituency</h3>
                <span style="font-size:0.8rem;color:var(--gray-400);"><?php echo count($lgas); ?> LGAs</span>
            </div>
            <div class="lga-grid">
                <?php if (count($lgas) > 0): ?>
                    <?php foreach ($lgas as $lga): ?>
                        <div class="lga-card">
                            <div class="lga-header">
                                <div>
                                    <div class="lga-name"><?php echo htmlspecialchars($lga['name']); ?></div>
                                    <div style="font-size:0.7rem;color:var(--gray-400);">
                                        <?php echo htmlspecialchars($lga['code'] ?? 'N/A'); ?>
                                    </div>
                                </div>
                                <span class="lga-code">
                                    <?php echo $lga['coordinator_count'] ?? 0; ?> coord(s)
                                </span>
                            </div>
                            <div class="lga-stats">
                                <div class="stat">
                                    <div class="number"><?php echo number_format($lga['ward_count'] ?? 0); ?></div>
                                    <div class="label">Wards</div>
                                </div>
                                <div class="stat">
                                    <div class="number"><?php echo number_format($lga['pu_count'] ?? 0); ?></div>
                                    <div class="label">PUs</div>
                                </div>
                                <div class="stat">
                                    <div class="number"><?php echo number_format($lga['coordinator_count'] ?? 0); ?></div>
                                    <div class="label">Coords</div>
                                </div>
                                <div class="stat">
                                    <div class="number"><?php echo number_format($lga['result_count'] ?? 0); ?></div>
                                    <div class="label">Results</div>
                                </div>
                            </div>
                            <div class="lga-actions">
                                <a href="monitor-constituency.php?sub=lga-details&lga=<?php echo $lga['id']; ?>" class="btn-view">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                                <a href="monitor-constituency.php?sub=wards&lga=<?php echo $lga['id']; ?>" class="btn-details">
                                    <i class="fas fa-layer-group"></i> Wards
                                </a>
                                <a href="coordinators.php?lga=<?php echo $lga['id']; ?>" class="btn-details">
                                    <i class="fas fa-user-tie"></i> Coordinators
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--gray-500);">
                        <i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                        <p>No LGAs found in this constituency.</p>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($sub_page === 'wards'): ?>
            <!-- Wards View -->
            <div class="section-header">
                <h3>
                    <i class="fas fa-layer-group" style="color:var(--primary);"></i> 
                    Wards
                    <?php if ($lga_id > 0): 
                        $lga_name = '';
                        foreach ($lgas as $l) { if ($l['id'] == $lga_id) { $lga_name = $l['name']; break; } }
                    ?>
                        - <?php echo htmlspecialchars($lga_name); ?>
                    <?php endif; ?>
                </h3>
                <div style="display:flex;gap:10px;">
                    <?php if ($lga_id > 0): ?>
                        <a href="monitor-constituency.php?sub=overview" class="btn-secondary" style="padding:6px 14px;border-radius:8px;font-size:0.75rem;text-decoration:none;background:var(--gray-100);color:var(--gray-600);">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="ward-grid">
                <?php if (count($wards) > 0): ?>
                    <?php foreach ($wards as $ward): ?>
                        <div class="ward-card">
                            <div class="ward-name"><?php echo htmlspecialchars($ward['name']); ?></div>
                            <div class="ward-lga">
                                <i class="fas fa-map-marker-alt" style="font-size:0.6rem;"></i>
                                <?php echo htmlspecialchars($ward['lga_name']); ?>
                                <span style="margin-left:6px;font-size:0.65rem;color:var(--gray-400);">
                                    <?php echo htmlspecialchars($ward['code'] ?? ''); ?>
                                </span>
                            </div>
                            <div class="ward-stats">
                                <div class="stat">
                                    <div class="number"><?php echo number_format($ward['pu_count'] ?? 0); ?></div>
                                    <div class="label">PUs</div>
                                </div>
                                <div class="stat">
                                    <div class="number"><?php echo number_format($ward['agent_count'] ?? 0); ?></div>
                                    <div class="label">Agents</div>
                                </div>
                                <div class="stat">
                                    <div class="number"><?php echo number_format($ward['registered_voters'] ?? 0); ?></div>
                                    <div class="label">Voters</div>
                                </div>
                            </div>
                            <div class="ward-actions">
                                <a href="monitor-constituency.php?sub=ward-details&ward=<?php echo $ward['id']; ?>" class="btn-view">
                                    <i class="fas fa-eye"></i> Details
                                </a>
                                <a href="monitor-constituency.php?sub=pus&ward=<?php echo $ward['id']; ?>" class="btn-pus">
                                    <i class="fas fa-flag-checkered"></i> PUs
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--gray-500);">
                        <i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                        <p>No wards found.</p>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($sub_page === 'pus'): ?>
            <!-- Polling Units View -->
            <div class="section-header">
                <h3>
                    <i class="fas fa-flag-checkered" style="color:var(--primary);"></i> 
                    Polling Units
                    <?php if ($ward_id > 0): 
                        $ward_name = '';
                        foreach ($wards as $w) { if ($w['id'] == $ward_id) { $ward_name = $w['name']; break; } }
                    ?>
                        - <?php echo htmlspecialchars($ward_name); ?>
                    <?php endif; ?>
                </h3>
                <div style="display:flex;gap:10px;">
                    <?php if ($ward_id > 0): ?>
                        <a href="monitor-constituency.php?sub=wards<?php echo $lga_id > 0 ? '&lga='.$lga_id : ''; ?>" class="btn-secondary" style="padding:6px 14px;border-radius:8px;font-size:0.75rem;text-decoration:none;background:var(--gray-100);color:var(--gray-600);">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (count($polling_units) > 0): ?>
                <div class="pu-table-wrap">
                    <table class="pu-table">
                        <thead>
                            <tr>
                                <th>PU Name</th>
                                <th>Code</th>
                                <th>Ward</th>
                                <th>Agents</th>
                                <th>Results</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($polling_units as $pu): 
                                $status = $pu['last_result_status'] ?? 'no-result';
                                $status_label = $status === 'no-result' ? 'No Result' : ucfirst($status);
                                $status_class = $status === 'verified' ? 'verified' : ($status === 'pending' ? 'pending' : 'no-result');
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($pu['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($pu['code'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($pu['ward_name']); ?></td>
                                    <td><?php echo number_format($pu['agent_count'] ?? 0); ?></td>
                                    <td><?php echo number_format($pu['result_count'] ?? 0); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo $status_label; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="monitor-constituency.php?sub=pu-details&pu=<?php echo $pu['id']; ?>" style="color:var(--primary);text-decoration:none;font-size:0.8rem;">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align:center;padding:40px;color:var(--gray-500);">
                    <i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                    <p>No polling units found.</p>
                </div>
            <?php endif; ?>

        <?php elseif ($sub_page === 'lga-details' && $lga_detail): ?>
            <!-- LGA Details -->
            <div class="detail-content">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;margin-bottom:16px;">
                    <div>
                        <h3 style="font-size:1.1rem;font-weight:700;margin:0;">
                            <?php echo htmlspecialchars($lga_detail['name']); ?>
                        </h3>
                        <div style="color:var(--gray-500);font-size:0.85rem;">
                            <?php echo htmlspecialchars($lga_detail['code'] ?? 'N/A'); ?>
                            <span style="margin-left:10px;"><?php echo htmlspecialchars($lga_detail['state_name']); ?></span>
                        </div>
                    </div>
                    <a href="monitor-constituency.php?sub=overview" class="btn-secondary" style="padding:6px 14px;border-radius:8px;font-size:0.75rem;text-decoration:none;background:var(--gray-100);color:var(--gray-600);">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
                <div class="detail-row">
                    <div class="detail-item">
                        <div class="label">Total Wards</div>
                        <div class="value"><?php echo number_format($lga_detail['ward_count'] ?? 0); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="label">Total Polling Units</div>
                        <div class="value"><?php echo number_format($lga_detail['pu_count'] ?? 0); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="label">Registered Voters</div>
                        <div class="value"><?php echo number_format($lga_detail['registered_voters'] ?? 0); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="label">Status</div>
                        <div class="value">
                            <span class="status-badge <?php echo ($lga_detail['is_active'] ?? 1) ? 'verified' : 'no-result'; ?>">
                                <?php echo ($lga_detail['is_active'] ?? 1) ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;">
                    <a href="monitor-constituency.php?sub=wards&lga=<?php echo $lga_detail['id']; ?>" class="btn-primary" style="padding:8px 18px;border-radius:8px;text-decoration:none;background:var(--primary);color:white;font-weight:500;font-size:0.85rem;">
                        <i class="fas fa-layer-group"></i> View Wards
                    </a>
                    <a href="monitor-constituency.php?sub=pus&lga=<?php echo $lga_detail['id']; ?>" class="btn-primary" style="padding:8px 18px;border-radius:8px;text-decoration:none;background:var(--primary);color:white;font-weight:500;font-size:0.85rem;">
                        <i class="fas fa-flag-checkered"></i> View PUs
                    </a>
                    <a href="coordinators.php?lga=<?php echo $lga_detail['id']; ?>" class="btn-secondary" style="padding:8px 18px;border-radius:8px;text-decoration:none;background:var(--gray-100);color:var(--gray-600);font-weight:500;font-size:0.85rem;">
                        <i class="fas fa-user-tie"></i> Coordinators
                    </a>
                </div>
            </div>

        <?php elseif ($sub_page === 'ward-details' && $ward_detail): ?>
            <!-- Ward Details -->
            <div class="detail-content">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;margin-bottom:16px;">
                    <div>
                        <h3 style="font-size:1.1rem;font-weight:700;margin:0;">
                            <?php echo htmlspecialchars($ward_detail['name']); ?>
                        </h3>
                        <div style="color:var(--gray-500);font-size:0.85rem;">
                            <?php echo htmlspecialchars($ward_detail['code'] ?? 'N/A'); ?>
                            <span style="margin-left:10px;"><?php echo htmlspecialchars($ward_detail['lga_name']); ?></span>
                        </div>
                    </div>
                    <a href="monitor-constituency.php?sub=wards<?php echo $lga_id > 0 ? '&lga='.$lga_id : ''; ?>" class="btn-secondary" style="padding:6px 14px;border-radius:8px;font-size:0.75rem;text-decoration:none;background:var(--gray-100);color:var(--gray-600);">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
                <div class="detail-row">
                    <div class="detail-item">
                        <div class="label">Total Polling Units</div>
                        <div class="value"><?php echo number_format($ward_detail['pu_count'] ?? 0); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="label">Registered Voters</div>
                        <div class="value"><?php echo number_format($ward_detail['registered_voters'] ?? 0); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="label">Status</div>
                        <div class="value">
                            <span class="status-badge <?php echo ($ward_detail['is_active'] ?? 1) ? 'verified' : 'no-result'; ?>">
                                <?php echo ($ward_detail['is_active'] ?? 1) ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;">
                    <a href="monitor-constituency.php?sub=pus&ward=<?php echo $ward_detail['id']; ?>" class="btn-primary" style="padding:8px 18px;border-radius:8px;text-decoration:none;background:var(--primary);color:white;font-weight:500;font-size:0.85rem;">
                        <i class="fas fa-flag-checkered"></i> View PUs
                    </a>
                    <a href="coordinators.php?ward=<?php echo $ward_detail['id']; ?>" class="btn-secondary" style="padding:8px 18px;border-radius:8px;text-decoration:none;background:var(--gray-100);color:var(--gray-600);font-weight:500;font-size:0.85rem;">
                        <i class="fas fa-user-tie"></i> Coordinators
                    </a>
                </div>
            </div>

        <?php elseif ($sub_page === 'pu-details' && $pu_detail): ?>
            <!-- PU Details -->
            <div class="detail-content">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;margin-bottom:16px;">
                    <div>
                        <h3 style="font-size:1.1rem;font-weight:700;margin:0;">
                            <?php echo htmlspecialchars($pu_detail['name']); ?>
                        </h3>
                        <div style="color:var(--gray-500);font-size:0.85rem;">
                            Code: <?php echo htmlspecialchars($pu_detail['code'] ?? 'N/A'); ?>
                            <span style="margin-left:10px;"><?php echo htmlspecialchars($pu_detail['ward_name']); ?></span>
                            <span style="margin-left:10px;"><?php echo htmlspecialchars($pu_detail['lga_name']); ?></span>
                        </div>
                    </div>
                    <a href="monitor-constituency.php?sub=pus<?php echo $ward_id > 0 ? '&ward='.$ward_id : ($lga_id > 0 ? '&lga='.$lga_id : ''); ?>" class="btn-secondary" style="padding:6px 14px;border-radius:8px;font-size:0.75rem;text-decoration:none;background:var(--gray-100);color:var(--gray-600);">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
                <div class="detail-row">
                    <div class="detail-item">
                        <div class="label">Type</div>
                        <div class="value"><?php echo ($pu_detail['is_rural'] ?? 0) ? 'Rural' : 'Urban'; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="label">Registered Voters</div>
                        <div class="value"><?php echo number_format($pu_detail['registered_voters'] ?? 0); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="label">Agents Assigned</div>
                        <div class="value"><?php echo number_format($pu_detail['agent_count'] ?? 0); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="label">Results Submitted</div>
                        <div class="value"><?php echo number_format($pu_detail['result_count'] ?? 0); ?></div>
                    </div>
                </div>
                <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;">
                    <a href="pu-details.php?id=<?php echo $pu_detail['id']; ?>" class="btn-primary" style="padding:8px 18px;border-radius:8px;text-decoration:none;background:var(--primary);color:white;font-weight:500;font-size:0.85rem;">
                        <i class="fas fa-eye"></i> Full Details
                    </a>
                    <a href="verify-ec8a.php?pu=<?php echo $pu_detail['id']; ?>" class="btn-secondary" style="padding:8px 18px;border-radius:8px;text-decoration:none;background:var(--gray-100);color:var(--gray-600);font-weight:500;font-size:0.85rem;">
                        <i class="fas fa-check-double"></i> Verify Results
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
// Sidebar toggle - standard
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