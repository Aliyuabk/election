<?php
// ============================================================
// CLIENT ADMIN DASHBOARD
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

// Check role - only client_admin can access this dashboard
$role_level = SessionManager::get('role_level');
if ($role_level !== 'client_admin') {
    if ($role_level === 'super_admin') {
        header('Location: ../super-admin/');
    } else {
        header('Location: ../' . $role_level . '/');
    }
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
// FETCH TENANT DETAILS
// ============================================================
$tenant = null;
try {
    $stmt = $db->prepare("
        SELECT t.*, s.name as state_name, l.name as lga_name
        FROM tenants t
        LEFT JOIN states s ON t.state_id = s.id
        LEFT JOIN lgas l ON t.lga_id = l.id
        WHERE t.id = ? AND t.deleted_at IS NULL
    ");
    $stmt->execute([$tenant_id]);
    $tenant = $stmt->fetch();
} catch (Exception $e) {
    // Continue
}

// ============================================================
// FETCH USER STATISTICS
// ============================================================
$user_stats = [
    'total' => 0,
    'active' => 0,
    'suspended' => 0,
    'online' => 0,
    'today' => 0
];

try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE tenant_id = ? AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $user_stats['total'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE tenant_id = ? AND status = 'active' AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $user_stats['active'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE tenant_id = ? AND status = 'suspended' AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $user_stats['suspended'] = $stmt->fetch()['total'] ?? 0;

    // Online users (active sessions in last 5 minutes)
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT u.id) as total 
        FROM users u
        JOIN user_sessions us ON u.id = us.user_id
        WHERE u.tenant_id = ? AND us.is_active = 1 
        AND us.last_activity_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $stmt->execute([$tenant_id]);
    $user_stats['online'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM users 
        WHERE tenant_id = ? AND DATE(created_at) = CURDATE() AND deleted_at IS NULL
    ");
    $stmt->execute([$tenant_id]);
    $user_stats['today'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    // Continue
}

// ============================================================
// FETCH ELECTION STATISTICS
// ============================================================
$election_stats = [
    'total' => 0,
    'active' => 0,
    'upcoming' => 0,
    'completed' => 0,
    'draft' => 0
];

try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM elections WHERE tenant_id = ? AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $election_stats['total'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM elections WHERE tenant_id = ? AND status = 'active' AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $election_stats['active'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM elections WHERE tenant_id = ? AND status = 'upcoming' AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $election_stats['upcoming'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM elections WHERE tenant_id = ? AND status = 'completed' AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $election_stats['completed'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM elections WHERE tenant_id = ? AND status = 'draft' AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $election_stats['draft'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    // Continue
}

// ============================================================
// FETCH AGENT STATISTICS
// ============================================================
$agent_stats = [
    'coordinators' => 0,
    'pu_agents' => 0,
    'volunteers' => 0,
    'observers' => 0,
    'total' => 0
];

try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM agent_assignments 
        WHERE tenant_id = ? AND status = 'active'
    ");
    $stmt->execute([$tenant_id]);
    $agent_stats['total'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM agent_assignments 
        WHERE tenant_id = ? AND assignment_type = 'data_agent' AND status = 'active'
    ");
    $stmt->execute([$tenant_id]);
    $agent_stats['pu_agents'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM agent_assignments 
        WHERE tenant_id = ? AND assignment_type = 'volunteer' AND status = 'active'
    ");
    $stmt->execute([$tenant_id]);
    $agent_stats['volunteers'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM agent_assignments 
        WHERE tenant_id = ? AND assignment_type = 'observer' AND status = 'active'
    ");
    $stmt->execute([$tenant_id]);
    $agent_stats['observers'] = $stmt->fetch()['total'] ?? 0;

    // Coordinators are users with coordinator roles
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.tenant_id = ? AND r.level IN ('national', 'state', 'lga', 'ward') 
        AND u.status = 'active' AND u.deleted_at IS NULL
    ");
    $stmt->execute([$tenant_id]);
    $agent_stats['coordinators'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    // Continue
}

// ============================================================
// FETCH RESULT STATISTICS
// ============================================================
$result_stats = [
    'ec8a' => 0,
    'ec8b' => 0,
    'ec8c' => 0,
    'pending' => 0,
    'verified' => 0,
    'approved' => 0
];

try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM results_ec8a WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $result_stats['ec8a'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM results_ec8b WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $result_stats['ec8b'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM results_ec8c WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $result_stats['ec8c'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM results_ec8a WHERE tenant_id = ? AND status = 'pending'");
    $stmt->execute([$tenant_id]);
    $result_stats['pending'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM results_ec8a WHERE tenant_id = ? AND status = 'verified'");
    $stmt->execute([$tenant_id]);
    $result_stats['verified'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM results_ec8a WHERE tenant_id = ? AND status = 'approved'");
    $stmt->execute([$tenant_id]);
    $result_stats['approved'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    // Continue
}

// ============================================================
// FETCH INCIDENT STATISTICS
// ============================================================
$incident_stats = [
    'total' => 0,
    'open' => 0,
    'resolved' => 0,
    'high' => 0
];

try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM incidents WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $incident_stats['total'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM incidents WHERE tenant_id = ? AND status IN ('reported', 'acknowledged', 'investigating', 'escalated')");
    $stmt->execute([$tenant_id]);
    $incident_stats['open'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM incidents WHERE tenant_id = ? AND status IN ('resolved', 'false_alarm')");
    $stmt->execute([$tenant_id]);
    $incident_stats['resolved'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM incidents WHERE tenant_id = ? AND severity = 'high'");
    $stmt->execute([$tenant_id]);
    $incident_stats['high'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    // Continue
}

// ============================================================
// FETCH FINANCIAL STATISTICS
// ============================================================
$financial_stats = [
    'budget' => 0,
    'expenses' => 0,
    'agent_payments' => 0,
    'outstanding' => 0
];

try {
    $stmt = $db->prepare("SELECT SUM(total_amount) as total FROM budgets WHERE tenant_id = ? AND status = 'active'");
    $stmt->execute([$tenant_id]);
    $financial_stats['budget'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->prepare("SELECT SUM(amount) as total FROM expenses WHERE tenant_id = ? AND status = 'paid'");
    $stmt->execute([$tenant_id]);
    $financial_stats['expenses'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->prepare("SELECT SUM(amount) as total FROM agent_payments WHERE tenant_id = ? AND status = 'paid'");
    $stmt->execute([$tenant_id]);
    $financial_stats['agent_payments'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->prepare("SELECT SUM(amount) as total FROM agent_payments WHERE tenant_id = ? AND status = 'pending'");
    $stmt->execute([$tenant_id]);
    $financial_stats['outstanding'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    // Continue
}

// ============================================================
// FETCH POLITICAL STRUCTURE
// ============================================================
$structure_stats = [
    'states' => 0,
    'lgas' => 0,
    'wards' => 0,
    'polling_units' => 0
];

try {
    // Get states from tenant
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM states WHERE id IN (SELECT state_id FROM lgas WHERE id IN (SELECT lga_id FROM wards WHERE id IN (SELECT ward_id FROM polling_units WHERE tenant_id = ?)))");
    $stmt->execute([$tenant_id]);
    $structure_stats['states'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM lgas WHERE id IN (SELECT lga_id FROM wards WHERE id IN (SELECT ward_id FROM polling_units WHERE tenant_id = ?))");
    $stmt->execute([$tenant_id]);
    $structure_stats['lgas'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM wards WHERE id IN (SELECT ward_id FROM polling_units WHERE tenant_id = ?)");
    $stmt->execute([$tenant_id]);
    $structure_stats['wards'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM polling_units WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $structure_stats['polling_units'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    // Continue
}

// ============================================================
// FETCH RECENT ACTIVITIES
// ============================================================
$recent_activities = [];
try {
    $stmt = $db->prepare("
        SELECT a.*, u.full_name as user_name
        FROM activity_logs a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.tenant_id = ?
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$tenant_id]);
    $recent_activities = $stmt->fetchAll();
} catch (Exception $e) {
    // Continue
}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       CLIENT ADMIN DASHBOARD - PRO STYLES
       ============================================================ */
    
    /* Organization Header */
    .org-header {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 20px 24px;
        display: flex;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
        box-shadow: var(--shadow);
        margin-bottom: 24px;
        position: relative;
        overflow: hidden;
    }
    .org-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
    }
    .org-header .org-logo {
        width: 64px;
        height: 64px;
        border-radius: 14px;
        background: var(--gray-100);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--primary);
        overflow: hidden;
        flex-shrink: 0;
        border: 2px solid var(--gray-200);
    }
    .org-header .org-logo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .org-header .org-info {
        flex: 1;
    }
    .org-header .org-info h1 {
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 2px;
    }
    .org-header .org-info .org-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        font-size: 0.82rem;
        color: var(--gray-500);
    }
    .org-header .org-info .org-meta span {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .org-header .org-info .org-meta .badge-plan {
        display: inline-block;
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .badge-plan.free { background: var(--gray-100); color: var(--gray-500); }
    .badge-plan.basic { background: #FFFBEB; color: #92400E; }
    .badge-plan.standard { background: #ECFDF5; color: #065F46; }
    .badge-plan.premium { background: #EFF6FF; color: #1E40AF; }
    .badge-plan.enterprise { background: #F5F3FF; color: #5B21B6; }

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
    .badge-status.active { background: #ECFDF5; color: #065F46; }
    .badge-status.active .dot { background: #10B981; }
    .badge-status.suspended { background: #FEF2F2; color: #991B1B; }
    .badge-status.suspended .dot { background: #EF4444; }
    .badge-status.trial { background: #FFFBEB; color: #92400E; }
    .badge-status.trial .dot { background: #F59E0B; }
    .badge-status.expired { background: #FEF2F2; color: #991B1B; }
    .badge-status.expired .dot { background: #EF4444; }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    .stat-card {
        background: white;
        border-radius: var(--radius);
        padding: 18px 20px;
        border: 1px solid var(--gray-200);
        box-shadow: var(--shadow);
        transition: var(--transition);
        cursor: pointer;
    }
    .stat-card:hover {
        box-shadow: var(--shadow-hover);
        transform: translateY(-2px);
    }
    .stat-card .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        margin-bottom: 10px;
    }
    .stat-card .stat-icon.blue { background: #EFF6FF; color: #3B82F6; }
    .stat-card .stat-icon.green { background: #ECFDF5; color: #10B981; }
    .stat-card .stat-icon.yellow { background: #FFFBEB; color: #F59E0B; }
    .stat-card .stat-icon.red { background: #FEF2F2; color: #EF4444; }
    .stat-card .stat-icon.purple { background: #F5F3FF; color: #8B5CF6; }
    .stat-card .stat-icon.orange { background: #FFF7ED; color: #EA580C; }
    .stat-card .stat-icon.teal { background: #ECFDF5; color: #14B8A6; }
    .stat-card .stat-number {
        font-size: 1.6rem;
        font-weight: 700;
        line-height: 1.2;
    }
    .stat-card .stat-label {
        color: var(--gray-500);
        font-size: 0.8rem;
        font-weight: 500;
    }
    .stat-card .stat-change {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.7rem;
        font-weight: 600;
        margin-top: 4px;
        padding: 2px 10px;
        border-radius: 20px;
    }
    .stat-card .stat-change.up { background: #ECFDF5; color: #065F46; }
    .stat-card .stat-change.down { background: #FEF2F2; color: #991B1B; }

    /* Widget Grid */
    .widgets-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
        margin-bottom: 24px;
    }
    .widget {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 20px 24px;
        box-shadow: var(--shadow);
    }
    .widget .widget-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }
    .widget .widget-header h3 {
        font-size: 0.95rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .widget .widget-header a {
        color: var(--primary);
        text-decoration: none;
        font-size: 0.8rem;
        font-weight: 500;
    }
    .widget .widget-header a:hover {
        text-decoration: underline;
    }

    /* Activity List */
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
        width: 34px;
        height: 34px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        flex-shrink: 0;
    }
    .activity-item .activity-icon.login { background: #EFF6FF; color: #3B82F6; }
    .activity-item .activity-icon.tenant { background: #ECFDF5; color: #10B981; }
    .activity-item .activity-icon.user { background: #F5F3FF; color: #8B5CF6; }
    .activity-item .activity-icon.election { background: #FFFBEB; color: #F59E0B; }
    .activity-item .activity-icon.broadcast { background: #EFF6FF; color: #3B82F6; }
    .activity-item .activity-icon.security { background: #FEF2F2; color: #EF4444; }
    .activity-item .activity-content {
        flex: 1;
        min-width: 0;
    }
    .activity-item .activity-content .title {
        font-weight: 500;
        font-size: 0.85rem;
    }
    .activity-item .activity-content .desc {
        color: var(--gray-500);
        font-size: 0.78rem;
    }
    .activity-item .activity-content .time {
        color: var(--gray-400);
        font-size: 0.68rem;
        margin-top: 2px;
    }

    /* Quick Actions */
    .quick-actions {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }
    .quick-action-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 14px 10px;
        background: var(--gray-50);
        border-radius: 10px;
        border: 1px solid var(--gray-200);
        text-decoration: none;
        color: var(--gray-700);
        font-size: 0.72rem;
        font-weight: 500;
        transition: var(--transition);
        text-align: center;
    }
    .quick-action-btn i {
        font-size: 1.2rem;
        color: var(--primary);
    }
    .quick-action-btn:hover {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(var(--primary-rgb), 0.15);
    }
    .quick-action-btn:hover i {
        color: white;
    }

    /* Mini Stats */
    .mini-stats {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    .mini-stat {
        background: var(--gray-50);
        border-radius: 10px;
        padding: 12px 16px;
        text-align: center;
    }
    .mini-stat .number {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--primary);
    }
    .mini-stat .label {
        font-size: 0.65rem;
        color: var(--gray-500);
    }

    .progress-bar {
        width: 100%;
        height: 6px;
        background: var(--gray-200);
        border-radius: 4px;
        overflow: hidden;
        margin-top: 6px;
    }
    .progress-bar .progress-fill {
        height: 100%;
        border-radius: 4px;
        transition: width 0.6s ease;
    }
    .progress-bar .progress-fill.blue { background: #3B82F6; }
    .progress-bar .progress-fill.green { background: #10B981; }
    .progress-bar .progress-fill.yellow { background: #F59E0B; }
    .progress-bar .progress-fill.red { background: #EF4444; }
    .progress-bar .progress-fill.purple { background: #8B5CF6; }

    /* Responsive */
    @media (max-width: 1200px) {
        .widgets-grid {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 992px) {
        .stats-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .org-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .org-header .org-logo {
            width: 56px;
            height: 56px;
            font-size: 1.2rem;
        }
        .org-header .org-info h1 {
            font-size: 1.1rem;
        }
        .quick-actions {
            grid-template-columns: 1fr 1fr;
        }
        .mini-stats {
            grid-template-columns: 1fr 1fr;
        }
        .widget {
            padding: 16px;
        }
    }
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .stat-card {
            padding: 12px 14px;
        }
        .stat-card .stat-number {
            font-size: 1.2rem;
        }
        .stat-card .stat-icon {
            width: 36px;
            height: 36px;
            font-size: 1rem;
        }
        .quick-actions {
            grid-template-columns: 1fr 1fr;
        }
        .quick-action-btn {
            padding: 10px 8px;
            font-size: 0.65rem;
        }
        .quick-action-btn i {
            font-size: 1rem;
        }
        .widget .widget-header h3 {
            font-size: 0.85rem;
        }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Organization Header -->
        <div class="org-header">
            <div class="org-logo">
                <?php if (!empty($tenant['logo_url'])): ?>
                    <img src="<?php echo htmlspecialchars($tenant['logo_url']); ?>" alt="<?php echo htmlspecialchars($tenant['name']); ?>">
                <?php else: ?>
                    <?php echo strtoupper(substr($tenant['name'] ?? 'O', 0, 2)); ?>
                <?php endif; ?>
            </div>
            <div class="org-info">
                <h1><?php echo htmlspecialchars($tenant['name'] ?? 'Organization'); ?></h1>
                <div class="org-meta">
                    <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($tenant['slug'] ?? 'N/A'); ?></span>
                    <span>
                        <i class="fas fa-credit-card"></i>
                        <span class="badge-plan <?php echo $tenant['subscription_plan'] ?? 'free'; ?>">
                            <?php echo ucfirst($tenant['subscription_plan'] ?? 'Free'); ?>
                        </span>
                    </span>
                    <span>
                        <span class="badge-status <?php echo $tenant['subscription_status'] ?? 'trial'; ?>">
                            <span class="dot"></span>
                            <?php echo ucfirst($tenant['subscription_status'] ?? 'Trial'); ?>
                        </span>
                    </span>
                    <?php if (!empty($tenant['subscription_end'])): ?>
                        <span><i class="fas fa-calendar-alt"></i> Expires: <?php echo date('M j, Y', strtotime($tenant['subscription_end'])); ?></span>
                    <?php endif; ?>
                    <span><i class="fas fa-users"></i> <?php echo number_format($user_stats['total']); ?> Users</span>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo number_format($user_stats['total']); ?></div>
                <div class="stat-label">Total Users</div>
                <div class="stat-change up"><i class="fas fa-arrow-up"></i> <?php echo $user_stats['active']; ?> active</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-vote-yea"></i></div>
                <div class="stat-number"><?php echo number_format($election_stats['total']); ?></div>
                <div class="stat-label">Total Elections</div>
                <div class="stat-change up"><i class="fas fa-play"></i> <?php echo $election_stats['active']; ?> active</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-user-tie"></i></div>
                <div class="stat-number"><?php echo number_format($agent_stats['total']); ?></div>
                <div class="stat-label">Total Agents</div>
                <div class="stat-change up"><i class="fas fa-user-plus"></i> <?php echo $agent_stats['pu_agents']; ?> PU agents</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-chart-bar"></i></div>
                <div class="stat-number"><?php echo number_format($result_stats['ec8a'] + $result_stats['ec8b'] + $result_stats['ec8c']); ?></div>
                <div class="stat-label">Total Results</div>
                <div class="stat-change up"><i class="fas fa-check-circle"></i> <?php echo $result_stats['approved']; ?> approved</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo number_format($incident_stats['total']); ?></div>
                <div class="stat-label">Total Incidents</div>
                <div class="stat-change down"><i class="fas fa-exclamation-circle"></i> <?php echo $incident_stats['open']; ?> open</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon teal"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-number">₦<?php echo number_format($financial_stats['budget'], 2); ?></div>
                <div class="stat-label">Total Budget</div>
                <div class="stat-change up"><i class="fas fa-arrow-up"></i> ₦<?php echo number_format($financial_stats['expenses'], 2); ?> spent</div>
            </div>
        </div>

        <!-- Widgets Grid -->
        <div class="widgets-grid">
            <!-- Left Column -->
            <div>
                <!-- Election Progress Widget -->
                <div class="widget" style="margin-bottom:20px;">
                    <div class="widget-header">
                        <h3><i class="fas fa-chart-pie" style="color:var(--primary);"></i> Election Progress</h3>
                        <a href="elections.php">View All →</a>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        <div>
                            <div style="display:flex;justify-content:space-between;font-size:0.82rem;">
                                <span>Active Elections</span>
                                <span><strong><?php echo $election_stats['active']; ?></strong> / <?php echo $election_stats['total']; ?></span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill green" style="width:<?php echo $election_stats['total'] > 0 ? ($election_stats['active'] / $election_stats['total'] * 100) : 0; ?>%;"></div>
                            </div>
                        </div>
                        <div>
                            <div style="display:flex;justify-content:space-between;font-size:0.82rem;">
                                <span>Completed Elections</span>
                                <span><strong><?php echo $election_stats['completed']; ?></strong> / <?php echo $election_stats['total']; ?></span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill blue" style="width:<?php echo $election_stats['total'] > 0 ? ($election_stats['completed'] / $election_stats['total'] * 100) : 0; ?>%;"></div>
                            </div>
                        </div>
                        <div>
                            <div style="display:flex;justify-content:space-between;font-size:0.82rem;">
                                <span>Upcoming Elections</span>
                                <span><strong><?php echo $election_stats['upcoming']; ?></strong> / <?php echo $election_stats['total']; ?></span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill yellow" style="width:<?php echo $election_stats['total'] > 0 ? ($election_stats['upcoming'] / $election_stats['total'] * 100) : 0; ?>%;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="widget">
                    <div class="widget-header">
                        <h3><i class="fas fa-clock" style="color:var(--primary);"></i> Recent Activities</h3>
                        <a href="audit-logs.php">View All →</a>
                    </div>
                    <?php if (count($recent_activities) > 0): ?>
                        <?php foreach (array_slice($recent_activities, 0, 8) as $activity): ?>
                            <?php 
                                $iconClass = 'system';
                                $icon = 'fa-cog';
                                $type = $activity['activity_type'] ?? '';
                                if (strpos($type, 'login') !== false) {
                                    $iconClass = 'login';
                                    $icon = 'fa-sign-in-alt';
                                } elseif (strpos($type, 'tenant') !== false) {
                                    $iconClass = 'tenant';
                                    $icon = 'fa-building';
                                } elseif (strpos($type, 'user') !== false || strpos($type, 'agent') !== false) {
                                    $iconClass = 'user';
                                    $icon = 'fa-user';
                                } elseif (strpos($type, 'election') !== false) {
                                    $iconClass = 'election';
                                    $icon = 'fa-vote-yea';
                                } elseif (strpos($type, 'broadcast') !== false) {
                                    $iconClass = 'broadcast';
                                    $icon = 'fa-bullhorn';
                                } elseif (strpos($type, 'security') !== false || strpos($type, 'password') !== false) {
                                    $iconClass = 'security';
                                    $icon = 'fa-shield-alt';
                                }
                            ?>
                            <div class="activity-item">
                                <div class="activity-icon <?php echo $iconClass; ?>">
                                    <i class="fas <?php echo $icon; ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="title"><?php echo htmlspecialchars($activity['user_name'] ?? 'System'); ?></div>
                                    <div class="desc"><?php echo htmlspecialchars($activity['description'] ?? 'Activity'); ?></div>
                                    <div class="time"><?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color:var(--gray-500);padding:20px 0;text-align:center;">No recent activities found.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Quick Actions -->
                <div class="widget" style="margin-bottom:20px;">
                    <div class="widget-header">
                        <h3><i class="fas fa-bolt" style="color:var(--primary);"></i> Quick Actions</h3>
                    </div>
                    <div class="quick-actions">
                        <a href="users-create.php" class="quick-action-btn">
                            <i class="fas fa-user-plus"></i>
                            Add User
                        </a>
                        <a href="elections-create.php" class="quick-action-btn">
                            <i class="fas fa-plus-circle"></i>
                            Create Election
                        </a>
                        <a href="agents-assign.php" class="quick-action-btn">
                            <i class="fas fa-user-check"></i>
                            Assign Agent
                        </a>
                        <a href="broadcast.php" class="quick-action-btn">
                            <i class="fas fa-bullhorn"></i>
                            Broadcast Message
                        </a>
                        <a href="reports.php" class="quick-action-btn">
                            <i class="fas fa-file-alt"></i>
                            Generate Report
                        </a>
                        <a href="profile.php" class="quick-action-btn">
                            <i class="fas fa-cog"></i>
                            Manage Profile
                        </a>
                    </div>
                </div>

                <!-- Agent Summary -->
                <div class="widget" style="margin-bottom:20px;">
                    <div class="widget-header">
                        <h3><i class="fas fa-user-tie" style="color:var(--primary);"></i> Agent Summary</h3>
                        <a href="agents.php">View All →</a>
                    </div>
                    <div class="mini-stats">
                        <div class="mini-stat">
                            <div class="number"><?php echo number_format($agent_stats['coordinators']); ?></div>
                            <div class="label">Coordinators</div>
                        </div>
                        <div class="mini-stat">
                            <div class="number"><?php echo number_format($agent_stats['pu_agents']); ?></div>
                            <div class="label">PU Agents</div>
                        </div>
                        <div class="mini-stat">
                            <div class="number"><?php echo number_format($agent_stats['volunteers']); ?></div>
                            <div class="label">Volunteers</div>
                        </div>
                        <div class="mini-stat">
                            <div class="number"><?php echo number_format($agent_stats['observers']); ?></div>
                            <div class="label">Observers</div>
                        </div>
                    </div>
                </div>

                <!-- Structure Summary -->
                <div class="widget">
                    <div class="widget-header">
                        <h3><i class="fas fa-sitemap" style="color:var(--primary);"></i> Political Structure</h3>
                        <a href="structure.php">View All →</a>
                    </div>
                    <div class="mini-stats">
                        <div class="mini-stat">
                            <div class="number"><?php echo number_format($structure_stats['states']); ?></div>
                            <div class="label">States</div>
                        </div>
                        <div class="mini-stat">
                            <div class="number"><?php echo number_format($structure_stats['lgas']); ?></div>
                            <div class="label">LGAs</div>
                        </div>
                        <div class="mini-stat">
                            <div class="number"><?php echo number_format($structure_stats['wards']); ?></div>
                            <div class="label">Wards</div>
                        </div>
                        <div class="mini-stat">
                            <div class="number"><?php echo number_format($structure_stats['polling_units']); ?></div>
                            <div class="label">Polling Units</div>
                        </div>
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