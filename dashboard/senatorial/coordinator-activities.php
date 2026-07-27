<?php
// ============================================================
// SENATORIAL COORDINATOR - COORDINATOR ACTIVITIES
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

$user_name = SessionManager::get('user_name', 'Coordinator');
$senatorial_id = SessionManager::get('senatorial_id');
$state_id = SessionManager::get('state_id');
$tenant_id = SessionManager::get('tenant_id');

$db = getDB();

// ============================================================
// GET SENATORIAL DISTRICT NAME
// ============================================================
$district_name = 'Senatorial District';
$state_name = 'State';
try {
    if ($senatorial_id) {
        $stmt = $db->prepare("
            SELECT s.name as state_name, sd.name as district_name 
            FROM senatorial_districts sd 
            JOIN states s ON sd.state_id = s.id 
            WHERE sd.id = ?
        ");
        $stmt->execute([$senatorial_id]);
        $result = $stmt->fetch();
        if ($result) {
            $district_name = $result['district_name'];
            $state_name = $result['state_name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching district: " . $e->getMessage());
}

// ============================================================
// GET LGA IDs FROM SENATORIAL DISTRICT
// ============================================================
$lga_ids = [];
try {
    if ($senatorial_id) {
        $stmt = $db->prepare("SELECT lgas_json FROM senatorial_districts WHERE id = ?");
        $stmt->execute([$senatorial_id]);
        $lgas_json = $stmt->fetchColumn();
        
        if ($lgas_json) {
            $lga_names = json_decode($lgas_json, true) ?: [];
            
            if (!empty($lga_names)) {
                $placeholders = implode(',', array_fill(0, count($lga_names), '?'));
                $stmt = $db->prepare("SELECT id FROM lgas WHERE name IN ($placeholders) AND state_id = ? AND is_active = 1");
                $stmt->execute(array_merge($lga_names, [$state_id]));
                $lga_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
        }
    }
} catch (Exception $e) {
    error_log("Error getting LGA IDs: " . $e->getMessage());
    $lga_ids = [];
}

$lga_list = !empty($lga_ids) ? implode(',', array_map('intval', $lga_ids)) : '0';

// ============================================================
// GET COORDINATOR ACTIVITIES
// ============================================================
$activities = [];
try {
    $stmt = $db->prepare("
        SELECT a.*, 
               u.full_name as user_name, 
               u.photograph_url as user_avatar,
               u.email as user_email,
               u.phone as user_phone,
               r.name as role_name,
               r.level as role_level
        FROM activity_logs a
        JOIN users u ON a.user_id = u.id
        JOIN roles r ON u.role_id = r.id
        WHERE a.tenant_id = ? 
        AND r.level IN ('federal_constituency', 'lga', 'ward')
        ORDER BY a.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$tenant_id]);
    $activities = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching activities: " . $e->getMessage());
    $activities = [];
}

// ============================================================
// GROUP ACTIVITIES BY COORDINATOR
// ============================================================
$grouped_activities = [];
foreach ($activities as $activity) {
    $user_id = $activity['user_id'];
    if (!isset($grouped_activities[$user_id])) {
        $grouped_activities[$user_id] = [
            'user_name' => $activity['user_name'],
            'user_avatar' => $activity['user_avatar'],
            'user_email' => $activity['user_email'],
            'user_phone' => $activity['user_phone'],
            'role_name' => $activity['role_name'],
            'role_level' => $activity['role_level'],
            'activities' => []
        ];
    }
    $grouped_activities[$user_id]['activities'][] = $activity;
}

// ============================================================
// GET LGAS FOR FILTER
// ============================================================
$lgas = [];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("SELECT id, name FROM lgas WHERE id IN ($lga_list) AND is_active = 1 ORDER BY name ASC");
        $stmt->execute();
        $lgas = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching LGAs: " . $e->getMessage());
}

$page_title = 'Coordinator Activities';
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

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.stat-card {
    background: white;
    border-radius: var(--radius);
    padding: 18px 20px;
    border: 1px solid var(--gray-200);
    text-align: center;
}
.stat-card .stat-number {
    font-size: 1.5rem;
    font-weight: 700;
}
.stat-card .stat-label {
    font-size: 0.75rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.stat-card .stat-icon {
    font-size: 1.2rem;
    margin-bottom: 4px;
}
.stat-card.blue .stat-number { color: #2563EB; }
.stat-card.blue .stat-icon { color: #2563EB; }
.stat-card.green .stat-number { color: #059669; }
.stat-card.green .stat-icon { color: #059669; }
.stat-card.purple .stat-number { color: #7C3AED; }
.stat-card.purple .stat-icon { color: #7C3AED; }
.stat-card.orange .stat-number { color: #EA580C; }
.stat-card.orange .stat-icon { color: #EA580C; }

.section-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
    margin-bottom: 20px;
}
.section-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.section-card .card-header h3 {
    font-size: 0.95rem;
    font-weight: 600;
    margin: 0;
}
.section-card .card-header a {
    font-size: 0.75rem;
    color: var(--primary);
    text-decoration: none;
}

.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 12px 0;
    border-bottom: 1px solid var(--gray-100);
}
.activity-item:last-child {
    border-bottom: none;
}
.activity-item .activity-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    flex-shrink: 0;
}
.activity-item .activity-icon.login { background: #DBEAFE; color: #2563EB; }
.activity-item .activity-icon.system { background: #EDE9FE; color: #7C3AED; }
.activity-item .activity-icon.result { background: #D1FAE5; color: #059669; }
.activity-item .activity-icon.incident { background: #FEE2E2; color: #DC2626; }
.activity-item .activity-icon.user { background: #FEF3C7; color: #D97706; }
.activity-item .activity-content {
    flex: 1;
    min-width: 0;
}
.activity-item .activity-content .title {
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--gray-700);
}
.activity-item .activity-content .desc {
    font-size: 0.8rem;
    color: var(--gray-500);
}
.activity-item .activity-content .time {
    font-size: 0.65rem;
    color: var(--gray-400);
    margin-top: 2px;
}
.text-truncate {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.coordinator-card {
    background: var(--gray-50);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 16px;
    border: 1px solid var(--gray-200);
}
.coordinator-card .coordinator-header {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 12px;
}
.coordinator-card .coordinator-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
    flex-shrink: 0;
}
.coordinator-card .coordinator-info .name {
    font-weight: 600;
    font-size: 0.9rem;
}
.coordinator-card .coordinator-info .role {
    font-size: 0.75rem;
    color: var(--gray-500);
}
.coordinator-card .coordinator-info .details {
    font-size: 0.7rem;
    color: var(--gray-400);
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: var(--gray-500);
}
.empty-state i {
    font-size: 3rem;
    display: block;
    margin-bottom: 12px;
    color: var(--gray-300);
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
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
                    <i class="fas fa-user-tie" style="color:var(--primary);margin-right:8px;"></i> 
                    Coordinator Activities
                    <small><?php echo htmlspecialchars($district_name); ?> - <?php echo htmlspecialchars($state_name); ?></small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="monitor-district.php" class="btn btn-secondary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--gray-100);color:var(--gray-600);border:none;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo number_format(count($grouped_activities)); ?></div>
                <div class="stat-label">Active Coordinators</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon"><i class="fas fa-activity"></i></div>
                <div class="stat-number"><?php echo number_format(count($activities)); ?></div>
                <div class="stat-label">Total Activities</div>
            </div>
            <div class="stat-card purple">
                <div class="stat-icon"><i class="fas fa-user"></i></div>
                <div class="stat-number">
                    <?php 
                        $fc_count = 0; $lga_count = 0; $ward_count = 0;
                        foreach ($grouped_activities as $g) {
                            if ($g['role_level'] === 'federal_constituency') $fc_count++;
                            elseif ($g['role_level'] === 'lga') $lga_count++;
                            elseif ($g['role_level'] === 'ward') $ward_count++;
                        }
                        echo $fc_count;
                    ?>
                </div>
                <div class="stat-label">Federal Constituency</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-icon"><i class="fas fa-map-marker-alt"></i></div>
                <div class="stat-number"><?php echo $lga_count; ?></div>
                <div class="stat-label">LGA Coordinators</div>
            </div>
        </div>

        <!-- Activities by Coordinator -->
        <div class="section-card">
            <div class="card-header">
                <h3><i class="fas fa-list" style="color:var(--primary);margin-right:6px;"></i> Activities by Coordinator</h3>
                <span style="font-size:0.75rem;color:var(--gray-400);">Latest activities</span>
            </div>
            <?php if (count($grouped_activities) > 0): ?>
                <?php foreach ($grouped_activities as $user_id => $coordinator): ?>
                    <div class="coordinator-card">
                        <div class="coordinator-header">
                            <div class="coordinator-avatar">
                                <?php if ($coordinator['user_avatar']): ?>
                                    <img src="<?php echo $coordinator['user_avatar']; ?>" alt="<?php echo htmlspecialchars($coordinator['user_name']); ?>" style="width:44px;height:44px;border-radius:50%;object-fit:cover;">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($coordinator['user_name'] ?? 'U', 0, 2)); ?>
                                <?php endif; ?>
                            </div>
                            <div class="coordinator-info">
                                <div class="name"><?php echo htmlspecialchars($coordinator['user_name']); ?></div>
                                <div class="role"><?php echo htmlspecialchars($coordinator['role_name']); ?></div>
                                <div class="details">
                                    <?php if ($coordinator['user_email']): ?>
                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($coordinator['user_email']); ?>
                                    <?php endif; ?>
                                    <?php if ($coordinator['user_phone']): ?>
                                        <span style="margin-left:12px;"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($coordinator['user_phone']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="margin-left:auto;font-size:0.7rem;color:var(--gray-400);">
                                <?php echo count($coordinator['activities']); ?> activities
                            </div>
                        </div>
                        <?php foreach (array_slice($coordinator['activities'], 0, 5) as $activity): ?>
                            <div class="activity-item" style="padding:6px 0 6px 14px;border-left:2px solid var(--gray-200);">
                                <div class="activity-content">
                                    <div class="desc"><?php echo htmlspecialchars($activity['description'] ?? ''); ?></div>
                                    <div class="time"><?php echo date('M j, Y g:i A', strtotime($activity['created_at'] ?? 'now')); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($coordinator['activities']) > 5): ?>
                            <div style="text-align:right;font-size:0.7rem;color:var(--gray-400);margin-top:4px;">
                                +<?php echo count($coordinator['activities']) - 5; ?> more activities
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <p>No coordinator activities found.</p>
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