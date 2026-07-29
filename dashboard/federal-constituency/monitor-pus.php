<?php
// ============================================================
// FEDERAL CONSTITUENCY COORDINATOR - MONITOR POLLING UNITS
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
$lga_filter = isset($_GET['lga']) ? (int)$_GET['lga'] : 0;
$ward_filter = isset($_GET['ward']) ? (int)$_GET['ward'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
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

// Get wards for filter
$wards = [];
if ($lga_filter > 0) {
    try {
        $stmt = $db->prepare("SELECT id, name FROM wards WHERE lga_id = ? AND is_active = 1 ORDER BY name ASC");
        $stmt->execute([$lga_filter]);
        $wards = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching wards: " . $e->getMessage());
    }
}

// Get polling units
$pus = [];
$total_pus = 0;
try {
    $where = ["pu.is_active = 1"];
    $params = [];
    
    if ($lga_filter > 0) {
        $where[] = "w.lga_id = ?";
        $params[] = $lga_filter;
    } elseif ($lga_list !== '0') {
        $where[] = "w.lga_id IN ($lga_list)";
    } else {
        $where[] = "1=0";
    }
    
    if ($ward_filter > 0) {
        $where[] = "pu.ward_id = ?";
        $params[] = $ward_filter;
    }
    
    if (!empty($search)) {
        $where[] = "(pu.name LIKE ? OR pu.code LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_clause = implode(" AND ", $where);
    
    $stmt = $db->prepare("
        SELECT 
            pu.*,
            w.name as ward_name,
            l.name as lga_name,
            (SELECT COUNT(*) FROM agent_assignments aa WHERE aa.pu_id = pu.id AND aa.status = 'active') as agent_count,
            (SELECT COUNT(*) FROM results_ec8a r WHERE r.pu_id = pu.id AND r.tenant_id = ?) as result_count,
            (SELECT status FROM results_ec8a r WHERE r.pu_id = pu.id AND r.tenant_id = ? ORDER BY r.created_at DESC LIMIT 1) as last_result_status,
            (SELECT COUNT(*) FROM incidents i WHERE i.pu_id = pu.id AND i.tenant_id = ?) as incident_count
        FROM polling_units pu
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        WHERE $where_clause
        ORDER BY l.name ASC, w.name ASC, pu.name ASC
        LIMIT 100
    ");
    $stmt->execute(array_merge($params, [$tenant_id, $tenant_id, $tenant_id]));
    $pus = $stmt->fetchAll();
    $total_pus = count($pus);
} catch (Exception $e) {
    error_log("Error fetching polling units: " . $e->getMessage());
}

$page_title = 'Monitor Polling Units';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.filter-section {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px 20px;
    margin-bottom: 24px;
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

.pu-table-wrap {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
}
.pu-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}
.pu-table th {
    text-align: left;
    padding: 12px 16px;
    background: var(--gray-50);
    font-weight: 600;
    color: var(--gray-600);
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--gray-200);
}
.pu-table td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}
.pu-table tr:hover td {
    background: var(--gray-50);
}
.pu-table .pu-name {
    font-weight: 500;
}
.pu-table .pu-code {
    font-size: 0.7rem;
    color: var(--gray-400);
}
.pu-table .status-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 0.65rem;
    font-weight: 600;
}
.status-badge.verified { background: #D1FAE5; color: #059669; }
.status-badge.pending { background: #FEF3C7; color: #D97706; }
.status-badge.flagged { background: #FEE2E2; color: #DC2626; }
.status-badge.no-result { background: #F3F4F6; color: #6B7280; }

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
.results-summary .note {
    color: var(--gray-400);
    font-size: 0.75rem;
}

@media (max-width: 768px) {
    .filter-section {
        flex-direction: column;
        align-items: stretch;
    }
    .pu-table-wrap {
        overflow-x: auto;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <h2 style="font-size:1.3rem;font-weight:700;margin-bottom:4px;">
            <i class="fas fa-flag-checkered" style="color:var(--primary);"></i> Monitor Polling Units
        </h2>
        <p style="color:var(--gray-500);font-size:0.9rem;margin-bottom:20px;">
            Monitor polling units in your federal constituency.
        </p>

        <!-- Filter -->
        <div class="filter-section">
            <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;flex:1;align-items:center;">
                <select name="lga" id="lga_select">
                    <option value="">All LGAs</option>
                    <?php foreach ($lgas as $lga): ?>
                        <option value="<?php echo $lga['id']; ?>" <?php echo ($lga_filter == $lga['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($lga['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="ward" id="ward_select">
                    <option value="">All Wards</option>
                    <?php foreach ($wards as $ward): ?>
                        <option value="<?php echo $ward['id']; ?>" <?php echo ($ward_filter == $ward['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ward['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="search" placeholder="Search by name or code..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
                <a href="monitor-pus.php" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
            </form>
        </div>

        <!-- Results -->
        <div class="results-summary">
            <div class="count"><span><?php echo number_format($total_pus); ?></span> polling units</div>
            <?php if ($total_pus >= 100): ?>
                <div class="note"><i class="fas fa-info-circle"></i> Showing first 100 results</div>
            <?php endif; ?>
        </div>

        <!-- Table -->
        <div class="pu-table-wrap">
            <table class="pu-table">
                <thead>
                    <tr>
                        <th>Polling Unit</th>
                        <th>Ward</th>
                        <th>LGA</th>
                        <th>Agents</th>
                        <th>Results</th>
                        <th>Incidents</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($pus) > 0): ?>
                        <?php foreach ($pus as $pu): 
                            $status = $pu['last_result_status'] ?? 'no-result';
                            $status_label = $status === 'no-result' ? 'No Result' : ucfirst($status);
                            $status_class = $status === 'verified' ? 'verified' : ($status === 'pending' ? 'pending' : ($status === 'flagged' ? 'flagged' : 'no-result'));
                        ?>
                            <tr>
                                <td>
                                    <div class="pu-name"><?php echo htmlspecialchars($pu['name']); ?></div>
                                    <div class="pu-code"><?php echo htmlspecialchars($pu['code'] ?? 'N/A'); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($pu['ward_name']); ?></td>
                                <td><?php echo htmlspecialchars($pu['lga_name']); ?></td>
                                <td><?php echo number_format($pu['agent_count'] ?? 0); ?></td>
                                <td><?php echo number_format($pu['result_count'] ?? 0); ?></td>
                                <td><?php echo number_format($pu['incident_count'] ?? 0); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo $status_label; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="pu-details.php?id=<?php echo $pu['id']; ?>" style="color:var(--primary);text-decoration:none;font-size:0.8rem;">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align:center;padding:40px;color:var(--gray-500);">
                                <i class="fas fa-inbox" style="font-size:1.5rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                                No polling units found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
// Dynamic ward dropdown
document.getElementById('lga_select').addEventListener('change', function() {
    var lgaId = this.value;
    var wardSelect = document.getElementById('ward_select');
    
    // Clear current options
    wardSelect.innerHTML = '<option value="">All Wards</option>';
    
    if (lgaId) {
        fetch('ajax/get-wards.php?lga=' + lgaId)
            .then(response => response.json())
            .then(data => {
                if (data && data.length > 0) {
                    data.forEach(function(ward) {
                        var option = document.createElement('option');
                        option.value = ward.id;
                        option.textContent = ward.name;
                        wardSelect.appendChild(option);
                    });
                }
            })
            .catch(function() {});
    }
});

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