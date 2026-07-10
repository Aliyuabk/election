<?php
// ============================================================
// STATE COORDINATOR - DASHBOARD
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

// Only state coordinator can access
if (SessionManager::get('role_level') !== 'state') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'State Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$state_id = SessionManager::get('state_id');

// If state_id is not set in session, try to get it from user record
if (empty($state_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT state_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['state_id'])) {
            $state_id = $user['state_id'];
            SessionManager::set('state_id', $state_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching state_id: " . $e->getMessage());
    }
}

$db = getDB();

// ============================================================
// FETCH STATISTICS
// ============================================================
$stats = [
    'total_lgas' => 0,
    'total_coordinators' => 0,
    'total_elections' => 0,
    'active_elections' => 0,
    'total_pus' => 0,
    'reported_pus' => 0,
    'total_incidents' => 0,
    'pending_incidents' => 0,
    'agents_online' => 0,
    'pending_uploads' => 0,
    'broadcast_count' => 0,
    'total_ward_coordinators' => 0,
    'total_pu_agents' => 0
];

try {
    // Get state name
    $state_name = 'Unknown State';
    if (!empty($state_id)) {
        $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
        $stmt->execute([$state_id]);
        $state = $stmt->fetch(PDO::FETCH_ASSOC);
        $state_name = $state['name'] ?? 'Unknown State';
    }
    
    // Total LGAs in state
    if (!empty($state_id)) {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM lgas WHERE state_id = ? AND is_active = 1");
        $stmt->execute([$state_id]);
        $stats['total_lgas'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    }
    
    // Total LGA Coordinators in state (role level = 'lga')
    if (!empty($state_id)) {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE r.level = 'lga' 
            AND u.state_id = ? 
            AND u.deleted_at IS NULL
            AND u.status = 'active'
        ");
        $stmt->execute([$state_id]);
        $stats['total_coordinators'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    }
    
    // Total Ward Coordinators (role level = 'ward')
    if (!empty($state_id)) {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE r.level = 'ward' 
            AND u.state_id = ? 
            AND u.deleted_at IS NULL
            AND u.status = 'active'
        ");
        $stmt->execute([$state_id]);
        $stats['total_ward_coordinators'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    }
    
    // Total PU Agents (role level = 'pu_agent')
    if (!empty($state_id)) {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE r.level = 'pu_agent' 
            AND u.state_id = ? 
            AND u.deleted_at IS NULL
            AND u.status = 'active'
        ");
        $stmt->execute([$state_id]);
        $stats['total_pu_agents'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    }
    
    // Total elections in state - using states_json LIKE search
    if (!empty($tenant_id) && !empty($state_id)) {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM elections 
            WHERE tenant_id = ? 
            AND deleted_at IS NULL
            AND (
                states_json LIKE ? 
                OR states_json IS NULL 
                OR states_json = '[]'
            )
        ");
        $stmt->execute([$tenant_id, '%"' . $state_id . '"%']);
        $stats['total_elections'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    }
    
    // Active elections
    if (!empty($tenant_id) && !empty($state_id)) {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM elections 
            WHERE tenant_id = ? 
            AND deleted_at IS NULL
            AND status = 'active'
            AND (
                states_json LIKE ? 
                OR states_json IS NULL 
                OR states_json = '[]'
            )
        ");
        $stmt->execute([$tenant_id, '%"' . $state_id . '"%']);
        $stats['active_elections'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    }
    
    // Total PUs in state
    if (!empty($state_id)) {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM polling_units pu
            JOIN wards w ON pu.ward_id = w.id
            JOIN lgas l ON w.lga_id = l.id
            WHERE l.state_id = ? AND pu.is_active = 1
        ");
        $stmt->execute([$state_id]);
        $stats['total_pus'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    }
    
    // Reported PUs (has results submitted)
    if (!empty($state_id) && !empty($tenant_id)) {
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT r.pu_id) as count 
            FROM results_ec8a r
            JOIN polling_units pu ON r.pu_id = pu.id
            JOIN wards w ON pu.ward_id = w.id
            JOIN lgas l ON w.lga_id = l.id
            WHERE l.state_id = ? 
            AND r.tenant_id = ?
            AND r.status IN ('pending', 'verified', 'approved')
        ");
        $stmt->execute([$state_id, $tenant_id]);
        $stats['reported_pus'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    }
    
    // Total incidents
    if (!empty($state_id)) {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM incidents 
            WHERE state_id = ?
        ");
        $stmt->execute([$state_id]);
        $stats['total_incidents'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    }
    
    // Pending incidents
    if (!empty($state_id)) {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM incidents 
            WHERE state_id = ? 
            AND status IN ('reported', 'acknowledged', 'investigating')
        ");
        $stmt->execute([$state_id]);
        $stats['pending_incidents'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    }
    
    // Agents online (last 15 minutes) - users with pu_id assigned
    if (!empty($state_id)) {
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT u.id) as count
            FROM users u
            INNER JOIN user_sessions us ON u.id = us.user_id
            WHERE u.state_id = ? 
            AND us.is_active = 1 
            AND us.last_activity_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            AND u.status = 'active'
            AND u.deleted_at IS NULL
        ");
        $stmt->execute([$state_id]);
        $stats['agents_online'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    }
    
    // Pending uploads (pending results)
    if (!empty($state_id) && !empty($tenant_id)) {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count
            FROM results_ec8a r
            JOIN polling_units pu ON r.pu_id = pu.id
            JOIN wards w ON pu.ward_id = w.id
            JOIN lgas l ON w.lga_id = l.id
            WHERE l.state_id = ? 
            AND r.tenant_id = ?
            AND r.status = 'pending'
        ");
        $stmt->execute([$state_id, $tenant_id]);
        $stats['pending_uploads'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    }
    
    // Broadcast count
    if (!empty($tenant_id) && !empty($state_id)) {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM broadcasts 
            WHERE tenant_id = ? 
            AND status = 'sent'
            AND (
                target_ids_json LIKE ? 
                OR target_ids_json IS NULL
                OR target_audience IN ('all', 'state')
            )
        ");
        $stmt->execute([$tenant_id, '%"' . $state_id . '"%']);
        $stats['broadcast_count'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    }
    
} catch (Exception $e) {
    error_log("State Dashboard Error: " . $e->getMessage());
}

// ============================================================
// FETCH RECENT ACTIVITY
// ============================================================
$activities = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM activity_logs 
        WHERE tenant_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$tenant_id]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching activities: " . $e->getMessage());
}

// ============================================================
// FETCH RECENT ELECTIONS
// ============================================================
$recent_elections = [];
try {
    if (!empty($tenant_id) && !empty($state_id)) {
        $stmt = $db->prepare("
            SELECT id, name, type, status, election_date, created_at
            FROM elections 
            WHERE tenant_id = ? 
            AND deleted_at IS NULL
            AND (
                states_json LIKE ? 
                OR states_json IS NULL 
                OR states_json = '[]'
            )
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute([$tenant_id, '%"' . $state_id . '"%']);
        $recent_elections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error fetching recent elections: " . $e->getMessage());
}

include '../includes/base.php';
include '../includes/sidebar.php';

// Election types for display
$election_types = [
    'presidential' => 'Presidential',
    'governorship' => 'Governorship',
    'senatorial' => 'Senatorial',
    'house_of_reps' => 'House of Reps',
    'house_of_assembly' => 'House of Assembly',
    'lga_chairman' => 'LGA Chairman',
    'councillorship' => 'Councillorship',
    'party_primary' => 'Party Primary',
    'internal_party' => 'Internal Party'
];

$status_colors = [
    'draft' => 'secondary',
    'upcoming' => 'warning',
    'active' => 'success',
    'closed' => 'danger',
    'cancelled' => 'danger',
    'archived' => 'secondary'
];
?>

<style>
.dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 14px;
    margin-bottom: 24px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 16px 18px;
    border: 1px solid var(--gray-200);
    box-shadow: var(--shadow-sm);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}
.stat-card:hover {
    box-shadow: var(--shadow-hover);
    transform: translateY(-2px);
}
.stat-card .stat-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    color: white;
    margin-bottom: 6px;
}
.stat-card .stat-icon.blue { background: #3B82F6; }
.stat-card .stat-icon.green { background: #10B981; }
.stat-card .stat-icon.purple { background: #8B5CF6; }
.stat-card .stat-icon.yellow { background: #F59E0B; }
.stat-card .stat-icon.red { background: #EF4444; }
.stat-card .stat-icon.teal { background: #0D9488; }
.stat-card .stat-icon.orange { background: #F97316; }
.stat-card .stat-icon.pink { background: #EC4899; }
.stat-card .stat-icon.indigo { background: #6366F1; }

.stat-card .stat-number {
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--gray-800);
    line-height: 1.2;
}
.stat-card .stat-label {
    font-size: 0.72rem;
    color: var(--gray-500);
    margin-top: 2px;
    font-weight: 500;
}
.stat-card .stat-sub {
    font-size: 0.6rem;
    color: var(--gray-400);
    margin-top: 3px;
}
.stat-card .stat-sub.up { color: #10B981; }
.stat-card .stat-sub.down { color: #EF4444; }

.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
    margin-bottom: 24px;
}
.quick-action-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    text-decoration: none;
    color: var(--gray-700);
    transition: var(--transition);
}
.quick-action-item:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-hover);
    border-color: var(--primary);
}
.quick-action-item .action-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    flex-shrink: 0;
}
.quick-action-item .action-icon.blue { background: #EFF6FF; color: #3B82F6; }
.quick-action-item .action-icon.purple { background: #F5F3FF; color: #8B5CF6; }
.quick-action-item .action-icon.green { background: #ECFDF5; color: #10B981; }
.quick-action-item .action-icon.red { background: #FEF2F2; color: #EF4444; }
.quick-action-item .action-icon.yellow { background: #FFFBEB; color: #F59E0B; }
.quick-action-item .action-icon.teal { background: #F0FDFA; color: #0D9488; }

.quick-action-item .action-text .title {
    font-weight: 600;
    font-size: 0.82rem;
}
.quick-action-item .action-text .desc {
    font-size: 0.62rem;
    color: var(--gray-400);
}

.welcome-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.welcome-section h1 {
    font-size: 1.4rem;
    font-weight: 700;
    margin: 0;
}
.welcome-section h1 i {
    color: var(--primary);
}
.welcome-section .subtitle {
    color: var(--gray-500);
    margin: 2px 0 0;
    font-size: 0.9rem;
}
.welcome-section .subtitle i {
    margin-right: 4px;
}

.btn-primary-sm {
    padding: 8px 18px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.78rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}
.btn-primary-sm:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}

.btn-secondary-sm {
    padding: 8px 18px;
    background: var(--gray-100);
    color: var(--gray-700);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.78rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}
.btn-secondary-sm:hover {
    background: var(--gray-200);
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--gray-100);
}
.activity-item:last-child {
    border-bottom: none;
}
.activity-item .activity-icon {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.65rem;
    flex-shrink: 0;
    background: var(--primary-light);
    color: var(--primary);
}
.activity-item .activity-content {
    flex: 1;
}
.activity-item .activity-content .text {
    font-size: 0.78rem;
    color: var(--gray-700);
}
.activity-item .activity-content .time {
    font-size: 0.62rem;
    color: var(--gray-400);
}

.badge-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.6rem;
    font-weight: 600;
}
.badge-status .dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    display: inline-block;
}
.badge-status.success { background: #ECFDF5; color: #065F46; }
.badge-status.success .dot { background: #10B981; }
.badge-status.warning { background: #FFFBEB; color: #92400E; }
.badge-status.warning .dot { background: #F59E0B; }
.badge-status.danger { background: #FEF2F2; color: #991B1B; }
.badge-status.danger .dot { background: #EF4444; }
.badge-status.secondary { background: #F3F4F6; color: #6B7280; }
.badge-status.secondary .dot { background: #9CA3AF; }

.election-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid var(--gray-100);
}
.election-item:last-child {
    border-bottom: none;
}
.election-item .election-info .name {
    font-weight: 600;
    font-size: 0.82rem;
}
.election-item .election-info .meta {
    font-size: 0.65rem;
    color: var(--gray-400);
}
.election-item .election-info .meta span {
    margin-right: 10px;
}

@media (max-width: 768px) {
    .dashboard-stats {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    .quick-actions {
        grid-template-columns: 1fr 1fr;
    }
    .welcome-section {
        flex-direction: column;
        align-items: flex-start;
    }
    .welcome-section .actions {
        width: 100%;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .welcome-section .actions a {
        flex: 1;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .dashboard-stats {
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }
    .quick-actions {
        grid-template-columns: 1fr;
    }
    .stat-card {
        padding: 12px 14px;
    }
    .stat-card .stat-number {
        font-size: 1.3rem;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <div>
                <h1>
                    <i class="fas fa-map-marked-alt"></i>
                    Welcome, <?php echo htmlspecialchars($user_name); ?>!
                </h1>
                <p class="subtitle">
                    <i class="fas fa-flag"></i> 
                    <?php echo htmlspecialchars($state_name); ?> Coordinator Dashboard
                </p>
            </div>
            <div class="actions">
                <a href="monitor-lgas.php" class="btn-primary-sm">
                    <i class="fas fa-eye"></i> Monitor LGAs
                </a>
                <a href="broadcasts-create.php" class="btn-secondary-sm">
                    <i class="fas fa-bullhorn"></i> Broadcast
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-map-marker-alt"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_lgas']); ?></div>
                <div class="stat-label">LGAs</div>
                <div class="stat-sub">Total Local Governments</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-user-tie"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_coordinators']); ?></div>
                <div class="stat-label">LGA Coordinators</div>
                <div class="stat-sub"><?php echo number_format($stats['total_ward_coordinators']); ?> Ward Coordinators</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-vote-yea"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_elections']); ?></div>
                <div class="stat-label">Elections</div>
                <div class="stat-sub"><?php echo number_format($stats['active_elections']); ?> active</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-flag-checkered"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_pus']); ?></div>
                <div class="stat-label">Polling Units</div>
                <div class="stat-sub"><?php echo number_format($stats['reported_pus']); ?> reported</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon teal"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo number_format($stats['agents_online']); ?></div>
                <div class="stat-label">Agents Online</div>
                <div class="stat-sub up"><i class="fas fa-circle" style="color:#10B981;font-size:0.4rem;"></i> <?php echo number_format($stats['total_pu_agents']); ?> total agents</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-upload"></i></div>
                <div class="stat-number"><?php echo number_format($stats['pending_uploads']); ?></div>
                <div class="stat-label">Pending Uploads</div>
                <div class="stat-sub down"><i class="fas fa-clock"></i> Awaiting verification</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_incidents']); ?></div>
                <div class="stat-label">Incidents</div>
                <div class="stat-sub down"><?php echo number_format($stats['pending_incidents']); ?> pending</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon indigo"><i class="fas fa-bullhorn"></i></div>
                <div class="stat-number"><?php echo number_format($stats['broadcast_count']); ?></div>
                <div class="stat-label">Broadcasts</div>
                <div class="stat-sub"><i class="fas fa-envelope"></i> Messages sent</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="monitor-lgas.php" class="quick-action-item">
                <div class="action-icon blue"><i class="fas fa-map-marker-alt"></i></div>
                <div class="action-text">
                    <div class="title">Monitor LGAs</div>
                    <div class="desc">View LGA performance</div>
                </div>
            </a>
            
            <a href="lga-coordinators.php" class="quick-action-item">
                <div class="action-icon purple"><i class="fas fa-user-tie"></i></div>
                <div class="action-text">
                    <div class="title">Coordinators</div>
                    <div class="desc">Manage LGA coordinators</div>
                </div>
            </a>
            
            <a href="elections.php" class="quick-action-item">
                <div class="action-icon green"><i class="fas fa-vote-yea"></i></div>
                <div class="action-text">
                    <div class="title">Elections</div>
                    <div class="desc">View state elections</div>
                </div>
            </a>
            
            <a href="incidents.php" class="quick-action-item">
                <div class="action-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="action-text">
                    <div class="title">Incidents</div>
                    <div class="desc">View and manage incidents</div>
                </div>
            </a>
            
            <a href="result-verification.php" class="quick-action-item">
                <div class="action-icon yellow"><i class="fas fa-check-double"></i></div>
                <div class="action-text">
                    <div class="title">Results</div>
                    <div class="desc">Verify election results</div>
                </div>
            </a>
            
            <a href="broadcasts.php" class="quick-action-item">
                <div class="action-icon teal"><i class="fas fa-bullhorn"></i></div>
                <div class="action-text">
                    <div class="title">Broadcast</div>
                    <div class="desc">Send messages</div>
                </div>
            </a>
        </div>

        <!-- Recent Activity & Elections -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
            <!-- Recent Activity -->
            <div style="background:white;border-radius:var(--radius);padding:18px 20px;border:1px solid var(--gray-200);">
                <h4 style="font-size:0.85rem;font-weight:600;margin:0 0 14px;padding-bottom:10px;border-bottom:1px solid var(--gray-200);">
                    <i class="fas fa-clock" style="color:var(--primary);margin-right:6px;"></i>
                    Recent Activity
                </h4>
                <?php if (count($activities) > 0): ?>
                    <?php foreach ($activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-circle"></i>
                            </div>
                            <div class="activity-content">
                                <div class="text"><?php echo htmlspecialchars($activity['description'] ?? 'Activity recorded'); ?></div>
                                <div class="time"><?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center;padding:30px 20px;color:var(--gray-400);">
                        <i class="fas fa-clock" style="font-size:2rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                        <p style="margin:0;font-size:0.85rem;">No recent activity</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Elections -->
            <div style="background:white;border-radius:var(--radius);padding:18px 20px;border:1px solid var(--gray-200);">
                <h4 style="font-size:0.85rem;font-weight:600;margin:0 0 14px;padding-bottom:10px;border-bottom:1px solid var(--gray-200);">
                    <i class="fas fa-vote-yea" style="color:var(--primary);margin-right:6px;"></i>
                    Recent Elections
                </h4>
                <?php if (count($recent_elections) > 0): ?>
                    <?php foreach ($recent_elections as $election): ?>
                        <div class="election-item">
                            <div class="election-info">
                                <div class="name"><?php echo htmlspecialchars($election['name']); ?></div>
                                <div class="meta">
                                    <span><i class="fas fa-tag"></i> <?php echo $election_types[$election['type']] ?? ucfirst($election['type']); ?></span>
                                    <span><i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($election['election_date'])); ?></span>
                                </div>
                            </div>
                            <div>
                                <span class="badge-status <?php echo $status_colors[$election['status']] ?? 'secondary'; ?>">
                                    <span class="dot"></span>
                                    <?php echo ucfirst($election['status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center;padding:30px 20px;color:var(--gray-400);">
                        <i class="fas fa-vote-yea" style="font-size:2rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                        <p style="margin:0;font-size:0.85rem;">No elections found</p>
                    </div>
                <?php endif; ?>
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
        setTimeout(function() {
            preloader.style.display = 'none';
        }, 600);
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
</script>
</body>
</html>