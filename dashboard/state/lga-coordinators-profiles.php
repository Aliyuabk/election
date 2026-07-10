<?php
// ============================================================
// STATE COORDINATOR - LGA COORDINATOR PROFILE
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'state') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'State Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$state_id = SessionManager::get('state_id');

if (empty($state_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT state_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['state_id'])) {
            $state_id = $user['state_id'];
            SessionManager::set('state_id', $state_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching state_id: " . $e->getMessage());
    }
}

$db = getDB();
$coordinator_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($coordinator_id <= 0) {
    header('Location: lga-coordinators.php');
    exit();
}

// Fetch coordinator details
$coordinator = null;
try {
    $stmt = $db->prepare("
        SELECT 
            u.*,
            r.name as role_name,
            r.level as role_level,
            l.name as lga_name,
            l.code as lga_code,
            s.name as state_name,
            (SELECT COUNT(*) FROM results_ec8a ra 
             JOIN polling_units pu ON ra.pu_id = pu.id 
             JOIN wards w ON pu.ward_id = w.id 
             WHERE w.lga_id = u.lga_id AND ra.status IN ('pending', 'verified', 'approved')) as verified_results,
            (SELECT COUNT(*) FROM polling_units pu 
             JOIN wards w ON pu.ward_id = w.id 
             WHERE w.lga_id = u.lga_id AND pu.is_active = 1) as total_pus,
            (SELECT COUNT(*) FROM incidents i WHERE i.lga_id = u.lga_id AND i.status IN ('reported', 'acknowledged', 'investigating')) as pending_incidents,
            (SELECT COUNT(*) FROM user_sessions us WHERE us.user_id = u.id AND us.is_active = 1 AND us.last_activity_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)) as is_online,
            (SELECT COUNT(*) FROM user_sessions us WHERE us.user_id = u.id AND us.is_active = 1) as active_sessions
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN lgas l ON u.lga_id = l.id
        LEFT JOIN states s ON u.state_id = s.id
        WHERE u.id = ? AND u.deleted_at IS NULL
    ");
    $stmt->execute([$coordinator_id]);
    $coordinator = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching coordinator: " . $e->getMessage());
}

if (!$coordinator) {
    header('Location: lga-coordinators.php');
    exit();
}

$full_name = $coordinator['first_name'] . ' ' . $coordinator['last_name'];
$initials = strtoupper(substr($coordinator['first_name'], 0, 1) . substr($coordinator['last_name'], 0, 1));
$reporting_rate = $coordinator['total_pus'] > 0 ? round(($coordinator['verified_results'] / $coordinator['total_pus']) * 100, 1) : 0;

$page_title = 'Coordinator Profile';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.profile-header {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 28px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
}

.profile-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.8rem;
    flex-shrink: 0;
}

.profile-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

.profile-info h2 {
    font-size: 1.2rem;
    font-weight: 700;
    margin: 0;
}

.profile-info .subtitle {
    color: var(--gray-500);
    font-size: 0.85rem;
}

.profile-info .badges {
    display: flex;
    gap: 8px;
    margin-top: 6px;
    flex-wrap: wrap;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.6rem;
    padding: 3px 12px;
    border-radius: 12px;
    font-weight: 600;
}

.status-badge .dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    display: inline-block;
}

.status-badge.active { background: #ECFDF5; color: #065F46; }
.status-badge.active .dot { background: #10B981; }
.status-badge.suspended { background: #FEF2F2; color: #991B1B; }
.status-badge.suspended .dot { background: #EF4444; }
.status-badge.pending { background: #FFFBEB; color: #92400E; }
.status-badge.pending .dot { background: #F59E0B; }

.status-badge.online { background: #ECFDF5; color: #065F46; }
.status-badge.online .dot { background: #10B981; animation: pulse-dot 1.5s ease-in-out infinite; }
.status-badge.offline { background: #F3F4F6; color: #6B7280; }
.status-badge.offline .dot { background: #9CA3AF; }

@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(0.8); }
}

.profile-actions {
    margin-left: auto;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.profile-actions a {
    padding: 6px 16px;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 500;
    text-decoration: none;
    transition: var(--transition);
}

.profile-actions .btn-edit { background: var(--primary); color: white; }
.profile-actions .btn-edit:hover { background: var(--primary-dark); }
.profile-actions .btn-activity { background: var(--gray-100); color: var(--gray-700); }
.profile-actions .btn-activity:hover { background: var(--gray-200); }
.profile-actions .btn-reset { background: #FFFBEB; color: #D97706; }
.profile-actions .btn-reset:hover { background: #FEF3C7; }

.profile-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.profile-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 18px 20px;
}

.profile-card h4 {
    font-size: 0.8rem;
    font-weight: 600;
    margin: 0 0 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-700);
}

.profile-card .detail-row {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    font-size: 0.8rem;
    border-bottom: 1px solid var(--gray-50);
}

.profile-card .detail-row:last-child {
    border-bottom: none;
}

.profile-card .detail-row .label {
    color: var(--gray-500);
}

.profile-card .detail-row .value {
    font-weight: 500;
    color: var(--gray-800);
}

.stats-mini {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 10px;
    margin-top: 4px;
}

.stats-mini .stat-item {
    text-align: center;
    padding: 12px 8px;
    background: var(--gray-50);
    border-radius: 8px;
}

.stats-mini .stat-item .number {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--gray-800);
}

.stats-mini .stat-item .label {
    font-size: 0.6rem;
    color: var(--gray-500);
}

.progress-section {
    margin: 10px 0;
}

.progress-section .progress-bar {
    height: 6px;
    background: var(--gray-200);
    border-radius: 4px;
    overflow: hidden;
}

.progress-section .progress-bar .fill {
    height: 100%;
    background: var(--primary);
    border-radius: 4px;
    transition: width 0.8s ease;
}

.progress-section .progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 0.65rem;
    color: var(--gray-500);
    margin-top: 4px;
}

@media (max-width: 768px) {
    .profile-grid {
        grid-template-columns: 1fr;
    }
    .profile-header {
        flex-direction: column;
        text-align: center;
        padding: 20px;
    }
    .profile-actions {
        margin-left: 0;
        width: 100%;
        justify-content: center;
    }
    .stats-mini {
        grid-template-columns: 1fr 1fr 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar">
                <?php if (!empty($coordinator['photograph_url'])): ?>
                    <img src="<?php echo htmlspecialchars($coordinator['photograph_url']); ?>" alt="<?php echo htmlspecialchars($full_name); ?>" />
                <?php else: ?>
                    <?php echo $initials; ?>
                <?php endif; ?>
            </div>
            
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($full_name); ?></h2>
                <div class="subtitle">
                    <i class="fas fa-map-marker-alt"></i> 
                    <?php echo htmlspecialchars($coordinator['lga_name'] ?? 'N/A'); ?> LGA
                    <span style="margin:0 6px;">•</span>
                    <?php echo htmlspecialchars($coordinator['state_name'] ?? 'N/A'); ?> State
                </div>
                <div class="badges">
                    <span class="status-badge <?php echo $coordinator['status']; ?>">
                        <span class="dot"></span> <?php echo ucfirst($coordinator['status']); ?>
                    </span>
                    <span class="status-badge <?php echo $coordinator['is_online'] > 0 ? 'online' : 'offline'; ?>">
                        <span class="dot"></span> <?php echo $coordinator['is_online'] > 0 ? 'Online' : 'Offline'; ?>
                    </span>
                    <?php if ($coordinator['active_sessions'] > 0): ?>
                        <span class="status-badge" style="background:#EFF6FF;color:#1E40AF;">
                            <i class="fas fa-desktop"></i> <?php echo $coordinator['active_sessions']; ?> session(s)
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="profile-actions">
                <a href="lga-coordinators-activity.php?id=<?php echo $coordinator['id']; ?>" class="btn-activity">
                    <i class="fas fa-clock"></i> Activity
                </a>
                <a href="lga-coordinators-reset-password.php?id=<?php echo $coordinator['id']; ?>" class="btn-reset">
                    <i class="fas fa-key"></i> Reset Password
                </a>
            </div>
        </div>

        <!-- Profile Grid -->
        <div class="profile-grid">
            <!-- Personal Information -->
            <div class="profile-card">
                <h4><i class="fas fa-user" style="margin-right:6px;color:var(--primary);"></i> Personal Information</h4>
                <div class="detail-row">
                    <span class="label">Full Name</span>
                    <span class="value"><?php echo htmlspecialchars($full_name); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">User Code</span>
                    <span class="value"><?php echo htmlspecialchars($coordinator['user_code']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Role</span>
                    <span class="value"><?php echo htmlspecialchars($coordinator['role_name'] ?? 'LGA Coordinator'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Email</span>
                    <span class="value"><?php echo htmlspecialchars($coordinator['email'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Phone</span>
                    <span class="value"><?php echo htmlspecialchars($coordinator['phone'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Gender</span>
                    <span class="value"><?php echo ucfirst($coordinator['gender'] ?? 'Not specified'); ?></span>
                </div>
                <?php if (!empty($coordinator['date_of_birth'])): ?>
                <div class="detail-row">
                    <span class="label">Date of Birth</span>
                    <span class="value"><?php echo date('M j, Y', strtotime($coordinator['date_of_birth'])); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- LGA & Performance -->
            <div class="profile-card">
                <h4><i class="fas fa-chart-bar" style="margin-right:6px;color:var(--primary);"></i> LGA Performance</h4>
                <div class="detail-row">
                    <span class="label">LGA</span>
                    <span class="value"><?php echo htmlspecialchars($coordinator['lga_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">LGA Code</span>
                    <span class="value"><?php echo htmlspecialchars($coordinator['lga_code'] ?? 'N/A'); ?></span>
                </div>
                
                <div class="stats-mini">
                    <div class="stat-item">
                        <div class="number"><?php echo number_format($coordinator['total_pus']); ?></div>
                        <div class="label">Total PUs</div>
                    </div>
                    <div class="stat-item">
                        <div class="number"><?php echo number_format($coordinator['verified_results']); ?></div>
                        <div class="label">Verified</div>
                    </div>
                    <div class="stat-item">
                        <div class="number" style="color:<?php echo $coordinator['pending_incidents'] > 0 ? '#EF4444' : '#10B981'; ?>;">
                            <?php echo number_format($coordinator['pending_incidents']); ?>
                        </div>
                        <div class="label">Incidents</div>
                    </div>
                </div>

                <div class="progress-section">
                    <div class="progress-bar">
                        <div class="fill" style="width: <?php echo $reporting_rate; ?>%;"></div>
                    </div>
                    <div class="progress-label">
                        <span>Reporting Rate</span>
                        <span><?php echo $reporting_rate; ?>%</span>
                    </div>
                </div>
            </div>

            <!-- Account Information -->
            <div class="profile-card">
                <h4><i class="fas fa-shield-alt" style="margin-right:6px;color:var(--primary);"></i> Account Information</h4>
                <div class="detail-row">
                    <span class="label">Status</span>
                    <span class="value">
                        <span class="status-badge <?php echo $coordinator['status']; ?>" style="font-size:0.7rem;">
                            <span class="dot"></span> <?php echo ucfirst($coordinator['status']); ?>
                        </span>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="label">Email Verified</span>
                    <span class="value">
                        <?php echo $coordinator['email_verified_at'] ? '<span style="color:#10B981;"><i class="fas fa-check-circle"></i> Verified</span>' : '<span style="color:#F59E0B;"><i class="fas fa-clock"></i> Pending</span>'; ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="label">2FA Enabled</span>
                    <span class="value">
                        <?php echo $coordinator['two_factor_enabled'] ? '<span style="color:#10B981;"><i class="fas fa-check-circle"></i> Yes</span>' : '<span style="color:#94A3B8;">No</span>'; ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="label">Created</span>
                    <span class="value"><?php echo date('M j, Y g:i A', strtotime($coordinator['created_at'])); ?></span>
                </div>
                <?php if (!empty($coordinator['last_login_at'])): ?>
                <div class="detail-row">
                    <span class="label">Last Login</span>
                    <span class="value"><?php echo date('M j, Y g:i A', strtotime($coordinator['last_login_at'])); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Last IP</span>
                    <span class="value"><?php echo htmlspecialchars($coordinator['last_login_ip'] ?? 'N/A'); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="profile-card">
                <h4><i class="fas fa-bolt" style="margin-right:6px;color:var(--primary);"></i> Quick Actions</h4>
                <div style="display:grid;gap:6px;">
                    <a href="lga-coordinators-activity.php?id=<?php echo $coordinator['id']; ?>" style="display:flex;align-items:center;gap:10px;padding:8px 14px;background:var(--gray-50);border-radius:8px;text-decoration:none;color:var(--gray-700);transition:var(--transition);">
                        <i class="fas fa-clock" style="color:var(--primary);"></i>
                        <span>View Activity Log</span>
                        <i class="fas fa-chevron-right" style="margin-left:auto;color:var(--gray-400);font-size:0.6rem;"></i>
                    </a>
                    <a href="lga-coordinators-reset-password.php?id=<?php echo $coordinator['id']; ?>" style="display:flex;align-items:center;gap:10px;padding:8px 14px;background:var(--gray-50);border-radius:8px;text-decoration:none;color:var(--gray-700);transition:var(--transition);">
                        <i class="fas fa-key" style="color:#D97706;"></i>
                        <span>Reset Password</span>
                        <i class="fas fa-chevron-right" style="margin-left:auto;color:var(--gray-400);font-size:0.6rem;"></i>
                    </a>
                    <?php if ($coordinator['status'] === 'active'): ?>
                    <a href="lga-coordinators-suspend.php?id=<?php echo $coordinator['id']; ?>" style="display:flex;align-items:center;gap:10px;padding:8px 14px;background:var(--gray-50);border-radius:8px;text-decoration:none;color:#DC2626;transition:var(--transition);" onclick="return confirm('Are you sure you want to suspend this coordinator?')">
                        <i class="fas fa-pause"></i>
                        <span>Suspend Coordinator</span>
                        <i class="fas fa-chevron-right" style="margin-left:auto;color:var(--gray-400);font-size:0.6rem;"></i>
                    </a>
                    <?php else: ?>
                    <a href="lga-coordinators-activate.php?id=<?php echo $coordinator['id']; ?>" style="display:flex;align-items:center;gap:10px;padding:8px 14px;background:var(--gray-50);border-radius:8px;text-decoration:none;color:#10B981;transition:var(--transition);">
                        <i class="fas fa-play"></i>
                        <span>Activate Coordinator</span>
                        <i class="fas fa-chevron-right" style="margin-left:auto;color:var(--gray-400);font-size:0.6rem;"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Same sidebar scripts as index.php
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