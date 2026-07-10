<?php
// ============================================================
// STATE COORDINATOR - LGA COORDINATORS LIST
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
$lga_filter = isset($_GET['lga_id']) ? (int)$_GET['lga_id'] : 0;

// Get state name
$state_name = 'Unknown State';
if (!empty($state_id)) {
    $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
    $stmt->execute([$state_id]);
    $state = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_name = $state['name'] ?? 'Unknown State';
}

// Get LGAs for filter
$lgas = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM lgas WHERE state_id = ? AND is_active = 1 ORDER BY name ASC");
    $stmt->execute([$state_id]);
    $lgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching LGAs: " . $e->getMessage());
}

// Fetch LGA Coordinators
$coordinators = [];
try {
    $sql = "
        SELECT 
            u.id,
            u.user_code,
            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            u.photograph_url,
            u.status,
            u.last_login_at,
            u.last_login_ip,
            u.created_at,
            l.name as lga_name,
            l.id as lga_id,
            (SELECT COUNT(*) FROM results_ec8a r 
             JOIN polling_units pu ON r.pu_id = pu.id 
             JOIN wards w ON pu.ward_id = w.id 
             WHERE w.lga_id = l.id AND r.status IN ('pending', 'verified', 'approved')) as verified_results,
            (SELECT COUNT(*) FROM polling_units pu 
             JOIN wards w ON pu.ward_id = w.id 
             WHERE w.lga_id = l.id AND pu.is_active = 1) as total_pus,
            (SELECT COUNT(*) FROM incidents i WHERE i.lga_id = l.id AND i.status IN ('reported', 'acknowledged', 'investigating')) as pending_incidents,
            (SELECT COUNT(*) FROM user_sessions us WHERE us.user_id = u.id AND us.is_active = 1 AND us.last_activity_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)) as is_online
        FROM users u
        JOIN roles r ON u.role_id = r.id
        JOIN lgas l ON u.lga_id = l.id
        WHERE r.level = 'lga'
        AND u.state_id = ?
        AND u.deleted_at IS NULL
    ";
    
    if ($lga_filter > 0) {
        $sql .= " AND u.lga_id = ?";
        $params = [$state_id, $lga_filter];
    } else {
        $params = [$state_id];
    }
    
    $sql .= " ORDER BY l.name ASC, u.first_name ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $coordinators = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching coordinators: " . $e->getMessage());
}

$page_title = 'LGA Coordinators';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.filter-bar {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
    align-items: center;
}

.filter-bar select,
.filter-bar input {
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.8rem;
    font-family: 'Inter', sans-serif;
    background: white;
    color: var(--gray-700);
    min-width: 180px;
}

.filter-bar select:focus,
.filter-bar input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.coordinator-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
}

.coordinator-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 18px 20px;
    transition: var(--transition);
}

.coordinator-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-hover);
}

.coordinator-card .card-top {
    display: flex;
    align-items: center;
    gap: 14px;
}

.coordinator-card .avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1rem;
    flex-shrink: 0;
}

.coordinator-card .avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

.coordinator-card .info .name {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--gray-800);
}

.coordinator-card .info .lga {
    font-size: 0.7rem;
    color: var(--primary);
    font-weight: 500;
}

.coordinator-card .info .lga i {
    margin-right: 4px;
}

.coordinator-card .details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px;
    margin: 12px 0;
    padding: 10px 0;
    border-top: 1px solid var(--gray-100);
    border-bottom: 1px solid var(--gray-100);
}

.coordinator-card .details .detail-item {
    font-size: 0.7rem;
    color: var(--gray-600);
}

.coordinator-card .details .detail-item .value {
    font-weight: 600;
    color: var(--gray-800);
}

.coordinator-card .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.55rem;
    padding: 2px 10px;
    border-radius: 12px;
    font-weight: 600;
}

.coordinator-card .status-badge .dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    display: inline-block;
}

.coordinator-card .status-badge.active { background: #ECFDF5; color: #065F46; }
.coordinator-card .status-badge.active .dot { background: #10B981; }

.coordinator-card .status-badge.suspended { background: #FEF2F2; color: #991B1B; }
.coordinator-card .status-badge.suspended .dot { background: #EF4444; }

.coordinator-card .status-badge.pending { background: #FFFBEB; color: #92400E; }
.coordinator-card .status-badge.pending .dot { background: #F59E0B; }

.coordinator-card .status-badge.online { background: #ECFDF5; color: #065F46; }
.coordinator-card .status-badge.online .dot { background: #10B981; animation: pulse-dot 1.5s ease-in-out infinite; }

.coordinator-card .status-badge.offline { background: #F3F4F6; color: #6B7280; }
.coordinator-card .status-badge.offline .dot { background: #9CA3AF; }

@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(0.8); }
}

.coordinator-card .actions {
    display: flex;
    gap: 6px;
    margin-top: 10px;
    flex-wrap: wrap;
}

.coordinator-card .actions a {
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 0.65rem;
    font-weight: 500;
    text-decoration: none;
    transition: var(--transition);
}

.coordinator-card .actions .btn-profile {
    background: var(--primary);
    color: white;
}

.coordinator-card .actions .btn-profile:hover {
    background: var(--primary-dark);
}

.coordinator-card .actions .btn-activity {
    background: var(--gray-100);
    color: var(--gray-700);
}

.coordinator-card .actions .btn-activity:hover {
    background: var(--gray-200);
}

.coordinator-card .actions .btn-suspend {
    background: #FEF2F2;
    color: #DC2626;
}

.coordinator-card .actions .btn-suspend:hover {
    background: #FEE2E2;
}

.coordinator-card .actions .btn-reset {
    background: #FFFBEB;
    color: #D97706;
}

.coordinator-card .actions .btn-reset:hover {
    background: #FEF3C7;
}

.empty-state {
    grid-column: 1/-1;
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}

.empty-state i {
    font-size: 3rem;
    color: var(--gray-300);
    display: block;
    margin-bottom: 12px;
}

.empty-state h3 {
    color: var(--gray-600);
    margin: 0;
}

.empty-state p {
    color: var(--gray-400);
    margin-top: 6px;
}

@media (max-width: 768px) {
    .coordinator-grid {
        grid-template-columns: 1fr;
    }
    .filter-bar {
        flex-direction: column;
    }
    .filter-bar select,
    .filter-bar input {
        width: 100%;
        min-width: unset;
    }
    .coordinator-card .details {
        grid-template-columns: 1fr 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="welcome-section">
            <div>
                <h1><i class="fas fa-user-tie"></i> LGA Coordinators</h1>
                <p class="subtitle">
                    <i class="fas fa-flag"></i> 
                    <?php echo htmlspecialchars($state_name); ?> State - Manage LGA Coordinators
                </p>
            </div>
            <div class="actions">
                <a href="lga-coordinators-assign.php" class="btn-primary-sm">
                    <i class="fas fa-user-plus"></i> Assign Coordinator
                </a>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <select id="lgaFilter" onchange="window.location.href='?lga_id='+this.value">
                <option value="0">All LGAs</option>
                <?php foreach ($lgas as $lga): ?>
                    <option value="<?php echo $lga['id']; ?>" <?php echo $lga_filter == $lga['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($lga['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <input type="text" id="searchInput" placeholder="Search coordinators..." onkeyup="filterCoordinators()" />
            
            <span style="font-size:0.75rem;color:var(--gray-500);margin-left:auto;">
                <?php echo count($coordinators); ?> coordinators found
            </span>
        </div>

        <!-- Coordinator Grid -->
        <div class="coordinator-grid" id="coordinatorGrid">
            <?php foreach ($coordinators as $coordinator): 
                $full_name = $coordinator['first_name'] . ' ' . $coordinator['last_name'];
                $initials = strtoupper(substr($coordinator['first_name'], 0, 1) . substr($coordinator['last_name'], 0, 1));
                $status_class = $coordinator['status'];
                $online_status = $coordinator['is_online'] > 0 ? 'online' : 'offline';
                $online_label = $coordinator['is_online'] > 0 ? 'Online' : 'Offline';
                $reporting_rate = $coordinator['total_pus'] > 0 ? round(($coordinator['verified_results'] / $coordinator['total_pus']) * 100, 1) : 0;
            ?>
                <div class="coordinator-card" data-search="<?php echo strtolower($full_name . ' ' . $coordinator['email'] . ' ' . $coordinator['lga_name']); ?>">
                    <div class="card-top">
                        <div class="avatar">
                            <?php if (!empty($coordinator['photograph_url'])): ?>
                                <img src="<?php echo htmlspecialchars($coordinator['photograph_url']); ?>" alt="<?php echo htmlspecialchars($full_name); ?>" />
                            <?php else: ?>
                                <?php echo $initials; ?>
                            <?php endif; ?>
                        </div>
                        <div class="info" style="flex:1;">
                            <div class="name"><?php echo htmlspecialchars($full_name); ?></div>
                            <div class="lga"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($coordinator['lga_name']); ?></div>
                            <div style="display:flex;gap:6px;margin-top:4px;flex-wrap:wrap;">
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <span class="dot"></span>
                                    <?php echo ucfirst($coordinator['status']); ?>
                                </span>
                                <span class="status-badge <?php echo $online_status; ?>">
                                    <span class="dot"></span>
                                    <?php echo $online_label; ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="details">
                        <div class="detail-item">
                            <div class="value"><?php echo htmlspecialchars($coordinator['email'] ?? 'N/A'); ?></div>
                            <div>Email</div>
                        </div>
                        <div class="detail-item">
                            <div class="value"><?php echo htmlspecialchars($coordinator['phone'] ?? 'N/A'); ?></div>
                            <div>Phone</div>
                        </div>
                        <div class="detail-item">
                            <div class="value"><?php echo number_format($coordinator['total_pus']); ?></div>
                            <div>Total PUs</div>
                        </div>
                        <div class="detail-item">
                            <div class="value"><?php echo number_format($coordinator['verified_results']); ?></div>
                            <div>Verified Results</div>
                        </div>
                        <div class="detail-item">
                            <div class="value" style="color:<?php echo $coordinator['pending_incidents'] > 0 ? '#EF4444' : '#10B981'; ?>;">
                                <?php echo number_format($coordinator['pending_incidents']); ?>
                            </div>
                            <div>Pending Incidents</div>
                        </div>
                        <div class="detail-item">
                            <div class="value"><?php echo $reporting_rate; ?>%</div>
                            <div>Reporting Rate</div>
                        </div>
                        <div class="detail-item" style="grid-column: span 2;">
                            <div class="value" style="font-size:0.6rem;color:var(--gray-400);">
                                Last login: <?php echo $coordinator['last_login_at'] ? date('M j, Y g:i A', strtotime($coordinator['last_login_at'])) : 'Never'; ?>
                            </div>
                            <div>Activity</div>
                        </div>
                    </div>

                    <div class="actions">
                        <a href="lga-coordinators-profiles.php?id=<?php echo $coordinator['id']; ?>" class="btn-profile">
                            <i class="fas fa-id-card"></i> Profile
                        </a>
                        <a href="lga-coordinators-activity.php?id=<?php echo $coordinator['id']; ?>" class="btn-activity">
                            <i class="fas fa-clock"></i> Activity
                        </a>
                        <?php if ($coordinator['status'] === 'active'): ?>
                            <a href="lga-coordinators-suspend.php?id=<?php echo $coordinator['id']; ?>" class="btn-suspend" onclick="return confirm('Are you sure you want to suspend this coordinator?')">
                                <i class="fas fa-pause"></i> Suspend
                            </a>
                        <?php endif; ?>
                        <a href="lga-coordinators-reset-password.php?id=<?php echo $coordinator['id']; ?>" class="btn-reset">
                            <i class="fas fa-key"></i> Reset
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($coordinators)): ?>
                <div class="empty-state">
                    <i class="fas fa-user-tie"></i>
                    <h3>No Coordinators Found</h3>
                    <p>No LGA coordinators have been assigned in <?php echo htmlspecialchars($state_name); ?> yet.</p>
                    <a href="lga-coordinators-assign.php" class="btn-primary-sm" style="margin-top:12px;">
                        <i class="fas fa-user-plus"></i> Assign Coordinator
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
function filterCoordinators() {
    var input = document.getElementById('searchInput');
    var filter = input.value.toLowerCase();
    var cards = document.querySelectorAll('.coordinator-card');
    
    cards.forEach(function(card) {
        var searchData = card.dataset.search || '';
        if (searchData.includes(filter)) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}

// Same scripts as index.php
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