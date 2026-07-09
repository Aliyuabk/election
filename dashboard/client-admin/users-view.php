<?php
// ============================================================
// USER VIEW - CLIENT ADMIN
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

// Check role - only client_admin can access this page
if (SessionManager::get('role_level') !== 'client_admin') {
    header('Location: ../client-admin/');
    exit();
}

// Get database connection
$db = getDB();

// Get user info
$user_id = SessionManager::get('user_id');
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');
$tenant_id = SessionManager::get('tenant_id');

// ============================================================
// GET USER ID
// ============================================================
$view_user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($view_user_id <= 0) {
    header('Location: users.php');
    exit();
}

// ============================================================
// FETCH USER DETAILS
// ============================================================
$user = null;
try {
    $stmt = $db->prepare("
        SELECT u.*, r.name as role_name, r.level as role_level
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.id = ? AND u.tenant_id = ? AND u.deleted_at IS NULL
    ");
    $stmt->execute([$view_user_id, $tenant_id]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    // Continue
}

if (!$user) {
    header('Location: users.php');
    exit();
}

// ============================================================
// FETCH USER ACTIVITY LOG
// ============================================================
$activities = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM activity_logs 
        WHERE user_id = ? AND tenant_id = ?
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$view_user_id, $tenant_id]);
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
    $stmt->execute([$view_user_id]);
    $sessions = $stmt->fetchAll();
} catch (Exception $e) {
    // Continue
}

// Get avatar color
$avatar_colors = ['blue', 'green', 'purple', 'orange', 'red', 'pink', 'teal'];
$color_idx = ($view_user_id ?? 0) % count($avatar_colors);
$avatar_color = $avatar_colors[$color_idx];

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       USER VIEW - CLIENT ADMIN STYLES
       ============================================================ */
    
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 20px;
    }
    .page-header h2 {
        font-size: 1.3rem;
        font-weight: 700;
    }
    .page-header h2 small {
        font-size: 0.8rem;
        font-weight: 400;
        color: var(--gray-500);
        display: block;
        margin-top: 2px;
    }
    
    .btn-primary {
        padding: 8px 18px;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.25);
    }
    .btn-outline {
        padding: 8px 16px;
        background: transparent;
        color: var(--gray-600);
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        font-weight: 500;
        font-size: 0.82rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-outline:hover {
        background: var(--gray-50);
        border-color: var(--gray-300);
    }
    
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
    
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.65rem;
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
    .detail-card .card-title i {
        color: var(--primary);
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
    
    @media (max-width: 992px) {
        .detail-grid {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 768px) {
        .profile-header {
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .profile-header .user-actions {
            margin-left: 0;
            width: 100%;
            justify-content: center;
        }
        .detail-row {
            flex-direction: column;
            padding: 6px 0;
        }
        .detail-row .label {
            min-width: auto;
            font-size: 0.75rem;
        }
        .detail-card {
            padding: 16px;
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .profile-header .user-avatar-lg {
            width: 64px;
            height: 64px;
            font-size: 1.5rem;
        }
    }
    @media (max-width: 480px) {
        .profile-header {
            padding: 16px;
        }
        .profile-header .user-avatar-lg {
            width: 56px;
            height: 56px;
            font-size: 1.2rem;
        }
        .profile-header .user-info h2 {
            font-size: 1.1rem;
        }
        .session-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
        }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-user-circle" style="color:var(--primary);margin-right:8px;"></i> User Details
                    <small>View complete user information</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="users-edit.php?id=<?php echo $view_user_id; ?>" class="btn-primary">
                    <i class="fas fa-edit"></i> Edit User
                </a>
                <a href="users.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Users
                </a>
            </div>
        </div>

        <!-- Profile Header -->
        <div class="profile-header">
            <div class="user-avatar-lg <?php echo $avatar_color; ?>">
                <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
            </div>
            <div class="user-info">
                <h2><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                <div class="user-meta">
                    <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></span>
                    <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></span>
                    <span><i class="fas fa-code"></i> <?php echo htmlspecialchars($user['user_code']); ?></span>
                    <span>
                        <span class="badge-status <?php echo $user['status']; ?>">
                            <span class="dot"></span>
                            <?php echo ucfirst($user['status']); ?>
                        </span>
                    </span>
                </div>
            </div>
            <div class="user-actions">
                <?php if ($user['status'] === 'active'): ?>
                    <form method="POST" action="users.php" style="display:inline;">
                        <input type="hidden" name="action" value="suspend">
                        <input type="hidden" name="id" value="<?php echo $view_user_id; ?>">
                        <button type="submit" class="btn-outline" style="padding:8px 16px;font-size:0.8rem;color:var(--danger);border-color:#FECACA;" onclick="return confirm('Suspend this user?')">
                            <i class="fas fa-pause"></i> Suspend
                        </button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="users.php" style="display:inline;">
                        <input type="hidden" name="action" value="activate">
                        <input type="hidden" name="id" value="<?php echo $view_user_id; ?>">
                        <button type="submit" class="btn-outline" style="padding:8px 16px;font-size:0.8rem;color:var(--secondary);border-color:#A7F3D0;" onclick="return confirm('Activate this user?')">
                            <i class="fas fa-play"></i> Activate
                        </button>
                    </form>
                <?php endif; ?>
                <form method="POST" action="users.php" style="display:inline;">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="id" value="<?php echo $view_user_id; ?>">
                    <button type="submit" class="btn-outline" style="padding:8px 16px;font-size:0.8rem;color:var(--warning);border-color:#FDE68A;" onclick="return confirm('Reset password for this user?')">
                        <i class="fas fa-key"></i> Reset Password
                    </button>
                </form>
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
                            <span class="badge-role">
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
                        <span class="label">Gender</span>
                        <span class="value"><?php echo ucfirst($user['gender'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Date of Birth</span>
                        <span class="value"><?php echo !empty($user['date_of_birth']) ? date('M j, Y', strtotime($user['date_of_birth'])) : 'N/A'; ?></span>
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