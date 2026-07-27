<?php
// ============================================================
// SENATORIAL COORDINATOR DASHBOARD
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

// Only Senatorial coordinator can access
if (SessionManager::get('role_level') !== 'senatorial') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$user_email = SessionManager::get('user_email');
$senatorial_id = SessionManager::get('senatorial_id');
$state_id = SessionManager::get('state_id');
$tenant_id = SessionManager::get('tenant_id');

$db = getDB();

// ============================================================
// FETCH SENATORIAL DISTRICT AND STATE NAMES
// ============================================================
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

// ============================================================
// FETCH DASHBOARD STATISTICS
// ============================================================

// Get LGAs in this senatorial district
$lga_ids = [];
try {
    $stmt = $db->prepare("SELECT lgas_json FROM senatorial_districts WHERE id = ?");
    $stmt->execute([$senatorial_id]);
    $lgas_json = $stmt->fetchColumn();
    if ($lgas_json) {
        $lga_ids = json_decode($lgas_json, true) ?: [];
    }
} catch (Exception $e) {
    $lga_ids = [];
}

$lga_list = !empty($lga_ids) ? implode(',', array_map('intval', $lga_ids)) : '0';

// ============================================================
// FETCH FEDERAL CONSTITUENCIES IN THIS SENATORIAL DISTRICT
// ============================================================
$federal_constituencies = [];
try {
    if (!empty($lga_ids)) {
        $stmt = $db->prepare("
            SELECT DISTINCT fc.id, fc.name, fc.code
            FROM federal_constituencies fc
            WHERE fc.state_id = ? AND fc.is_active = 1
            ORDER BY fc.name ASC
        ");
        $stmt->execute([$state_id]);
        $federal_constituencies = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $federal_constituencies = [];
}

// LGA Statistics
$lga_stats = [];
try {
    if (!empty($lga_ids)) {
        $stmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT l.id) as total_lgas,
                COUNT(DISTINCT w.id) as total_wards,
                COUNT(DISTINCT pu.id) as total_pus
            FROM lgas l
            LEFT JOIN wards w ON w.lga_id = l.id
            LEFT JOIN polling_units pu ON pu.ward_id = w.id
            WHERE l.id IN ($lga_list) AND l.is_active = 1
        ");
        $stmt->execute();
        $lga_stats = $stmt->fetch();
    } else {
        $lga_stats = ['total_lgas' => 0, 'total_wards' => 0, 'total_pus' => 0];
    }
} catch (Exception $e) {
    $lga_stats = ['total_lgas' => 0, 'total_wards' => 0, 'total_pus' => 0];
}

// ============================================================
// COORDINATOR STATISTICS
// ============================================================
$coordinator_stats = ['federal_constituency' => 0, 'lga' => 0, 'ward' => 0];
try {
    if (!empty($lga_ids)) {
        // LGA Coordinators
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT u.id) as count
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.tenant_id = ? AND u.status = 'active'
            AND r.level = 'lga'
            AND u.lga_id IN ($lga_list)
        ");
        $stmt->execute([$tenant_id]);
        $coordinator_stats['lga'] = (int)$stmt->fetchColumn();

        // Ward Coordinators
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT u.id) as count
            FROM users u
            JOIN roles r ON u.role_id = r.id
            JOIN wards w ON u.ward_id = w.id
            WHERE u.tenant_id = ? AND u.status = 'active'
            AND r.level = 'ward'
            AND w.lga_id IN ($lga_list)
        ");
        $stmt->execute([$tenant_id]);
        $coordinator_stats['ward'] = (int)$stmt->fetchColumn();

        // Federal Constituency Coordinators
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT u.id) as count
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.tenant_id = ? AND u.status = 'active'
            AND r.level = 'federal_constituency'
            AND u.state_id = ?
        ");
        $stmt->execute([$tenant_id, $state_id]);
        $coordinator_stats['federal_constituency'] = (int)$stmt->fetchColumn();
    }
} catch (Exception $e) {
    $coordinator_stats = ['federal_constituency' => 0, 'lga' => 0, 'ward' => 0];
}

// ============================================================
// AGENT STATISTICS
// ============================================================
$agent_stats = ['pu_agents' => 0, 'party_agents' => 0, 'observers' => 0, 'volunteers' => 0];
try {
    if (!empty($lga_ids)) {
        // PU Agents
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT u.id) as count
            FROM users u
            JOIN roles r ON u.role_id = r.id
            JOIN polling_units pu ON u.pu_id = pu.id
            JOIN wards w ON pu.ward_id = w.id
            WHERE u.tenant_id = ? AND u.status = 'active'
            AND r.level = 'pu_agent'
            AND w.lga_id IN ($lga_list)
        ");
        $stmt->execute([$tenant_id]);
        $agent_stats['pu_agents'] = (int)$stmt->fetchColumn();

        // Party Agents
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT u.id) as count
            FROM users u
            JOIN roles r ON u.role_id = r.id
            JOIN polling_units pu ON u.pu_id = pu.id
            JOIN wards w ON pu.ward_id = w.id
            WHERE u.tenant_id = ? AND u.status = 'active'
            AND r.level = 'party_agent'
            AND w.lga_id IN ($lga_list)
        ");
        $stmt->execute([$tenant_id]);
        $agent_stats['party_agents'] = (int)$stmt->fetchColumn();

        // Observers
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT u.id) as count
            FROM users u
            JOIN roles r ON u.role_id = r.id
            JOIN polling_units pu ON u.pu_id = pu.id
            JOIN wards w ON pu.ward_id = w.id
            WHERE u.tenant_id = ? AND u.status = 'active'
            AND r.level = 'observer'
            AND w.lga_id IN ($lga_list)
        ");
        $stmt->execute([$tenant_id]);
        $agent_stats['observers'] = (int)$stmt->fetchColumn();

        // Volunteers
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT u.id) as count
            FROM users u
            JOIN roles r ON u.role_id = r.id
            JOIN polling_units pu ON u.pu_id = pu.id
            JOIN wards w ON pu.ward_id = w.id
            WHERE u.tenant_id = ? AND u.status = 'active'
            AND r.level = 'volunteer'
            AND w.lga_id IN ($lga_list)
        ");
        $stmt->execute([$tenant_id]);
        $agent_stats['volunteers'] = (int)$stmt->fetchColumn();
    }
} catch (Exception $e) {
    $agent_stats = ['pu_agents' => 0, 'party_agents' => 0, 'observers' => 0, 'volunteers' => 0];
}

// Election Statistics
$election_stats = [];
try {
    if (!empty($lga_ids)) {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'upcoming' THEN 1 ELSE 0 END) as upcoming,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft
            FROM elections 
            WHERE tenant_id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$tenant_id]);
        $election_stats = $stmt->fetch();
    } else {
        $election_stats = ['total' => 0, 'active' => 0, 'upcoming' => 0, 'completed' => 0, 'draft' => 0];
    }
} catch (Exception $e) {
    $election_stats = ['total' => 0, 'active' => 0, 'upcoming' => 0, 'completed' => 0, 'draft' => 0];
}

// ============================================================
// POLLING UNIT REPORTING STATUS
// ============================================================
$pu_reporting_status = ['submitted' => 0, 'pending' => 0, 'verified' => 0, 'total' => 0];
try {
    if (!empty($lga_ids)) {
        $stmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT pu.id) as total,
                SUM(CASE WHEN r.id IS NOT NULL THEN 1 ELSE 0 END) as submitted,
                SUM(CASE WHEN r.id IS NOT NULL AND r.status = 'verified' THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN r.id IS NULL OR r.status = 'pending' THEN 1 ELSE 0 END) as pending
            FROM polling_units pu
            JOIN wards w ON pu.ward_id = w.id
            LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
            WHERE w.lga_id IN ($lga_list) AND pu.is_active = 1
        ");
        $stmt->execute([$tenant_id]);
        $pu_reporting_status = $stmt->fetch();
    }
} catch (Exception $e) {
    $pu_reporting_status = ['submitted' => 0, 'pending' => 0, 'verified' => 0, 'total' => 0];
}

// Result Statistics for this Senatorial District
$result_stats = [];
try {
    if (!empty($lga_ids)) {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_results,
                SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN r.status = 'flagged' THEN 1 ELSE 0 END) as flagged,
                SUM(CASE WHEN r.status = 'rejected' THEN 1 ELSE 0 END) as rejected
            FROM results_ec8a r
            JOIN polling_units pu ON r.pu_id = pu.id
            JOIN wards w ON pu.ward_id = w.id
            WHERE r.tenant_id = ? AND w.lga_id IN ($lga_list)
        ");
        $stmt->execute([$tenant_id]);
        $result_stats = $stmt->fetch();
    } else {
        $result_stats = ['total_results' => 0, 'verified' => 0, 'pending' => 0, 'flagged' => 0, 'rejected' => 0];
    }
} catch (Exception $e) {
    $result_stats = ['total_results' => 0, 'verified' => 0, 'pending' => 0, 'flagged' => 0, 'rejected' => 0];
}

// Incident Statistics for this Senatorial District
$incident_stats = [];
try {
    if (!empty($lga_ids)) {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'reported' THEN 1 ELSE 0 END) as reported,
                SUM(CASE WHEN status = 'investigating' THEN 1 ELSE 0 END) as investigating,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical,
                SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as high
            FROM incidents 
            WHERE tenant_id = ? AND lga_id IN ($lga_list)
        ");
        $stmt->execute([$tenant_id]);
        $incident_stats = $stmt->fetch();
    } else {
        $incident_stats = ['total' => 0, 'reported' => 0, 'investigating' => 0, 'resolved' => 0, 'critical' => 0, 'high' => 0];
    }
} catch (Exception $e) {
    $incident_stats = ['total' => 0, 'reported' => 0, 'investigating' => 0, 'resolved' => 0, 'critical' => 0, 'high' => 0];
}

// LGA Performance
$lga_performance = [];
try {
    if (!empty($lga_ids)) {
        $stmt = $db->prepare("
            SELECT 
                l.id as lga_id,
                l.name as lga_name,
                COUNT(r.id) as verified_count,
                COUNT(DISTINCT pu.id) as total_pus,
                ROUND((COUNT(r.id) / COUNT(DISTINCT pu.id)) * 100, 1) as completion_rate
            FROM lgas l
            LEFT JOIN wards w ON w.lga_id = l.id
            LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
            LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.status = 'verified'
            WHERE l.id IN ($lga_list)
            GROUP BY l.id, l.name
            ORDER BY completion_rate DESC, verified_count DESC
        ");
        $stmt->execute();
        $lga_performance = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $lga_performance = [];
}

// Recent Activities
$recent_activities = [];
try {
    if (!empty($lga_ids)) {
        $stmt = $db->prepare("
            SELECT a.*, u.full_name as user_name, u.photograph_url as user_avatar
            FROM activity_logs a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE a.tenant_id = ? 
            AND (
                a.entity_type IN ('lga', 'ward', 'pu', 'user', 'result', 'incident')
                OR a.activity_type IN ('login', 'logout', 'user_created', 'result_submitted', 'result_verified', 'incident_reported')
            )
            ORDER BY a.created_at DESC
            LIMIT 15
        ");
        $stmt->execute([$tenant_id]);
        $recent_activities = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $recent_activities = [];
}

// Broadcast Summary
$broadcast_stats = [];
try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
            SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled
        FROM broadcasts 
        WHERE tenant_id = ? AND (election_id IS NULL OR election_id IN (SELECT id FROM elections WHERE tenant_id = ? AND deleted_at IS NULL))
    ");
    $stmt->execute([$tenant_id, $tenant_id]);
    $broadcast_stats = $stmt->fetch();
} catch (Exception $e) {
    $broadcast_stats = ['total' => 0, 'sent' => 0, 'draft' => 0, 'scheduled' => 0];
}

// Include base and sidebar
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
/* ============================================================
   DASHBOARD STYLES - Senatorial Coordinator
   ============================================================ */
.welcome-section {
    margin-bottom: 24px;
}
.welcome-section h2 {
    font-size: 1.4rem;
    font-weight: 700;
    margin-bottom: 4px;
}
.welcome-section p {
    color: var(--gray-500);
    font-size: 0.9rem;
    margin-bottom: 8px;
}
.breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.8rem;
    color: var(--gray-400);
}
.breadcrumb i {
    font-size: 0.75rem;
}
.breadcrumb span {
    color: var(--gray-600);
}

/* Stats Grid */
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
    box-shadow: var(--shadow-sm);
    transition: var(--transition);
}
.stat-card:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow);
    transform: translateY(-2px);
}
.stat-card .stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 10px;
    font-size: 1.1rem;
}
.stat-card .stat-icon.blue { background: #DBEAFE; color: #2563EB; }
.stat-card .stat-icon.green { background: #D1FAE5; color: #059669; }
.stat-card .stat-icon.purple { background: #EDE9FE; color: #7C3AED; }
.stat-card .stat-icon.yellow { background: #FEF3C7; color: #D97706; }
.stat-card .stat-icon.red { background: #FEE2E2; color: #DC2626; }
.stat-card .stat-icon.orange { background: #FFEDD5; color: #EA580C; }
.stat-card .stat-icon.teal { background: #CCFBF1; color: #0D9488; }
.stat-card .stat-icon.indigo { background: #E0E7FF; color: #4F46E5; }
.stat-card .stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gray-800);
}
.stat-card .stat-label {
    font-size: 0.75rem;
    color: var(--gray-500);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.stat-card .stat-change {
    font-size: 0.7rem;
    margin-top: 6px;
    color: var(--gray-400);
}
.stat-card .stat-change.up { color: var(--secondary); }
.stat-card .stat-change.down { color: var(--danger); }

/* Charts Grid */
.charts-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 24px;
}
.chart-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
}
.chart-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.chart-card .card-header h3 {
    font-size: 0.9rem;
    font-weight: 600;
    margin: 0;
}
.chart-card .card-header .period {
    font-size: 0.7rem;
    color: var(--gray-400);
    background: var(--gray-100);
    padding: 2px 12px;
    border-radius: 20px;
}
.chart-container {
    height: 200px;
    position: relative;
}

/* Activities Grid */
.activities-grid {
    display: grid;
    grid-template-columns: 1.2fr 0.8fr;
    gap: 20px;
}
.activity-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
}
.activity-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.activity-card .card-header h3 {
    font-size: 0.9rem;
    font-weight: 600;
    margin: 0;
}
.activity-card .card-header a {
    font-size: 0.75rem;
    color: var(--primary);
    text-decoration: none;
}
.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--gray-100);
}
.activity-item:last-child {
    border-bottom: none;
}
.activity-item .activity-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    flex-shrink: 0;
}
.activity-item .activity-icon.login { background: #DBEAFE; color: #2563EB; }
.activity-item .activity-icon.system { background: #EDE9FE; color: #7C3AED; }
.activity-item .activity-icon.result { background: #D1FAE5; color: #059669; }
.activity-item .activity-icon.incident { background: #FEE2E2; color: #DC2626; }
.activity-item .activity-icon.user { background: #FEF3C7; color: #D97706; }
.activity-item .activity-content {
    flex: 1;
    min-width: 0;
}
.activity-item .activity-content .title {
    font-size: 0.82rem;
    font-weight: 500;
    color: var(--gray-700);
}
.activity-item .activity-content .desc {
    font-size: 0.78rem;
    color: var(--gray-500);
}
.activity-item .activity-content .time {
    font-size: 0.65rem;
    color: var(--gray-400);
    margin-top: 2px;
}
.text-truncate {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Quick Actions */
.quick-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}
.quick-action-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    background: var(--gray-50);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    text-decoration: none;
    color: var(--gray-700);
    font-size: 0.8rem;
    font-weight: 500;
    transition: var(--transition);
}
.quick-action-btn:hover {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}
.quick-action-btn i {
    font-size: 1rem;
    width: 20px;
    text-align: center;
}

/* Incident Summary */
.incident-summary {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 10px;
}
.incident-stat {
    text-align: center;
    padding: 10px;
    background: var(--gray-50);
    border-radius: 8px;
}
.incident-stat .label {
    display: block;
    font-size: 0.65rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.incident-stat .value {
    font-size: 1.3rem;
    font-weight: 700;
}

/* Status Badges */
.status-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
}
.status-badge.verified { background: #D1FAE5; color: #059669; }
.status-badge.pending { background: #FEF3C7; color: #D97706; }
.status-badge.flagged { background: #FEE2E2; color: #DC2626; }
.status-badge.rejected { background: #FEE2E2; color: #DC2626; }
.status-badge.active { background: #DBEAFE; color: #2563EB; }
.status-badge.completed { background: #D1FAE5; color: #059669; }

/* Responsive */
@media (max-width: 1024px) {
    .charts-grid {
        grid-template-columns: 1fr;
    }
    .activities-grid {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 640px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .quick-actions {
        grid-template-columns: 1fr;
    }
    .incident-summary {
        grid-template-columns: 1fr 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- ============================================================
        WELCOME SECTION
        ============================================================ -->
        <div class="welcome-section">
            <h2>Welcome, <?php echo htmlspecialchars($user_name); ?> 👋</h2>
            <p>Senatorial Coordinator - <?php echo htmlspecialchars($district_name); ?></p>
            <div class="breadcrumb">
                <i class="fas fa-flag"></i>
                <span><?php echo htmlspecialchars($state_name); ?></span>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span><?php echo htmlspecialchars($district_name); ?></span>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="color:var(--primary);font-weight:500;">Dashboard</span>
            </div>
        </div>
        
        <!-- ============================================================
        STATS CARDS
        ============================================================ -->
        <div class="stats-grid">
            <!-- Federal Constituencies -->
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-building"></i></div>
                <div class="stat-number"><?php echo number_format(count($federal_constituencies)); ?></div>
                <div class="stat-label">Federal Constituencies</div>
                <div class="stat-change"><i class="fas fa-layer-group"></i> <?php echo number_format($lga_stats['total_lgas'] ?? 0); ?> LGAs</div>
            </div>
            
            <!-- Polling Units -->
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-flag-checkered"></i></div>
                <div class="stat-number"><?php echo number_format($lga_stats['total_pus'] ?? 0); ?></div>
                <div class="stat-label">Polling Units</div>
                <div class="stat-change"><i class="fas fa-users"></i> <?php echo number_format($agent_stats['pu_agents'] ?? 0); ?> Agents</div>
            </div>
            
            <!-- Coordinators -->
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-user-tie"></i></div>
                <div class="stat-number"><?php echo number_format(($coordinator_stats['federal_constituency'] ?? 0) + ($coordinator_stats['lga'] ?? 0) + ($coordinator_stats['ward'] ?? 0)); ?></div>
                <div class="stat-label">Coordinators</div>
                <div class="stat-change">
                    <i class="fas fa-users"></i> 
                    <?php echo ($coordinator_stats['federal_constituency'] ?? 0); ?> FC · 
                    <?php echo ($coordinator_stats['lga'] ?? 0); ?> LGA · 
                    <?php echo ($coordinator_stats['ward'] ?? 0); ?> Ward
                </div>
            </div>
            
            <!-- Results -->
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-check-double"></i></div>
                <div class="stat-number"><?php echo number_format($result_stats['verified'] ?? 0); ?></div>
                <div class="stat-label">Verified Results</div>
                <div class="stat-change">
                    <span class="<?php echo ($result_stats['pending'] ?? 0) > 0 ? 'down' : ''; ?>">
                        <i class="fas fa-clock"></i> <?php echo $result_stats['pending'] ?? 0; ?> pending
                    </span>
                </div>
            </div>
            
            <!-- Incidents -->
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo number_format($incident_stats['total'] ?? 0); ?></div>
                <div class="stat-label">Incidents</div>
                <div class="stat-change down">
                    <i class="fas fa-clock"></i> <?php echo $incident_stats['reported'] ?? 0; ?> reported
                    <?php if (($incident_stats['critical'] ?? 0) > 0): ?>
                        <span style="color:var(--danger);font-weight:600;"> 🔴 <?php echo $incident_stats['critical']; ?> critical</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Reporting Status -->
            <div class="stat-card">
                <div class="stat-icon teal"><i class="fas fa-upload"></i></div>
                <div class="stat-number"><?php echo number_format(($pu_reporting_status['submitted'] ?? 0) / max(($pu_reporting_status['total'] ?? 1), 1) * 100); ?>%</div>
                <div class="stat-label">Reporting Rate</div>
                <div class="stat-change">
                    <i class="fas fa-check-circle"></i> 
                    <?php echo number_format($pu_reporting_status['submitted'] ?? 0); ?>/<?php echo number_format($pu_reporting_status['total'] ?? 0); ?> submitted
                </div>
            </div>
        </div>

        <!-- ============================================================
        CHARTS
        ============================================================ -->
        <div class="charts-grid">
            <!-- Result Progress Chart -->
            <div class="chart-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line" style="color:var(--primary);margin-right:6px;"></i> Result Verification Status</h3>
                    <span class="period"><?php echo htmlspecialchars($district_name); ?></span>
                </div>
                <div class="chart-container">
                    <canvas id="resultStatusChart"></canvas>
                </div>
            </div>

            <!-- LGA Performance Chart -->
            <div class="chart-card">
                <div class="card-header">
                    <h3><i class="fas fa-layer-group" style="color:var(--primary);margin-right:6px;"></i> LGA Performance</h3>
                    <span class="period">Verified Results</span>
                </div>
                <div class="chart-container">
                    <canvas id="lgaPerformanceChart"></canvas>
                </div>
            </div>
        </div>

        <!-- ============================================================
        ACTIVITIES & QUICK ACTIONS
        ============================================================ -->
        <div class="activities-grid">
            <!-- Recent Activities -->
            <div class="activity-card">
                <div class="card-header">
                    <h3><i class="fas fa-clock" style="color:var(--primary);margin-right:6px;"></i> Recent Activities</h3>
                    <a href="activity-logs.php">View All →</a>
                </div>
                <?php if (count($recent_activities) > 0): ?>
                    <?php foreach (array_slice($recent_activities, 0, 10) as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon <?php 
                                if (strpos($activity['activity_type'] ?? '', 'login') !== false) echo 'login';
                                elseif (strpos($activity['activity_type'] ?? '', 'result') !== false) echo 'result';
                                elseif (strpos($activity['activity_type'] ?? '', 'incident') !== false) echo 'incident';
                                elseif (strpos($activity['activity_type'] ?? '', 'user') !== false) echo 'user';
                                else echo 'system';
                            ?>">
                                <i class="fas 
                                    <?php 
                                    if (strpos($activity['activity_type'] ?? '', 'login') !== false) echo 'fa-sign-in-alt';
                                    elseif (strpos($activity['activity_type'] ?? '', 'result') !== false) echo 'fa-file-alt';
                                    elseif (strpos($activity['activity_type'] ?? '', 'incident') !== false) echo 'fa-exclamation-triangle';
                                    elseif (strpos($activity['activity_type'] ?? '', 'user') !== false) echo 'fa-user';
                                    else echo 'fa-cog';
                                    ?>
                                "></i>
                            </div>
                            <div class="activity-content">
                                <div class="title text-truncate"><?php echo htmlspecialchars($activity['user_name'] ?? 'System'); ?></div>
                                <div class="desc text-truncate"><?php echo htmlspecialchars($activity['description'] ?? ''); ?></div>
                                <div class="time"><?php echo date('M j, Y g:i A', strtotime($activity['created_at'] ?? 'now')); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color:var(--gray-500);padding:16px 0;text-align:center;">No recent activities</p>
                <?php endif; ?>
            </div>

            <!-- Quick Actions & Incident Summary -->
            <div>
                <!-- Quick Actions -->
                <div class="activity-card" style="margin-bottom:16px;">
                    <div class="card-header">
                        <h3><i class="fas fa-bolt" style="color:var(--primary);margin-right:6px;"></i> Quick Actions</h3>
                    </div>
                    <div class="quick-actions">
                        <a href="monitor-district.php" class="quick-action-btn">
                            <i class="fas fa-university"></i> Monitor District
                        </a>
                        <a href="broadcasts-create.php" class="quick-action-btn">
                            <i class="fas fa-bullhorn"></i> Broadcast
                        </a>
                        <a href="analytics-district.php" class="quick-action-btn">
                            <i class="fas fa-chart-pie"></i> Analytics
                        </a>
                        <a href="reports-district.php" class="quick-action-btn">
                            <i class="fas fa-file-alt"></i> Generate Report
                        </a>
                        <a href="view-results.php" class="quick-action-btn">
                            <i class="fas fa-chart-bar"></i> View Results
                        </a>
                        <a href="incidents.php" class="quick-action-btn">
                            <i class="fas fa-exclamation-triangle"></i> View Incidents
                        </a>
                    </div>
                </div>

                <!-- Incident Summary -->
                <div class="activity-card">
                    <div class="card-header">
                        <h3><i class="fas fa-exclamation-triangle" style="color:var(--danger);margin-right:6px;"></i> Incident Summary</h3>
                        <a href="incidents.php">View All →</a>
                    </div>
                    <div class="incident-summary">
                        <div class="incident-stat">
                            <span class="label">Reported</span>
                            <span class="value" style="color:var(--warning);"><?php echo $incident_stats['reported'] ?? 0; ?></span>
                        </div>
                        <div class="incident-stat">
                            <span class="label">Investigating</span>
                            <span class="value" style="color:var(--info);"><?php echo $incident_stats['investigating'] ?? 0; ?></span>
                        </div>
                        <div class="incident-stat">
                            <span class="label">Resolved</span>
                            <span class="value" style="color:var(--secondary);"><?php echo $incident_stats['resolved'] ?? 0; ?></span>
                        </div>
                    </div>
                    <?php if (($incident_stats['critical'] ?? 0) > 0 || ($incident_stats['high'] ?? 0) > 0): ?>
                        <div style="margin-top:12px;padding:10px 14px;background:#FEF2F2;border-radius:8px;border-left:3px solid #DC2626;">
                            <span style="font-size:0.8rem;color:#DC2626;font-weight:600;">
                                <i class="fas fa-exclamation-circle"></i> 
                                <?php echo $incident_stats['critical'] ?? 0; ?> critical · 
                                <?php echo $incident_stats['high'] ?? 0; ?> high priority incidents
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// ============================================================
// CHARTS - Senatorial Coordinator Dashboard
// ============================================================

// ============================================================
// RESULT STATUS CHART
// ============================================================
const ctx1 = document.getElementById('resultStatusChart').getContext('2d');
new Chart(ctx1, {
    type: 'doughnut',
    data: {
        labels: ['Verified', 'Pending', 'Flagged', 'Rejected'],
        datasets: [{
            data: [
                <?php echo $result_stats['verified'] ?? 0; ?>,
                <?php echo $result_stats['pending'] ?? 0; ?>,
                <?php echo $result_stats['flagged'] ?? 0; ?>,
                <?php echo $result_stats['rejected'] ?? 0; ?>
            ],
            backgroundColor: ['#10B981', '#F59E0B', '#EF4444', '#6B7280'],
            borderWidth: 2,
            borderColor: 'white'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    usePointStyle: true,
                    pointStyle: 'circle',
                    padding: 12,
                    font: { size: 11 }
                }
            }
        },
        cutout: '65%'
    }
});

// ============================================================
// LGA PERFORMANCE CHART
// ============================================================
const ctx2 = document.getElementById('lgaPerformanceChart').getContext('2d');
const lgaData = <?php 
    $lgas = array_column($lga_performance, 'lga_name');
    $rates = array_column($lga_performance, 'completion_rate');
    $counts = array_column($lga_performance, 'verified_count');
    echo json_encode(['labels' => $lgas, 'rates' => $rates, 'counts' => $counts]);
?>;

new Chart(ctx2, {
    type: 'bar',
    data: {
        labels: lgaData.labels.length > 0 ? lgaData.labels : ['No Data'],
        datasets: [{
            label: 'Completion Rate (%)',
            data: lgaData.rates.length > 0 ? lgaData.rates : [0],
            backgroundColor: 'rgba(124, 58, 237, 0.7)',
            borderColor: '#7C3AED',
            borderWidth: 1,
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.parsed.y + '%';
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                grid: { color: 'rgba(0,0,0,0.04)' },
                ticks: { 
                    font: { size: 10 },
                    callback: function(value) { return value + '%'; }
                }
            },
            x: {
                grid: { display: false },
                ticks: { 
                    font: { size: 10 },
                    maxRotation: 45
                }
            }
        }
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
</script>
</body>
</html>