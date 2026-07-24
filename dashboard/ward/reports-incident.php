<?php
// ============================================================
// WARD COORDINATOR - INCIDENT REPORT
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Start session
SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

// Only Ward coordinator can access
if (SessionManager::get('role_level') !== 'ward') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$ward_id = SessionManager::get('ward_id');
$tenant_id = SessionManager::get('tenant_id');

// If ward_id is not set in session, try to get it from user record
if (empty($ward_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT ward_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['ward_id'])) {
            $ward_id = $user['ward_id'];
            SessionManager::set('ward_id', $ward_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching ward_id: " . $e->getMessage());
    }
}

$db = getDB();

// ============================================================
// FETCH WARD NAME
// ============================================================
$ward_name = 'Ward';
try {
    if ($ward_id) {
        $stmt = $db->prepare("SELECT name FROM wards WHERE id = ?");
        $stmt->execute([$ward_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $ward_name = $result['name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching ward name: " . $e->getMessage());
}

// ============================================================
// FETCH FILTERS
// ============================================================
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';
$severity_filter = isset($_GET['severity']) ? $_GET['severity'] : 'all';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// ============================================================
// FETCH INCIDENT DATA
// ============================================================
$incidents = [];
$summary = [];

try {
    // Build conditions
    $conditions = "i.tenant_id = ? AND i.ward_id = ?";
    $params = [$tenant_id, $ward_id];
    
    if (!empty($date_from)) {
        $conditions .= " AND DATE(i.created_at) >= ?";
        $params[] = $date_from;
    }
    if (!empty($date_to)) {
        $conditions .= " AND DATE(i.created_at) <= ?";
        $params[] = $date_to;
    }
    if ($type_filter !== 'all') {
        $conditions .= " AND i.incident_type = ?";
        $params[] = $type_filter;
    }
    if ($severity_filter !== 'all') {
        $conditions .= " AND i.severity = ?";
        $params[] = $severity_filter;
    }
    if ($status_filter !== 'all') {
        $conditions .= " AND i.status = ?";
        $params[] = $status_filter;
    }
    
    // Get incidents
    $stmt = $db->prepare("
        SELECT 
            i.*,
            u.full_name as reporter_name,
            pu.name as pu_name,
            pu.code as pu_code
        FROM incidents i
        LEFT JOIN users u ON i.reporter_id = u.id
        LEFT JOIN polling_units pu ON i.pu_id = pu.id
        WHERE $conditions
        ORDER BY i.created_at DESC
        LIMIT 200
    ");
    $stmt->execute($params);
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get summary statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'reported' THEN 1 ELSE 0 END) as reported,
            SUM(CASE WHEN status = 'investigating' THEN 1 ELSE 0 END) as investigating,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
            SUM(CASE WHEN status = 'escalated' THEN 1 ELSE 0 END) as escalated,
            SUM(CASE WHEN is_panic = 1 THEN 1 ELSE 0 END) as panic,
            SUM(CASE WHEN severity = 'low' THEN 1 ELSE 0 END) as low,
            SUM(CASE WHEN severity = 'medium' THEN 1 ELSE 0 END) as medium,
            SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as high,
            SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical
        FROM incidents i
        WHERE i.tenant_id = ? AND i.ward_id = ?
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching incident report: " . $e->getMessage());
}

$page_title = 'Incident Report';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.report-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.report-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.report-header h2 i {
    color: #EF4444;
}

.filter-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 16px;
    align-items: center;
    background: white;
    padding: 12px 16px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}
.filter-bar select,
.filter-bar input[type="date"] {
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    background: white;
    min-width: 130px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}
.stat-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 10px 12px;
    text-align: center;
}
.stat-card .number {
    font-size: 1.2rem;
    font-weight: 700;
}
.stat-card .number.green { color: #10B981; }
.stat-card .number.blue { color: #3B82F6; }
.stat-card .number.orange { color: #F59E0B; }
.stat-card .number.red { color: #EF4444; }
.stat-card .number.purple { color: #8B5CF6; }
.stat-card .label {
    font-size: 0.6rem;
    color: var(--gray-500);
    font-weight: 500;
}

.report-section {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px;
    margin-bottom: 16px;
}
.report-section .section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}
.report-section .section-header h3 {
    font-size: 0.95rem;
    font-weight: 600;
    margin: 0;
}
.report-section .section-header .count {
    font-size: 0.7rem;
    color: var(--gray-400);
}

.table-container {
    overflow-x: auto;
}
.table-container table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}
.table-container th {
    background: var(--gray-50);
    padding: 8px 12px;
    text-align: left;
    font-weight: 600;
    color: var(--gray-600);
    border-bottom: 2px solid var(--gray-200);
    white-space: nowrap;
}
.table-container td {
    padding: 8px 12px;
    border-bottom: 1px solid var(--gray-100);
}

.status-badge {
    font-size: 0.6rem;
    padding: 2px 10px;
    border-radius: 20px;
    font-weight: 500;
}
.status-badge.reported { background: #FEF3C7; color: #92400E; }
.status-badge.investigating { background: #DBEAFE; color: #1E40AF; }
.status-badge.resolved { background: #D1FAE5; color: #065F46; }
.status-badge.escalated { background: #FEE2E2; color: #991B1B; }
.status-badge.low { background: #E5E7EB; color: #374151; }
.status-badge.medium { background: #FEF3C7; color: #92400E; }
.status-badge.high { background: #FEE2E2; color: #991B1B; }
.status-badge.critical { background: #7F1D1D; color: white; }

.export-buttons {
    display: flex;
    gap: 8px;
}
.export-buttons .btn-sm {
    padding: 4px 12px;
    font-size: 0.7rem;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.export-buttons .btn-sm.pdf { background: #FEE2E2; color: #991B1B; }
.export-buttons .btn-sm.excel { background: #D1FAE5; color: #065F46; }

.panic-badge {
    display: inline-block;
    padding: 1px 8px;
    border-radius: 12px;
    font-size: 0.55rem;
    font-weight: 700;
    background: #EF4444;
    color: white;
    animation: pulse 1.5s infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

@media (max-width: 768px) {
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="report-header">
            <div>
                <h2><i class="fas fa-exclamation-triangle"></i> Incident Report</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward
                </p>
            </div>
            <div class="export-buttons">
                <a href="export-pdf.php?type=incident&ward_id=<?php echo $ward_id; ?>" class="btn-sm pdf">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
                <a href="export-excel.php?type=incident&ward_id=<?php echo $ward_id; ?>" class="btn-sm excel">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
                <a href="incidents.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <select id="typeFilter">
                <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                <option value="violence" <?php echo $type_filter === 'violence' ? 'selected' : ''; ?>>Violence</option>
                <option value="intimidation" <?php echo $type_filter === 'intimidation' ? 'selected' : ''; ?>>Intimidation</option>
                <option value="ballot_stuffing" <?php echo $type_filter === 'ballot_stuffing' ? 'selected' : ''; ?>>Ballot Stuffing</option>
                <option value="vote_buying" <?php echo $type_filter === 'vote_buying' ? 'selected' : ''; ?>>Vote Buying</option>
                <option value="voter_suppression" <?php echo $type_filter === 'voter_suppression' ? 'selected' : ''; ?>>Voter Suppression</option>
                <option value="material_shortage" <?php echo $type_filter === 'material_shortage' ? 'selected' : ''; ?>>Material Shortage</option>
                <option value="technical_issue" <?php echo $type_filter === 'technical_issue' ? 'selected' : ''; ?>>Technical Issue</option>
            </select>
            <select id="severityFilter">
                <option value="all" <?php echo $severity_filter === 'all' ? 'selected' : ''; ?>>All Severity</option>
                <option value="low" <?php echo $severity_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                <option value="medium" <?php echo $severity_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                <option value="high" <?php echo $severity_filter === 'high' ? 'selected' : ''; ?>>High</option>
                <option value="critical" <?php echo $severity_filter === 'critical' ? 'selected' : ''; ?>>Critical</option>
            </select>
            <select id="statusFilter">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="reported" <?php echo $status_filter === 'reported' ? 'selected' : ''; ?>>Reported</option>
                <option value="investigating" <?php echo $status_filter === 'investigating' ? 'selected' : ''; ?>>Investigating</option>
                <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                <option value="escalated" <?php echo $status_filter === 'escalated' ? 'selected' : ''; ?>>Escalated</option>
            </select>
            <input type="date" id="dateFrom" value="<?php echo htmlspecialchars($date_from); ?>">
            <input type="date" id="dateTo" value="<?php echo htmlspecialchars($date_to); ?>">
            <button onclick="applyFilters()" class="btn-secondary-sm">
                <i class="fas fa-filter"></i> Apply
            </button>
            <button onclick="resetFilters()" class="btn-secondary-sm" style="background: var(--gray-100);">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number blue"><?php echo number_format($summary['total'] ?? 0); ?></div>
                <div class="label">Total</div>
            </div>
            <div class="stat-card">
                <div class="number orange"><?php echo number_format($summary['reported'] ?? 0); ?></div>
                <div class="label">Reported</div>
            </div>
            <div class="stat-card">
                <div class="number blue"><?php echo number_format($summary['investigating'] ?? 0); ?></div>
                <div class="label">Investigating</div>
            </div>
            <div class="stat-card">
                <div class="number green"><?php echo number_format($summary['resolved'] ?? 0); ?></div>
                <div class="label">Resolved</div>
            </div>
            <div class="stat-card">
                <div class="number red"><?php echo number_format($summary['escalated'] ?? 0); ?></div>
                <div class="label">Escalated</div>
            </div>
            <div class="stat-card">
                <div class="number red"><?php echo number_format($summary['panic'] ?? 0); ?></div>
                <div class="label">Panic Alerts</div>
            </div>
        </div>

        <!-- Incident List -->
        <div class="report-section">
            <div class="section-header">
                <h3><i class="fas fa-list"></i> Incident Details</h3>
                <span class="count"><?php echo count($incidents); ?> incidents</span>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Severity</th>
                            <th>Status</th>
                            <th>PU</th>
                            <th>Reported By</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($incidents) > 0): ?>
                            <?php foreach ($incidents as $inc): ?>
                                <tr>
                                    <td>#<?php echo $inc['id']; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($inc['title']); ?>
                                        <?php if ($inc['is_panic'] ?? 0): ?>
                                            <span class="panic-badge">PANIC</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $inc['incident_type'] ?? 'Unknown')); ?></td>
                                    <td><span class="status-badge <?php echo $inc['severity'] ?? 'medium'; ?>"><?php echo ucfirst($inc['severity'] ?? 'Medium'); ?></span></td>
                                    <td><span class="status-badge <?php echo $inc['status']; ?>"><?php echo ucfirst($inc['status']); ?></span></td>
                                    <td><?php echo htmlspecialchars($inc['pu_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($inc['reporter_name'] ?? 'Unknown'); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($inc['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" style="text-align:center;color:var(--gray-400);padding:20px;">No incidents found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
// Apply filters
function applyFilters() {
    const type = document.getElementById('typeFilter').value;
    const severity = document.getElementById('severityFilter').value;
    const status = document.getElementById('statusFilter').value;
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    window.location.href = `?type=${type}&severity=${severity}&status=${status}&date_from=${dateFrom}&date_to=${dateTo}`;
}

// Reset filters
function resetFilters() {
    document.getElementById('typeFilter').value = 'all';
    document.getElementById('severityFilter').value = 'all';
    document.getElementById('statusFilter').value = 'all';
    document.getElementById('dateFrom').value = '<?php echo date('Y-m-d', strtotime('-30 days')); ?>';
    document.getElementById('dateTo').value = '<?php echo date('Y-m-d'); ?>';
    window.location.href = '?';
}

// Preloader
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
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

// Sidebar dropdowns
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

// Profile dropdown
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