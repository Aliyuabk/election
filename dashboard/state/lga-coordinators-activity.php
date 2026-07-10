<?php
// ============================================================
// STATE COORDINATOR - LGA COORDINATOR ACTIVITY
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

// Get coordinator name
$coordinator_name = 'Coordinator';
try {
    $stmt = $db->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$coordinator_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $coordinator_name = $user['first_name'] . ' ' . $user['last_name'];
    }
} catch (Exception $e) {
    error_log("Error fetching coordinator name: " . $e->getMessage());
}

// Fetch activity logs
$activities = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM activity_logs 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$coordinator_id]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching activities: " . $e->getMessage());
}

$page_title = 'Coordinator Activity';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.activity-container {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px 24px;
}

.activity-item {
    display: flex;
    gap: 14px;
    padding: 12px 0;
    border-bottom: 1px solid var(--gray-100);
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-item .icon-wrapper {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    flex-shrink: 0;
}

.activity-item .icon-wrapper.login { background: #EFF6FF; color: #3B82F6; }
.activity-item .icon-wrapper.logout { background: #FEF2F2; color: #EF4444; }
.activity-item .icon-wrapper.create { background: #ECFDF5; color: #10B981; }
.activity-item .icon-wrapper.update { background: #FFFBEB; color: #F59E0B; }
.activity-item .icon-wrapper.delete { background: #FEF2F2; color: #DC2626; }
.activity-item .icon-wrapper.default { background: #F3F4F6; color: #6B7280; }

.activity-item .content {
    flex: 1;
}

.activity-item .content .description {
    font-size: 0.82rem;
    color: var(--gray-700);
}

.activity-item .content .description .highlight {
    font-weight: 600;
    color: var(--gray-900);
}

.activity-item .content .time {
    font-size: 0.65rem;
    color: var(--gray-400);
    margin-top: 2px;
}

.activity-item .content .meta {
    font-size: 0.6rem;
    color: var(--gray-400);
    margin-top: 2px;
}

.activity-item .content .meta i {
    margin-right: 4px;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
}

.empty-state i {
    font-size: 3rem;
    color: var(--gray-300);
    display: block;
    margin-bottom: 12px;
}

.empty-state h4 {
    color: var(--gray-600);
    margin: 0;
}

.empty-state p {
    color: var(--gray-400);
    font-size: 0.85rem;
    margin-top: 4px;
}

.activity-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}

.activity-stats .stat {
    text-align: center;
    padding: 10px;
    background: var(--gray-50);
    border-radius: 8px;
}

.activity-stats .stat .number {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--gray-800);
}

.activity-stats .stat .label {
    font-size: 0.6rem;
    color: var(--gray-500);
}

@media (max-width: 768px) {
    .activity-container {
        padding: 14px 16px;
    }
    .activity-stats {
        grid-template-columns: repeat(3, 1fr);
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="welcome-section">
            <div>
                <h1><i class="fas fa-clock"></i> Activity Log</h1>
                <p class="subtitle">
                    <i class="fas fa-user"></i> 
                    <?php echo htmlspecialchars($coordinator_name); ?> - Recent Activity
                </p>
            </div>
            <div class="actions">
                <a href="lga-coordinators-profiles.php?id=<?php echo $coordinator_id; ?>" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Profile
                </a>
            </div>
        </div>

        <!-- Activity Stats -->
        <?php 
        $activity_types = [];
        foreach ($activities as $activity) {
            $type = $activity['activity_type'] ?? 'other';
            $activity_types[$type] = ($activity_types[$type] ?? 0) + 1;
        }
        ?>
        <div class="activity-stats">
            <div class="stat">
                <div class="number"><?php echo count($activities); ?></div>
                <div class="label">Total Activities</div>
            </div>
            <?php foreach ($activity_types as $type => $count): ?>
                <div class="stat">
                    <div class="number"><?php echo $count; ?></div>
                    <div class="label"><?php echo ucfirst(str_replace('_', ' ', $type)); ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Activity List -->
        <div class="activity-container">
            <?php if (count($activities) > 0): ?>
                <?php foreach ($activities as $activity): 
                    $icon_class = 'default';
                    if (strpos($activity['activity_type'], 'login') !== false) $icon_class = 'login';
                    elseif (strpos($activity['activity_type'], 'logout') !== false) $icon_class = 'logout';
                    elseif (strpos($activity['activity_type'], 'create') !== false || strpos($activity['activity_type'], 'add') !== false) $icon_class = 'create';
                    elseif (strpos($activity['activity_type'], 'update') !== false || strpos($activity['activity_type'], 'edit') !== false) $icon_class = 'update';
                    elseif (strpos($activity['activity_type'], 'delete') !== false || strpos($activity['activity_type'], 'remove') !== false) $icon_class = 'delete';
                    
                    $icon_map = [
                        'login' => 'fa-sign-in-alt',
                        'logout' => 'fa-sign-out-alt',
                        'create' => 'fa-plus',
                        'update' => 'fa-edit',
                        'delete' => 'fa-trash',
                        'default' => 'fa-circle'
                    ];
                ?>
                    <div class="activity-item">
                        <div class="icon-wrapper <?php echo $icon_class; ?>">
                            <i class="fas <?php echo $icon_map[$icon_class] ?? $icon_map['default']; ?>"></i>
                        </div>
                        <div class="content">
                            <div class="description">
                                <?php echo htmlspecialchars($activity['description'] ?? 'Activity recorded'); ?>
                                <?php if (!empty($activity['entity_type']) && !empty($activity['entity_id'])): ?>
                                    <span class="highlight">(<?php echo htmlspecialchars($activity['entity_type']); ?> #<?php echo $activity['entity_id']; ?>)</span>
                                <?php endif; ?>
                            </div>
                            <div class="time">
                                <i class="far fa-clock"></i> 
                                <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                            </div>
                            <?php if (!empty($activity['ip_address'])): ?>
                                <div class="meta">
                                    <i class="fas fa-network-wired"></i> 
                                    <?php echo htmlspecialchars($activity['ip_address']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-clock"></i>
                    <h4>No Activity Found</h4>
                    <p>This coordinator hasn't performed any activities yet.</p>
                </div>
            <?php endif; ?>
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