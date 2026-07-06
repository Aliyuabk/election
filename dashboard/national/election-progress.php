<?php
// ============================================================
// NATIONAL COORDINATOR - ELECTION PROGRESS
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

// Get election ID
$election_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($election_id <= 0) {
    header('Location: elections.php?error=invalid_election');
    exit();
}

$db = getDB();

// ============================================================
// FETCH ELECTION DATA
// ============================================================
$election = null;
$back_url = 'elections.php';

try {
    $stmt = $db->prepare("
        SELECT 
            e.*,
            u.full_name as created_by_name
        FROM elections e
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.id = ? AND e.tenant_id = ?
    ");
    $stmt->execute([$election_id, $tenant_id]);
    $election = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$election) {
        header('Location: elections.php?error=election_not_found');
        exit();
    }
    
} catch (Exception $e) {
    error_log("Election Progress Error: " . $e->getMessage());
    header('Location: elections.php?error=database_error');
    exit();
}

// ============================================================
// GET LOCATION IDs FROM ELECTION
// ============================================================
$state_ids = json_decode($election['states_json'] ?? '[]', true);
$lga_ids = json_decode($election['lgas_json'] ?? '[]', true);
$ward_ids = json_decode($election['wards_json'] ?? '[]', true);
$pu_ids = json_decode($election['pus_json'] ?? '[]', true);

// If no specific locations, get all
if (empty($state_ids) && empty($lga_ids) && empty($ward_ids) && empty($pu_ids)) {
    // Get all states
    try {
        $stmt = $db->prepare("SELECT id FROM states WHERE is_active = 1");
        $stmt->execute();
        $state_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $state_ids = [];
    }
}

// ============================================================
// FETCH PROGRESS STATISTICS
// ============================================================
$stats = [
    'total_pus' => 0,
    'total_results' => 0,
    'verified_results' => 0,
    'pending_results' => 0,
    'flagged_results' => 0,
    'total_incidents' => 0,
    'states_with_results' => 0,
    'total_states' => count($state_ids),
    'progress_percent' => 0
];

// Get total PUs in election
try {
    $pu_placeholders = !empty($pu_ids) ? implode(',', array_fill(0, count($pu_ids), '?')) : '0';
    $ward_placeholders = !empty($ward_ids) ? implode(',', array_fill(0, count($ward_ids), '?')) : '0';
    $lga_placeholders = !empty($lga_ids) ? implode(',', array_fill(0, count($lga_ids), '?')) : '0';
    $state_placeholders = !empty($state_ids) ? implode(',', array_fill(0, count($state_ids), '?')) : '0';
    
    $query = "
        SELECT COUNT(*) as count 
        FROM polling_units pu
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        JOIN states s ON l.state_id = s.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if (!empty($pu_ids)) {
        $query .= " AND pu.id IN ($pu_placeholders)";
        $params = array_merge($params, $pu_ids);
    } elseif (!empty($ward_ids)) {
        $query .= " AND pu.ward_id IN ($ward_placeholders)";
        $params = array_merge($params, $ward_ids);
    } elseif (!empty($lga_ids)) {
        $query .= " AND w.lga_id IN ($lga_placeholders)";
        $params = array_merge($params, $lga_ids);
    } elseif (!empty($state_ids)) {
        $query .= " AND l.state_id IN ($state_placeholders)";
        $params = array_merge($params, $state_ids);
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $stats['total_pus'] = $stmt->fetchColumn() ?: 0;
    
} catch (Exception $e) {
    error_log("Total PUs error: " . $e->getMessage());
}

// Get results statistics
try {
    $query = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'flagged' THEN 1 ELSE 0 END) as flagged
        FROM results_ec8a r
        WHERE r.tenant_id = ?
    ";
    
    $params = [$tenant_id];
    
    if (!empty($pu_ids)) {
        $query .= " AND r.pu_id IN ($pu_placeholders)";
        $params = array_merge($params, $pu_ids);
    } elseif (!empty($ward_ids)) {
        $query .= " AND r.ward_id IN ($ward_placeholders)";
        $params = array_merge($params, $ward_ids);
    } elseif (!empty($lga_ids)) {
        $query .= " AND r.lga_id IN ($lga_placeholders)";
        $params = array_merge($params, $lga_ids);
    } elseif (!empty($state_ids)) {
        $query .= " AND r.state_id IN ($state_placeholders)";
        $params = array_merge($params, $state_ids);
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stats['total_results'] = $result['total'] ?? 0;
    $stats['verified_results'] = $result['verified'] ?? 0;
    $stats['pending_results'] = $result['pending'] ?? 0;
    $stats['flagged_results'] = $result['flagged'] ?? 0;
    
} catch (Exception $e) {
    error_log("Results stats error: " . $e->getMessage());
}

// Get incidents
try {
    $query = "
        SELECT COUNT(*) as count
        FROM incidents i
        WHERE i.tenant_id = ?
    ";
    
    $params = [$tenant_id];
    
    if (!empty($state_ids)) {
        $query .= " AND i.state_id IN ($state_placeholders)";
        $params = array_merge($params, $state_ids);
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $stats['total_incidents'] = $stmt->fetchColumn() ?: 0;
    
} catch (Exception $e) {
    error_log("Incidents stats error: " . $e->getMessage());
}

// Calculate progress
if ($stats['total_pus'] > 0) {
    $stats['progress_percent'] = round(($stats['verified_results'] / $stats['total_pus']) * 100);
}

// ============================================================
// FETCH STATE-WISE PROGRESS
// ============================================================
$state_progress = [];
try {
    $query = "
        SELECT 
            s.id,
            s.name,
            COUNT(DISTINCT pu.id) as pu_count,
            COUNT(r.id) as result_count,
            SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) as verified_count
        FROM states s
        JOIN lgas l ON l.state_id = s.id
        JOIN wards w ON w.lga_id = l.id
        JOIN polling_units pu ON pu.ward_id = w.id
        LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
    ";
    
    $params = [$tenant_id];
    
    if (!empty($state_ids)) {
        $query .= " WHERE s.id IN (" . implode(',', array_fill(0, count($state_ids), '?')) . ")";
        $params = array_merge($params, $state_ids);
    }
    
    $query .= " GROUP BY s.id ORDER BY s.name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $state_progress = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("State progress error: " . $e->getMessage());
}

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Election Progress';
$page_subtitle = $election['name'] ?? 'Election';
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
                <a href="elections.php" style="text-decoration:none;color:var(--gray-500);">Elections</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <a href="election-view.php?id=<?php echo $election_id; ?>" style="text-decoration:none;color:var(--gray-500);"><?php echo htmlspecialchars($election['name']); ?></a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);">Progress</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <i class="fas fa-chart-line" style="color:var(--primary);"></i>
                        Election Progress
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <?php echo htmlspecialchars($election['name']); ?>
                        <span style="font-size:0.7rem;background:<?php echo $election['status'] === 'active' ? '#10B981' : ($election['status'] === 'upcoming' ? '#3B82F6' : '#6B7280'); ?>;color:white;padding:2px 12px;border-radius:20px;font-weight:500;margin-left:8px;">
                            <?php echo ucfirst($election['status']); ?>
                        </span>
                    </p>
                    <p style="color:var(--gray-400);font-size:0.75rem;margin:2px 0 0;">
                        <?php if ($election['election_date']): ?>
                            <i class="fas fa-calendar-alt"></i> <?php echo date('F j, Y', strtotime($election['election_date'])); ?>
                            <?php if ($election['start_time']): ?>
                                • <?php echo date('g:i A', strtotime($election['start_time'])); ?>
                                <?php if ($election['end_time']): ?>
                                    - <?php echo date('g:i A', strtotime($election['end_time'])); ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="election-view.php?id=<?php echo $election_id; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    <a href="live-results.php?id=<?php echo $election_id; ?>" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-broadcast-tower"></i> Live Results
                    </a>
                </div>
            </div>
        </div>

        <!-- Overall Progress -->
        <div style="background:white;border-radius:var(--radius);padding:20px;margin-bottom:20px;border:1px solid var(--gray-200);">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:12px;">
                <div>
                    <h3 style="font-size:0.9rem;font-weight:600;margin:0;">Overall Progress</h3>
                    <p style="font-size:0.75rem;color:var(--gray-500);margin:2px 0 0;">
                        <?php echo number_format($stats['verified_results']); ?> of <?php echo number_format($stats['total_pus']); ?> polling units verified
                    </p>
                </div>
                <span style="font-size:1.2rem;font-weight:700;color:<?php echo $stats['progress_percent'] >= 80 ? '#10B981' : ($stats['progress_percent'] >= 50 ? '#F59E0B' : '#EF4444'); ?>;">
                    <?php echo $stats['progress_percent']; ?>%
                </span>
            </div>
            <div style="width:100%;height:16px;background:var(--gray-100);border-radius:8px;overflow:hidden;">
                <div style="width:<?php echo $stats['progress_percent']; ?>%;height:100%;background:linear-gradient(90deg, <?php echo $stats['progress_percent'] >= 80 ? '#10B981' : ($stats['progress_percent'] >= 50 ? '#F59E0B' : '#EF4444'); ?>, <?php echo $stats['progress_percent'] >= 80 ? '#34D399' : ($stats['progress_percent'] >= 50 ? '#FBBF24' : '#F87171'); ?>);border-radius:8px;transition:width 1s ease;"></div>
            </div>
            <div style="display:flex;justify-content:space-between;margin-top:8px;font-size:0.65rem;color:var(--gray-400);">
                <span>0%</span>
                <span>50%</span>
                <span>100%</span>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-flag-checkered"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_pus']); ?></div>
                <div class="stat-label">Polling Units</div>
                <div class="stat-change">Total coverage</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['verified_results']); ?></div>
                <div class="stat-label">Verified Results</div>
                <div class="stat-change up"><i class="fas fa-check"></i> Approved</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo number_format($stats['pending_results']); ?></div>
                <div class="stat-label">Pending Results</div>
                <div class="stat-change down"><i class="fas fa-hourglass-half"></i> Awaiting</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-flag"></i></div>
                <div class="stat-number"><?php echo number_format($stats['flagged_results']); ?></div>
                <div class="stat-label">Flagged Results</div>
                <div class="stat-change down"><i class="fas fa-exclamation-triangle"></i> Review</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_incidents']); ?></div>
                <div class="stat-label">Incidents</div>
                <div class="stat-change down"><i class="fas fa-clock"></i> Total</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon teal"><i class="fas fa-flag"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_states']); ?></div>
                <div class="stat-label">States</div>
                <div class="stat-change"><i class="fas fa-map"></i> Covered</div>
            </div>
        </div>

        <!-- State Progress -->
        <?php if (count($state_progress) > 0): ?>
        <div style="background:white;border-radius:var(--radius);padding:16px 20px;border:1px solid var(--gray-200);margin-bottom:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <h4 style="font-size:0.85rem;font-weight:600;margin:0;">
                    <i class="fas fa-map" style="color:var(--primary);margin-right:6px;"></i>
                    State-wise Progress
                </h4>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px;">
                <?php foreach ($state_progress as $state): 
                    $state_progress_percent = $state['pu_count'] > 0 ? round(($state['verified_count'] / $state['pu_count']) * 100) : 0;
                    $color = $state_progress_percent >= 80 ? '#10B981' : ($state_progress_percent >= 50 ? '#F59E0B' : '#EF4444');
                ?>
                    <div style="background:var(--gray-50);border-radius:8px;padding:12px 16px;border:1px solid var(--gray-200);">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                            <span style="font-weight:600;font-size:0.85rem;"><?php echo htmlspecialchars($state['name']); ?></span>
                            <span style="font-weight:600;font-size:0.8rem;color:<?php echo $color; ?>;"><?php echo $state_progress_percent; ?>%</span>
                        </div>
                        <div style="width:100%;height:6px;background:var(--gray-200);border-radius:4px;overflow:hidden;">
                            <div style="width:<?php echo $state_progress_percent; ?>%;height:100%;background:<?php echo $color; ?>;border-radius:4px;"></div>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:0.6rem;color:var(--gray-400);margin-top:4px;">
                            <span><?php echo number_format($state['verified_count']); ?> verified</span>
                            <span><?php echo number_format($state['pu_count']); ?> PUs</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
            <a href="live-results.php?id=<?php echo $election_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-broadcast-tower" style="color:var(--danger);"></i>
                <span>Live Results</span>
            </a>
            <a href="result-verification.php?election=<?php echo $election_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-check-double" style="color:var(--secondary);"></i>
                <span>Verify Results</span>
            </a>
            <a href="reports.php?type=election&id=<?php echo $election_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-file-pdf" style="color:var(--danger);"></i>
                <span>Generate Report</span>
            </a>
            <a href="analytics.php?election=<?php echo $election_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-chart-pie" style="color:var(--primary);"></i>
                <span>Analytics</span>
            </a>
        </div>
    </div>
</main>

<style>
.stat-icon.teal { background: #CCFBF1; color: #0D9488; }
.quick-action-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-hover); border-color: var(--primary); }
.btn-secondary:hover { background: var(--gray-200); transform: translateY(-1px); }
.btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3); }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    div[style*="grid-template-columns:repeat(auto-fill,minmax(250px,1fr))"] {
        grid-template-columns: 1fr !important;
    }
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