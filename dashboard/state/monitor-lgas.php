<?php
// ============================================================
// STATE COORDINATOR - MONITOR LGAS
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

// Only state coordinator can access
if (SessionManager::get('role_level') !== 'state') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$user_email = SessionManager::get('user_email');
$state_id = SessionManager::get('state_id');
$tenant_id = SessionManager::get('tenant_id');

$db = getDB();

// ============================================================
// FETCH STATE NAME
// ============================================================
$state_name = 'State';
try {
    if ($state_id) {
        $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
        $stmt->execute([$state_id]);
        $state_name = $stmt->fetchColumn() ?: 'State';
    }
} catch (Exception $e) {
    $state_name = 'State';
}

// ============================================================
// FETCH FILTER PARAMETERS
// ============================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'name';
$order = isset($_GET['order']) ? trim($_GET['order']) : 'ASC';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// ============================================================
// BUILD QUERY FOR LGAS
// ============================================================
$where_clauses = ['l.state_id = ?', 'l.is_active = 1'];
$params = [$state_id];

if (!empty($search)) {
    $where_clauses[] = '(l.name LIKE ? OR l.code LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status)) {
    if ($status === 'active') {
        $where_clauses[] = 'l.is_active = 1';
    } elseif ($status === 'inactive') {
        $where_clauses[] = 'l.is_active = 0';
    } elseif ($status === 'reporting') {
        $where_clauses[] = 'EXISTS (SELECT 1 FROM results_ec8a r JOIN polling_units pu ON r.pu_id = pu.id JOIN wards w ON pu.ward_id = w.id WHERE w.lga_id = l.id AND r.tenant_id = ?)';
        $params[] = $tenant_id;
    } elseif ($status === 'no_reports') {
        $where_clauses[] = 'NOT EXISTS (SELECT 1 FROM results_ec8a r JOIN polling_units pu ON r.pu_id = pu.id JOIN wards w ON pu.ward_id = w.id WHERE w.lga_id = l.id AND r.tenant_id = ?)';
        $params[] = $tenant_id;
    }
}

$where_sql = implode(' AND ', $where_clauses);

// ============================================================
// FETCH LGAS WITH AGGREGATED DATA
// ============================================================
$lgas = [];
$total_lgas = 0;

try {
    // Count total
    $count_stmt = $db->prepare("SELECT COUNT(*) FROM lgas l WHERE $where_sql");
    $count_stmt->execute($params);
    $total_lgas = $count_stmt->fetchColumn();

    // Order by
    $order_by = 'l.name ASC';
    if ($sort === 'name') {
        $order_by = "l.name $order";
    } elseif ($sort === 'wards') {
        $order_by = "ward_count $order";
    } elseif ($sort === 'pus') {
        $order_by = "pu_count $order";
    } elseif ($sort === 'coordinators') {
        $order_by = "coordinators $order";
    } elseif ($sort === 'agents') {
        $order_by = "agents $order";
    } elseif ($sort === 'results') {
        $order_by = "verified_results $order";
    } elseif ($sort === 'progress') {
        $order_by = "progress_percent $order";
    } elseif ($sort === 'incidents') {
        $order_by = "incidents $order";
    }

    // Fetch LGAs
    $stmt = $db->prepare("
        SELECT 
            l.id,
            l.name,
            l.code,
            l.registered_voters,
            l.is_active,
            l.gps_lat,
            l.gps_lng,
            (SELECT COUNT(*) FROM wards WHERE lga_id = l.id AND is_active = 1) as ward_count,
            (SELECT COUNT(*) FROM polling_units pu WHERE pu.ward_id IN (SELECT id FROM wards WHERE lga_id = l.id) AND pu.is_active = 1) as pu_count,
            (SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE u.tenant_id = ? AND r.level = 'lga' AND u.jurisdiction_id = l.id AND u.status = 'active' AND (u.deleted_at IS NULL OR u.deleted_at = '0000-00-00 00:00:00')) as coordinators,
            (SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE u.tenant_id = ? AND r.level = 'ward' AND u.jurisdiction_id IN (SELECT id FROM wards WHERE lga_id = l.id) AND u.status = 'active' AND (u.deleted_at IS NULL OR u.deleted_at = '0000-00-00 00:00:00')) as ward_coordinators,
            (SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE u.tenant_id = ? AND r.level = 'pu_agent' AND u.jurisdiction_id IN (SELECT id FROM polling_units WHERE ward_id IN (SELECT id FROM wards WHERE lga_id = l.id)) AND u.status = 'active' AND (u.deleted_at IS NULL OR u.deleted_at = '0000-00-00 00:00:00')) as agents,
            (SELECT COUNT(*) FROM results_ec8a r WHERE r.tenant_id = ? AND r.lga_id = l.id) as total_results,
            (SELECT COUNT(*) FROM results_ec8a r WHERE r.tenant_id = ? AND r.lga_id = l.id AND r.status = 'verified') as verified_results,
            (SELECT COUNT(*) FROM results_ec8a r WHERE r.tenant_id = ? AND r.lga_id = l.id AND r.status = 'pending') as pending_results,
            (SELECT COUNT(*) FROM results_ec8a r WHERE r.tenant_id = ? AND r.lga_id = l.id AND r.status = 'flagged') as flagged_results,
            (SELECT COUNT(*) FROM incidents i WHERE i.tenant_id = ? AND i.lga_id = l.id) as incidents,
            (SELECT COUNT(*) FROM incidents i WHERE i.tenant_id = ? AND i.lga_id = l.id AND i.status IN ('reported', 'investigating')) as open_incidents,
            (SELECT COUNT(*) FROM incidents i WHERE i.tenant_id = ? AND i.lga_id = l.id AND i.severity = 'critical') as critical_incidents,
            CASE 
                WHEN (SELECT COUNT(*) FROM polling_units pu WHERE pu.ward_id IN (SELECT id FROM wards WHERE lga_id = l.id) AND pu.is_active = 1) > 0 
                THEN ROUND((SELECT COUNT(*) FROM results_ec8a r WHERE r.tenant_id = ? AND r.lga_id = l.id AND r.status = 'verified') / 
                    (SELECT COUNT(*) FROM polling_units pu WHERE pu.ward_id IN (SELECT id FROM wards WHERE lga_id = l.id) AND pu.is_active = 1) * 100, 1)
                ELSE 0 
            END as progress_percent
        FROM lgas l
        WHERE $where_sql
        ORDER BY $order_by
        LIMIT ? OFFSET ?
    ");

    // Build params for main query
    $query_params = array_merge(
        [$tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id],
        $params,
        [$limit, $offset]
    );

    $stmt->execute($query_params);
    $lgas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Monitor LGAs Error: " . $e->getMessage());
    $lgas = [];
    $total_lgas = 0;
}

// ============================================================
// CALCULATE SUMMARY STATISTICS
// ============================================================
$summary_stats = [
    'total_lgas' => 0,
    'active_lgas' => 0,
    'reporting_lgas' => 0,
    'total_pus' => 0,
    'total_agents' => 0,
    'total_coordinators' => 0,
    'total_results' => 0,
    'verified_results' => 0,
    'total_incidents' => 0
];

foreach ($lgas as $lga) {
    $summary_stats['total_lgas']++;
    if ($lga['is_active']) $summary_stats['active_lgas']++;
    if ($lga['total_results'] > 0) $summary_stats['reporting_lgas']++;
    $summary_stats['total_pus'] += $lga['pu_count'];
    $summary_stats['total_agents'] += $lga['agents'];
    $summary_stats['total_coordinators'] += $lga['coordinators'];
    $summary_stats['total_results'] += $lga['total_results'];
    $summary_stats['verified_results'] += $lga['verified_results'];
    $summary_stats['total_incidents'] += $lga['incidents'];
}

$total_pages = ceil($total_lgas / $limit);

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Monitor LGAs';
$page_subtitle = $state_name;
?>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h2>Monitor LGAs</h2>
            <p>Local Government Areas in <?php echo htmlspecialchars($state_name); ?></p>
            <div class="breadcrumb">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span>Monitor LGAs</span>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-map-marker-alt"></i></div>
                <div class="stat-number"><?php echo number_format($summary_stats['total_lgas']); ?></div>
                <div class="stat-label">Total LGAs</div>
                <div class="stat-change"><i class="fas fa-check-circle"></i> <?php echo $summary_stats['active_lgas']; ?> active</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-double"></i></div>
                <div class="stat-number"><?php echo number_format($summary_stats['reporting_lgas']); ?></div>
                <div class="stat-label">LGAs Reporting</div>
                <div class="stat-change"><i class="fas fa-upload"></i> <?php echo number_format($summary_stats['total_lgas'] - $summary_stats['reporting_lgas']); ?> pending</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-user-tie"></i></div>
                <div class="stat-number"><?php echo number_format($summary_stats['total_coordinators']); ?></div>
                <div class="stat-label">LGA Coordinators</div>
                <div class="stat-change"><i class="fas fa-users"></i> <?php echo number_format($summary_stats['total_agents']); ?> agents</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-file-alt"></i></div>
                <div class="stat-number"><?php echo number_format($summary_stats['verified_results']); ?></div>
                <div class="stat-label">Verified Results</div>
                <div class="stat-change"><i class="fas fa-clock"></i> <?php echo number_format($summary_stats['total_results'] - $summary_stats['verified_results']); ?> pending</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo number_format($summary_stats['total_incidents']); ?></div>
                <div class="stat-label">Total Incidents</div>
                <div class="stat-change"><i class="fas fa-clock"></i> Reported</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-flag-checkered"></i></div>
                <div class="stat-number"><?php echo number_format($summary_stats['total_pus']); ?></div>
                <div class="stat-label">Polling Units</div>
                <div class="stat-change"><i class="fas fa-users"></i> Total</div>
            </div>
        </div>

        <!-- Filters -->
        <div style="background:white;border-radius:var(--radius);padding:16px 20px;margin-bottom:20px;border:1px solid var(--gray-200);">
            <form method="GET" action="" class="filter-form" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
                <div style="flex:1;min-width:180px;">
                    <div class="search-box" style="width:100%;">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search LGAs..." value="<?php echo htmlspecialchars($search); ?>" />
                    </div>
                </div>
                
                <div style="min-width:140px;">
                    <select name="status" class="form-select" style="width:100%;padding:8px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.8rem;background:white;">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="reporting" <?php echo $status == 'reporting' ? 'selected' : ''; ?>>Reporting</option>
                        <option value="no_reports" <?php echo $status == 'no_reports' ? 'selected' : ''; ?>>No Reports</option>
                    </select>
                </div>
                
                <div style="min-width:120px;">
                    <select name="sort" class="form-select" style="width:100%;padding:8px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.8rem;background:white;">
                        <option value="name" <?php echo $sort == 'name' ? 'selected' : ''; ?>>Sort by Name</option>
                        <option value="progress" <?php echo $sort == 'progress' ? 'selected' : ''; ?>>Sort by Progress</option>
                        <option value="results" <?php echo $sort == 'results' ? 'selected' : ''; ?>>Sort by Results</option>
                        <option value="wards" <?php echo $sort == 'wards' ? 'selected' : ''; ?>>Sort by Wards</option>
                        <option value="pus" <?php echo $sort == 'pus' ? 'selected' : ''; ?>>Sort by PUs</option>
                        <option value="incidents" <?php echo $sort == 'incidents' ? 'selected' : ''; ?>>Sort by Incidents</option>
                    </select>
                </div>
                
                <div style="min-width:100px;">
                    <select name="order" class="form-select" style="width:100%;padding:8px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.8rem;background:white;">
                        <option value="ASC" <?php echo $order == 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                        <option value="DESC" <?php echo $order == 'DESC' ? 'selected' : ''; ?>>Descending</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-primary" style="padding:8px 24px;background:var(--primary);color:white;border:none;border-radius:10px;font-family:'Inter',sans-serif;font-weight:600;font-size:0.8rem;cursor:pointer;transition:var(--transition);">
                    <i class="fas fa-filter"></i> Filter
                </button>
                
                <?php if (!empty($search) || !empty($status) || $sort != 'name'): ?>
                    <a href="monitor-lgas.php" class="btn-reset" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-600);border:none;border-radius:10px;font-family:'Inter',sans-serif;font-weight:500;font-size:0.8rem;cursor:pointer;text-decoration:none;transition:var(--transition);">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- LGAs Table -->
        <div style="background:white;border-radius:var(--radius);overflow:hidden;border:1px solid var(--gray-200);margin-bottom:20px;">
            <div style="overflow-x:auto;">
                <table class="lgas-table" style="width:100%;border-collapse:collapse;font-size:0.8rem;">
                    <thead style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
                        <tr>
                            <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--gray-600);">LGA</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Wards</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">PUs</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Coordinators</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Agents</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Results</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Progress</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Incidents</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($lgas) > 0): ?>
                            <?php foreach ($lgas as $lga): 
                                $progress = $lga['progress_percent'] ?? 0;
                                $progress_color = $progress >= 80 ? '#10B981' : ($progress >= 50 ? '#F59E0B' : '#EF4444');
                            ?>
                                <tr style="border-bottom:1px solid var(--gray-100);transition:var(--transition);hover:background:var(--gray-50);">
                                    <td style="padding:10px 14px;">
                                        <div style="display:flex;align-items:center;gap:10px;">
                                            <div style="width:32px;height:32px;border-radius:50%;background:var(--primary-light);display:flex;align-items:center;justify-content:center;color:var(--primary);font-weight:700;font-size:0.7rem;">
                                                <?php echo strtoupper(substr($lga['name'], 0, 2)); ?>
                                            </div>
                                            <div>
                                                <div style="font-weight:600;color:var(--gray-800);"><?php echo htmlspecialchars($lga['name']); ?></div>
                                                <?php if (!empty($lga['code'])): ?>
                                                    <div style="font-size:0.65rem;color:var(--gray-400);">Code: <?php echo htmlspecialchars($lga['code']); ?></div>
                                                <?php endif; ?>
                                                <?php if ($lga['registered_voters'] > 0): ?>
                                                    <div style="font-size:0.65rem;color:var(--gray-400);">Voters: <?php echo number_format($lga['registered_voters']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;font-weight:500;"><?php echo number_format($lga['ward_count']); ?></td>
                                    <td style="padding:10px 14px;text-align:center;font-weight:500;"><?php echo number_format($lga['pu_count']); ?></td>
                                    <td style="padding:10px 14px;text-align:center;font-weight:500;color:var(--primary);">
                                        <?php echo number_format($lga['coordinators']); ?>
                                        <div style="font-size:0.6rem;color:var(--gray-400);">+<?php echo $lga['ward_coordinators']; ?> ward coordinators</div>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;font-weight:500;color:var(--secondary);"><?php echo number_format($lga['agents']); ?></td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <div style="display:flex;flex-direction:column;align-items:center;gap:2px;">
                                            <span style="font-weight:600;color:var(--gray-800);"><?php echo number_format($lga['verified_results']); ?></span>
                                            <span style="font-size:0.6rem;color:var(--gray-400);">of <?php echo number_format($lga['total_results']); ?></span>
                                        </div>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <div style="display:flex;flex-direction:column;align-items:center;gap:4px;">
                                            <div style="width:80px;height:6px;background:var(--gray-200);border-radius:4px;overflow:hidden;">
                                                <div style="width:<?php echo $progress; ?>%;height:100%;background:<?php echo $progress_color; ?>;border-radius:4px;transition:width 0.6s ease;"></div>
                                            </div>
                                            <span style="font-size:0.65rem;font-weight:600;color:<?php echo $progress_color; ?>;">
                                                <?php echo $progress; ?>%
                                            </span>
                                        </div>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <?php if ($lga['incidents'] > 0): ?>
                                            <div style="display:flex;flex-direction:column;align-items:center;gap:2px;">
                                                <span style="font-weight:600;color:var(--danger);"><?php echo number_format($lga['incidents']); ?></span>
                                                <?php if ($lga['open_incidents'] > 0): ?>
                                                    <span style="font-size:0.6rem;color:var(--warning);"><?php echo $lga['open_incidents']; ?> open</span>
                                                <?php endif; ?>
                                                <?php if ($lga['critical_incidents'] > 0): ?>
                                                    <span style="font-size:0.6rem;color:var(--danger);">🔴 <?php echo $lga['critical_incidents']; ?> critical</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400);font-size:0.7rem;">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <div style="display:flex;gap:6px;justify-content:center;flex-wrap:wrap;">
                                            <a href="lga-dashboard.php?id=<?php echo $lga['id']; ?>" class="btn-sm btn-info" title="LGA Dashboard" style="padding:4px 10px;border-radius:6px;background:#3B82F6;color:white;text-decoration:none;font-size:0.7rem;transition:var(--transition);">
                                                <i class="fas fa-tachometer-alt"></i>
                                            </a>
                                            <a href="lga-coordinators.php?id=<?php echo $lga['id']; ?>" class="btn-sm btn-success" title="View Coordinators" style="padding:4px 10px;border-radius:6px;background:#10B981;color:white;text-decoration:none;font-size:0.7rem;transition:var(--transition);">
                                                <i class="fas fa-user-tie"></i>
                                            </a>
                                            <a href="lga-results.php?id=<?php echo $lga['id']; ?>" class="btn-sm btn-warning" title="View Results" style="padding:4px 10px;border-radius:6px;background:#F59E0B;color:white;text-decoration:none;font-size:0.7rem;transition:var(--transition);">
                                                <i class="fas fa-file-alt"></i>
                                            </a>
                                            <a href="broadcasts-create.php?lga=<?php echo $lga['id']; ?>" class="btn-sm btn-purple" title="Broadcast" style="padding:4px 10px;border-radius:6px;background:#8B5CF6;color:white;text-decoration:none;font-size:0.7rem;transition:var(--transition);">
                                                <i class="fas fa-bullhorn"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="padding:40px;text-align:center;color:var(--gray-500);">
                                    <i class="fas fa-search" style="font-size:2rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                                    No LGAs found matching your criteria
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;padding:12px 0;">
                <div style="font-size:0.8rem;color:var(--gray-500);">
                    Showing <?php echo (($page - 1) * $limit) + 1; ?> - <?php echo min($page * $limit, $total_lgas); ?> of <?php echo number_format($total_lgas); ?> LGAs
                </div>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>" 
                           class="btn-page" style="padding:6px 14px;border:1px solid var(--gray-200);border-radius:8px;text-decoration:none;color:var(--gray-600);font-size:0.8rem;transition:var(--transition);">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    if ($start_page > 1) {
                        echo '<a href="?page=1&search=' . urlencode($search) . '&status=' . urlencode($status) . '&sort=' . urlencode($sort) . '&order=' . urlencode($order) . '" class="btn-page" style="padding:6px 12px;border:1px solid var(--gray-200);border-radius:8px;text-decoration:none;color:var(--gray-600);font-size:0.8rem;transition:var(--transition);">1</a>';
                        if ($start_page > 2) echo '<span style="padding:6px 8px;color:var(--gray-400);">...</span>';
                    }
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>" 
                           class="btn-page <?php echo $i == $page ? 'active' : ''; ?>" 
                           style="padding:6px 12px;border:1px solid <?php echo $i == $page ? 'var(--primary)' : 'var(--gray-200)'; ?>;border-radius:8px;text-decoration:none;color:<?php echo $i == $page ? 'white' : 'var(--gray-600)'; ?>;font-size:0.8rem;transition:var(--transition);background:<?php echo $i == $page ? 'var(--primary)' : 'transparent'; ?>;">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; 
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo '<span style="padding:6px 8px;color:var(--gray-400);">...</span>';
                        echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search) . '&status=' . urlencode($status) . '&sort=' . urlencode($sort) . '&order=' . urlencode($order) . '" class="btn-page" style="padding:6px 12px;border:1px solid var(--gray-200);border-radius:8px;text-decoration:none;color:var(--gray-600);font-size:0.8rem;transition:var(--transition);">' . $total_pages . '</a>';
                    }
                    ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>" 
                           class="btn-page" style="padding:6px 14px;border:1px solid var(--gray-200);border-radius:8px;text-decoration:none;color:var(--gray-600);font-size:0.8rem;transition:var(--transition);">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-top:12px;">
            <a href="lga-coordinators.php?state=<?php echo $state_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-user-tie" style="color:var(--secondary);"></i>
                <span>Manage Coordinators</span>
            </a>
            <a href="broadcasts-create.php?state=<?php echo $state_id; ?>&target=lgas" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-bullhorn" style="color:var(--warning);"></i>
                <span>Broadcast to All LGAs</span>
            </a>
            <a href="reports.php?type=state&id=<?php echo $state_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-file-alt" style="color:var(--danger);"></i>
                <span>Generate State Report</span>
            </a>
            <a href="state-comparison.php" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-chart-bar" style="color:var(--primary);"></i>
                <span>Compare LGAs</span>
            </a>
        </div>
    </div>
</main>

<style>
.btn-sm:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.btn-sm.btn-info:hover { background: #2563EB; }
.btn-sm.btn-success:hover { background: #059669; }
.btn-sm.btn-warning:hover { background: #D97706; }
.btn-sm.btn-purple:hover { background: #7C3AED; }
.quick-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-hover);
    border-color: var(--primary);
}
.btn-page:hover {
    background: var(--gray-50);
    border-color: var(--gray-300);
}
.btn-page.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
.btn-page.active:hover {
    background: var(--primary-dark);
}
.form-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

.lgas-table tbody tr:hover {
    background: var(--gray-50);
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-form > div {
        min-width: 100% !important;
    }
    .lgas-table {
        font-size: 0.7rem;
    }
    .lgas-table th,
    .lgas-table td {
        padding: 6px 8px;
    }
    .btn-sm {
        padding: 2px 6px;
        font-size: 0.6rem;
    }
}
</style>

<script>
// ============================================================
// PRELOADER
// ============================================================
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});

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

// ============================================================
// SIDEBAR DROPDOWNS
// ============================================================
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

// ============================================================
// PROFILE DROPDOWN
// ============================================================
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

// ============================================================
// SEARCH
// ============================================================
var searchInput = document.getElementById('searchInput');
var searchResults = document.getElementById('searchResults');
var searchTimeout;

if (searchInput) {
    searchInput.addEventListener('input', function() {
        var query = this.value.trim();
        clearTimeout(searchTimeout);
        if (query.length < 2) {
            if (searchResults) searchResults.classList.remove('active');
            return;
        }
        searchTimeout = setTimeout(function() {
            fetch('search.php?q=' + encodeURIComponent(query))
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (searchResults) {
                        searchResults.innerHTML = '';
                        if (data && data.length > 0) {
                            data.forEach(function(item) {
                                var div = document.createElement('a');
                                div.className = 'result-item';
                                div.href = item.url || '#';
                                div.innerHTML = '<i class="fas ' + (item.icon || 'fa-file') + '"></i><span class="text-truncate">' + (item.label || item.name || '') + '</span><span class="result-type">' + ((item.type || '').charAt(0).toUpperCase() + (item.type || '').slice(1)) + '</span>';
                                searchResults.appendChild(div);
                            });
                            searchResults.classList.add('active');
                        } else {
                            searchResults.innerHTML = '<div style="padding:12px;text-align:center;color:var(--gray-500);font-size:0.8rem;"><i class="fas fa-search" style="display:block;font-size:1.2rem;margin-bottom:4px;"></i>No results found</div>';
                            searchResults.classList.add('active');
                        }
                    }
                })
                .catch(function() {});
        }, 300);
    });

    document.addEventListener('click', function(e) {
        var wrapper = document.querySelector('.search-wrapper');
        if (wrapper && !wrapper.contains(e.target) && searchResults) {
            searchResults.classList.remove('active');
        }
    });
}
</script>
</body>
</html>