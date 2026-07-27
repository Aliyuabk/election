<?php
// ============================================================
// SENATORIAL COORDINATOR - VIEW FEDERAL CONSTITUENCIES
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

// Get Senatorial District name
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
    $district_name = 'Senatorial District';
    $state_name = 'State';
}

// Get Federal Constituencies
$federal_constituencies = [];
try {
    $stmt = $db->prepare("
        SELECT fc.id, fc.name, fc.code,
               COUNT(DISTINCT l.id) as lga_count,
               COUNT(DISTINCT w.id) as ward_count,
               COUNT(DISTINCT pu.id) as pu_count,
               COUNT(DISTINCT u.id) as coordinator_count
        FROM federal_constituencies fc
        LEFT JOIN lgas l ON l.state_id = fc.state_id
        LEFT JOIN wards w ON w.lga_id = l.id
        LEFT JOIN polling_units pu ON pu.ward_id = w.id
        LEFT JOIN users u ON u.federal_constituency_id = fc.id AND u.role_id = (SELECT id FROM roles WHERE level = 'federal_constituency' LIMIT 1)
        WHERE fc.state_id = ? AND fc.is_active = 1
        GROUP BY fc.id, fc.name, fc.code
        ORDER BY fc.name ASC
    ");
    $stmt->execute([$state_id]);
    $federal_constituencies = $stmt->fetchAll();
} catch (Exception $e) {
    $federal_constituencies = [];
}

$page_title = 'Federal Constituencies';
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
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.stat-card {
    background: white;
    border-radius: var(--radius);
    padding: 18px 20px;
    border: 1px solid var(--gray-200);
}
.stat-card .stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gray-800);
}
.stat-card .stat-label {
    font-size: 0.75rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.table-wrap {
    overflow-x: auto;
}
.table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}
.table th {
    text-align: left;
    padding: 10px 12px;
    background: var(--gray-50);
    font-weight: 600;
    color: var(--gray-600);
    border-bottom: 2px solid var(--gray-200);
}
.table td {
    padding: 10px 12px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}
.table tr:hover td {
    background: var(--gray-50);
}

.section-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
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

.btn-sm {
    padding: 4px 12px;
    border-radius: 6px;
    background: var(--primary);
    color: white;
    text-decoration: none;
    font-size: 0.7rem;
    border: none;
}
.btn-sm:hover {
    background: var(--primary-dark);
    color: white;
}

.filters-row {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}
.filters-row select,
.filters-row input {
    padding: 8px 14px;
    border: 1.5px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.8rem;
    background: white;
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
                    <i class="fas fa-building" style="color:var(--primary);margin-right:8px;"></i> 
                    Federal Constituencies
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
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format(count($federal_constituencies)); ?></div>
                <div class="stat-label">Total Federal Constituencies</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format(array_sum(array_column($federal_constituencies, 'lga_count'))); ?></div>
                <div class="stat-label">Total LGAs</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format(array_sum(array_column($federal_constituencies, 'ward_count'))); ?></div>
                <div class="stat-label">Total Wards</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format(array_sum(array_column($federal_constituencies, 'pu_count'))); ?></div>
                <div class="stat-label">Total Polling Units</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="section-card" style="margin-bottom:20px;">
            <div class="filters-row">
                <select id="filterLGA" onchange="applyFilters()">
                    <option value="">All LGAs</option>
                    <?php
                    // Get all LGAs in this state
                    try {
                        $stmt = $db->prepare("SELECT id, name FROM lgas WHERE state_id = ? AND is_active = 1 ORDER BY name ASC");
                        $stmt->execute([$state_id]);
                        $lgas = $stmt->fetchAll();
                        foreach ($lgas as $lga) {
                            echo '<option value="' . $lga['id'] . '">' . htmlspecialchars($lga['name']) . '</option>';
                        }
                    } catch (Exception $e) {}
                    ?>
                </select>
                <input type="text" id="searchInput" placeholder="Search constituencies..." onkeyup="applyFilters()">
            </div>
        </div>

        <!-- Table -->
        <div class="section-card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Federal Constituencies List</h3>
                <span style="font-size:0.75rem;color:var(--gray-400);">Showing <?php echo count($federal_constituencies); ?> constituencies</span>
            </div>
            <div class="table-wrap">
                <table class="table" id="fcTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Code</th>
                            <th>LGAs</th>
                            <th>Wards</th>
                            <th>Polling Units</th>
                            <th>Coordinators</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($federal_constituencies) > 0): ?>
                            <?php $i = 1; foreach ($federal_constituencies as $fc): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($fc['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($fc['code']); ?></td>
                                    <td><?php echo number_format($fc['lga_count'] ?? 0); ?></td>
                                    <td><?php echo number_format($fc['ward_count'] ?? 0); ?></td>
                                    <td><?php echo number_format($fc['pu_count'] ?? 0); ?></td>
                                    <td><?php echo number_format($fc['coordinator_count'] ?? 0); ?></td>
                                    <td>
                                        <a href="view-constituency-details.php?id=<?php echo $fc['id']; ?>" class="btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center;padding:30px;color:var(--gray-500);">
                                    <i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
                                    No federal constituencies found in this state.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
function applyFilters() {
    var lga = document.getElementById('filterLGA').value;
    var search = document.getElementById('searchInput').value.toLowerCase();
    var rows = document.querySelectorAll('#fcTable tbody tr');
    
    rows.forEach(function(row) {
        var show = true;
        var cells = row.querySelectorAll('td');
        if (cells.length < 8) return;
        
        var rowName = cells[1]?.textContent?.trim().toLowerCase() || '';
        var rowCode = cells[2]?.textContent?.trim().toLowerCase() || '';
        
        if (search && !rowName.includes(search) && !rowCode.includes(search)) {
            show = false;
        }
        
        row.style.display = show ? '' : 'none';
    });
}

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