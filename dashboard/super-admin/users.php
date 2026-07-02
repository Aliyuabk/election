<?php
$page_title = "Manage Users";
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
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $tenant_id = isset($_POST['tenant_id']) ? (int)$_POST['tenant_id'] : 0;
    
    try {
        switch ($action) {
            case 'activate':
                $stmt = $conn->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ? AND deleted_at IS NULL");
                $stmt->execute([$user_id]);
                $message = "User activated successfully.";
                $message_type = 'success';
                break;
                
            case 'suspend':
                $stmt = $conn->prepare("UPDATE users SET status = 'suspended', updated_at = NOW() WHERE id = ? AND deleted_at IS NULL");
                $stmt->execute([$user_id]);
                $message = "User suspended successfully.";
                $message_type = 'success';
                break;
                
            case 'delete':
                $stmt = $conn->prepare("UPDATE users SET deleted_at = NOW(), updated_at = NOW() WHERE id = ?");
                $stmt->execute([$user_id]);
                $message = "User deleted successfully.";
                $message_type = 'success';
                break;
                
            case 'reset_password':
                $new_password = bin2hex(random_bytes(8));
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$password_hash, $user_id]);
                
                // Log activity - FIXED SQL
                $logSql = "INSERT INTO activity_logs (user_id, activity_type, description, ip_address, created_at) 
                          VALUES (?, 'password_reset', ?, ?, NOW())";
                $logStmt = $conn->prepare($logSql);
                $description = "Password reset for user ID: " . $user_id;
                $logStmt->execute([$_SESSION['user_id'] ?? 1, $description, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
                
                $message = "Password reset successfully. New password: <strong>{$new_password}</strong>";
                $message_type = 'info';
                break;
                
            case 'bulk_action':
                $bulk_action = $_POST['bulk_action'] ?? '';
                $user_ids = $_POST['user_ids'] ?? [];
                
                if (!empty($user_ids) && is_array($user_ids)) {
                    $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
                    
                    switch ($bulk_action) {
                        case 'activate':
                            $stmt = $conn->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id IN ($placeholders) AND deleted_at IS NULL");
                            $stmt->execute($user_ids);
                            $message = count($user_ids) . " users activated successfully.";
                            $message_type = 'success';
                            break;
                        case 'suspend':
                            $stmt = $conn->prepare("UPDATE users SET status = 'suspended', updated_at = NOW() WHERE id IN ($placeholders) AND deleted_at IS NULL");
                            $stmt->execute($user_ids);
                            $message = count($user_ids) . " users suspended successfully.";
                            $message_type = 'success';
                            break;
                        case 'delete':
                            $stmt = $conn->prepare("UPDATE users SET deleted_at = NOW(), updated_at = NOW() WHERE id IN ($placeholders)");
                            $stmt->execute($user_ids);
                            $message = count($user_ids) . " users deleted successfully.";
                            $message_type = 'success';
                            break;
                    }
                }
                break;
                
            case 'add_user':
                // Validate required fields
                $first_name = trim($_POST['first_name'] ?? '');
                $last_name = trim($_POST['last_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $role_id = (int)($_POST['role_id'] ?? 0);
                $tenant_id = (int)($_POST['tenant_id'] ?? 0);
                
                if (empty($first_name)) throw new Exception("First name is required.");
                if (empty($last_name)) throw new Exception("Last name is required.");
                if (empty($email) && empty($phone)) throw new Exception("Either email or phone is required.");
                if (!$role_id) throw new Exception("Role is required.");
                if (!$tenant_id) throw new Exception("Tenant is required.");
                
                // Check if email already exists
                if ($email) {
                    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND deleted_at IS NULL");
                    $checkStmt->execute([$email]);
                    if ($checkStmt->fetch()) {
                        throw new Exception("Email already registered.");
                    }
                }
                
                // Check if phone already exists
                if ($phone) {
                    $checkStmt = $conn->prepare("SELECT id FROM users WHERE phone = ? AND deleted_at IS NULL");
                    $checkStmt->execute([$phone]);
                    if ($checkStmt->fetch()) {
                        throw new Exception("Phone number already registered.");
                    }
                }
                
                // Generate user code
                $codeStmt = $conn->prepare("SELECT MAX(CAST(SUBSTRING(user_code, 4) AS UNSIGNED)) as max_code FROM users WHERE user_code LIKE 'USR%'");
                $codeStmt->execute();
                $maxCode = $codeStmt->fetch()['max_code'] ?? 0;
                $user_code = 'USR' . str_pad($maxCode + 1, 6, '0', STR_PAD_LEFT);
                
                // Generate random password
                $password = bin2hex(random_bytes(6));
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert user
                $sql = "INSERT INTO users (
                            user_code, tenant_id, role_id, 
                            first_name, last_name, 
                            email, phone, 
                            password_hash, status, 
                            created_by, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $user_code,
                    $tenant_id,
                    $role_id,
                    $first_name,
                    $last_name,
                    $email ?: null,
                    $phone,
                    $password_hash,
                    $_SESSION['user_id'] ?? 1
                ]);
                
                $newUserId = $conn->lastInsertId();
                
                // Log activity - FIXED SQL
                $logSql = "INSERT INTO activity_logs (user_id, tenant_id, activity_type, description, ip_address, created_at) 
                          VALUES (?, ?, 'user_created', ?, ?, NOW())";
                $logStmt = $conn->prepare($logSql);
                $description = "New user created: " . $first_name . ' ' . $last_name;
                $logStmt->execute([
                    $_SESSION['user_id'] ?? 1,
                    $tenant_id,
                    $description,
                    $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                ]);
                
                $message = "User created successfully. Password: <strong>{$password}</strong>";
                $message_type = 'success';
                break;
                
            case 'update_user':
                // Update user
                $first_name = trim($_POST['first_name'] ?? '');
                $last_name = trim($_POST['last_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $role_id = (int)($_POST['role_id'] ?? 0);
                $tenant_id = (int)($_POST['tenant_id'] ?? 0);
                $status = $_POST['status'] ?? 'active';
                
                if (empty($first_name)) throw new Exception("First name is required.");
                if (empty($last_name)) throw new Exception("Last name is required.");
                if (empty($email) && empty($phone)) throw new Exception("Either email or phone is required.");
                if (!$role_id) throw new Exception("Role is required.");
                if (!$tenant_id) throw new Exception("Tenant is required.");
                
                // Check if email already exists for other user
                if ($email) {
                    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? AND deleted_at IS NULL");
                    $checkStmt->execute([$email, $user_id]);
                    if ($checkStmt->fetch()) {
                        throw new Exception("Email already registered to another user.");
                    }
                }
                
                // Check if phone already exists for other user
                if ($phone) {
                    $checkStmt = $conn->prepare("SELECT id FROM users WHERE phone = ? AND id != ? AND deleted_at IS NULL");
                    $checkStmt->execute([$phone, $user_id]);
                    if ($checkStmt->fetch()) {
                        throw new Exception("Phone number already registered to another user.");
                    }
                }
                
                $sql = "UPDATE users SET 
                        first_name = ?, last_name = ?, email = ?, phone = ?,
                        role_id = ?, tenant_id = ?, status = ?,
                        updated_at = NOW()
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$first_name, $last_name, $email ?: null, $phone, $role_id, $tenant_id, $status, $user_id]);
                
                $message = "User updated successfully.";
                $message_type = 'success';
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
$filter_role = $_GET['role'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_tenant = $_GET['tenant'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_order = $_GET['order'] ?? 'DESC';
$per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// ============================================================
// BUILD QUERY
// ============================================================
$base_query = "FROM users u
               LEFT JOIN tenants t ON u.tenant_id = t.id
               LEFT JOIN roles r ON u.role_id = r.id
               WHERE u.deleted_at IS NULL";

$params = [];

if ($search) {
    $base_query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.user_code LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
}

if ($filter_role) {
    $base_query .= " AND u.role_id = ?";
    $params[] = $filter_role;
}

if ($filter_status) {
    $base_query .= " AND u.status = ?";
    $params[] = $filter_status;
}

if ($filter_tenant) {
    $base_query .= " AND u.tenant_id = ?";
    $params[] = $filter_tenant;
}

// Get total count
$count_query = "SELECT COUNT(*) as total " . $base_query;
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute($params);
$total_count = $count_stmt->fetch()['total'];
$total_pages = ceil($total_count / $per_page);

// Get paginated results
$query = "SELECT 
            u.*,
            t.name as tenant_name,
            t.slug as tenant_slug,
            r.name as role_name,
            r.level as role_level,
            r.slug as role_slug,
            (SELECT COUNT(*) FROM user_sessions WHERE user_id = u.id AND is_active = 1 AND last_activity_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)) as is_online
          " . $base_query . "
          ORDER BY u.$sort_by $sort_order 
          LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;

$stmt = $conn->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// ============================================================
// GET FILTER OPTIONS
// ============================================================
$roles = $conn->query("SELECT id, name, slug, level FROM roles WHERE is_active = 1 ORDER BY name")->fetchAll();
$tenants = $conn->query("SELECT id, name, slug FROM tenants WHERE deleted_at IS NULL ORDER BY name")->fetchAll();

// ============================================================
// GET STATS
// ============================================================
$stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as new_today
    FROM users 
    WHERE deleted_at IS NULL
")->fetch();

// Get online users count
$online_count = $conn->query("
    SELECT COUNT(DISTINCT user_id) as online 
    FROM user_sessions 
    WHERE is_active = 1 
    AND last_activity_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
")->fetch()['online'];

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
                <i class="fas fa-users" style="color:#4f9cf7;"></i>
                Manage Users
                <span class="page-badge"><?php echo number_format($total_count); ?></span>
            </h1>
            <p class="subtitle">Manage all users across the platform</p>
        </div>
        <div class="header-actions">
            <button class="btn-secondary" onclick="exportUsers()">
                <i class="fas fa-file-export"></i> Export
            </button>
            <button class="btn-primary" onclick="showAddUser()">
                <i class="fas fa-user-plus"></i> Add User
            </button>
        </div>
    </div>

    <!-- ============================================================
    ALERTS
    ============================================================ -->
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type ?: 'success'; ?>">
        <i class="fas fa-<?php echo $message_type === 'error' ? 'exclamation-circle' : ($message_type === 'info' ? 'info-circle' : 'check-circle'); ?>"></i>
        <?php echo $message; ?>
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
    <div class="user-stats">
        <div class="stat-card">
            <div class="stat-icon" style="background:#e8f0fe; color:#4f9cf7;">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo number_format($stats['total'] ?? 0); ?></div>
                <div class="stat-label">Total Users</div>
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
            <div class="stat-icon" style="background:#dbeafe; color:#4f9cf7;">
                <i class="fas fa-wifi"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo number_format($online_count ?? 0); ?></div>
                <div class="stat-label">Online Now</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef3c7; color:#f59e0b;">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo number_format($stats['new_today'] ?? 0); ?></div>
                <div class="stat-label">New Today</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fee2e2; color:#ef4444;">
                <i class="fas fa-pause-circle"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo number_format($stats['suspended'] ?? 0); ?></div>
                <div class="stat-label">Suspended</div>
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
                <input type="text" name="search" placeholder="Search users by name, email, phone..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="filter-group">
                <i class="fas fa-user-tag"></i>
                <select name="role">
                    <option value="">All Roles</option>
                    <?php foreach ($roles as $role): ?>
                    <option value="<?php echo $role['id']; ?>" <?php echo $filter_role == $role['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($role['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
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
                <i class="fas fa-circle"></i>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="suspended" <?php echo $filter_status === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="archived" <?php echo $filter_status === 'archived' ? 'selected' : ''; ?>>Archived</option>
                </select>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn-primary"><i class="fas fa-filter"></i> Filter</button>
                <a href="users.php" class="btn-secondary"><i class="fas fa-times"></i> Clear</a>
            </div>
        </form>
    </div>

    <!-- ============================================================
    USERS TABLE
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
                            <option value="activate">Activate</option>
                            <option value="suspend">Suspend</option>
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

        <table class="data-table" id="userTable">
            <thead>
                <tr>
                    <th style="width:36px;">
                        <input type="checkbox" id="selectAllHeader" onchange="toggleAllCheckboxes()">
                    </th>
                    <th style="width:50px;">
                        <a href="?sort=id&order=<?php echo $sort_order === 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($filter_role); ?>&tenant=<?php echo urlencode($filter_tenant); ?>&status=<?php echo urlencode($filter_status); ?>&page=<?php echo $page; ?>">
                            ID
                            <?php if ($sort_by === 'id'): ?>
                            <i class="fas fa-sort-<?php echo strtolower($sort_order); ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>User</th>
                    <th>Contact</th>
                    <th>Tenant</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th style="width:80px;">
                        <a href="?sort=last_login_at&order=<?php echo $sort_order === 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($filter_role); ?>&tenant=<?php echo urlencode($filter_tenant); ?>&status=<?php echo urlencode($filter_status); ?>&page=<?php echo $page; ?>">
                            Last Login
                            <?php if ($sort_by === 'last_login_at'): ?>
                            <i class="fas fa-sort-<?php echo strtolower($sort_order); ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th style="width:200px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="9" class="empty-table">
                        <i class="fas fa-users" style="font-size:3rem; color:#dce6f0; display:block; margin-bottom:16px;"></i>
                        <h3>No users found</h3>
                        <p>Start by adding your first user.</p>
                        <button onclick="showAddUser()" class="btn-primary" style="margin-top:12px;">
                            <i class="fas fa-user-plus"></i> Add User
                        </button>
                    </td>
                </tr>
                <?php endif; ?>
                
                <?php foreach ($users as $user): ?>
                <tr>
                    <td>
                        <div class="checkbox-wrapper">
                            <input type="checkbox" name="user_ids[]" value="<?php echo $user['id']; ?>" class="user-checkbox">
                        </div>
                    </td>
                    <td>
                        <span style="font-weight:600; color:#4f9cf7;">#<?php echo $user['id']; ?></span>
                        <span style="font-size:0.65rem; color:#8b9bb5; display:block;"><?php echo htmlspecialchars($user['user_code']); ?></span>
                    </td>
                    <td>
                        <div class="user-cell">
                            <div class="user-avatar">
                                <?php if ($user['photograph_url']): ?>
                                <img src="<?php echo htmlspecialchars($user['photograph_url']); ?>" alt="">
                                <?php else: ?>
                                <div class="avatar-placeholder" style="background: <?php echo $user['is_online'] ? '#10b981' : '#8b9bb5'; ?>;">
                                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($user['is_online']): ?>
                                <span class="online-dot"></span>
                                <?php endif; ?>
                            </div>
                            <div class="user-info">
                                <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                <div class="user-meta">
                                    <span class="user-code"><?php echo htmlspecialchars($user['user_code']); ?></span>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="contact-info">
                            <?php if ($user['email']): ?>
                            <div class="contact-item">
                                <i class="fas fa-envelope" style="color:#6d83a5; font-size:0.7rem;"></i>
                                <span><?php echo htmlspecialchars($user['email']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($user['phone']): ?>
                            <div class="contact-item">
                                <i class="fas fa-phone" style="color:#6d83a5; font-size:0.7rem;"></i>
                                <span><?php echo htmlspecialchars($user['phone']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($user['tenant_id']): ?>
                        <div class="tenant-badge">
                            <i class="fas fa-building" style="font-size:0.7rem;"></i>
                            <?php echo htmlspecialchars($user['tenant_name']); ?>
                        </div>
                        <?php else: ?>
                        <span style="color:#8b9bb5; font-size:0.8rem;">System</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="role-badge <?php echo $user['role_level'] ?? 'user'; ?>">
                            <i class="fas fa-user-shield"></i>
                            <?php echo htmlspecialchars($user['role_name'] ?? 'Unknown'); ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge <?php echo $user['status']; ?>">
                            <?php if ($user['status'] === 'active'): ?>
                            <i class="fas fa-check-circle"></i>
                            <?php elseif ($user['status'] === 'suspended'): ?>
                            <i class="fas fa-pause-circle"></i>
                            <?php elseif ($user['status'] === 'pending'): ?>
                            <i class="fas fa-clock"></i>
                            <?php else: ?>
                            <i class="fas fa-minus-circle"></i>
                            <?php endif; ?>
                            <?php echo ucfirst($user['status']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($user['last_login_at']): ?>
                        <span style="font-size:0.8rem;">
                            <?php echo date('M d, Y', strtotime($user['last_login_at'])); ?>
                        </span>
                        <span style="font-size:0.6rem; color:#8b9bb5; display:block;">
                            <?php echo date('H:i:s', strtotime($user['last_login_at'])); ?>
                        </span>
                        <?php else: ?>
                        <span style="color:#8b9bb5; font-size:0.8rem;">Never</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-icon view" title="View Details" onclick="viewUser(<?php echo $user['id']; ?>)">
                                <i class="fas fa-eye"></i>
                                <span class="tooltip">View</span>
                            </button>
                            <button class="btn-icon edit" title="Edit" onclick="editUser(<?php echo $user['id']; ?>)">
                                <i class="fas fa-edit"></i>
                                <span class="tooltip">Edit</span>
                            </button>
                            
                            <?php if ($user['status'] === 'suspended'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <input type="hidden" name="action" value="activate">
                                <button type="submit" class="btn-icon activate" title="Activate">
                                    <i class="fas fa-check-circle"></i>
                                    <span class="tooltip">Activate</span>
                                </button>
                            </form>
                            <?php elseif ($user['status'] === 'active'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <input type="hidden" name="action" value="suspend">
                                <button type="submit" class="btn-icon suspend" title="Suspend">
                                    <i class="fas fa-pause-circle"></i>
                                    <span class="tooltip">Suspend</span>
                                </button>
                            </form>
                            <?php endif; ?>
                            
                            <button class="btn-icon reset" title="Reset Password" onclick="resetPassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>')">
                                <i class="fas fa-key"></i>
                                <span class="tooltip">Reset Password</span>
                            </button>
                            
                            <form method="POST" style="display:inline;" onsubmit="return confirmDelete('<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>');">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn-icon delete" title="Delete">
                                    <i class="fas fa-trash"></i>
                                    <span class="tooltip">Delete</span>
                                </button>
                            </form>
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
                of <strong><?php echo number_format($total_count); ?></strong> users
            </div>
            <div class="pagination-links">
                <?php if ($page > 1): ?>
                <a href="?page=1&sort=<?php echo urlencode($sort_by); ?>&order=<?php echo urlencode($sort_order); ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($filter_role); ?>&tenant=<?php echo urlencode($filter_tenant); ?>&status=<?php echo urlencode($filter_status); ?>">
                    <i class="fas fa-angle-double-left"></i>
                </a>
                <a href="?page=<?php echo $page - 1; ?>&sort=<?php echo urlencode($sort_by); ?>&order=<?php echo urlencode($sort_order); ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($filter_role); ?>&tenant=<?php echo urlencode($filter_tenant); ?>&status=<?php echo urlencode($filter_status); ?>">
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
                    echo '<a href="?page=' . $i . '&sort=' . urlencode($sort_by) . '&order=' . urlencode($sort_order) . '&search=' . urlencode($search) . '&role=' . urlencode($filter_role) . '&tenant=' . urlencode($filter_tenant) . '&status=' . urlencode($filter_status) . '" class="' . $active . '">' . $i . '</a>';
                }
                
                if ($end_page < $total_pages) {
                    echo '<span class="ellipsis">…</span>';
                }
                ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&sort=<?php echo urlencode($sort_by); ?>&order=<?php echo urlencode($sort_order); ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($filter_role); ?>&tenant=<?php echo urlencode($filter_tenant); ?>&status=<?php echo urlencode($filter_status); ?>">
                    <i class="fas fa-angle-right"></i>
                </a>
                <a href="?page=<?php echo $total_pages; ?>&sort=<?php echo urlencode($sort_by); ?>&order=<?php echo urlencode($sort_order); ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($filter_role); ?>&tenant=<?php echo urlencode($filter_tenant); ?>&status=<?php echo urlencode($filter_status); ?>">
                    <i class="fas fa-angle-double-right"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ============================================================
    ADD/EDIT USER MODAL
    ============================================================ -->
    <div class="modal" id="userModal">
        <div class="modal-overlay" onclick="closeUserModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">
                    <i class="fas fa-user-plus"></i> 
                    <span id="modalTitleText">Add User</span>
                </h2>
                <button class="modal-close" onclick="closeUserModal()" title="Close (Esc)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="POST" id="userForm">
                    <input type="hidden" name="action" id="formAction" value="add_user">
                    <input type="hidden" name="user_id" id="userId" value="0">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name <span class="required">*</span></label>
                            <input type="text" name="first_name" id="first_name" required class="form-control" placeholder="Enter first name">
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name <span class="required">*</span></label>
                            <input type="text" name="last_name" id="last_name" required class="form-control" placeholder="Enter last name">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" name="email" id="email" class="form-control" placeholder="john.doe@example.com">
                            <small>Either email or phone is required</small>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" name="phone" id="phone" class="form-control" placeholder="+2348005555555">
                            <small>Either email or phone is required</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="tenant_id">Tenant <span class="required">*</span></label>
                            <select name="tenant_id" id="tenant_id" required class="form-control">
                                <option value="">Select Tenant</option>
                                <?php foreach ($tenants as $tenant): ?>
                                <option value="<?php echo $tenant['id']; ?>">
                                    <?php echo htmlspecialchars($tenant['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="role_id">Role <span class="required">*</span></label>
                            <select name="role_id" id="role_id" required class="form-control">
                                <option value="">Select Role</option>
                                <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>">
                                    <?php echo htmlspecialchars($role['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="pending">Pending</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> <span id="submitText">Add User</span>
                        </button>
                        <button type="button" class="btn-secondary" onclick="closeUserModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ============================================================
    VIEW USER MODAL
    ============================================================ -->
    <div class="modal" id="viewUserModal">
        <div class="modal-overlay" onclick="closeViewUserModal()"></div>
        <div class="modal-content" style="max-width:600px;">
            <div class="modal-header">
                <h2><i class="fas fa-user-circle"></i> User Details</h2>
                <button class="modal-close" onclick="closeViewUserModal()">&times;</button>
            </div>
            <div class="modal-body" id="viewUserBody">
                <!-- Loaded via AJAX -->
            </div>
        </div>
    </div>
</main>

<!-- ============================================================
PROFESSIONAL CSS
============================================================ -->
<style>
/* ============================================================
   USER MANAGEMENT - PROFESSIONAL STYLES
   ============================================================ */

/* Stats Cards */
.user-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.user-stats .stat-card {
    background: white;
    border-radius: 14px;
    padding: 18px 22px;
    display: flex;
    align-items: center;
    gap: 16px;
    border: 1px solid #eef3f8;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.user-stats .stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: #4f9cf7;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.user-stats .stat-card:hover::before {
    opacity: 1;
}

.user-stats .stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.08);
}

.user-stats .stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    flex-shrink: 0;
    transition: transform 0.3s ease;
}

.user-stats .stat-card:hover .stat-icon {
    transform: scale(1.05) rotate(-3deg);
}

.user-stats .stat-info {
    flex: 1;
}

.user-stats .stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: #0b1a33;
    line-height: 1.2;
}

.user-stats .stat-label {
    font-size: 0.7rem;
    color: #6d83a5;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Filter Bar */
.filter-bar {
    background: white;
    border-radius: 14px;
    padding: 18px 22px;
    margin-bottom: 24px;
    border: 1px solid #eef3f8;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
}

.filter-form {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #f8faff;
    border: 1.5px solid #e8edf4;
    border-radius: 10px;
    padding: 0 14px;
    transition: all 0.3s ease;
    flex: 1;
    min-width: 160px;
}

.filter-group:focus-within {
    border-color: #4f9cf7;
    box-shadow: 0 0 0 4px rgba(79, 156, 247, 0.08);
    background: white;
}

.filter-group i {
    color: #8b9bb5;
    font-size: 0.85rem;
    transition: color 0.3s ease;
}

.filter-group:focus-within i {
    color: #4f9cf7;
}

.filter-group input,
.filter-group select {
    border: none;
    padding: 11px 0;
    background: transparent;
    font-size: 0.85rem;
    color: #1f3149;
    width: 100%;
    outline: none;
}

.filter-group select {
    cursor: pointer;
    appearance: none;
    padding-right: 20px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%238b9bb5' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right center;
}

.filter-actions {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-actions .btn-primary,
.filter-actions .btn-secondary {
    padding: 10px 20px;
    font-size: 0.85rem;
    border-radius: 10px;
    font-weight: 500;
    transition: all 0.3s ease;
}

/* Table Container */
.table-container {
    background: white;
    border-radius: 14px;
    overflow: hidden;
    border: 1px solid #eef3f8;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
}

.table-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 22px;
    border-bottom: 1px solid #f0f4fa;
    flex-wrap: wrap;
    gap: 12px;
    background: #fafcff;
}

.toolbar-left {
    display: flex;
    align-items: center;
    gap: 14px;
}

.bulk-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.bulk-actions select {
    padding: 6px 14px;
    border: 1px solid #dce6f0;
    border-radius: 8px;
    font-size: 0.8rem;
    background: white;
    color: #1f3149;
    outline: none;
    cursor: pointer;
    transition: border-color 0.3s ease;
}

.bulk-actions select:focus {
    border-color: #4f9cf7;
}

.toolbar-right {
    display: flex;
    align-items: center;
    gap: 16px;
    font-size: 0.8rem;
    color: #6d83a5;
}

.toolbar-right span {
    display: flex;
    align-items: center;
    gap: 6px;
}

/* Data Table */
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.data-table thead {
    background: #f8faff;
    border-bottom: 2px solid #eef3f8;
}

.data-table thead th {
    padding: 14px 18px;
    text-align: left;
    font-weight: 600;
    color: #405473;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    position: sticky;
    top: 0;
    background: #f8faff;
    z-index: 2;
}

.data-table thead th a {
    color: inherit;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: color 0.2s ease;
}

.data-table thead th a:hover {
    color: #4f9cf7;
}

.data-table tbody tr {
    transition: background 0.2s ease;
    border-bottom: 1px solid #f5f8fc;
}

.data-table tbody tr:last-child {
    border-bottom: none;
}

.data-table tbody tr:hover {
    background: #f8faff;
}

.data-table tbody td {
    padding: 14px 18px;
    vertical-align: middle;
    color: #1f3149;
}

/* User Cell */
.user-cell {
    display: flex;
    align-items: center;
    gap: 14px;
}

.user-avatar {
    position: relative;
    width: 42px;
    height: 42px;
    flex-shrink: 0;
}

.user-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #eef3f8;
    transition: border-color 0.3s ease;
}

.user-avatar img:hover {
    border-color: #4f9cf7;
}

.avatar-placeholder {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
    border: 2px solid #eef3f8;
    transition: all 0.3s ease;
}

.avatar-placeholder:hover {
    transform: scale(1.05);
}

.online-dot {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 13px;
    height: 13px;
    border-radius: 50%;
    background: #10b981;
    border: 2.5px solid white;
    box-shadow: 0 0 10px rgba(16, 185, 129, 0.5);
    animation: pulse-dot 2s infinite;
}

@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.6; transform: scale(0.85); }
}

.user-info {
    flex: 1;
    min-width: 0;
}

.user-name {
    font-weight: 600;
    color: #0b1a33;
    font-size: 0.9rem;
    transition: color 0.2s ease;
}

tr:hover .user-name {
    color: #4f9cf7;
}

.user-meta {
    font-size: 0.7rem;
    color: #8b9bb5;
    margin-top: 1px;
}

.user-code {
    background: #f0f4fa;
    padding: 1px 10px;
    border-radius: 30px;
    font-size: 0.65rem;
    font-weight: 500;
}

/* Contact Info */
.contact-info {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.8rem;
    color: #1f3149;
}

.contact-item i {
    color: #6d83a5;
    font-size: 0.7rem;
    width: 14px;
}

.contact-item span {
    word-break: break-all;
}

/* Tenant Badge */
.tenant-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 14px;
    background: #f0f4fa;
    border-radius: 30px;
    font-size: 0.75rem;
    color: #1f3149;
    font-weight: 500;
    transition: all 0.3s ease;
}

.tenant-badge:hover {
    background: #e8edf4;
    transform: translateX(2px);
}

.tenant-badge i {
    font-size: 0.7rem;
    color: #6d83a5;
}

/* Role Badge */
.role-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.7rem;
    padding: 4px 14px;
    border-radius: 30px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.role-badge:hover {
    transform: scale(1.05);
}

.role-badge.super_admin { background: #ede9fe; color: #5b21b6; }
.role-badge.client_admin { background: #dbeafe; color: #1e40af; }
.role-badge.national { background: #d1fae5; color: #065f46; }
.role-badge.state { background: #fef3c7; color: #92400e; }
.role-badge.lga { background: #fce4ec; color: #c62828; }
.role-badge.ward { background: #e8f0fe; color: #4f9cf7; }
.role-badge.pu_agent { background: #f3e8ff; color: #7c3aed; }
.role-badge.party_agent { background: #fef3c7; color: #92400e; }
.role-badge.observer { background: #e0f7fa; color: #00838f; }
.role-badge.default { background: #f5f5f5; color: #616161; }

/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.7rem;
    padding: 4px 14px;
    border-radius: 30px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.status-badge:hover {
    transform: scale(1.05);
}

.status-badge.active { background: #d1fae5; color: #065f46; }
.status-badge.active i { color: #10b981; }
.status-badge.suspended { background: #fef3c7; color: #92400e; }
.status-badge.suspended i { color: #f59e0b; }
.status-badge.pending { background: #dbeafe; color: #1e40af; }
.status-badge.pending i { color: #4f9cf7; }
.status-badge.archived { background: #f3f4f6; color: #4b5563; }
.status-badge.archived i { color: #6b7280; }

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 2px;
    flex-wrap: wrap;
}

.action-buttons .btn-icon {
    width: 34px;
    height: 34px;
    border: none;
    background: transparent;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #6d83a5;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.85rem;
    text-decoration: none;
    position: relative;
}

.action-buttons .btn-icon:hover {
    background: #f0f5fe;
    color: #1f3d6b;
    transform: translateY(-2px);
}

.action-buttons .btn-icon .tooltip {
    display: none;
    position: absolute;
    bottom: calc(100% + 10px);
    left: 50%;
    transform: translateX(-50%);
    background: #0b1a33;
    color: white;
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 0.6rem;
    white-space: nowrap;
    z-index: 10;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.action-buttons .btn-icon:hover .tooltip {
    display: block;
    animation: fadeInUp 0.2s ease;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateX(-50%) translateY(5px); }
    to { opacity: 1; transform: translateX(-50%) translateY(0); }
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
.action-buttons .btn-icon.activate { color: #10b981; }
.action-buttons .btn-icon.activate:hover { background: #d1fae5; }
.action-buttons .btn-icon.suspend { color: #f59e0b; }
.action-buttons .btn-icon.suspend:hover { background: #fef3c7; }
.action-buttons .btn-icon.reset { color: #8b5cf6; }
.action-buttons .btn-icon.reset:hover { background: #ede9fe; }
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
    font-weight: 600;
    color: #1f3149;
    margin-bottom: 6px;
}

.modal .form-group .required {
    color: #ef4444;
    margin-left: 2px;
}

.modal .form-group .form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid #dce6f0;
    border-radius: 10px;
    font-size: 0.9rem;
    background: white;
    color: #1f3149;
    transition: all 0.3s ease;
}

.modal .form-group .form-control:focus {
    outline: none;
    border-color: #4f9cf7;
    box-shadow: 0 0 0 4px rgba(79, 156, 247, 0.08);
}

.modal .form-group .form-control:disabled {
    background: #f8faff;
    cursor: not-allowed;
}

.modal .form-group select.form-control {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%238b9bb5' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 36px;
}

.modal .form-group small {
    display: block;
    font-size: 0.7rem;
    color: #8b9bb5;
    margin-top: 4px;
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
    padding: 10px 28px;
    font-size: 0.9rem;
    border-radius: 10px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
    text-decoration: none;
}

.modal .form-actions .btn-primary {
    background: #4f9cf7;
    color: white;
}

.modal .form-actions .btn-primary:hover {
    background: #3b82d6;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(79, 156, 247, 0.3);
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
    padding: 16px 22px;
    border-top: 1px solid #f0f4fa;
    flex-wrap: wrap;
    gap: 12px;
    background: #fafcff;
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
    min-width: 38px;
    height: 38px;
    padding: 0 14px;
    border-radius: 8px;
    font-size: 0.85rem;
    color: #405473;
    text-decoration: none;
    transition: all 0.2s ease;
    font-weight: 500;
}

.pagination .pagination-links a:hover {
    background: #f0f5fe;
    color: #4f9cf7;
}

.pagination .pagination-links a.active {
    background: #4f9cf7;
    color: white;
    box-shadow: 0 4px 12px rgba(79, 156, 247, 0.3);
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
    font-size: 1.2rem;
    color: #1f3149;
    margin-bottom: 8px;
}

.empty-table p {
    font-size: 0.9rem;
    margin-bottom: 4px;
}

/* Responsive */
@media (max-width: 1024px) {
    .user-stats {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .user-stats {
        grid-template-columns: 1fr 1fr;
    }
    
    .filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-group {
        width: 100%;
        min-width: unset;
    }
    
    .filter-actions {
        flex-direction: column;
    }
    
    .filter-actions .btn-primary,
    .filter-actions .btn-secondary {
        width: 100%;
        justify-content: center;
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
        min-width: 800px;
    }
    
    .pagination {
        flex-direction: column;
        align-items: center;
    }
}

@media (max-width: 480px) {
    .user-stats {
        grid-template-columns: 1fr;
    }
    
    .action-buttons .btn-icon {
        width: 30px;
        height: 30px;
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
    const checkboxes = document.querySelectorAll('.user-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
}

// ============================================================
// BULK ACTION
// ============================================================
function confirmBulkAction() {
    const bulkAction = document.getElementById('bulkAction');
    const selected = document.querySelectorAll('.user-checkbox:checked');
    
    if (!bulkAction.value) {
        alert('Please select an action to perform.');
        return false;
    }
    
    if (selected.length === 0) {
        alert('Please select at least one user.');
        return false;
    }
    
    const actionNames = {
        'activate': 'activate',
        'suspend': 'suspend',
        'delete': 'permanently delete'
    };
    
    return confirm(`Are you sure you want to ${actionNames[bulkAction.value] || bulkAction.value} ${selected.length} selected user(s)?`);
}

// ============================================================
// VIEW USER
// ============================================================
function viewUser(userId) {
    const modal = document.getElementById('viewUserModal');
    const body = document.getElementById('viewUserBody');
    
    modal.classList.add('active');
    body.innerHTML = '<div style="text-align:center; padding:40px; color:#6d83a5;"><i class="fas fa-spinner fa-spin" style="font-size:2rem; display:block; margin-bottom:12px;"></i> Loading user details...</div>';
    document.body.style.overflow = 'hidden';
    
    fetch(`user-details.php?id=${userId}`)
        .then(response => response.text())
        .then(html => {
            body.innerHTML = html;
        })
        .catch(error => {
            body.innerHTML = '<div style="text-align:center; padding:40px; color:#ef4444;"><i class="fas fa-exclamation-circle" style="font-size:2rem; display:block; margin-bottom:12px;"></i> Failed to load user details</div>';
        });
}

function closeViewUserModal() {
    document.getElementById('viewUserModal').classList.remove('active');
    document.body.style.overflow = '';
}

// ============================================================
// ADD/EDIT USER
// ============================================================
function showAddUser() {
    const modal = document.getElementById('userModal');
    modal.classList.add('active');
    document.getElementById('modalTitleText').textContent = 'Add User';
    document.getElementById('formAction').value = 'add_user';
    document.getElementById('userId').value = '0';
    document.getElementById('submitText').textContent = 'Add User';
    document.getElementById('userForm').reset();
    document.getElementById('status').value = 'active';
    document.body.style.overflow = 'hidden';
}

function editUser(userId) {
    // Fetch user data via AJAX
    fetch(`user-details.php?id=${userId}&format=json`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const user = data.user;
                const modal = document.getElementById('userModal');
                modal.classList.add('active');
                document.getElementById('modalTitleText').textContent = 'Edit User';
                document.getElementById('formAction').value = 'update_user';
                document.getElementById('userId').value = user.id;
                document.getElementById('submitText').textContent = 'Update User';
                
                document.getElementById('first_name').value = user.first_name;
                document.getElementById('last_name').value = user.last_name;
                document.getElementById('email').value = user.email || '';
                document.getElementById('phone').value = user.phone || '';
                document.getElementById('tenant_id').value = user.tenant_id || '';
                document.getElementById('role_id').value = user.role_id || '';
                document.getElementById('status').value = user.status || 'active';
                document.body.style.overflow = 'hidden';
            } else {
                alert('Failed to load user data');
            }
        })
        .catch(error => {
            alert('Error loading user data');
            console.error(error);
        });
}

function closeUserModal() {
    document.getElementById('userModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Close modal on escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeUserModal();
        closeViewUserModal();
    }
});

// ============================================================
// RESET PASSWORD
// ============================================================
function resetPassword(userId, userName) {
    if (confirm(`Are you sure you want to reset the password for "${userName}"?\n\nA new random password will be generated and displayed.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="user_id" value="${userId}">
            <input type="hidden" name="action" value="reset_password">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// ============================================================
// CONFIRM DELETE
// ============================================================
function confirmDelete(userName) {
    return confirm(`⚠️ Are you sure you want to permanently delete "${userName}"?\n\nThis action cannot be undone.`);
}

// ============================================================
// EXPORT USERS
// ============================================================
function exportUsers() {
    const search = document.querySelector('input[name="search"]')?.value || '';
    const role = document.querySelector('select[name="role"]')?.value || '';
    const tenant = document.querySelector('select[name="tenant"]')?.value || '';
    const status = document.querySelector('select[name="status"]')?.value || '';
    
    window.location.href = `users-export.php?search=${encodeURIComponent(search)}&role=${encodeURIComponent(role)}&tenant=${encodeURIComponent(tenant)}&status=${encodeURIComponent(status)}`;
}

// ============================================================
// AUTO-SUBMIT ON FILTER CHANGE
// ============================================================
document.querySelectorAll('select[name="role"], select[name="tenant"], select[name="status"]').forEach(select => {
    select.addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
});

// ============================================================
// KEYBOARD SHORTCUTS
// ============================================================
document.addEventListener('keydown', function(e) {
    // Ctrl+U to add user
    if (e.ctrlKey && e.key === 'u') {
        e.preventDefault();
        showAddUser();
    }
});
</script>

<?php include 'includes/footer.php'; ?>