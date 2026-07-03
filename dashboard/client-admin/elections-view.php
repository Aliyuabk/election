<?php
// ============================================================
// ELECTION VIEW - CLIENT ADMIN
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
    $stmt = $db->prepare("
        SELECT e.*, u.full_name as created_by_name
        FROM elections e
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.id = ? AND e.tenant_id = ? AND e.deleted_at IS NULL
    ");
    $stmt->execute([$election_id, $tenant_id]);
    $election = $stmt->fetch();
} catch (Exception $e) {
    // Continue
}

if (!$election) {
    header('Location: elections.php');
    exit();
}

// ============================================================
// FETCH STATISTICS
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
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM polling_units pu WHERE pu.id IN (SELECT JSON_EXTRACT(e.pus_json, '$') FROM elections e WHERE e.id = ?)");
    $stmt->execute([$election_id]);
    // Fallback to counting from the pus_json field
    $pus_json = json_decode($election['pus_json'] ?? '[]', true);
    $stats['total_polling_units'] = count($pus_json);
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM results_ec8a WHERE election_id = ?");
    $stmt->execute([$election_id]);
    $stats['total_results'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM results_ec8a WHERE election_id = ? AND status = 'verified'");
    $stmt->execute([$election_id]);
    $stats['verified_results'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM results_ec8a WHERE election_id = ? AND status = 'pending'");
    $stmt->execute([$election_id]);
    $stats['pending_results'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    // Continue
}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       ELECTION VIEW - CLIENT ADMIN STYLES
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
    .btn-sm.purple { background: #F5F3FF; color: #5B21B6; }
    .btn-sm.purple:hover { background: #EDE9FE; }
    
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
    .stat-item .number.blue { color: #3B82F6; }
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
    
    .doc-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px 0;
        border-bottom: 1px solid var(--gray-50);
    }
    .doc-item:last-child {
        border-bottom: none;
    }
    .doc-item .doc-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: #FEF2F2;
        color: #DC2626;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }
    .doc-item .doc-info {
        flex: 1;
    }
    .doc-item .doc-info .doc-name {
        font-weight: 500;
        font-size: 0.85rem;
    }
    .doc-item .doc-info .doc-meta {
        font-size: 0.7rem;
        color: var(--gray-400);
    }
    .doc-item .doc-actions {
        display: flex;
        gap: 6px;
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
    
    .tab-container {
        margin-top: 16px;
    }
    .tab-buttons {
        display: flex;
        gap: 4px;
        border-bottom: 2px solid var(--gray-200);
        margin-bottom: 16px;
        padding: 0 4px;
    }
    .tab-btn {
        padding: 10px 20px;
        border: none;
        background: none;
        font-family: 'Inter', sans-serif;
        font-size: 0.85rem;
        font-weight: 500;
        color: var(--gray-500);
        cursor: pointer;
        transition: var(--transition);
        border-bottom: 2px solid transparent;
        margin-bottom: -2px;
    }
    .tab-btn:hover {
        color: var(--gray-700);
    }
    .tab-btn.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
    }
    .tab-content {
        display: none;
    }
    .tab-content.active {
        display: block;
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
        .tab-buttons {
            flex-wrap: wrap;
        }
        .tab-btn {
            padding: 8px 14px;
            font-size: 0.8rem;
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
                <a href="elections-candidates.php?id=<?php echo $election_id; ?>" class="btn-outline">
                    <i class="fas fa-user-tie"></i> Candidates
                </a>
                <a href="elections-pus.php?id=<?php echo $election_id; ?>" class="btn-outline">
                    <i class="fas fa-map-marker-alt"></i> Polling Units
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
                    <a href="elections-results.php?id=<?php echo $election_id; ?>" class="btn-outline">
                        <i class="fas fa-chart-bar"></i> Results
                    </a>
                    <a href="elections-export.php?id=<?php echo $election_id; ?>" class="btn-outline">
                        <i class="fas fa-file-export"></i> Export
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-item"><div class="number"><?php echo number_format($stats['total_candidates']); ?></div><div class="label">Total Candidates</div></div>
            <div class="stat-item"><div class="number purple"><?php echo number_format($stats['total_polling_units']); ?></div><div class="label">Polling Units</div></div>
            <div class="stat-item"><div class="number blue"><?php echo number_format($stats['total_results']); ?></div><div class="label">Total Results</div></div>
            <div class="stat-item"><div class="number green"><?php echo number_format($stats['verified_results']); ?></div><div class="label">Verified</div></div>
            <div class="stat-item"><div class="number yellow"><?php echo number_format($stats['pending_results']); ?></div><div class="label">Pending</div></div>
        </div>

        <!-- Tabs -->
        <div class="tab-container">
            <div class="tab-buttons">
                <button class="tab-btn active" data-tab="details" onclick="switchTab('details')">
                    <i class="fas fa-info-circle"></i> Details
                </button>
                <button class="tab-btn" data-tab="candidates" onclick="switchTab('candidates')">
                    <i class="fas fa-user-tie"></i> Candidates
                </button>
                <button class="tab-btn" data-tab="polling_units" onclick="switchTab('polling_units')">
                    <i class="fas fa-map-marker-alt"></i> Polling Units
                </button>
                <button class="tab-btn" data-tab="documents" onclick="switchTab('documents')">
                    <i class="fas fa-file-alt"></i> Documents
                </button>
            </div>

            <!-- Details Tab -->
            <div id="tab-details" class="tab-content active">
                <div class="detail-grid">
                    <!-- Left Column -->
                    <div>
                        <div class="detail-card">
                            <div class="card-title">
                                <i class="fas fa-info-circle" style="color:var(--primary);"></i> Election Details
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
                        <div class="detail-card">
                            <div class="card-title">
                                <i class="fas fa-bolt" style="color:var(--primary);"></i> Quick Actions
                            </div>
                            <div style="display:flex;flex-direction:column;gap:8px;">
                                <a href="elections-edit.php?id=<?php echo $election_id; ?>" class="btn-primary" style="justify-content:center;width:100%;">
                                    <i class="fas fa-edit"></i> Edit Election
                                </a>
                                <a href="elections-candidates.php?id=<?php echo $election_id; ?>" class="btn-outline" style="justify-content:center;width:100%;">
                                    <i class="fas fa-user-tie"></i> Manage Candidates
                                </a>
                                <a href="elections-pus.php?id=<?php echo $election_id; ?>" class="btn-outline" style="justify-content:center;width:100%;">
                                    <i class="fas fa-map-marker-alt"></i> Manage Polling Units
                                </a>
                                <a href="elections-results.php?id=<?php echo $election_id; ?>" class="btn-outline" style="justify-content:center;width:100%;">
                                    <i class="fas fa-chart-bar"></i> View Results
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
                            ?>
                                <div style="font-size:0.85rem;color:var(--gray-600);">
                                    <?php 
                                    $state_names = [];
                                    foreach ($states_list as $state_id) {
                                        // Fetch state name from database or use ID
                                        $state_names[] = "State ID: $state_id";
                                    }
                                    echo implode(', ', $state_names); 
                                    ?>
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

            <!-- Candidates Tab -->
            <div id="tab-candidates" class="tab-content">
                <div class="detail-card">
                    <div class="card-title">
                        <i class="fas fa-user-tie" style="color:var(--primary);"></i> Candidates
                        <a href="elections-candidates.php?id=<?php echo $election_id; ?>" style="margin-left:auto;font-size:0.8rem;color:var(--primary);text-decoration:none;">
                            Manage All →
                        </a>
                    </div>
                    <?php
                    $candidates = [];
                    try {
                        $stmt = $db->prepare("
                            SELECT c.*, p.name as party_name, p.acronym as party_acronym
                            FROM candidates c
                            LEFT JOIN political_parties p ON c.party_id = p.id
                            WHERE c.election_id = ?
                            ORDER BY c.position, c.last_name
                            LIMIT 10
                        ");
                        $stmt->execute([$election_id]);
                        $candidates = $stmt->fetchAll();
                    } catch (Exception $e) {}
                    ?>
                    <?php if (count($candidates) > 0): ?>
                        <?php foreach ($candidates as $candidate): ?>
                            <div class="list-item">
                                <div>
                                    <div class="name"><?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?></div>
                                    <div class="sub">
                                        <?php echo htmlspecialchars($candidate['position']); ?>
                                        <?php if (!empty($candidate['party_acronym'])): ?>
                                            · <?php echo htmlspecialchars($candidate['party_acronym']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="badge-status <?php echo $candidate['is_active'] ? 'active' : 'suspended'; ?>">
                                    <span class="dot"></span>
                                    <?php echo $candidate['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($candidates) >= 10): ?>
                            <div style="text-align:center;padding-top:8px;">
                                <a href="elections-candidates.php?id=<?php echo $election_id; ?>" style="color:var(--primary);text-decoration:none;font-size:0.8rem;">
                                    View all candidates →
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state-small">
                            <i class="fas fa-user-tie"></i>
                            No candidates added yet
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Polling Units Tab -->
            <div id="tab-polling_units" class="tab-content">
                <div class="detail-card">
                    <div class="card-title">
                        <i class="fas fa-map-marker-alt" style="color:var(--primary);"></i> Polling Units
                        <a href="elections-pus.php?id=<?php echo $election_id; ?>" style="margin-left:auto;font-size:0.8rem;color:var(--primary);text-decoration:none;">
                            Manage All →
                        </a>
                    </div>
                    <?php
                    $pus = [];
                    $pus_ids = json_decode($election['pus_json'] ?? '[]', true);
                    if (!empty($pus_ids)) {
                        $placeholders = implode(',', array_fill(0, count($pus_ids), '?'));
                        try {
                            $stmt = $db->prepare("
                                SELECT pu.*, w.name as ward_name, l.name as lga_name, s.name as state_name
                                FROM polling_units pu
                                LEFT JOIN wards w ON pu.ward_id = w.id
                                LEFT JOIN lgas l ON w.lga_id = l.id
                                LEFT JOIN states s ON l.state_id = s.id
                                WHERE pu.id IN ($placeholders)
                                LIMIT 10
                            ");
                            $stmt->execute($pus_ids);
                            $pus = $stmt->fetchAll();
                        } catch (Exception $e) {}
                    }
                    ?>
                    <?php if (count($pus) > 0): ?>
                        <?php foreach ($pus as $pu): ?>
                            <div class="list-item">
                                <div>
                                    <div class="name"><?php echo htmlspecialchars($pu['code'] . ' - ' . $pu['name']); ?></div>
                                    <div class="sub">
                                        <?php echo htmlspecialchars($pu['ward_name'] ?? 'N/A'); ?>,
                                        <?php echo htmlspecialchars($pu['lga_name'] ?? 'N/A'); ?>
                                    </div>
                                </div>
                                <span style="font-size:0.7rem;color:var(--gray-400);">
                                    <?php echo number_format($pu['registered_voters'] ?? 0); ?> voters
                                </span>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($pus) >= 10): ?>
                            <div style="text-align:center;padding-top:8px;">
                                <a href="elections-pus.php?id=<?php echo $election_id; ?>" style="color:var(--primary);text-decoration:none;font-size:0.8rem;">
                                    View all polling units →
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state-small">
                            <i class="fas fa-map-marker-alt"></i>
                            No polling units assigned
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Documents Tab -->
            <div id="tab-documents" class="tab-content">
                <div class="detail-card">
                    <div class="card-title">
                        <i class="fas fa-file-alt" style="color:var(--primary);"></i> Documents
                        <button onclick="openModal('uploadDocModal')" style="margin-left:auto;padding:4px 14px;background:var(--primary);color:white;border:none;border-radius:6px;font-size:0.75rem;cursor:pointer;font-family:'Inter',sans-serif;font-weight:500;">
                            <i class="fas fa-upload"></i> Upload
                        </button>
                    </div>
                    
                    <?php
                    // List sample documents (in production, fetch from database)
                    $sample_docs = [
                        ['name' => 'Election Guidelines 2027.pdf', 'size' => '1.2 MB', 'date' => '2024-01-15', 'type' => 'guidelines'],
                        ['name' => 'Candidate List - Official.xlsx', 'size' => '856 KB', 'date' => '2024-01-20', 'type' => 'candidate_list'],
                        ['name' => 'Election Rules and Regulations.docx', 'size' => '2.4 MB', 'date' => '2024-01-10', 'type' => 'rules'],
                        ['name' => 'Official Notice - Election Date.pdf', 'size' => '345 KB', 'date' => '2024-01-25', 'type' => 'notice'],
                    ];
                    ?>
                    
                    <?php foreach ($sample_docs as $doc): ?>
                        <div class="doc-item">
                            <div class="doc-icon">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                            <div class="doc-info">
                                <div class="doc-name"><?php echo htmlspecialchars($doc['name']); ?></div>
                                <div class="doc-meta">
                                    <?php echo $doc['size']; ?> · <?php echo date('M j, Y', strtotime($doc['date'])); ?>
                                    · <span style="background:var(--gray-100);padding:1px 8px;border-radius:10px;font-size:0.6rem;">
                                        <?php echo ucfirst(str_replace('_', ' ', $doc['type'])); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="doc-actions">
                                <button class="btn-sm info" onclick="alert('Downloading <?php echo $doc['name']; ?>')">
                                    <i class="fas fa-download"></i>
                                </button>
                                <button class="btn-sm danger" onclick="if(confirm('Delete this document?')){alert('Deleted');}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div style="margin-top:12px;padding:12px 16px;background:#F5F3FF;border-radius:8px;border:1px solid #EDE9FE;color:#5B21B6;font-size:0.8rem;">
                        <i class="fas fa-info-circle"></i>
                        <strong>Supported file types:</strong> PDF, DOC, DOCX, XLS, XLSX, TXT (Max 10MB)
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Upload Document Modal -->
<div class="modal-overlay" id="uploadDocModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-upload" style="color:var(--primary);"></i> Upload Document</h3>
            <button class="close-btn" onclick="closeModal('uploadDocModal')">&times;</button>
        </div>
        <form method="POST" action="elections-create.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_document">
            <input type="hidden" name="election_id" value="<?php echo $election_id; ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label>Document Type <span class="required">*</span></label>
                    <select name="doc_type" required>
                        <option value="guidelines">Election Guidelines</option>
                        <option value="candidate_list">Candidate List</option>
                        <option value="rules">Rules</option>
                        <option value="notice">Official Notice</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>File <span class="required">*</span></label>
                    <div class="file-upload-area" onclick="document.getElementById('docFile').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Click to upload or drag &amp; drop</p>
                        <div class="file-types">Supported: PDF, DOC, DOCX, XLS, XLSX, TXT</div>
                        <input type="file" name="document" id="docFile" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt" required>
                    </div>
                    <div class="file-preview" id="docPreview">
                        <div class="file-name" id="docFileName">file.pdf</div>
                        <div class="file-size" id="docFileSize">0 KB</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('uploadDocModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Upload Document</button>
            </div>
        </form>
    </div>
</div>

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
    document.querySelectorAll('.tab-btn').forEach(function(btn) {
        btn.classList.remove('active');
        if (btn.dataset.tab === tab) {
            btn.classList.add('active');
        }
    });
    
    document.querySelectorAll('.tab-content').forEach(function(content) {
        content.classList.remove('active');
    });
    document.getElementById('tab-' + tab).classList.add('active');
}

// ============================================================
// MODAL FUNCTIONS
// ============================================================
function openModal(id) {
    document.getElementById(id).classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

// ============================================================
// FILE UPLOAD PREVIEW
// ============================================================
document.getElementById('docFile').addEventListener('change', function() {
    var preview = document.getElementById('docPreview');
    var fileName = document.getElementById('docFileName');
    var fileSize = document.getElementById('docFileSize');
    
    if (this.files && this.files[0]) {
        var file = this.files[0];
        fileName.textContent = file.name;
        fileSize.textContent = (file.size / 1024).toFixed(1) + ' KB';
        preview.classList.add('show');
    } else {
        preview.classList.remove('show');
    }
});

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