<?php
// ============================================================
// STATE COORDINATOR - MANAGE POLLING UNITS
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

// Get parameters
$ward_id = isset($_GET['ward']) ? intval($_GET['ward']) : 0;
$lga_id = isset($_GET['lga']) ? intval($_GET['lga']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$db = getDB();

// ============================================================
// FETCH LOCATION DATA
// ============================================================
$location_name = '';
$back_url = 'monitor-lgas.php';
$ward_name = '';
$lga_name = '';
$state_name = '';
$lga_id_found = 0;
$ward_id_found = 0;

if ($ward_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT w.name as ward_name, w.lga_id, l.name as lga_name, s.name as state_name
            FROM wards w
            JOIN lgas l ON w.lga_id = l.id
            JOIN states s ON l.state_id = s.id
            WHERE w.id = ? AND l.state_id = ?
        ");
        $stmt->execute([$ward_id, $state_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $ward_name = $result['ward_name'];
            $lga_name = $result['lga_name'];
            $state_name = $result['state_name'];
            $lga_id_found = $result['lga_id'];
            $ward_id_found = $ward_id;
            $location_name = $ward_name;
            $back_url = "ward-dashboard.php?id=$ward_id";
        }
    } catch (Exception $e) {
        error_log("Location fetch error: " . $e->getMessage());
    }
} elseif ($lga_id > 0) {
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
            $lga_id_found = $lga_id;
            $location_name = $lga_name;
            $back_url = "lga-dashboard.php?id=$lga_id";
        }
    } catch (Exception $e) {
        error_log("Location fetch error: " . $e->getMessage());
    }
}

// ============================================================
// FETCH WARDS FOR FILTER
// ============================================================
$wards = [];
if ($lga_id_found > 0) {
    try {
        $stmt = $db->prepare("SELECT id, name FROM wards WHERE lga_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$lga_id_found]);
        $wards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $wards = [];
    }
}

// ============================================================
// BUILD QUERY FOR POLLING UNITS
// ============================================================
$where_clauses = ['pu.is_active = 1'];
$params = [];

if ($ward_id_found > 0) {
    $where_clauses[] = 'pu.ward_id = ?';
    $params[] = $ward_id_found;
} elseif ($lga_id_found > 0) {
    $where_clauses[] = 'w.lga_id = ?';
    $params[] = $lga_id_found;
}

if (!empty($search)) {
    $where_clauses[] = '(pu.name LIKE ? OR pu.code LIKE ? OR pu.description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = implode(' AND ', $where_clauses);

// ============================================================
// FETCH POLLING UNITS
// ============================================================
$polling_units = [];
$total_polling_units = 0;

try {
    // Count total
    $count_stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM polling_units pu
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        WHERE $where_sql
    ");
    $count_stmt->execute($params);
    $total_polling_units = $count_stmt->fetchColumn();

    // Fetch polling units
    $stmt = $db->prepare("
        SELECT 
            pu.*,
            w.name as ward_name,
            w.id as ward_id,
            l.name as lga_name,
            l.id as lga_id,
            (SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE u.tenant_id = ? AND r.level = 'pu_agent' AND u.jurisdiction_id = pu.id AND u.status = 'active') as agent_count,
            (SELECT COUNT(*) FROM results_ec8a r2 WHERE r2.pu_id = pu.id AND r2.tenant_id = ?) as result_count,
            (SELECT COUNT(*) FROM results_ec8a r2 WHERE r2.pu_id = pu.id AND r2.tenant_id = ? AND r2.status = 'verified') as verified_count
        FROM polling_units pu
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        WHERE $where_sql
        ORDER BY pu.name ASC
        LIMIT ? OFFSET ?
    ");
    
    $query_params = array_merge([$tenant_id, $tenant_id, $tenant_id], $params, [$limit, $offset]);
    $stmt->execute($query_params);
    $polling_units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Polling Units Error: " . $e->getMessage());
}

$total_pages = ceil($total_polling_units / $limit);

// ============================================================
// NETWORK QUALITY LABELS
// ============================================================
$network_labels = [
    '5g' => '5G',
    '4g' => '4G',
    '3g' => '3G',
    '2g' => '2G',
    'none' => 'No Network'
];
$network_colors = [
    '5g' => '#10B981',
    '4g' => '#3B82F6',
    '3g' => '#F59E0B',
    '2g' => '#EF4444',
    'none' => '#6B7280'
];

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Polling Units';
$page_subtitle = $location_name ? "Location: $location_name" : 'All Polling Units';
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
                <a href="<?php echo $back_url; ?>" style="text-decoration:none;color:var(--gray-500);">
                    <?php echo htmlspecialchars($location_name ?: 'Back'); ?>
                </a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);">Polling Units</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <i class="fas fa-flag-checkered" style="color:var(--primary);"></i>
                        Polling Units
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-map-marker-alt"></i> 
                        <?php echo number_format($total_polling_units); ?> polling units
                        <?php if ($location_name): ?>
                            • <?php echo htmlspecialchars($location_name); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="polling-unit-create.php?<?php echo $ward_id_found > 0 ? 'ward=' . $ward_id_found : ($lga_id_found > 0 ? 'lga=' . $lga_id_found : ''); ?>" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-plus"></i> Add PU
                    </a>
                    <a href="<?php echo $back_url; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-flag-checkered"></i></div>
                <div class="stat-number"><?php echo number_format($total_polling_units); ?></div>
                <div class="stat-label">Total PUs</div>
                <div class="stat-change">Active units</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-users"></i></div>
                <div class="stat-number">
                    <?php 
                    $total_agents = 0;
                    foreach ($polling_units as $pu) {
                        $total_agents += $pu['agent_count'] ?? 0;
                    }
                    echo number_format($total_agents);
                    ?>
                </div>
                <div class="stat-label">Total Agents</div>
                <div class="stat-change"><i class="fas fa-user-tie"></i> Assigned</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-file-alt"></i></div>
                <div class="stat-number">
                    <?php 
                    $total_results = 0;
                    foreach ($polling_units as $pu) {
                        $total_results += $pu['result_count'] ?? 0;
                    }
                    echo number_format($total_results);
                    ?>
                </div>
                <div class="stat-label">Results</div>
                <div class="stat-change"><i class="fas fa-upload"></i> Submitted</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-check-double"></i></div>
                <div class="stat-number">
                    <?php 
                    $total_verified = 0;
                    foreach ($polling_units as $pu) {
                        $total_verified += $pu['verified_count'] ?? 0;
                    }
                    echo number_format($total_verified);
                    ?>
                </div>
                <div class="stat-label">Verified</div>
                <div class="stat-change up"><i class="fas fa-check"></i> Approved</div>
            </div>
        </div>

        <!-- Filters -->
        <div style="background:white;border-radius:var(--radius);padding:12px 16px;margin-bottom:16px;border:1px solid var(--gray-200);">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
                <?php if ($ward_id_found > 0): ?>
                    <input type="hidden" name="ward" value="<?php echo $ward_id_found; ?>">
                <?php elseif ($lga_id_found > 0): ?>
                    <input type="hidden" name="lga" value="<?php echo $lga_id_found; ?>">
                <?php endif; ?>
                
                <div style="flex:1;min-width:150px;">
                    <div class="search-box" style="width:100%;">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search by name, code..." value="<?php echo htmlspecialchars($search); ?>" />
                    </div>
                </div>
                
                <?php if ($lga_id_found > 0): ?>
                <div style="min-width:150px;">
                    <select name="ward" class="form-select" style="width:100%;padding:6px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.7rem;background:white;">
                        <option value="">All Wards</option>
                        <?php foreach ($wards as $ward): ?>
                            <option value="<?php echo $ward['id']; ?>" <?php echo $ward_id_found == $ward['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ward['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <button type="submit" class="btn-primary" style="padding:6px 16px;background:var(--primary);color:white;border:none;border-radius:8px;font-family:'Inter',sans-serif;font-weight:600;font-size:0.7rem;cursor:pointer;transition:var(--transition);">
                    <i class="fas fa-filter"></i> Filter
                </button>
                
                <?php if (!empty($search) || $ward_id_found > 0): ?>
                    <a href="?<?php echo $ward_id_found > 0 ? 'ward=' . $ward_id_found : ($lga_id_found > 0 ? 'lga=' . $lga_id_found : ''); ?>" class="btn-reset" style="padding:6px 10px;background:var(--gray-100);color:var(--gray-600);border:none;border-radius:8px;font-family:'Inter',sans-serif;font-weight:500;font-size:0.7rem;cursor:pointer;text-decoration:none;transition:var(--transition);">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Polling Units Table -->
        <div style="background:white;border-radius:var(--radius);overflow:hidden;border:1px solid var(--gray-200);">
            <div style="padding:10px 16px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <h4 style="font-size:0.85rem;font-weight:600;margin:0;">
                    <i class="fas fa-list" style="color:var(--primary);margin-right:6px;"></i>
                    Polling Units List
                    <span style="font-size:0.65rem;font-weight:400;color:var(--gray-400);margin-left:8px;">
                        (<?php echo number_format($total_polling_units); ?>)
                    </span>
                </h4>
            </div>
            
            <?php if (count($polling_units) > 0): ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.7rem;">
                        <thead style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
                            <tr>
                                <th style="padding:6px 10px;text-align:left;font-weight:600;color:var(--gray-600);">Polling Unit</th>
                                <th style="padding:6px 10px;text-align:center;font-weight:600;color:var(--gray-600);">Code</th>
                                <th style="padding:6px 10px;text-align:center;font-weight:600;color:var(--gray-600);">Location</th>
                                <th style="padding:6px 10px;text-align:center;font-weight:600;color:var(--gray-600);">Agents</th>
                                <th style="padding:6px 10px;text-align:center;font-weight:600;color:var(--gray-600);">Results</th>
                                <th style="padding:6px 10px;text-align:center;font-weight:600;color:var(--gray-600);">Voters</th>
                                <th style="padding:6px 10px;text-align:center;font-weight:600;color:var(--gray-600);">Network</th>
                                <th style="padding:6px 10px;text-align:center;font-weight:600;color:var(--gray-600);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($polling_units as $pu): 
                                $progress = ($pu['result_count'] ?? 0) > 0 ? min(100, round((($pu['verified_count'] ?? 0) / ($pu['result_count'] ?? 1)) * 100)) : 0;
                                $network_color = $network_colors[$pu['network_quality']] ?? '#6B7280';
                                $network_label = $network_labels[$pu['network_quality']] ?? 'Unknown';
                            ?>
                                <tr style="border-bottom:1px solid var(--gray-100);transition:var(--transition);hover:background:var(--gray-50);">
                                    <td style="padding:6px 10px;">
                                        <div style="font-weight:500;font-size:0.75rem;"><?php echo htmlspecialchars($pu['name']); ?></div>
                                        <?php if (!empty($pu['description'])): ?>
                                            <div style="font-size:0.55rem;color:var(--gray-400);"><?php echo htmlspecialchars(substr($pu['description'], 0, 40)) . (strlen($pu['description']) > 40 ? '...' : ''); ?></div>
                                        <?php endif; ?>
                                        <?php if ($pu['is_rural']): ?>
                                            <span style="font-size:0.5rem;background:#FEF3C7;color:#92400E;padding:1px 6px;border-radius:8px;">Rural</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:6px 10px;text-align:center;font-size:0.7rem;color:var(--gray-500);">
                                        <?php echo htmlspecialchars($pu['code'] ?? 'N/A'); ?>
                                    </td>
                                    <td style="padding:6px 10px;text-align:center;font-size:0.65rem;">
                                        <div><?php echo htmlspecialchars($pu['ward_name']); ?></div>
                                        <div style="font-size:0.55rem;color:var(--gray-400);"><?php echo htmlspecialchars($pu['lga_name']); ?></div>
                                    </td>
                                    <td style="padding:6px 10px;text-align:center;font-weight:600;color:var(--secondary);">
                                        <?php echo number_format($pu['agent_count'] ?? 0); ?>
                                    </td>
                                    <td style="padding:6px 10px;text-align:center;">
                                        <div style="font-weight:600;"><?php echo number_format($pu['verified_count'] ?? 0); ?></div>
                                        <div style="font-size:0.5rem;color:var(--gray-400);">
                                            / <?php echo number_format($pu['result_count'] ?? 0); ?>
                                            <span style="color:<?php echo $progress >= 80 ? '#10B981' : ($progress >= 50 ? '#F59E0B' : '#EF4444'); ?>;">
                                                (<?php echo $progress; ?>%)
                                            </span>
                                        </div>
                                    </td>
                                    <td style="padding:6px 10px;text-align:center;">
                                        <?php echo number_format($pu['registered_voters'] ?? 0); ?>
                                    </td>
                                    <td style="padding:6px 10px;text-align:center;">
                                        <?php if (!empty($pu['network_quality'])): ?>
                                            <span style="display:inline-block;padding:1px 6px;border-radius:6px;font-size:0.55rem;font-weight:600;background:<?php echo $network_color; ?>20;color:<?php echo $network_color; ?>;">
                                                <?php echo $network_label; ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400);font-size:0.55rem;">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:6px 10px;text-align:center;">
                                        <div style="display:flex;gap:3px;justify-content:center;flex-wrap:wrap;">
                                            <a href="pu-dashboard.php?id=<?php echo $pu['id']; ?>" class="btn-sm" style="padding:2px 8px;border-radius:4px;background:var(--primary);color:white;text-decoration:none;font-size:0.6rem;" title="Dashboard">
                                                <i class="fas fa-tachometer-alt"></i>
                                            </a>
                                            <a href="polling-unit-edit.php?id=<?php echo $pu['id']; ?>" class="btn-sm" style="padding:2px 8px;border-radius:4px;background:var(--gray-200);color:var(--gray-700);text-decoration:none;font-size:0.6rem;" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="pu-agents.php?pu=<?php echo $pu['id']; ?>" class="btn-sm" style="padding:2px 8px;border-radius:4px;background:var(--secondary);color:white;text-decoration:none;font-size:0.6rem;" title="Agents">
                                                <i class="fas fa-user-tie"></i>
                                            </a>
                                            <a href="pu-results.php?id=<?php echo $pu['id']; ?>" class="btn-sm" style="padding:2px 8px;border-radius:4px;background:var(--warning);color:white;text-decoration:none;font-size:0.6rem;" title="Results">
                                                <i class="fas fa-file-alt"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="padding:40px 20px;text-align:center;color:var(--gray-500);">
                    <i class="fas fa-flag-checkered" style="font-size:2rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                    <p style="font-size:0.85rem;">No polling units found matching your criteria.</p>
                    <?php if ($location_name): ?>
                        <a href="polling-unit-create.php?<?php echo $ward_id_found > 0 ? 'ward=' . $ward_id_found : ($lga_id_found > 0 ? 'lga=' . $lga_id_found : ''); ?>" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-block;margin-top:12px;">
                            <i class="fas fa-plus"></i> Add Polling Unit
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;padding:10px 0;">
                <div style="font-size:0.65rem;color:var(--gray-500);">
                    Showing <?php echo (($page - 1) * $limit) + 1; ?> - <?php echo min($page * $limit, $total_polling_units); ?> of <?php echo number_format($total_polling_units); ?>
                </div>
                <div style="display:flex;gap:4px;flex-wrap:wrap;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&ward=<?php echo $ward_id_found; ?>&lga=<?php echo $lga_id_found; ?>&search=<?php echo urlencode($search); ?>" 
                           class="btn-page" style="padding:4px 10px;border:1px solid var(--gray-200);border-radius:6px;text-decoration:none;color:var(--gray-600);font-size:0.65rem;transition:var(--transition);">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    if ($start_page > 1) {
                        echo '<a href="?page=1&ward=' . $ward_id_found . '&lga=' . $lga_id_found . '&search=' . urlencode($search) . '" class="btn-page" style="padding:4px 10px;border:1px solid var(--gray-200);border-radius:6px;text-decoration:none;color:var(--gray-600);font-size:0.65rem;transition:var(--transition);">1</a>';
                        if ($start_page > 2) echo '<span style="padding:4px 6px;color:var(--gray-400);font-size:0.65rem;">...</span>';
                    }
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="?page=<?php echo $i; ?>&ward=<?php echo $ward_id_found; ?>&lga=<?php echo $lga_id_found; ?>&search=<?php echo urlencode($search); ?>" 
                           class="btn-page <?php echo $i == $page ? 'active' : ''; ?>" 
                           style="padding:4px 10px;border:1px solid <?php echo $i == $page ? 'var(--primary)' : 'var(--gray-200)'; ?>;border-radius:6px;text-decoration:none;color:<?php echo $i == $page ? 'white' : 'var(--gray-600)'; ?>;font-size:0.65rem;transition:var(--transition);background:<?php echo $i == $page ? 'var(--primary)' : 'transparent'; ?>;">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; 
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo '<span style="padding:4px 6px;color:var(--gray-400);font-size:0.65rem;">...</span>';
                        echo '<a href="?page=' . $total_pages . '&ward=' . $ward_id_found . '&lga=' . $lga_id_found . '&search=' . urlencode($search) . '" class="btn-page" style="padding:4px 10px;border:1px solid var(--gray-200);border-radius:6px;text-decoration:none;color:var(--gray-600);font-size:0.65rem;transition:var(--transition);">' . $total_pages . '</a>';
                    }
                    ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&ward=<?php echo $ward_id_found; ?>&lga=<?php echo $lga_id_found; ?>&search=<?php echo urlencode($search); ?>" 
                           class="btn-page" style="padding:4px 10px;border:1px solid var(--gray-200);border-radius:6px;text-decoration:none;color:var(--gray-600);font-size:0.65rem;transition:var(--transition);">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<style>
.btn-sm:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.btn-page:hover { background: var(--gray-50); border-color: var(--gray-300); }
.btn-page.active { background: var(--primary); color: white; border-color: var(--primary); }
.btn-page.active:hover { background: var(--primary-dark); }
.btn-secondary:hover { background: var(--gray-200); transform: translateY(-1px); }
.btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3); }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    table { font-size: 0.6rem; }
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