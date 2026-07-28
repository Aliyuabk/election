<?php
// ============================================================
// SENATORIAL COORDINATOR - USER DETAILS
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

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
$senatorial_id = SessionManager::get('senatorial_id');
$state_id = SessionManager::get('state_id');

$db = getDB();

// ============================================================
// GET USER ID
// ============================================================
$target_user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$target_user_id) {
    header('Location: coordinators.php');
    exit();
}

// ============================================================
// GET USER DATA
// ============================================================
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
        WHERE u.id = ? AND u.tenant_id = ?
    ");
    $stmt->execute([$target_user_id, $tenant_id]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    error_log("Error fetching user: " . $e->getMessage());
}

if (!$user) {
    header('Location: coordinators.php');
    exit();
}

// ============================================================
// GET USER STATISTICS
// ============================================================
$stats = [
    'broadcasts_sent' => 0,
    'incidents_reported' => 0,
    'results_verified' => 0,
    'total_activities' => 0,
    'assigned_agents' => 0
];

try {
    // Broadcasts
    $stmt = $db->prepare("SELECT COUNT(*) FROM broadcasts WHERE sender_id = ?");
    $stmt->execute([$target_user_id]);
    $stats['broadcasts_sent'] = (int)$stmt->fetchColumn();
    
    // Incidents
    $stmt = $db->prepare("SELECT COUNT(*) FROM incidents WHERE reporter_id = ?");
    $stmt->execute([$target_user_id]);
    $stats['incidents_reported'] = (int)$stmt->fetchColumn();
    
    // Results verified
    $stmt = $db->prepare("SELECT COUNT(*) FROM results_ec8a WHERE verified_by = ?");
    $stmt->execute([$target_user_id]);
    $stats['results_verified'] = (int)$stmt->fetchColumn();
    
    // Activities
    $stmt = $db->prepare("SELECT COUNT(*) FROM activity_logs WHERE user_id = ?");
    $stmt->execute([$target_user_id]);
    $stats['total_activities'] = (int)$stmt->fetchColumn();
    
    // Assigned agents (if user is a coordinator)
    if (in_array($user['role_level'], ['lga', 'ward', 'federal_constituency'])) {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM users 
            WHERE created_by = ? AND status = 'active'
        ");
        $stmt->execute([$target_user_id]);
        $stats['assigned_agents'] = (int)$stmt->fetchColumn();
    }
} catch (Exception $e) {
    error_log("Error fetching user stats: " . $e->getMessage());
}

// ============================================================
// GET USER ACTIVITY LOG
// ============================================================
$activities = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM activity_logs 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$target_user_id]);
    $activities = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching user activities: " . $e->getMessage());
}

// ============================================================
// HANDLE USER STATUS UPDATE
// ============================================================
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'toggle_status') {
        $new_status = $user['status'] === 'active' ? 'suspended' : 'active';
        try {
            $stmt = $db->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$new_status, $target_user_id]);
            
            logActivity($user_id, $new_status === 'active' ? 'user_activated' : 'user_suspended', 
                $new_status === 'active' ? "Activated user: {$user['full_name']} (ID: $target_user_id)" : "Suspended user: {$user['full_name']} (ID: $target_user_id)", 
                'user', $target_user_id);
            
            $success = "User " . ($new_status === 'active' ? 'activated' : 'suspended') . " successfully!";
            
            // Refresh user data
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$target_user_id]);
            $user = $stmt->fetch();
            
        } catch (Exception $e) {
            $error = 'Error updating user status: ' . $e->getMessage();
        }
    }
}

$page_title = 'User Details';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
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

.user-header {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 30px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
}
.user-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: 700;
    flex-shrink: 0;
    overflow: hidden;
}
.user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.user-info h3 {
    font-size: 1.1rem;
    font-weight: 700;
    margin: 0;
}
.user-info .role-badge {
    display: inline-block;
    background: #DBEAFE;
    color: #2563EB;
    padding: 2px 12px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
}
.user-info .email {
    color: var(--gray-500);
    font-size: 0.85rem;
    margin-top: 4px;
}
.user-info .location {
    color: var(--gray-400);
    font-size: 0.8rem;
}
.user-status {
    margin-left: auto;
    text-align: right;
}
.status-badge {
    display: inline-block;
    padding: 4px 16px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}
.status-badge.active { background: #D1FAE5; color: #059669; }
.status-badge.suspended { background: #FEE2E2; color: #DC2626; }
.status-badge.pending { background: #FEF3C7; color: #D97706; }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 12px;
    margin-bottom: 24px;
}
.stat-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 14px 16px;
    text-align: center;
}
.stat-card .number {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--gray-800);
}
.stat-card .label {
    font-size: 0.65rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.detail-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
    margin-bottom: 20px;
}
.detail-card .card-title {
    font-size: 0.9rem;
    font-weight: 600;
    margin: 0 0 16px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.detail-card .card-title i {
    color: var(--primary);
}
.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.info-row {
    display: flex;
    flex-direction: column;
}
.info-row .label {
    font-size: 0.65rem;
    color: var(--gray-400);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.info-row .value {
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--gray-800);
}

.btn {
    padding: 8px 18px;
    border-radius: 10px;
    border: none;
    font-weight: 600;
    font-size: 0.82rem;
    cursor: pointer;
    transition: var(--transition);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-primary {
    background: var(--primary);
    color: white;
}
.btn-primary:hover {
    background: #1D4ED8;
}
.btn-danger {
    background: #DC2626;
    color: white;
}
.btn-danger:hover {
    background: #B91C1C;
}
.btn-success {
    background: #10B981;
    color: white;
}
.btn-success:hover {
    background: #059669;
}
.btn-secondary {
    background: var(--gray-100);
    color: var(--gray-600);
}
.btn-secondary:hover {
    background: var(--gray-200);
}

.alert {
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 0.85rem;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-success {
    background: #ECFDF5;
    color: #065F46;
    border: 1px solid #A7F3D0;
}
.alert-error {
    background: #FEF2F2;
    color: #DC2626;
    border: 1px solid #FECACA;
}

.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--gray-100);
}
.activity-item:last-child {
    border-bottom: none;
}
.activity-item .activity-icon {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    flex-shrink: 0;
}
.activity-item .activity-icon.login { background: #DBEAFE; color: #2563EB; }
.activity-item .activity-icon.system { background: #EDE9FE; color: #7C3AED; }
.activity-item .activity-icon.result { background: #D1FAE5; color: #059669; }
.activity-item .activity-icon.incident { background: #FEE2E2; color: #DC2626; }
.activity-item .activity-content {
    flex: 1;
}
.activity-item .activity-content .desc {
    font-size: 0.8rem;
    color: var(--gray-600);
}
.activity-item .activity-content .time {
    font-size: 0.65rem;
    color: var(--gray-400);
}

.empty-state {
    text-align: center;
    padding: 30px 20px;
    color: var(--gray-500);
}
.empty-state i {
    font-size: 2rem;
    color: var(--gray-300);
    margin-bottom: 8px;
}
.empty-state p {
    font-size: 0.85rem;
    margin: 0;
}

@media (max-width: 768px) {
    .user-header {
        flex-direction: column;
        text-align: center;
    }
    .user-status {
        margin-left: 0;
        text-align: center;
        width: 100%;
    }
    .info-grid {
        grid-template-columns: 1fr;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .btn-group {
        flex-direction: column;
    }
    .btn-group .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-user" style="color:var(--primary);margin-right:8px;"></i> 
                    User Details
                    <small><?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?></small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="coordinators.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo htmlspecialchars($error); ?></div>
            </div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div><?php echo htmlspecialchars($success); ?></div>
            </div>
        <?php endif; ?>

        <!-- User Header -->
        <div class="user-header">
            <div class="user-avatar">
                <?php if (!empty($user['photograph_url'])): ?>
                    <img src="<?php echo htmlspecialchars($user['photograph_url']); ?>" alt="<?php echo htmlspecialchars($user['full_name']); ?>">
                <?php else: ?>
                    <?php echo substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'R', 0, 1); ?>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <h3><?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?></h3>
                <div>
                    <span class="role-badge">
                        <i class="fas fa-user-tie"></i> 
                        <?php echo htmlspecialchars($user['role_name'] ?? 'No Role'); ?>
                    </span>
                    <span style="font-size:0.7rem;color:var(--gray-400);margin-left:8px;">
                        <?php echo ucfirst($user['role_level'] ?? ''); ?>
                    </span>
                </div>
                <div class="email">
                    <i class="fas fa-envelope"></i> 
                    <?php echo htmlspecialchars($user['email'] ?? 'No email set'); ?>
                </div>
                <div class="location">
                    <i class="fas fa-phone"></i> 
                    <?php echo htmlspecialchars($user['phone'] ?? 'No phone'); ?>
                </div>
            </div>
            <div class="user-status">
                <span class="status-badge <?php echo $user['status']; ?>">
                    <?php echo ucfirst($user['status'] ?? 'Pending'); ?>
                </span>
                <div style="margin-top:8px;">
                    <?php if ($user['status'] === 'active'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="toggle_status">
                            <button type="submit" class="btn btn-danger" style="padding:4px 14px;font-size:0.7rem;" onclick="return confirm('Suspend this user?')">
                                <i class="fas fa-ban"></i> Suspend
                            </button>
                        </form>
                    <?php elseif ($user['status'] === 'suspended'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="toggle_status">
                            <button type="submit" class="btn btn-success" style="padding:4px 14px;font-size:0.7rem;" onclick="return confirm('Activate this user?')">
                                <i class="fas fa-check"></i> Activate
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo number_format($stats['broadcasts_sent']); ?></div>
                <div class="label">Broadcasts</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo number_format($stats['incidents_reported']); ?></div>
                <div class="label">Incidents</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo number_format($stats['results_verified']); ?></div>
                <div class="label">Verified</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo number_format($stats['total_activities']); ?></div>
                <div class="label">Activities</div>
            </div>
            <?php if ($stats['assigned_agents'] > 0): ?>
                <div class="stat-card">
                    <div class="number"><?php echo number_format($stats['assigned_agents']); ?></div>
                    <div class="label">Assigned Agents</div>
                </div>
            <?php endif; ?>
        </div>

        <!-- User Details -->
        <div class="detail-card">
            <div class="card-title">
                <i class="fas fa-info-circle"></i> Personal Information
            </div>
            <div class="info-grid">
                <div class="info-row">
                    <span class="label">User Code</span>
                    <span class="value"><?php echo htmlspecialchars($user['user_code'] ?? 'N/A'); ?></span>
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
                    <span class="label">Member Since</span>
                    <span class="value"><?php echo !empty($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : 'N/A'; ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Last Login</span>
                    <span class="value"><?php echo !empty($user['last_login_at']) ? date('M d, Y g:i A', strtotime($user['last_login_at'])) : 'Never'; ?></span>
                </div>
                <div class="info-row">
                    <span class="label">2FA Enabled</span>
                    <span class="value"><?php echo ($user['two_factor_enabled'] ?? 0) ? 'Yes' : 'No'; ?></span>
                </div>
            </div>
        </div>

        <!-- Jurisdiction -->
        <div class="detail-card">
            <div class="card-title">
                <i class="fas fa-map-marker-alt"></i> Jurisdiction
            </div>
            <div class="info-grid">
                <div class="info-row">
                    <span class="label">State</span>
                    <span class="value"><?php echo htmlspecialchars($user['state_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Senatorial District</span>
                    <span class="value"><?php echo htmlspecialchars($user['senatorial_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Federal Constituency</span>
                    <span class="value"><?php echo htmlspecialchars($user['federal_constituency_name'] ?? 'N/A'); ?></span>
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
        </div>

        <!-- Recent Activity -->
        <div class="detail-card">
            <div class="card-title">
                <i class="fas fa-history"></i> Recent Activity
            </div>
            <?php if (!empty($activities)): ?>
                <?php foreach ($activities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon <?php 
                            if (strpos($activity['activity_type'] ?? '', 'login') !== false) echo 'login';
                            elseif (strpos($activity['activity_type'] ?? '', 'result') !== false) echo 'result';
                            elseif (strpos($activity['activity_type'] ?? '', 'incident') !== false) echo 'incident';
                            else echo 'system';
                        ?>">
                            <i class="fas <?php 
                                if (strpos($activity['activity_type'] ?? '', 'login') !== false) echo 'fa-sign-in-alt';
                                elseif (strpos($activity['activity_type'] ?? '', 'result') !== false) echo 'fa-file-alt';
                                elseif (strpos($activity['activity_type'] ?? '', 'incident') !== false) echo 'fa-exclamation-triangle';
                                else echo 'fa-cog';
                            ?>"></i>
                        </div>
                        <div class="activity-content">
                            <div class="desc"><?php echo htmlspecialchars($activity['description'] ?? ''); ?></div>
                            <div class="time"><?php echo date('M d, Y g:i A', strtotime($activity['created_at'] ?? 'now')); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No recent activity for this user.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
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