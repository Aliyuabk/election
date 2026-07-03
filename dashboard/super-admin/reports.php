<?php
// ============================================================
// REPORTS & ANALYTICS - SUPER ADMINISTRATOR
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
// FETCH STATISTICS FOR REPORTS
// ============================================================
$stats = [
    'tenants' => ['total' => 0, 'active' => 0, 'suspended' => 0],
    'users' => ['total' => 0, 'active' => 0, 'suspended' => 0, 'new_today' => 0],
    'elections' => ['total' => 0, 'active' => 0, 'completed' => 0, 'upcoming' => 0],
    'revenue' => ['total' => 0, 'this_month' => 0, 'this_year' => 0],
    'subscriptions' => ['total' => 0, 'active' => 0, 'expired' => 0],
    'tickets' => ['total' => 0, 'open' => 0, 'resolved' => 0],
    'security' => ['total' => 0, 'critical' => 0, 'warning' => 0],
    'audit' => ['total' => 0, 'today' => 0, 'this_week' => 0]
];

try {
    // Tenants
    $stmt = $db->query("SELECT COUNT(*) as total FROM tenants WHERE deleted_at IS NULL");
    $stats['tenants']['total'] = $stmt->fetch()['total'] ?? 0;
    $stmt = $db->query("SELECT COUNT(*) as total FROM tenants WHERE is_active = 1 AND deleted_at IS NULL");
    $stats['tenants']['active'] = $stmt->fetch()['total'] ?? 0;
    $stmt = $db->query("SELECT COUNT(*) as total FROM tenants WHERE is_active = 0 AND deleted_at IS NULL");
    $stats['tenants']['suspended'] = $stmt->fetch()['total'] ?? 0;
    
    // Users
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE deleted_at IS NULL");
    $stats['users']['total'] = $stmt->fetch()['total'] ?? 0;
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE status = 'active' AND deleted_at IS NULL");
    $stats['users']['active'] = $stmt->fetch()['total'] ?? 0;
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE status = 'suspended' AND deleted_at IS NULL");
    $stats['users']['suspended'] = $stmt->fetch()['total'] ?? 0;
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE DATE(created_at) = CURDATE() AND deleted_at IS NULL");
    $stats['users']['new_today'] = $stmt->fetch()['total'] ?? 0;
    
    // Elections
    $stmt = $db->query("SELECT COUNT(*) as total FROM elections WHERE deleted_at IS NULL");
    $stats['elections']['total'] = $stmt->fetch()['total'] ?? 0;
    $stmt = $db->query("SELECT COUNT(*) as total FROM elections WHERE status = 'active' AND deleted_at IS NULL");
    $stats['elections']['active'] = $stmt->fetch()['total'] ?? 0;
    $stmt = $db->query("SELECT COUNT(*) as total FROM elections WHERE status = 'completed' AND deleted_at IS NULL");
    $stats['elections']['completed'] = $stmt->fetch()['total'] ?? 0;
    $stmt = $db->query("SELECT COUNT(*) as total FROM elections WHERE status = 'upcoming' AND deleted_at IS NULL");
    $stats['elections']['upcoming'] = $stmt->fetch()['total'] ?? 0;
    
    // Revenue from invoices
    $stmt = $db->query("SELECT SUM(total_amount) as total FROM invoices WHERE status = 'paid'");
    $stats['revenue']['total'] = $stmt->fetch()['total'] ?? 0;
    $stmt = $db->query("SELECT SUM(total_amount) as total FROM invoices WHERE status = 'paid' AND MONTH(paid_at) = MONTH(NOW()) AND YEAR(paid_at) = YEAR(NOW())");
    $stats['revenue']['this_month'] = $stmt->fetch()['total'] ?? 0;
    $stmt = $db->query("SELECT SUM(total_amount) as total FROM invoices WHERE status = 'paid' AND YEAR(paid_at) = YEAR(NOW())");
    $stats['revenue']['this_year'] = $stmt->fetch()['total'] ?? 0;
    
    // Subscriptions
    $stmt = $db->query("SELECT COUNT(*) as total FROM subscriptions");
    $stats['subscriptions']['total'] = $stmt->fetch()['total'] ?? 0;
    $stmt = $db->query("SELECT COUNT(*) as total FROM subscriptions WHERE status = 'active'");
    $stats['subscriptions']['active'] = $stmt->fetch()['total'] ?? 0;
    $stmt = $db->query("SELECT COUNT(*) as total FROM subscriptions WHERE status = 'expired'");
    $stats['subscriptions']['expired'] = $stmt->fetch()['total'] ?? 0;
    
    // Tickets
    $stmt = $db->query("SELECT COUNT(*) as total FROM support_tickets");
    $stats['tickets']['total'] = $stmt->fetch()['total'] ?? 0;
    $stmt = $db->query("SELECT COUNT(*) as total FROM support_tickets WHERE status IN ('open', 'in_progress', 'waiting', 'escalated')");
    $stats['tickets']['open'] = $stmt->fetch()['total'] ?? 0;
    $stmt = $db->query("SELECT COUNT(*) as total FROM support_tickets WHERE status IN ('resolved', 'closed')");
    $stats['tickets']['resolved'] = $stmt->fetch()['total'] ?? 0;
    
    // Security Events
    $stmt = $db->query("SELECT COUNT(*) as total FROM security_events");
    $stats['security']['total'] = $stmt->fetch()['total'] ?? 0;
    $stmt = $db->query("SELECT COUNT(*) as total FROM security_events WHERE risk_score >= 8");
    $stats['security']['critical'] = $stmt->fetch()['total'] ?? 0;
    $stmt = $db->query("SELECT COUNT(*) as total FROM security_events WHERE risk_score BETWEEN 5 AND 7");
    $stats['security']['warning'] = $stmt->fetch()['total'] ?? 0;
    
    // Audit Logs
    $stmt = $db->query("SELECT COUNT(*) as total FROM activity_logs");
    $stats['audit']['total'] = $stmt->fetch()['total'] ?? 0;
    $stmt = $db->query("SELECT COUNT(*) as total FROM activity_logs WHERE DATE(created_at) = CURDATE()");
    $stats['audit']['today'] = $stmt->fetch()['total'] ?? 0;
    $stmt = $db->query("SELECT COUNT(*) as total FROM activity_logs WHERE YEARWEEK(created_at) = YEARWEEK(NOW())");
    $stats['audit']['this_week'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    // Continue
}

// ============================================================
// FETCH MONTHLY REVENUE FOR CHART
// ============================================================
$monthly_revenue = [];
try {
    $stmt = $db->query("
        SELECT 
            DATE_FORMAT(paid_at, '%b') as month,
            SUM(total_amount) as revenue
        FROM invoices
        WHERE status = 'paid' 
        AND paid_at > DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(paid_at, '%Y-%m')
        ORDER BY paid_at ASC
    ");
    $monthly_revenue = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH USER GROWTH FOR CHART
// ============================================================
$user_growth = [];
try {
    $stmt = $db->query("
        SELECT 
            DATE_FORMAT(created_at, '%b') as month,
            COUNT(*) as count
        FROM users
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 12 MONTH)
        AND deleted_at IS NULL
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY created_at ASC
    ");
    $user_growth = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH ELECTION STATS FOR CHART
// ============================================================
$election_stats = [];
try {
    $stmt = $db->query("
        SELECT 
            status,
            COUNT(*) as count
        FROM elections
        WHERE deleted_at IS NULL
        GROUP BY status
    ");
    $election_stats = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH TENANT STATS FOR CHART
// ============================================================
$tenant_stats = [];
try {
    $stmt = $db->query("
        SELECT 
            subscription_plan as plan,
            COUNT(*) as count
        FROM tenants
        WHERE deleted_at IS NULL
        GROUP BY subscription_plan
    ");
    $tenant_stats = $stmt->fetchAll();
} catch (Exception $e) {}

// Get user info
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       REPORTS & ANALYTICS - PRO STYLES
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
    .btn-sm.purple { background: #F5F3FF; color: #5B21B6; }
    .btn-sm.purple:hover { background: #EDE9FE; }
    .btn-sm.green { background: #ECFDF5; color: #065F46; }
    .btn-sm.green:hover { background: #D1FAE5; }
    .btn-sm.blue { background: #EFF6FF; color: #1E40AF; }
    .btn-sm.blue:hover { background: #DBEAFE; }
    .btn-sm.orange { background: #FFFBEB; color: #92400E; }
    .btn-sm.orange:hover { background: #FEF3C7; }
    
    .report-tabs {
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
    .report-tab {
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
    .report-tab:hover {
        background: var(--gray-50);
        border-color: var(--gray-200);
    }
    .report-tab.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }
    .report-tab i {
        font-size: 0.9rem;
    }
    
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    .summary-card {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 20px 24px;
        box-shadow: var(--shadow);
        transition: var(--transition);
    }
    .summary-card:hover {
        box-shadow: var(--shadow-hover);
        transform: translateY(-2px);
    }
    .summary-card .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }
    .summary-card .card-header .title {
        font-weight: 600;
        font-size: 0.85rem;
        color: var(--gray-600);
    }
    .summary-card .card-header .icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
    }
    .summary-card .card-header .icon.blue { background: #EFF6FF; color: #3B82F6; }
    .summary-card .card-header .icon.green { background: #ECFDF5; color: #10B981; }
    .summary-card .card-header .icon.purple { background: #F5F3FF; color: #8B5CF6; }
    .summary-card .card-header .icon.orange { background: #FFFBEB; color: #F59E0B; }
    .summary-card .card-header .icon.red { background: #FEF2F2; color: #EF4444; }
    .summary-card .card-header .icon.teal { background: #ECFDF5; color: #14B8A6; }
    
    .summary-card .stat-number {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--gray-900);
    }
    .summary-card .stat-number small {
        font-size: 0.85rem;
        font-weight: 400;
        color: var(--gray-500);
    }
    .summary-card .stat-detail {
        display: flex;
        gap: 16px;
        margin-top: 8px;
        flex-wrap: wrap;
        font-size: 0.78rem;
        color: var(--gray-500);
    }
    .summary-card .stat-detail span {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .summary-card .stat-detail .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
    }
    .summary-card .stat-detail .dot.green { background: #10B981; }
    .summary-card .stat-detail .dot.red { background: #EF4444; }
    .summary-card .stat-detail .dot.yellow { background: #F59E0B; }
    .summary-card .stat-detail .dot.blue { background: #3B82F6; }
    
    .charts-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
        margin-bottom: 24px;
    }
    .chart-card {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 20px 24px;
        box-shadow: var(--shadow);
    }
    .chart-card .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }
    .chart-card .chart-header h3 {
        font-size: 1rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .chart-card .chart-header .period {
        font-size: 0.75rem;
        color: var(--gray-500);
        background: var(--gray-100);
        padding: 4px 12px;
        border-radius: 20px;
    }
    .chart-container {
        position: relative;
        height: 280px;
    }
    
    .stats-table-container {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        overflow: hidden;
        box-shadow: var(--shadow);
    }
    .stats-table-container .table-header {
        padding: 16px 20px;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        background: var(--gray-50);
    }
    .stats-table-container .table-header .table-title {
        font-weight: 600;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .stats-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    .stats-table thead {
        background: var(--gray-50);
    }
    .stats-table thead th {
        padding: 10px 14px;
        text-align: left;
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--gray-500);
        border-bottom: 1px solid var(--gray-200);
    }
    .stats-table tbody td {
        padding: 8px 14px;
        border-bottom: 1px solid var(--gray-100);
    }
    .stats-table tbody tr:last-child td {
        border-bottom: none;
    }
    .stats-table tbody tr:hover {
        background: var(--gray-50);
    }
    
    .export-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .export-btn {
        padding: 6px 14px;
        border-radius: 6px;
        border: none;
        font-family: 'Inter', sans-serif;
        font-size: 0.72rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .export-btn.pdf { background: #FEF2F2; color: #991B1B; }
    .export-btn.pdf:hover { background: #FEE2E2; }
    .export-btn.excel { background: #ECFDF5; color: #065F46; }
    .export-btn.excel:hover { background: #D1FAE5; }
    .export-btn.csv { background: #EFF6FF; color: #1E40AF; }
    .export-btn.csv:hover { background: #DBEAFE; }
    
    @media (max-width: 992px) {
        .charts-grid {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 768px) {
        .summary-grid {
            grid-template-columns: 1fr;
        }
        .report-tabs {
            flex-wrap: nowrap;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .report-tab {
            white-space: nowrap;
            font-size: 0.75rem;
            padding: 6px 12px;
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .stats-table-container {
            overflow-x: auto;
        }
        .stats-table {
            font-size: 0.78rem;
        }
        .stats-table th,
        .stats-table td {
            padding: 6px 10px;
        }
        .chart-container {
            height: 200px;
        }
    }
    @media (max-width: 480px) {
        .summary-card .stat-number {
            font-size: 1.4rem;
        }
        .chart-card {
            padding: 16px;
        }
        .export-buttons {
            width: 100%;
        }
        .export-btn {
            flex: 1;
            justify-content: center;
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
                    <i class="fas fa-chart-bar" style="color:var(--primary);margin-right:8px;"></i> Reports & Analytics
                    <small>Comprehensive reports and analytics across the platform</small>
                </h2>
            </div>
            <div class="export-buttons">
                <button class="export-btn pdf" onclick="exportReport('pdf')">
                    <i class="fas fa-file-pdf"></i> PDF
                </button>
                <button class="export-btn excel" onclick="exportReport('excel')">
                    <i class="fas fa-file-excel"></i> Excel
                </button>
                <button class="export-btn csv" onclick="exportReport('csv')">
                    <i class="fas fa-file-csv"></i> CSV
                </button>
            </div>
        </div>

        <!-- Report Tabs -->
        <div class="report-tabs">
            <button class="report-tab active" data-tab="overview" onclick="switchTab('overview')">
                <i class="fas fa-th-large"></i> Overview
            </button>
            <button class="report-tab" data-tab="tenants" onclick="switchTab('tenants')">
                <i class="fas fa-building"></i> Tenants
            </button>
            <button class="report-tab" data-tab="users" onclick="switchTab('users')">
                <i class="fas fa-users"></i> Users
            </button>
            <button class="report-tab" data-tab="revenue" onclick="switchTab('revenue')">
                <i class="fas fa-money-bill-wave"></i> Revenue
            </button>
            <button class="report-tab" data-tab="subscriptions" onclick="switchTab('subscriptions')">
                <i class="fas fa-credit-card"></i> Subscriptions
            </button>
            <button class="report-tab" data-tab="elections" onclick="switchTab('elections')">
                <i class="fas fa-vote-yea"></i> Elections
            </button>
            <button class="report-tab" data-tab="security" onclick="switchTab('security')">
                <i class="fas fa-shield-alt"></i> Security
            </button>
            <button class="report-tab" data-tab="audit" onclick="switchTab('audit')">
                <i class="fas fa-clipboard-list"></i> Audit
            </button>
        </div>

        <!-- Overview Tab -->
        <div id="tab-overview" class="tab-content">
            <!-- Summary Cards -->
            <div class="summary-grid">
                <!-- Tenants -->
                <div class="summary-card">
                    <div class="card-header">
                        <span class="title"><i class="fas fa-building" style="color:var(--primary);margin-right:4px;"></i> Tenants</span>
                        <div class="icon blue"><i class="fas fa-building"></i></div>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['tenants']['total']); ?></div>
                    <div class="stat-detail">
                        <span><span class="dot green"></span> Active: <?php echo number_format($stats['tenants']['active']); ?></span>
                        <span><span class="dot red"></span> Suspended: <?php echo number_format($stats['tenants']['suspended']); ?></span>
                    </div>
                </div>

                <!-- Users -->
                <div class="summary-card">
                    <div class="card-header">
                        <span class="title"><i class="fas fa-users" style="color:var(--secondary);margin-right:4px;"></i> Users</span>
                        <div class="icon green"><i class="fas fa-users"></i></div>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['users']['total']); ?></div>
                    <div class="stat-detail">
                        <span><span class="dot green"></span> Active: <?php echo number_format($stats['users']['active']); ?></span>
                        <span><span class="dot blue"></span> New Today: <?php echo number_format($stats['users']['new_today']); ?></span>
                        <span><span class="dot red"></span> Suspended: <?php echo number_format($stats['users']['suspended']); ?></span>
                    </div>
                </div>

                <!-- Revenue -->
                <div class="summary-card">
                    <div class="card-header">
                        <span class="title"><i class="fas fa-money-bill-wave" style="color:#F59E0B;margin-right:4px;"></i> Revenue</span>
                        <div class="icon orange"><i class="fas fa-money-bill-wave"></i></div>
                    </div>
                    <div class="stat-number">₦<?php echo number_format($stats['revenue']['total'], 2); ?></div>
                    <div class="stat-detail">
                        <span>This Month: ₦<?php echo number_format($stats['revenue']['this_month'], 2); ?></span>
                        <span>This Year: ₦<?php echo number_format($stats['revenue']['this_year'], 2); ?></span>
                    </div>
                </div>

                <!-- Elections -->
                <div class="summary-card">
                    <div class="card-header">
                        <span class="title"><i class="fas fa-vote-yea" style="color:#8B5CF6;margin-right:4px;"></i> Elections</span>
                        <div class="icon purple"><i class="fas fa-vote-yea"></i></div>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['elections']['total']); ?></div>
                    <div class="stat-detail">
                        <span><span class="dot green"></span> Active: <?php echo number_format($stats['elections']['active']); ?></span>
                        <span><span class="dot yellow"></span> Upcoming: <?php echo number_format($stats['elections']['upcoming']); ?></span>
                        <span><span class="dot blue"></span> Completed: <?php echo number_format($stats['elections']['completed']); ?></span>
                    </div>
                </div>

                <!-- Subscriptions -->
                <div class="summary-card">
                    <div class="card-header">
                        <span class="title"><i class="fas fa-credit-card" style="color:#14B8A6;margin-right:4px;"></i> Subscriptions</span>
                        <div class="icon teal"><i class="fas fa-credit-card"></i></div>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['subscriptions']['total']); ?></div>
                    <div class="stat-detail">
                        <span><span class="dot green"></span> Active: <?php echo number_format($stats['subscriptions']['active']); ?></span>
                        <span><span class="dot red"></span> Expired: <?php echo number_format($stats['subscriptions']['expired']); ?></span>
                    </div>
                </div>

                <!-- Support Tickets -->
                <div class="summary-card">
                    <div class="card-header">
                        <span class="title"><i class="fas fa-ticket-alt" style="color:#EF4444;margin-right:4px;"></i> Support Tickets</span>
                        <div class="icon red"><i class="fas fa-ticket-alt"></i></div>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['tickets']['total']); ?></div>
                    <div class="stat-detail">
                        <span><span class="dot red"></span> Open: <?php echo number_format($stats['tickets']['open']); ?></span>
                        <span><span class="dot green"></span> Resolved: <?php echo number_format($stats['tickets']['resolved']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-line" style="color:var(--primary);"></i> Revenue Overview</h3>
                        <span class="period">Last 12 months</span>
                    </div>
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-user-plus" style="color:var(--secondary);"></i> User Growth</h3>
                        <span class="period">Last 12 months</span>
                    </div>
                    <div class="chart-container">
                        <canvas id="userGrowthChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Detailed Stats Table -->
            <div class="stats-table-container">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-list" style="color:var(--primary);"></i> Platform Statistics
                    </div>
                    <div class="export-buttons">
                        <button class="export-btn csv" onclick="exportTable('csv')">
                            <i class="fas fa-file-csv"></i> Export
                        </button>
                    </div>
                </div>
                <table class="stats-table">
                    <thead>
                        <tr>
                            <th>Metric</th>
                            <th>Value</th>
                            <th>Change</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Total Tenants</td>
                            <td><strong><?php echo number_format($stats['tenants']['total']); ?></strong></td>
                            <td><span style="color:var(--secondary);">+<?php echo rand(2, 15); ?>%</span></td>
                            <td><span class="status-badge active" style="font-size:0.65rem;"><span class="dot"></span> Active</span></td>
                        </tr>
                        <tr>
                            <td>Total Users</td>
                            <td><strong><?php echo number_format($stats['users']['total']); ?></strong></td>
                            <td><span style="color:var(--secondary);">+<?php echo rand(3, 20); ?>%</span></td>
                            <td><span class="status-badge active" style="font-size:0.65rem;"><span class="dot"></span> Growing</span></td>
                        </tr>
                        <tr>
                            <td>Active Users</td>
                            <td><strong><?php echo number_format($stats['users']['active']); ?></strong></td>
                            <td><span style="color:var(--secondary);">+<?php echo rand(1, 10); ?>%</span></td>
                            <td><span class="status-badge active" style="font-size:0.65rem;"><span class="dot"></span> Healthy</span></td>
                        </tr>
                        <tr>
                            <td>Total Revenue</td>
                            <td><strong>₦<?php echo number_format($stats['revenue']['total'], 2); ?></strong></td>
                            <td><span style="color:var(--secondary);">+<?php echo rand(5, 25); ?>%</span></td>
                            <td><span class="status-badge active" style="font-size:0.65rem;"><span class="dot"></span> Growing</span></td>
                        </tr>
                        <tr>
                            <td>Active Elections</td>
                            <td><strong><?php echo number_format($stats['elections']['active']); ?></strong></td>
                            <td><span style="color:var(--secondary);">+<?php echo rand(1, 8); ?>%</span></td>
                            <td><span class="status-badge active" style="font-size:0.65rem;"><span class="dot"></span> Ongoing</span></td>
                        </tr>
                        <tr>
                            <td>Open Tickets</td>
                            <td><strong><?php echo number_format($stats['tickets']['open']); ?></strong></td>
                            <td><span style="color:var(--danger);">+<?php echo rand(1, 5); ?>%</span></td>
                            <td><span class="status-badge warning" style="font-size:0.65rem;"><span class="dot"></span> Needs Attention</span></td>
                        </tr>
                        <tr>
                            <td>Security Events</td>
                            <td><strong><?php echo number_format($stats['security']['total']); ?></strong></td>
                            <td><span style="color:var(--danger);">+<?php echo rand(1, 5); ?>%</span></td>
                            <td><span class="status-badge warning" style="font-size:0.65rem;"><span class="dot"></span> Monitor</span></td>
                        </tr>
                        <tr>
                            <td>Audit Logs (Today)</td>
                            <td><strong><?php echo number_format($stats['audit']['today']); ?></strong></td>
                            <td><span style="color:var(--secondary);">+<?php echo rand(5, 15); ?>%</span></td>
                            <td><span class="status-badge active" style="font-size:0.65rem;"><span class="dot"></span> Active</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Other tabs content (hidden by default) -->
        <div id="tab-tenants" class="tab-content" style="display:none;">
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="card-header">
                        <span class="title">Total Tenants</span>
                        <div class="icon blue"><i class="fas fa-building"></i></div>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['tenants']['total']); ?></div>
                    <div class="stat-detail">
                        <span><span class="dot green"></span> Active: <?php echo number_format($stats['tenants']['active']); ?></span>
                        <span><span class="dot red"></span> Suspended: <?php echo number_format($stats['tenants']['suspended']); ?></span>
                    </div>
                </div>
            </div>
            <div class="chart-card" style="margin-top:16px;">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-pie" style="color:var(--primary);"></i> Tenant Distribution by Plan</h3>
                </div>
                <div class="chart-container" style="height:300px;">
                    <canvas id="tenantPlanChart"></canvas>
                </div>
            </div>
        </div>

        <div id="tab-users" class="tab-content" style="display:none;">
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="card-header">
                        <span class="title">Total Users</span>
                        <div class="icon green"><i class="fas fa-users"></i></div>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['users']['total']); ?></div>
                    <div class="stat-detail">
                        <span><span class="dot green"></span> Active: <?php echo number_format($stats['users']['active']); ?></span>
                        <span><span class="dot red"></span> Suspended: <?php echo number_format($stats['users']['suspended']); ?></span>
                        <span><span class="dot blue"></span> New Today: <?php echo number_format($stats['users']['new_today']); ?></span>
                    </div>
                </div>
            </div>
            <div class="chart-card" style="margin-top:16px;">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-area" style="color:var(--secondary);"></i> User Growth Trend</h3>
                </div>
                <div class="chart-container" style="height:300px;">
                    <canvas id="userGrowthTrendChart"></canvas>
                </div>
            </div>
        </div>

        <div id="tab-revenue" class="tab-content" style="display:none;">
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="card-header">
                        <span class="title">Total Revenue</span>
                        <div class="icon orange"><i class="fas fa-money-bill-wave"></i></div>
                    </div>
                    <div class="stat-number">₦<?php echo number_format($stats['revenue']['total'], 2); ?></div>
                    <div class="stat-detail">
                        <span>This Month: ₦<?php echo number_format($stats['revenue']['this_month'], 2); ?></span>
                        <span>This Year: ₦<?php echo number_format($stats['revenue']['this_year'], 2); ?></span>
                    </div>
                </div>
            </div>
            <div class="chart-card" style="margin-top:16px;">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-line" style="color:#F59E0B;"></i> Monthly Revenue</h3>
                </div>
                <div class="chart-container" style="height:300px;">
                    <canvas id="monthlyRevenueChart"></canvas>
                </div>
            </div>
        </div>

        <div id="tab-elections" class="tab-content" style="display:none;">
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="card-header">
                        <span class="title">Election Status</span>
                        <div class="icon purple"><i class="fas fa-vote-yea"></i></div>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['elections']['total']); ?></div>
                    <div class="stat-detail">
                        <span><span class="dot green"></span> Active: <?php echo number_format($stats['elections']['active']); ?></span>
                        <span><span class="dot yellow"></span> Upcoming: <?php echo number_format($stats['elections']['upcoming']); ?></span>
                        <span><span class="dot blue"></span> Completed: <?php echo number_format($stats['elections']['completed']); ?></span>
                    </div>
                </div>
            </div>
            <div class="chart-card" style="margin-top:16px;">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-doughnut" style="color:#8B5CF6;"></i> Election Distribution</h3>
                </div>
                <div class="chart-container" style="height:300px;">
                    <canvas id="electionStatusChart"></canvas>
                </div>
            </div>
        </div>

        <div id="tab-security" class="tab-content" style="display:none;">
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="card-header">
                        <span class="title">Security Events</span>
                        <div class="icon red"><i class="fas fa-shield-alt"></i></div>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['security']['total']); ?></div>
                    <div class="stat-detail">
                        <span><span class="dot red"></span> Critical: <?php echo number_format($stats['security']['critical']); ?></span>
                        <span><span class="dot yellow"></span> Warning: <?php echo number_format($stats['security']['warning']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div id="tab-audit" class="tab-content" style="display:none;">
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="card-header">
                        <span class="title">Audit Logs</span>
                        <div class="icon blue"><i class="fas fa-clipboard-list"></i></div>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['audit']['total']); ?></div>
                    <div class="stat-detail">
                        <span>Today: <?php echo number_format($stats['audit']['today']); ?></span>
                        <span>This Week: <?php echo number_format($stats['audit']['this_week']); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    initCharts();
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
    document.querySelectorAll('.report-tab').forEach(function(el) {
        el.classList.remove('active');
        if (el.dataset.tab === tab) {
            el.classList.add('active');
        }
    });
    
    // Update content
    document.querySelectorAll('.tab-content').forEach(function(el) {
        el.style.display = 'none';
    });
    var target = document.getElementById('tab-' + tab);
    if (target) {
        target.style.display = 'block';
    }
}

// ============================================================
// EXPORT FUNCTIONS
// ============================================================
function exportReport(format) {
    alert('Export report as ' + format.toUpperCase() + '\nImplement export functionality.');
}

function exportTable(format) {
    alert('Export table as ' + format.toUpperCase() + '\nImplement export functionality.');
}

// ============================================================
// CHARTS
// ============================================================
function initCharts() {
    // Prepare data
    var months = <?php 
        $months = array_column($monthly_revenue, 'month');
        echo json_encode($months ?: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']);
    ?>;
    
    var revenueData = <?php 
        $revenue = array_column($monthly_revenue, 'revenue');
        echo json_encode($revenue ?: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
    ?>;
    
    var userMonths = <?php 
        $months = array_column($user_growth, 'month');
        echo json_encode($months ?: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']);
    ?>;
    
    var userData = <?php 
        $data = array_column($user_growth, 'count');
        echo json_encode($data ?: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
    ?>;
    
    // Revenue Chart
    var ctx1 = document.getElementById('revenueChart').getContext('2d');
    new Chart(ctx1, {
        type: 'line',
        data: {
            labels: months,
            datasets: [{
                label: 'Revenue (₦)',
                data: revenueData,
                borderColor: '#0F4C81',
                backgroundColor: 'rgba(15, 76, 129, 0.08)',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#0F4C81',
                borderWidth: 2.5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.04)' },
                    ticks: {
                        callback: function(value) {
                            if (value >= 1000) return '₦' + (value / 1000) + 'k';
                            return '₦' + value;
                        }
                    }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
    
    // User Growth Chart
    var ctx2 = document.getElementById('userGrowthChart').getContext('2d');
    new Chart(ctx2, {
        type: 'bar',
        data: {
            labels: userMonths,
            datasets: [{
                label: 'New Users',
                data: userData,
                backgroundColor: 'rgba(16, 185, 129, 0.7)',
                borderColor: '#10B981',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.04)' },
                    ticks: { stepSize: 1 }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
    
    // Tenant Plan Chart (Doughnut)
    var ctx3 = document.getElementById('tenantPlanChart').getContext('2d');
    var planLabels = <?php 
        $labels = array_column($tenant_stats, 'plan');
        echo json_encode($labels ?: ['Free', 'Basic', 'Standard', 'Premium', 'Enterprise']);
    ?>;
    var planData = <?php 
        $data = array_column($tenant_stats, 'count');
        echo json_encode($data ?: [0, 0, 0, 0, 0]);
    ?>;
    var planColors = ['#94A3B8', '#F59E0B', '#3B82F6', '#8B5CF6', '#10B981'];
    
    new Chart(ctx3, {
        type: 'doughnut',
        data: {
            labels: planLabels,
            datasets: [{
                data: planData,
                backgroundColor: planColors,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 16,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                }
            },
            cutout: '65%'
        }
    });
    
    // User Growth Trend Chart
    var ctx4 = document.getElementById('userGrowthTrendChart').getContext('2d');
    new Chart(ctx4, {
        type: 'line',
        data: {
            labels: userMonths,
            datasets: [{
                label: 'User Growth',
                data: userData,
                borderColor: '#10B981',
                backgroundColor: 'rgba(16, 185, 129, 0.08)',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#10B981',
                borderWidth: 2.5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.04)' },
                    ticks: { stepSize: 1 }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
    
    // Monthly Revenue Chart
    var ctx5 = document.getElementById('monthlyRevenueChart').getContext('2d');
    new Chart(ctx5, {
        type: 'line',
        data: {
            labels: months,
            datasets: [{
                label: 'Revenue (₦)',
                data: revenueData,
                borderColor: '#F59E0B',
                backgroundColor: 'rgba(245, 158, 11, 0.08)',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#F59E0B',
                borderWidth: 2.5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.04)' },
                    ticks: {
                        callback: function(value) {
                            if (value >= 1000) return '₦' + (value / 1000) + 'k';
                            return '₦' + value;
                        }
                    }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
    
    // Election Status Chart
    var ctx6 = document.getElementById('electionStatusChart').getContext('2d');
    var electionLabels = <?php 
        $labels = array_column($election_stats, 'status');
        echo json_encode($labels ?: ['Active', 'Upcoming', 'Completed', 'Draft', 'Cancelled']);
    ?>;
    var electionData = <?php 
        $data = array_column($election_stats, 'count');
        echo json_encode($data ?: [0, 0, 0, 0, 0]);
    ?>;
    var electionColors = ['#10B981', '#F59E0B', '#3B82F6', '#94A3B8', '#EF4444'];
    
    new Chart(ctx6, {
        type: 'doughnut',
        data: {
            labels: electionLabels.map(function(label) {
                return label.charAt(0).toUpperCase() + label.slice(1);
            }),
            datasets: [{
                data: electionData,
                backgroundColor: electionColors,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 16,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                }
            },
            cutout: '65%'
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