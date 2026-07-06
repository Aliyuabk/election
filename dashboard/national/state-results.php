<?php
// ============================================================
// NATIONAL COORDINATOR - VIEW STATE RESULTS
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
$state_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($state_id <= 0) {
    header('Location: monitor-states.php');
    exit();
}

$db = getDB();

// ============================================================
// FETCH STATE NAME
// ============================================================
$state_name = '';
try {
    $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
    $stmt->execute([$state_id]);
    $state_name = $stmt->fetchColumn() ?: 'State';
} catch (Exception $e) {
    $state_name = 'State';
}

// ============================================================
// FETCH FILTER PARAMETERS
// ============================================================
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$lga_id = isset($_GET['lga']) ? intval($_GET['lga']) : 0;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// ============================================================
// FETCH LGAs FOR FILTER
// ============================================================
$lgas = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM lgas WHERE state_id = ? AND is_active = 1 ORDER BY name ASC");
    $stmt->execute([$state_id]);
    $lgas = $stmt->fetchAll();
} catch (Exception $e) {
    $lgas = [];
}

// ============================================================
// BUILD QUERY FOR RESULTS
// ============================================================
$where_clauses = ['r.tenant_id = ?', 'l.state_id = ?'];
$params = [$tenant_id, $state_id];

if (!empty($status)) {
    $where_clauses[] = 'r.status = ?';
    $params[] = $status;
}

if (!empty($search)) {
    $where_clauses[] = '(pu.name LIKE ? OR pu.code LIKE ? OR l.name LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($lga_id > 0) {
    $where_clauses[] = 'l.id = ?';
    $params[] = $lga_id;
}

$where_sql = implode(' AND ', $where_clauses);

// ============================================================
// FETCH RESULTS
// ============================================================
$results = [];
$total_results = 0;

try {
    // Count total
    $count_stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        WHERE $where_sql
    ");
    $count_stmt->execute($params);
    $total_results = $count_stmt->fetchColumn();

    // Fetch results
    $stmt = $db->prepare("
        SELECT 
            r.*,
            pu.name as pu_name,
            pu.code as pu_code,
            w.name as ward_name,
            l.name as lga_name,
            u.full_name as agent_name,
            vu.full_name as verified_by_name
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        LEFT JOIN users u ON r.agent_id = u.id
        LEFT JOIN users vu ON r.verified_by = vu.id
        WHERE $where_sql
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $query_params = array_merge($params, [$limit, $offset]);
    $stmt->execute($query_params);
    $results = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("State Results Error: " . $e->getMessage());
}

// ============================================================
// FETCH SUMMARY STATISTICS
// ============================================================
$summary = [
    'total' => 0,
    'verified' => 0,
    'pending' => 0,
    'flagged' => 0,
    'rejected' => 0
];

try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'flagged' THEN 1 ELSE 0 END) as flagged,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        WHERE r.tenant_id = ? AND l.state_id = ?
    ");
    $stmt->execute([$tenant_id, $state_id]);
    $summary = $stmt->fetch();
} catch (Exception $e) {
    $summary = ['total' => 0, 'verified' => 0, 'pending' => 0, 'flagged' => 0, 'rejected' => 0];
}

$total_pages = ceil($total_results / $limit);

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'State Results';
$page_subtitle = $state_name;
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
                <a href="monitor-states.php" style="text-decoration:none;color:var(--gray-500);">Monitor States</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <a href="view-state.php?id=<?php echo $state_id; ?>" style="text-decoration:none;color:var(--gray-500);"><?php echo htmlspecialchars($state_name); ?></a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);">Results</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <?php echo htmlspecialchars($state_name); ?> Results
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-file-alt"></i> 
                        <?php echo number_format($summary['total']); ?> total results • 
                        <?php echo number_format($summary['verified'] ?? 0); ?> verified
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="view-state.php?id=<?php echo $state_id; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    <a href="reports.php?type=state_results&id=<?php echo $state_id; ?>" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-file-pdf"></i> Export
                    </a>
                </div>
            </div>
        </div>

        <!-- Summary Stats -->
        <!-- Summary Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php echo number_format($summary['verified'] ?? 0); ?></div>
                <div class="stat-label">Verified</div>
                <div class="stat-change up"><i class="fas fa-check"></i> Approved</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo number_format($summary['pending'] ?? 0); ?></div>
                <div class="stat-label">Pending</div>
                <div class="stat-change down"><i class="fas fa-hourglass-half"></i> Awaiting review</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-flag"></i></div>
                <div class="stat-number"><?php echo number_format($summary['flagged'] ?? 0); ?></div>
                <div class="stat-label">Flagged</div>
                <div class="stat-change down"><i class="fas fa-exclamation-triangle"></i> Needs attention</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-times-circle"></i></div>
                <div class="stat-number"><?php echo number_format($summary['rejected'] ?? 0); ?></div>
                <div class="stat-label">Rejected</div>
                <div class="stat-change down"><i class="fas fa-times"></i> Returned</div>
            </div>
        </div>

        <!-- Filters -->
        <div style="background:white;border-radius:var(--radius);padding:16px 20px;margin-bottom:20px;border:1px solid var(--gray-200);">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
                <input type="hidden" name="id" value="<?php echo $state_id; ?>">
                
                <div style="flex:1;min-width:150px;">
                    <div class="search-box" style="width:100%;">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search PU, code, LGA..." value="<?php echo htmlspecialchars($search); ?>" />
                    </div>
                </div>
                
                <div style="min-width:140px;">
                    <select name="lga" class="form-select" style="width:100%;padding:8px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.8rem;background:white;">
                        <option value="">All LGAs</option>
                        <?php foreach ($lgas as $lga): ?>
                            <option value="<?php echo $lga['id']; ?>" <?php echo $lga_id == $lga['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lga['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="min-width:120px;">
                    <select name="status" class="form-select" style="width:100%;padding:8px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.8rem;background:white;">
                        <option value="">All Status</option>
                        <option value="verified" <?php echo $status == 'verified' ? 'selected' : ''; ?>>Verified</option>
                        <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="flagged" <?php echo $status == 'flagged' ? 'selected' : ''; ?>>Flagged</option>
                        <option value="rejected" <?php echo $status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-primary" style="padding:8px 24px;background:var(--primary);color:white;border:none;border-radius:10px;font-family:'Inter',sans-serif;font-weight:600;font-size:0.8rem;cursor:pointer;transition:var(--transition);">
                    <i class="fas fa-filter"></i> Filter
                </button>
                
                <?php if (!empty($search) || !empty($status) || $lga_id > 0): ?>
                    <a href="state-results.php?id=<?php echo $state_id; ?>" class="btn-reset" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-600);border:none;border-radius:10px;font-family:'Inter',sans-serif;font-weight:500;font-size:0.8rem;cursor:pointer;text-decoration:none;transition:var(--transition);">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Results Table -->
        <div style="background:white;border-radius:var(--radius);overflow:hidden;border:1px solid var(--gray-200);margin-bottom:20px;">
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.8rem;">
                    <thead style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
                        <tr>
                            <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--gray-600);">PU / LGA</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Agent</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Total Votes</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Status</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Verified By</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Submitted</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($results) > 0): ?>
                            <?php foreach ($results as $result): 
                                $status_colors = [
                                    'verified' => '#10B981',
                                    'pending' => '#F59E0B',
                                    'flagged' => '#EF4444',
                                    'rejected' => '#6B7280'
                                ];
                                $status_color = $status_colors[$result['status']] ?? '#6B7280';
                            ?>
                                <tr style="border-bottom:1px solid var(--gray-100);transition:var(--transition);hover:background:var(--gray-50);">
                                    <td style="padding:10px 14px;">
                                        <div style="font-weight:500;"><?php echo htmlspecialchars($result['pu_name']); ?></div>
                                        <div style="font-size:0.7rem;color:var(--gray-500);">
                                            <?php echo htmlspecialchars($result['pu_code']); ?> • 
                                            <?php echo htmlspecialchars($result['ward_name']); ?> • 
                                            <?php echo htmlspecialchars($result['lga_name']); ?>
                                        </div>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;font-size:0.75rem;">
                                        <?php echo htmlspecialchars($result['agent_name'] ?? 'N/A'); ?>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;font-weight:600;">
                                        <?php echo number_format($result['total_votes_cast']); ?>
                                        <div style="font-size:0.6rem;color:var(--gray-400);">
                                            <?php echo number_format($result['valid_votes']); ?> valid
                                        </div>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <span style="display:inline-block;padding:2px 12px;border-radius:12px;font-size:0.7rem;font-weight:600;background:<?php echo $status_color; ?>20;color:<?php echo $status_color; ?>;">
                                            <?php echo ucfirst($result['status']); ?>
                                        </span>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;font-size:0.75rem;">
                                        <?php echo $result['verified_by'] ? htmlspecialchars($result['verified_by_name']) : '—'; ?>
                                        <?php if ($result['verified_at']): ?>
                                            <div style="font-size:0.6rem;color:var(--gray-400);">
                                                <?php echo date('M j, Y', strtotime($result['verified_at'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;font-size:0.7rem;color:var(--gray-500);">
                                        <?php echo date('M j, Y', strtotime($result['created_at'])); ?>
                                        <div style="font-size:0.6rem;color:var(--gray-400);">
                                            <?php echo date('g:i A', strtotime($result['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <div style="display:flex;gap:4px;justify-content:center;flex-wrap:wrap;">
                                            <a href="result-view.php?id=<?php echo $result['id']; ?>" class="btn-sm" style="padding:3px 10px;border-radius:6px;background:var(--primary);color:white;text-decoration:none;font-size:0.65rem;">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="result-verify.php?id=<?php echo $result['id']; ?>" class="btn-sm" style="padding:3px 10px;border-radius:6px;background:var(--secondary);color:white;text-decoration:none;font-size:0.65rem;">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <a href="result-flag.php?id=<?php echo $result['id']; ?>" class="btn-sm" style="padding:3px 10px;border-radius:6px;background:#EF4444;color:white;text-decoration:none;font-size:0.65rem;">
                                                <i class="fas fa-flag"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="padding:40px;text-align:center;color:var(--gray-500);">
                                    <i class="fas fa-file-alt" style="font-size:2rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                                    No results found for this state
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
                    Showing <?php echo (($page - 1) * $limit) + 1; ?> - <?php echo min($page * $limit, $total_results); ?> of <?php echo number_format($total_results); ?> results
                </div>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    <?php if ($page > 1): ?>
                        <a href="?id=<?php echo $state_id; ?>&page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&lga=<?php echo $lga_id; ?>&status=<?php echo urlencode($status); ?>" 
                           class="btn-page" style="padding:6px 14px;border:1px solid var(--gray-200);border-radius:8px;text-decoration:none;color:var(--gray-600);font-size:0.8rem;transition:var(--transition);">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    if ($start_page > 1) {
                        echo '<a href="?id=' . $state_id . '&page=1&search=' . urlencode($search) . '&lga=' . $lga_id . '&status=' . urlencode($status) . '" class="btn-page" style="padding:6px 12px;border:1px solid var(--gray-200);border-radius:8px;text-decoration:none;color:var(--gray-600);font-size:0.8rem;transition:var(--transition);">1</a>';
                        if ($start_page > 2) echo '<span style="padding:6px 8px;color:var(--gray-400);">...</span>';
                    }
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="?id=<?php echo $state_id; ?>&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&lga=<?php echo $lga_id; ?>&status=<?php echo urlencode($status); ?>" 
                           class="btn-page <?php echo $i == $page ? 'active' : ''; ?>" 
                           style="padding:6px 12px;border:1px solid <?php echo $i == $page ? 'var(--primary)' : 'var(--gray-200)'; ?>;border-radius:8px;text-decoration:none;color:<?php echo $i == $page ? 'white' : 'var(--gray-600)'; ?>;font-size:0.8rem;transition:var(--transition);background:<?php echo $i == $page ? 'var(--primary)' : 'transparent'; ?>;">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; 
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo '<span style="padding:6px 8px;color:var(--gray-400);">...</span>';
                        echo '<a href="?id=' . $state_id . '&page=' . $total_pages . '&search=' . urlencode($search) . '&lga=' . $lga_id . '&status=' . urlencode($status) . '" class="btn-page" style="padding:6px 12px;border:1px solid var(--gray-200);border-radius:8px;text-decoration:none;color:var(--gray-600);font-size:0.8rem;transition:var(--transition);">' . $total_pages . '</a>';
                    }
                    ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?id=<?php echo $state_id; ?>&page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&lga=<?php echo $lga_id; ?>&status=<?php echo urlencode($status); ?>" 
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
/* Same styles as previous pages */
.btn-sm:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.btn-page:hover { background: var(--gray-50); border-color: var(--gray-300); }
.btn-page.active { background: var(--primary); color: white; border-color: var(--primary); }
.btn-page.active:hover { background: var(--primary-dark); }
.form-select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1); }

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