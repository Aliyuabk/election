<?php
// ============================================================
// NATIONAL COORDINATOR - MANAGE ELECTIONS (FIXED)
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
// GET FILTER PARAMETERS
// ============================================================
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$type_filter = isset($_GET['type']) ? trim($_GET['type']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// ============================================================
// BUILD QUERY - National Coordinator sees ALL tenant elections
// ============================================================
$where_clauses = ['e.deleted_at IS NULL'];
$params = [];

// If tenant_id is set, filter by it
if (!empty($tenant_id)) {
    $where_clauses[] = 'e.tenant_id = ?';
    $params[] = $tenant_id;
}
// If no tenant_id (super admin or national view), show all

if (!empty($status_filter)) {
    $where_clauses[] = 'e.status = ?';
    $params[] = $status_filter;
}

if (!empty($type_filter)) {
    $where_clauses[] = 'e.type = ?';
    $params[] = $type_filter;
}

if (!empty($search)) {
    $where_clauses[] = '(e.name LIKE ? OR e.description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = implode(' AND ', $where_clauses);

// ============================================================
// FETCH ELECTIONS
// ============================================================
$elections = [];
$total_elections = 0;

try {
    // Count total
    $count_stmt = $db->prepare("SELECT COUNT(*) FROM elections e WHERE $where_sql");
    $count_stmt->execute($params);
    $total_elections = $count_stmt->fetchColumn();

    // Fetch elections
    $stmt = $db->prepare("
        SELECT 
            e.*,
            u.full_name as created_by_name,
            t.name as tenant_name
        FROM elections e
        LEFT JOIN users u ON e.created_by = u.id
        LEFT JOIN tenants t ON e.tenant_id = t.id
        WHERE $where_sql
        ORDER BY e.election_date DESC, e.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $query_params = array_merge($params, [$limit, $offset]);
    $stmt->execute($query_params);
    $elections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Elections PDO Error: " . $e->getMessage());
} catch (Exception $e) {
    error_log("Elections Error: " . $e->getMessage());
}

$total_pages = ceil($total_elections / $limit);

// ============================================================
// FETCH ALL ELECTIONS FOR STATS (without pagination)
// ============================================================
$all_elections = [];
try {
    $stats_stmt = $db->prepare("SELECT e.status FROM elections e WHERE $where_sql");
    $stats_stmt->execute($params);
    $all_elections = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Stats Error: " . $e->getMessage());
}

// ============================================================
// ELECTION TYPES AND STATUSES
// ============================================================
$election_types = [
    'presidential' => 'Presidential',
    'governorship' => 'Governorship',
    'senatorial' => 'Senatorial',
    'house_of_reps' => 'House of Reps',
    'house_of_assembly' => 'House of Assembly',
    'lga_chairman' => 'LGA Chairman',
    'councillorship' => 'Councillorship',
    'party_primary' => 'Party Primary',
    'internal_party' => 'Internal Party'
];

$status_labels = [
    'draft' => 'Draft',
    'upcoming' => 'Upcoming',
    'active' => 'Active',
    'closed' => 'Closed',
    'cancelled' => 'Cancelled',
    'archived' => 'Archived'
];

$status_colors = [
    'draft' => '#6B7280',
    'upcoming' => '#3B82F6',
    'active' => '#10B981',
    'closed' => '#6B7280',
    'cancelled' => '#EF4444',
    'archived' => '#6B7280'
];

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Elections';
$page_subtitle = 'Manage all elections';
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
                <span style="font-weight:600;color:var(--gray-800);">Elections</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <i class="fas fa-vote-yea" style="color:var(--primary);"></i>
                        Elections
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-calendar-alt"></i> 
                        <?php echo number_format($total_elections); ?> elections found
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="elections-create.php" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-plus"></i> Create Election
                    </a>
                    <a href="elections-templates.php" class="btn-secondary" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-copy"></i> Templates
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-vote-yea"></i></div>
                <div class="stat-number"><?php echo number_format($total_elections); ?></div>
                <div class="stat-label">Total Elections</div>
                <div class="stat-change">All records</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-play-circle"></i></div>
                <div class="stat-number">
                    <?php 
                    $active = 0;
                    foreach ($all_elections as $e) {
                        if ($e['status'] === 'active') $active++;
                    }
                    echo number_format($active);
                    ?>
                </div>
                <div class="stat-label">Active</div>
                <div class="stat-change up"><i class="fas fa-play"></i> Ongoing</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
                <div class="stat-number">
                    <?php 
                    $upcoming = 0;
                    foreach ($all_elections as $e) {
                        if ($e['status'] === 'upcoming') $upcoming++;
                    }
                    echo number_format($upcoming);
                    ?>
                </div>
                <div class="stat-label">Upcoming</div>
                <div class="stat-change"><i class="fas fa-calendar"></i> Scheduled</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-check-double"></i></div>
                <div class="stat-number">
                    <?php 
                    $closed = 0;
                    foreach ($all_elections as $e) {
                        if ($e['status'] === 'closed') $closed++;
                    }
                    echo number_format($closed);
                    ?>
                </div>
                <div class="stat-label">Completed</div>
                <div class="stat-change up"><i class="fas fa-check"></i> Done</div>
            </div>
        </div>

        <!-- Debug Info (remove after fixing) -->
        <?php if (count($elections) == 0 && $total_elections > 0): ?>
            <div style="background:#FEF2F2;padding:12px 16px;border-radius:8px;margin-bottom:16px;border:1px solid #FECACA;color:#991B1B;font-size:0.85rem;">
                <i class="fas fa-exclamation-triangle"></i> 
                <strong>Debug:</strong> Found <?php echo $total_elections; ?> elections but none are showing in the table.
                Current tenant_id: <?php echo var_dump($tenant_id); ?>
                <br>Query: <?php echo htmlspecialchars("SELECT COUNT(*) FROM elections e WHERE $where_sql"); ?>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div style="background:white;border-radius:var(--radius);padding:16px 20px;margin-bottom:20px;border:1px solid var(--gray-200);">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
                <div style="flex:1;min-width:150px;">
                    <div class="search-box" style="width:100%;">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search elections..." value="<?php echo htmlspecialchars($search); ?>" />
                    </div>
                </div>
                
                <div style="min-width:140px;">
                    <select name="status" class="form-select" style="width:100%;padding:8px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.8rem;background:white;">
                        <option value="">All Status</option>
                        <?php foreach ($status_labels as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $status_filter == $key ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="min-width:140px;">
                    <select name="type" class="form-select" style="width:100%;padding:8px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.8rem;background:white;">
                        <option value="">All Types</option>
                        <?php foreach ($election_types as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $type_filter == $key ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn-primary" style="padding:8px 24px;background:var(--primary);color:white;border:none;border-radius:10px;font-family:'Inter',sans-serif;font-weight:600;font-size:0.8rem;cursor:pointer;transition:var(--transition);">
                    <i class="fas fa-filter"></i> Filter
                </button>
                
                <?php if (!empty($search) || !empty($status_filter) || !empty($type_filter)): ?>
                    <a href="elections.php" class="btn-reset" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-600);border:none;border-radius:10px;font-family:'Inter',sans-serif;font-weight:500;font-size:0.8rem;cursor:pointer;text-decoration:none;transition:var(--transition);">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Elections Table -->
        <div style="background:white;border-radius:var(--radius);overflow:hidden;border:1px solid var(--gray-200);">
            <div style="padding:12px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <h4 style="font-size:0.9rem;font-weight:600;margin:0;">
                    <i class="fas fa-list" style="color:var(--primary);margin-right:6px;"></i>
                    Election List
                </h4>
                <span style="font-size:0.75rem;color:var(--gray-500);">Showing <?php echo count($elections); ?> of <?php echo number_format($total_elections); ?></span>
            </div>
            
            <?php if (count($elections) > 0): ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.8rem;">
                        <thead style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
                            <tr>
                                <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--gray-600);">Election</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Type</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Date</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Status</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Tenant</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Coverage</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Created By</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($elections as $election): 
                                $status_color = $status_colors[$election['status']] ?? '#6B7280';
                                $status_label = $status_labels[$election['status']] ?? ucfirst($election['status']);
                                $type_label = $election_types[$election['type']] ?? ucfirst($election['type']);
                                
                                // Count coverage
                                $coverage = [];
                                if (!empty($election['states_json'])) {
                                    $states = json_decode($election['states_json'], true);
                                    if (is_array($states)) {
                                        $coverage[] = count($states) . ' states';
                                    }
                                }
                                if (!empty($election['lgas_json'])) {
                                    $lgas = json_decode($election['lgas_json'], true);
                                    if (is_array($lgas)) {
                                        $coverage[] = count($lgas) . ' LGAs';
                                    }
                                }
                                if (!empty($election['wards_json'])) {
                                    $wards = json_decode($election['wards_json'], true);
                                    if (is_array($wards)) {
                                        $coverage[] = count($wards) . ' wards';
                                    }
                                }
                                if (!empty($election['pus_json'])) {
                                    $pus = json_decode($election['pus_json'], true);
                                    if (is_array($pus)) {
                                        $coverage[] = count($pus) . ' PUs';
                                    }
                                }
                                $coverage_text = !empty($coverage) ? implode(', ', $coverage) : 'Not set';
                            ?>
                                <tr style="border-bottom:1px solid var(--gray-100);transition:var(--transition);">
                                    <td style="padding:10px 14px;">
                                        <div style="font-weight:500;font-size:0.85rem;"><?php echo htmlspecialchars($election['name']); ?></div>
                                        <div style="font-size:0.65rem;color:var(--gray-400);">
                                            <?php if (!empty($election['cycle'])): ?>
                                                Cycle: <?php echo htmlspecialchars($election['cycle']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;font-size:0.75rem;">
                                        <span style="display:inline-block;padding:2px 10px;border-radius:10px;background:var(--gray-100);color:var(--gray-600);">
                                            <?php echo $type_label; ?>
                                        </span>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;font-size:0.75rem;">
                                        <?php if ($election['election_date']): ?>
                                            <div><?php echo date('M j, Y', strtotime($election['election_date'])); ?></div>
                                            <?php if ($election['start_time'] && $election['end_time']): ?>
                                                <div style="font-size:0.6rem;color:var(--gray-400);">
                                                    <?php echo date('g:i A', strtotime($election['start_time'])); ?> - 
                                                    <?php echo date('g:i A', strtotime($election['end_time'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400);">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <span style="display:inline-block;padding:2px 12px;border-radius:12px;font-size:0.65rem;font-weight:600;background:<?php echo $status_color; ?>20;color:<?php echo $status_color; ?>;">
                                            <?php echo $status_label; ?>
                                        </span>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;font-size:0.7rem;color:var(--gray-500);">
                                        <?php echo htmlspecialchars($election['tenant_name'] ?? 'N/A'); ?>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;font-size:0.7rem;color:var(--gray-500);">
                                        <?php echo $coverage_text; ?>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;font-size:0.7rem;color:var(--gray-500);">
                                        <?php echo htmlspecialchars($election['created_by_name'] ?? 'System'); ?>
                                        <div style="font-size:0.6rem;color:var(--gray-400);">
                                            <?php echo date('M j, Y', strtotime($election['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <div style="display:flex;gap:4px;justify-content:center;flex-wrap:wrap;">
                                            <!-- <a href="election-view.php?id=<?php echo $election['id']; ?>" class="btn-sm" style="padding:3px 10px;border-radius:6px;background:var(--primary);color:white;text-decoration:none;font-size:0.65rem;" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a> -->
                                            <a href="elections-edit.php?id=<?php echo $election['id']; ?>" class="btn-sm" style="padding:3px 10px;border-radius:6px;background:var(--gray-200);color:var(--gray-700);text-decoration:none;font-size:0.65rem;" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($election['status'] === 'draft'): ?>
                                                <a href="election-duplicate.php?id=<?php echo $election['id']; ?>" class="btn-sm" style="padding:3px 10px;border-radius:6px;background:#8B5CF6;color:white;text-decoration:none;font-size:0.65rem;" title="Duplicate">
                                                    <i class="fas fa-copy"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="election-progress.php?id=<?php echo $election['id']; ?>" class="btn-sm" style="padding:3px 10px;border-radius:6px;background:#10B981;color:white;text-decoration:none;font-size:0.65rem;" title="Progress">
                                                <i class="fas fa-chart-line"></i>
                                            </a>
                                            <?php if ($election['status'] === 'active' || $election['status'] === 'upcoming'): ?>
                                                <a href="live-results.php?id=<?php echo $election['id']; ?>" class="btn-sm" style="padding:3px 10px;border-radius:6px;background:#EF4444;color:white;text-decoration:none;font-size:0.65rem;" title="Live">
                                                    <i class="fas fa-broadcast-tower"></i>
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
                <div style="padding:60px 20px;text-align:center;color:var(--gray-500);">
                    <i class="fas fa-vote-yea" style="font-size:3rem;display:block;margin-bottom:12px;color:var(--gray-300);"></i>
                    <h3 style="font-size:1.1rem;font-weight:600;color:var(--gray-600);margin:0 0 8px;">No Elections Found</h3>
                    <p style="font-size:0.85rem;color:var(--gray-400);margin:0;">
                        <?php if (!empty($tenant_id)): ?>
                            No elections found for your tenant. 
                        <?php else: ?>
                            No elections found in the system.
                        <?php endif; ?>
                    </p>
                    <a href="elections-create.php" class="btn-primary" style="padding:10px 28px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.85rem;display:inline-flex;align-items:center;gap:8px;margin-top:16px;">
                        <i class="fas fa-plus"></i> Create Election
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;padding:12px 0;">
                <div style="font-size:0.8rem;color:var(--gray-500);">
                    Showing <?php echo (($page - 1) * $limit) + 1; ?> - <?php echo min($page * $limit, $total_elections); ?> of <?php echo number_format($total_elections); ?> elections
                </div>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>" 
                           class="btn-page" style="padding:6px 14px;border:1px solid var(--gray-200);border-radius:8px;text-decoration:none;color:var(--gray-600);font-size:0.8rem;transition:var(--transition);">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    if ($start_page > 1) {
                        echo '<a href="?page=1&search=' . urlencode($search) . '&status=' . urlencode($status_filter) . '&type=' . urlencode($type_filter) . '" class="btn-page" style="padding:6px 12px;border:1px solid var(--gray-200);border-radius:8px;text-decoration:none;color:var(--gray-600);font-size:0.8rem;transition:var(--transition);">1</a>';
                        if ($start_page > 2) echo '<span style="padding:6px 8px;color:var(--gray-400);">...</span>';
                    }
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>" 
                           class="btn-page <?php echo $i == $page ? 'active' : ''; ?>" 
                           style="padding:6px 12px;border:1px solid <?php echo $i == $page ? 'var(--primary)' : 'var(--gray-200)'; ?>;border-radius:8px;text-decoration:none;color:<?php echo $i == $page ? 'white' : 'var(--gray-600)'; ?>;font-size:0.8rem;transition:var(--transition);background:<?php echo $i == $page ? 'var(--primary)' : 'transparent'; ?>;">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; 
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo '<span style="padding:6px 8px;color:var(--gray-400);">...</span>';
                        echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search) . '&status=' . urlencode($status_filter) . '&type=' . urlencode($type_filter) . '" class="btn-page" style="padding:6px 12px;border:1px solid var(--gray-200);border-radius:8px;text-decoration:none;color:var(--gray-600);font-size:0.8rem;transition:var(--transition);">' . $total_pages . '</a>';
                    }
                    ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>" 
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

/* Search box style */
.search-box {
    display: flex;
    align-items: center;
    background: var(--gray-50);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    padding: 4px 12px;
    transition: var(--transition);
}
.search-box:focus-within {
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.08);
}
.search-box i {
    color: var(--gray-400);
    font-size: 0.85rem;
}
.search-box input {
    border: none;
    outline: none;
    background: transparent;
    padding: 8px 10px;
    font-family: 'Inter', sans-serif;
    font-size: 0.85rem;
    width: 100%;
    color: var(--gray-700);
}

/* Stat cards */
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 16px 20px;
    border: 1px solid var(--gray-200);
    box-shadow: var(--shadow);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}
.stat-card:hover {
    box-shadow: var(--shadow-hover);
    transform: translateY(-2px);
}
.stat-card .stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    color: white;
    margin-bottom: 8px;
}
.stat-card .stat-icon.blue { background: #3B82F6; }
.stat-card .stat-icon.green { background: #10B981; }
.stat-card .stat-icon.yellow { background: #F59E0B; }
.stat-card .stat-icon.purple { background: #8B5CF6; }
.stat-card .stat-number {
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--gray-800);
    line-height: 1.2;
}
.stat-card .stat-label {
    font-size: 0.75rem;
    color: var(--gray-500);
    margin-top: 2px;
    font-weight: 500;
}
.stat-card .stat-change {
    font-size: 0.65rem;
    color: var(--gray-400);
    margin-top: 4px;
}
.stat-card .stat-change.up { color: var(--secondary); }

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    table { font-size: 0.7rem; }
    th, td { padding: 6px 8px !important; }
}
@media (max-width: 480px) {
    .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
    .stat-card { padding: 12px 14px; }
    .stat-card .stat-number { font-size: 1.2rem; }
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