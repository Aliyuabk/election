<?php
// ============================================================
// SENATORIAL COORDINATOR - PROFILE
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

if (SessionManager::get('role_level') !== 'senatorial') {
    header('Location: ../client-admin/');
    exit();
}

$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$db = getDB();

// Get user profile data
$user = null;
try {
    $stmt = $db->prepare("
        SELECT 
            u.*,
            r.name as role_name,
            r.level as role_level,
            s.name as state_name,
            l.name as lga_name,
            w.name as ward_name,
            pu.name as pu_name,
            sd.name as senatorial_name,
            fc.name as federal_constituency_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        LEFT JOIN states s ON u.state_id = s.id
        LEFT JOIN lgas l ON u.lga_id = l.id
        LEFT JOIN wards w ON u.ward_id = w.id
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        LEFT JOIN senatorial_districts sd ON u.senatorial_id = sd.id
        LEFT JOIN federal_constituencies fc ON u.federal_constituency_id = fc.id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    error_log("Error fetching profile: " . $e->getMessage());
}

if (!$user) {
    header('Location: ../dashboard/');
    exit();
}

// Get user statistics
$stats = [
    'broadcasts_sent' => 0,
    'incidents_reported' => 0,
    'results_verified' => 0,
    'total_activities' => 0
];

try {
    // Broadcasts sent by this user
    $stmt = $db->prepare("SELECT COUNT(*) FROM broadcasts WHERE sender_id = ?");
    $stmt->execute([$user_id]);
    $stats['broadcasts_sent'] = (int)$stmt->fetchColumn();
    
    // Incidents reported by this user
    $stmt = $db->prepare("SELECT COUNT(*) FROM incidents WHERE reporter_id = ?");
    $stmt->execute([$user_id]);
    $stats['incidents_reported'] = (int)$stmt->fetchColumn();
    
    // Results verified by this user
    $stmt = $db->prepare("SELECT COUNT(*) FROM results_ec8a WHERE verified_by = ?");
    $stmt->execute([$user_id]);
    $stats['results_verified'] = (int)$stmt->fetchColumn();
    
    // Total activities
    $stmt = $db->prepare("SELECT COUNT(*) FROM activity_logs WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stats['total_activities'] = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    error_log("Error fetching stats: " . $e->getMessage());
}

$page_title = 'My Profile';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.profile-header {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 30px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 30px;
    flex-wrap: wrap;
}
.profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    font-weight: 700;
    flex-shrink: 0;
    overflow: hidden;
}
.profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.profile-avatar .avatar-placeholder {
    text-transform: uppercase;
}
.profile-info h1 {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0 0 4px 0;
}
.profile-info .role-badge {
    display: inline-block;
    background: #DBEAFE;
    color: #2563EB;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}
.profile-info .email {
    color: var(--gray-500);
    font-size: 0.9rem;
    margin-top: 4px;
}
.profile-info .location {
    color: var(--gray-400);
    font-size: 0.8rem;
}
.profile-stats {
    margin-left: auto;
    display: flex;
    gap: 30px;
}
.profile-stats .stat-item {
    text-align: center;
}
.profile-stats .stat-number {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--gray-800);
}
.profile-stats .stat-label {
    font-size: 0.7rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.profile-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
.profile-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px;
}
.profile-card .card-title {
    font-size: 0.9rem;
    font-weight: 600;
    margin: 0 0 16px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.profile-card .card-title i {
    color: var(--primary);
}
.info-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid var(--gray-100);
}
.info-row:last-child {
    border-bottom: none;
}
.info-row .label {
    color: var(--gray-500);
    font-size: 0.8rem;
}
.info-row .value {
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--gray-800);
    text-align: right;
    max-width: 60%;
}
.info-row .value .badge-status {
    display: inline-block;
    padding: 2px 12px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
}
.badge-status.active { background: #D1FAE5; color: #059669; }
.badge-status.pending { background: #FEF3C7; color: #D97706; }
.badge-status.suspended { background: #FEE2E2; color: #DC2626; }

.quick-profile-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-top: 16px;
}
.quick-profile-action {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    background: var(--gray-50);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    text-decoration: none;
    color: var(--gray-700);
    font-size: 0.8rem;
    font-weight: 500;
    transition: var(--transition);
}
.quick-profile-action:hover {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}
.quick-profile-action i {
    font-size: 1rem;
    width: 20px;
    text-align: center;
}

@media (max-width: 768px) {
    .profile-header {
        flex-direction: column;
        text-align: center;
    }
    .profile-stats {
        margin-left: 0;
        width: 100%;
        justify-content: center;
    }
    .profile-grid {
        grid-template-columns: 1fr;
    }
    .quick-profile-actions {
        grid-template-columns: 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar">
                <?php if (!empty($user['photograph_url'])): ?>
                    <img src="<?php echo htmlspecialchars($user['photograph_url']); ?>" alt="<?php echo htmlspecialchars($user['full_name']); ?>">
                <?php else: ?>
                    <span class="avatar-placeholder">
                        <?php echo substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'R', 0, 1); ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?></h1>
                <span class="role-badge">
                    <i class="fas fa-user-tie"></i> 
                    <?php echo htmlspecialchars($user['role_name'] ?? 'Senatorial Coordinator'); ?>
                </span>
                <div class="email">
                    <i class="fas fa-envelope"></i> 
                    <?php echo htmlspecialchars($user['email'] ?? 'No email set'); ?>
                </div>
                <div class="location">
                    <i class="fas fa-map-marker-alt"></i> 
                    <?php 
                        $location = [];
                        if (!empty($user['senatorial_name'])) $location[] = $user['senatorial_name'];
                        if (!empty($user['state_name'])) $location[] = $user['state_name'];
                        echo htmlspecialchars(implode(' • ', $location));
                    ?>
                </div>
            </div>
            <div class="profile-stats">
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($stats['broadcasts_sent']); ?></div>
                    <div class="stat-label">Broadcasts</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($stats['incidents_reported']); ?></div>
                    <div class="stat-label">Incidents</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($stats['results_verified']); ?></div>
                    <div class="stat-label">Verified</div>
                </div>
            </div>
        </div>

        <!-- Profile Grid -->
        <div class="profile-grid">
            <!-- Personal Information -->
            <div class="profile-card">
                <div class="card-title">
                    <i class="fas fa-user"></i> Personal Information
                </div>
                <div class="info-row">
                    <span class="label">Full Name</span>
                    <span class="value"><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Email</span>
                    <span class="value"><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Phone</span>
                    <span class="value"><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Gender</span>
                    <span class="value"><?php echo ucfirst(htmlspecialchars($user['gender'] ?? 'Not specified')); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Date of Birth</span>
                    <span class="value"><?php echo !empty($user['date_of_birth']) ? date('M d, Y', strtotime($user['date_of_birth'])) : 'Not specified'; ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Status</span>
                    <span class="value">
                        <span class="badge-status <?php echo strtolower($user['status'] ?? 'pending'); ?>">
                            <?php echo ucfirst($user['status'] ?? 'Pending'); ?>
                        </span>
                    </span>
                </div>
            </div>

            <!-- Assignment & Jurisdiction -->
            <div class="profile-card">
                <div class="card-title">
                    <i class="fas fa-briefcase"></i> Assignment & Jurisdiction
                </div>
                <div class="info-row">
                    <span class="label">Role</span>
                    <span class="value"><?php echo htmlspecialchars($user['role_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Senatorial District</span>
                    <span class="value"><?php echo htmlspecialchars($user['senatorial_name'] ?? 'Not assigned'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">State</span>
                    <span class="value"><?php echo htmlspecialchars($user['state_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">LGA</span>
                    <span class="value"><?php echo htmlspecialchars($user['lga_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Ward</span>
                    <span class="value"><?php echo htmlspecialchars($user['ward_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Polling Unit</span>
                    <span class="value"><?php echo htmlspecialchars($user['pu_name'] ?? 'N/A'); ?></span>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="profile-card">
                <div class="card-title">
                    <i class="fas fa-bolt"></i> Quick Actions
                </div>
                <div class="quick-profile-actions">
                    <a href="profile-edit.php" class="quick-profile-action">
                        <i class="fas fa-edit"></i> Edit Profile
                    </a>
                    <a href="change-password.php" class="quick-profile-action">
                        <i class="fas fa-key"></i> Change Password
                    </a>
                    <a href="profile-activity.php" class="quick-profile-action">
                        <i class="fas fa-history"></i> Activity Log
                    </a>
                    <a href="profile-security.php" class="quick-profile-action">
                        <i class="fas fa-shield-alt"></i> Security
                    </a>
                </div>
            </div>

            <!-- Account Information -->
            <div class="profile-card">
                <div class="card-title">
                    <i class="fas fa-cog"></i> Account Information
                </div>
                <div class="info-row">
                    <span class="label">User Code</span>
                    <span class="value"><?php echo htmlspecialchars($user['user_code'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Member Since</span>
                    <span class="value"><?php echo !empty($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : 'N/A'; ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Last Login</span>
                    <span class="value"><?php echo !empty($user['last_login_at']) ? date('M d, Y g:i A', strtotime($user['last_login_at'])) : 'Never'; ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Last Login IP</span>
                    <span class="value"><?php echo htmlspecialchars($user['last_login_ip'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">2FA Status</span>
                    <span class="value">
                        <span class="badge-status <?php echo ($user['two_factor_enabled'] ?? 0) ? 'active' : 'pending'; ?>">
                            <?php echo ($user['two_factor_enabled'] ?? 0) ? 'Enabled' : 'Disabled'; ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Sidebar toggle functions
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

window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});
</script>
</body>
</html>