<?php
// ============================================================
// STATE COORDINATOR - VIEW STATE COORDINATORS
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
// FETCH COORDINATORS
// ============================================================
$coordinators = [];
$total_coordinators = 0;

try {
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
            CASE 
                WHEN u.jurisdiction_type = 'lga' THEN (SELECT name FROM lgas WHERE id = u.jurisdiction_id)
                WHEN u.jurisdiction_type = 'ward' THEN (SELECT name FROM wards WHERE id = u.jurisdiction_id)
                WHEN u.jurisdiction_type = 'pu' THEN (SELECT name FROM polling_units WHERE id = u.jurisdiction_id)
                ELSE 'State'
            END as jurisdiction_name
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.tenant_id = ? 
        AND r.level IN ('state', 'lga', 'ward', 'pu_agent')
        AND u.jurisdiction_id IN (SELECT id FROM lgas WHERE state_id = ?)
        AND (u.deleted_at IS NULL OR u.deleted_at = '0000-00-00 00:00:00')
        ORDER BY FIELD(r.level, 'state', 'lga', 'ward', 'pu_agent'), u.full_name ASC
    ");
    $stmt->execute([$tenant_id, $state_id]);
    $coordinators = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_coordinators = count($coordinators);
    
} catch (Exception $e) {
    error_log("State Coordinators Error: " . $e->getMessage());
}

// ============================================================
// GROUP COORDINATORS BY ROLE LEVEL
// ============================================================
$grouped = [
    'state' => [],
    'lga' => [],
    'ward' => [],
    'pu_agent' => []
];

foreach ($coordinators as $coord) {
    $level = $coord['role_level'] ?? 'pu_agent';
    if (isset($grouped[$level])) {
        $grouped[$level][] = $coord;
    }
}

// ============================================================
// CALCULATE STATISTICS
// ============================================================
$stats = [
    'total' => $total_coordinators,
    'state' => count($grouped['state']),
    'lga' => count($grouped['lga']),
    'ward' => count($grouped['ward']),
    'pu_agent' => count($grouped['pu_agent']),
    'active' => 0,
    'pending' => 0,
    'suspended' => 0
];

foreach ($coordinators as $coord) {
    $status = $coord['status'] ?? '';
    if ($status === 'active') $stats['active']++;
    elseif ($status === 'pending') $stats['pending']++;
    elseif ($status === 'suspended') $stats['suspended']++;
}

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'State Coordinators';
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
                <span style="font-weight:600;color:var(--gray-800);">Coordinators</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <i class="fas fa-user-tie" style="color:var(--primary);"></i>
                        State Coordinators
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-users"></i> 
                        <?php echo number_format($total_coordinators); ?> coordinators • <?php echo htmlspecialchars($state_name); ?>
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="coordinators-create.php?state=<?php echo $state_id; ?>&level=state" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
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
                <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
                <div class="stat-label">Total Coordinators</div>
                <div class="stat-change">All levels</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-user-check"></i></div>
                <div class="stat-number"><?php echo number_format($stats['active']); ?></div>
                <div class="stat-label">Active</div>
                <div class="stat-change up"><i class="fas fa-check-circle"></i> Active staff</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo number_format($stats['pending']); ?></div>
                <div class="stat-label">Pending</div>
                <div class="stat-change down"><i class="fas fa-hourglass-half"></i> Awaiting</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-user-slash"></i></div>
                <div class="stat-number"><?php echo number_format($stats['suspended']); ?></div>
                <div class="stat-label">Suspended</div>
                <div class="stat-change down"><i class="fas fa-times-circle"></i> Inactive</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-layer-group"></i></div>
                <div class="stat-number"><?php echo number_format($stats['lga']); ?></div>
                <div class="stat-label">LGA Coordinators</div>
                <div class="stat-change"><i class="fas fa-map-marker-alt"></i> Supervisors</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-user"></i></div>
                <div class="stat-number"><?php echo number_format($stats['pu_agent']); ?></div>
                <div class="stat-label">PU Agents</div>
                <div class="stat-change"><i class="fas fa-flag-checkered"></i> Field staff</div>
            </div>
        </div>

        <!-- Coordinators by Role -->
        <?php foreach ($grouped as $level => $coords): 
            if (empty($coords)) continue;
            $level_label = ucfirst(str_replace('_', ' ', $level));
            $icon = $level === 'state' ? 'fa-flag' : ($level === 'lga' ? 'fa-map-marker-alt' : ($level === 'ward' ? 'fa-layer-group' : 'fa-user'));
            $color = $level === 'state' ? 'var(--primary)' : ($level === 'lga' ? 'var(--secondary)' : ($level === 'ward' ? 'var(--warning)' : 'var(--danger)'));
        ?>
            <div style="background:white;border-radius:var(--radius);overflow:hidden;border:1px solid var(--gray-200);margin-bottom:20px;">
                <div style="padding:12px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                    <h4 style="font-size:0.9rem;font-weight:600;margin:0;">
                        <i class="fas <?php echo $icon; ?>" style="color:<?php echo $color; ?>;margin-right:6px;"></i>
                        <?php echo $level_label; ?> Coordinators
                    </h4>
                    <span style="font-size:0.75rem;color:var(--gray-500);"><?php echo count($coords); ?> coordinators</span>
                </div>
                
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;padding:12px;">
                    <?php foreach ($coords as $coord): ?>
                        <div style="background:var(--gray-50);border-radius:10px;padding:12px 14px;border:1px solid var(--gray-200);transition:var(--transition);hover:transform:translateY(-2px);hover:box-shadow:var(--shadow-hover);">
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                                <div style="width:40px;height:40px;border-radius:50%;background:<?php echo $coord['status'] === 'active' ? 'var(--primary)' : ($coord['status'] === 'pending' ? 'var(--warning)' : 'var(--danger)'); ?>;color:white;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.85rem;flex-shrink:0;">
                                    <?php echo strtoupper(substr($coord['first_name'] ?? 'U', 0, 1) . substr($coord['last_name'] ?? 'N', 0, 1)); ?>
                                </div>
                                <div style="flex:1;min-width:0;">
                                    <div style="font-weight:600;font-size:0.85rem;"><?php echo htmlspecialchars($coord['full_name'] ?? 'Unknown'); ?></div>
                                    <div style="font-size:0.65rem;color:var(--gray-500);">
                                        <span class="badge <?php echo $coord['status'] === 'active' ? 'badge-success' : ($coord['status'] === 'pending' ? 'badge-warning' : 'badge-danger'); ?>">
                                            <?php echo ucfirst($coord['status'] ?? 'Unknown'); ?>
                                        </span>
                                        • <?php echo htmlspecialchars($coord['role_name'] ?? 'Unknown'); ?>
                                    </div>
                                    <?php if (!empty($coord['jurisdiction_name']) && $level !== 'state'): ?>
                                        <div style="font-size:0.6rem;color:var(--gray-400);">
                                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($coord['jurisdiction_name']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="font-size:0.7rem;color:var(--gray-500);">
                                <div><i class="fas fa-envelope" style="width:14px;"></i> <?php echo htmlspecialchars($coord['email'] ?? 'N/A'); ?></div>
                                <div><i class="fas fa-phone" style="width:14px;"></i> <?php echo htmlspecialchars($coord['phone'] ?? 'N/A'); ?></div>
                                <div><i class="fas fa-clock" style="width:14px;"></i> Last login: <?php echo ($coord['last_login_at'] ?? null) ? date('M j, Y g:i A', strtotime($coord['last_login_at'])) : 'Never'; ?></div>
                            </div>
                            <div style="display:flex;gap:4px;margin-top:8px;flex-wrap:wrap;">
                                <a href="coordinator-view.php?id=<?php echo $coord['id']; ?>" class="btn-sm" style="padding:3px 10px;border-radius:4px;background:var(--primary);color:white;text-decoration:none;font-size:0.65rem;">View</a>
                                <a href="coordinator-edit.php?id=<?php echo $coord['id']; ?>" class="btn-sm" style="padding:3px 10px;border-radius:4px;background:var(--gray-200);color:var(--gray-700);text-decoration:none;font-size:0.65rem;">Edit</a>
                                <?php if ($coord['status'] === 'active'): ?>
                                    <a href="coordinator-suspend.php?id=<?php echo $coord['id']; ?>" class="btn-sm" style="padding:3px 10px;border-radius:4px;background:#FEE2E2;color:#991B1B;text-decoration:none;font-size:0.65rem;" onclick="return confirm('Suspend this coordinator?')">Suspend</a>
                                <?php elseif ($coord['status'] === 'suspended'): ?>
                                    <a href="coordinator-activate.php?id=<?php echo $coord['id']; ?>" class="btn-sm" style="padding:3px 10px;border-radius:4px;background:#D1FAE5;color:#065F46;text-decoration:none;font-size:0.65rem;" onclick="return confirm('Activate this coordinator?')">Activate</a>
                                <?php endif; ?>
                                <a href="coordinator-reset-password.php?id=<?php echo $coord['id']; ?>" class="btn-sm" style="padding:3px 10px;border-radius:4px;background:#FEF3C7;color:#92400E;text-decoration:none;font-size:0.65rem;">Reset Password</a>
                                <a href="coordinator-activity.php?id=<?php echo $coord['id']; ?>" class="btn-sm" style="padding:3px 10px;border-radius:4px;background:#8B5CF6;color:white;text-decoration:none;font-size:0.65rem;">Activity</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Quick Actions -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
            <a href="coordinator-activity.php?state=<?php echo $state_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-clock" style="color:var(--primary);"></i>
                <span>View Activity Log</span>
            </a>
            <a href="coordinator-performance.php?state=<?php echo $state_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-chart-bar" style="color:var(--secondary);"></i>
                <span>Performance Report</span>
            </a>
            <a href="broadcasts-create.php?state=<?php echo $state_id; ?>&target=coordinators" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-bullhorn" style="color:var(--warning);"></i>
                <span>Broadcast to Coordinators</span>
            </a>
        </div>
    </div>
</main>

<style>
.badge-success { background: #D1FAE5; color: #065F46; padding: 2px 10px; border-radius: 12px; font-size: 0.65rem; font-weight: 600; }
.badge-warning { background: #FEF3C7; color: #92400E; padding: 2px 10px; border-radius: 12px; font-size: 0.65rem; font-weight: 600; }
.badge-danger { background: #FEE2E2; color: #991B1B; padding: 2px 10px; border-radius: 12px; font-size: 0.65rem; font-weight: 600; }
.btn-sm:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
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
    div[style*="grid-template-columns:repeat(auto-fill,minmax(280px,1fr))"] {
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