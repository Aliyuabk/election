<?php
$page_title = "Tenant Subscriptions";
require_once 'includes/db.php';

// Get database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// ============================================================
// HANDLE ACTIONS
// ============================================================
$message = '';
$error = '';
$message_type = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $subscription_id = isset($_POST['subscription_id']) ? (int)$_POST['subscription_id'] : 0;
    $tenant_id = isset($_POST['tenant_id']) ? (int)$_POST['tenant_id'] : 0;
    
    try {
        switch ($action) {
            case 'add_subscription':
                // Validate required fields
                if (empty($_POST['tenant_id'])) {
                    throw new Exception("Please select a tenant.");
                }
                if (empty($_POST['plan'])) {
                    throw new Exception("Please select a subscription plan.");
                }
                if (empty($_POST['billing_cycle'])) {
                    throw new Exception("Please select a billing cycle.");
                }
                if (empty($_POST['start_date'])) {
                    throw new Exception("Please select a start date.");
                }
                if (empty($_POST['end_date'])) {
                    throw new Exception("Please select an end date.");
                }
                
                // Calculate amount based on plan and billing cycle
                $plan_prices = [
                    'free' => 0,
                    'basic' => 50,
                    'standard' => 150,
                    'premium' => 350,
                    'enterprise' => 750
                ];
                
                $cycle_multipliers = [
                    'monthly' => 1,
                    'quarterly' => 3,
                    'yearly' => 12
                ];
                
                $plan = $_POST['plan'];
                $billing_cycle = $_POST['billing_cycle'];
                $base_price = $plan_prices[$plan] ?? 0;
                $multiplier = $cycle_multipliers[$billing_cycle] ?? 1;
                $amount = $base_price * $multiplier;
                
                // Insert subscription
                $sql = "INSERT INTO subscriptions (
                            tenant_id, plan, billing_cycle, amount, currency, 
                            start_date, end_date, auto_renew, payment_status, 
                            payment_method, transaction_reference, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $_POST['tenant_id'],
                    $plan,
                    $billing_cycle,
                    $amount,
                    $_POST['currency'] ?? 'NGN',
                    $_POST['start_date'],
                    $_POST['end_date'],
                    isset($_POST['auto_renew']) ? 1 : 0,
                    $_POST['payment_status'] ?? 'pending',
                    $_POST['payment_method'] ?? null,
                    $_POST['transaction_reference'] ?? null
                ]);
                
                $subscription_id = $conn->lastInsertId();
                
                // Update tenant subscription info
                $updateTenant = "UPDATE tenants SET 
                                 subscription_plan = ?, 
                                 subscription_status = ?, 
                                 subscription_end = ?,
                                 updated_at = NOW() 
                                 WHERE id = ?";
                $stmt = $conn->prepare($updateTenant);
                $stmt->execute([
                    $plan,
                    $_POST['payment_status'] === 'paid' ? 'active' : 'trial',
                    $_POST['end_date'],
                    $_POST['tenant_id']
                ]);
                
                // Log activity
                $logSql = "INSERT INTO activity_logs (user_id, tenant_id, activity_type, description, ip_address, created_at) 
                          VALUES (?, ?, 'subscription_added', ?, ?, NOW())";
                $logStmt = $conn->prepare($logSql);
                $tenantNameStmt = $conn->prepare("SELECT name FROM tenants WHERE id = ?");
                $tenantNameStmt->execute([$_POST['tenant_id']]);
                $tenantName = $tenantNameStmt->fetch()['name'] ?? 'Unknown Tenant';
                $logStmt->execute([
                    $_SESSION['user_id'] ?? 1,
                    $_POST['tenant_id'],
                    "Added subscription for tenant: " . $tenantName,
                    $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                ]);
                
                $message = "Subscription added successfully.";
                $message_type = 'success';
                break;
                
            case 'update_subscription':
                // Update subscription
                $sql = "UPDATE subscriptions SET 
                        plan = ?, billing_cycle = ?, amount = ?,
                        start_date = ?, end_date = ?, auto_renew = ?,
                        payment_status = ?, payment_method = ?,
                        transaction_reference = ?, updated_at = NOW()
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $_POST['plan'],
                    $_POST['billing_cycle'],
                    $_POST['amount'],
                    $_POST['start_date'],
                    $_POST['end_date'],
                    isset($_POST['auto_renew']) ? 1 : 0,
                    $_POST['payment_status'],
                    $_POST['payment_method'] ?? null,
                    $_POST['transaction_reference'] ?? null,
                    $subscription_id
                ]);
                
                // Update tenant if payment is now paid
                if ($_POST['payment_status'] === 'paid') {
                    $updateTenant = "UPDATE tenants SET 
                                     subscription_plan = ?, 
                                     subscription_status = 'active', 
                                     subscription_end = ?,
                                     updated_at = NOW() 
                                     WHERE id = ?";
                    $stmt = $conn->prepare($updateTenant);
                    $stmt->execute([
                        $_POST['plan'],
                        $_POST['end_date'],
                        $_POST['tenant_id']
                    ]);
                }
                
                $message = "Subscription updated successfully.";
                $message_type = 'success';
                break;
                
            case 'delete_subscription':
                $sql = "DELETE FROM subscriptions WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$subscription_id]);
                $message = "Subscription deleted successfully.";
                $message_type = 'success';
                break;
                
            case 'renew_subscription':
                // Get current subscription
                $subQuery = "SELECT * FROM subscriptions WHERE id = ?";
                $subStmt = $conn->prepare($subQuery);
                $subStmt->execute([$subscription_id]);
                $currentSub = $subStmt->fetch();
                
                if ($currentSub) {
                    // Create new subscription with extended dates
                    $new_start = date('Y-m-d', strtotime($currentSub['end_date'] . ' +1 day'));
                    $new_end = date('Y-m-d', strtotime($new_start . ' +' . ($currentSub['billing_cycle'] === 'monthly' ? '1 month' : ($currentSub['billing_cycle'] === 'quarterly' ? '3 months' : '1 year'))));
                    
                    $sql = "INSERT INTO subscriptions (
                                tenant_id, plan, billing_cycle, amount, currency,
                                start_date, end_date, auto_renew, payment_status,
                                payment_method, transaction_reference, created_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([
                        $currentSub['tenant_id'],
                        $currentSub['plan'],
                        $currentSub['billing_cycle'],
                        $currentSub['amount'],
                        $currentSub['currency'],
                        $new_start,
                        $new_end,
                        $currentSub['auto_renew'],
                        'pending',
                        $currentSub['payment_method'],
                        null
                    ]);
                    
                    // Update tenant
                    $updateTenant = "UPDATE tenants SET 
                                     subscription_end = ?,
                                     updated_at = NOW() 
                                     WHERE id = ?";
                    $stmt = $conn->prepare($updateTenant);
                    $stmt->execute([$new_end, $currentSub['tenant_id']]);
                    
                    $message = "Subscription renewed successfully.";
                    $message_type = 'success';
                }
                break;
                
            case 'bulk_action':
                $bulk_action = $_POST['bulk_action'] ?? '';
                $subscription_ids = $_POST['subscription_ids'] ?? [];
                
                if (!empty($subscription_ids) && is_array($subscription_ids)) {
                    $placeholders = implode(',', array_fill(0, count($subscription_ids), '?'));
                    
                    switch ($bulk_action) {
                        case 'delete':
                            $stmt = $conn->prepare("DELETE FROM subscriptions WHERE id IN ($placeholders)");
                            $stmt->execute($subscription_ids);
                            $message = count($subscription_ids) . " subscriptions deleted successfully.";
                            $message_type = 'success';
                            break;
                        case 'mark_paid':
                            $stmt = $conn->prepare("UPDATE subscriptions SET payment_status = 'paid', updated_at = NOW() WHERE id IN ($placeholders)");
                            $stmt->execute($subscription_ids);
                            $message = count($subscription_ids) . " subscriptions marked as paid.";
                            $message_type = 'success';
                            break;
                        case 'mark_overdue':
                            $stmt = $conn->prepare("UPDATE subscriptions SET payment_status = 'overdue', updated_at = NOW() WHERE id IN ($placeholders)");
                            $stmt->execute($subscription_ids);
                            $message = count($subscription_ids) . " subscriptions marked as overdue.";
                            $message_type = 'success';
                            break;
                    }
                }
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        $message_type = 'error';
    }
}

// ============================================================
// GET FILTERS AND PAGINATION
// ============================================================
$search = $_GET['search'] ?? '';
$filter_plan = $_GET['plan'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_tenant = isset($_GET['tenant']) ? $_GET['tenant'] : '';  // FIXED: Check if key exists
$filter_billing = $_GET['billing'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_order = $_GET['order'] ?? 'DESC';
$per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// ============================================================
// BUILD QUERY
// ============================================================
$base_query = "FROM subscriptions s
               INNER JOIN tenants t ON s.tenant_id = t.id
               WHERE t.deleted_at IS NULL";

$params = [];

if ($search) {
    $base_query .= " AND (t.name LIKE ? OR t.slug LIKE ? OR s.transaction_reference LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if ($filter_plan) {
    $base_query .= " AND s.plan = ?";
    $params[] = $filter_plan;
}

if ($filter_status) {
    $base_query .= " AND s.payment_status = ?";
    $params[] = $filter_status;
}

if ($filter_tenant) {
    $base_query .= " AND s.tenant_id = ?";
    $params[] = $filter_tenant;
}

if ($filter_billing) {
    $base_query .= " AND s.billing_cycle = ?";
    $params[] = $filter_billing;
}

if ($date_from) {
    $base_query .= " AND DATE(s.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $base_query .= " AND DATE(s.created_at) <= ?";
    $params[] = $date_to;
}

// Get total count
$count_query = "SELECT COUNT(*) as total " . $base_query;
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute($params);
$total_count = $count_stmt->fetch()['total'];
$total_pages = ceil($total_count / $per_page);

// Get paginated results
$query = "SELECT 
            s.*,
            t.name as tenant_name,
            t.slug as tenant_slug,
            t.subscription_plan as tenant_plan,
            t.subscription_status as tenant_status
          " . $base_query . "
          ORDER BY s.$sort_by $sort_order 
          LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;

$stmt = $conn->prepare($query);
$stmt->execute($params);
$subscriptions = $stmt->fetchAll();

// ============================================================
// GET FILTER OPTIONS
// ============================================================
$tenants = $conn->query("
    SELECT id, name, slug, subscription_plan, subscription_status 
    FROM tenants 
    WHERE deleted_at IS NULL 
    ORDER BY name
")->fetchAll();

// Get subscription stats with filters
$stats_query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN payment_status = 'overdue' THEN 1 ELSE 0 END) as overdue,
                    SUM(CASE WHEN payment_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                    SUM(CASE WHEN plan = 'free' THEN 1 ELSE 0 END) as free,
                    SUM(CASE WHEN plan = 'basic' THEN 1 ELSE 0 END) as basic,
                    SUM(CASE WHEN plan = 'standard' THEN 1 ELSE 0 END) as standard,
                    SUM(CASE WHEN plan = 'premium' THEN 1 ELSE 0 END) as premium,
                    SUM(CASE WHEN plan = 'enterprise' THEN 1 ELSE 0 END) as enterprise,
                    COALESCE(SUM(amount), 0) as total_revenue
                FROM subscriptions";
$stats = $conn->query($stats_query)->fetch();

// Get monthly revenue for chart
$monthlyRevenue = $conn->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        SUM(amount) as revenue,
        COUNT(*) as count
    FROM subscriptions 
    WHERE payment_status = 'paid'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
")->fetchAll();

// Get plan distribution with filters
$planDistribution = $conn->query("
    SELECT 
        plan,
        COUNT(*) as count
    FROM subscriptions
    GROUP BY plan
")->fetchAll();

include 'includes/base.php';
?>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/header.php'; ?>

<main class="main-content">
    <!-- ============================================================
    PAGE HEADER
    ============================================================ -->
    <div class="page-header">
        <div class="header-left">
            <h1>
                <i class="fas fa-crown" style="color:#f59e0b;"></i>
                Tenant Subscriptions
                <span class="page-badge"><?php echo number_format($total_count); ?></span>
            </h1>
            <p class="subtitle">Manage all tenant subscriptions and billing plans</p>
        </div>
        <div class="header-actions">
            <button class="btn-secondary" onclick="exportSubscriptions()">
                <i class="fas fa-file-export"></i> Export
            </button>
            <button class="btn-primary" onclick="showAddSubscription()">
                <i class="fas fa-plus"></i> Add Subscription
            </button>
        </div>
    </div>

    <!-- ============================================================
    ALERTS
    ============================================================ -->
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type ?: 'success'; ?>">
        <i class="fas fa-<?php echo $message_type === 'error' ? 'exclamation-circle' : 'check-circle'; ?>"></i>
        <?php echo htmlspecialchars($message); ?>
        <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($error); ?>
        <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php endif; ?>

    <!-- ============================================================
    STATISTICS
    ============================================================ -->
    <div class="subscription-stats">
        <div class="stat-card">
            <div class="stat-icon" style="background:#e8f0fe; color:#4f9cf7;">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo number_format($stats['total'] ?? 0); ?></div>
                <div class="stat-label">Total Subscriptions</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#d1fae5; color:#10b981;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo number_format($stats['active'] ?? 0); ?></div>
                <div class="stat-label">Active</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef3c7; color:#f59e0b;">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo number_format($stats['pending'] ?? 0); ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fee2e2; color:#ef4444;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo number_format($stats['overdue'] ?? 0); ?></div>
                <div class="stat-label">Overdue</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#ede9fe; color:#8b5cf6;">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number">₦<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>
    </div>

    <!-- ============================================================
    SEARCH & FILTERS
    ============================================================ -->
    <div class="filter-bar">
        <form method="GET" class="filter-form" id="filterForm">
            <div class="filter-group">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search by tenant or reference..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="filter-group">
                <i class="fas fa-building"></i>
                <select name="tenant">
                    <option value="">All Tenants</option>
                    <?php foreach ($tenants as $tenant): ?>
                    <option value="<?php echo $tenant['id']; ?>" <?php echo $filter_tenant == $tenant['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($tenant['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <i class="fas fa-crown"></i>
                <select name="plan">
                    <option value="">All Plans</option>
                    <option value="free" <?php echo $filter_plan === 'free' ? 'selected' : ''; ?>>Free</option>
                    <option value="basic" <?php echo $filter_plan === 'basic' ? 'selected' : ''; ?>>Basic</option>
                    <option value="standard" <?php echo $filter_plan === 'standard' ? 'selected' : ''; ?>>Standard</option>
                    <option value="premium" <?php echo $filter_plan === 'premium' ? 'selected' : ''; ?>>Premium</option>
                    <option value="enterprise" <?php echo $filter_plan === 'enterprise' ? 'selected' : ''; ?>>Enterprise</option>
                </select>
            </div>
            
            <div class="filter-group">
                <i class="fas fa-circle"></i>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="paid" <?php echo $filter_status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="overdue" <?php echo $filter_status === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                    <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    <option value="refunded" <?php echo $filter_status === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                </select>
            </div>
            
            <div class="filter-group">
                <i class="fas fa-sync"></i>
                <select name="billing">
                    <option value="">All Cycles</option>
                    <option value="monthly" <?php echo $filter_billing === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                    <option value="quarterly" <?php echo $filter_billing === 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                    <option value="yearly" <?php echo $filter_billing === 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                </select>
            </div>
            
            <div class="filter-group date-range">
                <i class="fas fa-calendar"></i>
                <input type="date" name="date_from" placeholder="From" value="<?php echo htmlspecialchars($date_from); ?>">
                <span style="color:#8b9bb5;">to</span>
                <input type="date" name="date_to" placeholder="To" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn-primary"><i class="fas fa-filter"></i> Filter</button>
                <a href="tenant-subscriptions.php" class="btn-secondary"><i class="fas fa-times"></i> Clear</a>
            </div>
        </form>
    </div>

    <!-- ============================================================
    PLAN DISTRIBUTION
    ============================================================ -->
    <div class="plan-distribution">
        <?php 
        $planColors = [
            'free' => 'free',
            'basic' => 'basic', 
            'standard' => 'standard',
            'premium' => 'premium',
            'enterprise' => 'enterprise'
        ];
        $planLabels = [
            'free' => 'Free',
            'basic' => 'Basic',
            'standard' => 'Standard',
            'premium' => 'Premium',
            'enterprise' => 'Enterprise'
        ];
        $planIcons = [
            'free' => 'fa-gift',
            'basic' => 'fa-star',
            'standard' => 'fa-crown',
            'premium' => 'fa-gem',
            'enterprise' => 'fa-rocket'
        ];
        ?>
        <?php foreach ($planDistribution as $plan): ?>
        <div class="plan-item <?php echo $planColors[$plan['plan']] ?? 'free'; ?>">
            <span class="plan-icon"><i class="fas <?php echo $planIcons[$plan['plan']] ?? 'fa-circle'; ?>"></i></span>
            <span class="plan-name"><?php echo $planLabels[$plan['plan']] ?? ucfirst($plan['plan']); ?></span>
            <span class="plan-count"><?php echo $plan['count']; ?></span>
            <div class="plan-bar" style="width: <?php echo ($stats['total'] > 0) ? ($plan['count'] / $stats['total'] * 100) : 0; ?>%;"></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ============================================================
    SUBSCRIPTIONS TABLE
    ============================================================ -->
    <div class="table-container">
        <div class="table-toolbar">
            <div class="toolbar-left">
                <form method="POST" id="bulkActionForm" onsubmit="return confirmBulkAction();">
                    <input type="hidden" name="action" value="bulk_action">
                    <div class="bulk-actions">
                        <input type="checkbox" id="selectAll" onchange="toggleAllCheckboxes()">
                        <label for="selectAll" style="font-size:0.8rem; color:#6d83a5; cursor:pointer;">Select All</label>
                        <select name="bulk_action" id="bulkAction">
                            <option value="">Bulk Actions</option>
                            <option value="mark_paid">Mark as Paid</option>
                            <option value="mark_overdue">Mark as Overdue</option>
                            <option value="delete">Delete</option>
                        </select>
                        <button type="submit" class="btn-secondary" style="padding:4px 16px; font-size:0.8rem;">
                            Apply
                        </button>
                    </div>
                </form>
            </div>
            <div class="toolbar-right">
                <span><i class="fas fa-database"></i> <?php echo number_format($total_count); ?> records</span>
                <span><i class="fas fa-arrow-up"></i> <?php echo ucfirst($sort_by); ?> (<?php echo $sort_order; ?>)</span>
            </div>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:36px;">
                        <input type="checkbox" id="selectAllHeader" onchange="toggleAllCheckboxes()">
                    </th>
                    <th style="width:50px;">
                        <a href="?sort=id&order=<?php echo $sort_order === 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>&plan=<?php echo urlencode($filter_plan); ?>&status=<?php echo urlencode($filter_status); ?>&tenant=<?php echo urlencode($filter_tenant); ?>&billing=<?php echo urlencode($filter_billing); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&page=<?php echo $page; ?>">
                            ID
                            <?php if ($sort_by === 'id'): ?>
                            <i class="fas fa-sort-<?php echo strtolower($sort_order); ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>Tenant</th>
                    <th>Plan</th>
                    <th>Billing</th>
                    <th>Amount</th>
                    <th>
                        <a href="?sort=start_date&order=<?php echo $sort_order === 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>&plan=<?php echo urlencode($filter_plan); ?>&status=<?php echo urlencode($filter_status); ?>&tenant=<?php echo urlencode($filter_tenant); ?>&billing=<?php echo urlencode($filter_billing); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&page=<?php echo $page; ?>">
                            Start
                            <?php if ($sort_by === 'start_date'): ?>
                            <i class="fas fa-sort-<?php echo strtolower($sort_order); ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>
                        <a href="?sort=end_date&order=<?php echo $sort_order === 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>&plan=<?php echo urlencode($filter_plan); ?>&status=<?php echo urlencode($filter_status); ?>&tenant=<?php echo urlencode($filter_tenant); ?>&billing=<?php echo urlencode($filter_billing); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&page=<?php echo $page; ?>">
                            End
                            <?php if ($sort_by === 'end_date'): ?>
                            <i class="fas fa-sort-<?php echo strtolower($sort_order); ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>Status</th>
                    <th style="width:80px;">Auto Renew</th>
                    <th style="width:180px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($subscriptions)): ?>
                <tr>
                    <td colspan="11" class="empty-table">
                        <i class="fas fa-crown" style="font-size:3rem; color:#dce6f0; display:block; margin-bottom:16px;"></i>
                        <h3>No subscriptions found</h3>
                        <p><?php echo $search || $filter_plan || $filter_status ? 'Try adjusting your filters.' : 'Start by adding your first subscription.'; ?></p>
                        <?php if (!$search && !$filter_plan && !$filter_status): ?>
                        <button onclick="showAddSubscription()" class="btn-primary" style="margin-top:12px;">
                            <i class="fas fa-plus"></i> Add Subscription
                        </button>
                        <?php else: ?>
                        <a href="tenant-subscriptions.php" class="btn-secondary" style="margin-top:12px;">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
                
                <?php foreach ($subscriptions as $sub): ?>
                <tr>
                    <td>
                        <div class="checkbox-wrapper">
                            <input type="checkbox" name="subscription_ids[]" value="<?php echo $sub['id']; ?>" class="sub-checkbox">
                        </div>
                    </td>
                    <td>
                        <span class="subscription-id">#<?php echo $sub['id']; ?></span>
                    </td>
                    <td>
                        <div class="tenant-cell">
                            <div class="tenant-info">
                                <div class="tenant-name-cell"><?php echo htmlspecialchars($sub['tenant_name']); ?></div>
                                <div class="tenant-slug"><?php echo htmlspecialchars($sub['tenant_slug']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="plan-badge <?php echo $sub['plan']; ?>">
                            <i class="fas <?php echo $planIcons[$sub['plan']] ?? 'fa-crown'; ?>"></i>
                            <?php echo ucfirst($sub['plan']); ?>
                        </span>
                    </td>
                    <td>
                        <span class="billing-cycle">
                            <i class="fas fa-sync"></i>
                            <?php echo ucfirst($sub['billing_cycle']); ?>
                        </span>
                    </td>
                    <td>
                        <div class="amount-display">
                            <strong>₦<?php echo number_format($sub['amount'], 2); ?></strong>
                            <span class="currency"><?php echo $sub['currency']; ?></span>
                        </div>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($sub['start_date'])); ?></td>
                    <td>
                        <?php echo date('M d, Y', strtotime($sub['end_date'])); ?>
                        <?php if (strtotime($sub['end_date']) < time()): ?>
                        <span class="expiry-warning">(Expired)</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-badge <?php echo $sub['payment_status']; ?>">
                            <?php if ($sub['payment_status'] === 'paid'): ?>
                            <i class="fas fa-check-circle"></i>
                            <?php elseif ($sub['payment_status'] === 'pending'): ?>
                            <i class="fas fa-clock"></i>
                            <?php elseif ($sub['payment_status'] === 'overdue'): ?>
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php elseif ($sub['payment_status'] === 'cancelled'): ?>
                            <i class="fas fa-times-circle"></i>
                            <?php else: ?>
                            <i class="fas fa-minus-circle"></i>
                            <?php endif; ?>
                            <?php echo ucfirst($sub['payment_status']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($sub['auto_renew']): ?>
                        <span class="auto-renew yes"><i class="fas fa-check-circle"></i> Yes</span>
                        <?php else: ?>
                        <span class="auto-renew no"><i class="fas fa-times-circle"></i> No</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-icon view" title="View Details" onclick="viewSubscription(<?php echo $sub['id']; ?>)">
                                <i class="fas fa-eye"></i>
                                <span class="tooltip">View</span>
                            </button>
                            <button class="btn-icon edit" title="Edit" onclick="editSubscription(<?php echo $sub['id']; ?>)">
                                <i class="fas fa-edit"></i>
                                <span class="tooltip">Edit</span>
                            </button>
                            <button class="btn-icon renew" title="Renew" style="color:#10b981;" onclick="renewSubscription(<?php echo $sub['id']; ?>)">
                                <i class="fas fa-sync"></i>
                                <span class="tooltip">Renew</span>
                            </button>
                            <button class="btn-icon delete" title="Delete" style="color:#ef4444;" onclick="deleteSubscription(<?php echo $sub['id']; ?>)">
                                <i class="fas fa-trash"></i>
                                <span class="tooltip">Delete</span>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- ============================================================
        PAGINATION
        ============================================================ -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <div class="pagination-info">
                Showing <strong><?php echo $offset + 1; ?></strong> to 
                <strong><?php echo min($offset + $per_page, $total_count); ?></strong> 
                of <strong><?php echo number_format($total_count); ?></strong> subscriptions
            </div>
            <div class="pagination-links">
                <?php if ($page > 1): ?>
                <a href="?page=1&sort=<?php echo urlencode($sort_by); ?>&order=<?php echo urlencode($sort_order); ?>&search=<?php echo urlencode($search); ?>&plan=<?php echo urlencode($filter_plan); ?>&status=<?php echo urlencode($filter_status); ?>&tenant=<?php echo urlencode($filter_tenant); ?>&billing=<?php echo urlencode($filter_billing); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                    <i class="fas fa-angle-double-left"></i>
                </a>
                <a href="?page=<?php echo $page - 1; ?>&sort=<?php echo urlencode($sort_by); ?>&order=<?php echo urlencode($sort_order); ?>&search=<?php echo urlencode($search); ?>&plan=<?php echo urlencode($filter_plan); ?>&status=<?php echo urlencode($filter_status); ?>&tenant=<?php echo urlencode($filter_tenant); ?>&billing=<?php echo urlencode($filter_billing); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                    <i class="fas fa-angle-left"></i>
                </a>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1) {
                    echo '<span class="ellipsis">…</span>';
                }
                
                for ($i = $start_page; $i <= $end_page; $i++) {
                    $active = $i === $page ? 'active' : '';
                    echo '<a href="?page=' . $i . '&sort=' . urlencode($sort_by) . '&order=' . urlencode($sort_order) . '&search=' . urlencode($search) . '&plan=' . urlencode($filter_plan) . '&status=' . urlencode($filter_status) . '&tenant=' . urlencode($filter_tenant) . '&billing=' . urlencode($filter_billing) . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '" class="' . $active . '">' . $i . '</a>';
                }
                
                if ($end_page < $total_pages) {
                    echo '<span class="ellipsis">…</span>';
                }
                ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&sort=<?php echo urlencode($sort_by); ?>&order=<?php echo urlencode($sort_order); ?>&search=<?php echo urlencode($search); ?>&plan=<?php echo urlencode($filter_plan); ?>&status=<?php echo urlencode($filter_status); ?>&tenant=<?php echo urlencode($filter_tenant); ?>&billing=<?php echo urlencode($filter_billing); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                    <i class="fas fa-angle-right"></i>
                </a>
                <a href="?page=<?php echo $total_pages; ?>&sort=<?php echo urlencode($sort_by); ?>&order=<?php echo urlencode($sort_order); ?>&search=<?php echo urlencode($search); ?>&plan=<?php echo urlencode($filter_plan); ?>&status=<?php echo urlencode($filter_status); ?>&tenant=<?php echo urlencode($filter_tenant); ?>&billing=<?php echo urlencode($filter_billing); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                    <i class="fas fa-angle-double-right"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ============================================================
    ADD/EDIT SUBSCRIPTION MODAL
    ============================================================ -->
    <div class="modal" id="subscriptionModal">
        <div class="modal-overlay" onclick="closeSubscriptionModal()"></div>
        <div class="modal-content wide">
            <div class="modal-header">
                <h2>
                    <i class="fas fa-crown" style="color:#f59e0b;"></i> 
                    <span id="subModalTitle">Add Subscription</span>
                </h2>
                <button class="modal-close" onclick="closeSubscriptionModal()" title="Close (Esc)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="POST" id="subscriptionForm">
                    <input type="hidden" name="action" id="subFormAction" value="add_subscription">
                    <input type="hidden" name="subscription_id" id="subscriptionId" value="0">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="tenant_id">Tenant <span class="required">*</span></label>
                            <select name="tenant_id" id="sub_tenant_id" required class="form-control">
                                <option value="">Select Tenant</option>
                                <?php foreach ($tenants as $tenant): ?>
                                <option value="<?php echo $tenant['id']; ?>">
                                    <?php echo htmlspecialchars($tenant['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="plan">Subscription Plan <span class="required">*</span></label>
                            <select name="plan" id="sub_plan" required class="form-control" onchange="updateSubscriptionAmount()">
                                <option value="">Select Plan</option>
                                <option value="free">Free - ₦0</option>
                                <option value="basic">Basic - ₦50/month</option>
                                <option value="standard">Standard - ₦150/month</option>
                                <option value="premium">Premium - ₦350/month</option>
                                <option value="enterprise">Enterprise - ₦750/month</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="billing_cycle">Billing Cycle <span class="required">*</span></label>
                            <select name="billing_cycle" id="sub_billing_cycle" required class="form-control" onchange="updateSubscriptionAmount()">
                                <option value="">Select Cycle</option>
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="amount">Amount (₦) <span class="required">*</span></label>
                            <input type="number" name="amount" id="sub_amount" required class="form-control" step="0.01" min="0" readonly>
                            <small>Auto-calculated based on plan and cycle</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="currency">Currency <span class="required">*</span></label>
                            <select name="currency" id="sub_currency" required class="form-control">
                                <option value="NGN">NGN - Nigerian Naira</option>
                                <option value="USD">USD - US Dollar</option>
                                <option value="EUR">EUR - Euro</option>
                                <option value="GBP">GBP - British Pound</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="payment_status">Payment Status <span class="required">*</span></label>
                            <select name="payment_status" id="sub_payment_status" required class="form-control">
                                <option value="pending">Pending</option>
                                <option value="paid">Paid</option>
                                <option value="overdue">Overdue</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="refunded">Refunded</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="start_date">Start Date <span class="required">*</span></label>
                            <input type="date" name="start_date" id="sub_start_date" required class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="end_date">End Date <span class="required">*</span></label>
                            <input type="date" name="end_date" id="sub_end_date" required class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="payment_method">Payment Method</label>
                            <select name="payment_method" id="sub_payment_method" class="form-control">
                                <option value="">Select Method</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="debit_card">Debit Card</option>
                                <option value="mobile_money">Mobile Money</option>
                                <option value="paypal">PayPal</option>
                                <option value="cash">Cash</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="transaction_reference">Transaction Reference</label>
                            <input type="text" name="transaction_reference" id="sub_transaction_reference" class="form-control" placeholder="e.g., TXN-2024-001">
                        </div>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" name="auto_renew" id="sub_auto_renew" checked>
                        <div>
                            <label for="sub_auto_renew">Auto Renew</label>
                            <small>Automatically renew subscription when it expires</small>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> <span id="subSubmitText">Add Subscription</span>
                        </button>
                        <button type="button" class="btn-secondary" onclick="closeSubscriptionModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ============================================================
    VIEW SUBSCRIPTION MODAL
    ============================================================ -->
    <div class="modal" id="viewSubscriptionModal">
        <div class="modal-overlay" onclick="closeViewSubscriptionModal()"></div>
        <div class="modal-content" style="max-width:600px;">
            <div class="modal-header">
                <h2><i class="fas fa-file-invoice"></i> Subscription Details</h2>
                <button class="modal-close" onclick="closeViewSubscriptionModal()" title="Close (Esc)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="viewSubscriptionBody">
                <!-- Loaded via AJAX -->
                <div style="text-align:center; padding:40px; color:#6d83a5;">
                    <i class="fas fa-spinner fa-spin" style="font-size:2rem; display:block; margin-bottom:12px;"></i>
                    Loading subscription details...
                </div>
            </div>
        </div>
    </div>
</main>

<!-- ============================================================
STYLES
============================================================ -->
<style>
/* ============================================================
   SUBSCRIPTION STYLES
   ============================================================ */

/* Subscription Stats */
.subscription-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.subscription-stats .stat-card {
    background: white;
    border-radius: 14px;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    border: 1px solid #eef3f8;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    transition: all 0.2s ease;
}

.subscription-stats .stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
}

.subscription-stats .stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.subscription-stats .stat-info {
    flex: 1;
}

.subscription-stats .stat-number {
    font-size: 1.3rem;
    font-weight: 700;
    color: #0b1a33;
    line-height: 1.2;
}

.subscription-stats .stat-label {
    font-size: 0.7rem;
    color: #6d83a5;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

/* Filter Bar - Date Range */
.filter-group.date-range {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 0 12px;
    min-width: 240px;
}

.filter-group.date-range input[type="date"] {
    border: none;
    padding: 10px 0;
    background: transparent;
    font-size: 0.85rem;
    color: #1f3149;
    width: 100%;
    outline: none;
    min-width: 100px;
}

.filter-group.date-range span {
    color: #8b9bb5;
    font-size: 0.8rem;
}

/* Plan Distribution */
.plan-distribution {
    display: grid;
    grid-template-columns: 1fr;
    gap: 8px;
    background: white;
    border-radius: 14px;
    padding: 20px;
    margin-bottom: 24px;
    border: 1px solid #eef3f8;
}

.plan-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 12px;
    border-radius: 8px;
    background: #f8faff;
    position: relative;
    overflow: hidden;
}

.plan-item .plan-bar {
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    opacity: 0.15;
    border-radius: 8px;
    transition: width 0.6s ease;
}

.plan-item .plan-icon {
    position: relative;
    z-index: 1;
    width: 24px;
    color: #4f9cf7;
}

.plan-item.free .plan-bar { background: #9ca3af; }
.plan-item.basic .plan-bar { background: #60a5fa; }
.plan-item.standard .plan-bar { background: #34d399; }
.plan-item.premium .plan-bar { background: #fbbf24; }
.plan-item.enterprise .plan-bar { background: #a78bfa; }

.plan-item .plan-name {
    font-weight: 500;
    color: #1f3149;
    font-size: 0.85rem;
    flex: 1;
    position: relative;
    z-index: 1;
}

.plan-item .plan-count {
    font-weight: 600;
    color: #0b1a33;
    font-size: 0.9rem;
    position: relative;
    z-index: 1;
}

/* Table Styles */
.table-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid #f0f4fa;
    flex-wrap: wrap;
    gap: 12px;
}

.toolbar-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.bulk-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.bulk-actions select {
    padding: 6px 12px;
    border: 1px solid #dce6f0;
    border-radius: 8px;
    font-size: 0.8rem;
    background: white;
    color: #1f3149;
    outline: none;
}

.bulk-actions select:focus {
    border-color: #4f9cf7;
}

.toolbar-right {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.8rem;
    color: #6d83a5;
}

/* Subscription ID */
.subscription-id {
    font-weight: 600;
    color: #4f9cf7;
    font-size: 0.85rem;
}

/* Billing Cycle */
.billing-cycle {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.8rem;
    color: #405473;
    background: #f0f4fa;
    padding: 2px 10px;
    border-radius: 30px;
}

.billing-cycle i {
    font-size: 0.7rem;
    color: #8b9bb5;
}

/* Amount Display */
.amount-display {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.amount-display strong {
    color: #0b1a33;
    font-size: 0.95rem;
}

.amount-display .currency {
    font-size: 0.6rem;
    color: #8b9bb5;
    text-transform: uppercase;
}

/* Auto Renew */
.auto-renew {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.75rem;
    padding: 2px 10px;
    border-radius: 30px;
}

.auto-renew.yes {
    background: #d1fae5;
    color: #065f46;
}

.auto-renew.no {
    background: #f3f4f6;
    color: #4b5563;
}

/* Expiry Warning */
.expiry-warning {
    font-size: 0.6rem;
    color: #ef4444;
    display: block;
}

/* Action Buttons with Tooltips */
.action-buttons {
    display: flex;
    gap: 2px;
    flex-wrap: wrap;
}

.action-buttons .btn-icon {
    width: 32px;
    height: 32px;
    border: none;
    background: transparent;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #6d83a5;
    cursor: pointer;
    transition: all 0.15s;
    font-size: 0.85rem;
    text-decoration: none;
    position: relative;
}

.action-buttons .btn-icon:hover {
    background: #f0f5fe;
    color: #1f3d6b;
    transform: translateY(-1px);
}

.action-buttons .btn-icon .tooltip {
    display: none;
    position: absolute;
    bottom: calc(100% + 8px);
    left: 50%;
    transform: translateX(-50%);
    background: #0b1a33;
    color: white;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.6rem;
    white-space: nowrap;
    z-index: 10;
}

.action-buttons .btn-icon:hover .tooltip {
    display: block;
}

.action-buttons .btn-icon .tooltip::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-top-color: #0b1a33;
}

.action-buttons .btn-icon.view { color: #4f9cf7; }
.action-buttons .btn-icon.view:hover { background: #e8f0fe; }
.action-buttons .btn-icon.edit { color: #8b5cf6; }
.action-buttons .btn-icon.edit:hover { background: #ede9fe; }
.action-buttons .btn-icon.renew { color: #10b981; }
.action-buttons .btn-icon.renew:hover { background: #d1fae5; }
.action-buttons .btn-icon.delete { color: #ef4444; }
.action-buttons .btn-icon.delete:hover { background: #fee2e2; }

/* Modal */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 9999;
}

.modal.active {
    display: block;
    animation: modalFadeIn 0.3s ease;
}

@keyframes modalFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal .modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(11, 26, 51, 0.6);
    backdrop-filter: blur(6px);
}

.modal .modal-content {
    position: relative;
    max-width: 580px;
    width: 95%;
    margin: 50px auto;
    background: white;
    border-radius: 20px;
    box-shadow: 0 40px 80px rgba(0,0,0,0.25);
    max-height: calc(100vh - 100px);
    overflow: hidden;
    animation: modalSlideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.modal .modal-content.wide {
    max-width: 720px;
}

@keyframes modalSlideUp {
    from {
        opacity: 0;
        transform: translateY(30px) scale(0.96);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 28px;
    border-bottom: 1px solid #eef3f8;
    background: linear-gradient(135deg, #f8faff, #f0f4fa);
    position: sticky;
    top: 0;
    z-index: 5;
}

.modal .modal-header h2 {
    font-size: 1.2rem;
    font-weight: 600;
    color: #0b1a33;
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0;
}

.modal .modal-header h2 i {
    color: #4f9cf7;
    font-size: 1.3rem;
}

.modal .modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #8b9bb5;
    cursor: pointer;
    padding: 4px 10px;
    border-radius: 8px;
    transition: all 0.3s ease;
    line-height: 1;
    width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal .modal-close:hover {
    background: #fee2e2;
    color: #ef4444;
    transform: rotate(90deg);
}

.modal .modal-body {
    padding: 28px;
    overflow-y: auto;
    max-height: calc(100vh - 180px);
}

.modal .modal-body::-webkit-scrollbar {
    width: 4px;
}

.modal .modal-body::-webkit-scrollbar-track {
    background: #f0f4fa;
    border-radius: 10px;
}

.modal .modal-body::-webkit-scrollbar-thumb {
    background: #d0dbe8;
    border-radius: 10px;
}

.modal .modal-body::-webkit-scrollbar-thumb:hover {
    background: #b0c0d0;
}

/* Modal Form */
.modal .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
}

.modal .form-group {
    margin-bottom: 0;
}

.modal .form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 500;
    color: #1f3149;
    margin-bottom: 6px;
}

.modal .form-group .required {
    color: #ef4444;
}

.modal .form-group input,
.modal .form-group select {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #dce6f0;
    border-radius: 10px;
    font-size: 0.9rem;
    background: white;
    color: #1f3149;
    transition: 0.15s;
}

.modal .form-group input:focus,
.modal .form-group select:focus {
    outline: none;
    border-color: #4f9cf7;
    box-shadow: 0 0 0 3px rgba(79, 156, 247, 0.1);
}

.modal .form-group input[readonly] {
    background: #f8faff;
    cursor: not-allowed;
}

.modal .form-group small {
    display: block;
    font-size: 0.7rem;
    color: #8b9bb5;
    margin-top: 4px;
}

.checkbox-group {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 0;
}

.checkbox-group input[type="checkbox"] {
    width: 18px;
    height: 18px;
    margin-top: 2px;
    accent-color: #4f9cf7;
    cursor: pointer;
    flex-shrink: 0;
}

.checkbox-group label {
    font-weight: 500;
    color: #1f3149;
    cursor: pointer;
    display: block;
}

.checkbox-group small {
    display: block;
    font-size: 0.7rem;
    color: #8b9bb5;
    font-weight: 400;
}

.modal .form-actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #eef3f8;
}

.modal .form-actions .btn-primary,
.modal .form-actions .btn-secondary {
    padding: 10px 24px;
    font-size: 0.9rem;
    border-radius: 10px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    text-decoration: none;
}

.modal .form-actions .btn-primary {
    background: #4f9cf7;
    color: white;
}

.modal .form-actions .btn-primary:hover {
    background: #3b82d6;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(79, 156, 247, 0.3);
}

.modal .form-actions .btn-secondary {
    background: #f0f5fe;
    color: #1f3d6b;
    border: 1px solid #dce6f0;
}

.modal .form-actions .btn-secondary:hover {
    background: #e5edf9;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-top: 1px solid #f0f4fa;
    flex-wrap: wrap;
    gap: 12px;
}

.pagination .pagination-info {
    font-size: 0.85rem;
    color: #6d83a5;
}

.pagination .pagination-info strong {
    color: #1f3149;
}

.pagination .pagination-links {
    display: flex;
    gap: 4px;
    align-items: center;
}

.pagination .pagination-links a,
.pagination .pagination-links span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 12px;
    border-radius: 8px;
    font-size: 0.85rem;
    color: #405473;
    text-decoration: none;
    transition: all 0.15s;
}

.pagination .pagination-links a:hover {
    background: #f0f5fe;
    color: #4f9cf7;
}

.pagination .pagination-links a.active {
    background: #4f9cf7;
    color: white;
}

.pagination .pagination-links .ellipsis {
    color: #8b9bb5;
}

/* Empty State */
.empty-table {
    text-align: center;
    padding: 60px 20px !important;
    color: #8b9bb5;
}

.empty-table h3 {
    font-size: 1.1rem;
    color: #1f3149;
    margin-bottom: 8px;
}

.empty-table p {
    font-size: 0.9rem;
    margin-bottom: 4px;
}

/* Responsive */
@media (max-width: 1024px) {
    .subscription-stats {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .subscription-stats {
        grid-template-columns: 1fr 1fr;
    }
    
    .filter-group.date-range {
        min-width: 100%;
        flex-wrap: wrap;
    }
    
    .filter-group.date-range input[type="date"] {
        min-width: 120px;
    }
    
    .modal .form-row {
        grid-template-columns: 1fr;
    }
    
    .table-toolbar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .toolbar-left {
        flex-wrap: wrap;
    }
    
    .data-table {
        font-size: 0.8rem;
        min-width: 900px;
    }
    
    .pagination {
        flex-direction: column;
        align-items: center;
    }
}

@media (max-width: 480px) {
    .subscription-stats {
        grid-template-columns: 1fr;
    }
    
    .action-buttons .btn-icon {
        width: 28px;
        height: 28px;
        font-size: 0.75rem;
    }
}
</style>

<!-- ============================================================
JAVASCRIPT
============================================================ -->
<script>
// ============================================================
// TOGGLE CHECKBOXES
// ============================================================
function toggleAllCheckboxes() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.sub-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
}

// ============================================================
// BULK ACTION
// ============================================================
function confirmBulkAction() {
    const bulkAction = document.getElementById('bulkAction');
    const selected = document.querySelectorAll('.sub-checkbox:checked');
    
    if (!bulkAction.value) {
        alert('Please select an action to perform.');
        return false;
    }
    
    if (selected.length === 0) {
        alert('Please select at least one subscription.');
        return false;
    }
    
    const actionNames = {
        'mark_paid': 'mark as paid',
        'mark_overdue': 'mark as overdue',
        'delete': 'permanently delete'
    };
    
    return confirm(`Are you sure you want to ${actionNames[bulkAction.value] || bulkAction.value} ${selected.length} selected subscription(s)?`);
}

// ============================================================
// SUBSCRIPTION MODAL FUNCTIONS
// ============================================================

function showAddSubscription(tenantId = null) {
    const modal = document.getElementById('subscriptionModal');
    modal.classList.add('active');
    document.getElementById('subModalTitle').textContent = 'Add Subscription';
    document.getElementById('subFormAction').value = 'add_subscription';
    document.getElementById('subscriptionId').value = '0';
    document.getElementById('subSubmitText').textContent = 'Add Subscription';
    document.getElementById('subscriptionForm').reset();
    document.getElementById('sub_amount').value = '';
    document.getElementById('sub_payment_status').value = 'pending';
    document.getElementById('sub_auto_renew').checked = true;
    document.getElementById('sub_tenant_id').disabled = false;
    
    // Set default dates
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('sub_start_date').value = today;
    const endDate = new Date();
    endDate.setMonth(endDate.getMonth() + 1);
    document.getElementById('sub_end_date').value = endDate.toISOString().split('T')[0];
    
    if (tenantId) {
        document.getElementById('sub_tenant_id').value = tenantId;
        document.getElementById('sub_tenant_id').disabled = true;
    }
    
    document.body.style.overflow = 'hidden';
}

function editSubscription(id) {
    // Fetch subscription data via AJAX
    fetch(`get-subscription.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const sub = data.subscription;
                const modal = document.getElementById('subscriptionModal');
                modal.classList.add('active');
                document.getElementById('subModalTitle').textContent = 'Edit Subscription';
                document.getElementById('subFormAction').value = 'update_subscription';
                document.getElementById('subscriptionId').value = sub.id;
                document.getElementById('subSubmitText').textContent = 'Update Subscription';
                document.getElementById('sub_tenant_id').value = sub.tenant_id;
                document.getElementById('sub_tenant_id').disabled = true;
                document.getElementById('sub_plan').value = sub.plan;
                document.getElementById('sub_billing_cycle').value = sub.billing_cycle;
                document.getElementById('sub_amount').value = sub.amount;
                document.getElementById('sub_currency').value = sub.currency;
                document.getElementById('sub_payment_status').value = sub.payment_status;
                document.getElementById('sub_start_date').value = sub.start_date;
                document.getElementById('sub_end_date').value = sub.end_date;
                document.getElementById('sub_payment_method').value = sub.payment_method || '';
                document.getElementById('sub_transaction_reference').value = sub.transaction_reference || '';
                document.getElementById('sub_auto_renew').checked = sub.auto_renew == 1;
                document.body.style.overflow = 'hidden';
            } else {
                alert('Failed to load subscription data');
            }
        })
        .catch(error => {
            alert('Error loading subscription data');
            console.error(error);
        });
}

function closeSubscriptionModal() {
    document.getElementById('subscriptionModal').classList.remove('active');
    document.getElementById('sub_tenant_id').disabled = false;
    document.body.style.overflow = '';
}

// ============================================================
// VIEW SUBSCRIPTION
// ============================================================
function viewSubscription(id) {
    const modal = document.getElementById('viewSubscriptionModal');
    const body = document.getElementById('viewSubscriptionBody');
    
    modal.classList.add('active');
    body.innerHTML = '<div style="text-align:center; padding:40px; color:#6d83a5;"><i class="fas fa-spinner fa-spin" style="font-size:2rem; display:block; margin-bottom:12px;"></i> Loading subscription details...</div>';
    document.body.style.overflow = 'hidden';
    
    fetch(`subscription-details.php?id=${id}`)
        .then(response => response.text())
        .then(html => {
            body.innerHTML = html;
        })
        .catch(error => {
            body.innerHTML = '<div style="text-align:center; padding:40px; color:#ef4444;"><i class="fas fa-exclamation-circle" style="font-size:2rem; display:block; margin-bottom:12px;"></i> Failed to load subscription details</div>';
        });
}

function closeViewSubscriptionModal() {
    document.getElementById('viewSubscriptionModal').classList.remove('active');
    document.body.style.overflow = '';
}

// ============================================================
// CALCULATE AMOUNT
// ============================================================
function updateSubscriptionAmount() {
    const plan = document.getElementById('sub_plan').value;
    const cycle = document.getElementById('sub_billing_cycle').value;
    const amountField = document.getElementById('sub_amount');
    
    const planPrices = {
        'free': 0,
        'basic': 50,
        'standard': 150,
        'premium': 350,
        'enterprise': 750
    };
    
    const cycleMultipliers = {
        'monthly': 1,
        'quarterly': 3,
        'yearly': 12
    };
    
    if (plan && cycle) {
        const price = planPrices[plan] || 0;
        const multiplier = cycleMultipliers[cycle] || 1;
        amountField.value = (price * multiplier).toFixed(2);
    } else {
        amountField.value = '';
    }
}

// ============================================================
// SUBSCRIPTION ACTIONS
// ============================================================
function renewSubscription(id) {
    if (confirm('Are you sure you want to renew this subscription?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="renew_subscription">
            <input type="hidden" name="subscription_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteSubscription(id) {
    if (confirm('⚠️ Are you sure you want to delete this subscription? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_subscription">
            <input type="hidden" name="subscription_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// ============================================================
// EXPORT SUBSCRIPTIONS
// ============================================================
function exportSubscriptions() {
    const search = document.querySelector('input[name="search"]')?.value || '';
    const plan = document.querySelector('select[name="plan"]')?.value || '';
    const status = document.querySelector('select[name="status"]')?.value || '';
    const tenant = document.querySelector('select[name="tenant"]')?.value || '';
    const billing = document.querySelector('select[name="billing"]')?.value || '';
    const date_from = document.querySelector('input[name="date_from"]')?.value || '';
    const date_to = document.querySelector('input[name="date_to"]')?.value || '';
    
    window.location.href = `subscriptions-export.php?search=${encodeURIComponent(search)}&plan=${encodeURIComponent(plan)}&status=${encodeURIComponent(status)}&tenant=${encodeURIComponent(tenant)}&billing=${encodeURIComponent(billing)}&date_from=${encodeURIComponent(date_from)}&date_to=${encodeURIComponent(date_to)}`;
}

// ============================================================
// CLOSE MODALS ON ESCAPE
// ============================================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeSubscriptionModal();
        closeViewSubscriptionModal();
    }
});

// ============================================================
// AUTO-SUBMIT ON FILTER CHANGE
// ============================================================
document.querySelectorAll('select[name="plan"], select[name="status"], select[name="tenant"], select[name="billing"]').forEach(select => {
    select.addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
});

// ============================================================
// DATE INPUT AUTO-SUBMIT (with debounce)
// ============================================================
let filterTimeout;
document.querySelectorAll('input[name="date_from"], input[name="date_to"]').forEach(input => {
    input.addEventListener('change', function() {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(() => {
            document.getElementById('filterForm').submit();
        }, 500);
    });
});
</script>

<?php include 'includes/footer.php'; ?>