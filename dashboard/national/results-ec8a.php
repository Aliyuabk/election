<?php
// ============================================================
// NATIONAL COORDINATOR - EC8A RESULTS (POLLING UNIT)
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
$state_filter = isset($_GET['state']) ? intval($_GET['state']) : 0;
$lga_filter = isset($_GET['lga']) ? intval($_GET['lga']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// ============================================================
// FETCH STATES FOR FILTER
// ============================================================
$states = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM states WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $states = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $states = [];
}

// ============================================================
// FETCH LGAS FOR FILTER
// ============================================================
$lgas = [];
if ($state_filter > 0) {
    try {
        $stmt = $db->prepare("SELECT id, name FROM lgas WHERE state_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$state_filter]);
        $lgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $lgas = [];
    }
}

// ============================================================
// FETCH EC8A RESULTS
// ============================================================
$results = [];
$total_results = 0;

try {
    $where_clauses = ['r.tenant_id = ?'];
    $params = [$tenant_id];
    
    if (!empty($status_filter)) {
        $where_clauses[] = 'r.status = ?';
        $params[] = $status_filter;
    }
    
    if ($state_filter > 0) {
        $where_clauses[] = 'r.state_id = ?';
        $params[] = $state_filter;
    }
    
    if ($lga_filter > 0) {
        $where_clauses[] = 'r.lga_id = ?';
        $params[] = $lga_filter;
    }
    
    if (!empty($search)) {
        $where_clauses[] = '(r.pu_name LIKE ? OR r.pu_code LIKE ? OR u.full_name LIKE ?)';
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
            s.name as state_name,
            l.name as lga_name,
            w.name as ward_name,
            pu.name as pu_name,
            pu.code as pu_code
        FROM results_ec8a r
        LEFT JOIN users u ON r.agent_id = u.id
        LEFT JOIN users vu ON r.verified_by = vu.id
        LEFT JOIN states s ON r.state_id = s.id
        LEFT JOIN lgas l ON r.lga_id = l.id
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
    error_log("EC8A Results Error: " . $e->getMessage());
}

$total_pages = ceil($total_results / $limit);

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

$page_title = 'EC8A Results';
$page_subtitle = 'Polling Unit Result Forms';
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
                <a href="result-verification.php" style="text-decoration:none;color:var(--gray-500);">Result Verification</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);">EC8A</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <i class="fas fa-flag-checkered" style="color:var(--primary);"></i>
                        EC8A Results
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-file-alt"></i> 
                        Polling Unit Result Forms • <?php echo number_format($total_results); ?> records
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="result-verification.php" class="btn-secondary" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    <a href="reports.php?type=ec8a" class="btn-primary" style="padding:8px 16px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-file-pdf"></i> Export
                    </a>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div style="background:white;border-radius:var(--radius);padding:12px 16px;margin-bottom:16px;border:1px solid var(--gray-200);">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
                <div style="flex:1;min-width:150px;">
                    <div class="search-box" style="width:100%;">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search PU, code, or agent..." value="<?php echo htmlspecialchars($search); ?>" />
                    </div>
                </div>
                
                <div style="min-width:130px;">
                    <select name="state" class="form-select" style="width:100%;padding:6px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.75rem;background:white;" id="stateSelect">
                        <option value="">All States</option>
                        <?php foreach ($states as $state): ?>
                            <option value="<?php echo $state['id']; ?>" <?php echo $state_filter == $state['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($state['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="min-width:130px;">
                    <select name="lga" class="form-select" style="width:100%;padding:6px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.75rem;background:white;" id="lgaSelect">
                        <option value="">All LGAs</option>
                        <?php foreach ($lgas as $lga): ?>
                            <option value="<?php echo $lga['id']; ?>" <?php echo $lga_filter == $lga['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lga['name']); ?>
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
                
                <?php if (!empty($search) || !empty($status_filter) || $state_filter > 0 || $lga_filter > 0): ?>
                    <a href="results-ec8a.php" class="btn-reset" style="padding:6px 12px;background:var(--gray-100);color:var(--gray-600);border:none;border-radius:8px;font-family:'Inter',sans-serif;font-weight:500;font-size:0.75rem;cursor:pointer;text-decoration:none;transition:var(--transition);">
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
                    EC8A Records
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
                                <th style="padding:8px 12px;text-align:left;font-weight:600;color:var(--gray-600);">Polling Unit / Agent</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);">Location</th>
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
                                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($result['agent_name'] ?? 'Unknown'); ?>
                                            <?php if (!empty($result['pu_code'])): ?>
                                                • <?php echo htmlspecialchars($result['pu_code']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="padding:8px 12px;text-align:center;font-size:0.7rem;">
                                        <div><?php echo htmlspecialchars($result['ward_name'] ?? ''); ?></div>
                                        <div style="font-size:0.6rem;color:var(--gray-400);"><?php echo htmlspecialchars($result['lga_name'] ?? ''); ?></div>
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
                    <p style="font-size:0.85rem;">No EC8A results found.</p>
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
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&state=<?php echo $state_filter; ?>&lga=<?php echo $lga_filter; ?>" 
                           class="btn-page" style="padding:4px 10px;border:1px solid var(--gray-200);border-radius:6px;text-decoration:none;color:var(--gray-600);font-size:0.7rem;transition:var(--transition);">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    if ($start_page > 1) {
                        echo '<a href="?page=1&search=' . urlencode($search) . '&status=' . urlencode($status_filter) . '&state=' . $state_filter . '&lga=' . $lga_filter . '" class="btn-page" style="padding:4px 10px;border:1px solid var(--gray-200);border-radius:6px;text-decoration:none;color:var(--gray-600);font-size:0.7rem;transition:var(--transition);">1</a>';
                        if ($start_page > 2) echo '<span style="padding:4px 6px;color:var(--gray-400);font-size:0.7rem;">...</span>';
                    }
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&state=<?php echo $state_filter; ?>&lga=<?php echo $lga_filter; ?>" 
                           class="btn-page <?php echo $i == $page ? 'active' : ''; ?>" 
                           style="padding:4px 10px;border:1px solid <?php echo $i == $page ? 'var(--primary)' : 'var(--gray-200)'; ?>;border-radius:6px;text-decoration:none;color:<?php echo $i == $page ? 'white' : 'var(--gray-600)'; ?>;font-size:0.7rem;transition:var(--transition);background:<?php echo $i == $page ? 'var(--primary)' : 'transparent'; ?>;">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; 
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo '<span style="padding:4px 6px;color:var(--gray-400);font-size:0.7rem;">...</span>';
                        echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search) . '&status=' . urlencode($status_filter) . '&state=' . $state_filter . '&lga=' . $lga_filter . '" class="btn-page" style="padding:4px 10px;border:1px solid var(--gray-200);border-radius:6px;text-decoration:none;color:var(--gray-600);font-size:0.7rem;transition:var(--transition);">' . $total_pages . '</a>';
                    }
                    ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&state=<?php echo $state_filter; ?>&lga=<?php echo $lga_filter; ?>" 
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

@media (max-width: 768px) {
    table { font-size: 0.65rem; }
    th, td { padding: 4px 6px !important; }
}
</style>

<script>
// ============================================================
// LOCATION DROPDOWN CHAINING
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    var stateSelect = document.getElementById('stateSelect');
    var lgaSelect = document.getElementById('lgaSelect');
    
    if (stateSelect) {
        stateSelect.addEventListener('change', function() {
            var stateId = this.value;
            if (stateId) {
                fetch('ajax-get-lgas.php?state_id=' + stateId)
                    .then(response => response.json())
                    .then(data => {
                        lgaSelect.innerHTML = '<option value="">All LGAs</option>';
                        data.forEach(function(lga) {
                            lgaSelect.innerHTML += '<option value="' + lga.id + '">' + lga.name + '</option>';
                        });
                    })
                    .catch(error => console.error('Error:', error));
            } else {
                lgaSelect.innerHTML = '<option value="">All LGAs</option>';
            }
        });
    }
});

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