<?php
// ============================================================
// NATIONAL COORDINATOR - LIVE RESULTS
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
    error_log("Live Results Error: " . $e->getMessage());
    header('Location: elections.php?error=database_error');
    exit();
}

// ============================================================
// GET ELECTION LOCATIONS
// ============================================================
$state_ids = json_decode($election['states_json'] ?? '[]', true);
$lga_ids = json_decode($election['lgas_json'] ?? '[]', true);
$ward_ids = json_decode($election['wards_json'] ?? '[]', true);
$pu_ids = json_decode($election['pus_json'] ?? '[]', true);

// ============================================================
// FETCH LIVE RESULTS DATA
// ============================================================
$results = [];
$party_votes = [];
$total_votes = 0;
$total_pus = 0;
$reporting_pus = 0;

try {
    // Build query for results
    $query = "
        SELECT 
            r.*,
            pu.name as pu_name,
            pu.code as pu_code,
            w.name as ward_name,
            l.name as lga_name,
            s.name as state_name,
            u.full_name as agent_name
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        JOIN states s ON l.state_id = s.id
        LEFT JOIN users u ON r.agent_id = u.id
        WHERE r.tenant_id = ? AND r.election_id = ?
    ";
    
    $params = [$tenant_id, $election_id];
    
    // Add location filters if present
    if (!empty($pu_ids)) {
        $placeholders = implode(',', array_fill(0, count($pu_ids), '?'));
        $query .= " AND r.pu_id IN ($placeholders)";
        $params = array_merge($params, $pu_ids);
    } elseif (!empty($ward_ids)) {
        $placeholders = implode(',', array_fill(0, count($ward_ids), '?'));
        $query .= " AND r.ward_id IN ($placeholders)";
        $params = array_merge($params, $ward_ids);
    } elseif (!empty($lga_ids)) {
        $placeholders = implode(',', array_fill(0, count($lga_ids), '?'));
        $query .= " AND r.lga_id IN ($placeholders)";
        $params = array_merge($params, $lga_ids);
    } elseif (!empty($state_ids)) {
        $placeholders = implode(',', array_fill(0, count($state_ids), '?'));
        $query .= " AND r.state_id IN ($placeholders)";
        $params = array_merge($params, $state_ids);
    }
    
    $query .= " ORDER BY r.created_at DESC LIMIT 100";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Aggregate party votes
    foreach ($results as $result) {
        if (!empty($result['party_votes_json'])) {
            $party_votes_data = json_decode($result['party_votes_json'], true);
            if (is_array($party_votes_data)) {
                foreach ($party_votes_data as $party => $votes) {
                    if (!isset($party_votes[$party])) {
                        $party_votes[$party] = 0;
                    }
                    $party_votes[$party] += intval($votes);
                }
            }
        }
        $total_votes += intval($result['total_votes_cast'] ?? 0);
    }
    
    // Get total PUs
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
        $placeholders = implode(',', array_fill(0, count($pu_ids), '?'));
        $query .= " AND pu.id IN ($placeholders)";
        $params = $pu_ids;
    } elseif (!empty($ward_ids)) {
        $placeholders = implode(',', array_fill(0, count($ward_ids), '?'));
        $query .= " AND pu.ward_id IN ($placeholders)";
        $params = $ward_ids;
    } elseif (!empty($lga_ids)) {
        $placeholders = implode(',', array_fill(0, count($lga_ids), '?'));
        $query .= " AND w.lga_id IN ($placeholders)";
        $params = $lga_ids;
    } elseif (!empty($state_ids)) {
        $placeholders = implode(',', array_fill(0, count($state_ids), '?'));
        $query .= " AND l.state_id IN ($placeholders)";
        $params = $state_ids;
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $total_pus = $stmt->fetchColumn() ?: 0;
    
    // Get reporting PUs
    $query = "
        SELECT COUNT(DISTINCT r.pu_id) as count 
        FROM results_ec8a r
        WHERE r.tenant_id = ? AND r.election_id = ?
    ";
    
    $params = [$tenant_id, $election_id];
    
    if (!empty($pu_ids)) {
        $placeholders = implode(',', array_fill(0, count($pu_ids), '?'));
        $query .= " AND r.pu_id IN ($placeholders)";
        $params = array_merge($params, $pu_ids);
    } elseif (!empty($ward_ids)) {
        $placeholders = implode(',', array_fill(0, count($ward_ids), '?'));
        $query .= " AND r.ward_id IN ($placeholders)";
        $params = array_merge($params, $ward_ids);
    } elseif (!empty($lga_ids)) {
        $placeholders = implode(',', array_fill(0, count($lga_ids), '?'));
        $query .= " AND r.lga_id IN ($placeholders)";
        $params = array_merge($params, $lga_ids);
    } elseif (!empty($state_ids)) {
        $placeholders = implode(',', array_fill(0, count($state_ids), '?'));
        $query .= " AND r.state_id IN ($placeholders)";
        $params = array_merge($params, $state_ids);
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $reporting_pus = $stmt->fetchColumn() ?: 0;
    
} catch (Exception $e) {
    error_log("Live results fetch error: " . $e->getMessage());
}

// Sort party votes descending
arsort($party_votes);

// Calculate reporting percentage
$reporting_percent = $total_pus > 0 ? round(($reporting_pus / $total_pus) * 100) : 0;

// Get latest 20 results for display
$latest_results = array_slice($results, 0, 20);

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Live Results';
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
                <span style="font-weight:600;color:var(--gray-800);">Live Results</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;display:flex;align-items:center;gap:12px;">
                        <i class="fas fa-broadcast-tower" style="color:var(--danger);"></i>
                        Live Results
                        <?php if ($election['status'] === 'active'): ?>
                            <span style="font-size:0.6rem;background:#EF4444;color:white;padding:2px 12px;border-radius:20px;font-weight:500;animation:pulse 1.5s ease-in-out infinite;">
                                LIVE
                            </span>
                        <?php endif; ?>
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <?php echo htmlspecialchars($election['name']); ?>
                        <?php if ($election['election_date']): ?>
                            • <?php echo date('F j, Y', strtotime($election['election_date'])); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="election-progress.php?id=<?php echo $election_id; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-chart-line"></i> Progress
                    </a>
                    <a href="election-view.php?id=<?php echo $election_id; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-vote-yea"></i></div>
                <div class="stat-number"><?php echo number_format($total_votes); ?></div>
                <div class="stat-label">Total Votes</div>
                <div class="stat-change">Cast so far</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-flag-checkered"></i></div>
                <div class="stat-number"><?php echo number_format($reporting_pus); ?></div>
                <div class="stat-label">Reporting PUs</div>
                <div class="stat-change"><i class="fas fa-check"></i> <?php echo $reporting_percent; ?>% coverage</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo number_format($total_pus); ?></div>
                <div class="stat-label">Total PUs</div>
                <div class="stat-change">In election</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo date('g:i A'); ?></div>
                <div class="stat-label">Last Update</div>
                <div class="stat-change"><i class="fas fa-sync-alt"></i> Live</div>
            </div>
        </div>

        <!-- Results Grid -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
            <!-- Party Votes -->
            <div style="background:white;border-radius:var(--radius);padding:16px 20px;border:1px solid var(--gray-200);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <h4 style="font-size:0.85rem;font-weight:600;margin:0;">
                        <i class="fas fa-chart-bar" style="color:var(--primary);margin-right:6px;"></i>
                        Party Votes Summary
                    </h4>
                    <span style="font-size:0.7rem;color:var(--gray-400);"><?php echo count($party_votes); ?> parties</span>
                </div>
                <?php if (count($party_votes) > 0): ?>
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        <?php 
                        $max_votes = max($party_votes) ?: 1;
                        $party_colors = ['#3B82F6', '#10B981', '#8B5CF6', '#F59E0B', '#EF4444', '#EC4899', '#14B8A6', '#F97316', '#6366F1', '#84CC16'];
                        $i = 0;
                        foreach ($party_votes as $party => $votes): 
                            $percentage = ($votes / $max_votes) * 100;
                            $color = $party_colors[$i % count($party_colors)];
                            $i++;
                        ?>
                            <div>
                                <div style="display:flex;justify-content:space-between;font-size:0.8rem;">
                                    <span style="font-weight:500;"><?php echo htmlspecialchars($party); ?></span>
                                    <span style="font-weight:600;"><?php echo number_format($votes); ?></span>
                                </div>
                                <div style="width:100%;height:8px;background:var(--gray-100);border-radius:4px;overflow:hidden;margin-top:2px;">
                                    <div style="width:<?php echo $percentage; ?>%;height:100%;background:<?php echo $color; ?>;border-radius:4px;transition:width 0.5s ease;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color:var(--gray-400);text-align:center;padding:20px 0;">No results submitted yet</p>
                <?php endif; ?>
            </div>

            <!-- Latest Results -->
            <div style="background:white;border-radius:var(--radius);padding:16px 20px;border:1px solid var(--gray-200);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <h4 style="font-size:0.85rem;font-weight:600;margin:0;">
                        <i class="fas fa-clock" style="color:var(--primary);margin-right:6px;"></i>
                        Latest Submissions
                    </h4>
                    <span style="font-size:0.7rem;color:var(--gray-400);"><?php echo count($latest_results); ?> recent</span>
                </div>
                <?php if (count($latest_results) > 0): ?>
                    <div style="max-height:300px;overflow-y:auto;">
                        <?php foreach ($latest_results as $result): ?>
                            <div style="display:flex;align-items:center;gap:10px;padding:6px 0;border-bottom:1px solid var(--gray-100);">
                                <div style="width:32px;height:32px;border-radius:50%;background:var(--primary)20;color:var(--primary);display:flex;align-items:center;justify-content:center;font-weight:600;font-size:0.7rem;flex-shrink:0;">
                                    <?php echo substr($result['pu_name'] ?? 'PU', 0, 2); ?>
                                </div>
                                <div style="flex:1;min-width:0;">
                                    <div style="font-weight:500;font-size:0.75rem;"><?php echo htmlspecialchars($result['pu_name'] ?? 'Unknown PU'); ?></div>
                                    <div style="font-size:0.6rem;color:var(--gray-400);">
                                        <?php echo htmlspecialchars($result['lga_name'] ?? ''); ?>
                                        <span style="margin:0 4px;">•</span>
                                        <?php echo date('g:i A', strtotime($result['created_at'])); ?>
                                    </div>
                                </div>
                                <div style="font-weight:600;font-size:0.8rem;color:var(--secondary);">
                                    <?php echo number_format($result['total_votes_cast'] ?? 0); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color:var(--gray-400);text-align:center;padding:20px 0;">No results submitted yet</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
            <a href="result-verification.php?election=<?php echo $election_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-check-double" style="color:var(--secondary);"></i>
                <span>Verify Results</span>
            </a>
            <a href="analytics.php?election=<?php echo $election_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-chart-pie" style="color:var(--primary);"></i>
                <span>Analytics</span>
            </a>
            <a href="reports.php?type=election&id=<?php echo $election_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-file-pdf" style="color:var(--danger);"></i>
                <span>Export Report</span>
            </a>
            <button onclick="location.reload()" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);border:none;cursor:pointer;width:100%;">
                <i class="fas fa-sync-alt" style="color:var(--warning);"></i>
                <span>Refresh Data</span>
            </button>
        </div>
    </div>
</main>

<style>
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.quick-action-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-hover); border-color: var(--primary); }
.btn-secondary:hover { background: var(--gray-200); transform: translateY(-1px); }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    div[style*="grid-template-columns:1fr 1fr;gap:20px;"] { grid-template-columns: 1fr !important; }
}
</style>

<script>
// ============================================================
// AUTO-REFRESH (every 30 seconds)
// ============================================================
setTimeout(function() {
    location.reload();
}, 30000);

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