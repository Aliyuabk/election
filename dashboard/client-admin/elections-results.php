<?php
// ============================================================
// ELECTION RESULTS - CLIENT ADMIN (PROFESSIONAL UI)
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Start session and check login
SessionManager::start();

// Redirect if not logged in
if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

// Check role - only client_admin can access this page
if (SessionManager::get('role_level') !== 'client_admin') {
    header('Location: ../client-admin/');
    exit();
}

// Get database connection
$db = getDB();

// Get user info
$user_id = SessionManager::get('user_id');
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');
$tenant_id = SessionManager::get('tenant_id');

// ============================================================
// GET ELECTION ID
// ============================================================
$election_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($election_id <= 0) {
    header('Location: elections.php');
    exit();
}

// ============================================================
// FETCH ELECTION DETAILS
// ============================================================
$election = null;
try {
    $stmt = $db->prepare("SELECT * FROM elections WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL");
    $stmt->execute([$election_id, $tenant_id]);
    $election = $stmt->fetch();
} catch (Exception $e) {}

if (!$election) {
    header('Location: elections.php');
    exit();
}

// ============================================================
// FETCH RESULTS
// ============================================================
$results = [];
$summary = [
    'total_results' => 0,
    'verified' => 0,
    'pending' => 0,
    'rejected' => 0,
    'total_votes' => 0,
    'turnout' => 0,
    'parties' => []
];

try {
    // Fetch results with party data
    $stmt = $db->prepare("
        SELECT r.*, 
               u.full_name as agent_name,
               pu.name as pu_name, pu.code as pu_code,
               w.name as ward_name,
               l.name as lga_name,
               s.name as state_name
        FROM results_ec8a r
        LEFT JOIN users u ON r.agent_id = u.id
        LEFT JOIN polling_units pu ON r.pu_id = pu.id
        LEFT JOIN wards w ON pu.ward_id = w.id
        LEFT JOIN lgas l ON w.lga_id = l.id
        LEFT JOIN states s ON l.state_id = s.id
        WHERE r.election_id = ?
        ORDER BY r.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$election_id]);
    $results = $stmt->fetchAll();
    
    // Get summary stats
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(valid_votes) as total_votes
        FROM results_ec8a
        WHERE election_id = ?
    ");
    $stmt->execute([$election_id]);
    $summary_data = $stmt->fetch();
    
    $summary['total_results'] = $summary_data['total'] ?? 0;
    $summary['verified'] = $summary_data['verified'] ?? 0;
    $summary['pending'] = $summary_data['pending'] ?? 0;
    $summary['rejected'] = $summary_data['rejected'] ?? 0;
    $summary['total_votes'] = $summary_data['total_votes'] ?? 0;
    
    // Calculate turnout if registered voters available
    $stmt = $db->prepare("SELECT SUM(registered_voters) as total FROM polling_units pu WHERE pu.id IN (SELECT JSON_EXTRACT(e.pus_json, '$') FROM elections e WHERE e.id = ?)");
    $stmt->execute([$election_id]);
    $total_voters = $stmt->fetch()['total'] ?? 0;
    $summary['turnout'] = $total_voters > 0 ? round(($summary['total_votes'] / $total_voters) * 100, 1) : 0;
    
} catch (Exception $e) {}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       ELECTION RESULTS - PROFESSIONAL UI STYLES
       ============================================================ */
    
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
    
    .btn-primary {
        padding: 10px 20px;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.3);
    }
    .btn-outline {
        padding: 10px 18px;
        background: transparent;
        color: var(--gray-600);
        border: 1.5px solid var(--gray-200);
        border-radius: 10px;
        font-weight: 500;
        font-size: 0.85rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-outline:hover {
        background: var(--gray-50);
        border-color: var(--primary);
        color: var(--primary);
    }
    .btn-success {
        padding: 10px 20px;
        background: var(--secondary);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-success:hover {
        background: #059669;
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(16, 185, 129, 0.3);
    }
    
    .results-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 14px;
        margin-bottom: 24px;
    }
    .summary-card {
        background: white;
        border-radius: 14px;
        padding: 18px 20px;
        border: 1px solid var(--gray-200);
        text-align: center;
        transition: var(--transition);
    }
    .summary-card:hover {
        box-shadow: var(--shadow-hover);
        transform: translateY(-2px);
    }
    .summary-card .number {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--primary);
        line-height: 1.2;
    }
    .summary-card .number.green { color: var(--secondary); }
    .summary-card .number.yellow { color: var(--warning); }
    .summary-card .number.blue { color: #3B82F6; }
    .summary-card .number.red { color: var(--danger); }
    .summary-card .number.purple { color: #8B5CF6; }
    .summary-card .label {
        font-size: 0.75rem;
        color: var(--gray-500);
        margin-top: 4px;
        font-weight: 500;
    }
    .summary-card .sub-label {
        font-size: 0.65rem;
        color: var(--gray-400);
        margin-top: 2px;
    }
    
    .table-container {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        overflow: hidden;
        box-shadow: var(--shadow);
    }
    .table-container .table-header {
        padding: 16px 24px;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        background: linear-gradient(135deg, var(--gray-50), white);
    }
    .table-container .table-header .table-title {
        font-weight: 600;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--gray-700);
    }
    .table-container .table-header .table-title i {
        color: var(--primary);
    }
    .table-container .table-header .table-title .count {
        background: var(--primary);
        color: white;
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .table-container .table-header .table-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    .data-table thead {
        background: var(--gray-50);
    }
    .data-table thead th {
        padding: 12px 16px;
        text-align: left;
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--gray-500);
        border-bottom: 2px solid var(--gray-200);
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 2;
        background: var(--gray-50);
    }
    .data-table tbody td {
        padding: 10px 16px;
        border-bottom: 1px solid var(--gray-100);
        vertical-align: middle;
    }
    .data-table tbody tr:hover {
        background: var(--gray-50);
    }
    
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .badge-status .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
    }
    .badge-status.verified { background: #ECFDF5; color: #065F46; }
    .badge-status.verified .dot { background: #10B981; }
    .badge-status.pending { background: #FFFBEB; color: #92400E; }
    .badge-status.pending .dot { background: #F59E0B; }
    .badge-status.rejected { background: #FEF2F2; color: #991B1B; }
    .badge-status.rejected .dot { background: #EF4444; }
    .badge-status.flagged { background: #F5F3FF; color: #5B21B6; }
    .badge-status.flagged .dot { background: #8B5CF6; }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--gray-500);
    }
    .empty-state i {
        font-size: 4rem;
        color: var(--gray-300);
        display: block;
        margin-bottom: 16px;
    }
    .empty-state h4 {
        color: var(--gray-700);
        margin-bottom: 8px;
        font-size: 1.1rem;
    }
    .empty-state p {
        font-size: 0.9rem;
        color: var(--gray-400);
        max-width: 400px;
        margin: 0 auto;
    }
    
    @media (max-width: 768px) {
        .results-summary {
            grid-template-columns: repeat(2, 1fr);
        }
        .table-container {
            overflow-x: auto;
        }
        .data-table {
            font-size: 0.78rem;
        }
        .data-table th, .data-table td {
            padding: 8px 12px;
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }
    @media (max-width: 480px) {
        .results-summary {
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .summary-card {
            padding: 12px 14px;
        }
        .summary-card .number {
            font-size: 1.3rem;
        }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-chart-bar" style="color:var(--primary);margin-right:8px;"></i> Election Results
                    <small><?php echo htmlspecialchars($election['name']); ?></small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="elections-export.php?id=<?php echo $election_id; ?>" class="btn-success">
                    <i class="fas fa-file-export"></i> Export
                </a>
                <a href="elections-view.php?id=<?php echo $election_id; ?>" class="btn-outline">
                    <i class="fas fa-eye"></i> View Election
                </a>
                <a href="elections.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="results-summary">
            <div class="summary-card">
                <div class="number blue"><?php echo number_format($summary['total_results']); ?></div>
                <div class="label">Total Results</div>
                <div class="sub-label">Submitted</div>
            </div>
            <div class="summary-card">
                <div class="number green"><?php echo number_format($summary['verified']); ?></div>
                <div class="label">Verified</div>
                <div class="sub-label"><?php echo $summary['total_results'] > 0 ? round(($summary['verified'] / $summary['total_results']) * 100, 1) : 0; ?>% of total</div>
            </div>
            <div class="summary-card">
                <div class="number yellow"><?php echo number_format($summary['pending']); ?></div>
                <div class="label">Pending</div>
                <div class="sub-label">Awaiting verification</div>
            </div>
            <div class="summary-card">
                <div class="number red"><?php echo number_format($summary['rejected']); ?></div>
                <div class="label">Rejected</div>
                <div class="sub-label">Needs review</div>
            </div>
            <div class="summary-card">
                <div class="number purple"><?php echo number_format($summary['total_votes']); ?></div>
                <div class="label">Total Votes</div>
                <div class="sub-label">Cast so far</div>
            </div>
            <div class="summary-card">
                <div class="number" style="color:#F59E0B;"><?php echo $summary['turnout']; ?>%</div>
                <div class="label">Turnout</div>
                <div class="sub-label">Voter participation</div>
            </div>
        </div>

        <!-- Results Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-list" style="color:var(--primary);"></i> Results List
                    <span class="count"><?php echo number_format($summary['total_results']); ?></span>
                </div>
                <div class="table-actions">
                    <span style="font-size:0.75rem;color:var(--gray-400);">
                        Showing latest 50 results
                    </span>
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>PU Code</th>
                        <th>PU Name</th>
                        <th>Ward</th>
                        <th>LGA</th>
                        <th>State</th>
                        <th>Valid Votes</th>
                        <th>Status</th>
                        <th>Agent</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($results) > 0): ?>
                        <?php foreach ($results as $result): ?>
                            <tr>
                                <td>
                                    <span style="font-family:monospace;font-size:0.75rem;background:var(--gray-100);padding:2px 8px;border-radius:4px;">
                                        <?php echo htmlspecialchars($result['pu_code'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight:500;font-size:0.85rem;">
                                        <?php echo htmlspecialchars($result['pu_name'] ?? 'N/A'); ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($result['ward_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($result['lga_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($result['state_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <span style="font-weight:600;font-size:0.85rem;">
                                        <?php echo number_format($result['valid_votes'] ?? 0); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-status <?php echo $result['status'] ?? 'pending'; ?>">
                                        <span class="dot"></span>
                                        <?php echo ucfirst($result['status'] ?? 'Pending'); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($result['agent_name'] ?? 'N/A'); ?></td>
                                <td style="text-align:center;">
                                    <button class="btn-sm info" onclick="viewResult(<?php echo $result['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-sm success" onclick="verifyResult(<?php echo $result['id']; ?>)">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <i class="fas fa-chart-bar"></i>
                                    <h4>No results found</h4>
                                    <p>Results will appear here once they are submitted.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
// ============================================================
// PRELOADER
// ============================================================
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});

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

// ============================================================
// SIDEBAR DROPDOWNS
// ============================================================
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

// ============================================================
// PROFILE DROPDOWN
// ============================================================
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

// ============================================================
// RESULT FUNCTIONS
// ============================================================
function viewResult(id) {
    alert('View result ID: ' + id + '\nImplement with modal or page.');
}

function verifyResult(id) {
    if (confirm('Verify this result? This action cannot be undone.')) {
        alert('Result ' + id + ' verified!');
    }
}

// ============================================================
// SEARCH FUNCTIONALITY
// ============================================================
var searchInput = document.querySelector('.search-wrap input');
if (searchInput) {
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            this.closest('form').submit();
        }
    });
}
</script>
</body>
</html>