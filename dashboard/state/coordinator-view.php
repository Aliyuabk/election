<?php
// ============================================================
// STATE COORDINATOR - LGA COORDINATOR PROFILES
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

// If state_id is not set in session, try to get it from user record
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

// ============================================================
// GET COORDINATOR ID
// ============================================================
$coordinator_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($coordinator_id <= 0) {
    header('Location: lga-coordinators.php');
    exit();
}

// ============================================================
// FETCH COORDINATOR DETAILS
// ============================================================
$coordinator = null;
$lga_name = '';
$state_name = '';

try {
    $stmt = $db->prepare("
        SELECT 
            u.*,
            r.name as role_name,
            r.level as role_level,
            l.name as lga_name,
            s.name as state_name,
            (SELECT COUNT(*) FROM users u2 WHERE u2.lga_id = u.lga_id AND u2.deleted_at IS NULL AND u2.status = 'active') as total_agents,
            (SELECT COUNT(*) FROM users u2 WHERE u2.lga_id = u.lga_id AND u2.deleted_at IS NULL AND u2.last_login_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)) as agents_online,
            (SELECT COUNT(*) FROM incidents i WHERE i.lga_id = u.lga_id AND i.status NOT IN ('resolved', 'false_alarm')) as active_incidents,
            (SELECT COUNT(*) FROM results_ec8a r2 WHERE r2.lga_id = u.lga_id AND r2.status = 'pending') as pending_results
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN lgas l ON u.lga_id = l.id
        LEFT JOIN states s ON u.state_id = s.id
        WHERE u.id = ? AND u.deleted_at IS NULL
    ");
    $stmt->execute([$coordinator_id]);
    $coordinator = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($coordinator) {
        $lga_name = $coordinator['lga_name'] ?? 'N/A';
        $state_name = $coordinator['state_name'] ?? 'Unknown State';
    }
} catch (Exception $e) {
    error_log("Error fetching coordinator: " . $e->getMessage());
}

if (!$coordinator) {
    header('Location: lga-coordinators.php');
    exit();
}

// ============================================================
// FETCH RECENT ACTIVITY
// ============================================================
$activities = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM activity_logs 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$coordinator_id]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching activities: " . $e->getMessage());
}

include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.profile-header {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px;
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

.profile-avatar {
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
.profile-avatar.blue { background: #3B82F6; }
.profile-avatar.green { background: #10B981; }
.profile-avatar.purple { background: #8B5CF6; }
.profile-avatar.orange { background: #F59E0B; }
.profile-avatar.red { background: #EF4444; }
.profile-avatar.teal { background: #0D9488; }
.profile-avatar.pink { background: #EC4899; }

.profile-info h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.profile-info .subtitle {
    color: var(--gray-500);
    font-size: 0.85rem;
    margin-top: 2px;
}
.profile-info .subtitle i {
    margin-right: 4px;
}
.profile-info .meta {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-top: 8px;
    font-size: 0.8rem;
    color: var(--gray-500);
}
.profile-info .meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}

.profile-actions {
    margin-left: auto;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.badge-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 12px;
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

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
    margin-bottom: 24px;
}
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 16px 20px;
    border: 1px solid var(--gray-200);
    text-align: center;
}
.stat-card .number {
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--gray-800);
}
.stat-card .label {
    font-size: 0.7rem;
    color: var(--gray-500);
    margin-top: 2px;
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px 24px;
    padding: 20px;
    background: var(--gray-50);
    border-radius: 12px;
}
.info-grid .info-item {
    display: flex;
    flex-direction: column;
}
.info-grid .info-item .label {
    font-size: 0.7rem;
    color: var(--gray-400);
    font-weight: 500;
}
.info-grid .info-item .value {
    font-size: 0.9rem;
    color: var(--gray-800);
    font-weight: 500;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--gray-100);
}
.activity-item:last-child {
    border-bottom: none;
}
.activity-item .activity-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    flex-shrink: 0;
    background: var(--primary-light);
    color: var(--primary);
}
.activity-item .activity-content {
    flex: 1;
}
.activity-item .activity-content .text {
    font-size: 0.8rem;
    color: var(--gray-700);
}
.activity-item .activity-content .time {
    font-size: 0.65rem;
    color: var(--gray-400);
}

.btn-primary-sm {
    padding: 8px 20px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}
.btn-primary-sm:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}

.btn-secondary-sm {
    padding: 8px 20px;
    background: var(--gray-100);
    color: var(--gray-700);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}
.btn-secondary-sm:hover {
    background: var(--gray-200);
}

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
    margin: 0;
}
.page-header h2 small {
    font-size: 0.8rem;
    font-weight: 400;
    color: var(--gray-500);
    display: block;
    margin-top: 2px;
}

.section-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--gray-800);
    margin: 0 0 12px 0;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--gray-200);
}
.section-title i {
    color: var(--primary);
    margin-right: 6px;
}

@media (max-width: 768px) {
    .profile-header {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    .profile-actions {
        margin-left: 0;
        width: 100%;
        justify-content: center;
    }
    .info-grid {
        grid-template-columns: 1fr;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .page-header {
        flex-direction: column;
        align-items: flex-start;
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
                    <i class="fas fa-id-card" style="color:var(--primary);margin-right:8px;"></i>
                    Coordinator Profile
                    <small><?php echo htmlspecialchars($lga_name); ?> LGA Coordinator</small>
                </h2>
            </div>
            <div>
                <a href="lga-coordinators.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Coordinators
                </a>
            </div>
        </div>

        <!-- Profile Header -->
        <?php 
        $avatar_colors = ['blue', 'green', 'purple', 'orange', 'red', 'teal', 'pink'];
        $color_idx = ($coordinator['id'] ?? 0) % count($avatar_colors);
        $avatar_color = $avatar_colors[$color_idx];
        $initials = strtoupper(substr($coordinator['first_name'] ?? '', 0, 1) . substr($coordinator['last_name'] ?? '', 0, 1));
        ?>
        
        <div class="profile-header">
            <div class="profile-avatar <?php echo $avatar_color; ?>">
                <?php echo $initials ?: '?'; ?>
            </div>
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($coordinator['full_name'] ?? $coordinator['first_name'] . ' ' . $coordinator['last_name']); ?></h2>
                <div class="subtitle">
                    <i class="fas fa-user-tie"></i> 
                    <?php echo htmlspecialchars($coordinator['role_name'] ?? 'LGA Coordinator'); ?>
                    <span class="badge-status <?php echo $coordinator['status']; ?>" style="margin-left:8px;">
                        <span class="dot"></span>
                        <?php echo ucfirst($coordinator['status']); ?>
                    </span>
                </div>
                <div class="meta">
                    <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($coordinator['email'] ?? 'N/A'); ?></span>
                    <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($coordinator['phone'] ?? 'N/A'); ?></span>
                    <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($lga_name); ?></span>
                    <span><i class="fas fa-code"></i> <?php echo htmlspecialchars($coordinator['user_code'] ?? 'N/A'); ?></span>
                </div>
            </div>
            <div class="profile-actions">
                <a href="coordinator-edit.php?id=<?php echo $coordinator['id']; ?>" class="btn-primary-sm">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <?php if ($coordinator['status'] === 'active'): ?>
                    <button onclick="confirmAction('suspend', <?php echo $coordinator['id']; ?>)" class="btn-secondary-sm" style="color:var(--danger);border-color:var(--danger);">
                        <i class="fas fa-pause"></i> Suspend
                    </button>
                <?php else: ?>
                    <button onclick="confirmAction('activate', <?php echo $coordinator['id']; ?>)" class="btn-primary-sm" style="background:#10B981;">
                        <i class="fas fa-play"></i> Activate
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo number_format($coordinator['total_agents'] ?? 0); ?></div>
                <div class="label">Total Agents</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color:#10B981;"><?php echo number_format($coordinator['agents_online'] ?? 0); ?></div>
                <div class="label">Agents Online</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color:#F59E0B;"><?php echo number_format($coordinator['pending_results'] ?? 0); ?></div>
                <div class="label">Pending Results</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color:#EF4444;"><?php echo number_format($coordinator['active_incidents'] ?? 0); ?></div>
                <div class="label">Active Incidents</div>
            </div>
        </div>

        <!-- Details Grid -->
        <div style="background:white;border-radius:var(--radius);border:1px solid var(--gray-200);padding:20px;margin-bottom:24px;">
            <h4 class="section-title"><i class="fas fa-info-circle"></i> Personal Information</h4>
            <div class="info-grid">
                <div class="info-item">
                    <span class="label">First Name</span>
                    <span class="value"><?php echo htmlspecialchars($coordinator['first_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Last Name</span>
                    <span class="value"><?php echo htmlspecialchars($coordinator['last_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Email Address</span>
                    <span class="value"><?php echo htmlspecialchars($coordinator['email'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Phone Number</span>
                    <span class="value"><?php echo htmlspecialchars($coordinator['phone'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Gender</span>
                    <span class="value"><?php echo ucfirst($coordinator['gender'] ?? 'Not specified'); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Date of Birth</span>
                    <span class="value"><?php echo !empty($coordinator['date_of_birth']) ? date('F j, Y', strtotime($coordinator['date_of_birth'])) : 'N/A'; ?></span>
                </div>
                <div class="info-item">
                    <span class="label">User Code</span>
                    <span class="value"><?php echo htmlspecialchars($coordinator['user_code'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Role</span>
                    <span class="value"><?php echo htmlspecialchars($coordinator['role_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">State</span>
                    <span class="value"><?php echo htmlspecialchars($state_name); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">LGA</span>
                    <span class="value"><?php echo htmlspecialchars($lga_name); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Status</span>
                    <span class="value">
                        <span class="badge-status <?php echo $coordinator['status']; ?>">
                            <span class="dot"></span>
                            <?php echo ucfirst($coordinator['status']); ?>
                        </span>
                    </span>
                </div>
                <div class="info-item">
                    <span class="label">Last Login</span>
                    <span class="value">
                        <?php if (!empty($coordinator['last_login_at'])): ?>
                            <?php echo date('M j, Y g:i A', strtotime($coordinator['last_login_at'])); ?>
                        <?php else: ?>
                            <span style="color:var(--gray-400);">Never logged in</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div style="background:white;border-radius:var(--radius);border:1px solid var(--gray-200);padding:20px;">
            <h4 class="section-title"><i class="fas fa-clock"></i> Recent Activity</h4>
            <?php if (count($activities) > 0): ?>
                <?php foreach ($activities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="fas fa-circle"></i>
                        </div>
                        <div class="activity-content">
                            <div class="text"><?php echo htmlspecialchars($activity['description'] ?? 'Activity recorded'); ?></div>
                            <div class="time"><?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align:center;padding:30px 20px;color:var(--gray-400);">
                    <i class="fas fa-clock" style="font-size:2rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                    <p style="margin:0;">No recent activity</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- ============================================================
CONFIRMATION MODAL
============================================================ -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="confirmTitle">Confirm Action</h3>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="confirmBody">
            <p>Are you sure you want to perform this action?</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <form method="POST" action="" id="confirmForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
                <input type="hidden" name="action" id="confirmAction" value="">
                <input type="hidden" name="user_id" id="confirmUserId" value="">
                <button type="submit" class="btn btn-danger" id="confirmBtn">Confirm</button>
            </form>
        </div>
    </div>
</div>

<style>
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.4);
    z-index: 300;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.modal-overlay.active { display: flex; }
.modal {
    background: white;
    border-radius: var(--radius);
    max-width: 440px;
    width: 100%;
    padding: 28px 32px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.15);
    animation: modalIn 0.25s ease;
}
@keyframes modalIn {
    from { transform: scale(0.95) translateY(10px); opacity: 0; }
    to { transform: scale(1) translateY(0); opacity: 1; }
}
.modal .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}
.modal .modal-header h3 {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--gray-800);
}
.modal .modal-header .close-btn {
    background: none;
    border: none;
    font-size: 1.4rem;
    color: var(--gray-400);
    cursor: pointer;
    transition: var(--transition);
    padding: 0 4px;
}
.modal .modal-header .close-btn:hover {
    color: var(--gray-600);
}
.modal .modal-body {
    margin-bottom: 20px;
    color: var(--gray-600);
    font-size: 0.9rem;
    line-height: 1.6;
}
.modal .modal-body strong {
    color: var(--gray-800);
}
.modal .modal-footer {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}
.modal .modal-footer .btn {
    padding: 8px 20px;
    border-radius: 8px;
    border: none;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}
.modal .modal-footer .btn-secondary {
    background: var(--gray-100);
    color: var(--gray-600);
}
.modal .modal-footer .btn-secondary:hover {
    background: var(--gray-200);
}
.modal .modal-footer .btn-danger {
    background: var(--danger);
    color: white;
}
.modal .modal-footer .btn-danger:hover {
    background: #DC2626;
}
.modal .modal-footer .btn-primary {
    background: var(--primary);
    color: white;
}
.modal .modal-footer .btn-primary:hover {
    background: var(--primary-dark);
}
@media (max-width: 480px) {
    .modal { padding: 20px; margin: 10px; }
    .modal .modal-footer { flex-direction: column; }
    .modal .modal-footer .btn { width: 100%; justify-content: center; }
}
</style>

<script>
// ============================================================
// PRELOADER
// ============================================================
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() {
            preloader.style.display = 'none';
        }, 600);
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
// CONFIRMATION MODAL
// ============================================================
function confirmAction(action, userId) {
    var modal = document.getElementById('confirmModal');
    
    if (action === 'suspend') {
        document.getElementById('confirmTitle').textContent = 'Suspend Coordinator';
        document.getElementById('confirmBody').innerHTML = 'Are you sure you want to suspend <strong><?php echo htmlspecialchars($coordinator['full_name'] ?? 'this coordinator'); ?></strong>? The user will lose access to the platform.';
        document.getElementById('confirmAction').value = 'suspend';
        document.getElementById('confirmBtn').className = 'btn btn-danger';
        document.getElementById('confirmBtn').textContent = 'Suspend';
    } else if (action === 'activate') {
        document.getElementById('confirmTitle').textContent = 'Activate Coordinator';
        document.getElementById('confirmBody').innerHTML = 'Are you sure you want to activate <strong><?php echo htmlspecialchars($coordinator['full_name'] ?? 'this coordinator'); ?></strong>? The user will regain full access.';
        document.getElementById('confirmAction').value = 'activate';
        document.getElementById('confirmBtn').className = 'btn btn-primary';
        document.getElementById('confirmBtn').textContent = 'Activate';
    }
    
    document.getElementById('confirmUserId').value = userId;
    modal.classList.add('active');
}

function closeModal() {
    document.getElementById('confirmModal').classList.remove('active');
}
</script>
</body>
</html>