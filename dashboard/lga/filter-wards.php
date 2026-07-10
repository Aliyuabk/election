<?php
// ============================================================
// LGA COORDINATOR - FILTER BY WARD
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

// Get filters
$ward_filter = isset($_GET['ward_id']) ? (int)$_GET['ward_id'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';

// Get LGA name
$lga_name = 'LGA';
try {
    if ($lga_id) {
        $stmt = $db->prepare("SELECT name FROM lgas WHERE id = ?");
        $stmt->execute([$lga_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $lga_name = $result['name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching LGA: " . $e->getMessage());
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

// Fetch filtered users
$filtered_users = [];
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
            u.created_at,
            r.name as role_name,
            r.level as role_level,
            w.name as ward_name,
            pu.name as pu_name,
            (SELECT COUNT(*) FROM user_sessions us WHERE us.user_id = u.id AND us.is_active = 1 AND us.last_activity_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)) as is_online
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN wards w ON u.ward_id = w.id
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        WHERE u.tenant_id = ?
        AND u.lga_id = ?
        AND u.deleted_at IS NULL
        AND r.level IN ('ward', 'pu_agent', 'party_agent')
    ";
    $params = [$tenant_id, $lga_id];
    
    if ($ward_filter > 0) {
        $sql .= " AND u.ward_id = ?";
        $params[] = $ward_filter;
    }
    
    if (!empty($status_filter)) {
        $sql .= " AND u.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($role_filter)) {
        $sql .= " AND r.level = ?";
        $params[] = $role_filter;
    }
    
    $sql .= " ORDER BY w.name ASC, u.first_name ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $filtered_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching filtered users: " . $e->getMessage());
}

$role_labels = [
    'ward' => 'Ward Coordinator',
    'pu_agent' => 'PU Agent',
    'party_agent' => 'Party Agent'
];

$page_title = 'Filter by Ward';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.filter-container {
    max-width: 900px;
    margin: 0 auto;
}

.filter-box {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px 24px;
    margin-bottom: 20px;
}

.filter-box .filter-row {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: flex-end;
}

.filter-box .filter-group {
    flex: 1;
    min-width: 160px;
}

.filter-box .filter-group label {
    display: block;
    font-weight: 600;
    font-size: 0.7rem;
    color: var(--gray-600);
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.filter-box .filter-group select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.8rem;
    font-family: 'Inter', sans-serif;
    background: white;
}

.filter-box .filter-group select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.filter-box .btn-filter {
    padding: 8px 24px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.filter-box .btn-filter:hover {
    background: var(--primary-dark);
}

.filter-box .btn-clear {
    padding: 8px 24px;
    background: var(--gray-100);
    color: var(--gray-700);
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.8rem;
    text-decoration: none;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.filter-box .btn-clear:hover {
    background: var(--gray-200);
}

.results-count {
    font-size: 0.85rem;
    color: var(--gray-500);
    margin-bottom: 16px;
}

.results-count strong {
    color: var(--gray-700);
}

.user-item {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 14px 18px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 14px;
    transition: var(--transition);
}

.user-item:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow-hover);
}

.user-item .avatar {
    width: 40px;
    height: 40px;
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

.user-item .info {
    flex: 1;
}

.user-item .info .name {
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--gray-800);
}

.user-item .info .role {
    font-size: 0.7rem;
    color: var(--primary);
}

.user-item .info .location {
    font-size: 0.65rem;
    color: var(--gray-500);
}

.user-item .badges {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.5rem;
    padding: 2px 8px;
    border-radius: 8px;
    font-weight: 600;
}

.status-badge .dot {
    width: 4px;
    height: 4px;
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

.empty-state {
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
    .filter-box .filter-row {
        flex-direction: column;
    }
    .filter-box .filter-group {
        width: 100%;
        min-width: unset;
    }
    .filter-box .btn-filter,
    .filter-box .btn-clear {
        width: 100%;
        text-align: center;
    }
    .user-item {
        flex-direction: column;
        text-align: center;
    }
    .user-item .badges {
        justify-content: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="filter-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-filter"></i> Filter by Ward</h1>
                    <p class="subtitle">
                        <i class="fas fa-map-marker-alt"></i> 
                        <?php echo htmlspecialchars($lga_name); ?> LGA - Filter Coordinators and Agents
                    </p>
                </div>
                <div class="actions">
                    <a href="manage-wards.php" class="btn-secondary-sm">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>

            <!-- Filter Form -->
            <div class="filter-box">
                <form method="GET" action="">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Ward</label>
                            <select name="ward_id">
                                <option value="0">All Wards</option>
                                <?php foreach ($wards as $w): ?>
                                    <option value="<?php echo $w['id']; ?>" <?php echo $ward_filter == $w['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($w['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>Role</label>
                            <select name="role">
                                <option value="">All Roles</option>
                                <?php foreach ($role_labels as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $role_filter === $key ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            </select>
                        </div>
                        
                        <div style="display:flex;gap:8px;">
                            <button type="submit" class="btn-filter">
                                <i class="fas fa-filter"></i> Apply
                            </button>
                            <a href="filter-wards.php" class="btn-clear">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Results -->
            <div class="results-count">
                <strong><?php echo count($filtered_users); ?></strong> user<?php echo count($filtered_users) !== 1 ? 's' : ''; ?> found
                <?php if ($ward_filter > 0): ?>
                    in <strong><?php 
                        foreach ($wards as $w) {
                            if ($w['id'] == $ward_filter) {
                                echo htmlspecialchars($w['name']);
                                break;
                            }
                        }
                    ?></strong>
                <?php endif; ?>
            </div>

            <?php if (!empty($filtered_users)): ?>
                <?php foreach ($filtered_users as $user): 
                    $full_name = $user['first_name'] . ' ' . $user['last_name'];
                    $initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
                    $online_status = $user['is_online'] > 0 ? 'online' : 'offline';
                    $role_display = $role_labels[$user['role_level']] ?? $user['role_name'];
                    $location = $user['pu_name'] ?? $user['ward_name'] ?? 'N/A';
                ?>
                    <div class="user-item">
                        <div class="avatar"><?php echo $initials; ?></div>
                        
                        <div class="info">
                            <div class="name"><?php echo htmlspecialchars($full_name); ?></div>
                            <div class="role"><?php echo htmlspecialchars($role_display); ?></div>
                            <div class="location">
                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($location); ?>
                                <?php if (!empty($user['email'])): ?>
                                    <span style="margin:0 4px;">•</span>
                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="badges">
                            <span class="status-badge <?php echo $user['status']; ?>">
                                <span class="dot"></span> <?php echo ucfirst($user['status']); ?>
                            </span>
                            <span class="status-badge <?php echo $online_status; ?>">
                                <span class="dot"></span> <?php echo ucfirst($online_status); ?>
                            </span>
                        </div>
                        
                        <a href="coordinator-profile.php?id=<?php echo $user['id']; ?>" style="padding:4px 14px;border-radius:6px;font-size:0.65rem;font-weight:500;text-decoration:none;background:var(--primary);color:white;transition:var(--transition);">
                            <i class="fas fa-eye"></i> View
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No Users Found</h3>
                    <p>No users match the selected filters.</p>
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