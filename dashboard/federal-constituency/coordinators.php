<?php
// ============================================================
// FEDERAL CONSTITUENCY COORDINATOR - COORDINATORS
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'federal_constituency') {
    header('Location: ../client-admin/');
    exit();
}

$constituency_id = SessionManager::get('federal_constituency_id');
$tenant_id = SessionManager::get('tenant_id');
$db = getDB();

// Get LGA IDs
$lga_ids = [];
try {
    $stmt = $db->prepare("SELECT lgas_json FROM federal_constituencies WHERE id = ?");
    $stmt->execute([$constituency_id]);
    $lgas_json = $stmt->fetchColumn();
    if ($lgas_json) {
        $lga_ids = json_decode($lgas_json, true) ?: [];
    }
} catch (Exception $e) {
    error_log("Error fetching LGA IDs: " . $e->getMessage());
}

$lga_list = !empty($lga_ids) ? implode(',', array_map('intval', $lga_ids)) : '0';

// Get filters
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$lga_filter = isset($_GET['lga']) ? (int)$_GET['lga'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get LGAs for filter
$lgas = [];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("SELECT id, name FROM lgas WHERE id IN ($lga_list) ORDER BY name ASC");
        $stmt->execute();
        $lgas = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching LGAs: " . $e->getMessage());
}

// Get coordinators
$coordinators = [];
$total = 0;
try {
    $where = ["u.tenant_id = ?", "u.status = 'active'"];
    $params = [$tenant_id];
    
    if ($role_filter) {
        $where[] = "r.level = ?";
        $params[] = $role_filter;
    } else {
        $where[] = "r.level IN ('lga', 'ward')";
    }
    
    if ($lga_filter > 0) {
        $where[] = "u.lga_id = ?";
        $params[] = $lga_filter;
    } elseif ($lga_list !== '0') {
        $where[] = "u.lga_id IN ($lga_list)";
    } else {
        $where[] = "1=0";
    }
    
    if (!empty($search)) {
        $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_clause = implode(" AND ", $where);
    
    $stmt = $db->prepare("
        SELECT 
            u.*,
            r.name as role_name,
            r.level as role_level,
            l.name as lga_name,
            w.name as ward_name,
            (SELECT COUNT(*) FROM users u2 
             WHERE u2.created_by = u.id AND u2.status = 'active') as subordinates_count
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN lgas l ON u.lga_id = l.id
        LEFT JOIN wards w ON u.ward_id = w.id
        WHERE $where_clause
        ORDER BY r.level ASC, u.full_name ASC
        LIMIT 100
    ");
    $stmt->execute($params);
    $coordinators = $stmt->fetchAll();
    $total = count($coordinators);
} catch (Exception $e) {
    error_log("Error fetching coordinators: " . $e->getMessage());
}

$page_title = 'Coordinators';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.filter-section {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 14px 18px;
    margin-bottom: 20px;
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}
.filter-section select,
.filter-section input {
    padding: 8px 14px;
    border: 1.5px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.85rem;
    background: white;
}
.filter-section select:focus,
.filter-section input:focus {
    outline: none;
    border-color: var(--primary);
}
.filter-section .btn-filter {
    padding: 8px 20px;
    border: none;
    border-radius: 8px;
    background: var(--primary);
    color: white;
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
}
.filter-section .btn-reset {
    padding: 8px 18px;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    background: white;
    color: var(--gray-600);
    font-weight: 500;
    font-size: 0.8rem;
    text-decoration: none;
}

.results-summary {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 12px 20px;
    margin-bottom: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    font-size: 0.85rem;
}
.results-summary .count {
    font-weight: 600;
    color: var(--gray-700);
}
.results-summary .count span {
    color: var(--primary);
}

.coordinator-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px 20px;
    margin-bottom: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    transition: var(--transition);
}
.coordinator-card:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow);
}
.coordinator-card .coordinator-info {
    display: flex;
    align-items: center;
    gap: 14px;
}
.coordinator-card .coordinator-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    font-weight: 700;
    flex-shrink: 0;
}
.coordinator-card .coordinator-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}
.coordinator-card .coordinator-details .name {
    font-weight: 600;
    font-size: 0.95rem;
}
.coordinator-card .coordinator-details .email {
    font-size: 0.8rem;
    color: var(--gray-500);
}
.coordinator-card .coordinator-details .location {
    font-size: 0.75rem;
    color: var(--gray-400);
}
.coordinator-card .coordinator-meta {
    text-align: right;
}
.coordinator-card .coordinator-meta .role-badge {
    display: inline-block;
    padding: 2px 12px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
}
.coordinator-card .coordinator-meta .role-badge.lga { background: #DBEAFE; color: #2563EB; }
.coordinator-card .coordinator-meta .role-badge.ward { background: #D1FAE5; color: #059669; }
.coordinator-card .coordinator-meta .subordinates {
    font-size: 0.75rem;
    color: var(--gray-400);
    margin-top: 4px;
}
.coordinator-card .coordinator-actions {
    display: flex;
    gap: 6px;
}
.coordinator-card .coordinator-actions a {
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 500;
    text-decoration: none;
}
.coordinator-card .coordinator-actions .btn-view {
    background: var(--gray-100);
    color: var(--gray-600);
}
.coordinator-card .coordinator-actions .btn-view:hover {
    background: var(--gray-200);
}
.coordinator-card .coordinator-actions .btn-profile {
    background: var(--primary);
    color: white;
}
.coordinator-card .coordinator-actions .btn-profile:hover {
    background: var(--primary-dark);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--gray-500);
}
.empty-state i {
    font-size: 3rem;
    color: var(--gray-300);
    display: block;
    margin-bottom: 12px;
}
.empty-state h3 {
    font-size: 1.1rem;
    color: var(--gray-700);
    margin-bottom: 4px;
}

@media (max-width: 768px) {
    .filter-section {
        flex-direction: column;
        align-items: stretch;
    }
    .coordinator-card {
        flex-direction: column;
        align-items: stretch;
        text-align: center;
    }
    .coordinator-card .coordinator-info {
        flex-direction: column;
    }
    .coordinator-card .coordinator-meta {
        text-align: center;
    }
    .coordinator-card .coordinator-actions {
        justify-content: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <h2 style="font-size:1.3rem;font-weight:700;margin-bottom:4px;">
            <i class="fas fa-user-tie" style="color:var(--primary);"></i> Coordinators
        </h2>
        <p style="color:var(--gray-500);font-size:0.9rem;margin-bottom:20px;">
            Manage LGA and Ward coordinators in your constituency.
        </p>

        <!-- Filter -->
        <div class="filter-section">
            <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;flex:1;align-items:center;">
                <select name="role">
                    <option value="">All Roles</option>
                    <option value="lga" <?php echo ($role_filter === 'lga') ? 'selected' : ''; ?>>LGA Coordinators</option>
                    <option value="ward" <?php echo ($role_filter === 'ward') ? 'selected' : ''; ?>>Ward Coordinators</option>
                </select>
                <select name="lga">
                    <option value="">All LGAs</option>
                    <?php foreach ($lgas as $lga): ?>
                        <option value="<?php echo $lga['id']; ?>" <?php echo ($lga_filter == $lga['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($lga['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="search" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
                <a href="coordinators.php" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
            </form>
        </div>

        <!-- Results -->
        <div class="results-summary">
            <div class="count"><span><?php echo number_format($total); ?></span> coordinators found</div>
            <?php if ($total >= 100): ?>
                <div style="font-size:0.75rem;color:var(--gray-400);">
                    <i class="fas fa-info-circle"></i> Showing first 100
                </div>
            <?php endif; ?>
        </div>

        <!-- Coordinators -->
        <?php if (count($coordinators) > 0): ?>
            <?php foreach ($coordinators as $coordinator): ?>
                <div class="coordinator-card">
                    <div class="coordinator-info">
                        <div class="coordinator-avatar">
                            <?php if (!empty($coordinator['photograph_url'])): ?>
                                <img src="<?php echo htmlspecialchars($coordinator['photograph_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($coordinator['full_name']); ?>">
                            <?php else: ?>
                                <?php echo strtoupper(substr($coordinator['first_name'] ?? 'U', 0, 1) . substr($coordinator['last_name'] ?? 'R', 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="coordinator-details">
                            <div class="name"><?php echo htmlspecialchars($coordinator['full_name']); ?></div>
                            <div class="email">
                                <i class="fas fa-envelope" style="font-size:0.65rem;"></i>
                                <?php echo htmlspecialchars($coordinator['email']); ?>
                            </div>
                            <div class="location">
                                <i class="fas fa-map-marker-alt" style="font-size:0.65rem;"></i>
                                <?php 
                                $location = [];
                                if ($coordinator['lga_name']) $location[] = $coordinator['lga_name'];
                                if ($coordinator['ward_name']) $location[] = $coordinator['ward_name'];
                                echo htmlspecialchars(implode(' → ', $location) ?: 'No location');
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="coordinator-meta">
                        <span class="role-badge <?php echo $coordinator['role_level']; ?>">
                            <?php echo htmlspecialchars($coordinator['role_name']); ?>
                        </span>
                        <div class="subordinates">
                            <i class="fas fa-users"></i>
                            <?php echo number_format($coordinator['subordinates_count'] ?? 0); ?> subordinates
                        </div>
                    </div>
                    <div class="coordinator-actions">
                        <a href="coordinator-profile.php?id=<?php echo $coordinator['id']; ?>" class="btn-profile">
                            <i class="fas fa-id-card"></i> Profile
                        </a>
                        <a href="coordinator-activity.php?id=<?php echo $coordinator['id']; ?>" class="btn-view">
                            <i class="fas fa-clock"></i> Activity
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-user-tie"></i>
                <h3>No Coordinators Found</h3>
                <p>No coordinators match your filter criteria.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
// Sidebar toggle (standard)
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