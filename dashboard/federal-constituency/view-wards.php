<?php
// ============================================================
// FEDERAL CONSTITUENCY COORDINATOR - VIEW WARDS
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
$lga_filter = isset($_GET['lga']) ? (int)$_GET['lga'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

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
// GET WARDS
// ============================================================
$wards = [];
$total_wards = 0;

try {
    $where = ["w.is_active = 1"];
    $params = [];
    
    if ($lga_filter > 0) {
        $where[] = "w.lga_id = ?";
        $params[] = $lga_filter;
    } elseif ($lga_list !== '0') {
        $where[] = "w.lga_id IN ($lga_list)";
    } else {
        $where[] = "1=0";
    }
    
    if (!empty($search)) {
        $where[] = "(w.name LIKE ? OR w.code LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $params[] = $tenant_id;
    $params[] = $tenant_id;
    
    $where_clause = implode(" AND ", $where);
    
    $sql = "
        SELECT 
            w.id,
            w.name,
            w.code,
            w.registered_voters,
            w.is_active,
            l.id as lga_id,
            l.name as lga_name,
            COUNT(DISTINCT pu.id) as pu_count,
            (SELECT COUNT(*) FROM users u 
             WHERE u.ward_id = w.id AND u.tenant_id = ? AND u.status = 'active') as agent_count,
            (SELECT COUNT(*) FROM results_ec8a r 
             JOIN polling_units pu2 ON r.pu_id = pu2.id 
             WHERE pu2.ward_id = w.id AND r.tenant_id = ?) as result_count
        FROM wards w
        JOIN lgas l ON w.lga_id = l.id
        LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
        WHERE $where_clause
        GROUP BY w.id, w.name, w.code, w.registered_voters, w.is_active, l.id, l.name
        ORDER BY l.name ASC, w.name ASC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $wards = $stmt->fetchAll();
    $total_wards = count($wards);
    
} catch (Exception $e) {
    error_log("Error fetching wards: " . $e->getMessage());
}

// ============================================================
// POPUP DATA HANDLING
// ============================================================
$popup = isset($_GET['popup']) ? $_GET['popup'] : '';
$ward_id = isset($_GET['ward']) ? (int)$_GET['ward'] : 0;
$pu_id = isset($_GET['pu']) ? (int)$_GET['pu'] : 0;

$popup_data = null;
$popup_pus = [];

if ($popup === 'ward-details' && $ward_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT 
                w.*,
                l.name as lga_name,
                COUNT(DISTINCT pu.id) as pu_count,
                (SELECT COUNT(*) FROM users u 
                 WHERE u.ward_id = w.id AND u.tenant_id = ? AND u.status = 'active') as agent_count
            FROM wards w
            JOIN lgas l ON w.lga_id = l.id
            LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
            WHERE w.id = ?
            GROUP BY w.id
        ");
        $stmt->execute([$tenant_id, $ward_id]);
        $popup_data = $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error fetching ward details: " . $e->getMessage());
    }
}

if ($popup === 'pus' && $ward_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT 
                pu.*,
                w.name as ward_name,
                l.name as lga_name,
                (SELECT COUNT(*) FROM agent_assignments aa WHERE aa.pu_id = pu.id AND aa.status = 'active') as agent_count,
                (SELECT COUNT(*) FROM results_ec8a r WHERE r.pu_id = pu.id AND r.tenant_id = ?) as result_count,
                (SELECT status FROM results_ec8a r WHERE r.pu_id = pu.id AND r.tenant_id = ? ORDER BY r.created_at DESC LIMIT 1) as last_result_status
            FROM polling_units pu
            JOIN wards w ON pu.ward_id = w.id
            JOIN lgas l ON w.lga_id = l.id
            WHERE pu.ward_id = ? AND pu.is_active = 1
            ORDER BY pu.name ASC
        ");
        $stmt->execute([$tenant_id, $tenant_id, $ward_id]);
        $popup_pus = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching PUs: " . $e->getMessage());
    }
}

if ($popup === 'pu-details' && $pu_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT 
                pu.*,
                w.name as ward_name,
                l.name as lga_name,
                (SELECT COUNT(*) FROM agent_assignments aa WHERE aa.pu_id = pu.id AND aa.status = 'active') as agent_count,
                (SELECT COUNT(*) FROM results_ec8a r WHERE r.pu_id = pu.id AND r.tenant_id = ?) as result_count,
                (SELECT COUNT(*) FROM incidents i WHERE i.pu_id = pu.id AND i.tenant_id = ?) as incident_count
            FROM polling_units pu
            JOIN wards w ON pu.ward_id = w.id
            JOIN lgas l ON w.lga_id = l.id
            WHERE pu.id = ?
        ");
        $stmt->execute([$tenant_id, $tenant_id, $pu_id]);
        $popup_data = $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error fetching PU details: " . $e->getMessage());
    }
}

$page_title = 'View Wards';
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
    min-width: 150px;
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
.filter-section .btn-filter:hover {
    background: var(--primary-dark);
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

.wards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}
.ward-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 18px 20px;
    transition: var(--transition);
    cursor: pointer;
}
.ward-card:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow);
    transform: translateY(-2px);
}
.ward-card .ward-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}
.ward-card .ward-header .ward-name {
    font-weight: 600;
    font-size: 0.95rem;
}
.ward-card .ward-header .ward-code {
    font-size: 0.7rem;
    color: var(--gray-400);
    background: var(--gray-100);
    padding: 2px 10px;
    border-radius: 12px;
}
.ward-card .ward-lga {
    font-size: 0.8rem;
    color: var(--gray-500);
    margin-top: 2px;
}
.ward-card .ward-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin-top: 12px;
}
.ward-card .ward-stats .stat {
    text-align: center;
    padding: 6px;
    background: var(--gray-50);
    border-radius: 6px;
}
.ward-card .ward-stats .stat .number {
    font-weight: 700;
    font-size: 1rem;
}
.ward-card .ward-stats .stat .label {
    font-size: 0.55rem;
    color: var(--gray-500);
    text-transform: uppercase;
}
.ward-card .ward-actions {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--gray-100);
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.ward-card .ward-actions button {
    font-size: 0.7rem;
    padding: 4px 12px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    border: none;
    cursor: pointer;
}
.ward-card .ward-actions .btn-view {
    background: var(--primary);
    color: white;
}
.ward-card .ward-actions .btn-view:hover {
    background: var(--primary-dark);
}
.ward-card .ward-actions .btn-pus {
    background: var(--gray-100);
    color: var(--gray-600);
}
.ward-card .ward-actions .btn-pus:hover {
    background: var(--gray-200);
}

.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}
.empty-state i {
    font-size: 2.5rem;
    color: var(--gray-300);
    display: block;
    margin-bottom: 12px;
}
.empty-state h3 {
    font-size: 1.1rem;
    color: var(--gray-700);
    margin-bottom: 4px;
}
.empty-state p {
    color: var(--gray-500);
    font-size: 0.9rem;
}

/* Popup styles - same as monitor-constituency */
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
    max-width: 900px;
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
.popup-body .pu-table-wrap { overflow-x: auto; }
.popup-body .pu-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}
.popup-body .pu-table th {
    text-align: left;
    padding: 8px 10px;
    background: var(--gray-50);
    font-weight: 600;
    color: var(--gray-600);
    font-size: 0.65rem;
    text-transform: uppercase;
    border-bottom: 2px solid var(--gray-200);
}
.popup-body .pu-table td {
    padding: 8px 10px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}
.popup-body .status-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.6rem;
    font-weight: 600;
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
.popup-footer .btn-primary {
    background: var(--primary);
    color: white;
}
.popup-footer .btn-secondary {
    background: var(--gray-100);
    color: var(--gray-600);
}

@media (max-width: 768px) {
    .filter-section { flex-direction: column; align-items: stretch; }
    .filter-section select, .filter-section input { min-width: unset; width: 100%; }
    .wards-grid { grid-template-columns: 1fr; }
    .popup-body .detail-row { grid-template-columns: 1fr; }
    .popup-container { max-width: 100%; margin: 10px; }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:4px;">
            <h2 style="font-size:1.3rem;font-weight:700;margin:0;">
                <i class="fas fa-layer-group" style="color:var(--primary);"></i> View Wards
            </h2>
            <div style="font-size:0.85rem;color:var(--gray-500);">
                <?php echo htmlspecialchars($constituency['name'] ?? 'Federal Constituency'); ?>
            </div>
        </div>
        <p style="color:var(--gray-500);font-size:0.9rem;margin-bottom:20px;">
            All wards in your federal constituency.
        </p>

        <!-- Filter -->
        <div class="filter-section">
            <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;flex:1;align-items:center;">
                <select name="lga">
                    <option value="">All LGAs</option>
                    <?php foreach ($lgas as $lga): ?>
                        <option value="<?php echo $lga['id']; ?>" <?php echo ($lga_filter == $lga['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($lga['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="search" placeholder="Search wards..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
                <a href="view-wards.php" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
            </form>
        </div>

        <!-- Results -->
        <div class="results-summary">
            <div class="count"><span><?php echo number_format($total_wards); ?></span> wards found</div>
            <?php if (!empty($search)): ?>
                <div style="font-size:0.85rem;color:var(--gray-500);">
                    Search: "<strong><?php echo htmlspecialchars($search); ?></strong>"
                </div>
            <?php endif; ?>
        </div>

        <!-- Wards Grid -->
        <div class="wards-grid">
            <?php if (count($wards) > 0): ?>
                <?php foreach ($wards as $ward): ?>
                    <div class="ward-card">
                        <div class="ward-header">
                            <div>
                                <div class="ward-name"><?php echo htmlspecialchars($ward['name']); ?></div>
                                <div class="ward-lga">
                                    <i class="fas fa-map-marker-alt" style="font-size:0.7rem;"></i>
                                    <?php echo htmlspecialchars($ward['lga_name']); ?>
                                </div>
                            </div>
                            <span class="ward-code"><?php echo htmlspecialchars($ward['code'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="ward-stats">
                            <div class="stat">
                                <div class="number"><?php echo number_format($ward['pu_count'] ?? 0); ?></div>
                                <div class="label">PUs</div>
                            </div>
                            <div class="stat">
                                <div class="number"><?php echo number_format($ward['agent_count'] ?? 0); ?></div>
                                <div class="label">Agents</div>
                            </div>
                            <div class="stat">
                                <div class="number"><?php echo number_format($ward['result_count'] ?? 0); ?></div>
                                <div class="label">Results</div>
                            </div>
                        </div>
                        <div class="ward-actions">
                            <button class="btn-view" onclick="openPopup('ward-details&ward=<?php echo $ward['id']; ?>')">
                                <i class="fas fa-eye"></i> View Details
                            </button>
                            <button class="btn-pus" onclick="openPopup('pus&ward=<?php echo $ward['id']; ?>')">
                                <i class="fas fa-flag-checkered"></i> PUs
                            </button>
                            <button class="btn-pus" onclick="window.location.href='coordinators.php?ward=<?php echo $ward['id']; ?>'">
                                <i class="fas fa-user-tie"></i> Coordinators
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Wards Found</h3>
                    <p>
                        <?php if ($lga_list === '0'): ?>
                            No LGAs are assigned to your federal constituency. 
                            Please contact your administrator to assign LGAs.
                        <?php elseif ($lga_filter > 0): ?>
                            No wards found in the selected LGA. Try selecting a different LGA.
                        <?php else: ?>
                            No wards found in your constituency. Try adjusting your filters.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Popup -->
<div class="popup-overlay" id="popupOverlay" onclick="if(event.target===this) closePopup()">
    <div class="popup-container">
        <div class="popup-header">
            <h3 id="popupTitle">Details</h3>
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
    var wardId = params.get('ward') || 0;
    var puId = params.get('pu') || 0;
    
    var titles = {
        'ward-details': 'Ward Details',
        'pus': 'Polling Units',
        'pu-details': 'Polling Unit Details'
    };
    title.textContent = titles[popup] || 'Details';
    
    var url = window.location.pathname + '?popup=' + popup;
    if (wardId) url += '&ward=' + wardId;
    if (puId) url += '&pu=' + puId;
    
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