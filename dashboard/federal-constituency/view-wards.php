<?php
// ============================================================
// FEDERAL CONSTITUENCY COORDINATOR - VIEW WARDS (FIXED)
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
$state_id = SessionManager::get('state_id');
$tenant_id = SessionManager::get('tenant_id');
$db = getDB();

error_log("View Wards - Session: constituency_id=$constituency_id, state_id=$state_id, tenant_id=$tenant_id");

// ============================================================
// GET CONSTITUENCY DETAILS
// ============================================================
$constituency = null;
try {
    if ($constituency_id) {
        $stmt = $db->prepare("
            SELECT fc.*, s.name as state_name 
            FROM federal_constituencies fc 
            JOIN states s ON fc.state_id = s.id 
            WHERE fc.id = ?
        ");
        $stmt->execute([$constituency_id]);
        $constituency = $stmt->fetch();
        error_log("View Wards - Constituency found: " . ($constituency ? $constituency['name'] : 'NULL'));
        if ($constituency) {
            error_log("View Wards - LGA JSON: " . ($constituency['lgas_json'] ?? 'NULL'));
        }
    }
} catch (Exception $e) {
    error_log("Error fetching constituency: " . $e->getMessage());
}

// ============================================================
// GET LGA IDs - IMPROVED WITH FALLBACK
// ============================================================
$lga_ids = [];
$lga_list = '0';
$lgas_for_filter = [];

if ($constituency && !empty($constituency['lgas_json'])) {
    try {
        $lga_names = json_decode($constituency['lgas_json'], true);
        
        if (is_array($lga_names) && !empty($lga_names)) {
            error_log("View Wards - Parsed LGA names: " . print_r($lga_names, true));
            
            // First, try to find LGAs by name
            $placeholders = implode(',', array_fill(0, count($lga_names), '?'));
            $stmt = $db->prepare("
                SELECT id, name 
                FROM lgas 
                WHERE name IN ($placeholders) 
                AND state_id = ? 
                AND is_active = 1
            ");
            $params = array_merge($lga_names, [$state_id]);
            $stmt->execute($params);
            $found_lgas = $stmt->fetchAll();
            
            if (!empty($found_lgas)) {
                foreach ($found_lgas as $l) {
                    $lga_ids[] = $l['id'];
                    $lgas_for_filter[] = $l;
                }
                error_log("View Wards - Found LGAs by name: " . print_r($lga_ids, true));
            } else {
                // ============================================================
                // CRITICAL FIX: If no LGAs found by name, try to get 
                // all LGAs in the state as fallback
                // ============================================================
                error_log("View Wards - No LGAs found by name, using fallback - all LGAs in state");
                $stmt = $db->prepare("
                    SELECT id, name 
                    FROM lgas 
                    WHERE state_id = ? 
                    AND is_active = 1
                    ORDER BY name ASC
                ");
                $stmt->execute([$state_id]);
                $all_lgas = $stmt->fetchAll();
                
                foreach ($all_lgas as $l) {
                    $lga_ids[] = $l['id'];
                    $lgas_for_filter[] = $l;
                }
                error_log("View Wards - Fallback LGAs found: " . print_r($lga_ids, true));
            }
        }
    } catch (Exception $e) {
        error_log("Error processing LGA data: " . $e->getMessage());
    }
}

// If still no LGAs, try one more fallback
if (empty($lga_ids) && $state_id) {
    error_log("View Wards - Final fallback: getting all LGAs in state");
    try {
        $stmt = $db->prepare("
            SELECT id, name 
            FROM lgas 
            WHERE state_id = ? 
            AND is_active = 1
            ORDER BY name ASC
        ");
        $stmt->execute([$state_id]);
        $all_lgas = $stmt->fetchAll();
        foreach ($all_lgas as $l) {
            $lga_ids[] = $l['id'];
            $lgas_for_filter[] = $l;
        }
        error_log("View Wards - Final fallback LGAs: " . print_r($lga_ids, true));
    } catch (Exception $e) {
        error_log("Error in final fallback: " . $e->getMessage());
    }
}

$lga_list = !empty($lga_ids) ? implode(',', array_map('intval', $lga_ids)) : '0';
error_log("View Wards - Final LGA list: $lga_list");

// ============================================================
// GET FILTERS
// ============================================================
$lga_filter = isset($_GET['lga']) ? (int)$_GET['lga'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ============================================================
// GET WARDS
// ============================================================
$wards = [];
$total_wards = 0;

try {
    if ($lga_list !== '0') {
        $where = ["w.is_active = 1"];
        $params = [];
        
        if ($lga_filter > 0) {
            $where[] = "w.lga_id = ?";
            $params[] = $lga_filter;
        } else {
            $where[] = "w.lga_id IN ($lga_list)";
        }
        
        if (!empty($search)) {
            $where[] = "(w.name LIKE ? OR w.code LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        // Add tenant_id for subqueries
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
        
        error_log("View Wards - SQL: $sql");
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $wards = $stmt->fetchAll();
        $total_wards = count($wards);
        
        error_log("View Wards - Found $total_wards wards");
        
    } else {
        error_log("View Wards - No LGA list available (lga_list = $lga_list)");
    }
} catch (Exception $e) {
    error_log("Error fetching wards: " . $e->getMessage());
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
    padding: 16px 20px;
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
.filter-section .btn-reset:hover {
    background: var(--gray-50);
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
.ward-card .ward-actions a {
    font-size: 0.7rem;
    padding: 4px 12px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
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
.empty-state .debug-info {
    margin-top: 16px;
    padding: 12px 16px;
    background: #FEF3C7;
    border-radius: 8px;
    text-align: left;
    font-size: 0.8rem;
    color: #92400E;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}

@media (max-width: 768px) {
    .filter-section {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-section select,
    .filter-section input {
        min-width: unset;
        width: 100%;
    }
    .wards-grid {
        grid-template-columns: 1fr;
    }
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
                    <?php foreach ($lgas_for_filter as $lga): ?>
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
                            <a href="ward-details.php?id=<?php echo $ward['id']; ?>" class="btn-view">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                            <a href="monitor-pus.php?ward=<?php echo $ward['id']; ?>" class="btn-pus">
                                <i class="fas fa-flag-checkered"></i> PUs
                            </a>
                            <a href="coordinators.php?ward=<?php echo $ward['id']; ?>" class="btn-pus">
                                <i class="fas fa-user-tie"></i> Coordinators
                            </a>
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
                    <?php if (empty($lgas_for_filter) || $lga_list === '0'): ?>
                        <div class="debug-info">
                            <strong>💡 Debug Info:</strong><br>
                            Constituency ID: <?php echo $constituency_id ?: 'Not set'; ?><br>
                            State ID: <?php echo $state_id ?: 'Not set'; ?><br>
                            LGA List: <?php echo $lga_list; ?><br>
                            <?php if ($constituency && !empty($constituency['lgas_json'])): ?>
                                LGA JSON: <?php echo htmlspecialchars($constituency['lgas_json']); ?><br>
                                <span style="font-size:0.7rem;color:#92400E;">
                                    <strong>Note:</strong> The LGAs listed in the JSON may not exist in the database yet.
                                    Please add the required LGAs to the database.
                                </span>
                            <?php else: ?>
                                No LGA JSON found in constituency data.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
// Sidebar toggle
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