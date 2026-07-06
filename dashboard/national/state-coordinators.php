<?php
// ============================================================
// NATIONAL COORDINATOR - VIEW STATE COORDINATORS
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

// Get state ID from URL - check both GET and POST
$state_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($state_id <= 0 && isset($_POST['state_id'])) {
    $state_id = intval($_POST['state_id']);
}

// Debug log
error_log("=== state-coordinators.php ===");
error_log("GET: " . print_r($_GET, true));
error_log("State ID: " . $state_id);

// If no state ID, try to get from session
if ($state_id <= 0) {
    $state_id = SessionManager::get('state_id', 0);
    error_log("State ID from session: " . $state_id);
}

// If still invalid, redirect
if ($state_id <= 0) {
    error_log("Redirecting due to invalid state ID");
    header('Location: monitor-states.php?error=invalid_state&debug=' . urlencode(print_r($_GET, true)));
    exit();
}

$db = getDB();

// ============================================================
// VERIFY STATE EXISTS
// ============================================================
$state_name = '';
try {
    $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
    $stmt->execute([$state_id]);
    $state_name = $stmt->fetchColumn();
    error_log("State name found: " . $state_name);
    
    if (!$state_name) {
        error_log("State not found with ID: " . $state_id);
        header('Location: monitor-states.php?error=state_not_found');
        exit();
    }
} catch (Exception $e) {
    error_log("State Coordinators Error: " . $e->getMessage());
    header('Location: monitor-states.php?error=database_error');
    exit();
}

// ============================================================
// FETCH COORDINATORS
// ============================================================
$state_coordinators = [];
$lga_coordinators = [];

try {
    // State Coordinators
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
            s.name as jurisdiction_name
        FROM users u
        JOIN roles r ON u.role_id = r.id
        JOIN states s ON u.jurisdiction_id = s.id
        WHERE u.tenant_id = ? 
        AND r.level = 'state'
        AND u.jurisdiction_id = ?
        AND (u.deleted_at IS NULL OR u.deleted_at = '0000-00-00 00:00:00')
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$tenant_id, $state_id]);
    $state_coordinators = $stmt->fetchAll();
    error_log("State coordinators found: " . count($state_coordinators));

    // LGA Coordinators
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
            l.name as jurisdiction_name
        FROM users u
        JOIN roles r ON u.role_id = r.id
        JOIN lgas l ON u.jurisdiction_id = l.id
        WHERE u.tenant_id = ? 
        AND r.level = 'lga'
        AND u.jurisdiction_id IN (SELECT id FROM lgas WHERE state_id = ?)
        AND (u.deleted_at IS NULL OR u.deleted_at = '0000-00-00 00:00:00')
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$tenant_id, $state_id]);
    $lga_coordinators = $stmt->fetchAll();
    error_log("LGA coordinators found: " . count($lga_coordinators));

} catch (Exception $e) {
    error_log("State Coordinators Fetch Error: " . $e->getMessage());
}

// ============================================================
// CALCULATE STATISTICS
// ============================================================
$total_state_coordinators = count($state_coordinators);
$active_state_coordinators = 0;
$total_lga_coordinators = count($lga_coordinators);
$active_lga_coordinators = 0;

foreach ($state_coordinators as $coord) {
    if (($coord['status'] ?? '') === 'active') $active_state_coordinators++;
}
foreach ($lga_coordinators as $coord) {
    if (($coord['status'] ?? '') === 'active') $active_lga_coordinators++;
}

// Check for success message
$success_message = '';
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = 'Coordinator created successfully!';
}

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'State Coordinators';
$page_subtitle = $state_name;
?>
<!-- Rest of HTML remains the same -->

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
                <span style="font-weight:600;color:var(--gray-800);">Coordinators</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <?php echo htmlspecialchars($state_name); ?> Coordinators
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-user-tie"></i> 
                        <?php echo $total_state_coordinators; ?> State Coordinators • 
                        <?php echo $total_lga_coordinators; ?> LGA Coordinators
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="view-state.php?id=<?php echo $state_id; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    <a href="coordinators-create.php?state=<?php echo $state_id; ?>&level=state" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-user-plus"></i> Add Coordinator
                    </a>
                </div>
            </div>
        </div>

        <!-- Success Message -->
        <?php if ($success_message): ?>
            <div style="background:#D1FAE5;color:#065F46;padding:12px 20px;border-radius:10px;margin-bottom:20px;border:1px solid #A7F3D0;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-check-circle" style="font-size:1.2rem;"></i>
                <span><?php echo htmlspecialchars($success_message); ?></span>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-user-tie"></i></div>
                <div class="stat-number"><?php echo number_format($total_state_coordinators); ?></div>
                <div class="stat-label">State Coordinators</div>
                <div class="stat-change up"><i class="fas fa-check-circle"></i> <?php echo $active_state_coordinators; ?> active</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo number_format($total_lga_coordinators); ?></div>
                <div class="stat-label">LGA Coordinators</div>
                <div class="stat-change up"><i class="fas fa-check-circle"></i> <?php echo $active_lga_coordinators; ?> active</div>
            </div>
        </div>

        <!-- State Coordinators Section -->
        <div style="background:white;border-radius:var(--radius);overflow:hidden;border:1px solid var(--gray-200);margin-bottom:20px;">
            <div style="padding:12px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <h4 style="font-size:0.9rem;font-weight:600;margin:0;">
                    <i class="fas fa-user-tie" style="color:var(--primary);margin-right:6px;"></i>
                    State Coordinators
                </h4>
                <span style="font-size:0.75rem;color:var(--gray-500);"><?php echo $total_state_coordinators; ?> coordinators</span>
            </div>
            
            <?php if (count($state_coordinators) > 0): ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;padding:16px;">
                    <?php foreach ($state_coordinators as $coord): ?>
                        <div style="background:var(--gray-50);border-radius:12px;padding:16px;border:1px solid var(--gray-200);transition:var(--transition);hover:transform:translateY(-2px);hover:box-shadow:var(--shadow-hover);">
                            <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">
                                <div style="width:48px;height:48px;border-radius:50%;background:var(--primary);color:white;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1rem;flex-shrink:0;">
                                    <?php echo strtoupper(substr($coord['first_name'] ?? 'U', 0, 1) . substr($coord['last_name'] ?? 'N', 0, 1)); ?>
                                </div>
                                <div style="flex:1;min-width:0;">
                                    <div style="font-weight:600;font-size:0.9rem;"><?php echo htmlspecialchars($coord['full_name'] ?? 'Unknown'); ?></div>
                                    <div style="font-size:0.7rem;color:var(--gray-500);">
                                        <span class="badge <?php echo ($coord['status'] ?? '') === 'active' ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo ucfirst($coord['status'] ?? 'Unknown'); ?>
                                        </span>
                                        • <?php echo htmlspecialchars($coord['role_name'] ?? 'Unknown'); ?>
                                    </div>
                                </div>
                            </div>
                            <div style="font-size:0.75rem;color:var(--gray-500);">
                                <div><i class="fas fa-envelope" style="width:16px;"></i> <?php echo htmlspecialchars($coord['email'] ?? 'N/A'); ?></div>
                                <div><i class="fas fa-phone" style="width:16px;"></i> <?php echo htmlspecialchars($coord['phone'] ?? 'N/A'); ?></div>
                                <div><i class="fas fa-map-marker-alt" style="width:16px;"></i> <?php echo htmlspecialchars($coord['jurisdiction_name'] ?? 'N/A'); ?></div>
                                <div><i class="fas fa-clock" style="width:16px;"></i> Last login: <?php echo ($coord['last_login_at'] ?? null) ? date('M j, Y g:i A', strtotime($coord['last_login_at'])) : 'Never'; ?></div>
                            </div>
                            <div style="display:flex;gap:6px;margin-top:10px;flex-wrap:wrap;">
                                <a href="coordinator-view.php?id=<?php echo $coord['id']; ?>" class="btn-sm" style="padding:4px 12px;border-radius:6px;background:var(--primary);color:white;text-decoration:none;font-size:0.7rem;">View</a>
                                <a href="coordinator-edit.php?id=<?php echo $coord['id']; ?>" class="btn-sm" style="padding:4px 12px;border-radius:6px;background:var(--gray-200);color:var(--gray-700);text-decoration:none;font-size:0.7rem;">Edit</a>
                                <a href="coordinator-suspend.php?id=<?php echo $coord['id']; ?>" class="btn-sm" style="padding:4px 12px;border-radius:6px;background:#FEE2E2;color:#991B1B;text-decoration:none;font-size:0.7rem;" onclick="return confirm('Suspend this coordinator?')">Suspend</a>
                                <a href="coordinator-reset-password.php?id=<?php echo $coord['id']; ?>" class="btn-sm" style="padding:4px 12px;border-radius:6px;background:#FEF3C7;color:#92400E;text-decoration:none;font-size:0.7rem;">Reset Password</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="padding:30px;text-align:center;color:var(--gray-500);">
                    <i class="fas fa-user-tie" style="font-size:1.5rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                    No state coordinators assigned yet
                    <div style="margin-top:12px;">
                        <a href="coordinators-create.php?state=<?php echo $state_id; ?>&level=state" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;">
                            <i class="fas fa-user-plus"></i> Add State Coordinator
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- LGA Coordinators Section -->
        <div style="background:white;border-radius:var(--radius);overflow:hidden;border:1px solid var(--gray-200);margin-bottom:20px;">
            <div style="padding:12px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <h4 style="font-size:0.9rem;font-weight:600;margin:0;">
                    <i class="fas fa-users" style="color:var(--secondary);margin-right:6px;"></i>
                    LGA Coordinators
                </h4>
                <span style="font-size:0.75rem;color:var(--gray-500);"><?php echo $total_lga_coordinators; ?> coordinators</span>
            </div>
            
            <?php if (count($lga_coordinators) > 0): ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;padding:16px;">
                    <?php foreach ($lga_coordinators as $coord): ?>
                        <div style="background:var(--gray-50);border-radius:12px;padding:16px;border:1px solid var(--gray-200);transition:var(--transition);hover:transform:translateY(-2px);hover:box-shadow:var(--shadow-hover);">
                            <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">
                                <div style="width:48px;height:48px;border-radius:50%;background:var(--secondary);color:white;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1rem;flex-shrink:0;">
                                    <?php echo strtoupper(substr($coord['first_name'] ?? 'U', 0, 1) . substr($coord['last_name'] ?? 'N', 0, 1)); ?>
                                </div>
                                <div style="flex:1;min-width:0;">
                                    <div style="font-weight:600;font-size:0.9rem;"><?php echo htmlspecialchars($coord['full_name'] ?? 'Unknown'); ?></div>
                                    <div style="font-size:0.7rem;color:var(--gray-500);">
                                        <span class="badge <?php echo ($coord['status'] ?? '') === 'active' ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo ucfirst($coord['status'] ?? 'Unknown'); ?>
                                        </span>
                                        • <?php echo htmlspecialchars($coord['role_name'] ?? 'Unknown'); ?>
                                    </div>
                                </div>
                            </div>
                            <div style="font-size:0.75rem;color:var(--gray-500);">
                                <div><i class="fas fa-envelope" style="width:16px;"></i> <?php echo htmlspecialchars($coord['email'] ?? 'N/A'); ?></div>
                                <div><i class="fas fa-phone" style="width:16px;"></i> <?php echo htmlspecialchars($coord['phone'] ?? 'N/A'); ?></div>
                                <div><i class="fas fa-map-marker-alt" style="width:16px;"></i> <?php echo htmlspecialchars($coord['jurisdiction_name'] ?? 'N/A'); ?></div>
                                <div><i class="fas fa-clock" style="width:16px;"></i> Last login: <?php echo ($coord['last_login_at'] ?? null) ? date('M j, Y g:i A', strtotime($coord['last_login_at'])) : 'Never'; ?></div>
                            </div>
                            <div style="display:flex;gap:6px;margin-top:10px;flex-wrap:wrap;">
                                <a href="coordinator-view.php?id=<?php echo $coord['id']; ?>" class="btn-sm" style="padding:4px 12px;border-radius:6px;background:var(--primary);color:white;text-decoration:none;font-size:0.7rem;">View</a>
                                <a href="coordinator-edit.php?id=<?php echo $coord['id']; ?>" class="btn-sm" style="padding:4px 12px;border-radius:6px;background:var(--gray-200);color:var(--gray-700);text-decoration:none;font-size:0.7rem;">Edit</a>
                                <a href="coordinator-suspend.php?id=<?php echo $coord['id']; ?>" class="btn-sm" style="padding:4px 12px;border-radius:6px;background:#FEE2E2;color:#991B1B;text-decoration:none;font-size:0.7rem;" onclick="return confirm('Suspend this coordinator?')">Suspend</a>
                                <a href="coordinator-reset-password.php?id=<?php echo $coord['id']; ?>" class="btn-sm" style="padding:4px 12px;border-radius:6px;background:#FEF3C7;color:#92400E;text-decoration:none;font-size:0.7rem;">Reset Password</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="padding:30px;text-align:center;color:var(--gray-500);">
                    <i class="fas fa-users" style="font-size:1.5rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                    No LGA coordinators assigned yet
                    <div style="margin-top:12px;">
                        <a href="coordinators-create.php?state=<?php echo $state_id; ?>&level=lga" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;">
                            <i class="fas fa-user-plus"></i> Add LGA Coordinator
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

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
.badge-danger { background: #FEE2E2; color: #991B1B; padding: 2px 10px; border-radius: 12px; font-size: 0.65rem; font-weight: 600; }
.btn-sm:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.quick-action-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-hover); border-color: var(--primary); }
.btn-secondary:hover { background: var(--gray-200); transform: translateY(-1px); }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: 1fr 1fr; }
    div[style*="grid-template-columns:repeat(auto-fill,minmax(300px,1fr))"] {
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