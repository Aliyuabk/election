<?php
// ============================================================
// STATE COORDINATOR - LGA RESULTS
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
$lga_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

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
    error_log("LGA Results Error: " . $e->getMessage());
    header('Location: monitor-lgas.php?error=database_error');
    exit();
}

// ============================================================
// GET FILTER PARAMETERS
// ============================================================
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$ward_filter = isset($_GET['ward']) ? intval($_GET['ward']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// ============================================================
// FETCH WARDS FOR FILTER
// ============================================================
$wards = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM wards WHERE lga_id = ? AND is_active = 1 ORDER BY name");
    $stmt->execute([$lga_id]);
    $wards = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $wards = [];
}

// ============================================================
// FETCH RESULTS
// ============================================================
$results = [];
$total_results = 0;

try {
    $where_clauses = ['r.tenant_id = ?', 'r.lga_id = ?'];
    $params = [$tenant_id, $lga_id];
    
    if (!empty($status_filter)) {
        $where_clauses[] = 'r.status = ?';
        $params[] = $status_filter;
    }
    
    if ($ward_filter > 0) {
        $where_clauses[] = 'r.ward_id = ?';
        $params[] = $ward_filter;
    }
    
    if (!empty($search)) {
        $where_clauses[] = '(pu.name LIKE ? OR pu.code LIKE ? OR u.full_name LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_sql = implode(' AND ', $where_clauses);
    
    // Count total
    $count_stmt = $db->prepare("SELECT COUNT(*) FROM results_ec8a r WHERE $where_sql");
    $count_stmt->execute($params);
    $total_results = $count_stmt->fetchColumn();
    
    // Fetch results
    $stmt = $db->prepare("
        SELECT 
            r.*,
            u.full_name as agent_name,
            u.phone as agent_phone,
            vu.full_name as verified_by_name,
            w.name as ward_name,
            pu.name as pu_name,
            pu.code as pu_code
        FROM results_ec8a r
        LEFT JOIN users u ON r.agent_id = u.id
        LEFT JOIN users vu ON r.verified_by = vu.id
        LEFT JOIN wards w ON r.ward_id = w.id
        LEFT JOIN polling_units pu ON r.pu_id = pu.id
        WHERE $where_sql
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $query_params = array_merge($params, [$limit, $offset]);
    $stmt->execute($query_params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("LGA Results Fetch Error: " . $e->getMessage());
}

$total_pages = ceil($total_results / $limit);

// ============================================================
// FETCH STATISTICS
// ============================================================
$stats = [
    'total' => 0,
    'verified' => 0,
    'pending' => 0,
    'flagged' => 0,
    'rejected' => 0,
    'total_votes' => 0,
    'valid_votes' => 0
];

foreach ($results as $result) {
    $stats['total']++;
    $status = $result['status'] ?? '';
    if (isset($stats[$status])) {
        $stats[$status]++;
    }
    $stats['total_votes'] += $result['total_votes_cast'] ?? 0;
    $stats['valid_votes'] += $result['valid_votes'] ?? 0;
}

// ============================================================
// STATUS COLORS AND LABELS
// ============================================================
$status_colors = [
    'pending' => '#F59E0B',
    'verified' => '#10B981',
    'rejected' => '#EF4444',
    'flagged' => '#8B5CF6',
    'approved' => '#10B981'
];

$status_labels = [
    'pending' => 'Pending',
    'verified' => 'Verified',
    'rejected' => 'Rejected',
    'flagged' => 'Flagged',
    'approved' => 'Approved'
];

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'LGA Results';
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
                <span style="font-weight:600;color:var(--gray-800);">Results</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <i class="fas fa-file-alt" style="color:var(--primary);"></i>
                        <?php echo htmlspecialchars($lga_name); ?> Results
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-flag"></i> <?php echo htmlspecialchars($state_name); ?> • 
                        <?php echo number_format($stats['total']); ?> results • 
                        <?php echo number_format($stats['verified']); ?> verified
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="lga-dashboard.php?id=<?php echo $lga_id; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    <a href="reports.php?type=lga&id=<?php echo $lga_id; ?>" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-file-pdf"></i> Export
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-file-alt"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
                <div class="stat-label">Total Results</div>
                <div class="stat-change">All records</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['verified']); ?></div>
                <div class="stat-label">Verified</div>
                <div class="stat-change up"><i class="fas fa-check"></i> Approved</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo number_format($stats['pending']); ?></div>
                <div class="stat-label">Pending</div>
                <div class="stat-change down"><i class="fas fa-hourglass-half"></i> Awaiting</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-flag"></i></div>
                <div class="stat-number"><?php echo number_format($stats['flagged']); ?></div>
                <div class="stat-label">Flagged</div>
                <div class="stat-change down"><i class="fas fa-exclamation-triangle"></i> Review</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon teal"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_votes']); ?></div>
                <div class="stat-label">Total Votes Cast</div>
                <div class="stat-change">All votes</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pink"><i class="fas fa-check-double"></i></div>
                <div class="stat-number"><?php echo number_format($stats['valid_votes']); ?></div>
                <div class="stat-label">Valid Votes</div>
                <div class="stat-change up"><i class="fas fa-check"></i> Accepted</div>
            </div>
        </div>

        <!-- Filters -->
        <div style="background:white;border-radius:var(--radius);padding:12px 16px;margin-bottom:16px;border:1px solid var(--gray-200);">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
                <input type="hidden" name="id" value="<?php echo $lga_id; ?>">
                
                <div style="flex:1;min-width:150px;">
                    <div class="search-box" style="width:100%;">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search PU, agent..." value="<?php echo htmlspecialchars($search); ?>" />
                    </div>
                </div>
                
                <div style="min-width:130px;">
                    <select name="ward" class="form-select" style="width:100%;padding:6px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.75rem;background:white;">
                        <option value="">All Wards</option>
                        <?php foreach ($wards as $ward): ?>
                            <option value="<?php echo $ward['id']; ?>" <?php echo $ward_filter == $ward['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ward['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="min-width:120px;">
                    <select name="status" class="form-select" style="width:100%;padding:6px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.75rem;background:white;">
                        <option value="">All Status</option>
                        <?php foreach ($status_labels as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $status_filter == $key ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn-primary" style="padding:6px 16px;background:var(--primary);color:white;border:none;border-radius:8px;font-family:'Inter',sans-serif;font-weight:600;font-size:0.75rem;cursor:pointer;transition:var(--transition);">
                    <i class="fas fa-filter"></i> Filter
                </button>
                
                <?php if (!empty($search) || !empty($status_filter) || $ward_filter > 0): ?>
                    <a href="lga-results.php?id=<?php echo $lga_id; ?>" class="btn-reset" style="padding:6px 12px;background:var(--gray-100);color:var(--gray-600);border:none;border-radius:8px;font-family:'Inter',sans-serif;font-weight:500;font-size:0.75rem;cursor:pointer;text-decoration:none;transition:var(--transition);">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Results Table -->
        <div style="background:white;border-radius:var(--radius);overflow:hidden;border:1px solid var(--gray-200);">
            <div style="padding:10px 16px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <h4 style="font-size:0.85rem;font-weight:600;margin:0;">
                    <i class="fas fa-list" style="color:var(--primary);margin-right:6px;"></i>
                    Result Records
                    <span style="font-size:0.65rem;font-weight:400;color:var(--gray-400);margin-left:8px;">
                        (<?php echo number_format($total_results); ?> results)
                    </span>
                </h4>
            </div>
            
            <?php if (count($results) > 0): ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.75rem;">
                        <thead style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
                            <tr>
                                <th style="padding:8px 12px;text-align:left;font-weight:600;color:var(--gray-600);">Polling Unit</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);">Agent</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);">Votes</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);">Status</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);">Verified By</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);">Submitted</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $result): 
                                $status_color = $status_colors[$result['status']] ?? '#6B7280';
                                $status_label = $status_labels[$result['status']] ?? ucfirst($result['status']);
                            ?>
                                <tr style="border-bottom:1px solid var(--gray-100);transition:var(--transition);hover:background:var(--gray-50);">
                                    <td style="padding:8px 12px;">
                                        <div style="font-weight:500;font-size:0.8rem;"><?php echo htmlspecialchars($result['pu_name'] ?? 'Unknown PU'); ?></div>
                                        <div style="font-size:0.6rem;color:var(--gray-400);">
                                            <?php echo htmlspecialchars($result['pu_code'] ?? ''); ?>
                                            <?php if (!empty($result['ward_name'])): ?>
                                                • <?php echo htmlspecialchars($result['ward_name']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="padding:8px 12px;text-align:center;font-size:0.7rem;">
                                        <?php echo htmlspecialchars($result['agent_name'] ?? 'Unknown'); ?>
                                    </td>
                                    <td style="padding:8px 12px;text-align:center;">
                                        <div style="font-weight:600;font-size:0.85rem;"><?php echo number_format($result['total_votes_cast']); ?></div>
                                        <div style="font-size:0.55rem;color:var(--gray-400);">
                                            Valid: <?php echo number_format($result['valid_votes']); ?>
                                        </div>
                                    </td>
                                    <td style="padding:8px 12px;text-align:center;">
                                        <span style="display:inline-block;padding:2px 10px;border-radius:10px;font-size:0.6rem;font-weight:600;background:<?php echo $status_color; ?>20;color:<?php echo $status_color; ?>;">
                                            <?php echo $status_label; ?>
                                        </span>
                                    </td>
                                    <td style="padding:8px 12px;text-align:center;font-size:0.7rem;">
                                        <?php if ($result['verified_by']): ?>
                                            <div><?php echo htmlspecialchars($result['verified_by_name'] ?? 'Unknown'); ?></div>
                                            <div style="font-size:0.55rem;color:var(--gray-400);">
                                                <?php echo date('M j, Y', strtotime($result['verified_at'])); ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:8px 12px;text-align:center;font-size:0.65rem;color:var(--gray-500);">
                                        <?php echo date('M j, Y', strtotime($result['created_at'])); ?>
                                        <div style="font-size:0.55rem;color:var(--gray-400);">
                                            <?php echo date('g:i A', strtotime($result['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td style="padding:8px 12px;text-align:center;">
                                        <div style="display:flex;gap:3px;justify-content:center;flex-wrap:wrap;">
                                            <a href="result-view.php?id=<?php echo $result['id']; ?>" class="btn-sm" style="padding:2px 8px;border-radius:4px;background:var(--primary);color:white;text-decoration:none;font-size:0.6rem;" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($result['status'] === 'pending'): ?>
                                                <a href="result-verify.php?id=<?php echo $result['id']; ?>" class="btn-sm" style="padding:2px 8px;border-radius:4px;background:#10B981;color:white;text-decoration:none;font-size:0.6rem;" title="Verify">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($result['status'] === 'flagged' || $result['status'] === 'pending'): ?>
                                                <a href="result-flag.php?id=<?php echo $result['id']; ?>" class="btn-sm" style="padding:2px 8px;border-radius:4px;background:#EF4444;color:white;text-decoration:none;font-size:0.6rem;" title="Flag">
                                                    <i class="fas fa-flag"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (!empty($result['photo_url'])): ?>
                                                <a href="<?php echo $result['photo_url']; ?>" target="_blank" class="btn-sm" style="padding:2px 8px;border-radius:4px;background:#8B5CF6;color:white;text-decoration:none;font-size:0.6rem;" title="Photo">
                                                    <i class="fas fa-image"></i>
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
                    <i class="fas fa-file-alt" style="font-size:2rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                    <p style="font-size:0.85rem;">No results found for this LGA.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;padding:10px 0;">
                <div style="font-size:0.7rem;color:var(--gray-500);">
                    Showing <?php echo (($page - 1) * $limit) + 1; ?> - <?php echo min($page * $limit, $total_results); ?> of <?php echo number_format($total_results); ?>
                </div>
                <div style="display:flex;gap:4px;flex-wrap:wrap;">
                    <?php if ($page > 1): ?>
                        <a href="?id=<?php echo $lga_id; ?>&page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&ward=<?php echo $ward_filter; ?>" 
                           class="btn-page" style="padding:4px 10px;border:1px solid var(--gray-200);border-radius:6px;text-decoration:none;color:var(--gray-600);font-size:0.7rem;transition:var(--transition);">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    if ($start_page > 1) {
                        echo '<a href="?id=' . $lga_id . '&page=1&search=' . urlencode($search) . '&status=' . urlencode($status_filter) . '&ward=' . $ward_filter . '" class="btn-page" style="padding:4px 10px;border:1px solid var(--gray-200);border-radius:6px;text-decoration:none;color:var(--gray-600);font-size:0.7rem;transition:var(--transition);">1</a>';
                        if ($start_page > 2) echo '<span style="padding:4px 6px;color:var(--gray-400);font-size:0.7rem;">...</span>';
                    }
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="?id=<?php echo $lga_id; ?>&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&ward=<?php echo $ward_filter; ?>" 
                           class="btn-page <?php echo $i == $page ? 'active' : ''; ?>" 
                           style="padding:4px 10px;border:1px solid <?php echo $i == $page ? 'var(--primary)' : 'var(--gray-200)'; ?>;border-radius:6px;text-decoration:none;color:<?php echo $i == $page ? 'white' : 'var(--gray-600)'; ?>;font-size:0.7rem;transition:var(--transition);background:<?php echo $i == $page ? 'var(--primary)' : 'transparent'; ?>;">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; 
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo '<span style="padding:4px 6px;color:var(--gray-400);font-size:0.7rem;">...</span>';
                        echo '<a href="?id=' . $lga_id . '&page=' . $total_pages . '&search=' . urlencode($search) . '&status=' . urlencode($status_filter) . '&ward=' . $ward_filter . '" class="btn-page" style="padding:4px 10px;border:1px solid var(--gray-200);border-radius:6px;text-decoration:none;color:var(--gray-600);font-size:0.7rem;transition:var(--transition);">' . $total_pages . '</a>';
                    }
                    ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?id=<?php echo $lga_id; ?>&page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&ward=<?php echo $ward_filter; ?>" 
                           class="btn-page" style="padding:4px 10px;border:1px solid var(--gray-200);border-radius:6px;text-decoration:none;color:var(--gray-600);font-size:0.7rem;transition:var(--transition);">
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

.stat-icon.teal { background: #CCFBF1; color: #0D9488; }
.stat-icon.pink { background: #FCE7F3; color: #DB2777; }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
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