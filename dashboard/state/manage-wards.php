<?php
// ============================================================
// STATE COORDINATOR - MANAGE WARDS
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

// Get LGA ID from URL
$lga_id = isset($_GET['lga']) ? intval($_GET['lga']) : 0;

if ($lga_id <= 0) {
    header('Location: monitor-lgas.php?error=invalid_lga');
    exit();
}

$db = getDB();

// ============================================================
// FETCH LGA AND STATE DATA
// ============================================================
$lga_name = '';
$state_name = '';

try {
    $stmt = $db->prepare("
        SELECT l.name as lga_name, s.name as state_name 
        FROM lgas l 
        JOIN states s ON l.state_id = s.id 
        WHERE l.id = ? AND l.state_id = ?
    ");
    $stmt->execute([$lga_id, $state_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $lga_name = $result['lga_name'];
        $state_name = $result['state_name'];
    } else {
        header('Location: monitor-lgas.php?error=lga_not_found');
        exit();
    }
} catch (Exception $e) {
    error_log("Manage Wards Error: " . $e->getMessage());
    header('Location: monitor-lgas.php?error=database_error');
    exit();
}

// ============================================================
// GET FILTER PARAMETERS
// ============================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// ============================================================
// FETCH WARDS
// ============================================================
$wards = [];
$total_wards = 0;

try {
    $where_clauses = ['w.lga_id = ?'];
    $params = [$lga_id];
    
    if (!empty($search)) {
        $where_clauses[] = '(w.name LIKE ? OR w.code LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($status)) {
        if ($status === 'active') {
            $where_clauses[] = 'w.is_active = 1';
        } elseif ($status === 'inactive') {
            $where_clauses[] = 'w.is_active = 0';
        }
    }
    
    $where_sql = implode(' AND ', $where_clauses);
    
    // Count total
    $count_stmt = $db->prepare("SELECT COUNT(*) FROM wards w WHERE $where_sql");
    $count_stmt->execute($params);
    $total_wards = $count_stmt->fetchColumn();
    
    // Fetch wards
    $stmt = $db->prepare("
        SELECT 
            w.id,
            w.name,
            w.code,
            w.registered_voters,
            w.is_active,
            (SELECT COUNT(*) FROM polling_units WHERE ward_id = w.id AND is_active = 1) as pu_count,
            (SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE u.tenant_id = ? AND r.level = 'ward' AND u.jurisdiction_id = w.id AND u.status = 'active' AND (u.deleted_at IS NULL OR u.deleted_at = '0000-00-00 00:00:00')) as coordinators,
            (SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE u.tenant_id = ? AND r.level = 'pu_agent' AND u.jurisdiction_id IN (SELECT id FROM polling_units WHERE ward_id = w.id) AND u.status = 'active' AND (u.deleted_at IS NULL OR u.deleted_at = '0000-00-00 00:00:00')) as agents,
            (SELECT COUNT(*) FROM results_ec8a r2 WHERE r2.tenant_id = ? AND r2.ward_id = w.id) as total_results,
            (SELECT COUNT(*) FROM results_ec8a r2 WHERE r2.tenant_id = ? AND r2.ward_id = w.id AND r2.status = 'verified') as verified_results,
            (SELECT COUNT(*) FROM incidents i WHERE i.tenant_id = ? AND i.ward_id = w.id) as incidents
        FROM wards w
        WHERE $where_sql
        ORDER BY w.name ASC
        LIMIT ? OFFSET ?
    ");
    
    $query_params = array_merge([$tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id], $params, [$limit, $offset]);
    $stmt->execute($query_params);
    $wards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Manage Wards Error: " . $e->getMessage());
}

$total_pages = ceil($total_wards / $limit);

// ============================================================
// CALCULATE SUMMARY STATISTICS
// ============================================================
$summary = [
    'total' => 0,
    'active' => 0,
    'total_pus' => 0,
    'total_coordinators' => 0,
    'total_agents' => 0,
    'total_results' => 0,
    'verified_results' => 0,
    'total_incidents' => 0
];

foreach ($wards as $ward) {
    $summary['total']++;
    if ($ward['is_active']) $summary['active']++;
    $summary['total_pus'] += $ward['pu_count'];
    $summary['total_coordinators'] += $ward['coordinators'];
    $summary['total_agents'] += $ward['agents'];
    $summary['total_results'] += $ward['total_results'];
    $summary['verified_results'] += $ward['verified_results'];
    $summary['total_incidents'] += $ward['incidents'];
}

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Manage Wards';
$page_subtitle = $lga_name;
?>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Breadcrumb -->
        <div class="welcome-section">
            <div class="breadcrumb">
                <i class="fas fa-home"></i>
                <a href="index.php" style="text-decoration:none;color:var(--gray-500);">Dashboard</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <a href="monitor-lgas.php" style="text-decoration:none;color:var(--gray-500);">Monitor LGAs</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <a href="lga-dashboard.php?id=<?php echo $lga_id; ?>" style="text-decoration:none;color:var(--gray-500);"><?php echo htmlspecialchars($lga_name); ?></a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);">Manage Wards</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <i class="fas fa-layer-group" style="color:var(--primary);"></i>
                        Manage Wards
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-map-marker-alt"></i> 
                        <?php echo htmlspecialchars($lga_name); ?> • 
                        <?php echo number_format($total_wards); ?> wards • 
                        <?php echo $summary['active']; ?> active
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="ward-create.php?lga=<?php echo $lga_id; ?>" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-plus"></i> Add Ward
                    </a>
                    <a href="lga-dashboard.php?id=<?php echo $lga_id; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-layer-group"></i></div>
                <div class="stat-number"><?php echo number_format($summary['total']); ?></div>
                <div class="stat-label">Total Wards</div>
                <div class="stat-change"><i class="fas fa-check-circle"></i> <?php echo $summary['active']; ?> active</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-flag-checkered"></i></div>
                <div class="stat-number"><?php echo number_format($summary['total_pus']); ?></div>
                <div class="stat-label">Polling Units</div>
                <div class="stat-change"><i class="fas fa-users"></i> Total</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-user-tie"></i></div>
                <div class="stat-number"><?php echo number_format($summary['total_coordinators']); ?></div>
                <div class="stat-label">Ward Coordinators</div>
                <div class="stat-change"><i class="fas fa-users"></i> Active</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-user"></i></div>
                <div class="stat-number"><?php echo number_format($summary['total_agents']); ?></div>
                <div class="stat-label">PU Agents</div>
                <div class="stat-change"><i class="fas fa-users"></i> Assigned</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-file-alt"></i></div>
                <div class="stat-number"><?php echo number_format($summary['verified_results']); ?></div>
                <div class="stat-label">Verified Results</div>
                <div class="stat-change"><i class="fas fa-clock"></i> <?php echo number_format($summary['total_results'] - $summary['verified_results']); ?> pending</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo number_format($summary['total_incidents']); ?></div>
                <div class="stat-label">Incidents</div>
                <div class="stat-change"><i class="fas fa-clock"></i> Total</div>
            </div>
        </div>

        <!-- Filters -->
        <div style="background:white;border-radius:var(--radius);padding:12px 16px;margin-bottom:16px;border:1px solid var(--gray-200);">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
                <input type="hidden" name="lga" value="<?php echo $lga_id; ?>">
                
                <div style="flex:1;min-width:150px;">
                    <div class="search-box" style="width:100%;">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search wards..." value="<?php echo htmlspecialchars($search); ?>" />
                    </div>
                </div>
                
                <div style="min-width:120px;">
                    <select name="status" class="form-select" style="width:100%;padding:6px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.75rem;background:white;">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-primary" style="padding:6px 16px;background:var(--primary);color:white;border:none;border-radius:8px;font-family:'Inter',sans-serif;font-weight:600;font-size:0.75rem;cursor:pointer;transition:var(--transition);">
                    <i class="fas fa-filter"></i> Filter
                </button>
                
                <?php if (!empty($search) || !empty($status)): ?>
                    <a href="manage-wards.php?lga=<?php echo $lga_id; ?>" class="btn-reset" style="padding:6px 12px;background:var(--gray-100);color:var(--gray-600);border:none;border-radius:8px;font-family:'Inter',sans-serif;font-weight:500;font-size:0.75rem;cursor:pointer;text-decoration:none;transition:var(--transition);">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Wards Table -->
        <div style="background:white;border-radius:var(--radius);overflow:hidden;border:1px solid var(--gray-200);">
            <div style="padding:10px 16px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <h4 style="font-size:0.85rem;font-weight:600;margin:0;">
                    <i class="fas fa-list" style="color:var(--primary);margin-right:6px;"></i>
                    Ward List
                    <span style="font-size:0.65rem;font-weight:400;color:var(--gray-400);margin-left:8px;">
                        (<?php echo number_format($total_wards); ?> wards)
                    </span>
                </h4>
            </div>
            
            <?php if (count($wards) > 0): ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.75rem;">
                        <thead style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
                            <tr>
                                <th style="padding:8px 12px;text-align:left;font-weight:600;color:var(--gray-600);">Ward</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);">Code</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);">PUs</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);">Coordinators</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);">Agents</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);">Results</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);">Status</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($wards as $ward): 
                                $progress = $ward['pu_count'] > 0 ? round(($ward['verified_results'] / $ward['pu_count']) * 100) : 0;
                            ?>
                                <tr style="border-bottom:1px solid var(--gray-100);transition:var(--transition);hover:background:var(--gray-50);">
                                    <td style="padding:8px 12px;">
                                        <div style="font-weight:500;font-size:0.8rem;"><?php echo htmlspecialchars($ward['name']); ?></div>
                                        <?php if ($ward['registered_voters'] > 0): ?>
                                            <div style="font-size:0.6rem;color:var(--gray-400);">Voters: <?php echo number_format($ward['registered_voters']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:8px 12px;text-align:center;font-size:0.7rem;color:var(--gray-500);">
                                        <?php echo htmlspecialchars($ward['code'] ?? 'N/A'); ?>
                                    </td>
                                    <td style="padding:8px 12px;text-align:center;font-weight:600;">
                                        <?php echo number_format($ward['pu_count']); ?>
                                    </td>
                                    <td style="padding:8px 12px;text-align:center;color:var(--primary);">
                                        <?php echo number_format($ward['coordinators']); ?>
                                    </td>
                                    <td style="padding:8px 12px;text-align:center;color:var(--secondary);">
                                        <?php echo number_format($ward['agents']); ?>
                                    </td>
                                    <td style="padding:8px 12px;text-align:center;">
                                        <span style="font-weight:600;"><?php echo number_format($ward['verified_results']); ?></span>
                                        <span style="font-size:0.55rem;color:var(--gray-400);">/ <?php echo number_format($ward['total_results']); ?></span>
                                        <div style="font-size:0.55rem;color:<?php echo $progress >= 80 ? '#10B981' : ($progress >= 50 ? '#F59E0B' : '#EF4444'); ?>;">
                                            <?php echo $progress; ?>%
                                        </div>
                                    </td>
                                    <td style="padding:8px 12px;text-align:center;">
                                        <span style="display:inline-block;padding:2px 8px;border-radius:8px;font-size:0.6rem;font-weight:600;background:<?php echo $ward['is_active'] ? '#D1FAE5' : '#FEE2E2'; ?>;color:<?php echo $ward['is_active'] ? '#065F46' : '#991B1B'; ?>;">
                                            <?php echo $ward['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td style="padding:8px 12px;text-align:center;">
                                        <div style="display:flex;gap:3px;justify-content:center;flex-wrap:wrap;">
                                            <a href="ward-dashboard.php?id=<?php echo $ward['id']; ?>" class="btn-sm" style="padding:2px 8px;border-radius:4px;background:var(--primary);color:white;text-decoration:none;font-size:0.6rem;" title="Dashboard">
                                                <i class="fas fa-tachometer-alt"></i>
                                            </a>
                                            <a href="ward-edit.php?id=<?php echo $ward['id']; ?>" class="btn-sm" style="padding:2px 8px;border-radius:4px;background:var(--gray-200);color:var(--gray-700);text-decoration:none;font-size:0.6rem;" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="polling-units.php?ward=<?php echo $ward['id']; ?>" class="btn-sm" style="padding:2px 8px;border-radius:4px;background:#8B5CF6;color:white;text-decoration:none;font-size:0.6rem;" title="View PUs">
                                                <i class="fas fa-flag-checkered"></i>
                                            </a>
                                            <?php if ($ward['incidents'] > 0): ?>
                                                <a href="incidents.php?ward=<?php echo $ward['id']; ?>" class="btn-sm" style="padding:2px 8px;border-radius:4px;background:#EF4444;color:white;text-decoration:none;font-size:0.6rem;" title="Incidents">
                                                    <i class="fas fa-exclamation-triangle"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="padding:40px 20px;text-align:center;color:var(--gray-500);">
                    <i class="fas fa-layer-group" style="font-size:2rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                    <p style="font-size:0.85rem;">No wards found in this LGA.</p>
                    <a href="ward-create.php?lga=<?php echo $lga_id; ?>" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-block;margin-top:12px;">
                        <i class="fas fa-plus"></i> Add First Ward
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;padding:10px 0;">
                <div style="font-size:0.7rem;color:var(--gray-500);">
                    Showing <?php echo (($page - 1) * $limit) + 1; ?> - <?php echo min($page * $limit, $total_wards); ?> of <?php echo number_format($total_wards); ?>
                </div>
                <div style="display:flex;gap:4px;flex-wrap:wrap;">
                    <?php if ($page > 1): ?>
                        <a href="?lga=<?php echo $lga_id; ?>&page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" 
                           class="btn-page" style="padding:4px 10px;border:1px solid var(--gray-200);border-radius:6px;text-decoration:none;color:var(--gray-600);font-size:0.7rem;transition:var(--transition);">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    if ($start_page > 1) {
                        echo '<a href="?lga=' . $lga_id . '&page=1&search=' . urlencode($search) . '&status=' . urlencode($status) . '" class="btn-page" style="padding:4px 10px;border:1px solid var(--gray-200);border-radius:6px;text-decoration:none;color:var(--gray-600);font-size:0.7rem;transition:var(--transition);">1</a>';
                        if ($start_page > 2) echo '<span style="padding:4px 6px;color:var(--gray-400);font-size:0.7rem;">...</span>';
                    }
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="?lga=<?php echo $lga_id; ?>&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" 
                           class="btn-page <?php echo $i == $page ? 'active' : ''; ?>" 
                           style="padding:4px 10px;border:1px solid <?php echo $i == $page ? 'var(--primary)' : 'var(--gray-200)'; ?>;border-radius:6px;text-decoration:none;color:<?php echo $i == $page ? 'white' : 'var(--gray-600)'; ?>;font-size:0.7rem;transition:var(--transition);background:<?php echo $i == $page ? 'var(--primary)' : 'transparent'; ?>;">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; 
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo '<span style="padding:4px 6px;color:var(--gray-400);font-size:0.7rem;">...</span>';
                        echo '<a href="?lga=' . $lga_id . '&page=' . $total_pages . '&search=' . urlencode($search) . '&status=' . urlencode($status) . '" class="btn-page" style="padding:4px 10px;border:1px solid var(--gray-200);border-radius:6px;text-decoration:none;color:var(--gray-600);font-size:0.7rem;transition:var(--transition);">' . $total_pages . '</a>';
                    }
                    ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?lga=<?php echo $lga_id; ?>&page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" 
                           class="btn-page" style="padding:4px 10px;border:1px solid var(--gray-200);border-radius:6px;text-decoration:none;color:var(--gray-600);font-size:0.7rem;transition:var(--transition);">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-top:12px;">
            <a href="ward-create.php?lga=<?php echo $lga_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-plus-circle" style="color:var(--primary);"></i>
                <span>Add New Ward</span>
            </a>
            <a href="polling-units.php?lga=<?php echo $lga_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-flag-checkered" style="color:var(--secondary);"></i>
                <span>View Polling Units</span>
            </a>
            <a href="broadcasts-create.php?lga=<?php echo $lga_id; ?>&target=wards" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-bullhorn" style="color:var(--warning);"></i>
                <span>Broadcast to Wards</span>
            </a>
            <a href="reports.php?type=wards&lga=<?php echo $lga_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-file-alt" style="color:var(--danger);"></i>
                <span>Generate Report</span>
            </a>
        </div>
    </div>
</main>

<style>
.btn-sm:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.btn-page:hover { background: var(--gray-50); border-color: var(--gray-300); }
.btn-page.active { background: var(--primary); color: white; border-color: var(--primary); }
.btn-page.active:hover { background: var(--primary-dark); }
.btn-secondary:hover { background: var(--gray-200); transform: translateY(-1px); }
.btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3); }
.quick-action-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-hover); border-color: var(--primary); }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    table { font-size: 0.65rem; }
    th, td { padding: 4px 6px !important; }
}
</style>

<script>
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