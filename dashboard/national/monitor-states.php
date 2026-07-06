<?php
// ============================================================
// NATIONAL COORDINATOR - MONITOR STATES
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

$db = getDB();

// ============================================================
// FETCH FILTER PARAMETERS
// ============================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$region = isset($_GET['region']) ? trim($_GET['region']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// ============================================================
// FETCH REGIONS (Zones)
// ============================================================
$regions = [
    'North Central' => ['Benue', 'FCT', 'Kogi', 'Kwara', 'Nasarawa', 'Niger', 'Plateau'],
    'North East' => ['Adamawa', 'Bauchi', 'Borno', 'Gombe', 'Taraba', 'Yobe'],
    'North West' => ['Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi', 'Sokoto', 'Zamfara'],
    'South East' => ['Abia', 'Anambra', 'Ebonyi', 'Enugu', 'Imo'],
    'South South' => ['Akwa Ibom', 'Bayelsa', 'Cross River', 'Delta', 'Edo', 'Rivers'],
    'South West' => ['Ekiti', 'Lagos', 'Ogun', 'Ondo', 'Osun', 'Oyo']
];

// ============================================================
// BUILD QUERY
// ============================================================
$where_clauses = ['s.is_active = 1'];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(s.name LIKE ? OR s.capital LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($region) && isset($regions[$region])) {
    $state_placeholders = implode(',', array_fill(0, count($regions[$region]), '?'));
    $where_clauses[] = "s.name IN ($state_placeholders)";
    $params = array_merge($params, $regions[$region]);
}

if (!empty($status)) {
    switch($status) {
        case 'active':
            $where_clauses[] = "s.is_active = 1";
            break;
        case 'inactive':
            $where_clauses[] = "s.is_active = 0";
            break;
    }
}

$where_sql = implode(' AND ', $where_clauses);

// ============================================================
// FETCH STATES WITH AGGREGATED DATA
// ============================================================
try {
    // Count total states
    $count_stmt = $db->prepare("SELECT COUNT(*) FROM states s WHERE $where_sql");
    $count_stmt->execute($params);
    $total_states = $count_stmt->fetchColumn();

    // Fetch states with data
    $stmt = $db->prepare("
        SELECT 
            s.*,
            COUNT(DISTINCT l.id) as lga_count,
            COUNT(DISTINCT w.id) as ward_count,
            COUNT(DISTINCT pu.id) as pu_count,
            (SELECT COUNT(*) FROM users u 
             JOIN roles r ON u.role_id = r.id 
             WHERE u.tenant_id = ? AND r.level = 'state' 
             AND u.jurisdiction_id = s.id AND u.status = 'active') as coordinators,
            (SELECT COUNT(*) FROM users u 
             JOIN roles r ON u.role_id = r.id 
             WHERE u.tenant_id = ? AND r.level = 'pu_agent' 
             AND u.jurisdiction_id IN (
                 SELECT pu.id FROM polling_units pu 
                 JOIN wards w ON pu.ward_id = w.id 
                 JOIN lgas l ON w.lga_id = l.id 
                 WHERE l.state_id = s.id
             ) AND u.status = 'active') as agents,
            (SELECT COUNT(*) FROM results_ec8a r 
             JOIN polling_units pu ON r.pu_id = pu.id 
             JOIN wards w ON pu.ward_id = w.id 
             JOIN lgas l ON w.lga_id = l.id 
             WHERE r.tenant_id = ? AND l.state_id = s.id) as results_total,
            (SELECT COUNT(*) FROM results_ec8a r 
             JOIN polling_units pu ON r.pu_id = pu.id 
             JOIN wards w ON pu.ward_id = w.id 
             JOIN lgas l ON w.lga_id = l.id 
             WHERE r.tenant_id = ? AND l.state_id = s.id AND r.status = 'verified') as results_verified,
            (SELECT COUNT(*) FROM results_ec8a r 
             JOIN polling_units pu ON r.pu_id = pu.id 
             JOIN wards w ON pu.ward_id = w.id 
             JOIN lgas l ON w.lga_id = l.id 
             WHERE r.tenant_id = ? AND l.state_id = s.id AND r.status = 'pending') as results_pending,
            (SELECT COUNT(*) FROM incidents i 
             WHERE i.tenant_id = ? AND i.state_id = s.id) as incidents_total,
            (SELECT COUNT(*) FROM incidents i 
             WHERE i.tenant_id = ? AND i.state_id = s.id AND i.status = 'reported') as incidents_reported,
            (SELECT COUNT(*) FROM incidents i 
             WHERE i.tenant_id = ? AND i.state_id = s.id AND i.status = 'resolved') as incidents_resolved,
            (SELECT COUNT(*) FROM elections e 
             WHERE e.tenant_id = ? AND e.deleted_at IS NULL 
             AND JSON_CONTAINS(e.states_json, JSON_QUOTE(s.id))) as election_count
        FROM states s
        LEFT JOIN lgas l ON l.state_id = s.id
        LEFT JOIN wards w ON w.lga_id = l.id
        LEFT JOIN polling_units pu ON pu.ward_id = w.id
        WHERE $where_sql
        GROUP BY s.id
        ORDER BY s.name ASC
        LIMIT ? OFFSET ?
    ");

    // Build params for main query
    $query_params = array_merge(
        [$tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id],
        [$tenant_id, $tenant_id, $tenant_id, $tenant_id],
        $params,
        [$limit, $offset]
    );

    $stmt->execute($query_params);
    $states = $stmt->fetchAll();

} catch (Exception $e) {
    $states = [];
    $total_states = 0;
    error_log("Monitor States Error: " . $e->getMessage());
}

// ============================================================
// CALCULATE SUMMARY STATISTICS
// ============================================================
$summary_stats = [
    'total_states' => 0,
    'states_reporting' => 0,
    'states_pending' => 0,
    'states_completed' => 0,
    'total_lgas' => 0,
    'total_wards' => 0,
    'total_pus' => 0,
    'total_agents' => 0,
    'total_coordinators' => 0,
    'total_results' => 0,
    'total_incidents' => 0,
    'critical_incidents' => 0
];

foreach ($states as $state) {
    $summary_stats['total_states']++;
    $summary_stats['total_lgas'] += $state['lga_count'];
    $summary_stats['total_wards'] += $state['ward_count'];
    $summary_stats['total_pus'] += $state['pu_count'];
    $summary_stats['total_agents'] += $state['agents'];
    $summary_stats['total_coordinators'] += $state['coordinators'];
    $summary_stats['total_results'] += $state['results_total'];
    $summary_stats['total_incidents'] += $state['incidents_total'];
    
    if ($state['results_verified'] > 0) {
        $summary_stats['states_reporting']++;
    }
    if ($state['results_total'] > 0 && $state['results_verified'] == 0) {
        $summary_stats['states_pending']++;
    }
    if ($state['pu_count'] > 0 && $state['results_total'] >= $state['pu_count'] * 0.9) {
        $summary_stats['states_completed']++;
    }
}

// Calculate page info
$total_pages = ceil($total_states / $limit);

include '../includes/base.php';
include '../includes/sidebar.php';

// Set page title for header
$page_title = 'Monitor States';
$page_subtitle = 'Nationwide overview of all states';
?>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h2>Monitor States</h2>
            <p>Nationwide overview of all states with real-time election data</p>
            <div class="breadcrumb">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span>Monitor States</span>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-flag"></i></div>
                <div class="stat-number"><?php echo number_format($summary_stats['total_states']); ?></div>
                <div class="stat-label">Total States</div>
                <div class="stat-change up"><i class="fas fa-check-circle"></i> <?php echo $summary_stats['states_reporting']; ?> reporting</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-double"></i></div>
                <div class="stat-number"><?php echo number_format($summary_stats['states_completed']); ?></div>
                <div class="stat-label">States Completed</div>
                <div class="stat-change"><i class="fas fa-flag-checkered"></i> <?php echo $summary_stats['states_pending']; ?> pending</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-user-tie"></i></div>
                <div class="stat-number"><?php echo number_format($summary_stats['total_coordinators']); ?></div>
                <div class="stat-label">State Coordinators</div>
                <div class="stat-change"><i class="fas fa-users"></i> Active staff</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-user"></i></div>
                <div class="stat-number"><?php echo number_format($summary_stats['total_agents']); ?></div>
                <div class="stat-label">Total Agents</div>
                <div class="stat-change"><i class="fas fa-flag-checkered"></i> Nationwide</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-file-alt"></i></div>
                <div class="stat-number"><?php echo number_format($summary_stats['total_results']); ?></div>
                <div class="stat-label">Total Results</div>
                <div class="stat-change"><i class="fas fa-upload"></i> Submitted</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo number_format($summary_stats['total_incidents']); ?></div>
                <div class="stat-label">Total Incidents</div>
                <div class="stat-change down"><i class="fas fa-clock"></i> Reported</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section" style="background:white;border-radius:var(--radius);padding:16px 20px;margin-bottom:20px;border:1px solid var(--gray-200);">
            <form method="GET" action="" class="filter-form" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
                <div style="flex:1;min-width:180px;">
                    <div class="search-box" style="width:100%;">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search states..." value="<?php echo htmlspecialchars($search); ?>" />
                    </div>
                </div>
                
                <div style="min-width:160px;">
                    <select name="region" class="form-select" style="width:100%;padding:8px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.8rem;background:white;">
                        <option value="">All Regions</option>
                        <?php foreach (array_keys($regions) as $r): ?>
                            <option value="<?php echo htmlspecialchars($r); ?>" <?php echo $region == $r ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($r); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="min-width:140px;">
                    <select name="status" class="form-select" style="width:100%;padding:8px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.8rem;background:white;">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-primary" style="padding:8px 24px;background:var(--primary);color:white;border:none;border-radius:10px;font-family:'Inter',sans-serif;font-weight:600;font-size:0.8rem;cursor:pointer;transition:var(--transition);">
                    <i class="fas fa-filter"></i> Filter
                </button>
                
                <?php if (!empty($search) || !empty($region) || !empty($status)): ?>
                    <a href="monitor-states.php" class="btn-reset" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-600);border:none;border-radius:10px;font-family:'Inter',sans-serif;font-weight:500;font-size:0.8rem;cursor:pointer;text-decoration:none;transition:var(--transition);">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- States Table -->
        <div style="background:white;border-radius:var(--radius);overflow:hidden;border:1px solid var(--gray-200);margin-bottom:20px;">
            <div style="overflow-x:auto;">
                <table class="states-table" style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                    <thead style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
                        <tr>
                            <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-600);">State</th>
                            <th style="padding:12px 16px;text-align:center;font-weight:600;color:var(--gray-600);">LGAs</th>
                            <th style="padding:12px 16px;text-align:center;font-weight:600;color:var(--gray-600);">Wards</th>
                            <th style="padding:12px 16px;text-align:center;font-weight:600;color:var(--gray-600);">PUs</th>
                            <th style="padding:12px 16px;text-align:center;font-weight:600;color:var(--gray-600);">Coordinators</th>
                            <th style="padding:12px 16px;text-align:center;font-weight:600;color:var(--gray-600);">Agents</th>
                            <th style="padding:12px 16px;text-align:center;font-weight:600;color:var(--gray-600);">Results</th>
                            <th style="padding:12px 16px;text-align:center;font-weight:600;color:var(--gray-600);">Progress</th>
                            <th style="padding:12px 16px;text-align:center;font-weight:600;color:var(--gray-600);">Incidents</th>
                            <th style="padding:12px 16px;text-align:center;font-weight:600;color:var(--gray-600);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($states) > 0): ?>
                            <?php foreach ($states as $state): 
                                $progress_percent = $state['pu_count'] > 0 ? min(100, round(($state['results_verified'] / $state['pu_count']) * 100)) : 0;
                                $progress_color = $progress_percent >= 80 ? '#10B981' : ($progress_percent >= 50 ? '#F59E0B' : '#EF4444');
                            ?>
                                <tr style="border-bottom:1px solid var(--gray-100);transition:var(--transition);hover:background:var(--gray-50);">
                                    <td style="padding:12px 16px;font-weight:600;">
                                        <div style="display:flex;align-items:center;gap:10px;">
                                            <div style="width:32px;height:32px;border-radius:50%;background:var(--primary-light);display:flex;align-items:center;justify-content:center;color:var(--primary);font-weight:700;font-size:0.7rem;">
                                                <?php echo strtoupper(substr($state['name'], 0, 2)); ?>
                                            </div>
                                            <div>
                                                <div style="font-weight:600;color:var(--gray-800);"><?php echo htmlspecialchars($state['name']); ?></div>
                                                <div style="font-size:0.7rem;color:var(--gray-500);">Capital: <?php echo htmlspecialchars($state['capital'] ?? 'N/A'); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding:12px 16px;text-align:center;font-weight:500;"><?php echo number_format($state['lga_count']); ?></td>
                                    <td style="padding:12px 16px;text-align:center;font-weight:500;"><?php echo number_format($state['ward_count']); ?></td>
                                    <td style="padding:12px 16px;text-align:center;font-weight:500;"><?php echo number_format($state['pu_count']); ?></td>
                                    <td style="padding:12px 16px;text-align:center;font-weight:500;color:var(--primary);"><?php echo number_format($state['coordinators']); ?></td>
                                    <td style="padding:12px 16px;text-align:center;font-weight:500;color:var(--secondary);"><?php echo number_format($state['agents']); ?></td>
                                    <td style="padding:12px 16px;text-align:center;">
                                        <div style="display:flex;flex-direction:column;align-items:center;gap:2px;">
                                            <span style="font-weight:600;color:var(--gray-800);"><?php echo number_format($state['results_verified']); ?></span>
                                            <span style="font-size:0.6rem;color:var(--gray-400);">of <?php echo number_format($state['results_total']); ?></span>
                                        </div>
                                    </td>
                                    <td style="padding:12px 16px;text-align:center;">
                                        <div style="display:flex;flex-direction:column;align-items:center;gap:4px;">
                                            <div style="width:80px;height:6px;background:var(--gray-200);border-radius:4px;overflow:hidden;">
                                                <div style="width:<?php echo $progress_percent; ?>%;height:100%;background:<?php echo $progress_color; ?>;border-radius:4px;transition:width 0.6s ease;"></div>
                                            </div>
                                            <span style="font-size:0.65rem;font-weight:600;color:<?php echo $progress_color; ?>;">
                                                <?php echo $progress_percent; ?>%
                                            </span>
                                        </div>
                                    </td>
                                    <td style="padding:12px 16px;text-align:center;">
                                        <?php if ($state['incidents_total'] > 0): ?>
                                            <div style="display:flex;flex-direction:column;align-items:center;gap:2px;">
                                                <span style="font-weight:600;color:var(--danger);"><?php echo number_format($state['incidents_total']); ?></span>
                                                <?php if ($state['incidents_reported'] > 0): ?>
                                                    <span style="font-size:0.6rem;color:var(--warning);"><?php echo $state['incidents_reported']; ?> open</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400);font-size:0.75rem;">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:12px 16px;text-align:center;">
                                        <div style="display:flex;gap:6px;justify-content:center;flex-wrap:wrap;">
                                            <a href="view-state.php?id=<?php echo $state['id']; ?>" class="btn-sm btn-info" title="View Details" style="padding:4px 10px;border-radius:6px;background:#EFF6FF;color:#3B82F6;text-decoration:none;font-size:0.7rem;transition:var(--transition);">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="state-dashboard.php?id=<?php echo $state['id']; ?>" class="btn-sm btn-primary" title="State Dashboard" style="padding:4px 10px;border-radius:6px;background:var(--primary);color:white;text-decoration:none;font-size:0.7rem;transition:var(--transition);">
                                                <i class="fas fa-tachometer-alt"></i>
                                            </a>
                                            <a href="state-coordinators.php?id=<?php echo $state['id']; ?>" class="btn-sm btn-success" title="View Coordinators" style="padding:4px 10px;border-radius:6px;background:#10B981;color:white;text-decoration:none;font-size:0.7rem;transition:var(--transition);">
                                                <i class="fas fa-user-tie"></i>
                                            </a>
                                            <a href="state-results.php?id=<?php echo $state['id']; ?>" class="btn-sm btn-warning" title="View Results" style="padding:4px 10px;border-radius:6px;background:#F59E0B;color:white;text-decoration:none;font-size:0.7rem;transition:var(--transition);">
                                                <i class="fas fa-file-alt"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" style="padding:40px;text-align:center;color:var(--gray-500);">
                                    <i class="fas fa-search" style="font-size:2rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                                    No states found matching your criteria
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
                    Showing <?php echo (($page - 1) * $limit) + 1; ?> - <?php echo min($page * $limit, $total_states); ?> of <?php echo number_format($total_states); ?> states
                </div>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&region=<?php echo urlencode($region); ?>&status=<?php echo urlencode($status); ?>" 
                           class="btn-page" style="padding:6px 14px;border:1px solid var(--gray-200);border-radius:8px;text-decoration:none;color:var(--gray-600);font-size:0.8rem;transition:var(--transition);">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    if ($start_page > 1) {
                        echo '<a href="?page=1&search=' . urlencode($search) . '&region=' . urlencode($region) . '&status=' . urlencode($status) . '" class="btn-page" style="padding:6px 12px;border:1px solid var(--gray-200);border-radius:8px;text-decoration:none;color:var(--gray-600);font-size:0.8rem;transition:var(--transition);">1</a>';
                        if ($start_page > 2) echo '<span style="padding:6px 8px;color:var(--gray-400);">...</span>';
                    }
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&region=<?php echo urlencode($region); ?>&status=<?php echo urlencode($status); ?>" 
                           class="btn-page <?php echo $i == $page ? 'active' : ''; ?>" 
                           style="padding:6px 12px;border:1px solid <?php echo $i == $page ? 'var(--primary)' : 'var(--gray-200)'; ?>;border-radius:8px;text-decoration:none;color:<?php echo $i == $page ? 'white' : 'var(--gray-600)'; ?>;font-size:0.8rem;transition:var(--transition);background:<?php echo $i == $page ? 'var(--primary)' : 'transparent'; ?>;">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; 
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo '<span style="padding:6px 8px;color:var(--gray-400);">...</span>';
                        echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search) . '&region=' . urlencode($region) . '&status=' . urlencode($status) . '" class="btn-page" style="padding:6px 12px;border:1px solid var(--gray-200);border-radius:8px;text-decoration:none;color:var(--gray-600);font-size:0.8rem;transition:var(--transition);">' . $total_pages . '</a>';
                    }
                    ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&region=<?php echo urlencode($region); ?>&status=<?php echo urlencode($status); ?>" 
                           class="btn-page" style="padding:6px 14px;border:1px solid var(--gray-200);border-radius:8px;text-decoration:none;color:var(--gray-600);font-size:0.8rem;transition:var(--transition);">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-top:12px;">
            <a href="state-comparison.php" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-chart-bar" style="color:var(--primary);"></i>
                <span>Compare States</span>
            </a>
            <a href="state-coordinators.php" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-user-tie" style="color:var(--secondary);"></i>
                <span>Manage Coordinators</span>
            </a>
            <a href="broadcasts-create.php?target=states" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-bullhorn" style="color:var(--warning);"></i>
                <span>Broadcast to All States</span>
            </a>
            <a href="reports.php?type=state" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-file-alt" style="color:var(--danger);"></i>
                <span>Generate State Report</span>
            </a>
        </div>
    </div>
</main>

<style>
/* Additional styles for this page */
.btn-sm:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.btn-sm.btn-info:hover { background: #DBEAFE; }
.btn-sm.btn-primary:hover { background: var(--primary-dark); }
.btn-sm.btn-success:hover { background: #059669; }
.btn-sm.btn-warning:hover { background: #D97706; }

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

.states-table tbody tr:hover {
    background: var(--gray-50);
}

@media (max-width: 768px) {
    .filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-form > div {
        min-width: 100% !important;
    }
    .states-table {
        font-size: 0.7rem;
    }
    .states-table th,
    .states-table td {
        padding: 8px 10px;
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