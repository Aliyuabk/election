<?php
// ============================================================
// NATIONAL COORDINATOR - COORDINATOR ACTIVITY LOG
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

// Get parameters
$coordinator_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$state_id = isset($_GET['state']) ? intval($_GET['state']) : 0;

$db = getDB();

// ============================================================
// FETCH COORDINATOR DATA
// ============================================================
$coordinator = null;
$coordinator_name = '';
$back_url = 'monitor-states.php';

if ($coordinator_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT id, full_name, email, role_id, jurisdiction_type, jurisdiction_id
            FROM users 
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$coordinator_id, $tenant_id]);
        $coordinator = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($coordinator) {
            $coordinator_name = $coordinator['full_name'];
            $back_url = "coordinator-view.php?id=$coordinator_id";
        }
    } catch (Exception $e) {
        error_log("Coordinator fetch error: " . $e->getMessage());
    }
} elseif ($state_id > 0) {
    try {
        $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
        $stmt->execute([$state_id]);
        $state_name = $stmt->fetchColumn();
        $back_url = "view-state.php?id=$state_id";
    } catch (Exception $e) {
        error_log("State fetch error: " . $e->getMessage());
    }
}

// ============================================================
// FETCH ACTIVITY LOGS
// ============================================================
$activities = [];
$total_activities = 0;

try {
    $where_clauses = ['tenant_id = ?'];
    $params = [$tenant_id];
    
    if ($coordinator_id > 0) {
        $where_clauses[] = 'user_id = ?';
        $params[] = $coordinator_id;
    } elseif ($state_id > 0) {
        $where_clauses[] = "entity_type IN ('state', 'lga', 'ward', 'pu')";
        $where_clauses[] = "entity_id IN (
            SELECT id FROM lgas WHERE state_id = ?
            UNION SELECT id FROM wards WHERE lga_id IN (SELECT id FROM lgas WHERE state_id = ?)
            UNION SELECT id FROM polling_units WHERE ward_id IN (SELECT id FROM wards WHERE lga_id IN (SELECT id FROM lgas WHERE state_id = ?))
        )";
        $params[] = $state_id;
        $params[] = $state_id;
        $params[] = $state_id;
    }
    
    $where_sql = implode(' AND ', $where_clauses);
    
    $stmt = $db->prepare("
        SELECT 
            a.*,
            u.full_name as user_name
        FROM activity_logs a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE $where_sql
        ORDER BY a.created_at DESC
        LIMIT 50
    ");
    $stmt->execute($params);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_activities = count($activities);
    
} catch (Exception $e) {
    error_log("Activity logs error: " . $e->getMessage());
}

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Activity Log';
$page_subtitle = $coordinator_name ? "Coordinator: $coordinator_name" : 'All Activities';
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
                <a href="<?php echo $back_url; ?>" style="text-decoration:none;color:var(--gray-500);">
                    <?php echo $coordinator_name ? htmlspecialchars($coordinator_name) : 'Back'; ?>
                </a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);">Activity Log</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <?php echo $coordinator_name ? htmlspecialchars($coordinator_name) : 'Activity Log'; ?>
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-clock"></i> 
                        <?php echo number_format($total_activities); ?> activities recorded
                    </p>
                </div>
                <a href="<?php echo $back_url; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Activities List -->
        <div style="background:white;border-radius:var(--radius);overflow:hidden;border:1px solid var(--gray-200);">
            <div style="padding:12px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <h4 style="font-size:0.9rem;font-weight:600;margin:0;">
                    <i class="fas fa-list" style="color:var(--primary);margin-right:6px;"></i>
                    Activity Log
                </h4>
                <span style="font-size:0.75rem;color:var(--gray-500);"><?php echo number_format($total_activities); ?> entries</span>
            </div>
            
            <?php if (count($activities) > 0): ?>
                <div style="overflow-y:auto;max-height:600px;">
                    <?php 
                    $type_colors = [
                        'login' => '#3B82F6',
                        'logout' => '#6B7280',
                        'user_created' => '#10B981',
                        'user_suspended' => '#EF4444',
                        'user_activated' => '#10B981',
                        'user_deleted' => '#EF4444',
                        'user_updated' => '#8B5CF6',
                        'user_archived' => '#F59E0B',
                        'election_created' => '#0D9488',
                        'election_updated' => '#0D9488',
                        'election_deleted' => '#EF4444',
                        'broadcast_created' => '#F59E0B',
                        'broadcast_resent' => '#F59E0B',
                        'backup_created' => '#8B5CF6',
                        'settings_changed' => '#6B7280',
                        'password_reset' => '#F59E0B',
                        'password_change' => '#F59E0B',
                        'profile_updated' => '#3B82F6',
                        '2fa_enabled' => '#10B981',
                        '2fa_disabled' => '#EF4444',
                        'state_added' => '#10B981',
                        'state_deleted' => '#EF4444',
                        'lga_added' => '#10B981',
                        'ward_added' => '#10B981',
                        'pu_added' => '#10B981',
                    ];
                    $type_icons = [
                        'login' => 'fa-sign-in-alt',
                        'logout' => 'fa-sign-out-alt',
                        'user_created' => 'fa-user-plus',
                        'user_suspended' => 'fa-user-slash',
                        'user_activated' => 'fa-user-check',
                        'user_deleted' => 'fa-user-times',
                        'user_updated' => 'fa-user-edit',
                        'user_archived' => 'fa-archive',
                        'election_created' => 'fa-vote-yea',
                        'election_updated' => 'fa-edit',
                        'election_deleted' => 'fa-trash',
                        'broadcast_created' => 'fa-bullhorn',
                        'broadcast_resent' => 'fa-redo',
                        'backup_created' => 'fa-database',
                        'settings_changed' => 'fa-cog',
                        'password_reset' => 'fa-key',
                        'password_change' => 'fa-key',
                        'profile_updated' => 'fa-user-edit',
                        '2fa_enabled' => 'fa-shield-alt',
                        '2fa_disabled' => 'fa-shield-alt',
                        'state_added' => 'fa-flag',
                        'state_deleted' => 'fa-flag',
                        'lga_added' => 'fa-map-marker-alt',
                        'ward_added' => 'fa-layer-group',
                        'pu_added' => 'fa-flag-checkered',
                    ];
                    ?>
                    <?php foreach ($activities as $activity): 
                        $type_color = $type_colors[$activity['activity_type'] ?? ''] ?? '#6B7280';
                        $type_icon = $type_icons[$activity['activity_type'] ?? ''] ?? 'fa-circle';
                    ?>
                        <div style="display:flex;align-items:flex-start;gap:14px;padding:12px 20px;border-bottom:1px solid var(--gray-100);transition:var(--transition);hover:background:var(--gray-50);">
                            <div style="width:40px;height:40px;border-radius:50%;background:<?php echo $type_color; ?>20;display:flex;align-items:center;justify-content:center;color:<?php echo $type_color; ?>;flex-shrink:0;">
                                <i class="fas <?php echo $type_icon; ?>" style="font-size:0.9rem;"></i>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:4px;">
                                    <div>
                                        <span style="font-weight:600;font-size:0.85rem;color:var(--gray-800);">
                                            <?php echo htmlspecialchars($activity['user_name'] ?? 'System'); ?>
                                        </span>
                                        <span style="font-size:0.7rem;color:var(--gray-400);margin:0 6px;">•</span>
                                        <span style="display:inline-block;padding:1px 10px;border-radius:10px;font-size:0.6rem;font-weight:600;background:<?php echo $type_color; ?>20;color:<?php echo $type_color; ?>;">
                                            <?php echo ucfirst(str_replace('_', ' ', $activity['activity_type'] ?? 'unknown')); ?>
                                        </span>
                                    </div>
                                    <span style="font-size:0.65rem;color:var(--gray-400);white-space:nowrap;">
                                        <i class="fas fa-clock"></i> <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                    </span>
                                </div>
                                <div style="font-size:0.8rem;color:var(--gray-600);margin-top:2px;">
                                    <?php echo htmlspecialchars($activity['description']); ?>
                                </div>
                                <?php if (!empty($activity['entity_type']) && !empty($activity['entity_id'])): ?>
                                    <div style="font-size:0.65rem;color:var(--gray-400);margin-top:2px;">
                                        <i class="fas fa-tag"></i> 
                                        <?php echo ucfirst($activity['entity_type']); ?> ID: <?php echo $activity['entity_id']; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($activity['ip_address'])): ?>
                                    <div style="font-size:0.6rem;color:var(--gray-400);margin-top:1px;">
                                        <i class="fas fa-network-wired"></i> <?php echo htmlspecialchars($activity['ip_address']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="padding:40px;text-align:center;color:var(--gray-500);">
                    <i class="fas fa-search" style="font-size:2rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                    <p>No activities found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<style>
.btn-secondary:hover { background: var(--gray-200); transform: translateY(-1px); }
div[style*="hover:background:var(--gray-50);"]:hover { background: var(--gray-50); }

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