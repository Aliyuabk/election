<?php
// ============================================================
// BUDGET DETAILS - CLIENT ADMIN (PROFESSIONAL UI)
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
// GET BUDGET ID
// ============================================================
$budget_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($budget_id <= 0) {
    header('Location: budgets.php');
    exit();
}

// ============================================================
// FETCH BUDGET DETAILS
// ============================================================
$budget = null;
try {
    $stmt = $db->prepare("
        SELECT b.*, 
               e.name as election_name, e.type as election_type, e.election_date,
               u.full_name as created_by_name,
               (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE budget_id = b.id AND tenant_id = ? AND status != 'rejected') as spent_amount,
               (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE budget_id = b.id AND tenant_id = ? AND status = 'pending') as pending_amount,
               (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE budget_id = b.id AND tenant_id = ? AND status = 'approved') as approved_amount,
               (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE budget_id = b.id AND tenant_id = ? AND status = 'paid') as paid_amount
        FROM budgets b
        LEFT JOIN elections e ON b.election_id = e.id
        LEFT JOIN users u ON b.created_by = u.id
        WHERE b.id = ? AND b.tenant_id = ?
    ");
    $stmt->execute([$tenant_id, $tenant_id, $tenant_id, $tenant_id, $budget_id, $tenant_id]);
    $budget = $stmt->fetch();
    
    if (!$budget) {
        header('Location: budgets.php');
        exit();
    }
} catch (Exception $e) {
    header('Location: budgets.php');
    exit();
}

// ============================================================
// FETCH EXPENSES FOR THIS BUDGET
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$where_conditions = ["e.budget_id = ?", "e.tenant_id = ?"];
$params = [$budget_id, $tenant_id];

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Count total
$count_sql = "SELECT COUNT(*) as total FROM expenses e $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_expenses = $stmt->fetch()['total'] ?? 0;
$total_pages = ceil($total_expenses / $limit);

// Fetch expenses
$sql = "
    SELECT e.*, 
           u.first_name as paid_to_first, u.last_name as paid_to_last,
           au.first_name as approved_by_first, au.last_name as approved_by_last
    FROM expenses e
    LEFT JOIN users u ON e.paid_to_user_id = u.id
    LEFT JOIN users au ON e.approved_by = au.id
    $where_clause
    ORDER BY e.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

// ============================================================
// GET EXPENSE STATISTICS
// ============================================================
$expense_stats = [
    'total' => $budget['spent_amount'],
    'pending' => $budget['pending_amount'],
    'approved' => $budget['approved_amount'],
    'paid' => $budget['paid_amount'],
    'count' => 0
];

try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM expenses WHERE budget_id = ? AND tenant_id = ?");
    $stmt->execute([$budget_id, $tenant_id]);
    $expense_stats['count'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       BUDGET DETAILS - PROFESSIONAL UI STYLES
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
    
    .budget-hero {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 28px 32px;
        box-shadow: var(--shadow);
        margin-bottom: 24px;
        position: relative;
        overflow: hidden;
    }
    .budget-hero::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
    }
    .budget-hero .hero-content {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 16px;
    }
    .budget-hero .hero-info h1 {
        font-size: 1.4rem;
        font-weight: 700;
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .budget-hero .hero-info .meta {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        font-size: 0.85rem;
        color: var(--gray-500);
    }
    .budget-hero .hero-info .meta span {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .budget-hero .hero-status {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 12px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 600;
    }
    .badge-status .dot {
        width: 5px;
        height: 5px;
        border-radius: 50%;
        display: inline-block;
    }
    .badge-status.active { background: #ECFDF5; color: #065F46; }
    .badge-status.active .dot { background: #10B981; }
    .badge-status.closed { background: var(--gray-100); color: var(--gray-500); }
    .badge-status.closed .dot { background: var(--gray-400); }
    .badge-status.draft { background: #FFFBEB; color: #92400E; }
    .badge-status.draft .dot { background: #F59E0B; }
    .badge-status.cancelled { background: #FEF2F2; color: #991B1B; }
    .badge-status.cancelled .dot { background: #EF4444; }
    
    .badge-category {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 10px;
        font-size: 0.6rem;
        font-weight: 600;
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .badge-category.agent_payment { background: #F5F3FF; color: #5B21B6; }
    .badge-category.transport { background: #EFF6FF; color: #1E40AF; }
    .badge-category.materials { background: #ECFDF5; color: #065F46; }
    .badge-category.logistics { background: #FFFBEB; color: #92400E; }
    .badge-category.security { background: #FEF2F2; color: #991B1B; }
    .badge-category.communication { background: #F5F3FF; color: #5B21B6; }
    .badge-category.media { background: #EFF6FF; color: #1E40AF; }
    .badge-category.other { background: var(--gray-100); color: var(--gray-500); }
    
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
        cursor: default;
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
    .stat-item .number.blue { color: #3B82F6; }
    .stat-item .number.red { color: var(--danger); }
    .stat-item .number.purple { color: #8B5CF6; }
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
    
    .detail-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
        margin-bottom: 24px;
    }
    .detail-card {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 24px 28px;
        box-shadow: var(--shadow);
        transition: var(--transition);
    }
    .detail-card:hover {
        box-shadow: var(--shadow-hover);
    }
    .detail-card .card-title {
        font-weight: 600;
        font-size: 1rem;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--gray-100);
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--gray-700);
    }
    .detail-card .card-title i {
        color: var(--primary);
        font-size: 1.1rem;
    }
    
    .detail-row {
        display: flex;
        padding: 8px 0;
        border-bottom: 1px solid var(--gray-50);
        font-size: 0.85rem;
        transition: var(--transition);
    }
    .detail-row:hover {
        background: var(--gray-50);
        margin: 0 -8px;
        padding: 8px 8px;
        border-radius: 6px;
    }
    .detail-row:last-child {
        border-bottom: none;
    }
    .detail-row .label {
        font-weight: 500;
        color: var(--gray-500);
        min-width: 120px;
        flex-shrink: 0;
    }
    .detail-row .value {
        color: var(--gray-700);
        word-break: break-word;
    }
    
    .progress-bar-container {
        margin-bottom: 12px;
    }
    .progress-bar-container .progress-label {
        display: flex;
        justify-content: space-between;
        font-size: 0.75rem;
        color: var(--gray-500);
        margin-bottom: 4px;
    }
    .progress-bar-container .progress-track {
        width: 100%;
        height: 8px;
        background: var(--gray-200);
        border-radius: 4px;
        overflow: hidden;
    }
    .progress-bar-container .progress-track .progress-fill {
        height: 100%;
        border-radius: 4px;
        transition: width 1s ease;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
    }
    
    .table-container {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        overflow: hidden;
        box-shadow: var(--shadow);
        transition: var(--transition);
    }
    .table-container:hover {
        box-shadow: var(--shadow-hover);
    }
    .table-container .table-header {
        padding: 14px 20px;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        background: linear-gradient(135deg, var(--gray-50), white);
    }
    .table-container .table-header .table-title {
        font-weight: 600;
        font-size: 0.9rem;
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
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
    }
    .data-table thead {
        background: var(--gray-50);
    }
    .data-table thead th {
        padding: 10px 14px;
        text-align: left;
        font-weight: 600;
        font-size: 0.65rem;
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
        padding: 8px 14px;
        border-bottom: 1px solid var(--gray-100);
        vertical-align: middle;
        transition: var(--transition);
    }
    .data-table tbody tr:last-child td {
        border-bottom: none;
    }
    .data-table tbody tr {
        transition: var(--transition);
    }
    .data-table tbody tr:hover {
        background: var(--gray-50);
    }
    
    .badge-status-sm {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        padding: 1px 8px;
        border-radius: 8px;
        font-size: 0.55rem;
        font-weight: 600;
    }
    .badge-status-sm .dot {
        width: 4px;
        height: 4px;
        border-radius: 50%;
        display: inline-block;
    }
    .badge-status-sm.pending { background: #FFFBEB; color: #92400E; }
    .badge-status-sm.pending .dot { background: #F59E0B; }
    .badge-status-sm.approved { background: #EFF6FF; color: #1E40AF; }
    .badge-status-sm.approved .dot { background: #3B82F6; }
    .badge-status-sm.paid { background: #ECFDF5; color: #065F46; }
    .badge-status-sm.paid .dot { background: #10B981; }
    .badge-status-sm.rejected { background: #FEF2F2; color: #991B1B; }
    .badge-status-sm.rejected .dot { background: #EF4444; }
    
    .pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        padding: 12px 20px;
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        margin-top: 16px;
        box-shadow: var(--shadow);
    }
    .pagination .info {
        font-size: 0.78rem;
        color: var(--gray-500);
    }
    .pagination .info strong {
        color: var(--gray-700);
    }
    .pagination .pages {
        display: flex;
        gap: 4px;
        align-items: center;
    }
    .pagination .pages a,
    .pagination .pages span {
        padding: 4px 12px;
        border-radius: 6px;
        font-size: 0.78rem;
        text-decoration: none;
        color: var(--gray-600);
        transition: var(--transition);
        min-width: 32px;
        text-align: center;
        border: 1px solid transparent;
    }
    .pagination .pages a:hover {
        background: var(--gray-100);
        border-color: var(--gray-200);
    }
    .pagination .pages .active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        box-shadow: 0 2px 8px rgba(var(--primary-rgb), 0.2);
    }
    .pagination .pages .disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }
    
    .empty-state-small {
        text-align: center;
        padding: 30px 20px;
        color: var(--gray-400);
        font-size: 0.85rem;
    }
    .empty-state-small i {
        font-size: 2rem;
        display: block;
        margin-bottom: 8px;
        color: var(--gray-300);
    }
    
    .action-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 4px;
    }
    .action-buttons .btn {
        padding: 6px 14px;
        border-radius: 6px;
        border: none;
        font-weight: 500;
        font-size: 0.78rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        text-decoration: none;
    }
    
    @media (max-width: 768px) {
        .detail-grid {
            grid-template-columns: 1fr;
        }
        .budget-hero {
            padding: 20px;
        }
        .budget-hero .hero-content {
            flex-direction: column;
        }
        .budget-hero .hero-status {
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
        .table-container {
            overflow-x: auto;
        }
        .data-table {
            font-size: 0.75rem;
        }
        .data-table th, .data-table td {
            padding: 6px 10px;
        }
        .action-buttons {
            width: 100%;
        }
        .action-buttons .btn {
            flex: 1;
            justify-content: center;
        }
    }
    @media (max-width: 480px) {
        .budget-hero {
            padding: 16px;
        }
        .budget-hero .hero-info h1 {
            font-size: 1.1rem;
        }
        .stats-grid {
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .stat-item {
            padding: 10px 12px;
        }
        .stat-item .number {
            font-size: 1.1rem;
        }
        .detail-card {
            padding: 16px 18px;
        }
        .data-table th, .data-table td {
            padding: 4px 8px;
            font-size: 0.7rem;
        }
        .action-buttons {
            flex-direction: column;
        }
        .action-buttons .btn {
            width: 100%;
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
                    <i class="fas fa-wallet" style="color:var(--primary);margin-right:8px;"></i> Budget Details
                    <small>Complete budget information and financial breakdown</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="budgets-edit.php?id=<?php echo $budget_id; ?>" class="btn-primary">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <a href="expenses.php?budget=<?php echo $budget_id; ?>" class="btn-outline">
                    <i class="fas fa-receipt"></i> View Expenses
                </a>
                <a href="budgets.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Budget Hero -->
        <div class="budget-hero">
            <div class="hero-content">
                <div class="hero-info">
                    <h1>
                        <?php echo htmlspecialchars($budget['name']); ?>
                        <span class="badge-status <?php echo $budget['status']; ?>">
                            <span class="dot"></span>
                            <?php echo ucfirst($budget['status']); ?>
                        </span>
                    </h1>
                    <div class="meta">
                        <span><i class="fas fa-calendar-alt"></i> 
                            <?php echo date('M j, Y', strtotime($budget['start_date'])); ?>
                            <?php if (!empty($budget['end_date'])): ?>
                                - <?php echo date('M j, Y', strtotime($budget['end_date'])); ?>
                            <?php endif; ?>
                        </span>
                        <?php if (!empty($budget['election_name'])): ?>
                            <span><i class="fas fa-vote-yea"></i> <?php echo htmlspecialchars($budget['election_name']); ?></span>
                        <?php endif; ?>
                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($budget['created_by_name'] ?? 'System'); ?></span>
                        <span><i class="fas fa-clock"></i> Created <?php echo date('M j, Y', strtotime($budget['created_at'])); ?></span>
                    </div>
                </div>
                <div class="hero-status">
                    <div style="text-align:right;">
                        <div style="font-size:1.2rem;font-weight:700;color:var(--primary);">
                            ₦<?php echo number_format($budget['total_amount']); ?>
                        </div>
                        <div style="font-size:0.7rem;color:var(--gray-500);">Total Budget</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-item">
                <div class="number green">₦<?php echo number_format($budget['spent_amount']); ?></div>
                <div class="label">Total Spent</div>
                <div class="sub-label"><?php echo $budget['total_amount'] > 0 ? round(($budget['spent_amount'] / $budget['total_amount']) * 100, 1) : 0; ?>% utilized</div>
            </div>
            <div class="stat-item">
                <div class="number blue">₦<?php echo number_format($budget['approved_amount']); ?></div>
                <div class="label">Approved</div>
                <div class="sub-label">Awaiting payment</div>
            </div>
            <div class="stat-item">
                <div class="number yellow">₦<?php echo number_format($budget['pending_amount']); ?></div>
                <div class="label">Pending</div>
                <div class="sub-label">Awaiting approval</div>
            </div>
            <class="stat-item">
                <div class="number red">₦<?php echo number_format($budget['total_amount'] - $budget['spent_amount']); ?></div>
                <div class="label">Remaining</div>
                <div class="sub-label">Available balance</div>
            </div>
        </div>

        <!-- Detail Grid -->
        <div class="detail-grid">
            <!-- Left Column - Details -->
            <div>
                <div class="detail-card">
                    <div class="card-title">
                        <i class="fas fa-info-circle" style="color:var(--primary);"></i> Budget Information
                    </div>
                    <div class="detail-row">
                        <span class="label">Budget Name</span>
                        <span class="value"><strong><?php echo htmlspecialchars($budget['name']); ?></strong></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Total Amount</span>
                        <span class="value"><strong>₦<?php echo number_format($budget['total_amount']); ?></strong></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Status</span>
                        <span class="value">
                            <span class="badge-status <?php echo $budget['status']; ?>">
                                <span class="dot"></span>
                                <?php echo ucfirst($budget['status']); ?>
                            </span>
                        </span>
                    </div>
                    <?php if (!empty($budget['election_name'])): ?>
                    <div class="detail-row">
                        <span class="label">Election</span>
                        <span class="value">
                            <?php echo htmlspecialchars($budget['election_name']); ?>
                            <?php if (!empty($budget['election_type'])): ?>
                                (<?php echo ucfirst(str_replace('_', ' ', $budget['election_type'])); ?>)
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="detail-row">
                        <span class="label">Period</span>
                        <span class="value">
                            <?php echo date('M j, Y', strtotime($budget['start_date'])); ?>
                            <?php if (!empty($budget['end_date'])): ?>
                                - <?php echo date('M j, Y', strtotime($budget['end_date'])); ?>
                            <?php else: ?>
                                - Ongoing
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Created By</span>
                        <span class="value"><?php echo htmlspecialchars($budget['created_by_name'] ?? 'System'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Created At</span>
                        <span class="value"><?php echo date('M j, Y g:i A', strtotime($budget['created_at'])); ?></span>
                    </div>
                    <?php if (!empty($budget['description'])): ?>
                    <div class="detail-row" style="flex-direction:column;align-items:flex-start;">
                        <span class="label">Description</span>
                        <span class="value" style="margin-top:4px;font-size:0.82rem;color:var(--gray-600);">
                            <?php echo nl2br(htmlspecialchars($budget['description'])); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Progress -->
                <div class="detail-card">
                    <div class="card-title">
                        <i class="fas fa-chart-pie" style="color:var(--primary);"></i> Budget Progress
                    </div>
                    <?php 
                    $percent_used = $budget['total_amount'] > 0 ? round(($budget['spent_amount'] / $budget['total_amount']) * 100, 1) : 0;
                    $percent_remaining = 100 - $percent_used;
                    ?>
                    <div class="progress-bar-container">
                        <div class="progress-label">
                            <span>Utilization</span>
                            <span><?php echo $percent_used; ?>%</span>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill" style="width: <?php echo $percent_used; ?>%;"></div>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:12px;">
                        <div style="text-align:center;font-size:0.75rem;padding:8px;background:#ECFDF5;border-radius:6px;">
                            <div style="font-weight:700;color:#065F46;"><?php echo $percent_used; ?>%</div>
                            <div style="color:var(--gray-500);">Used</div>
                        </div>
                        <div style="text-align:center;font-size:0.75rem;padding:8px;background:#EFF6FF;border-radius:6px;">
                            <div style="font-weight:700;color:#1E40AF;"><?php echo $percent_remaining; ?>%</div>
                            <div style="color:var(--gray-500);">Remaining</div>
                        </div>
                    </div>
                    <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--gray-100);display:flex;justify-content:space-between;font-size:0.75rem;color:var(--gray-500);">
                        <span>Spent: ₦<?php echo number_format($budget['spent_amount']); ?></span>
                        <span>Remaining: ₦<?php echo number_format($budget['total_amount'] - $budget['spent_amount']); ?></span>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="detail-card" style="margin-top:16px;">
                    <div class="card-title">
                        <i class="fas fa-bolt" style="color:var(--primary);"></i> Quick Actions
                    </div>
                    <div class="action-buttons">
                        <a href="budgets-edit.php?id=<?php echo $budget_id; ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="expenses.php?budget=<?php echo $budget_id; ?>" class="btn btn-success">
                            <i class="fas fa-receipt"></i> Expenses
                        </a>
                        <?php if ($budget['status'] == 'active'): ?>
                            <button onclick="closeBudget(<?php echo $budget_id; ?>)" class="btn btn-outline">
                                <i class="fas fa-check-circle"></i> Close
                            </button>
                        <?php endif; ?>
                        <button class="btn btn-outline" onclick="window.print();">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expenses Section -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-receipt" style="color:var(--primary);"></i> Related Expenses
                    <span class="count"><?php echo number_format($expense_stats['count']); ?></span>
                </div>
                <div class="table-actions">
                    <a href="expenses.php?budget=<?php echo $budget_id; ?>" class="btn-sm info">
                        <i class="fas fa-eye"></i> View All
                    </a>
                </div>
            </div>
            <?php if (count($expenses) > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:30px;">#</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses as $index => $expense): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <div style="font-weight:500;font-size:0.8rem;">
                                    <?php echo htmlspecialchars($expense['description']); ?>
                                </div>
                                <?php if (!empty($expense['payment_reference'])): ?>
                                    <div style="font-size:0.6rem;color:var(--gray-400);">
                                        Ref: <?php echo htmlspecialchars($expense['payment_reference']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge-category <?php echo $expense['category']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $expense['category'])); ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-weight:600;font-size:0.85rem;">
                                    ₦<?php echo number_format($expense['amount']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge-status-sm <?php echo $expense['status']; ?>">
                                    <span class="dot"></span>
                                    <?php echo ucfirst($expense['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-size:0.7rem;">
                                    <?php echo date('M j, Y', strtotime($expense['created_at'])); ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="empty-state-small">
                    <i class="fas fa-receipt"></i>
                    No expenses recorded for this budget yet.
                    <div style="margin-top:8px;">
                        <a href="expenses.php?budget=<?php echo $budget_id; ?>" class="btn-sm info">
                            <i class="fas fa-plus"></i> Add Expense
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <div class="info">
                Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $limit, $total_expenses); ?></strong> of <strong><?php echo number_format($total_expenses); ?></strong> expenses
            </div>
            <div class="pages">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1) {
                    echo '<a href="?page=1">1</a>';
                    if ($start_page > 2) echo '<span>…</span>';
                }
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="?page=<?php echo $i; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor;
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<span>…</span>';
                    echo '<a href="?page=' . $total_pages . '">' . $total_pages . '</a>';
                }
                ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
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
// BUDGET FUNCTIONS
// ============================================================
function closeBudget(id) {
    if (confirm('Close this budget? This will mark it as closed and prevent further changes.')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = 'budgets.php';
        form.innerHTML = '<input type="hidden" name="action" value="close_budget"><input type="hidden" name="id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
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