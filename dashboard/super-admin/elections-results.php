<?php
// ============================================================
// ELECTION RESULTS - SUPER ADMINISTRATOR
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
// FETCH RESULTS DATA
// ============================================================
// Fetch EC8A Results (Polling Unit Level)
$ec8a_results = [];
try {
    $stmt = $db->prepare("
        SELECT r.*, u.full_name as agent_name, pu.name as pu_name
        FROM results_ec8a r
        LEFT JOIN users u ON r.agent_id = u.id
        LEFT JOIN polling_units pu ON r.pu_id = pu.id
        WHERE r.election_id = ?
        ORDER BY r.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$election_id]);
    $ec8a_results = $stmt->fetchAll();
} catch (Exception $e) {}

// Fetch EC8B Results (Ward Level)
$ec8b_results = [];
try {
    $stmt = $db->prepare("
        SELECT r.*, w.name as ward_name
        FROM results_ec8b r
        LEFT JOIN wards w ON r.ward_id = w.id
        WHERE r.election_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$election_id]);
    $ec8b_results = $stmt->fetchAll();
} catch (Exception $e) {}

// Fetch EC8C Results (LGA Level)
$ec8c_results = [];
try {
    $stmt = $db->prepare("
        SELECT r.*, l.name as lga_name
        FROM results_ec8c r
        LEFT JOIN lgas l ON r.lga_id = l.id
        WHERE r.election_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$election_id]);
    $ec8c_results = $stmt->fetchAll();
} catch (Exception $e) {}

// Fetch EC8D Results (State Level)
$ec8d_results = [];
try {
    $stmt = $db->prepare("
        SELECT r.*, s.name as state_name
        FROM results_ec8d r
        LEFT JOIN states s ON r.state_id = s.id
        WHERE r.election_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$election_id]);
    $ec8d_results = $stmt->fetchAll();
} catch (Exception $e) {}

// Fetch EC8E Results (National Level)
$ec8e_results = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM results_ec8e
        WHERE election_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$election_id]);
    $ec8e_results = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH SUMMARY STATISTICS
// ============================================================
$stats = [
    'total_pu_results' => count($ec8a_results),
    'total_ward_results' => count($ec8b_results),
    'total_lga_results' => count($ec8c_results),
    'total_state_results' => count($ec8d_results),
    'verified' => 0,
    'pending' => 0,
    'flagged' => 0
];

foreach ($ec8a_results as $r) {
    if ($r['status'] === 'verified') $stats['verified']++;
    elseif ($r['status'] === 'pending') $stats['pending']++;
    elseif ($r['status'] === 'flagged') $stats['flagged']++;
}

// Get user info
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       ELECTION RESULTS - PRO STYLES
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
    .btn-sm {
        padding: 4px 10px;
        font-size: 0.7rem;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .btn-sm.success { background: #ECFDF5; color: #065F46; }
    .btn-sm.success:hover { background: #D1FAE5; }
    .btn-sm.danger { background: #FEF2F2; color: #991B1B; }
    .btn-sm.danger:hover { background: #FEE2E2; }
    .btn-sm.warning { background: #FFFBEB; color: #92400E; }
    .btn-sm.warning:hover { background: #FEF3C7; }
    .btn-sm.info { background: #EFF6FF; color: #1E40AF; }
    .btn-sm.info:hover { background: #DBEAFE; }
    
    .election-header {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 20px 24px;
        box-shadow: var(--shadow);
        margin-bottom: 24px;
        position: relative;
        overflow: hidden;
    }
    .election-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
    }
    .election-header .election-info {
        display: flex;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
    }
    .election-header .election-info .icon {
        width: 56px;
        height: 56px;
        border-radius: 14px;
        background: #F5F3FF;
        color: #8B5CF6;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        flex-shrink: 0;
    }
    .election-header .election-info .details h2 {
        font-size: 1.2rem;
        font-weight: 700;
        margin-bottom: 2px;
    }
    .election-header .election-info .details .meta {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        font-size: 0.82rem;
        color: var(--gray-500);
    }
    .election-header .election-info .details .meta span {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .election-header .election-actions {
        margin-left: auto;
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
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
    .badge-status.flagged { background: #FEF2F2; color: #991B1B; }
    .badge-status.flagged .dot { background: #EF4444; }
    .badge-status.approved { background: #EFF6FF; color: #1E40AF; }
    .badge-status.approved .dot { background: #3B82F6; }
    .badge-status.rejected { background: #FEF2F2; color: #991B1B; }
    .badge-status.rejected .dot { background: #EF4444; }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
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
    .stat-item .number.red { color: var(--danger); }
    .stat-item .number.yellow { color: var(--warning); }
    .stat-item .number.purple { color: #8B5CF6; }
    .stat-item .number.blue { color: #3B82F6; }
    .stat-item .label {
        font-size: 0.7rem;
        color: var(--gray-500);
        margin-top: 2px;
    }
    
    .results-tabs {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 20px;
        background: white;
        padding: 12px 16px;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        box-shadow: var(--shadow);
    }
    .results-tab {
        padding: 8px 16px;
        border-radius: 8px;
        border: 1px solid transparent;
        background: transparent;
        color: var(--gray-600);
        font-family: 'Inter', sans-serif;
        font-size: 0.82rem;
        font-weight: 500;
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .results-tab:hover {
        background: var(--gray-50);
        border-color: var(--gray-200);
    }
    .results-tab.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }
    .results-tab .badge {
        background: var(--gray-200);
        color: var(--gray-600);
        padding: 1px 8px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
    }
    .results-tab.active .badge {
        background: rgba(255,255,255,0.3);
        color: white;
    }
    
    .table-container {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        overflow: hidden;
        box-shadow: var(--shadow);
    }
    .table-container .table-header {
        padding: 16px 20px;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        background: var(--gray-50);
    }
    .table-container .table-header .table-title {
        font-weight: 600;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .table-container .table-header .table-title .count {
        background: var(--primary);
        color: white;
        padding: 0 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
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
        padding: 10px 14px;
        text-align: left;
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--gray-500);
        border-bottom: 1px solid var(--gray-200);
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 2;
        background: var(--gray-50);
    }
    .data-table tbody td {
        padding: 8px 14px;
        border-bottom: 1px solid var(--gray-100);
        vertical-align: middle;
    }
    .data-table tbody tr:last-child td {
        border-bottom: none;
    }
    .data-table tbody tr:hover {
        background: var(--gray-50);
    }
    
    .action-dropdown {
        position: relative;
        display: inline-block;
    }
    .action-dropdown .dropdown-btn {
        background: none;
        border: none;
        padding: 4px 8px;
        cursor: pointer;
        color: var(--gray-400);
        font-size: 1.1rem;
        transition: var(--transition);
        border-radius: 6px;
    }
    .action-dropdown .dropdown-btn:hover {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .action-dropdown .dropdown-menu {
        position: absolute;
        right: 0;
        top: 100%;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.12);
        border: 1px solid var(--gray-200);
        min-width: 180px;
        padding: 6px;
        display: none;
        z-index: 50;
        animation: dropdownFade 0.2s ease;
    }
    @keyframes dropdownFade {
        from { opacity: 0; transform: translateY(-8px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .action-dropdown .dropdown-menu.open { display: block; }
    .action-dropdown .dropdown-menu a,
    .action-dropdown .dropdown-menu button {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 14px;
        width: 100%;
        border: none;
        background: none;
        font-family: 'Inter', sans-serif;
        font-size: 0.8rem;
        color: var(--gray-600);
        cursor: pointer;
        border-radius: 8px;
        transition: var(--transition);
        text-decoration: none;
    }
    .action-dropdown .dropdown-menu a:hover,
    .action-dropdown .dropdown-menu button:hover {
        background: var(--gray-50);
        color: var(--primary);
    }
    .action-dropdown .dropdown-menu .danger:hover {
        background: #FEF2F2;
        color: var(--danger);
    }
    .action-dropdown .dropdown-menu i {
        width: 16px;
        color: var(--gray-400);
        font-size: 0.85rem;
    }
    .action-dropdown .dropdown-menu .divider {
        height: 1px;
        background: var(--gray-100);
        margin: 4px 8px;
    }
    
    .empty-state {
        text-align: center;
        padding: 48px 20px;
        color: var(--gray-500);
    }
    .empty-state i {
        font-size: 3rem;
        color: var(--gray-300);
        display: block;
        margin-bottom: 12px;
    }
    .empty-state h4 {
        color: var(--gray-700);
        margin-bottom: 4px;
        font-size: 1rem;
    }
    .empty-state p {
        font-size: 0.85rem;
        color: var(--gray-400);
    }
    
    .tab-content {
        display: none;
    }
    .tab-content.active {
        display: block;
    }
    
    @media (max-width: 768px) {
        .election-header .election-info {
            flex-direction: column;
            align-items: flex-start;
        }
        .election-header .election-actions {
            margin-left: 0;
            width: 100%;
        }
        .election-header .election-actions .btn-outline,
        .election-header .election-actions .btn-primary {
            flex: 1;
            justify-content: center;
        }
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .results-tabs {
            flex-wrap: nowrap;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .results-tab {
            white-space: nowrap;
            font-size: 0.75rem;
            padding: 6px 12px;
        }
        .table-container {
            overflow-x: auto;
        }
        .data-table {
            font-size: 0.78rem;
        }
        .data-table th,
        .data-table td {
            padding: 6px 10px;
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
        .data-table th,
        .data-table td {
            padding: 4px 8px;
            font-size: 0.7rem;
        }
        .election-header {
            padding: 16px;
        }
        .election-header .election-info .icon {
            width: 44px;
            height: 44px;
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
                    <i class="fas fa-chart-bar" style="color:var(--primary);margin-right:8px;"></i> Election Results
                    <small>View and manage results for <?php echo htmlspecialchars($election['name']); ?></small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="elections.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Elections
                </a>
                <a href="elections-edit.php?id=<?php echo $election_id; ?>" class="btn-outline">
                    <i class="fas fa-edit"></i> Edit Election
                </a>
            </div>
        </div>

        <!-- Election Header -->
        <div class="election-header">
            <div class="election-info">
                <div class="icon">
                    <i class="fas fa-vote-yea"></i>
                </div>
                <div class="details">
                    <h2><?php echo htmlspecialchars($election['name']); ?></h2>
                    <div class="meta">
                        <span><i class="fas fa-tag"></i> <?php echo ucfirst(str_replace('_', ' ', $election['type'])); ?></span>
                        <span><i class="fas fa-calendar-alt"></i> <?php echo date('M j, Y', strtotime($election['election_date'])); ?></span>
                        <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($election['tenant_name'] ?? 'N/A'); ?></span>
                        <span><i class="fas fa-info-circle"></i> Status: <span class="badge-status <?php echo $election['status']; ?>"><span class="dot"></span> <?php echo ucfirst($election['status']); ?></span></span>
                    </div>
                </div>
                <div class="election-actions">
                    <a href="elections-results-export.php?id=<?php echo $election_id; ?>" class="btn-outline">
                        <i class="fas fa-file-export"></i> Export Results
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-item"><div class="number green"><?php echo number_format($stats['total_pu_results']); ?></div><div class="label">PU Results</div></div>
            <div class="stat-item"><div class="number blue"><?php echo number_format($stats['total_ward_results']); ?></div><div class="label">Ward Results</div></div>
            <div class="stat-item"><div class="number purple"><?php echo number_format($stats['total_lga_results']); ?></div><div class="label">LGA Results</div></div>
            <div class="stat-item"><div class="number"><?php echo number_format($stats['total_state_results']); ?></div><div class="label">State Results</div></div>
            <div class="stat-item"><div class="number green"><?php echo number_format($stats['verified']); ?></div><div class="label">Verified</div></div>
            <div class="stat-item"><div class="number yellow"><?php echo number_format($stats['pending']); ?></div><div class="label">Pending</div></div>
            <div class="stat-item"><div class="number red"><?php echo number_format($stats['flagged']); ?></div><div class="label">Flagged</div></div>
        </div>

        <!-- Results Tabs -->
        <div class="results-tabs">
            <button class="results-tab active" data-tab="ec8a" onclick="switchTab('ec8a')">
                <i class="fas fa-flag-checkered"></i> EC8A (PU)
                <span class="badge"><?php echo count($ec8a_results); ?></span>
            </button>
            <button class="results-tab" data-tab="ec8b" onclick="switchTab('ec8b')">
                <i class="fas fa-layer-group"></i> EC8B (Ward)
                <span class="badge"><?php echo count($ec8b_results); ?></span>
            </button>
            <button class="results-tab" data-tab="ec8c" onclick="switchTab('ec8c')">
                <i class="fas fa-map"></i> EC8C (LGA)
                <span class="badge"><?php echo count($ec8c_results); ?></span>
            </button>
            <button class="results-tab" data-tab="ec8d" onclick="switchTab('ec8d')">
                <i class="fas fa-map-marked-alt"></i> EC8D (State)
                <span class="badge"><?php echo count($ec8d_results); ?></span>
            </button>
            <button class="results-tab" data-tab="ec8e" onclick="switchTab('ec8e')">
                <i class="fas fa-flag"></i> EC8E (National)
                <span class="badge"><?php echo count($ec8e_results); ?></span>
            </button>
        </div>

        <!-- EC8A Tab -->
        <div id="tab-ec8a" class="tab-content active">
            <div class="table-container">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-list" style="color:var(--primary);"></i> EC8A - Polling Unit Results
                        <span class="count"><?php echo count($ec8a_results); ?></span>
                    </div>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>PU Code</th>
                            <th>PU Name</th>
                            <th>Agent</th>
                            <th>Registered Voters</th>
                            <th>Accredited</th>
                            <th>Valid Votes</th>
                            <th>Total Votes</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($ec8a_results) > 0): ?>
                            <?php foreach ($ec8a_results as $result): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($result['pu_code'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($result['pu_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($result['agent_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo number_format($result['registered_voters'] ?? 0); ?></td>
                                    <td><?php echo number_format($result['accredited_voters'] ?? 0); ?></td>
                                    <td><?php echo number_format($result['valid_votes'] ?? 0); ?></td>
                                    <td><?php echo number_format($result['total_votes_cast'] ?? 0); ?></td>
                                    <td>
                                        <span class="badge-status <?php echo $result['status'] ?? 'pending'; ?>">
                                            <span class="dot"></span>
                                            <?php echo ucfirst($result['status'] ?? 'Pending'); ?>
                                        </span>
                                    </td>
                                    <td style="font-size:0.7rem;color:var(--gray-500);">
                                        <?php echo date('M j, Y', strtotime($result['created_at'])); ?>
                                    </td>
                                    <td>
                                        <div class="action-dropdown">
                                            <button class="dropdown-btn" onclick="toggleDropdown(this)"><i class="fas fa-ellipsis-v"></i></button>
                                            <div class="dropdown-menu">
                                                <a href="#" onclick="viewResult('ec8a', <?php echo $result['id']; ?>)"><i class="fas fa-eye"></i> View</a>
                                                <?php if ($result['status'] === 'pending'): ?>
                                                    <button onclick="verifyResult('ec8a', <?php echo $result['id']; ?>)"><i class="fas fa-check"></i> Verify</button>
                                                    <button class="danger" onclick="flagResult('ec8a', <?php echo $result['id']; ?>)"><i class="fas fa-flag"></i> Flag</button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10">
                                    <div class="empty-state">
                                        <i class="fas fa-flag-checkered"></i>
                                        <h4>No EC8A results found</h4>
                                        <p>No polling unit results have been submitted for this election yet.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- EC8B Tab -->
        <div id="tab-ec8b" class="tab-content">
            <div class="table-container">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-layer-group" style="color:var(--secondary);"></i> EC8B - Ward Results
                        <span class="count"><?php echo count($ec8b_results); ?></span>
                    </div>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Ward</th>
                            <th>Valid Votes</th>
                            <th>Rejected Votes</th>
                            <th>Total Votes</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($ec8b_results) > 0): ?>
                            <?php foreach ($ec8b_results as $result): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($result['ward_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo number_format($result['valid_votes'] ?? 0); ?></td>
                                    <td><?php echo number_format($result['rejected_votes'] ?? 0); ?></td>
                                    <td><?php echo number_format($result['total_votes'] ?? 0); ?></td>
                                    <td>
                                        <span class="badge-status <?php echo $result['status'] ?? 'pending'; ?>">
                                            <span class="dot"></span>
                                            <?php echo ucfirst($result['status'] ?? 'Pending'); ?>
                                        </span>
                                    </td>
                                    <td style="font-size:0.7rem;color:var(--gray-500);">
                                        <?php echo date('M j, Y', strtotime($result['created_at'])); ?>
                                    </td>
                                    <td>
                                        <div class="action-dropdown">
                                            <button class="dropdown-btn" onclick="toggleDropdown(this)"><i class="fas fa-ellipsis-v"></i></button>
                                            <div class="dropdown-menu">
                                                <a href="#" onclick="viewResult('ec8b', <?php echo $result['id']; ?>)"><i class="fas fa-eye"></i> View</a>
                                                <?php if ($result['status'] === 'pending'): ?>
                                                    <button onclick="verifyResult('ec8b', <?php echo $result['id']; ?>)"><i class="fas fa-check"></i> Verify</button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="fas fa-layer-group"></i>
                                        <h4>No EC8B results found</h4>
                                        <p>No ward results have been compiled for this election yet.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- EC8C Tab -->
        <div id="tab-ec8c" class="tab-content">
            <div class="table-container">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-map" style="color:var(--warning);"></i> EC8C - LGA Results
                        <span class="count"><?php echo count($ec8c_results); ?></span>
                    </div>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>LGA</th>
                            <th>Valid Votes</th>
                            <th>Rejected Votes</th>
                            <th>Total Votes</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($ec8c_results) > 0): ?>
                            <?php foreach ($ec8c_results as $result): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($result['lga_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo number_format($result['valid_votes'] ?? 0); ?></td>
                                    <td><?php echo number_format($result['rejected_votes'] ?? 0); ?></td>
                                    <td><?php echo number_format($result['total_votes'] ?? 0); ?></td>
                                    <td>
                                        <span class="badge-status <?php echo $result['status'] ?? 'pending'; ?>">
                                            <span class="dot"></span>
                                            <?php echo ucfirst($result['status'] ?? 'Pending'); ?>
                                        </span>
                                    </td>
                                    <td style="font-size:0.7rem;color:var(--gray-500);">
                                        <?php echo date('M j, Y', strtotime($result['created_at'])); ?>
                                    </td>
                                    <td>
                                        <div class="action-dropdown">
                                            <button class="dropdown-btn" onclick="toggleDropdown(this)"><i class="fas fa-ellipsis-v"></i></button>
                                            <div class="dropdown-menu">
                                                <a href="#" onclick="viewResult('ec8c', <?php echo $result['id']; ?>)"><i class="fas fa-eye"></i> View</a>
                                                <?php if ($result['status'] === 'pending'): ?>
                                                    <button onclick="verifyResult('ec8c', <?php echo $result['id']; ?>)"><i class="fas fa-check"></i> Verify</button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="fas fa-map"></i>
                                        <h4>No EC8C results found</h4>
                                        <p>No LGA results have been compiled for this election yet.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- EC8D Tab -->
        <div id="tab-ec8d" class="tab-content">
            <div class="table-container">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-map-marked-alt" style="color:var(--info);"></i> EC8D - State Results
                        <span class="count"><?php echo count($ec8d_results); ?></span>
                    </div>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>State</th>
                            <th>Valid Votes</th>
                            <th>Rejected Votes</th>
                            <th>Total Votes</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($ec8d_results) > 0): ?>
                            <?php foreach ($ec8d_results as $result): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($result['state_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo number_format($result['valid_votes'] ?? 0); ?></td>
                                    <td><?php echo number_format($result['rejected_votes'] ?? 0); ?></td>
                                    <td><?php echo number_format($result['total_votes'] ?? 0); ?></td>
                                    <td>
                                        <span class="badge-status <?php echo $result['status'] ?? 'pending'; ?>">
                                            <span class="dot"></span>
                                            <?php echo ucfirst($result['status'] ?? 'Pending'); ?>
                                        </span>
                                    </td>
                                    <td style="font-size:0.7rem;color:var(--gray-500);">
                                        <?php echo date('M j, Y', strtotime($result['created_at'])); ?>
                                    </td>
                                    <td>
                                        <div class="action-dropdown">
                                            <button class="dropdown-btn" onclick="toggleDropdown(this)"><i class="fas fa-ellipsis-v"></i></button>
                                            <div class="dropdown-menu">
                                                <a href="#" onclick="viewResult('ec8d', <?php echo $result['id']; ?>)"><i class="fas fa-eye"></i> View</a>
                                                <?php if ($result['status'] === 'pending'): ?>
                                                    <button onclick="verifyResult('ec8d', <?php echo $result['id']; ?>)"><i class="fas fa-check"></i> Verify</button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="fas fa-map-marked-alt"></i>
                                        <h4>No EC8D results found</h4>
                                        <p>No state results have been compiled for this election yet.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- EC8E Tab -->
        <div id="tab-ec8e" class="tab-content">
            <div class="table-container">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-flag" style="color:var(--secondary);"></i> EC8E - National Results
                        <span class="count"><?php echo count($ec8e_results); ?></span>
                    </div>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Total Valid Votes</th>
                            <th>Total Rejected</th>
                            <th>Total Votes Cast</th>
                            <th>Status</th>
                            <th>Declaration Time</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($ec8e_results) > 0): ?>
                            <?php foreach ($ec8e_results as $result): ?>
                                <tr>
                                    <td><?php echo number_format($result['valid_votes'] ?? 0); ?></td>
                                    <td><?php echo number_format($result['rejected_votes'] ?? 0); ?></td>
                                    <td><?php echo number_format($result['total_votes'] ?? 0); ?></td>
                                    <td>
                                        <span class="badge-status <?php echo $result['status'] ?? 'pending'; ?>">
                                            <span class="dot"></span>
                                            <?php echo ucfirst($result['status'] ?? 'Pending'); ?>
                                        </span>
                                    </td>
                                    <td style="font-size:0.7rem;color:var(--gray-500);">
                                        <?php echo !empty($result['declaration_time']) ? date('M j, Y g:i A', strtotime($result['declaration_time'])) : 'Not declared'; ?>
                                    </td>
                                    <td>
                                        <div class="action-dropdown">
                                            <button class="dropdown-btn" onclick="toggleDropdown(this)"><i class="fas fa-ellipsis-v"></i></button>
                                            <div class="dropdown-menu">
                                                <a href="#" onclick="viewResult('ec8e', <?php echo $result['id']; ?>)"><i class="fas fa-eye"></i> View</a>
                                                <?php if ($result['status'] === 'pending'): ?>
                                                    <button onclick="verifyResult('ec8e', <?php echo $result['id']; ?>)"><i class="fas fa-check"></i> Verify</button>
                                                <?php endif; ?>
                                                <?php if ($result['status'] !== 'declared'): ?>
                                                    <button onclick="declareResult(<?php echo $result['id']; ?>)"><i class="fas fa-gavel"></i> Declare</button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <i class="fas fa-flag"></i>
                                        <h4>No EC8E results found</h4>
                                        <p>National results have not been compiled for this election yet.</p>
                                    </div>
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
// TAB SWITCHING
// ============================================================
function switchTab(tab) {
    // Update tabs
    document.querySelectorAll('.results-tab').forEach(function(el) {
        el.classList.remove('active');
        if (el.dataset.tab === tab) {
            el.classList.add('active');
        }
    });
    
    // Update content
    document.querySelectorAll('.tab-content').forEach(function(el) {
        el.classList.remove('active');
    });
    var target = document.getElementById('tab-' + tab);
    if (target) {
        target.classList.add('active');
    }
}

// ============================================================
// DROPDOWN FUNCTIONS
// ============================================================
function toggleDropdown(btn) {
    var menu = btn.nextElementSibling;
    var isOpen = menu.classList.contains('open');
    document.querySelectorAll('.action-dropdown .dropdown-menu').forEach(function(m) {
        m.classList.remove('open');
    });
    if (!isOpen) {
        menu.classList.toggle('open');
    }
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.action-dropdown')) {
        document.querySelectorAll('.action-dropdown .dropdown-menu').forEach(function(m) {
            m.classList.remove('open');
        });
    }
});

// ============================================================
// RESULT ACTION FUNCTIONS
// ============================================================
function viewResult(type, id) {
    alert('View ' + type.toUpperCase() + ' result ID: ' + id + '\nImplement full result view.');
}

function verifyResult(type, id) {
    if (!confirm('Verify this ' + type.toUpperCase() + ' result?')) return;
    
    // AJAX call to verify result
    fetch('elections-results-ajax.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'action=verify&type=' + type + '&id=' + id
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            alert('Result verified successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(function() {
        alert('An error occurred. Please try again.');
    });
}

function flagResult(type, id) {
    if (!confirm('Flag this ' + type.toUpperCase() + ' result for review?')) return;
    
    fetch('elections-results-ajax.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'action=flag&type=' + type + '&id=' + id
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            alert('Result flagged for review.');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(function() {
        alert('An error occurred. Please try again.');
    });
}

function declareResult(id) {
    if (!confirm('Declare this national result?')) return;
    
    fetch('elections-results-ajax.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'action=declare&id=' + id
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            alert('Result declared successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(function() {
        alert('An error occurred. Please try again.');
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