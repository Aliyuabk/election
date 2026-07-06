<?php
// ============================================================
// NATIONAL COORDINATOR - ACTIVITY LOGS
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

// Get parameters
$lga_id = isset($_GET['lga']) ? intval($_GET['lga']) : 0;
$state_id = isset($_GET['state']) ? intval($_GET['state']) : 0;
$user_filter = isset($_GET['user']) ? intval($_GET['user']) : 0;
$type_filter = isset($_GET['type']) ? trim($_GET['type']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$db = getDB();

// ============================================================
// FETCH LGA NAME IF PROVIDED
// ============================================================
$lga_name = '';
$state_name = '';
$back_url = 'monitor-states.php';

if ($lga_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT l.name as lga_name, s.name as state_name 
            FROM lgas l 
            JOIN states s ON l.state_id = s.id 
            WHERE l.id = ?
        ");
        $stmt->execute([$lga_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $lga_name = $result['lga_name'];
            $state_name = $result['state_name'];
            $back_url = "lga-dashboard.php?id=$lga_id";
        }
    } catch (Exception $e) {
        error_log("LGA name fetch error: " . $e->getMessage());
    }
} elseif ($state_id > 0) {
    try {
        $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
        $stmt->execute([$state_id]);
        $state_name = $stmt->fetchColumn();
        $back_url = "view-state.php?id=$state_id";
    } catch (Exception $e) {
        error_log("State name fetch error: " . $e->getMessage());
    }
}

// ============================================================
// FETCH USERS FOR FILTER
// ============================================================
$users = [];
try {
    $stmt = $db->prepare("
        SELECT id, full_name, email 
        FROM users 
        WHERE tenant_id = ? AND status = 'active'
        ORDER BY full_name ASC
    ");
    $stmt->execute([$tenant_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $users = [];
}

// ============================================================
// BUILD QUERY FOR ACTIVITY LOGS
// ============================================================
$where_clauses = ['a.tenant_id = ?'];
$params = [$tenant_id];

if ($lga_id > 0) {
    $where_clauses[] = "(
        (a.entity_type = 'lga' AND a.entity_id = ?) OR
        (a.entity_type = 'ward' AND a.entity_id IN (SELECT id FROM wards WHERE lga_id = ?)) OR
        (a.entity_type = 'pu' AND a.entity_id IN (SELECT id FROM polling_units WHERE ward_id IN (SELECT id FROM wards WHERE lga_id = ?)))
    )";
    $params[] = $lga_id;
    $params[] = $lga_id;
    $params[] = $lga_id;
} elseif ($state_id > 0) {
    $where_clauses[] = "(
        (a.entity_type = 'state' AND a.entity_id = ?) OR
        (a.entity_type = 'lga' AND a.entity_id IN (SELECT id FROM lgas WHERE state_id = ?)) OR
        (a.entity_type = 'ward' AND a.entity_id IN (SELECT id FROM wards WHERE lga_id IN (SELECT id FROM lgas WHERE state_id = ?))) OR
        (a.entity_type = 'pu' AND a.entity_id IN (SELECT id FROM polling_units WHERE ward_id IN (SELECT id FROM wards WHERE lga_id IN (SELECT id FROM lgas WHERE state_id = ?))))
    )";
    $params[] = $state_id;
    $params[] = $state_id;
    $params[] = $state_id;
    $params[] = $state_id;
}

if ($user_filter > 0) {
    $where_clauses[] = 'a.user_id = ?';
    $params[] = $user_filter;
}

if (!empty($type_filter)) {
    $where_clauses[] = 'a.activity_type = ?';
    $params[] = $type_filter;
}

if (!empty($search)) {
    $where_clauses[] = '(a.description LIKE ? OR u.full_name LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = implode(' AND ', $where_clauses);

// ============================================================
// FETCH ACTIVITY LOGS
// ============================================================
$activities = [];
$total_activities = 0;

try {
    // Count total
    $count_stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM activity_logs a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE $where_sql
    ");
    $count_stmt->execute($params);
    $total_activities = $count_stmt->fetchColumn();

    // Fetch activities
    $stmt = $db->prepare("
        SELECT 
            a.*,
            u.full_name as user_name,
            u.email as user_email,
            u.photograph_url as user_avatar
        FROM activity_logs a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE $where_sql
        ORDER BY a.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $query_params = array_merge($params, [$limit, $offset]);
    $stmt->execute($query_params);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Activity Logs Error: " . $e->getMessage());
    $activities = [];
}

// ============================================================
// GET ACTIVITY TYPE STATISTICS
// ============================================================
$type_stats = [];
try {
    $stats_where = $where_sql;
    $stats_params = $params;
    
    $stmt = $db->prepare("
        SELECT 
            activity_type,
            COUNT(*) as count,
            DATE(created_at) as date
        FROM activity_logs a
        WHERE $stats_where
        GROUP BY activity_type, DATE(created_at)
        ORDER BY date DESC, count DESC
        LIMIT 50
    ");
    $stmt->execute($stats_params);
    $type_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $type_stats = [];
}

// Calculate total pages
$total_pages = ceil($total_activities / $limit);

// Activity type colors
$type_colors = [
    'login' => '#3B82F6',
    'logout' => '#6B7280',
    'user_created' => '#10B981',
    'user_suspended' => '#EF4444',
    'user_activated' => '#10B981',
    'user_deleted' => '#EF4444',
    'user_archived' => '#F59E0B',
    'tenant_created' => '#8B5CF6',
    'tenant_updated' => '#8B5CF6',
    'election_created' => '#0D9488',
    'election_updated' => '#0D9488',
    'election_deleted' => '#EF4444',
    'election_status_changed' => '#F59E0B',
    'candidate_added' => '#8B5CF6',
    'party_added' => '#8B5CF6',
    'broadcast_created' => '#F59E0B',
    'invoice_created' => '#10B981',
    'invoice_sent' => '#3B82F6',
    'invoice_paid' => '#10B981',
    'backup_created' => '#8B5CF6',
    'settings_changed' => '#6B7280',
    'password_reset' => '#F59E0B',
    'password_change' => '#F59E0B',
    'profile_updated' => '#3B82F6',
    '2fa_enabled' => '#10B981',
    '2fa_disabled' => '#EF4444',
    'state_added' => '#10B981',
    'state_deleted' => '#EF4444',
    'lga_added' => '#10B981',
    'ward_added' => '#10B981',
    'pu_added' => '#10B981',
    'broadcast_resent' => '#F59E0B',
    'ticket_created' => '#8B5CF6',
    'ticket_replied' => '#8B5CF6',
    'ticket_assigned' => '#8B5CF6',
    'budget_created' => '#10B981',
    'expense_created' => '#F59E0B',
];

$type_icons = [
    'login' => 'fa-sign-in-alt',
    'logout' => 'fa-sign-out-alt',
    'user_created' => 'fa-user-plus',
    'user_suspended' => 'fa-user-slash',
    'user_activated' => 'fa-user-check',
    'user_deleted' => 'fa-user-times',
    'user_archived' => 'fa-archive',
    'tenant_created' => 'fa-building',
    'tenant_updated' => 'fa-edit',
    'election_created' => 'fa-vote-yea',
    'election_updated' => 'fa-edit',
    'election_deleted' => 'fa-trash',
    'election_status_changed' => 'fa-exchange-alt',
    'candidate_added' => 'fa-user-plus',
    'party_added' => 'fa-flag',
    'broadcast_created' => 'fa-bullhorn',
    'broadcast_resent' => 'fa-redo',
    'invoice_created' => 'fa-file-invoice',
    'invoice_sent' => 'fa-paper-plane',
    'invoice_paid' => 'fa-check-circle',
    'backup_created' => 'fa-database',
    'settings_changed' => 'fa-cog',
    'password_reset' => 'fa-key',
    'password_change' => 'fa-key',
    'profile_updated' => 'fa-user-edit',
    '2fa_enabled' => 'fa-shield-alt',
    '2fa_disabled' => 'fa-shield-alt',
    'state_added' => 'fa-flag',
    'state_deleted' => 'fa-flag',
    'lga_added' => 'fa-map-marker-alt',
    'ward_added' => 'fa-layer-group',
    'pu_added' => 'fa-flag-checkered',
    'ticket_created' => 'fa-ticket-alt',
    'ticket_replied' => 'fa-reply',
    'ticket_assigned' => 'fa-user-check',
    'budget_created' => 'fa-wallet',
    'expense_created' => 'fa-money-bill-wave',
];

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Activity Logs';
$page_subtitle = $lga_name ? "LGA: $lga_name" : ($state_name ? "State: $state_name" : 'All Activities');
?>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Breadcrumb -->
        <div class="welcome-section">
            <div class="breadcrumb">
                <i class="fas fa-home"></i>
                <a href="../national/index.php" style="text-decoration:none;color:var(--gray-500);">Dashboard</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <?php if ($lga_id > 0): ?>
                    <a href="<?php echo $back_url; ?>" style="text-decoration:none;color:var(--gray-500);"><?php echo htmlspecialchars($lga_name); ?></a>
                    <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <?php elseif ($state_id > 0): ?>
                    <a href="<?php echo $back_url; ?>" style="text-decoration:none;color:var(--gray-500);"><?php echo htmlspecialchars($state_name); ?></a>
                    <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <?php endif; ?>
                <span style="font-weight:600;color:var(--gray-800);">Activity Logs</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">Activity Logs</h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-clock"></i> 
                        <?php echo number_format($total_activities); ?> activities recorded
                        <?php if ($lga_id > 0): ?>
                            • <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($lga_name); ?>
                        <?php elseif ($state_id > 0): ?>
                            • <i class="fas fa-flag"></i> <?php echo htmlspecialchars($state_name); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <a href="<?php echo $back_url; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div style="background:white;border-radius:var(--radius);padding:16px 20px;margin-bottom:20px;border:1px solid var(--gray-200);">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
                <?php if ($lga_id > 0): ?>
                    <input type="hidden" name="lga" value="<?php echo $lga_id; ?>">
                <?php endif; ?>
                <?php if ($state_id > 0): ?>
                    <input type="hidden" name="state" value="<?php echo $state_id; ?>">
                <?php endif; ?>
                
                <div style="flex:1;min-width:150px;">
                    <div class="search-box" style="width:100%;">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search activities..." value="<?php echo htmlspecialchars($search); ?>" />
                    </div>
                </div>
                
                <div style="min-width:140px;">
                    <select name="type" class="form-select" style="width:100%;padding:8px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.8rem;background:white;">
                        <option value="">All Types</option>
                        <?php foreach (array_keys($type_colors) as $type): ?>
                            <option value="<?php echo $type; ?>" <?php echo $type_filter == $type ? 'selected' : ''; ?>>
                                <?php echo ucfirst(str_replace('_', ' ', $type)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="min-width:160px;">
                    <select name="user" class="form-select" style="width:100%;padding:8px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.8rem;background:white;">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn-primary" style="padding:8px 24px;background:var(--primary);color:white;border:none;border-radius:10px;font-family:'Inter',sans-serif;font-weight:600;font-size:0.8rem;cursor:pointer;transition:var(--transition);">
                    <i class="fas fa-filter"></i> Filter
                </button>
                
                <?php if (!empty($search) || !empty($type_filter) || $user_filter > 0): ?>
                    <a href="?<?php echo $lga_id > 0 ? 'lga=' . $lga_id : ($state_id > 0 ? 'state=' . $state_id : ''); ?>" class="btn-reset" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-600);border:none;border-radius:10px;font-family:'Inter',sans-serif;font-weight:500;font-size:0.8rem;cursor:pointer;text-decoration:none;transition:var(--transition);">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Activity Logs List -->
        <div style="background:white;border-radius:var(--radius);overflow:hidden;border:1px solid var(--gray-200);margin-bottom:20px;">
            <div style="padding:12px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <h4 style="font-size:0.9rem;font-weight:600;margin:0;">
                    <i class="fas fa-list" style="color:var(--primary);margin-right:6px;"></i>
                    Activities
                </h4>
                <span style="font-size:0.75rem;color:var(--gray-500);">Showing <?php echo count($activities); ?> of <?php echo number_format($total_activities); ?></span>
            </div>
            
            <?php if (count($activities) > 0): ?>
                <div style="overflow-y:auto;max-height:600px;">
                    <?php foreach ($activities as $activity): 
                        $type_color = $type_colors[$activity['activity_type']] ?? '#6B7280';
                        $type_icon = $type_icons[$activity['activity_type']] ?? 'fa-circle';
                    ?>
                        <div style="display:flex;align-items:flex-start;gap:14px;padding:12px 20px;border-bottom:1px solid var(--gray-100);transition:var(--transition);hover:background:var(--gray-50);">
                            <div style="width:40px;height:40px;border-radius:50%;background:<?php echo $type_color; ?>20;display:flex;align-items:center;justify-content:center;color:<?php echo $type_color; ?>;flex-shrink:0;">
                                <i class="fas <?php echo $type_icon; ?>" style="font-size:0.9rem;"></i>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:4px;">
                                    <div>
                                        <span style="font-weight:600;font-size:0.85rem;color:var(--gray-800);">
                                            <?php echo htmlspecialchars($activity['user_name'] ?? 'System'); ?>
                                        </span>
                                        <span style="font-size:0.7rem;color:var(--gray-400);margin:0 6px;">•</span>
                                        <span style="display:inline-block;padding:1px 10px;border-radius:10px;font-size:0.6rem;font-weight:600;background:<?php echo $type_color; ?>20;color:<?php echo $type_color; ?>;">
                                            <?php echo ucfirst(str_replace('_', ' ', $activity['activity_type'])); ?>
                                        </span>
                                    </div>
                                    <span style="font-size:0.65rem;color:var(--gray-400);white-space:nowrap;">
                                        <i class="fas fa-clock"></i> <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                    </span>
                                </div>
                                <div style="font-size:0.8rem;color:var(--gray-600);margin-top:2px;">
                                    <?php echo htmlspecialchars($activity['description']); ?>
                                </div>
                                <?php if (!empty($activity['entity_type']) && !empty($activity['entity_id'])): ?>
                                    <div style="font-size:0.65rem;color:var(--gray-400);margin-top:2px;">
                                        <i class="fas fa-tag"></i> 
                                        <?php echo ucfirst($activity['entity_type']); ?> ID: <?php echo $activity['entity_id']; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($activity['ip_address'])): ?>
                                    <div style="font-size:0.6rem;color:var(--gray-400);margin-top:1px;">
                                        <i class="fas fa-network-wired"></i> <?php echo htmlspecialchars($activity['ip_address']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="padding:40px;text-align:center;color:var(--gray-500);">
                    <i class="fas fa-search" style="font-size:2rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                    <p>No activities found matching your criteria</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;padding:12px 0;">
                <div style="font-size:0.8rem;color:var(--gray-500);">
                    Showing <?php echo (($page - 1) * $limit) + 1; ?> - <?php echo min($page * $limit, $total_activities); ?> of <?php echo number_format($total_activities); ?> activities
                </div>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&lga=<?php echo $lga_id; ?>&state=<?php echo $state_id; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&user=<?php echo $user_filter; ?>" 
                           class="btn-page" style="padding:6px 14px;border:1px solid var(--gray-200);border-radius:8px;text-decoration:none;color:var(--gray-600);font-size:0.8rem;transition:var(--transition);">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    if ($start_page > 1) {
                        echo '<a href="?page=1&lga=' . $lga_id . '&state=' . $state_id . '&search=' . urlencode($search) . '&type=' . urlencode($type_filter) . '&user=' . $user_filter . '" class="btn-page" style="padding:6px 12px;border:1px solid var(--gray-200);border-radius:8px;text-decoration:none;color:var(--gray-600);font-size:0.8rem;transition:var(--transition);">1</a>';
                        if ($start_page > 2) echo '<span style="padding:6px 8px;color:var(--gray-400);">...</span>';
                    }
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="?page=<?php echo $i; ?>&lga=<?php echo $lga_id; ?>&state=<?php echo $state_id; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&user=<?php echo $user_filter; ?>" 
                           class="btn-page <?php echo $i == $page ? 'active' : ''; ?>" 
                           style="padding:6px 12px;border:1px solid <?php echo $i == $page ? 'var(--primary)' : 'var(--gray-200)'; ?>;border-radius:8px;text-decoration:none;color:<?php echo $i == $page ? 'white' : 'var(--gray-600)'; ?>;font-size:0.8rem;transition:var(--transition);background:<?php echo $i == $page ? 'var(--primary)' : 'transparent'; ?>;">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; 
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo '<span style="padding:6px 8px;color:var(--gray-400);">...</span>';
                        echo '<a href="?page=' . $total_pages . '&lga=' . $lga_id . '&state=' . $state_id . '&search=' . urlencode($search) . '&type=' . urlencode($type_filter) . '&user=' . $user_filter . '" class="btn-page" style="padding:6px 12px;border:1px solid var(--gray-200);border-radius:8px;text-decoration:none;color:var(--gray-600);font-size:0.8rem;transition:var(--transition);">' . $total_pages . '</a>';
                    }
                    ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&lga=<?php echo $lga_id; ?>&state=<?php echo $state_id; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&user=<?php echo $user_filter; ?>" 
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
.form-select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1); }
.quick-action-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-hover); border-color: var(--primary); }

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
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