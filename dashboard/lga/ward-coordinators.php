<?php
// ============================================================
// LGA COORDINATOR - WARD COORDINATORS
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'lga') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'LGA Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$lga_id = SessionManager::get('lga_id');
$ward_filter = isset($_GET['ward_id']) ? (int)$_GET['ward_id'] : 0;

if (empty($lga_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT lga_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['lga_id'])) {
            $lga_id = $user['lga_id'];
            SessionManager::set('lga_id', $lga_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching lga_id: " . $e->getMessage());
    }
}

$db = getDB();

// Get LGA name
$lga_name = 'LGA';
$state_name = 'State';
try {
    if ($lga_id) {
        $stmt = $db->prepare("
            SELECT l.name as lga_name, s.name as state_name 
            FROM lgas l 
            JOIN states s ON l.state_id = s.id 
            WHERE l.id = ?
        ");
        $stmt->execute([$lga_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $lga_name = $result['lga_name'];
            $state_name = $result['state_name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching LGA/State: " . $e->getMessage());
}

// Get wards for filter
$wards = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM wards WHERE lga_id = ? AND is_active = 1 ORDER BY name ASC");
    $stmt->execute([$lga_id]);
    $wards = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching wards: " . $e->getMessage());
}

// Fetch ward coordinators
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
            u.status,
            u.last_login_at,
            u.created_at,
            w.name as ward_name,
            w.id as ward_id,
            COUNT(DISTINCT pu.id) as total_pus,
            COUNT(DISTINCT r.id) as verified_results,
            (SELECT COUNT(*) FROM user_sessions us WHERE us.user_id = u.id AND us.is_active = 1 AND us.last_activity_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)) as is_online
        FROM users u
        JOIN roles r ON u.role_id = r.id
        JOIN wards w ON u.ward_id = w.id
        LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
        LEFT JOIN results_ec8a ra ON ra.pu_id = pu.id AND ra.status IN ('verified', 'approved')
        WHERE r.level = 'ward'
        AND u.lga_id = ?
        AND u.deleted_at IS NULL
    ";
    $params = [$lga_id];
    
    if ($ward_filter > 0) {
        $sql .= " AND u.ward_id = ?";
        $params[] = $ward_filter;
    }
    
    $sql .= " GROUP BY u.id, u.user_code, u.first_name, u.last_name, u.email, u.phone, u.status, u.last_login_at, u.created_at, w.name, w.id
              ORDER BY w.name ASC, u.first_name ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $coordinators = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching coordinators: " . $e->getMessage());
}

$page_title = 'Ward Coordinators';
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
    background: white;
    padding: 12px 18px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}

.filter-bar select {
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.8rem;
    font-family: 'Inter', sans-serif;
    background: white;
    min-width: 180px;
}

.filter-bar select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.filter-bar .filter-info {
    font-size: 0.75rem;
    color: var(--gray-500);
    margin-left: auto;
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
    padding: 16px 18px;
    transition: var(--transition);
}

.coordinator-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-hover);
}

.coordinator-card .card-top {
    display: flex;
    align-items: center;
    gap: 12px;
}

.coordinator-card .avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.coordinator-card .info .name {
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--gray-800);
}

.coordinator-card .info .ward {
    font-size: 0.7rem;
    color: var(--primary);
    font-weight: 500;
}

.coordinator-card .info .ward i {
    margin-right: 4px;
}

.coordinator-card .badges {
    display: flex;
    gap: 6px;
    margin: 8px 0;
    flex-wrap: wrap;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.55rem;
    padding: 2px 10px;
    border-radius: 10px;
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

.status-badge.online { background: #ECFDF5; color: #065F46; }
.status-badge.online .dot { background: #10B981; animation: pulse-dot 1.5s ease-in-out infinite; }
.status-badge.offline { background: #F3F4F6; color: #6B7280; }
.status-badge.offline .dot { background: #9CA3AF; }

@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(0.8); }
}

.coordinator-card .details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4px;
    margin: 8px 0;
    padding: 8px 0;
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

.coordinator-card .actions {
    display: flex;
    gap: 6px;
    margin-top: 8px;
    flex-wrap: wrap;
}

.coordinator-card .actions a {
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 0.6rem;
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

.coordinator-card .actions .btn-reset {
    background: #FFFBEB;
    color: #D97706;
}

.coordinator-card .actions .btn-reset:hover {
    background: #FEF3C7;
}

.coordinator-card .actions .btn-suspend {
    background: #FEF2F2;
    color: #DC2626;
}

.coordinator-card .actions .btn-suspend:hover {
    background: #FEE2E2;
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
        align-items: stretch;
    }
    .filter-bar select {
        width: 100%;
        min-width: unset;
    }
    .filter-bar .filter-info {
        margin-left: 0;
        text-align: center;
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
                <h1><i class="fas fa-user-tie"></i> Ward Coordinators</h1>
                <p class="subtitle">
                    <i class="fas fa-map-marker-alt"></i> 
                    <?php echo htmlspecialchars($lga_name); ?> LGA - Manage Ward Coordinators
                </p>
            </div>
            <div class="actions">
                <a href="manage-wards.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Wards
                </a>
            </div>
        </div>

        <!-- Filter -->
        <div class="filter-bar">
            <select id="wardFilter" onchange="window.location.href='?ward_id='+this.value">
                <option value="0">All Wards</option>
                <?php foreach ($wards as $w): ?>
                    <option value="<?php echo $w['id']; ?>" <?php echo $ward_filter == $w['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($w['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <span class="filter-info">
                <i class="fas fa-list"></i> <?php echo count($coordinators); ?> coordinators found
            </span>
        </div>

        <!-- Coordinator Grid -->
        <div class="coordinator-grid">
            <?php foreach ($coordinators as $coordinator): 
                $full_name = $coordinator['first_name'] . ' ' . $coordinator['last_name'];
                $initials = strtoupper(substr($coordinator['first_name'], 0, 1) . substr($coordinator['last_name'], 0, 1));
                $online_status = $coordinator['is_online'] > 0 ? 'online' : 'offline';
                $online_label = $coordinator['is_online'] > 0 ? 'Online' : 'Offline';
            ?>
                <div class="coordinator-card">
                    <div class="card-top">
                        <div class="avatar"><?php echo $initials; ?></div>
                        <div class="info">
                            <div class="name"><?php echo htmlspecialchars($full_name); ?></div>
                            <div class="ward"><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($coordinator['ward_name']); ?></div>
                        </div>
                    </div>

                    <div class="badges">
                        <span class="status-badge <?php echo $coordinator['status']; ?>">
                            <span class="dot"></span> <?php echo ucfirst($coordinator['status']); ?>
                        </span>
                        <span class="status-badge <?php echo $online_status; ?>">
                            <span class="dot"></span> <?php echo $online_label; ?>
                        </span>
                    </div>

                    <div class="details">
                        <div class="detail-item">
                            <div class="value"><?php echo number_format($coordinator['total_pus']); ?></div>
                            <div>Polling Units</div>
                        </div>
                        <div class="detail-item">
                            <div class="value"><?php echo number_format($coordinator['verified_results']); ?></div>
                            <div>Verified Results</div>
                        </div>
                        <div class="detail-item" style="grid-column: span 2;">
                            <div class="value" style="font-size:0.6rem;color:var(--gray-400);">
                                <?php echo htmlspecialchars($coordinator['email'] ?? 'N/A'); ?>
                            </div>
                            <div>Email</div>
                        </div>
                    </div>

                    <div class="actions">
                        <a href="coordinator-profile.php?id=<?php echo $coordinator['id']; ?>" class="btn-profile">
                            <i class="fas fa-id-card"></i> Profile
                        </a>
                        <a href="reset-password.php?id=<?php echo $coordinator['id']; ?>" class="btn-reset">
                            <i class="fas fa-key"></i> Reset
                        </a>
                        <?php if ($coordinator['status'] === 'active'): ?>
                            <a href="suspend-coordinator.php?id=<?php echo $coordinator['id']; ?>" class="btn-suspend" onclick="return confirm('Are you sure?')">
                                <i class="fas fa-pause"></i> Suspend
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($coordinators)): ?>
                <div class="empty-state">
                    <i class="fas fa-user-tie"></i>
                    <h3>No Coordinators Found</h3>
                    <p>No ward coordinators have been assigned in <?php echo htmlspecialchars($lga_name); ?>.</p>
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