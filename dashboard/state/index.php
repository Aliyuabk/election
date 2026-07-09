<?php
// ============================================================
// STATE COORDINATOR - DASHBOARD
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

$user_name = SessionManager::get('user_name', 'State Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$state_id = SessionManager::get('state_id');

$db = getDB();

// ============================================================
// FETCH STATISTICS
// ============================================================
$stats = [
    'total_lgas' => 0,
    'total_coordinators' => 0,
    'total_elections' => 0,
    'active_elections' => 0,
    'total_pus' => 0,
    'reported_pus' => 0,
    'total_incidents' => 0,
    'pending_incidents' => 0,
    'agents_online' => 0,
    'pending_uploads' => 0,
    'broadcast_count' => 0
];

try {
    // Get state name
    $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
    $stmt->execute([$state_id]);
    $state = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_name = $state['name'] ?? 'Unknown State';
    
    // Total LGAs in state
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM lgas WHERE state_id = ? AND is_active = 1");
    $stmt->execute([$state_id]);
    $stats['total_lgas'] = $stmt->fetchColumn() ?: 0;
    
    // Total coordinators in state
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE r.level IN ('lga', 'ward') 
        AND u.state_id = ? 
        AND u.deleted_at IS NULL
    ");
    $stmt->execute([$state_id]);
    $stats['total_coordinators'] = $stmt->fetchColumn() ?: 0;
    
    // Total elections in state
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM elections 
        WHERE tenant_id = ? 
        AND states_json LIKE ? 
        AND deleted_at IS NULL
    ");
    $stmt->execute([$tenant_id, '%"' . $state_id . '"%']);
    $stats['total_elections'] = $stmt->fetchColumn() ?: 0;
    
    // Active elections
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM elections 
        WHERE tenant_id = ? 
        AND states_json LIKE ? 
        AND status = 'active' 
        AND deleted_at IS NULL
    ");
    $stmt->execute([$tenant_id, '%"' . $state_id . '"%']);
    $stats['active_elections'] = $stmt->fetchColumn() ?: 0;
    
    // Total PUs in state
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM polling_units pu
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        WHERE l.state_id = ? AND pu.is_active = 1
    ");
    $stmt->execute([$state_id]);
    $stats['total_pus'] = $stmt->fetchColumn() ?: 0;
    
    // Reported PUs
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT r.pu_id) as count 
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        WHERE l.state_id = ? AND r.tenant_id = ?
    ");
    $stmt->execute([$state_id, $tenant_id]);
    $stats['reported_pus'] = $stmt->fetchColumn() ?: 0;
    
    // Total incidents
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM incidents WHERE state_id = ?");
    $stmt->execute([$state_id]);
    $stats['total_incidents'] = $stmt->fetchColumn() ?: 0;
    
    // Pending incidents
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM incidents WHERE state_id = ? AND status IN ('reported', 'acknowledged')");
    $stmt->execute([$state_id]);
    $stats['pending_incidents'] = $stmt->fetchColumn() ?: 0;
    
    // Agents online (last 15 minutes)
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT u.id) as count
        FROM users u
        JOIN user_sessions us ON u.id = us.user_id
        JOIN polling_units pu ON u.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        WHERE l.state_id = ? AND us.is_active = 1 AND us.last_activity_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->execute([$state_id]);
    $stats['agents_online'] = $stmt->fetchColumn() ?: 0;
    
    // Pending uploads (pending results)
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        WHERE l.state_id = ? AND r.status = 'pending'
    ");
    $stmt->execute([$state_id]);
    $stats['pending_uploads'] = $stmt->fetchColumn() ?: 0;
    
    // Broadcast count
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM broadcasts 
        WHERE tenant_id = ? 
        AND target_audience IN ('state', 'all')
        AND (target_ids_json LIKE ? OR target_ids_json IS NULL)
    ");
    $stmt->execute([$tenant_id, '%"' . $state_id . '"%']);
    $stats['broadcast_count'] = $stmt->fetchColumn() ?: 0;
    
} catch (Exception $e) {
    error_log("State Dashboard Error: " . $e->getMessage());
}

include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

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
.stat-card .stat-icon.purple { background: #8B5CF6; }
.stat-card .stat-icon.yellow { background: #F59E0B; }
.stat-card .stat-icon.red { background: #EF4444; }
.stat-card .stat-icon.teal { background: #0D9488; }
.stat-card .stat-icon.orange { background: #F97316; }
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
.stat-card .stat-change.down { color: var(--danger); }

.quick-action-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-hover);
    border-color: var(--primary);
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <div>
                <h1 style="font-size:1.5rem;font-weight:700;margin:0;">
                    <i class="fas fa-map-marked-alt" style="color:var(--primary);"></i>
                    Welcome, <?php echo htmlspecialchars($user_name); ?>!
                </h1>
                <p style="color:var(--gray-500);margin:2px 0 0;">
                    <i class="fas fa-flag"></i> 
                    <?php echo htmlspecialchars($state_name ?? 'State'); ?> Coordinator Dashboard
                </p>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="monitor-lgas.php" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                    <i class="fas fa-eye"></i> Monitor LGAs
                </a>
                <a href="broadcasts-create.php" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                    <i class="fas fa-bullhorn"></i> Broadcast
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-map-marker-alt"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_lgas']); ?></div>
                <div class="stat-label">LGAs</div>
                <div class="stat-change">Total LGAs</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-user-tie"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_coordinators']); ?></div>
                <div class="stat-label">Coordinators</div>
                <div class="stat-change up"><i class="fas fa-users"></i> Active</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-vote-yea"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_elections']); ?></div>
                <div class="stat-label">Elections</div>
                <div class="stat-change"><?php echo number_format($stats['active_elections']); ?> active</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-flag-checkered"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_pus']); ?></div>
                <div class="stat-label">Polling Units</div>
                <div class="stat-change"><?php echo number_format($stats['reported_pus']); ?> reported</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon teal"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo number_format($stats['agents_online']); ?></div>
                <div class="stat-label">Agents Online</div>
                <div class="stat-change up"><i class="fas fa-circle" style="color:#10B981;font-size:0.5rem;"></i> Active</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-upload"></i></div>
                <div class="stat-number"><?php echo number_format($stats['pending_uploads']); ?></div>
                <div class="stat-label">Pending Uploads</div>
                <div class="stat-change down"><i class="fas fa-clock"></i> Awaiting</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_incidents']); ?></div>
                <div class="stat-label">Incidents</div>
                <div class="stat-change down"><?php echo number_format($stats['pending_incidents']); ?> pending</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-bullhorn"></i></div>
                <div class="stat-number"><?php echo number_format($stats['broadcast_count']); ?></div>
                <div class="stat-label">Broadcasts</div>
                <div class="stat-change"><i class="fas fa-envelope"></i> Sent</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:20px;">
            <a href="monitor-lgas.php" class="quick-action-card" style="display:flex;align-items:center;gap:12px;padding:16px 20px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);transition:var(--transition);">
                <div style="width:40px;height:40px;border-radius:10px;background:#EFF6FF;color:#3B82F6;display:flex;align-items:center;justify-content:center;font-size:1.1rem;">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <div>
                    <div style="font-weight:600;font-size:0.85rem;">Monitor LGAs</div>
                    <div style="font-size:0.65rem;color:var(--gray-400);">View LGA performance</div>
                </div>
            </a>
            
            <a href="lga-coordinators.php" class="quick-action-card" style="display:flex;align-items:center;gap:12px;padding:16px 20px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);transition:var(--transition);">
                <div style="width:40px;height:40px;border-radius:10px;background:#F5F3FF;color:#8B5CF6;display:flex;align-items:center;justify-content:center;font-size:1.1rem;">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div>
                    <div style="font-weight:600;font-size:0.85rem;">Coordinators</div>
                    <div style="font-size:0.65rem;color:var(--gray-400);">Manage LGA coordinators</div>
                </div>
            </a>
            
            <a href="elections.php" class="quick-action-card" style="display:flex;align-items:center;gap:12px;padding:16px 20px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);transition:var(--transition);">
                <div style="width:40px;height:40px;border-radius:10px;background:#ECFDF5;color:#10B981;display:flex;align-items:center;justify-content:center;font-size:1.1rem;">
                    <i class="fas fa-vote-yea"></i>
                </div>
                <div>
                    <div style="font-weight:600;font-size:0.85rem;">Elections</div>
                    <div style="font-size:0.65rem;color:var(--gray-400);">View state elections</div>
                </div>
            </a>
            
            <a href="incidents.php" class="quick-action-card" style="display:flex;align-items:center;gap:12px;padding:16px 20px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);transition:var(--transition);">
                <div style="width:40px;height:40px;border-radius:10px;background:#FEF2F2;color:#EF4444;display:flex;align-items:center;justify-content:center;font-size:1.1rem;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <div style="font-weight:600;font-size:0.85rem;">Incidents</div>
                    <div style="font-size:0.65rem;color:var(--gray-400);">View and manage incidents</div>
                </div>
            </a>
            
            <a href="result-verification.php" class="quick-action-card" style="display:flex;align-items:center;gap:12px;padding:16px 20px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);transition:var(--transition);">
                <div style="width:40px;height:40px;border-radius:10px;background:#FFFBEB;color:#F59E0B;display:flex;align-items:center;justify-content:center;font-size:1.1rem;">
                    <i class="fas fa-check-double"></i>
                </div>
                <div>
                    <div style="font-weight:600;font-size:0.85rem;">Results</div>
                    <div style="font-size:0.65rem;color:var(--gray-400);">Verify election results</div>
                </div>
            </a>
            
            <a href="broadcasts.php" class="quick-action-card" style="display:flex;align-items:center;gap:12px;padding:16px 20px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);transition:var(--transition);">
                <div style="width:40px;height:40px;border-radius:10px;background:#F5F3FF;color:#8B5CF6;display:flex;align-items:center;justify-content:center;font-size:1.1rem;">
                    <i class="fas fa-bullhorn"></i>
                </div>
                <div>
                    <div style="font-weight:600;font-size:0.85rem;">Broadcast</div>
                    <div style="font-size:0.65rem;color:var(--gray-400);">Send messages</div>
                </div>
            </a>
        </div>

        <!-- Recent Activity -->
        <div style="background:white;border-radius:var(--radius);padding:20px;border:1px solid var(--gray-200);">
            <h4 style="font-size:0.9rem;font-weight:600;margin:0 0 16px;padding-bottom:12px;border-bottom:1px solid var(--gray-200);">
                <i class="fas fa-clock" style="color:var(--primary);margin-right:6px;"></i>
                Recent Activity
            </h4>
            <?php
            // Fetch recent activity
            $activities = [];
            try {
                $stmt = $db->prepare("
                    SELECT * FROM activity_logs 
                    WHERE tenant_id = ? AND (state_id = ? OR state_id IS NULL)
                    ORDER BY created_at DESC 
                    LIMIT 10
                ");
                $stmt->execute([$tenant_id, $state_id]);
                $activities = $stmt->fetchAll();
            } catch (Exception $e) {}
            ?>
            
            <?php if (count($activities) > 0): ?>
                <?php foreach ($activities as $activity): ?>
                    <div style="display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid var(--gray-100);">
                        <div style="width:32px;height:32px;border-radius:50%;background:var(--primary-light);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:0.7rem;">
                            <i class="fas fa-circle"></i>
                        </div>
                        <div style="flex:1;">
                            <div style="font-size:0.8rem;color:var(--gray-700);"><?php echo htmlspecialchars($activity['description'] ?? 'Activity'); ?></div>
                            <div style="font-size:0.65rem;color:var(--gray-400);"><?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align:center;padding:20px;color:var(--gray-400);">
                    <i class="fas fa-clock" style="font-size:2rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                    <p>No recent activity</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
// ============================================================
// PRELOADER
// ============================================================
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});

// ============================================================
// SIDEBAR TOGGLE
// ============================================================
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

// ============================================================
// SIDEBAR DROPDOWNS
// ============================================================
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

// ============================================================
// PROFILE DROPDOWN
// ============================================================
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