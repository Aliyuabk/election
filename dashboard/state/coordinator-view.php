<?php
// ============================================================
// STATE COORDINATOR - VIEW COORDINATOR DETAILS
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

// Get coordinator ID
$coordinator_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($coordinator_id <= 0) {
    header('Location: monitor-lgas.php?error=invalid_coordinator');
    exit();
}

$db = getDB();

// ============================================================
// FETCH COORDINATOR DATA
// ============================================================
$coordinator = null;
$back_url = 'state-coordinators.php';

try {
    $stmt = $db->prepare("
        SELECT 
            u.*,
            r.name as role_name,
            r.level as role_level,
            r.permissions_json,
            CASE 
                WHEN u.jurisdiction_type = 'state' THEN (SELECT name FROM states WHERE id = u.jurisdiction_id)
                WHEN u.jurisdiction_type = 'lga' THEN (SELECT name FROM lgas WHERE id = u.jurisdiction_id)
                WHEN u.jurisdiction_type = 'ward' THEN (SELECT name FROM wards WHERE id = u.jurisdiction_id)
                WHEN u.jurisdiction_type = 'pu' THEN (SELECT name FROM polling_units WHERE id = u.jurisdiction_id)
                ELSE 'Unknown'
            END as jurisdiction_name,
            CASE 
                WHEN u.jurisdiction_type = 'lga' THEN (SELECT s.name FROM states s JOIN lgas l ON l.state_id = s.id WHERE l.id = u.jurisdiction_id)
                WHEN u.jurisdiction_type = 'ward' THEN (SELECT s.name FROM states s JOIN lgas l ON l.state_id = s.id JOIN wards w ON w.lga_id = l.id WHERE w.id = u.jurisdiction_id)
                WHEN u.jurisdiction_type = 'pu' THEN (SELECT s.name FROM states s JOIN lgas l ON l.state_id = s.id JOIN wards w ON w.lga_id = l.id JOIN polling_units pu ON pu.ward_id = w.id WHERE pu.id = u.jurisdiction_id)
                ELSE 'State'
            END as parent_location
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.id = ? AND u.tenant_id = ?
    ");
    $stmt->execute([$coordinator_id, $tenant_id]);
    $coordinator = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$coordinator) {
        header('Location: monitor-lgas.php?error=coordinator_not_found');
        exit();
    }
    
    // Determine back URL based on role level
    if ($coordinator['role_level'] === 'state') {
        $back_url = "state-coordinators.php";
    } elseif ($coordinator['role_level'] === 'lga') {
        $back_url = "lga-coordinators.php?id=" . $coordinator['jurisdiction_id'];
    } elseif ($coordinator['role_level'] === 'ward') {
        $back_url = "ward-dashboard.php?id=" . $coordinator['jurisdiction_id'];
    } elseif ($coordinator['role_level'] === 'pu_agent') {
        $back_url = "pu-agents.php?pu=" . $coordinator['jurisdiction_id'];
    } else {
        $back_url = "state-coordinators.php";
    }
    
} catch (Exception $e) {
    error_log("Coordinator View Error: " . $e->getMessage());
    header('Location: monitor-lgas.php?error=database_error');
    exit();
}

// ============================================================
// FETCH COORDINATOR STATISTICS
// ============================================================
$stats = [
    'submissions' => 0,
    'verified_submissions' => 0,
    'incidents_reported' => 0,
    'checkins' => 0,
    'last_activity' => null,
    'assigned_pus' => 0,
    'assigned_wards' => 0,
    'assigned_lgas' => 0
];

try {
    // Get submissions count
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified
        FROM results_ec8a 
        WHERE agent_id = ? AND tenant_id = ?
    ");
    $stmt->execute([$coordinator_id, $tenant_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['submissions'] = $result['total'] ?? 0;
    $stats['verified_submissions'] = $result['verified'] ?? 0;
    
    // Get incidents reported
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM incidents WHERE reporter_id = ? AND tenant_id = ?");
    $stmt->execute([$coordinator_id, $tenant_id]);
    $stats['incidents_reported'] = $stmt->fetchColumn() ?: 0;
    
    // Get checkins
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM agent_checkins WHERE agent_id = ? AND tenant_id = ?");
    $stmt->execute([$coordinator_id, $tenant_id]);
    $stats['checkins'] = $stmt->fetchColumn() ?: 0;
    
    // Get last activity
    $stmt = $db->prepare("SELECT created_at FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$coordinator_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $stats['last_activity'] = $result['created_at'];
    }
    
    // Get assigned PUs
    if ($coordinator['role_level'] === 'pu_agent') {
        $stats['assigned_pus'] = 1;
    } elseif ($coordinator['role_level'] === 'ward') {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM polling_units WHERE ward_id = ? AND is_active = 1");
        $stmt->execute([$coordinator['jurisdiction_id']]);
        $stats['assigned_pus'] = $stmt->fetchColumn() ?: 0;
    } elseif ($coordinator['role_level'] === 'lga') {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM wards WHERE lga_id = ? AND is_active = 1");
        $stmt->execute([$coordinator['jurisdiction_id']]);
        $stats['assigned_wards'] = $stmt->fetchColumn() ?: 0;
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM polling_units WHERE ward_id IN (SELECT id FROM wards WHERE lga_id = ?) AND is_active = 1");
        $stmt->execute([$coordinator['jurisdiction_id']]);
        $stats['assigned_pus'] = $stmt->fetchColumn() ?: 0;
    }
    
} catch (Exception $e) {
    error_log("Stats fetch error: " . $e->getMessage());
}

// ============================================================
// FETCH RECENT ACTIVITIES
// ============================================================
$recent_activities = [];
try {
    $stmt = $db->prepare("
        SELECT a.*
        FROM activity_logs a
        WHERE a.user_id = ?
        ORDER BY a.created_at DESC
        LIMIT 15
    ");
    $stmt->execute([$coordinator_id]);
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_activities = [];
}

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Coordinator Details';
$page_subtitle = $coordinator['full_name'] ?? 'Coordinator';
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
                <a href="<?php echo $back_url; ?>" style="text-decoration:none;color:var(--gray-500);">Coordinators</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);">View Coordinator</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;display:flex;align-items:center;gap:12px;">
                        <span><?php echo htmlspecialchars($coordinator['full_name']); ?></span>
                        <span style="font-size:0.7rem;background:<?php echo ($coordinator['status'] ?? '') === 'active' ? '#10B981' : '#6B7280'; ?>;color:white;padding:2px 12px;border-radius:20px;font-weight:500;">
                            <?php echo ucfirst($coordinator['status'] ?? 'Unknown'); ?>
                        </span>
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-user-tag"></i> 
                        <?php echo htmlspecialchars($coordinator['role_name']); ?> • 
                        <i class="fas fa-map-marker-alt"></i> 
                        <?php echo htmlspecialchars($coordinator['jurisdiction_name'] ?? 'Unknown'); ?>
                        <?php if (!empty($coordinator['parent_location'])): ?>
                            • <?php echo htmlspecialchars($coordinator['parent_location']); ?>
                        <?php endif; ?>
                    </p>
                    <p style="color:var(--gray-400);font-size:0.75rem;margin:2px 0 0;">
                        <i class="fas fa-id-card"></i> User Code: <?php echo htmlspecialchars($coordinator['user_code']); ?>
                        • <i class="fas fa-calendar-alt"></i> Joined: <?php echo date('M j, Y', strtotime($coordinator['created_at'])); ?>
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="coordinator-edit.php?id=<?php echo $coordinator_id; ?>" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="<?php echo $back_url; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-file-alt"></i></div>
                <div class="stat-number"><?php echo number_format($stats['submissions']); ?></div>
                <div class="stat-label">Submissions</div>
                <div class="stat-change"><i class="fas fa-upload"></i> Total results</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['verified_submissions']); ?></div>
                <div class="stat-label">Verified</div>
                <div class="stat-change up"><i class="fas fa-check"></i> <?php echo $stats['submissions'] > 0 ? round(($stats['verified_submissions'] / $stats['submissions']) * 100) : 0; ?>%</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-sign-in-alt"></i></div>
                <div class="stat-number"><?php echo number_format($stats['checkins']); ?></div>
                <div class="stat-label">Check-ins</div>
                <div class="stat-change"><i class="fas fa-clock"></i> Total</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['incidents_reported']); ?></div>
                <div class="stat-label">Incidents</div>
                <div class="stat-change down"><i class="fas fa-flag"></i> Reported</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo $stats['last_activity'] ? date('M j', strtotime($stats['last_activity'])) : 'Never'; ?></div>
                <div class="stat-label">Last Activity</div>
                <div class="stat-change"><?php echo $stats['last_activity'] ? date('g:i A', strtotime($stats['last_activity'])) : '—'; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon teal"><i class="fas fa-flag-checkered"></i></div>
                <div class="stat-number"><?php echo number_format($stats['assigned_pus']); ?></div>
                <div class="stat-label">Assigned PUs</div>
                <div class="stat-change"><?php echo $stats['assigned_wards'] > 0 ? $stats['assigned_wards'] . ' wards' : ''; ?></div>
            </div>
        </div>

        <!-- Profile Details -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
            <!-- Left Column: Personal Info -->
            <div style="background:white;border-radius:var(--radius);padding:20px;border:1px solid var(--gray-200);">
                <h4 style="font-size:0.9rem;font-weight:600;margin:0 0 16px;padding-bottom:12px;border-bottom:1px solid var(--gray-200);">
                    <i class="fas fa-user" style="color:var(--primary);margin-right:6px;"></i>
                    Personal Information
                </h4>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <label style="display:block;font-size:0.65rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">First Name</label>
                        <div style="font-weight:500;font-size:0.9rem;"><?php echo htmlspecialchars($coordinator['first_name'] ?? 'N/A'); ?></div>
                    </div>
                    <div>
                        <label style="display:block;font-size:0.65rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">Last Name</label>
                        <div style="font-weight:500;font-size:0.9rem;"><?php echo htmlspecialchars($coordinator['last_name'] ?? 'N/A'); ?></div>
                    </div>
                    <div>
                        <label style="display:block;font-size:0.65rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">Email</label>
                        <div style="font-weight:500;font-size:0.85rem;">
                            <a href="mailto:<?php echo htmlspecialchars($coordinator['email'] ?? ''); ?>" style="color:var(--primary);text-decoration:none;">
                                <?php echo htmlspecialchars($coordinator['email'] ?? 'N/A'); ?>
                            </a>
                        </div>
                    </div>
                    <div>
                        <label style="display:block;font-size:0.65rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">Phone</label>
                        <div style="font-weight:500;font-size:0.85rem;">
                            <a href="tel:<?php echo htmlspecialchars($coordinator['phone'] ?? ''); ?>" style="color:var(--primary);text-decoration:none;">
                                <?php echo htmlspecialchars($coordinator['phone'] ?? 'N/A'); ?>
                            </a>
                        </div>
                    </div>
                    <div>
                        <label style="display:block;font-size:0.65rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">User Code</label>
                        <div style="font-weight:500;font-size:0.85rem;"><?php echo htmlspecialchars($coordinator['user_code'] ?? 'N/A'); ?></div>
                    </div>
                    <div>
                        <label style="display:block;font-size:0.65rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">Joined</label>
                        <div style="font-weight:500;font-size:0.85rem;"><?php echo date('M j, Y', strtotime($coordinator['created_at'])); ?></div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Role & Jurisdiction -->
            <div style="background:white;border-radius:var(--radius);padding:20px;border:1px solid var(--gray-200);">
                <h4 style="font-size:0.9rem;font-weight:600;margin:0 0 16px;padding-bottom:12px;border-bottom:1px solid var(--gray-200);">
                    <i class="fas fa-user-tie" style="color:var(--secondary);margin-right:6px;"></i>
                    Role & Jurisdiction
                </h4>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <label style="display:block;font-size:0.65rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">Role</label>
                        <div style="font-weight:500;font-size:0.9rem;">
                            <span class="badge" style="background:var(--primary)20;color:var(--primary);padding:2px 12px;border-radius:10px;">
                                <?php echo htmlspecialchars($coordinator['role_name'] ?? 'N/A'); ?>
                            </span>
                        </div>
                    </div>
                    <div>
                        <label style="display:block;font-size:0.65rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">Role Level</label>
                        <div style="font-weight:500;font-size:0.9rem;">
                            <?php echo ucfirst(htmlspecialchars($coordinator['role_level'] ?? 'N/A')); ?>
                        </div>
                    </div>
                    <div>
                        <label style="display:block;font-size:0.65rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">Jurisdiction</label>
                        <div style="font-weight:500;font-size:0.85rem;">
                            <?php echo htmlspecialchars($coordinator['jurisdiction_name'] ?? 'N/A'); ?>
                        </div>
                    </div>
                    <div>
                        <label style="display:block;font-size:0.65rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">Jurisdiction Type</label>
                        <div style="font-weight:500;font-size:0.85rem;">
                            <?php echo ucfirst(htmlspecialchars($coordinator['jurisdiction_type'] ?? 'N/A')); ?>
                        </div>
                    </div>
                    <div style="grid-column:1/-1;">
                        <label style="display:block;font-size:0.65rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">Parent Location</label>
                        <div style="font-weight:500;font-size:0.85rem;">
                            <?php echo htmlspecialchars($coordinator['parent_location'] ?? 'N/A'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div style="background:white;border-radius:var(--radius);padding:16px 20px;border:1px solid var(--gray-200);margin-bottom:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <h4 style="font-size:0.85rem;font-weight:600;margin:0;">
                    <i class="fas fa-clock" style="color:var(--primary);margin-right:6px;"></i>
                    Recent Activities
                </h4>
                <a href="coordinator-activity.php?id=<?php echo $coordinator_id; ?>" style="font-size:0.7rem;color:var(--primary);text-decoration:none;">View All →</a>
            </div>
            <?php if (count($recent_activities) > 0): ?>
                <div style="max-height:300px;overflow-y:auto;">
                    <?php foreach (array_slice($recent_activities, 0, 10) as $activity): ?>
                        <div style="display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid var(--gray-100);">
                            <div style="width:28px;height:28px;border-radius:50%;background:<?php echo strpos($activity['activity_type'] ?? '', 'login') !== false ? '#EFF6FF' : '#F1F5F9'; ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:<?php echo strpos($activity['activity_type'] ?? '', 'login') !== false ? '#3B82F6' : '#64748B'; ?>;">
                                <i class="fas <?php echo strpos($activity['activity_type'] ?? '', 'login') !== false ? 'fa-sign-in-alt' : 'fa-cog'; ?>" style="font-size:0.6rem;"></i>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="font-size:0.75rem;color:var(--gray-500);"><?php echo htmlspecialchars($activity['description'] ?? ''); ?></div>
                                <div style="font-size:0.6rem;color:var(--gray-400);"><?php echo date('M j, Y g:i A', strtotime($activity['created_at'] ?? 'now')); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="color:var(--gray-500);text-align:center;padding:16px 0;">No recent activities</p>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
            <a href="coordinator-edit.php?id=<?php echo $coordinator_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-edit" style="color:var(--primary);"></i>
                <span>Edit Profile</span>
            </a>
            <?php if ($coordinator['status'] === 'active'): ?>
                <a href="coordinator-suspend.php?id=<?php echo $coordinator_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);" onclick="return confirm('Suspend this coordinator?')">
                    <i class="fas fa-user-slash" style="color:var(--danger);"></i>
                    <span>Suspend</span>
                </a>
            <?php elseif ($coordinator['status'] === 'suspended'): ?>
                <a href="coordinator-activate.php?id=<?php echo $coordinator_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);" onclick="return confirm('Activate this coordinator?')">
                    <i class="fas fa-user-check" style="color:#10B981;"></i>
                    <span>Activate</span>
                </a>
            <?php endif; ?>
            <a href="coordinator-reset-password.php?id=<?php echo $coordinator_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-key" style="color:var(--warning);"></i>
                <span>Reset Password</span>
            </a>
            <a href="coordinator-activity.php?id=<?php echo $coordinator_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-clock" style="color:var(--secondary);"></i>
                <span>View Activity Log</span>
            </a>
        </div>
    </div>
</main>

<style>
.badge { padding: 2px 10px; border-radius: 10px; font-size: 0.75rem; font-weight: 600; }
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
    div[style*="grid-template-columns:1fr 1fr"] { grid-template-columns: 1fr !important; }
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