<?php
// ============================================================
// NOTIFICATIONS - Super Administrator
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Start session
SessionManager::start();

// Check if logged in
if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

// Check role - only super_admin can access this page
if (SessionManager::get('role_level') !== 'super_admin') {
    header('Location: ../client-admin/');
    exit();
}

$db = getDB();
$user_id = SessionManager::get('user_id');
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');

// ============================================================
// ENSURE NOTIFICATIONS TABLE EXISTS
// ============================================================
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            type ENUM('system','election','result','incident','chat','broadcast','payment','security','tenant','user') NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            data_json JSON DEFAULT NULL,
            action_url VARCHAR(500) DEFAULT NULL,
            is_read TINYINT(1) DEFAULT 0,
            read_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_read (user_id, is_read),
            INDEX idx_created (created_at)
        )
    ");
} catch (Exception $e) {}

// ============================================================
// HANDLE AJAX REQUESTS
// ============================================================
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => '', 'data' => []];
    
    try {
        switch ($action) {
            case 'get_notifications':
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                $unread_only = isset($_GET['unread']) ? (bool)$_GET['unread'] : false;
                
                $sql = "SELECT * FROM notifications WHERE user_id = ?";
                $params = [$user_id];
                
                if ($unread_only) {
                    $sql .= " AND is_read = 0";
                }
                
                $sql .= " ORDER BY created_at DESC LIMIT ?";
                $params[] = $limit;
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $notifications = $stmt->fetchAll();
                
                // Get unread count
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
                $stmt->execute([$user_id]);
                $unread_count = $stmt->fetch()['count'] ?? 0;
                
                $response = [
                    'success' => true,
                    'data' => $notifications,
                    'unread_count' => $unread_count
                ];
                break;
                
            case 'mark_read':
                $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                
                if ($id > 0) {
                    $stmt = $db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
                    $stmt->execute([$id, $user_id]);
                    $response = ['success' => true, 'message' => 'Notification marked as read.'];
                } else {
                    // Mark all as read
                    $stmt = $db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
                    $stmt->execute([$user_id]);
                    $response = ['success' => true, 'message' => 'All notifications marked as read.'];
                }
                break;
                
            case 'mark_all_read':
                $stmt = $db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
                $stmt->execute([$user_id]);
                $response = ['success' => true, 'message' => 'All notifications marked as read.'];
                break;
                
            case 'get_unread_count':
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
                $stmt->execute([$user_id]);
                $count = $stmt->fetch()['count'] ?? 0;
                $response = ['success' => true, 'unread_count' => $count];
                break;
                
            case 'delete':
                $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                if ($id > 0) {
                    $stmt = $db->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
                    $stmt->execute([$id, $user_id]);
                    $response = ['success' => true, 'message' => 'Notification deleted.'];
                }
                break;
                
            default:
                throw new Exception('Invalid action.');
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// ============================================================
// FETCH NOTIFICATIONS FOR DISPLAY
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE clause
$where_conditions = ["user_id = ?"];
$params = [$user_id];

if (!empty($filter)) {
    if ($filter === 'unread') {
        $where_conditions[] = "is_read = 0";
    } elseif ($filter === 'read') {
        $where_conditions[] = "is_read = 1";
    } elseif (in_array($filter, ['system','election','result','incident','chat','broadcast','payment','security','tenant','user'])) {
        $where_conditions[] = "type = ?";
        $params[] = $filter;
    }
}

if (!empty($search)) {
    $where_conditions[] = "(title LIKE ? OR message LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
}

$where_clause = implode(" AND ", $where_conditions);

// Count total
$count_sql = "SELECT COUNT(*) as total FROM notifications WHERE $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_notifications = $stmt->fetch()['total'] ?? 0;
$total_pages = ceil($total_notifications / $limit);

// Fetch notifications
$sql = "SELECT * FROM notifications WHERE $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$notifications = $stmt->fetchAll();

// Get unread count for badge
$stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$user_id]);
$unread_count = $stmt->fetch()['count'] ?? 0;

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       NOTIFICATIONS PAGE - PRO STYLES
       ============================================================ */
    
    .notifications-container { max-width: 1000px; margin: 0 auto; }
    
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
    
    .notification-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }
    .stat-item {
        background: white;
        border-radius: 12px;
        padding: 14px 18px;
        border: 1px solid var(--gray-200);
        text-align: center;
        transition: var(--transition);
        cursor: pointer;
    }
    .stat-item:hover {
        box-shadow: var(--shadow-hover);
        transform: translateY(-2px);
    }
    .stat-item .number {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary);
    }
    .stat-item .number.green { color: var(--secondary); }
    .stat-item .number.yellow { color: var(--warning); }
    .stat-item .number.red { color: var(--danger); }
    .stat-item .label {
        font-size: 0.7rem;
        color: var(--gray-500);
        margin-top: 2px;
    }
    
    .filter-bar {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 12px 16px;
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        box-shadow: var(--shadow);
    }
    .filter-bar .search-wrap {
        flex: 1;
        min-width: 180px;
        display: flex;
        align-items: center;
        background: var(--gray-50);
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        padding: 4px 12px;
        transition: var(--transition);
    }
    .filter-bar .search-wrap:focus-within {
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
    }
    .filter-bar .search-wrap i {
        color: var(--gray-400);
        font-size: 0.8rem;
    }
    .filter-bar .search-wrap input {
        border: none;
        outline: none;
        background: transparent;
        padding: 4px 8px;
        font-family: 'Inter', sans-serif;
        font-size: 0.85rem;
        width: 100%;
        color: var(--gray-700);
    }
    .filter-bar select {
        padding: 6px 12px;
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        font-family: 'Inter', sans-serif;
        font-size: 0.82rem;
        background: var(--gray-50);
        color: var(--gray-700);
        cursor: pointer;
        transition: var(--transition);
        min-width: 130px;
    }
    .filter-bar select:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
    }
    .filter-bar .btn-filter {
        padding: 6px 16px;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.8rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .filter-bar .btn-filter:hover {
        background: var(--primary-dark);
    }
    .filter-bar .btn-clear {
        padding: 6px 14px;
        background: transparent;
        color: var(--gray-500);
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        font-weight: 500;
        font-size: 0.8rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .filter-bar .btn-clear:hover {
        background: var(--gray-50);
        border-color: var(--gray-300);
    }
    
    .notification-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .notification-item {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 16px 20px;
        box-shadow: var(--shadow);
        transition: var(--transition);
        display: flex;
        gap: 14px;
        align-items: flex-start;
        text-decoration: none;
        color: var(--gray-700);
    }
    .notification-item:hover {
        box-shadow: var(--shadow-hover);
        transform: translateX(4px);
    }
    .notification-item.unread {
        border-left: 4px solid var(--primary);
        background: #F8FAFF;
    }
    .notification-item .notif-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }
    .notification-item .notif-icon.system { background: #EFF6FF; color: #3B82F6; }
    .notification-item .notif-icon.election { background: #ECFDF5; color: #10B981; }
    .notification-item .notif-icon.result { background: #F5F3FF; color: #8B5CF6; }
    .notification-item .notif-icon.incident { background: #FEF2F2; color: #EF4444; }
    .notification-item .notif-icon.chat { background: #FFFBEB; color: #F59E0B; }
    .notification-item .notif-icon.payment { background: #ECFDF5; color: #10B981; }
    .notification-item .notif-icon.security { background: #FEF2F2; color: #EF4444; }
    .notification-item .notif-icon.broadcast { background: #F5F3FF; color: #8B5CF6; }
    .notification-item .notif-icon.tenant { background: #EFF6FF; color: #3B82F6; }
    .notification-item .notif-icon.user { background: #F5F3FF; color: #8B5CF6; }
    
    .notification-item .notif-content {
        flex: 1;
        min-width: 0;
    }
    .notification-item .notif-content .notif-title {
        font-weight: 600;
        font-size: 0.9rem;
    }
    .notification-item .notif-content .notif-message {
        font-size: 0.85rem;
        color: var(--gray-500);
        margin-top: 2px;
    }
    .notification-item .notif-content .notif-meta {
        display: flex;
        gap: 12px;
        margin-top: 6px;
        font-size: 0.7rem;
        color: var(--gray-400);
    }
    .notification-item .notif-content .notif-meta .type-badge {
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
        background: var(--gray-100);
        color: var(--gray-500);
    }
    .notification-item .notif-content .notif-meta .type-badge.system { background: #EFF6FF; color: #3B82F6; }
    .notification-item .notif-content .notif-meta .type-badge.election { background: #ECFDF5; color: #10B981; }
    .notification-item .notif-content .notif-meta .type-badge.result { background: #F5F3FF; color: #8B5CF6; }
    .notification-item .notif-content .notif-meta .type-badge.incident { background: #FEF2F2; color: #EF4444; }
    .notification-item .notif-content .notif-meta .type-badge.security { background: #FEF2F2; color: #EF4444; }
    .notification-item .notif-content .notif-meta .type-badge.payment { background: #ECFDF5; color: #10B981; }
    
    .notification-item .notif-actions {
        display: flex;
        gap: 6px;
        flex-shrink: 0;
    }
    .notification-item .notif-actions button {
        background: none;
        border: none;
        color: var(--gray-400);
        cursor: pointer;
        transition: var(--transition);
        padding: 4px;
        border-radius: 6px;
        font-size: 0.8rem;
    }
    .notification-item .notif-actions button:hover {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .notification-item .notif-actions button.mark-read {
        color: var(--primary);
    }
    .notification-item .notif-actions button.mark-read:hover {
        background: #EFF6FF;
    }
    .notification-item .notif-actions button.delete:hover {
        background: #FEF2F2;
        color: var(--danger);
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--gray-500);
    }
    .empty-state i {
        font-size: 3rem;
        color: var(--gray-300);
        display: block;
        margin-bottom: 16px;
    }
    .empty-state h3 {
        color: var(--gray-700);
        margin-bottom: 6px;
    }
    .empty-state p {
        font-size: 0.9rem;
    }
    
    .pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        padding: 14px 20px;
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        margin-top: 16px;
        box-shadow: var(--shadow);
    }
    .pagination .info {
        font-size: 0.82rem;
        color: var(--gray-500);
    }
    .pagination .pages {
        display: flex;
        gap: 4px;
        align-items: center;
    }
    .pagination .pages a,
    .pagination .pages span {
        padding: 6px 14px;
        border-radius: 8px;
        font-size: 0.82rem;
        text-decoration: none;
        color: var(--gray-600);
        transition: var(--transition);
        min-width: 36px;
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
    }
    .pagination .pages .disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }
    
    .toast-container {
        position: fixed;
        top: 80px;
        right: 20px;
        z-index: 999;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .toast {
        padding: 14px 20px;
        border-radius: 10px;
        color: white;
        font-size: 0.85rem;
        font-weight: 500;
        box-shadow: var(--shadow-hover);
        animation: slideIn 0.3s ease;
        min-width: 280px;
        max-width: 400px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .toast.success { background: var(--secondary); }
    .toast.error { background: var(--danger); }
    @keyframes slideIn {
        from { opacity: 0; transform: translateX(100px); }
        to { opacity: 1; transform: translateX(0); }
    }
    
    @media (max-width: 768px) {
        .notification-stats { grid-template-columns: repeat(2, 1fr); }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar .search-wrap { min-width: auto; }
        .filter-bar select { width: 100%; }
        .notification-item { padding: 12px 14px; flex-wrap: wrap; }
        .notification-item .notif-actions { width: 100%; justify-content: flex-end; }
        .page-header { flex-direction: column; align-items: flex-start; }
        .pagination { flex-direction: column; align-items: center; }
    }
    @media (max-width: 480px) {
        .notification-stats { grid-template-columns: 1fr 1fr; gap: 8px; }
        .stat-item { padding: 10px 12px; }
        .stat-item .number { font-size: 1.2rem; }
        .notification-item .notif-icon { width: 32px; height: 32px; font-size: 0.8rem; }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="notifications-container">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h2>
                        <i class="fas fa-bell" style="color:var(--primary);margin-right:8px;"></i> Notifications
                        <small>Manage all your notifications</small>
                    </h2>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <button onclick="markAllRead()" class="btn-outline">
                        <i class="fas fa-check-double"></i> Mark All Read
                    </button>
                    <button onclick="location.reload()" class="btn-outline">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                </div>
            </div>

            <!-- Stats -->
            <div class="notification-stats">
                <div class="stat-item" onclick="applyFilter('')">
                    <div class="number"><?php echo number_format($total_notifications); ?></div>
                    <div class="label">Total</div>
                </div>
                <div class="stat-item" onclick="applyFilter('unread')">
                    <div class="number green"><?php echo number_format($unread_count); ?></div>
                    <div class="label">Unread</div>
                </div>
                <div class="stat-item" onclick="applyFilter('read')">
                    <div class="number"><?php echo number_format($total_notifications - $unread_count); ?></div>
                    <div class="label">Read</div>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:10px;flex:1;align-items:center;width:100%;">
                    <div class="search-wrap" style="flex:1;">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search notifications..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <select name="filter">
                        <option value="">All Types</option>
                        <option value="system" <?php echo $filter === 'system' ? 'selected' : ''; ?>>System</option>
                        <option value="election" <?php echo $filter === 'election' ? 'selected' : ''; ?>>Election</option>
                        <option value="result" <?php echo $filter === 'result' ? 'selected' : ''; ?>>Result</option>
                        <option value="incident" <?php echo $filter === 'incident' ? 'selected' : ''; ?>>Incident</option>
                        <option value="chat" <?php echo $filter === 'chat' ? 'selected' : ''; ?>>Chat</option>
                        <option value="payment" <?php echo $filter === 'payment' ? 'selected' : ''; ?>>Payment</option>
                        <option value="security" <?php echo $filter === 'security' ? 'selected' : ''; ?>>Security</option>
                        <option value="tenant" <?php echo $filter === 'tenant' ? 'selected' : ''; ?>>Tenant</option>
                        <option value="user" <?php echo $filter === 'user' ? 'selected' : ''; ?>>User</option>
                    </select>
                    <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
                    <?php if (!empty($search) || !empty($filter)): ?>
                        <a href="notification.php" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Notifications List -->
            <div class="notification-list">
                <?php if (count($notifications) > 0): ?>
                    <?php foreach ($notifications as $notif): ?>
                        <?php 
                            $iconClass = $notif['type'] ?? 'system';
                            $iconMap = [
                                'system' => 'fa-cog',
                                'election' => 'fa-vote-yea',
                                'result' => 'fa-chart-bar',
                                'incident' => 'fa-exclamation-triangle',
                                'chat' => 'fa-comment',
                                'payment' => 'fa-credit-card',
                                'security' => 'fa-shield-alt',
                                'broadcast' => 'fa-bullhorn',
                                'tenant' => 'fa-building',
                                'user' => 'fa-user'
                            ];
                            $icon = $iconMap[$iconClass] ?? 'fa-bell';
                            $is_unread = !$notif['is_read'];
                            $time = date('M j, Y g:i A', strtotime($notif['created_at']));
                        ?>
                        <div class="notification-item <?php echo $is_unread ? 'unread' : ''; ?>" data-id="<?php echo $notif['id']; ?>">
                            <div class="notif-icon <?php echo $iconClass; ?>">
                                <i class="fas <?php echo $icon; ?>"></i>
                            </div>
                            <div class="notif-content">
                                <div class="notif-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                <div class="notif-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                                <div class="notif-meta">
                                    <span class="type-badge <?php echo $iconClass; ?>"><?php echo ucfirst($notif['type']); ?></span>
                                    <span><i class="far fa-clock"></i> <?php echo $time; ?></span>
                                    <?php if (!$is_unread): ?>
                                        <span style="color:var(--secondary);"><i class="fas fa-check-circle"></i> Read</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="notif-actions">
                                <?php if ($is_unread): ?>
                                    <button class="mark-read" onclick="markRead(<?php echo $notif['id']; ?>)" title="Mark as read">
                                        <i class="fas fa-check"></i>
                                    </button>
                                <?php endif; ?>
                                <button class="delete" onclick="deleteNotification(<?php echo $notif['id']; ?>)" title="Delete">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <h3>No notifications found</h3>
                        <p>You're all caught up! No notifications to display.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <div class="info">
                    Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $limit, $total_notifications); ?></strong> of <strong><?php echo number_format($total_notifications); ?></strong> notifications
                </div>
                <div class="pages">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    if ($start_page > 1) {
                        echo '<a href="?page=1&search=' . urlencode($search) . '&filter=' . urlencode($filter) . '">1</a>';
                        if ($start_page > 2) echo '<span>…</span>';
                    }
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor;
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo '<span>…</span>';
                        echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search) . '&filter=' . urlencode($filter) . '">' . $total_pages . '</a>';
                    }
                    ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
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
// NOTIFICATION FUNCTIONS
// ============================================================
function applyFilter(filter) {
    window.location.href = 'notification.php?filter=' + filter;
}

function markRead(id) {
    fetch('notification.php?action=mark_read', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'id=' + id
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            location.reload();
        }
    })
    .catch(function() {});
}

function markAllRead() {
    if (!confirm('Mark all notifications as read?')) return;
    
    fetch('notification.php?action=mark_all_read', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            location.reload();
        }
    })
    .catch(function() {});
}

function deleteNotification(id) {
    if (!confirm('Delete this notification?')) return;
    
    fetch('notification.php?action=delete', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'id=' + id
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            location.reload();
        }
    })
    .catch(function() {});
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