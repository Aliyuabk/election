<?php
// ============================================================
// USER VIEW - SUPER ADMINISTRATOR
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Start session and check login
SessionManager::start();

// Redirect if not logged in
if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

// Check role - only super_admin can access this page
if (SessionManager::get('role_level') !== 'super_admin') {
    header('Location: ../client-admin/');
    exit();
}

// Get database connection
$db = getDB();

// ============================================================
// GET USER ID
// ============================================================
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id <= 0) {
    header('Location: tenants.php');
    exit();
}

// ============================================================
// FETCH USER DETAILS
// ============================================================
$user = null;
try {
    $stmt = $db->prepare("
        SELECT 
            u.*,
            r.name as role_name,
            r.level as role_level,
            t.name as tenant_name,
            t.slug as tenant_slug,
            t.logo_url as tenant_logo
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        LEFT JOIN tenants t ON u.tenant_id = t.id
        WHERE u.id = ? AND u.deleted_at IS NULL
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    // Continue
}

if (!$user) {
    header('Location: tenants.php');
    exit();
}

// ============================================================
// FETCH USER ACTIVITY LOG
// ============================================================
$activities = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM activity_logs 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $activities = $stmt->fetchAll();
} catch (Exception $e) {
    // Continue
}

// ============================================================
// FETCH USER SESSIONS
// ============================================================
$sessions = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM user_sessions 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $sessions = $stmt->fetchAll();
} catch (Exception $e) {
    // Continue
}

// ============================================================
// HANDLE USER ACTIONS
// ============================================================
$action_result = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'suspend':
                $stmt = $db->prepare("UPDATE users SET status = 'suspended', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$user_id]);
                if ($stmt->rowCount() > 0) {
                    $action_result = ['success' => true, 'message' => 'User suspended successfully.'];
                    logActivity(SessionManager::get('user_id'), 'user_suspended', "Suspended user ID: $user_id");
                    // Refresh user data
                    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                }
                break;
            case 'activate':
                $stmt = $db->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$user_id]);
                if ($stmt->rowCount() > 0) {
                    $action_result = ['success' => true, 'message' => 'User activated successfully.'];
                    logActivity(SessionManager::get('user_id'), 'user_activated', "Activated user ID: $user_id");
                    // Refresh user data
                    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                }
                break;
            case 'reset_password':
                $new_password = generateRandomPassword(12);
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$password_hash, $user_id]);
                
                if ($stmt->rowCount() > 0) {
                    // Send email with new password
                    $subject = "Password Reset - " . APP_NAME;
                    $message = "Your password has been reset.\n\n";
                    $message .= "New Password: $new_password\n\n";
                    $message .= "Please change your password after logging in.\n\n";
                    $message .= "Login: " . APP_URL . "/auth/login.php\n\n";
                    $message .= "Best regards,\n" . APP_NAME . " Team";
                    sendEmail($user['email'], $subject, $message);
                    
                    $action_result = ['success' => true, 'message' => 'Password reset successfully. New password sent via email.'];
                    logActivity(SessionManager::get('user_id'), 'user_password_reset', "Reset password for user ID: $user_id");
                }
                break;
        }
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// Get user info
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       USER VIEW - PRO STYLES
       ============================================================ */
    
    .profile-header {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 24px 28px;
        display: flex;
        align-items: center;
        gap: 24px;
        flex-wrap: wrap;
        box-shadow: var(--shadow);
        margin-bottom: 24px;
        position: relative;
        overflow: hidden;
    }
    .profile-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
    }
    .profile-header .user-avatar-lg {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        font-weight: 700;
        color: white;
        flex-shrink: 0;
        border: 3px solid var(--gray-200);
    }
    .profile-header .user-avatar-lg.blue { background: #3B82F6; }
    .profile-header .user-avatar-lg.green { background: #10B981; }
    .profile-header .user-avatar-lg.purple { background: #8B5CF6; }
    .profile-header .user-avatar-lg.orange { background: #F59E0B; }
    .profile-header .user-avatar-lg.red { background: #EF4444; }
    .profile-header .user-avatar-lg.pink { background: #EC4899; }
    .profile-header .user-avatar-lg.teal { background: #14B8A6; }
    
    .profile-header .user-info h2 {
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 2px;
    }
    .profile-header .user-info .user-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        font-size: 0.82rem;
        color: var(--gray-500);
    }
    .profile-header .user-info .user-meta span {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .profile-header .user-actions {
        margin-left: auto;
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .detail-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
    }
    .detail-card {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 20px 24px;
        box-shadow: var(--shadow);
    }
    .detail-card .card-title {
        font-weight: 600;
        font-size: 0.95rem;
        margin-bottom: 16px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--gray-100);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .detail-row {
        display: flex;
        padding: 8px 0;
        border-bottom: 1px solid var(--gray-50);
        font-size: 0.85rem;
    }
    .detail-row:last-child {
        border-bottom: none;
    }
    .detail-row .label {
        font-weight: 500;
        color: var(--gray-500);
        min-width: 140px;
        flex-shrink: 0;
    }
    .detail-row .value {
        color: var(--gray-700);
        word-break: break-word;
    }
    .detail-row .value .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .badge-status .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
    }
    .badge-status.active { background: #ECFDF5; color: #065F46; }
    .badge-status.active .dot { background: #10B981; }
    .badge-status.suspended { background: #FEF2F2; color: #991B1B; }
    .badge-status.suspended .dot { background: #EF4444; }
    .badge-status.pending { background: #FFFBEB; color: #92400E; }
    .badge-status.pending .dot { background: #F59E0B; }

    .badge-role {
        display: inline-block;
        padding: 2px 14px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 500;
        background: #EFF6FF;
        color: #1E40AF;
    }
    .badge-role.super_admin { background: #F5F3FF; color: #5B21B6; }
    .badge-role.client_admin { background: #ECFDF5; color: #065F46; }
    .badge-role.national { background: #EFF6FF; color: #1E40AF; }

    .activity-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 10px 0;
        border-bottom: 1px solid var(--gray-100);
    }
    .activity-item:last-child { border-bottom: none; }
    .activity-item .act-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        flex-shrink: 0;
    }
    .activity-item .act-icon.login { background: #EFF6FF; color: #3B82F6; }
    .activity-item .act-icon.tenant { background: #ECFDF5; color: #10B981; }
    .activity-item .act-icon.user { background: #F5F3FF; color: #8B5CF6; }
    .activity-item .act-icon.system { background: #FEF2F2; color: #EF4444; }
    .activity-item .act-content { flex: 1; }
    .activity-item .act-content .act-desc { font-size: 0.82rem; }
    .activity-item .act-content .act-time { font-size: 0.7rem; color: var(--gray-400); }

    .session-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid var(--gray-100);
        font-size: 0.82rem;
    }
    .session-item:last-child { border-bottom: none; }
    .session-item .session-device { font-weight: 500; }
    .session-item .session-time { color: var(--gray-400); font-size: 0.75rem; }
    .session-item .session-status .online { color: var(--secondary); }
    .session-item .session-status .offline { color: var(--gray-400); }

    .empty-state-small {
        text-align: center;
        padding: 20px;
        color: var(--gray-400);
        font-size: 0.85rem;
    }
    .empty-state-small i {
        font-size: 1.6rem;
        display: block;
        margin-bottom: 6px;
        color: var(--gray-300);
    }

    .toast-container {
        position: fixed;
        top: 80px;
        right: 20px;
        z-index: 999;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .toast {
        padding: 14px 20px;
        border-radius: 10px;
        color: white;
        font-size: 0.85rem;
        font-weight: 500;
        box-shadow: var(--shadow-hover);
        animation: slideIn 0.3s ease;
        min-width: 280px;
        max-width: 400px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .toast.success { background: var(--secondary); }
    .toast.error { background: var(--danger); }
    @keyframes slideIn {
        from { transform: translateX(100px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }

    @media (max-width: 992px) {
        .detail-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 768px) {
        .profile-header { flex-direction: column; align-items: center; text-align: center; }
        .profile-header .user-actions { margin-left: 0; width: 100%; justify-content: center; }
        .detail-row { flex-direction: column; padding: 6px 0; }
        .detail-row .label { min-width: auto; font-size: 0.75rem; }
        .detail-card { padding: 16px; }
    }
    @media (max-width: 480px) {
        .profile-header .user-avatar-lg { width: 64px; height: 64px; font-size: 1.5rem; }
        .profile-header { padding: 16px; }
        .profile-header .user-info h2 { font-size: 1.1rem; }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Toast Messages -->
        <?php if (!empty($action_result['message'])): ?>
        <div class="toast-container" style="position:static;margin-bottom:16px;">
            <div class="toast <?php echo $action_result['success'] ? 'success' : 'error'; ?>" style="position:static;animation:none;max-width:100%;">
                <i class="fas <?php echo $action_result['success'] ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($action_result['message']); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Profile Header -->
        <?php 
        $avatar_colors = ['blue', 'green', 'purple', 'orange', 'red', 'pink', 'teal'];
        $color_idx = ($user['id'] ?? 0) % count($avatar_colors);
        $avatar_color = $avatar_colors[$color_idx];
        ?>
        <div class="profile-header">
            <div class="user-avatar-lg <?php echo $avatar_color; ?>">
                <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
            </div>
            <div class="user-info">
                <h2><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                <div class="user-meta">
                    <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></span>
                    <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></span>
                    <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($user['tenant_name'] ?? 'N/A'); ?></span>
                    <span><i class="fas fa-code"></i> <?php echo htmlspecialchars($user['user_code']); ?></span>
                </div>
            </div>
            <div class="user-actions">
                <a href="users-edit.php?id=<?php echo $user['id']; ?>" class="btn-primary" style="padding:8px 16px;font-size:0.8rem;">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <a href="tenants-users.php?id=<?php echo $user['tenant_id']; ?>" class="btn-outline" style="padding:8px 16px;font-size:0.8rem;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Detail Grid -->
        <div class="detail-grid">
            <!-- Left Column -->
            <div>
                <!-- User Details -->
                <div class="detail-card">
                    <div class="card-title">
                        <i class="fas fa-user" style="color:var(--primary);"></i> User Details
                    </div>
                    <div class="detail-row">
                        <span class="label">Full Name</span>
                        <span class="value"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">User Code</span>
                        <span class="value"><?php echo htmlspecialchars($user['user_code']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Email</span>
                        <span class="value"><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Phone</span>
                        <span class="value"><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Role</span>
                        <span class="value">
                            <span class="badge-role <?php echo $user['role_level'] ?? ''; ?>">
                                <?php echo htmlspecialchars($user['role_name'] ?? 'N/A'); ?>
                            </span>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Status</span>
                        <span class="value">
                            <span class="badge-status <?php echo $user['status']; ?>">
                                <span class="dot"></span>
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="label">2FA Enabled</span>
                        <span class="value">
                            <?php if ($user['two_factor_enabled']): ?>
                                <span style="color:var(--secondary);"><i class="fas fa-check-circle"></i> Yes</span>
                            <?php else: ?>
                                <span style="color:var(--gray-400);"><i class="fas fa-times-circle"></i> No</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Tenant</span>
                        <span class="value">
                            <a href="tenants-view.php?id=<?php echo $user['tenant_id']; ?>" style="color:var(--primary);text-decoration:none;">
                                <?php echo htmlspecialchars($user['tenant_name'] ?? 'N/A'); ?>
                            </a>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Joined</span>
                        <span class="value"><?php echo date('M j, Y g:i A', strtotime($user['created_at'])); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Last Login</span>
                        <span class="value">
                            <?php echo !empty($user['last_login_at']) ? date('M j, Y g:i A', strtotime($user['last_login_at'])) : 'Never'; ?>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Last Login IP</span>
                        <span class="value"><?php echo htmlspecialchars($user['last_login_ip'] ?? 'N/A'); ?></span>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="detail-card" style="margin-top:16px;">
                    <div class="card-title">
                        <i class="fas fa-bolt" style="color:var(--primary);"></i> Quick Actions
                    </div>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="reset_password">
                            <button type="submit" class="btn-primary" style="padding:8px 16px;font-size:0.8rem;background:var(--warning);" onclick="return confirm('Reset password for this user?')">
                                <i class="fas fa-key"></i> Reset Password
                            </button>
                        </form>
                        <?php if ($user['status'] === 'active'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="suspend">
                                <button type="submit" class="btn-primary" style="padding:8px 16px;font-size:0.8rem;background:var(--danger);" onclick="return confirm('Suspend this user?')">
                                    <i class="fas fa-pause"></i> Suspend
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="activate">
                                <button type="submit" class="btn-primary" style="padding:8px 16px;font-size:0.8rem;background:var(--secondary);" onclick="return confirm('Activate this user?')">
                                    <i class="fas fa-play"></i> Activate
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Recent Activity -->
                <div class="detail-card">
                    <div class="card-title">
                        <i class="fas fa-clock" style="color:var(--primary);"></i> Recent Activity
                    </div>
                    <?php if (count($activities) > 0): ?>
                        <?php foreach (array_slice($activities, 0, 8) as $activity): ?>
                            <?php 
                                $act_icon = 'system';
                                $act_icon_class = 'fa-cog';
                                if (strpos($activity['activity_type'] ?? '', 'login') !== false) {
                                    $act_icon = 'login';
                                    $act_icon_class = 'fa-sign-in-alt';
                                } elseif (strpos($activity['activity_type'] ?? '', 'tenant') !== false) {
                                    $act_icon = 'tenant';
                                    $act_icon_class = 'fa-building';
                                } elseif (strpos($activity['activity_type'] ?? '', 'user') !== false) {
                                    $act_icon = 'user';
                                    $act_icon_class = 'fa-user';
                                }
                            ?>
                            <div class="activity-item">
                                <div class="act-icon <?php echo $act_icon; ?>">
                                    <i class="fas <?php echo $act_icon_class; ?>"></i>
                                </div>
                                <div class="act-content">
                                    <div class="act-desc"><?php echo htmlspecialchars($activity['description'] ?? 'Activity'); ?></div>
                                    <div class="act-time"><?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state-small">
                            <i class="fas fa-clock"></i>
                            No recent activity
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sessions -->
                <div class="detail-card" style="margin-top:16px;">
                    <div class="card-title">
                        <i class="fas fa-laptop" style="color:var(--primary);"></i> Active Sessions
                    </div>
                    <?php if (count($sessions) > 0): ?>
                        <?php foreach ($sessions as $session): ?>
                            <div class="session-item">
                                <div>
                                    <div class="session-device">
                                        <i class="fas fa-<?php echo $session['device_type'] === 'web' ? 'desktop' : ($session['device_type'] === 'android' ? 'android' : 'apple'); ?>"></i>
                                        <?php echo ucfirst($session['device_type']); ?>
                                    </div>
                                    <div style="font-size:0.7rem;color:var(--gray-400);">
                                        <?php echo htmlspecialchars($session['ip_address'] ?? 'N/A'); ?>
                                    </div>
                                </div>
                                <div class="session-status">
                                    <?php if ($session['is_active']): ?>
                                        <span class="online"><i class="fas fa-circle" style="font-size:8px;"></i> Active</span>
                                    <?php else: ?>
                                        <span class="offline"><i class="fas fa-circle" style="font-size:8px;"></i> Inactive</span>
                                    <?php endif; ?>
                                    <div style="font-size:0.65rem;color:var(--gray-400);">
                                        <?php echo date('M j, Y', strtotime($session['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state-small">
                            <i class="fas fa-laptop"></i>
                            No sessions found
                        </div>
                    <?php endif; ?>
                </div>
            </div>
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

// ============================================================
// SEARCH
// ============================================================
var searchInput = document.getElementById('searchInput');
var searchResults = document.getElementById('searchResults');
var searchTimeout;

if (searchInput) {
    searchInput.addEventListener('input', function() {
        var query = this.value.trim();
        clearTimeout(searchTimeout);
        if (query.length < 2) {
            if (searchResults) searchResults.classList.remove('active');
            return;
        }
        searchTimeout = setTimeout(function() {
            fetch('search.php?q=' + encodeURIComponent(query))
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (searchResults) {
                        searchResults.innerHTML = '';
                        if (data && data.length > 0) {
                            data.forEach(function(item) {
                                var div = document.createElement('a');
                                div.className = 'result-item';
                                div.href = item.url || '#';
                                div.innerHTML = '<i class="fas ' + (item.icon || 'fa-file') + '"></i><span class="text-truncate">' + (item.label || item.name || '') + '</span><span class="result-type">' + ((item.type || '').charAt(0).toUpperCase() + (item.type || '').slice(1)) + '</span>';
                                searchResults.appendChild(div);
                            });
                            searchResults.classList.add('active');
                        } else {
                            searchResults.innerHTML = '<div style="padding:12px;text-align:center;color:var(--gray-500);font-size:0.8rem;"><i class="fas fa-search" style="display:block;font-size:1.2rem;margin-bottom:4px;"></i>No results found</div>';
                            searchResults.classList.add('active');
                        }
                    }
                })
                .catch(function() {});
        }, 300);
    });

    document.addEventListener('click', function(e) {
        var wrapper = document.querySelector('.search-wrapper');
        if (wrapper && !wrapper.contains(e.target) && searchResults) {
            searchResults.classList.remove('active');
        }
    });
}
</script>
</body>
</html>