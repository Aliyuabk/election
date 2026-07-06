<?php
// ============================================================
// NATIONAL COORDINATOR - POLLING UNIT DASHBOARD
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

// Get PU ID from URL
$pu_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($pu_id <= 0) {
    header('Location: monitor-states.php?error=invalid_pu');
    exit();
}

$db = getDB();

// ============================================================
// FETCH POLLING UNIT DATA WITH LOCATION
// ============================================================
$pu_data = null;
$ward_name = '';
$lga_name = '';
$state_name = '';
$ward_id = 0;
$lga_id = 0;
$state_id = 0;

try {
    $stmt = $db->prepare("
        SELECT 
            pu.*,
            w.name as ward_name,
            w.id as ward_id,
            l.name as lga_name,
            l.id as lga_id,
            s.name as state_name,
            s.id as state_id
        FROM polling_units pu
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        JOIN states s ON l.state_id = s.id
        WHERE pu.id = ?
    ");
    $stmt->execute([$pu_id]);
    $pu_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pu_data) {
        header('Location: monitor-states.php?error=pu_not_found');
        exit();
    }
    
    $ward_name = $pu_data['ward_name'];
    $lga_name = $pu_data['lga_name'];
    $state_name = $pu_data['state_name'];
    $ward_id = $pu_data['ward_id'];
    $lga_id = $pu_data['lga_id'];
    $state_id = $pu_data['state_id'];
    
} catch (Exception $e) {
    error_log("PU Dashboard Check Error: " . $e->getMessage());
    header('Location: monitor-states.php?error=database_error');
    exit();
}

// ============================================================
// FETCH PU STATISTICS
// ============================================================
$stats = [
    'agents' => 0,
    'total_results' => 0,
    'verified_results' => 0,
    'pending_results' => 0,
    'flagged_results' => 0,
    'incidents' => 0,
    'checkins' => 0,
    'elections' => 0,
    'active_elections' => 0
];

try {
    // Get Agents
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.tenant_id = ? AND r.level = 'pu_agent' 
        AND u.jurisdiction_id = ? AND u.status = 'active'
    ");
    $stmt->execute([$tenant_id, $pu_id]);
    $stats['agents'] = $stmt->fetchColumn() ?? 0;
    
    // Get Results
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'flagged' THEN 1 ELSE 0 END) as flagged
        FROM results_ec8a 
        WHERE tenant_id = ? AND pu_id = ?
    ");
    $stmt->execute([$tenant_id, $pu_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_results'] = $result['total'] ?? 0;
    $stats['verified_results'] = $result['verified'] ?? 0;
    $stats['pending_results'] = $result['pending'] ?? 0;
    $stats['flagged_results'] = $result['flagged'] ?? 0;
    
    // Get Incidents
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM incidents 
        WHERE tenant_id = ? AND pu_id = ?
    ");
    $stmt->execute([$tenant_id, $pu_id]);
    $stats['incidents'] = $stmt->fetchColumn() ?? 0;
    
    // Get Checkins
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM agent_checkins 
        WHERE tenant_id = ? AND pu_id = ?
    ");
    $stmt->execute([$tenant_id, $pu_id]);
    $stats['checkins'] = $stmt->fetchColumn() ?? 0;
    
    // Get Elections
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM elections 
        WHERE tenant_id = ? AND deleted_at IS NULL 
        AND pus_json IS NOT NULL AND pus_json != '' 
        AND JSON_CONTAINS(pus_json, JSON_QUOTE(?))
    ");
    $stmt->execute([$tenant_id, $pu_id]);
    $stats['elections'] = $stmt->fetchColumn() ?? 0;
    
} catch (Exception $e) {
    error_log("PU Stats error: " . $e->getMessage());
}

// ============================================================
// CALCULATE PROGRESS
// ============================================================
$total_results = $stats['total_results'] ?? 0;
$verified_results = $stats['verified_results'] ?? 0;
$progress_percent = $total_results > 0 ? min(100, round(($verified_results / $total_results) * 100)) : 0;

// Get network quality display
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

$page_title = 'Polling Unit Dashboard';
$page_subtitle = $pu_data['name'] ?? 'Polling Unit';
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
                <a href="view-state.php?id=<?php echo $state_id; ?>" style="text-decoration:none;color:var(--gray-500);"><?php echo htmlspecialchars($state_name); ?></a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <a href="lga-dashboard.php?id=<?php echo $lga_id; ?>" style="text-decoration:none;color:var(--gray-500);"><?php echo htmlspecialchars($lga_name); ?></a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <a href="ward-dashboard.php?id=<?php echo $ward_id; ?>" style="text-decoration:none;color:var(--gray-500);"><?php echo htmlspecialchars($ward_name); ?></a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);"><?php echo htmlspecialchars($pu_data['name'] ?? 'PU'); ?></span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <?php echo htmlspecialchars($pu_data['name'] ?? 'Polling Unit'); ?>
                        <span style="font-size:0.7rem;background:<?php echo ($pu_data['is_active'] ?? 0) ? 'var(--primary)' : '#6B7280'; ?>;color:white;padding:2px 12px;border-radius:20px;font-weight:500;margin-left:8px;">
                            <?php echo ($pu_data['is_active'] ?? 0) ? 'Active' : 'Inactive'; ?>
                        </span>
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-code"></i> Code: <?php echo htmlspecialchars($pu_data['code'] ?? 'N/A'); ?>
                        <?php if (!empty($pu_data['description'])): ?>
                            • <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($pu_data['description']); ?>
                        <?php endif; ?>
                        <?php if (($pu_data['registered_voters'] ?? 0) > 0): ?>
                            • <i class="fas fa-users"></i> <?php echo number_format($pu_data['registered_voters'] ?? 0); ?> voters
                        <?php endif; ?>
                        <?php if (($pu_data['accredited_voters'] ?? 0) > 0): ?>
                            • <i class="fas fa-check-circle"></i> <?php echo number_format($pu_data['accredited_voters'] ?? 0); ?> accredited
                        <?php endif; ?>
                        <?php if (!empty($pu_data['network_quality'])): ?>
                            • <i class="fas fa-wifi"></i> 
                            <span style="color:<?php echo $network_colors[$pu_data['network_quality']] ?? '#6B7280'; ?>;">
                                <?php echo $network_labels[$pu_data['network_quality']] ?? $pu_data['network_quality']; ?>
                            </span>
                        <?php endif; ?>
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="ward-dashboard.php?id=<?php echo $ward_id; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-arrow-left"></i> Back to Ward
                    </a>
                    <a href="pu-results.php?id=<?php echo $pu_id; ?>" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-file-alt"></i> View Results
                    </a>
                </div>
            </div>
        </div>

        <!-- Progress Overview -->
        <div style="background:white;border-radius:var(--radius);padding:20px;margin-bottom:20px;border:1px solid var(--gray-200);">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:12px;">
                <div>
                    <h3 style="font-size:0.9rem;font-weight:600;margin:0;">Result Submission Progress</h3>
                    <p style="font-size:0.75rem;color:var(--gray-500);margin:2px 0 0;">
                        <?php echo number_format($stats['verified_results'] ?? 0); ?> of <?php echo number_format($stats['total_results'] ?? 0); ?> results verified
                    </p>
                </div>
                <span style="font-size:1.2rem;font-weight:700;color:<?php echo $progress_percent >= 80 ? '#10B981' : ($progress_percent >= 50 ? '#F59E0B' : '#EF4444'); ?>;">
                    <?php echo $progress_percent; ?>%
                </span>
            </div>
            <div style="width:100%;height:12px;background:var(--gray-100);border-radius:8px;overflow:hidden;">
                <div style="width:<?php echo $progress_percent; ?>%;height:100%;background:linear-gradient(90deg, <?php echo $progress_percent >= 80 ? '#10B981' : ($progress_percent >= 50 ? '#F59E0B' : '#EF4444'); ?>, <?php echo $progress_percent >= 80 ? '#34D399' : ($progress_percent >= 50 ? '#FBBF24' : '#F87171'); ?>);border-radius:8px;transition:width 1s ease;"></div>
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
                <div class="stat-icon blue"><i class="fas fa-user-tie"></i></div>
                <div class="stat-number"><?php echo number_format($stats['agents'] ?? 0); ?></div>
                <div class="stat-label">PU Agents</div>
                <div class="stat-change"><i class="fas fa-users"></i> Assigned</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['verified_results'] ?? 0); ?></div>
                <div class="stat-label">Verified Results</div>
                <div class="stat-change up"><i class="fas fa-check"></i> Approved</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo number_format($stats['pending_results'] ?? 0); ?></div>
                <div class="stat-label">Pending Results</div>
                <div class="stat-change down"><i class="fas fa-hourglass-half"></i> Awaiting</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-flag"></i></div>
                <div class="stat-number"><?php echo number_format($stats['flagged_results'] ?? 0); ?></div>
                <div class="stat-label">Flagged Results</div>
                <div class="stat-change down"><i class="fas fa-exclamation-triangle"></i> Needs review</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['incidents'] ?? 0); ?></div>
                <div class="stat-label">Incidents</div>
                <div class="stat-change down"><i class="fas fa-clock"></i> Total</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon teal"><i class="fas fa-sign-in-alt"></i></div>
                <div class="stat-number"><?php echo number_format($stats['checkins'] ?? 0); ?></div>
                <div class="stat-label">Check-ins</div>
                <div class="stat-change"><i class="fas fa-clock"></i> Total</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-vote-yea"></i></div>
                <div class="stat-number"><?php echo number_format($stats['elections'] ?? 0); ?></div>
                <div class="stat-label">Elections</div>
                <div class="stat-change"><i class="fas fa-calendar"></i> Assigned</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pink"><i class="fas fa-address-card"></i></div>
                <div class="stat-number"><?php echo number_format($pu_data['registered_voters'] ?? 0); ?></div>
                <div class="stat-label">Registered Voters</div>
                <div class="stat-change"><i class="fas fa-users"></i> Total</div>
            </div>
        </div>

        <!-- Location Info -->
        <div style="background:white;border-radius:var(--radius);padding:16px 20px;border:1px solid var(--gray-200);margin-bottom:20px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
                <div>
                    <label style="display:block;font-size:0.65rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">State</label>
                    <div style="font-weight:500;"><?php echo htmlspecialchars($state_name); ?></div>
                </div>
                <div>
                    <label style="display:block;font-size:0.65rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">LGA</label>
                    <div style="font-weight:500;"><?php echo htmlspecialchars($lga_name); ?></div>
                </div>
                <div>
                    <label style="display:block;font-size:0.65rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">Ward</label>
                    <div style="font-weight:500;"><?php echo htmlspecialchars($ward_name); ?></div>
                </div>
                <?php if (!empty($pu_data['address'])): ?>
                    <div>
                        <label style="display:block;font-size:0.65rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">Address</label>
                        <div style="font-weight:500;font-size:0.85rem;"><?php echo htmlspecialchars($pu_data['address']); ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($pu_data['gps_lat']) && !empty($pu_data['gps_lng'])): ?>
                    <div>
                        <label style="display:block;font-size:0.65rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">GPS Coordinates</label>
                        <div style="font-weight:500;font-size:0.85rem;">
                            <?php echo number_format($pu_data['gps_lat'], 6); ?>, <?php echo number_format($pu_data['gps_lng'], 6); ?>
                            <?php if (!empty($pu_data['gps_accuracy'])): ?>
                                <span style="font-size:0.65rem;color:var(--gray-400);">(±<?php echo $pu_data['gps_accuracy']; ?>m)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
            <a href="pu-results.php?id=<?php echo $pu_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-file-alt" style="color:var(--primary);"></i>
                <span>View Results</span>
            </a>
            <a href="pu-agents.php?pu=<?php echo $pu_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-user-tie" style="color:var(--secondary);"></i>
                <span>View Agents</span>
            </a>
            <a href="broadcasts-create.php?pu=<?php echo $pu_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-bullhorn" style="color:var(--warning);"></i>
                <span>Broadcast to PU</span>
            </a>
            <a href="incidents.php?pu=<?php echo $pu_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-exclamation-triangle" style="color:var(--danger);"></i>
                <span>View Incidents</span>
            </a>
            <a href="pu-checkins.php?id=<?php echo $pu_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-sign-in-alt" style="color:var(--teal);"></i>
                <span>View Check-ins</span>
            </a>
            <a href="reports.php?type=pu&id=<?php echo $pu_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-file-pdf" style="color:var(--danger);"></i>
                <span>Generate Report</span>
            </a>
        </div>
    </div>
</main>

<style>
.stat-icon.teal { background: #CCFBF1; color: #0D9488; }
.stat-icon.pink { background: #FCE7F3; color: #DB2777; }
.quick-action-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-hover); border-color: var(--primary); }
.btn-secondary:hover { background: var(--gray-200); transform: translateY(-1px); }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
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