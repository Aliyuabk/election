<?php
// ============================================================
// STATE COORDINATOR - MANAGE LGA COORDINATORS
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
// GET FILTER PARAMETERS
// ============================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$lga_filter = isset($_GET['lga']) ? intval($_GET['lga']) : 0;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// ============================================================
// FETCH LGAS FOR FILTER
// ============================================================
$lgas = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM lgas WHERE state_id = ? AND is_active = 1 ORDER BY name");
    $stmt->execute([$state_id]);
    $lgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $lgas = [];
}

// ============================================================
// FETCH LGA COORDINATORS
// ============================================================
$coordinators = [];
$total_coordinators = 0;

try {
    $where_clauses = ['u.tenant_id = ?', 'r.level = "lga"', '(u.deleted_at IS NULL OR u.deleted_at = "0000-00-00 00:00:00")'];
    $params = [$tenant_id];
    
    if ($lga_filter > 0) {
        $where_clauses[] = 'u.jurisdiction_id = ?';
        $params[] = $lga_filter;
    } else {
        $where_clauses[] = 'u.jurisdiction_id IN (SELECT id FROM lgas WHERE state_id = ?)';
        $params[] = $state_id;
    }
    
    if (!empty($status_filter)) {
        $where_clauses[] = 'u.status = ?';
        $params[] = $status_filter;
    }
    
    if (!empty($search)) {
        $where_clauses[] = '(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR l.name LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_sql = implode(' AND ', $where_clauses);
    
    // Count total
    $count_stmt = $db->prepare("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id JOIN lgas l ON u.jurisdiction_id = l.id WHERE $where_sql");
    $count_stmt->execute($params);
    $total_coordinators = $count_stmt->fetchColumn();
    
    // Fetch coordinators
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.user_code,
            u.first_name,
            u.last_name,
            u.full_name,
            u.email,
            u.phone,
            u.status,
            u.last_login_at,
            u.created_at,
            r.name as role_name,
            r.level as role_level,
            l.name as lga_name,
            l.id as lga_id,
            l.code as lga_code,
            (SELECT COUNT(*) FROM users u2 JOIN roles r2 ON u2.role_id = r2.id WHERE u2.tenant_id = ? AND r2.level = 'ward' AND u2.jurisdiction_id IN (SELECT id FROM wards WHERE lga_id = l.id) AND u2.status = 'active' AND (u2.deleted_at IS NULL OR u2.deleted_at = '0000-00-00 00:00:00')) as ward_coordinators,
            (SELECT COUNT(*) FROM users u2 JOIN roles r2 ON u2.role_id = r2.id WHERE u2.tenant_id = ? AND r2.level = 'pu_agent' AND u2.jurisdiction_id IN (SELECT id FROM polling_units WHERE ward_id IN (SELECT id FROM wards WHERE lga_id = l.id)) AND u2.status = 'active' AND (u2.deleted_at IS NULL OR u2.deleted_at = '0000-00-00 00:00:00')) as pu_agents,
            (SELECT COUNT(*) FROM results_ec8a r2 WHERE r2.tenant_id = ? AND r2.lga_id = l.id) as total_results,
            (SELECT COUNT(*) FROM results_ec8a r2 WHERE r2.tenant_id = ? AND r2.lga_id = l.id AND r2.status = 'verified') as verified_results
        FROM users u
        JOIN roles r ON u.role_id = r.id
        JOIN lgas l ON u.jurisdiction_id = l.id
        WHERE $where_sql
        ORDER BY u.full_name ASC
        LIMIT ? OFFSET ?
    ");
    
    $query_params = array_merge([$tenant_id, $tenant_id, $tenant_id, $tenant_id], $params, [$limit, $offset]);
    $stmt->execute($query_params);
    $coordinators = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Manage LGA Coordinators Error: " . $e->getMessage());
}

$total_pages = ceil($total_coordinators / $limit);

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Manage LGA Coordinators';
$page_subtitle = $state_name;
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
                <span style="font-weight:600;color:var(--gray-800);">Manage LGA Coordinators</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <i class="fas fa-user-tie" style="color:var(--primary);"></i>
                        Manage LGA Coordinators
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-users"></i> 
                        <?php echo number_format($total_coordinators); ?> coordinators • <?php echo htmlspecialchars($state_name); ?>
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="coordinators-create.php?state=<?php echo $state_id; ?>&level=lga" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-user-plus"></i> Add Coordinator
                    </a>
                    <a href="monitor-lgas.php" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-user-tie"></i></div>
                <div class="stat-number"><?php echo number_format($total_coordinators); ?></div>
                <div class="stat-label">Total Coordinators</div>
                <div class="stat-change">All LGA coordinators</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-user-check"></i></div>
                <div class="stat-number">
                    <?php 
                    $active = 0;
                    foreach ($coordinators as $c) {
                        if ($c['status'] === 'active') $active++;
                    }
                    echo number_format($active);
                    ?>
                </div>
                <div class="stat-label">Active</div>
                <div class="stat-change up"><i class="fas fa-check-circle"></i> Active staff</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
                <div class="stat-number">
                    <?php 
                    $pending = 0;
                    foreach ($coordinators as $c) {
                        if ($c['status'] === 'pending') $pending++;
                    }
                    echo number_format($pending);
                    ?>
                </div>
                <div class="stat-label">Pending</div>
                <div class="stat-change down"><i class="fas fa-hourglass-half"></i> Awaiting</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-user-slash"></i></div>
                <div class="stat-number">
                    <?php 
                    $suspended = 0;
                    foreach ($coordinators as $c) {
                        if ($c['status'] === 'suspended') $suspended++;
                    }
                    echo number_format($suspended);
                    ?>
                </div>
                <div class="stat-label">Suspended</div>
                <div class="stat-change down"><i class="fas fa-times-circle"></i> Inactive</div>
            </div>
        </div>

        <!-- Filters -->
        <div style="background:white;border-radius:var(--radius);padding:16px 20px;margin-bottom:20px;border:1px solid var(--gray-200);">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
                <div style="flex:1;min-width:150px;">
                    <div class="search-box" style="width:100%;">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search coordinators..." value="<?php echo htmlspecialchars($search); ?>" />
                    </div>
                </div>
                
                <div style="min-width:150px;">
                    <select name="lga" class="form-select" style="width:100%;padding:8px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.8rem;background:white;">
                        <option value="">All LGAs</option>
                        <?php foreach ($lgas as $lga): ?>
                            <option value="<?php echo $lga['id']; ?>" <?php echo $lga_filter == $lga['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lga['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="min-width:120px;">
                    <select name="status" class="form-select" style="width:100%;padding:8px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.8rem;background:white;">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="suspended" <?php echo $status_filter == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-primary" style="padding:8px 24px;background:var(--primary);color:white;border:none;border-radius:10px;font-family:'Inter',sans-serif;font-weight:600;font-size:0.8rem;cursor:pointer;transition:var(--transition);">
                    <i class="fas fa-filter"></i> Filter
                </button>
                
                <?php if (!empty($search) || $lga_filter > 0 || !empty($status_filter)): ?>
                    <a href="manage-lga-coordinators.php" class="btn-reset" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-600);border:none;border-radius:10px;font-family:'Inter',sans-serif;font-weight:500;font-size:0.8rem;cursor:pointer;text-decoration:none;transition:var(--transition);">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Coordinators Table -->
        <div style="background:white;border-radius:var(--radius);overflow:hidden;border:1px solid var(--gray-200);">
            <div style="padding:12px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <h4 style="font-size:0.9rem;font-weight:600;margin:0;">
                    <i class="fas fa-list" style="color:var(--primary);margin-right:6px;"></i>
                    Coordinator List
                </h4>
                <span style="font-size:0.75rem;color:var(--gray-500);"><?php echo count($coordinators); ?> of <?php echo number_format($total_coordinators); ?></span>
            </div>
            
            <?php if (count($coordinators) > 0): ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.8rem;">
                        <thead style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
                            <tr>
                                <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--gray-600);">Coordinator</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">LGA</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Status</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Ward Coords</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">PU Agents</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Results</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Last Login</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($coordinators as $coord): 
                                $status_color = $coord['status'] === 'active' ? '#10B981' : ($coord['status'] === 'pending' ? '#F59E0B' : '#EF4444');
                            ?>
                                <tr style="border-bottom:1px solid var(--gray-100);transition:var(--transition);hover:background:var(--gray-50);">
                                    <td style="padding:10px 14px;">
                                        <div style="display:flex;align-items:center;gap:10px;">
                                            <div style="width:32px;height:32px;border-radius:50%;background:var(--primary);color:white;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:0.7rem;flex-shrink:0;">
                                                <?php echo strtoupper(substr($coord['first_name'] ?? 'U', 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div style="font-weight:500;font-size:0.85rem;"><?php echo htmlspecialchars($coord['full_name'] ?? 'Unknown'); ?></div>
                                                <div style="font-size:0.65rem;color:var(--gray-400);">
                                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($coord['email'] ?? 'N/A'); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;font-size:0.75rem;">
                                        <div style="font-weight:500;"><?php echo htmlspecialchars($coord['lga_name'] ?? 'Unknown'); ?></div>
                                        <?php if (!empty($coord['lga_code'])): ?>
                                            <div style="font-size:0.6rem;color:var(--gray-400);"><?php echo htmlspecialchars($coord['lga_code']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <span style="display:inline-block;padding:2px 10px;border-radius:10px;font-size:0.65rem;font-weight:600;background:<?php echo $status_color; ?>20;color:<?php echo $status_color; ?>;">
                                            <?php echo ucfirst($coord['status'] ?? 'Unknown'); ?>
                                        </span>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;font-weight:600;color:var(--primary);">
                                        <?php echo number_format($coord['ward_coordinators'] ?? 0); ?>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;font-weight:600;color:var(--secondary);">
                                        <?php echo number_format($coord['pu_agents'] ?? 0); ?>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <div style="font-weight:600;"><?php echo number_format($coord['verified_results'] ?? 0); ?></div>
                                        <div style="font-size:0.6rem;color:var(--gray-400);">
                                            / <?php echo number_format($coord['total_results'] ?? 0); ?>
                                        </div>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;font-size:0.7rem;color:var(--gray-500);">
                                        <?php if ($coord['last_login_at']): ?>
                                            <?php echo date('M j, Y', strtotime($coord['last_login_at'])); ?>
                                            <div style="font-size:0.6rem;color:var(--gray-400);">
                                                <?php echo date('g:i A', strtotime($coord['last_login_at'])); ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400);">Never</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <div style="display:flex;gap:4px;justify-content:center;flex-wrap:wrap;">
                                            <a href="coordinator-view.php?id=<?php echo $coord['id']; ?>" class="btn-sm" style="padding:3px 8px;border-radius:4px;background:var(--primary);color:white;text-decoration:none;font-size:0.65rem;" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="coordinator-edit.php?id=<?php echo $coord['id']; ?>" class="btn-sm" style="padding:3px 8px;border-radius:4px;background:var(--gray-200);color:var(--gray-700);text-decoration:none;font-size:0.65rem;" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($coord['status'] === 'active'): ?>
                                                <a href="coordinator-suspend.php?id=<?php echo $coord['id']; ?>" class="btn-sm" style="padding:3px 8px;border-radius:4px;background:#FEE2E2;color:#991B1B;text-decoration:none;font-size:0.65rem;" title="Suspend" onclick="return confirm('Suspend this coordinator?')">
                                                    <i class="fas fa-user-slash"></i>
                                                </a>
                                            <?php elseif ($coord['status'] === 'suspended'): ?>
                                                <a href="coordinator-activate.php?id=<?php echo $coord['id']; ?>" class="btn-sm" style="padding:3px 8px;border-radius:4px;background:#D1FAE5;color:#065F46;text-decoration:none;font-size:0.65rem;" title="Activate" onclick="return confirm('Activate this coordinator?')">
                                                    <i class="fas fa-user-check"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="coordinator-reset-password.php?id=<?php echo $coord['id']; ?>" class="btn-sm" style="padding:3px 8px;border-radius:4px;background:#FEF3C7;color:#92400E;text-decoration:none;font-size:0.65rem;" title="Reset Password">
                                                <i class="fas fa-key"></i>
                                            </a>
                                            <a href="coordinator-activity.php?id=<?php echo $coord['id']; ?>" class="btn-sm" style="padding:3px 8px;border-radius:4px;background:#8B5CF6;color:white;text-decoration:none;font-size:0.65rem;" title="Activity">
                                                <i class="fas fa-clock"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="padding:40px;text-align:center;color:var(--gray-500);">
                    <i class="fas fa-user-tie" style="font-size:2rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                    <p>No LGA coordinators found</p>
                    <a href="coordinators-create.php?state=<?php echo $state_id; ?>&level=lga" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-block;margin-top:12px;">
                        <i class="fas fa-user-plus"></i> Add First Coordinator
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;padding:12px 0;">
                <div style="font-size:0.8rem;color:var(--gray-500);">
                    Showing <?php echo (($page - 1) * $limit) + 1; ?> - <?php echo min($page * $limit, $total_coordinators); ?> of <?php echo number_format($total_coordinators); ?> coordinators
                </div>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&lga=<?php echo $lga_filter; ?>&status=<?php echo urlencode($status_filter); ?>" 
                           class="btn-page" style="padding:6px 14px;border:1px solid var(--gray-200);border-radius:8px;text-decoration:none;color:var(--gray-600);font-size:0.8rem;transition:var(--transition);">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    if ($start_page > 1) {
                        echo '<a href="?page=1&search=' . urlencode($search) . '&lga=' . $lga_filter . '&status=' . urlencode($status_filter) . '" class="btn-page" style="padding:6px 12px;border:1px solid var(--gray-200);border-radius:8px;text-decoration:none;color:var(--gray-600);font-size:0.8rem;transition:var(--transition);">1</a>';
                        if ($start_page > 2) echo '<span style="padding:6px 8px;color:var(--gray-400);">...</span>';
                    }
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&lga=<?php echo $lga_filter; ?>&status=<?php echo urlencode($status_filter); ?>" 
                           class="btn-page <?php echo $i == $page ? 'active' : ''; ?>" 
                           style="padding:6px 12px;border:1px solid <?php echo $i == $page ? 'var(--primary)' : 'var(--gray-200)'; ?>;border-radius:8px;text-decoration:none;color:<?php echo $i == $page ? 'white' : 'var(--gray-600)'; ?>;font-size:0.8rem;transition:var(--transition);background:<?php echo $i == $page ? 'var(--primary)' : 'transparent'; ?>;">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; 
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo '<span style="padding:6px 8px;color:var(--gray-400);">...</span>';
                        echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search) . '&lga=' . $lga_filter . '&status=' . urlencode($status_filter) . '" class="btn-page" style="padding:6px 12px;border:1px solid var(--gray-200);border-radius:8px;text-decoration:none;color:var(--gray-600);font-size:0.8rem;transition:var(--transition);">' . $total_pages . '</a>';
                    }
                    ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&lga=<?php echo $lga_filter; ?>&status=<?php echo urlencode($status_filter); ?>" 
                           class="btn-page" style="padding:6px 14px;border:1px solid var(--gray-200);border-radius:8px;text-decoration:none;color:var(--gray-600);font-size:0.8rem;transition:var(--transition);">
                            Next <i class="fas fa-chevron-right"></i>
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
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: 1fr 1fr; }
    table { font-size: 0.7rem; }
    th, td { padding: 6px 8px !important; }
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