<?php
// ============================================================
// REPORTS MANAGEMENT - CLIENT ADMIN (PROFESSIONAL UI)
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
// FETCH STATISTICS FOR DASHBOARD
// ============================================================
$stats = [
    'total_users' => 0,
    'total_agents' => 0,
    'total_elections' => 0,
    'total_incidents' => 0,
    'total_candidates' => 0,
    'total_polling_units' => 0,
    'total_budget' => 0,
    'total_expenses' => 0
];

try {
    // Users
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE tenant_id = ? AND status = 'active'");
    $stmt->execute([$tenant_id]);
    $stats['total_users'] = $stmt->fetch()['total'] ?? 0;
    
    // Agents (users with agent roles)
    $stmt = $db->prepare("
        SELECT COUNT(*) as total FROM users u 
        LEFT JOIN roles r ON u.role_id = r.id 
        WHERE u.tenant_id = ? AND u.status = 'active' 
        AND r.level IN ('pu_agent', 'party_agent', 'volunteer', 'observer')
    ");
    $stmt->execute([$tenant_id]);
    $stats['total_agents'] = $stmt->fetch()['total'] ?? 0;
    
    // Elections
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM elections WHERE tenant_id = ? AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $stats['total_elections'] = $stmt->fetch()['total'] ?? 0;
    
    // Incidents
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM incidents WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $stats['total_incidents'] = $stmt->fetch()['total'] ?? 0;
    
    // Candidates
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM candidates WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $stats['total_candidates'] = $stmt->fetch()['total'] ?? 0;
    
    // Polling Units
    $stmt = $db->query("SELECT COUNT(*) as total FROM polling_units WHERE is_active = 1");
    $stats['total_polling_units'] = $stmt->fetch()['total'] ?? 0;
    
    // Budget
    $stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM budgets WHERE tenant_id = ? AND status = 'active'");
    $stmt->execute([$tenant_id]);
    $stats['total_budget'] = $stmt->fetch()['total'] ?? 0;
    
    // Expenses
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE tenant_id = ? AND status != 'rejected'");
    $stmt->execute([$tenant_id]);
    $stats['total_expenses'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       REPORTS MANAGEMENT - PROFESSIONAL UI STYLES
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
    .btn-sm {
        padding: 4px 12px;
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
    .btn-sm.info { background: #EFF6FF; color: #1E40AF; }
    .btn-sm.info:hover { background: #DBEAFE; }
    .btn-sm.success { background: #ECFDF5; color: #065F46; }
    .btn-sm.success:hover { background: #D1FAE5; }
    .btn-sm.danger { background: #FEF2F2; color: #991B1B; }
    .btn-sm.danger:hover { background: #FEE2E2; }
    .btn-sm.warning { background: #FFFBEB; color: #92400E; }
    .btn-sm.warning:hover { background: #FEF3C7; }
    .btn-sm.purple { background: #F5F3FF; color: #5B21B6; }
    .btn-sm.purple:hover { background: #EDE9FE; }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 12px;
        margin-bottom: 24px;
    }
    .stat-item {
        background: white;
        border-radius: 12px;
        padding: 16px 20px;
        border: 1px solid var(--gray-200);
        text-align: center;
        transition: var(--transition);
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }
    .stat-item::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
        opacity: 0;
        transition: var(--transition);
    }
    .stat-item:hover::before {
        opacity: 1;
    }
    .stat-item:hover {
        box-shadow: var(--shadow-hover);
        transform: translateY(-3px);
    }
    .stat-item .number {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--primary);
        line-height: 1.2;
    }
    .stat-item .number.green { color: var(--secondary); }
    .stat-item .number.yellow { color: var(--warning); }
    .stat-item .number.red { color: var(--danger); }
    .stat-item .number.purple { color: #8B5CF6; }
    .stat-item .number.blue { color: #3B82F6; }
    .stat-item .number.orange { color: #F59E0B; }
    .stat-item .label {
        font-size: 0.7rem;
        color: var(--gray-500);
        margin-top: 4px;
        font-weight: 500;
    }
    .stat-item .sub-label {
        font-size: 0.6rem;
        color: var(--gray-400);
        margin-top: 2px;
    }
    
    .reports-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 24px;
    }
    
    .report-card {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        overflow: hidden;
        transition: var(--transition);
        box-shadow: var(--shadow);
        position: relative;
    }
    .report-card:hover {
        box-shadow: var(--shadow-hover);
        transform: translateY(-4px);
    }
    .report-card .report-header {
        padding: 20px 20px 16px;
        display: flex;
        align-items: flex-start;
        gap: 14px;
        border-bottom: 1px solid var(--gray-100);
    }
    .report-card .report-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        flex-shrink: 0;
    }
    .report-card .report-icon.primary { background: #EFF6FF; color: var(--primary); }
    .report-card .report-icon.success { background: #ECFDF5; color: var(--secondary); }
    .report-card .report-icon.warning { background: #FFFBEB; color: #F59E0B; }
    .report-card .report-icon.danger { background: #FEF2F2; color: var(--danger); }
    .report-card .report-icon.purple { background: #F5F3FF; color: #8B5CF6; }
    .report-card .report-icon.orange { background: #FFF7ED; color: #EA580C; }
    .report-card .report-icon.teal { background: #ECFDF5; color: #0D9488; }
    .report-card .report-icon.pink { background: #FDF2F8; color: #DB2777; }
    
    .report-card .report-info {
        flex: 1;
        min-width: 0;
    }
    .report-card .report-info h3 {
        font-weight: 700;
        font-size: 1rem;
        color: var(--gray-800);
        margin-bottom: 2px;
    }
    .report-card .report-info p {
        font-size: 0.78rem;
        color: var(--gray-500);
    }
    .report-card .report-info .count {
        font-weight: 600;
        font-size: 0.85rem;
        color: var(--primary);
        margin-top: 2px;
    }
    
    .report-card .report-body {
        padding: 16px 20px 20px;
    }
    .report-card .report-body .export-options {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }
    .report-card .report-body .export-options .btn-export {
        padding: 4px 12px;
        border-radius: 6px;
        border: 1px solid var(--gray-200);
        background: white;
        font-size: 0.7rem;
        font-weight: 500;
        color: var(--gray-600);
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        text-decoration: none;
    }
    .report-card .report-body .export-options .btn-export:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: #EFF6FF;
    }
    .report-card .report-body .export-options .btn-export i {
        font-size: 0.7rem;
    }
    .report-card .report-body .export-options .btn-export.pdf i { color: #DC2626; }
    .report-card .report-body .export-options .btn-export.excel i { color: #10B981; }
    .report-card .report-body .export-options .btn-export.csv i { color: #3B82F6; }
    
    .report-card .report-footer {
        padding: 10px 20px;
        border-top: 1px solid var(--gray-100);
        background: var(--gray-50);
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.7rem;
        color: var(--gray-400);
    }
    
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.4);
        backdrop-filter: blur(4px);
        z-index: 300;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .modal-overlay.active { display: flex; }
    .modal {
        background: white;
        border-radius: var(--radius);
        max-width: 560px;
        width: 100%;
        padding: 28px 32px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        animation: modalIn 0.3s ease;
        max-height: 90vh;
        overflow-y: auto;
    }
    @keyframes modalIn {
        from { opacity: 0; transform: scale(0.95) translateY(20px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }
    .modal .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 14px;
        border-bottom: 2px solid var(--gray-100);
    }
    .modal .modal-header h3 {
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--gray-800);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .modal .modal-header h3 i {
        color: var(--primary);
    }
    .modal .modal-header .close-btn {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: var(--gray-400);
        cursor: pointer;
        transition: var(--transition);
        padding: 4px 8px;
        border-radius: 8px;
    }
    .modal .modal-header .close-btn:hover {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .modal .form-group {
        margin-bottom: 16px;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .modal .form-group label {
        font-weight: 600;
        font-size: 0.85rem;
        color: var(--gray-700);
    }
    .modal .form-group .help-text {
        font-size: 0.75rem;
        color: var(--gray-400);
        margin-top: 2px;
    }
    .modal .form-group select,
    .modal .form-group input {
        padding: 10px 14px;
        border: 1.5px solid var(--gray-200);
        border-radius: 10px;
        font-family: 'Inter', sans-serif;
        font-size: 0.85rem;
        transition: var(--transition);
        background: var(--gray-50);
        color: var(--gray-700);
        width: 100%;
    }
    .modal .form-group select:focus,
    .modal .form-group input:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 4px rgba(var(--primary-rgb), 0.08);
    }
    .modal .form-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 20px;
        padding-top: 16px;
        border-top: 2px solid var(--gray-100);
    }
    .modal .form-actions .btn {
        padding: 10px 24px;
        border-radius: 10px;
        border: none;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .modal .form-actions .btn-primary {
        background: var(--primary);
        color: white;
    }
    .modal .form-actions .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.25);
    }
    .modal .form-actions .btn-secondary {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .modal .form-actions .btn-secondary:hover {
        background: var(--gray-200);
    }
    
    .toast {
        padding: 14px 20px;
        border-radius: 10px;
        color: white;
        font-size: 0.85rem;
        font-weight: 500;
        box-shadow: var(--shadow-hover);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        max-width: 100%;
    }
    .toast.success { background: var(--secondary); }
    .toast.error { background: var(--danger); }
    .toast.info { background: #3B82F6; }
    
    .toast-container {
        position: fixed;
        top: 80px;
        right: 20px;
        z-index: 999;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .reports-grid { grid-template-columns: 1fr; }
        .page-header { flex-direction: column; align-items: flex-start; }
        .modal { padding: 20px; margin: 10px; }
        .modal .form-actions { flex-direction: column; }
        .modal .form-actions .btn { width: 100%; justify-content: center; }
    }
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
        .stat-item { padding: 10px 12px; }
        .stat-item .number { font-size: 1.1rem; }
        .report-card .report-header { flex-direction: column; align-items: center; text-align: center; }
        .report-card .report-body .export-options { justify-content: center; }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Toast Messages -->
        <?php if (!empty($_GET['exported'])): ?>
        <div class="toast success" style="position:static;animation:none;">
            <i class="fas fa-check-circle"></i>
            Report exported successfully!
        </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-chart-bar" style="color:var(--primary);margin-right:8px;"></i> Reports
                    <small>Generate and export operational reports</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button onclick="openModal('customReportModal')" class="btn-primary">
                    <i class="fas fa-plus-circle"></i> Custom Report
                </button>
            </div>
        </div>

        <!-- Statistics Dashboard -->
        <div class="stats-grid">
            <div class="stat-item" onclick="generateReport('users')">
                <div class="number blue"><?php echo number_format($stats['total_users']); ?></div>
                <div class="label">Total Users</div>
                <div class="sub-label">Active accounts</div>
            </div>
            <div class="stat-item" onclick="generateReport('agents')">
                <div class="number purple"><?php echo number_format($stats['total_agents']); ?></div>
                <div class="label">Total Agents</div>
                <div class="sub-label">Field agents</div>
            </div>
            <div class="stat-item" onclick="generateReport('elections')">
                <div class="number green"><?php echo number_format($stats['total_elections']); ?></div>
                <div class="label">Elections</div>
                <div class="sub-label">Total elections</div>
            </div>
            <div class="stat-item" onclick="generateReport('incidents')">
                <div class="number red"><?php echo number_format($stats['total_incidents']); ?></div>
                <div class="label">Incidents</div>
                <div class="sub-label">Reported incidents</div>
            </div>
            <div class="stat-item" onclick="generateReport('candidates')">
                <div class="number orange"><?php echo number_format($stats['total_candidates']); ?></div>
                <div class="label">Candidates</div>
                <div class="sub-label">Registered candidates</div>
            </div>
            <div class="stat-item" onclick="generateReport('polling_units')">
                <div class="number yellow"><?php echo number_format($stats['total_polling_units']); ?></div>
                <div class="label">Polling Units</div>
                <div class="sub-label">Active PUs</div>
            </div>
            <div class="stat-item" onclick="generateReport('financial')">
                <div class="number" style="color:#0D9488;">₦<?php echo number_format($stats['total_budget']); ?></div>
                <div class="label">Budget</div>
                <div class="sub-label">Total allocated</div>
            </div>
            <div class="stat-item" onclick="generateReport('financial')">
                <div class="number" style="color:#EA580C;">₦<?php echo number_format($stats['total_expenses']); ?></div>
                <div class="label">Expenses</div>
                <div class="sub-label">Total spent</div>
            </div>
        </div>

        <!-- Reports Grid -->
        <div class="reports-grid">
            <!-- User Report -->
            <div class="report-card">
                <div class="report-header">
                    <div class="report-icon primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="report-info">
                        <h3>User Report</h3>
                        <p>Complete user directory and activity</p>
                        <div class="count"><?php echo number_format($stats['total_users']); ?> users</div>
                    </div>
                </div>
                <div class="report-body">
                    <div class="export-options">
                        
                        <button class="btn-export excel" onclick="exportReport('users', 'excel')">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                        <button class="btn-export csv" onclick="exportReport('users', 'csv')">
                            <i class="fas fa-file-csv"></i> CSV
                        </button>
                    </div>
                </div>
                <div class="report-footer">
                    <span><i class="fas fa-clock"></i> Real-time</span>
                    <span>Last updated: <?php echo date('M j, Y'); ?></span>
                </div>
            </div>

            <!-- Agent Report -->
            <div class="report-card">
                <div class="report-header">
                    <div class="report-icon success">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="report-info">
                        <h3>Agent Report</h3>
                        <p>Field agent performance and assignments</p>
                        <div class="count"><?php echo number_format($stats['total_agents']); ?> agents</div>
                    </div>
                </div>
                <div class="report-body">
                    <div class="export-options"> 
                        <button class="btn-export excel" onclick="exportReport('agents', 'excel')">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                        <button class="btn-export csv" onclick="exportReport('agents', 'csv')">
                            <i class="fas fa-file-csv"></i> CSV
                        </button>
                    </div>
                </div>
                <div class="report-footer">
                    <span><i class="fas fa-clock"></i> Real-time</span>
                    <span>Last updated: <?php echo date('M j, Y'); ?></span>
                </div>
            </div>

            <!-- Election Report -->
            <div class="report-card">
                <div class="report-header">
                    <div class="report-icon warning">
                        <i class="fas fa-vote-yea"></i>
                    </div>
                    <div class="report-info">
                        <h3>Election Report</h3>
                        <p>Election details and results summary</p>
                        <div class="count"><?php echo number_format($stats['total_elections']); ?> elections</div>
                    </div>
                </div>
                <div class="report-body">
                    <div class="export-options"> 
                        <button class="btn-export excel" onclick="exportReport('elections', 'excel')">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                        <button class="btn-export csv" onclick="exportReport('elections', 'csv')">
                            <i class="fas fa-file-csv"></i> CSV
                        </button>
                    </div>
                </div>
                <div class="report-footer">
                    <span><i class="fas fa-clock"></i> Real-time</span>
                    <span>Last updated: <?php echo date('M j, Y'); ?></span>
                </div>
            </div>

            <!-- Incident Report -->
            <div class="report-card">
                <div class="report-header">
                    <div class="report-icon danger">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="report-info">
                        <h3>Incident Report</h3>
                        <p>All incidents with status and resolution</p>
                        <div class="count"><?php echo number_format($stats['total_incidents']); ?> incidents</div>
                    </div>
                </div>
                <div class="report-body">
                    <div class="export-options"> 
                        <button class="btn-export excel" onclick="exportReport('incidents', 'excel')">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                        <button class="btn-export csv" onclick="exportReport('incidents', 'csv')">
                            <i class="fas fa-file-csv"></i> CSV
                        </button>
                    </div>
                </div>
                <div class="report-footer">
                    <span><i class="fas fa-clock"></i> Real-time</span>
                    <span>Last updated: <?php echo date('M j, Y'); ?></span>
                </div>
            </div>

            <!-- Financial Report -->
            <div class="report-card">
                <div class="report-header">
                    <div class="report-icon teal">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="report-info">
                        <h3>Financial Report</h3>
                        <p>Budget, expenses, and financial summary</p>
                        <div class="count">₦<?php echo number_format($stats['total_budget']); ?> budget</div>
                    </div>
                </div>
                <div class="report-body">
                    <div class="export-options">
                        
                        <button class="btn-export excel" onclick="exportReport('financial', 'excel')">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                        <button class="btn-export csv" onclick="exportReport('financial', 'csv')">
                            <i class="fas fa-file-csv"></i> CSV
                        </button>
                    </div>
                </div>
                <div class="report-footer">
                    <span><i class="fas fa-clock"></i> Real-time</span>
                    <span>Last updated: <?php echo date('M j, Y'); ?></span>
                </div>
            </div>

            <!-- Polling Unit Report -->
            <!-- <div class="report-card">
                <div class="report-header">
                    <div class="report-icon orange">
                        <i class="fas fa-flag-checkered"></i>
                    </div>
                    <div class="report-info">
                        <h3>Polling Unit Report</h3>
                        <p>Polling unit details and statistics</p>
                        <div class="count"><?php echo number_format($stats['total_polling_units']); ?> PUs</div>
                    </div>
                </div>
                <div class="report-body"> 
                        <button class="btn-export excel" onclick="exportReport('polling_units', 'excel')">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                        <button class="btn-export csv" onclick="exportReport('polling_units', 'csv')">
                            <i class="fas fa-file-csv"></i> CSV
                        </button>
                    </div>
                </div>
                <div class="report-footer">
                    <span><i class="fas fa-clock"></i> Real-time</span>
                    <span>Last updated: <?php echo date('M j, Y'); ?></span>
                </div>
            </div> -->

            <!-- Candidate Report -->
            <div class="report-card">
                <div class="report-header">
                    <div class="report-icon pink">
                        <i class="fas fa-user-tag"></i>
                    </div>
                    <div class="report-info">
                        <h3>Candidate Report</h3>
                        <p>Candidate profiles and election participation</p>
                        <div class="count"><?php echo number_format($stats['total_candidates']); ?> candidates</div>
                    </div>
                </div>
                <div class="report-body">
                    <div class="export-options"> 
                        <button class="btn-export excel" onclick="exportReport('candidates', 'excel')">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                        <button class="btn-export csv" onclick="exportReport('candidates', 'csv')">
                            <i class="fas fa-file-csv"></i> CSV
                        </button>
                    </div>
                </div>
                <div class="report-footer">
                    <span><i class="fas fa-clock"></i> Real-time</span>
                    <span>Last updated: <?php echo date('M j, Y'); ?></span>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Custom Report Modal -->
<div class="modal-overlay" id="customReportModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle" style="color:var(--primary);"></i> Custom Report</h3>
            <button class="close-btn" onclick="closeModal('customReportModal')">&times;</button>
        </div>
        <form method="POST" action="reports-generate.php">
            <div class="form-group">
                <label>Report Type <span class="required">*</span></label>
                <select name="report_type" required>
                    <option value="users">User Report</option>
                    <option value="agents">Agent Report</option>
                    <option value="elections">Election Report</option>
                    <option value="incidents">Incident Report</option>
                    <option value="financial">Financial Report</option>
                    <option value="polling_units">Polling Unit Report</option>
                    <option value="candidates">Candidate Report</option>
                    <option value="custom">Custom Report</option>
                </select>
            </div>
            <div class="form-group">
                <label>Export Format <span class="required">*</span></label>
                <select name="format" required>
                    <option value="pdf">PDF</option>
                    <option value="excel">Excel</option>
                    <option value="csv">CSV</option>
                </select>
            </div>
            <div class="form-group">
                <label>Date From</label>
                <input type="date" name="date_from">
            </div>
            <div class="form-group">
                <label>Date To</label>
                <input type="date" name="date_to">
            </div>
            <div class="form-group">
                <label>Additional Filters</label>
                <select name="filter">
                    <option value="all">All</option>
                    <option value="active">Active Only</option>
                    <option value="inactive">Inactive Only</option>
                    <option value="pending">Pending Only</option>
                </select>
                <div class="help-text">Optional filters for your report</div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('customReportModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-file-alt"></i> Generate Report</button>
            </div>
        </form>
    </div>
</div>

<!-- Toast Container for notifications -->
<div class="toast-container" id="toastContainer"></div>

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
// MODAL FUNCTIONS
// ============================================================
function openModal(id) {
    document.getElementById(id).classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
    document.body.style.overflow = '';
}

document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
});

// ============================================================
// REPORT FUNCTIONS
// ============================================================
function generateReport(type) {
    // Scroll to the report card and highlight it
    var cards = document.querySelectorAll('.report-card');
    var targets = {
        'users': 0,
        'agents': 1,
        'elections': 2,
        'incidents': 3,
        'financial': 4,
        'polling_units': 5,
        'candidates': 6
    };
    
    var index = targets[type];
    if (index !== undefined && cards[index]) {
        cards[index].scrollIntoView({ behavior: 'smooth', block: 'center' });
        cards[index].style.borderColor = 'var(--primary)';
        cards[index].style.boxShadow = '0 8px 30px rgba(15, 76, 129, 0.15)';
        setTimeout(function() {
            cards[index].style.borderColor = '';
            cards[index].style.boxShadow = '';
        }, 3000);
    }
}

function exportReport(type, format) {
    // Show loading toast
    var toast = document.createElement('div');
    toast.className = 'toast info';
    toast.style.position = 'fixed';
    toast.style.top = '80px';
    toast.style.right = '20px';
    toast.style.zIndex = '999';
    toast.style.animation = 'slideIn 0.3s ease';
    toast.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating ' + type + ' report...';
    document.body.appendChild(toast);
    
    // Simulate export process
    setTimeout(function() {
        toast.remove();
        window.location.href = 'reports-export.php?type=' + type + '&format=' + format;
    }, 1500);
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