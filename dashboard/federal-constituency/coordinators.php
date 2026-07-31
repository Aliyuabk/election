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

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$constituency_id = SessionManager::get('federal_constituency_id');
$state_id = SessionManager::get('state_id');
$tenant_id = SessionManager::get('tenant_id');

$db = getDB();

// ============================================================
// GET LGA IDs
// ============================================================
$lga_ids = [];
try {
    if ($constituency_id) {
        $stmt = $db->prepare("SELECT lgas_json FROM federal_constituencies WHERE id = ?");
        $stmt->execute([$constituency_id]);
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
}

$lga_list = !empty($lga_ids) ? implode(',', array_map('intval', $lga_ids)) : '0';

// ============================================================
// GET FILTERS
// ============================================================
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$lga_filter = isset($_GET['lga']) ? (int)$_GET['lga'] : 0;
$ward_filter = isset($_GET['ward']) ? (int)$_GET['ward'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// ============================================================
// GET LGAS FOR FILTER
// ============================================================
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

// ============================================================
// GET COORDINATORS
// ============================================================
$coordinators = [];
$total_coordinators = 0;

try {
    $where = ["u.tenant_id = ?", "u.status = 'active'"];
    $params = [$tenant_id];
    
    if (!empty($role_filter)) {
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
    
    if ($ward_filter > 0) {
        $where[] = "u.ward_id = ?";
        $params[] = $ward_filter;
    }
    
    if (!empty($search)) {
        $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_clause = implode(" AND ", $where);
    
    // Get total count
    $count_sql = "SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE $where_clause";
    $count_params = array_slice($params, 0);
    $stmt = $db->prepare($count_sql);
    $stmt->execute($count_params);
    $total_coordinators = (int)$stmt->fetchColumn();
    
    // Get data
    $params[] = $limit;
    $params[] = $offset;
    
    $sql = "
        SELECT 
            u.*,
            r.name as role_name,
            r.level as role_level,
            l.name as lga_name,
            w.name as ward_name,
            (SELECT COUNT(*) FROM users u2 WHERE u2.created_by = u.id AND u2.status = 'active') as subordinates_count
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN lgas l ON u.lga_id = l.id
        LEFT JOIN wards w ON u.ward_id = w.id
        WHERE $where_clause
        ORDER BY r.level ASC, u.full_name ASC
        LIMIT ? OFFSET ?
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $coordinators = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error fetching coordinators: " . $e->getMessage());
}

// ============================================================
// POPUP DATA HANDLING
// ============================================================
$popup = isset($_GET['popup']) ? $_GET['popup'] : '';
$coordinator_id = isset($_GET['coordinator']) ? (int)$_GET['coordinator'] : 0;

$popup_data = null;

if ($popup === 'coordinator-details' && $coordinator_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT 
                u.*,
                r.name as role_name,
                r.level as role_level,
                l.name as lga_name,
                w.name as ward_name,
                pu.name as pu_name,
                s.name as state_name
            FROM users u
            JOIN roles r ON u.role_id = r.id
            LEFT JOIN lgas l ON u.lga_id = l.id
            LEFT JOIN wards w ON u.ward_id = w.id
            LEFT JOIN polling_units pu ON u.pu_id = pu.id
            LEFT JOIN states s ON u.state_id = s.id
            WHERE u.id = ?
        ");
        $stmt->execute([$coordinator_id]);
        $popup_data = $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error fetching coordinator details: " . $e->getMessage());
    }
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
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    font-weight: 700;
    flex-shrink: 0;
    overflow: hidden;
}
.coordinator-card .coordinator-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
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
.coordinator-card .coordinator-actions button {
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 500;
    text-decoration: none;
    border: none;
    cursor: pointer;
}
.coordinator-card .coordinator-actions .btn-profile {
    background: var(--primary);
    color: white;
}
.coordinator-card .coordinator-actions .btn-profile:hover {
    background: var(--primary-dark);
}
.coordinator-card .coordinator-actions .btn-view {
    background: var(--gray-100);
    color: var(--gray-600);
}
.coordinator-card .coordinator-actions .btn-view:hover {
    background: var(--gray-200);
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 6px;
    margin-top: 16px;
    flex-wrap: wrap;
}
.pagination a,
.pagination span {
    padding: 6px 12px;
    border: 1px solid var(--gray-200);
    border-radius: 6px;
    text-decoration: none;
    color: var(--gray-600);
    font-size: 0.8rem;
    transition: var(--transition);
}
.pagination a:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
.pagination .active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
.pagination .disabled {
    opacity: 0.5;
    cursor: not-allowed;
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

/* Popup styles */
.popup-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: flex-start;
    justify-content: center;
    overflow-y: auto;
    padding: 40px 20px;
}
.popup-overlay.active { display: flex; }
.popup-container {
    background: white;
    border-radius: var(--radius);
    max-width: 700px;
    width: 100%;
    margin: auto;
    animation: popupSlideIn 0.3s ease;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
@keyframes popupSlideIn {
    from { opacity: 0; transform: translateY(-20px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.popup-header {
    padding: 16px 24px;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.popup-header h3 { font-size: 1.1rem; font-weight: 700; margin: 0; }
.popup-header .popup-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: var(--gray-400);
    cursor: pointer;
    transition: var(--transition);
    padding: 4px 8px;
}
.popup-header .popup-close:hover { color: var(--gray-600); }
.popup-body { padding: 24px; max-height: 70vh; overflow-y: auto; }
.popup-body .detail-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.popup-body .detail-item .label {
    font-size: 0.7rem;
    color: var(--gray-400);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.popup-body .detail-item .value {
    font-size: 0.95rem;
    font-weight: 500;
}
.popup-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--gray-200);
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.popup-footer .btn {
    padding: 8px 18px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.8rem;
    text-decoration: none;
    border: none;
    cursor: pointer;
}
.popup-footer .btn-secondary {
    background: var(--gray-100);
    color: var(--gray-600);
}

@media (max-width: 768px) {
    .filter-section { flex-direction: column; align-items: stretch; }
    .coordinator-card { flex-direction: column; align-items: stretch; text-align: center; }
    .coordinator-card .coordinator-info { flex-direction: column; }
    .coordinator-card .coordinator-meta { text-align: center; }
    .coordinator-card .coordinator-actions { justify-content: center; }
    .popup-body .detail-row { grid-template-columns: 1fr; }
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
            <div class="count"><span><?php echo number_format($total_coordinators); ?></span> coordinators found</div>
            <?php if ($total_coordinators > $limit): ?>
                <div style="font-size:0.75rem;color:var(--gray-400);">
                    Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $limit, $total_coordinators); ?> of <?php echo number_format($total_coordinators); ?>
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
                            <div class="email"><i class="fas fa-envelope" style="font-size:0.65rem;"></i> <?php echo htmlspecialchars($coordinator['email']); ?></div>
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
                        <button class="btn-profile" onclick="openPopup('coordinator-details&coordinator=<?php echo $coordinator['id']; ?>')">
                            <i class="fas fa-id-card"></i> Profile
                        </button>
                        <button class="btn-view" onclick="window.location.href='coordinator-activity.php?id=<?php echo $coordinator['id']; ?>'">
                            <i class="fas fa-clock"></i> Activity
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Pagination -->
            <?php if ($total_coordinators > $limit): ?>
                <div class="pagination">
                    <?php
                    $total_pages = ceil($total_coordinators / $limit);
                    $query_params = $_GET;
                    unset($query_params['page']);
                    $base_url = '?' . http_build_query($query_params);
                    
                    if ($page > 1): ?>
                        <a href="<?php echo $base_url; ?>&page=<?php echo $page - 1; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="<?php echo $base_url; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="<?php echo $base_url; ?>&page=<?php echo $page + 1; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-user-tie"></i>
                <h3>No Coordinators Found</h3>
                <p>No coordinators match your filter criteria.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Popup -->
<div class="popup-overlay" id="popupOverlay" onclick="if(event.target===this) closePopup()">
    <div class="popup-container">
        <div class="popup-header">
            <h3 id="popupTitle">Coordinator Details</h3>
            <button class="popup-close" onclick="closePopup()">&times;</button>
        </div>
        <div class="popup-body" id="popupBody">
            <div id="popupContent">
                <div style="text-align:center;padding:40px;color:var(--gray-400);">
                    <i class="fas fa-spinner fa-spin" style="font-size:2rem;"></i>
                    <p>Loading...</p>
                </div>
            </div>
        </div>
        <div class="popup-footer">
            <button class="btn btn-secondary" onclick="closePopup()">Close</button>
        </div>
    </div>
</div>

<script>
function openPopup(action) {
    var overlay = document.getElementById('popupOverlay');
    var content = document.getElementById('popupContent');
    var title = document.getElementById('popupTitle');
    
    overlay.classList.add('active');
    content.innerHTML = '<div style="text-align:center;padding:40px;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:2rem;"></i><p>Loading...</p></div>';
    
    var params = new URLSearchParams(action);
    var popup = params.get('popup') || action.split('&')[0];
    var coordinatorId = params.get('coordinator') || 0;
    
    title.textContent = 'Coordinator Details';
    
    var url = window.location.pathname + '?popup=' + popup;
    if (coordinatorId) url += '&coordinator=' + coordinatorId;
    
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(response => response.text())
        .then(html => {
            var parser = new DOMParser();
            var doc = parser.parseFromString(html, 'text/html');
            var bodyContent = doc.querySelector('.popup-body-content');
            if (bodyContent) {
                content.innerHTML = bodyContent.innerHTML;
            } else {
                content.innerHTML = html;
            }
        })
        .catch(function() {
            content.innerHTML = '<div style="text-align:center;padding:40px;color:var(--gray-500);"><i class="fas fa-exclamation-circle" style="font-size:2rem;color:var(--gray-300);display:block;margin-bottom:8px;"></i><p>Failed to load content.</p></div>';
        });
}

function closePopup() {
    document.getElementById('popupOverlay').classList.remove('active');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closePopup();
});

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