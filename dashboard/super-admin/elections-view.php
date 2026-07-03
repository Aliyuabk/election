<?php
// ============================================================
// ELECTION VIEW - SUPER ADMINISTRATOR
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

// Check role - only super_admin can access this page
if (SessionManager::get('role_level') !== 'super_admin') {
    header('Location: ../client-admin/');
    exit();
}

// Get database connection
$db = getDB();

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
    $stmt = $db->prepare("
        SELECT e.*, t.name as tenant_name, u.full_name as created_by_name
        FROM elections e
        LEFT JOIN tenants t ON e.tenant_id = t.id
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.id = ? AND e.deleted_at IS NULL
    ");
    $stmt->execute([$election_id]);
    $election = $stmt->fetch();
} catch (Exception $e) {
    // Continue
}

if (!$election) {
    header('Location: elections.php');
    exit();
}

// ============================================================
// FETCH ELECTION STATISTICS
// ============================================================
$stats = [
    'total_candidates' => 0,
    'total_polling_units' => 0,
    'total_results' => 0,
    'verified_results' => 0,
    'pending_results' => 0
];

try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM candidates WHERE election_id = ?");
    $stmt->execute([$election_id]);
    $stats['total_candidates'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM results_ec8a WHERE election_id = ?");
    $stmt->execute([$election_id]);
    $stats['total_results'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM results_ec8a WHERE election_id = ? AND status = 'verified'");
    $stmt->execute([$election_id]);
    $stats['verified_results'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM results_ec8a WHERE election_id = ? AND status = 'pending'");
    $stmt->execute([$election_id]);
    $stats['pending_results'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {}

// Get user info
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       ELECTION VIEW - PRO STYLES
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
        padding: 8px 18px;
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
        transform: translateY(-1px);
        box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.25);
    }
    .btn-outline {
        padding: 8px 16px;
        background: transparent;
        color: var(--gray-600);
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        font-weight: 500;
        font-size: 0.82rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-outline:hover {
        background: var(--gray-50);
        border-color: var(--gray-300);
    }
    
    .election-hero {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 28px 32px;
        box-shadow: var(--shadow);
        margin-bottom: 24px;
        position: relative;
        overflow: hidden;
    }
    .election-hero::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
    }
    .election-hero .hero-content {
        display: flex;
        align-items: center;
        gap: 24px;
        flex-wrap: wrap;
    }
    .election-hero .hero-icon {
        width: 64px;
        height: 64px;
        border-radius: 16px;
        background: #F5F3FF;
        color: #8B5CF6;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        flex-shrink: 0;
    }
    .election-hero .hero-info h1 {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 4px;
    }
    .election-hero .hero-info .meta {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        font-size: 0.85rem;
        color: var(--gray-500);
    }
    .election-hero .hero-info .meta span {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .election-hero .hero-actions {
        margin-left: auto;
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .badge-status .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
    }
    .badge-status.draft { background: var(--gray-100); color: var(--gray-500); }
    .badge-status.draft .dot { background: var(--gray-400); }
    .badge-status.upcoming { background: #FFFBEB; color: #92400E; }
    .badge-status.upcoming .dot { background: #F59E0B; }
    .badge-status.active { background: #ECFDF5; color: #065F46; }
    .badge-status.active .dot { background: #10B981; }
    .badge-status.completed { background: #EFF6FF; color: #1E40AF; }
    .badge-status.completed .dot { background: #3B82F6; }
    .badge-status.cancelled { background: #FEF2F2; color: #991B1B; }
    .badge-status.cancelled .dot { background: #EF4444; }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 12px;
        margin-bottom: 24px;
    }
    .stat-item {
        background: white;
        border-radius: 12px;
        padding: 14px 18px;
        border: 1px solid var(--gray-200);
        text-align: center;
        transition: var(--transition);
    }
    .stat-item:hover {
        box-shadow: var(--shadow-hover);
        transform: translateY(-2px);
    }
    .stat-item .number {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--primary);
    }
    .stat-item .number.green { color: var(--secondary); }
    .stat-item .number.yellow { color: var(--warning); }
    .stat-item .number.purple { color: #8B5CF6; }
    .stat-item .label {
        font-size: 0.7rem;
        color: var(--gray-500);
        margin-top: 2px;
    }
    
    .detail-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
    }
    .detail-card {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 20px 24px;
        box-shadow: var(--shadow);
    }
    .detail-card .card-title {
        font-weight: 600;
        font-size: 0.95rem;
        margin-bottom: 16px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--gray-100);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .detail-card .card-title i {
        color: var(--primary);
    }
    
    .detail-row {
        display: flex;
        padding: 8px 0;
        border-bottom: 1px solid var(--gray-50);
        font-size: 0.85rem;
    }
    .detail-row:last-child {
        border-bottom: none;
    }
    .detail-row .label {
        font-weight: 500;
        color: var(--gray-500);
        min-width: 140px;
        flex-shrink: 0;
    }
    .detail-row .value {
        color: var(--gray-700);
        word-break: break-word;
    }
    
    .list-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid var(--gray-50);
        font-size: 0.85rem;
    }
    .list-item:last-child {
        border-bottom: none;
    }
    .list-item .name {
        font-weight: 500;
    }
    .list-item .sub {
        font-size: 0.75rem;
        color: var(--gray-500);
    }
    
    .empty-state-small {
        text-align: center;
        padding: 20px;
        color: var(--gray-400);
        font-size: 0.85rem;
    }
    .empty-state-small i {
        font-size: 1.6rem;
        display: block;
        margin-bottom: 6px;
        color: var(--gray-300);
    }
    
    @media (max-width: 992px) {
        .detail-grid {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 768px) {
        .election-hero .hero-content {
            flex-direction: column;
            align-items: flex-start;
        }
        .election-hero .hero-actions {
            margin-left: 0;
            width: 100%;
        }
        .election-hero .hero-actions .btn-outline,
        .election-hero .hero-actions .btn-primary {
            flex: 1;
            justify-content: center;
        }
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .detail-row {
            flex-direction: column;
            padding: 6px 0;
        }
        .detail-row .label {
            min-width: auto;
            font-size: 0.75rem;
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .stat-item {
            padding: 10px 12px;
        }
        .stat-item .number {
            font-size: 1.2rem;
        }
        .election-hero {
            padding: 16px;
        }
        .election-hero .hero-icon {
            width: 48px;
            height: 48px;
            font-size: 1.2rem;
        }
        .election-hero .hero-info h1 {
            font-size: 1.2rem;
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
                    <i class="fas fa-vote-yea" style="color:var(--primary);margin-right:8px;"></i> Election Details
                    <small>View complete election information</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="elections-edit.php?id=<?php echo $election_id; ?>" class="btn-primary">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <a href="elections-results.php?id=<?php echo $election_id; ?>" class="btn-outline">
                    <i class="fas fa-chart-bar"></i> Results
                </a>
                <a href="elections-candidates.php?id=<?php echo $election_id; ?>" class="btn-outline">
                    <i class="fas fa-user-tie"></i> Candidates
                </a>
                <a href="elections.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Election Hero -->
        <div class="election-hero">
            <div class="hero-content">
                <div class="hero-icon">
                    <i class="fas fa-vote-yea"></i>
                </div>
                <div class="hero-info">
                    <h1><?php echo htmlspecialchars($election['name']); ?></h1>
                    <div class="meta">
                        <span><i class="fas fa-tag"></i> <?php echo ucfirst(str_replace('_', ' ', $election['type'])); ?></span>
                        <span><i class="fas fa-calendar-alt"></i> <?php echo date('M j, Y', strtotime($election['election_date'])); ?></span>
                        <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($election['tenant_name'] ?? 'N/A'); ?></span>
                        <span><i class="fas fa-code"></i> Cycle: <?php echo htmlspecialchars($election['cycle']); ?></span>
                        <span>
                            <span class="badge-status <?php echo $election['status']; ?>">
                                <span class="dot"></span>
                                <?php echo ucfirst($election['status']); ?>
                            </span>
                        </span>
                    </div>
                </div>
                <div class="hero-actions">
                    <?php if ($election['status'] !== 'cancelled' && $election['status'] !== 'completed'): ?>
                        <form method="POST" action="elections-edit.php?id=<?php echo $election_id; ?>" style="display:inline;">
                            <input type="hidden" name="action" value="change_status">
                            <input type="hidden" name="status" value="<?php echo $election['status'] === 'active' ? 'completed' : 'active'; ?>">
                            <button type="submit" class="btn-outline" style="padding:8px 16px;font-size:0.8rem;">
                                <i class="fas fa-<?php echo $election['status'] === 'active' ? 'check-circle' : 'play'; ?>"></i>
                                <?php echo $election['status'] === 'active' ? 'Mark Completed' : 'Activate'; ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-item"><div class="number"><?php echo number_format($stats['total_candidates']); ?></div><div class="label">Total Candidates</div></div>
            <div class="stat-item"><div class="number purple"><?php echo number_format($stats['total_results']); ?></div><div class="label">Total Results</div></div>
            <div class="stat-item"><div class="number green"><?php echo number_format($stats['verified_results']); ?></div><div class="label">Verified</div></div>
            <div class="stat-item"><div class="number yellow"><?php echo number_format($stats['pending_results']); ?></div><div class="label">Pending</div></div>
        </div>

        <!-- Detail Grid -->
        <div class="detail-grid">
            <!-- Left Column -->
            <div>
                <!-- Election Details -->
                <div class="detail-card">
                    <div class="card-title">
                        <i class="fas fa-info-circle"></i> Election Details
                    </div>
                    <div class="detail-row">
                        <span class="label">Election Name</span>
                        <span class="value"><?php echo htmlspecialchars($election['name']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Type</span>
                        <span class="value"><?php echo ucfirst(str_replace('_', ' ', $election['type'])); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Cycle</span>
                        <span class="value"><?php echo htmlspecialchars($election['cycle']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Status</span>
                        <span class="value">
                            <span class="badge-status <?php echo $election['status']; ?>">
                                <span class="dot"></span>
                                <?php echo ucfirst($election['status']); ?>
                            </span>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Election Date</span>
                        <span class="value"><?php echo date('M j, Y', strtotime($election['election_date'])); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Start Time</span>
                        <span class="value"><?php echo !empty($election['start_time']) ? date('g:i A', strtotime($election['start_time'])) : 'Not set'; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">End Time</span>
                        <span class="value"><?php echo !empty($election['end_time']) ? date('g:i A', strtotime($election['end_time'])) : 'Not set'; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Tenant</span>
                        <span class="value"><?php echo htmlspecialchars($election['tenant_name'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Created By</span>
                        <span class="value"><?php echo htmlspecialchars($election['created_by_name'] ?? 'System'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Created At</span>
                        <span class="value"><?php echo date('M j, Y g:i A', strtotime($election['created_at'])); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Last Updated</span>
                        <span class="value"><?php echo date('M j, Y g:i A', strtotime($election['updated_at'])); ?></span>
                    </div>
                    <?php if (!empty($election['description'])): ?>
                        <div class="detail-row" style="flex-direction:column;align-items:flex-start;">
                            <span class="label">Description</span>
                            <span class="value" style="margin-top:4px;"><?php echo nl2br(htmlspecialchars($election['description'])); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Quick Actions -->
                <div class="detail-card">
                    <div class="card-title">
                        <i class="fas fa-bolt" style="color:var(--primary);"></i> Quick Actions
                    </div>
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        <a href="elections-edit.php?id=<?php echo $election_id; ?>" class="btn-primary" style="justify-content:center;width:100%;">
                            <i class="fas fa-edit"></i> Edit Election
                        </a>
                        <a href="elections-results.php?id=<?php echo $election_id; ?>" class="btn-outline" style="justify-content:center;width:100%;">
                            <i class="fas fa-chart-bar"></i> View Results
                        </a>
                        <a href="elections-candidates.php?id=<?php echo $election_id; ?>" class="btn-outline" style="justify-content:center;width:100%;">
                            <i class="fas fa-user-tie"></i> Manage Candidates
                        </a>
                        <a href="elections-pus.php?id=<?php echo $election_id; ?>" class="btn-outline" style="justify-content:center;width:100%;">
                            <i class="fas fa-map-marker-alt"></i> Manage Polling Units
                        </a>
                        <a href="elections-export.php?id=<?php echo $election_id; ?>" class="btn-outline" style="justify-content:center;width:100%;">
                            <i class="fas fa-file-export"></i> Export Data
                        </a>
                    </div>
                </div>

                <!-- Jurisdiction -->
                <div class="detail-card" style="margin-top:16px;">
                    <div class="card-title">
                        <i class="fas fa-map-marked-alt" style="color:var(--primary);"></i> Jurisdiction
                    </div>
                    <?php
                    $states_list = json_decode($election['states_json'] ?? '[]', true);
                    if (!empty($states_list)):
                        $state_names = [];
                        foreach ($states_list as $state_id) {
                            foreach ($states as $s) {
                                if ($s['id'] == $state_id) {
                                    $state_names[] = $s['name'];
                                    break;
                                }
                            }
                        }
                    ?>
                        <div style="font-size:0.85rem;color:var(--gray-600);">
                            <?php echo implode(', ', $state_names); ?>
                        </div>
                    <?php else: ?>
                        <div style="font-size:0.85rem;color:var(--gray-400);">
                            <i class="fas fa-globe-africa"></i> All States
                        </div>
                    <?php endif; ?>
                    <div style="margin-top:8px;font-size:0.75rem;color:var(--gray-400);">
                        <i class="fas fa-info-circle"></i> 
                        <?php echo !empty($states_list) ? count($states_list) . ' states selected' : 'All states included'; ?>
                    </div>
                </div>
            </div>
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
// SEARCH
// ============================================================
var searchInput = document.getElementById('searchInput');
var searchResults = document.getElementById('searchResults');
var searchTimeout;

if (searchInput) {
    searchInput.addEventListener('input', function() {
        var query = this.value.trim();
        clearTimeout(searchTimeout);
        if (query.length < 2) {
            if (searchResults) searchResults.classList.remove('active');
            return;
        }
        searchTimeout = setTimeout(function() {
            fetch('search.php?q=' + encodeURIComponent(query))
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (searchResults) {
                        searchResults.innerHTML = '';
                        if (data && data.length > 0) {
                            data.forEach(function(item) {
                                var div = document.createElement('a');
                                div.className = 'result-item';
                                div.href = item.url || '#';
                                div.innerHTML = '<i class="fas ' + (item.icon || 'fa-file') + '"></i><span class="text-truncate">' + (item.label || item.name || '') + '</span><span class="result-type">' + ((item.type || '').charAt(0).toUpperCase() + (item.type || '').slice(1)) + '</span>';
                                searchResults.appendChild(div);
                            });
                            searchResults.classList.add('active');
                        } else {
                            searchResults.innerHTML = '<div style="padding:12px;text-align:center;color:var(--gray-500);font-size:0.8rem;"><i class="fas fa-search" style="display:block;font-size:1.2rem;margin-bottom:4px;"></i>No results found</div>';
                            searchResults.classList.add('active');
                        }
                    }
                })
                .catch(function() {});
        }, 300);
    });

    document.addEventListener('click', function(e) {
        var wrapper = document.querySelector('.search-wrapper');
        if (wrapper && !wrapper.contains(e.target) && searchResults) {
            searchResults.classList.remove('active');
        }
    });
}
</script>
</body>
</html>