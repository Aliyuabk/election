<?php
// ============================================================
// SUPPORT TICKET MANAGEMENT - SUPER ADMINISTRATOR
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
// ENSURE TICKETS TABLES EXIST
// ============================================================
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS support_tickets (
            id INT PRIMARY KEY AUTO_INCREMENT,
            tenant_id INT NOT NULL,
            user_id INT NOT NULL,
            ticket_number VARCHAR(50) UNIQUE NOT NULL,
            category ENUM('technical','billing','feature_request','bug_report','account','security','other') NOT NULL,
            priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
            subject VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            status ENUM('open','in_progress','waiting','resolved','closed','escalated') DEFAULT 'open',
            assigned_to INT DEFAULT NULL,
            resolved_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS support_ticket_replies (
            id INT PRIMARY KEY AUTO_INCREMENT,
            ticket_id INT NOT NULL,
            user_id INT NOT NULL,
            message TEXT NOT NULL,
            attachment_urls_json JSON DEFAULT NULL,
            is_internal TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch (Exception $e) {
    // Tables exist
}

// ============================================================
// HANDLE TICKET ACTIONS
// ============================================================
$action_result = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_ticket':
                $tenant_id = (int)($_POST['tenant_id'] ?? 0);
                $category = $_POST['category'] ?? 'other';
                $priority = $_POST['priority'] ?? 'medium';
                $subject = trim($_POST['subject'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                if (empty($subject) || empty($description)) {
                    throw new Exception('Subject and description are required.');
                }
                
                $ticket_number = 'TKT-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
                
                $stmt = $db->prepare("
                    INSERT INTO support_tickets (tenant_id, user_id, ticket_number, category, priority, subject, description, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'open')
                ");
                $stmt->execute([$tenant_id, SessionManager::get('user_id'), $ticket_number, $category, $priority, $subject, $description]);
                
                logActivity(SessionManager::get('user_id'), 'ticket_created', "Created ticket: $ticket_number");
                $action_result = ['success' => true, 'message' => "Ticket $ticket_number created successfully."];
                break;
                
            case 'assign_ticket':
                $id = (int)($_POST['id'] ?? 0);
                $assigned_to = (int)($_POST['assigned_to'] ?? 0);
                
                if ($id <= 0 || $assigned_to <= 0) {
                    throw new Exception('Invalid ticket or user.');
                }
                
                $stmt = $db->prepare("UPDATE support_tickets SET assigned_to = ?, status = 'in_progress', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$assigned_to, $id]);
                
                logActivity(SessionManager::get('user_id'), 'ticket_assigned', "Assigned ticket ID: $id to user: $assigned_to");
                $action_result = ['success' => true, 'message' => 'Ticket assigned successfully.'];
                break;
                
            case 'reply_ticket':
                $id = (int)($_POST['id'] ?? 0);
                $message = trim($_POST['message'] ?? '');
                $is_internal = isset($_POST['is_internal']) ? 1 : 0;
                
                if ($id <= 0 || empty($message)) {
                    throw new Exception('Invalid ticket or message.');
                }
                
                $stmt = $db->prepare("
                    INSERT INTO support_ticket_replies (ticket_id, user_id, message, is_internal)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$id, SessionManager::get('user_id'), $message, $is_internal]);
                
                $stmt = $db->prepare("UPDATE support_tickets SET updated_at = NOW() WHERE id = ?");
                $stmt->execute([$id]);
                
                logActivity(SessionManager::get('user_id'), 'ticket_replied', "Replied to ticket ID: $id");
                $action_result = ['success' => true, 'message' => 'Reply added successfully.'];
                break;
                
            case 'close_ticket':
                $id = (int)($_POST['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception('Invalid ticket ID.');
                }
                
                $stmt = $db->prepare("UPDATE support_tickets SET status = 'closed', resolved_at = NOW(), updated_at = NOW() WHERE id = ?");
                $stmt->execute([$id]);
                
                logActivity(SessionManager::get('user_id'), 'ticket_closed', "Closed ticket ID: $id");
                $action_result = ['success' => true, 'message' => 'Ticket closed successfully.'];
                break;
                
            case 'reopen_ticket':
                $id = (int)($_POST['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception('Invalid ticket ID.');
                }
                
                $stmt = $db->prepare("UPDATE support_tickets SET status = 'open', resolved_at = NULL, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$id]);
                
                logActivity(SessionManager::get('user_id'), 'ticket_reopened', "Reopened ticket ID: $id");
                $action_result = ['success' => true, 'message' => 'Ticket reopened successfully.'];
                break;
                
            case 'escalate_ticket':
                $id = (int)($_POST['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception('Invalid ticket ID.');
                }
                
                $stmt = $db->prepare("UPDATE support_tickets SET status = 'escalated', priority = 'urgent', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$id]);
                
                logActivity(SessionManager::get('user_id'), 'ticket_escalated', "Escalated ticket ID: $id");
                $action_result = ['success' => true, 'message' => 'Ticket escalated successfully.'];
                break;
        }
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// ============================================================
// FETCH TICKETS WITH PAGINATION & FILTERS
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$priority_filter = isset($_GET['priority']) ? $_GET['priority'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';

// Build WHERE clause
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(subject LIKE ? OR ticket_number LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if (!empty($priority_filter)) {
    $where_conditions[] = "priority = ?";
    $params[] = $priority_filter;
}

if (!empty($category_filter)) {
    $where_conditions[] = "category = ?";
    $params[] = $category_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Count total
$count_sql = "SELECT COUNT(*) as total FROM support_tickets $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_tickets = $stmt->fetch()['total'] ?? 0;
$total_pages = ceil($total_tickets / $limit);

// Fetch tickets
$sql = "
    SELECT 
        t.*,
        u.full_name as user_name,
        u.email as user_email,
        a.full_name as assigned_to_name,
        tn.name as tenant_name,
        (SELECT COUNT(*) FROM support_ticket_replies WHERE ticket_id = t.id) as reply_count
    FROM support_tickets t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN users a ON t.assigned_to = a.id
    LEFT JOIN tenants tn ON t.tenant_id = tn.id
    $where_clause
    ORDER BY 
        FIELD(t.status, 'urgent', 'high', 'medium', 'low'),
        t.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

// ============================================================
// FETCH USERS FOR ASSIGNMENT
// ============================================================
$users = [];
try {
    $stmt = $db->query("SELECT id, full_name, email FROM users WHERE deleted_at IS NULL ORDER BY full_name");
    $users = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH TENANTS
// ============================================================
$tenants = [];
try {
    $stmt = $db->query("SELECT id, name FROM tenants WHERE deleted_at IS NULL ORDER BY name");
    $tenants = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH STATISTICS
// ============================================================
$stats = [
    'total' => 0,
    'open' => 0,
    'in_progress' => 0,
    'resolved' => 0,
    'closed' => 0,
    'escalated' => 0,
    'urgent' => 0,
    'high' => 0,
    'medium' => 0,
    'low' => 0
];

try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM support_tickets");
    $stats['total'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM support_tickets WHERE status = 'open'");
    $stats['open'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM support_tickets WHERE status = 'in_progress'");
    $stats['in_progress'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM support_tickets WHERE status = 'resolved'");
    $stats['resolved'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM support_tickets WHERE status = 'closed'");
    $stats['closed'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM support_tickets WHERE status = 'escalated'");
    $stats['escalated'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM support_tickets WHERE priority = 'urgent'");
    $stats['urgent'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM support_tickets WHERE priority = 'high'");
    $stats['high'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM support_tickets WHERE priority = 'medium'");
    $stats['medium'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM support_tickets WHERE priority = 'low'");
    $stats['low'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {}

// Get user info
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       SUPPORT TICKETS - PRO STYLES
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
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 10px;
        margin-bottom: 20px;
    }
    .stat-item {
        background: white;
        border-radius: 10px;
        padding: 12px 16px;
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
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--primary);
    }
    .stat-item .number.green { color: var(--secondary); }
    .stat-item .number.red { color: var(--danger); }
    .stat-item .number.yellow { color: var(--warning); }
    .stat-item .number.purple { color: #8B5CF6; }
    .stat-item .number.orange { color: #F59E0B; }
    .stat-item .label {
        font-size: 0.7rem;
        color: var(--gray-500);
        margin-top: 2px;
    }
    
    .filter-bar-pro {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 14px 20px;
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        box-shadow: var(--shadow);
    }
    .filter-bar-pro .search-wrap {
        flex: 1;
        min-width: 200px;
        display: flex;
        align-items: center;
        background: var(--gray-50);
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        padding: 6px 14px;
        transition: var(--transition);
    }
    .filter-bar-pro .search-wrap:focus-within {
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
    }
    .filter-bar-pro .search-wrap i {
        color: var(--gray-400);
        font-size: 0.85rem;
    }
    .filter-bar-pro .search-wrap input {
        border: none;
        outline: none;
        background: transparent;
        padding: 6px 10px;
        font-family: 'Inter', sans-serif;
        font-size: 0.85rem;
        width: 100%;
        color: var(--gray-700);
    }
    .filter-bar-pro select {
        padding: 8px 14px;
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        font-family: 'Inter', sans-serif;
        font-size: 0.82rem;
        background: var(--gray-50);
        color: var(--gray-700);
        cursor: pointer;
        transition: var(--transition);
        min-width: 120px;
    }
    .filter-bar-pro select:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
    }
    .filter-bar-pro .btn-filter {
        padding: 8px 18px;
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
    .filter-bar-pro .btn-filter:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.25);
    }
    .filter-bar-pro .btn-clear {
        padding: 8px 14px;
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
    .filter-bar-pro .btn-clear:hover {
        background: var(--gray-50);
        border-color: var(--gray-300);
    }
    
    .table-container {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        overflow: hidden;
        box-shadow: var(--shadow);
    }
    .table-container .table-header {
        padding: 16px 20px;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        background: var(--gray-50);
    }
    .table-container .table-header .table-title {
        font-weight: 600;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .table-container .table-header .table-title .count {
        background: var(--primary);
        color: white;
        padding: 0 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    .data-table thead {
        background: var(--gray-50);
    }
    .data-table thead th {
        padding: 10px 14px;
        text-align: left;
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--gray-500);
        border-bottom: 1px solid var(--gray-200);
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
    }
    .data-table tbody tr:last-child td {
        border-bottom: none;
    }
    .data-table tbody tr:hover {
        background: var(--gray-50);
    }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
    }
    .status-badge .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
    }
    .status-badge.open { background: #EFF6FF; color: #1E40AF; }
    .status-badge.open .dot { background: #3B82F6; }
    .status-badge.in_progress { background: #FFFBEB; color: #92400E; }
    .status-badge.in_progress .dot { background: #F59E0B; }
    .status-badge.waiting { background: #F5F3FF; color: #5B21B6; }
    .status-badge.waiting .dot { background: #8B5CF6; }
    .status-badge.resolved { background: #ECFDF5; color: #065F46; }
    .status-badge.resolved .dot { background: #10B981; }
    .status-badge.closed { background: var(--gray-100); color: var(--gray-500); }
    .status-badge.closed .dot { background: var(--gray-400); }
    .status-badge.escalated { background: #FEF2F2; color: #991B1B; }
    .status-badge.escalated .dot { background: #EF4444; }
    
    .priority-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 600;
    }
    .priority-badge.urgent { background: #FEF2F2; color: #991B1B; }
    .priority-badge.high { background: #FFFBEB; color: #92400E; }
    .priority-badge.medium { background: #EFF6FF; color: #1E40AF; }
    .priority-badge.low { background: #ECFDF5; color: #065F46; }
    
    .category-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 500;
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .category-badge.technical { background: #EFF6FF; color: #1E40AF; }
    .category-badge.billing { background: #ECFDF5; color: #065F46; }
    .category-badge.feature_request { background: #F5F3FF; color: #5B21B6; }
    .category-badge.bug_report { background: #FEF2F2; color: #991B1B; }
    .category-badge.account { background: #FFFBEB; color: #92400E; }
    .category-badge.security { background: #FEF2F2; color: #991B1B; }
    .category-badge.other { background: var(--gray-100); color: var(--gray-500); }
    
    .action-dropdown {
        position: relative;
        display: inline-block;
    }
    .action-dropdown .dropdown-btn {
        background: none;
        border: none;
        padding: 4px 8px;
        cursor: pointer;
        color: var(--gray-400);
        font-size: 1.1rem;
        transition: var(--transition);
        border-radius: 6px;
    }
    .action-dropdown .dropdown-btn:hover {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .action-dropdown .dropdown-menu {
        position: absolute;
        right: 0;
        top: 100%;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.12);
        border: 1px solid var(--gray-200);
        min-width: 180px;
        padding: 6px;
        display: none;
        z-index: 50;
        animation: dropdownFade 0.2s ease;
    }
    @keyframes dropdownFade {
        from { opacity: 0; transform: translateY(-8px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .action-dropdown .dropdown-menu.open { display: block; }
    .action-dropdown .dropdown-menu a,
    .action-dropdown .dropdown-menu button {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 14px;
        width: 100%;
        border: none;
        background: none;
        font-family: 'Inter', sans-serif;
        font-size: 0.8rem;
        color: var(--gray-600);
        cursor: pointer;
        border-radius: 8px;
        transition: var(--transition);
        text-decoration: none;
    }
    .action-dropdown .dropdown-menu a:hover,
    .action-dropdown .dropdown-menu button:hover {
        background: var(--gray-50);
        color: var(--primary);
    }
    .action-dropdown .dropdown-menu .danger:hover {
        background: #FEF2F2;
        color: var(--danger);
    }
    .action-dropdown .dropdown-menu i {
        width: 16px;
        color: var(--gray-400);
        font-size: 0.85rem;
    }
    .action-dropdown .dropdown-menu .divider {
        height: 1px;
        background: var(--gray-100);
        margin: 4px 8px;
    }
    
    .pagination-pro {
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
    .pagination-pro .info {
        font-size: 0.82rem;
        color: var(--gray-500);
    }
    .pagination-pro .pages {
        display: flex;
        gap: 4px;
        align-items: center;
    }
    .pagination-pro .pages a,
    .pagination-pro .pages span {
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
    .pagination-pro .pages a:hover {
        background: var(--gray-100);
        border-color: var(--gray-200);
    }
    .pagination-pro .pages .active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }
    .pagination-pro .pages .disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }
    
    .empty-state-pro {
        text-align: center;
        padding: 48px 20px;
        color: var(--gray-500);
    }
    .empty-state-pro i {
        font-size: 3rem;
        color: var(--gray-300);
        display: block;
        margin-bottom: 12px;
    }
    .empty-state-pro h4 {
        color: var(--gray-700);
        margin-bottom: 4px;
        font-size: 1rem;
    }
    
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.4);
        z-index: 300;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .modal-overlay.active {
        display: flex;
    }
    .modal {
        background: white;
        border-radius: var(--radius);
        max-width: 560px;
        width: 100%;
        padding: 28px 32px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        animation: modalIn 0.25s ease;
        max-height: 90vh;
        overflow-y: auto;
    }
    @keyframes modalIn {
        from { transform: scale(0.95) translateY(10px); opacity: 0; }
        to { transform: scale(1) translateY(0); opacity: 1; }
    }
    .modal .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }
    .modal .modal-header h3 {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--gray-800);
    }
    .modal .modal-header .close-btn {
        background: none;
        border: none;
        font-size: 1.4rem;
        color: var(--gray-400);
        cursor: pointer;
        transition: var(--transition);
        padding: 0 4px;
    }
    .modal .modal-header .close-btn:hover {
        color: var(--gray-600);
    }
    .modal .form-group {
        margin-bottom: 14px;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .modal .form-group label {
        font-weight: 600;
        font-size: 0.82rem;
        color: var(--gray-700);
    }
    .modal .form-group label .required {
        color: var(--danger);
        margin-left: 2px;
    }
    .modal .form-group .help-text {
        font-size: 0.7rem;
        color: var(--gray-400);
        margin-top: 2px;
    }
    .modal .form-group input,
    .modal .form-group select,
    .modal .form-group textarea {
        padding: 10px 14px;
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        font-family: 'Inter', sans-serif;
        font-size: 0.85rem;
        transition: var(--transition);
        background: var(--gray-50);
        color: var(--gray-700);
        width: 100%;
    }
    .modal .form-group input:focus,
    .modal .form-group select:focus,
    .modal .form-group textarea:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
    }
    .modal .form-group textarea {
        resize: vertical;
        min-height: 80px;
    }
    .modal .form-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 20px;
        padding-top: 16px;
        border-top: 1px solid var(--gray-200);
    }
    .modal .form-actions .btn {
        padding: 8px 20px;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .modal .form-actions .btn-primary {
        background: var(--primary);
        color: white;
    }
    .modal .form-actions .btn-primary:hover {
        background: var(--primary-dark);
    }
    .modal .form-actions .btn-secondary {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .modal .form-actions .btn-secondary:hover {
        background: var(--gray-200);
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
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .filter-bar-pro { flex-direction: column; align-items: stretch; }
        .filter-bar-pro .search-wrap { min-width: auto; }
        .filter-bar-pro select { width: 100%; }
        .table-container { overflow-x: auto; }
        .data-table { font-size: 0.78rem; }
        .data-table th, .data-table td { padding: 6px 10px; }
        .pagination-pro { flex-direction: column; align-items: center; }
        .page-header { flex-direction: column; align-items: flex-start; }
        .modal { padding: 20px; margin: 10px; }
    }
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
        .stat-item { padding: 10px 12px; }
        .stat-item .number { font-size: 1.2rem; }
        .data-table th, .data-table td { padding: 4px 8px; font-size: 0.7rem; }
        .status-badge { font-size: 0.55rem; padding: 1px 6px; }
        .priority-badge { font-size: 0.55rem; padding: 1px 6px; }
        .category-badge { font-size: 0.55rem; padding: 1px 6px; }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Toast Messages -->
        <?php if (!empty($action_result['message'])): ?>
        <div style="margin-bottom:16px;">
            <div class="toast <?php echo $action_result['success'] ? 'success' : 'error'; ?>" style="position:static;animation:none;max-width:100%;">
                <i class="fas <?php echo $action_result['success'] ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($action_result['message']); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-ticket-alt" style="color:var(--primary);margin-right:8px;"></i> Support Tickets
                    <small>Manage all support tickets across the platform</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button onclick="openModal('createTicketModal')" class="btn-primary">
                    <i class="fas fa-plus-circle"></i> Create Ticket
                </button>
                <button onclick="location.reload()" class="btn-outline">
                    <i class="fas fa-sync"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-item"><div class="number"><?php echo number_format($stats['total']); ?></div><div class="label">Total</div></div>
            <div class="stat-item"><div class="number blue"><?php echo number_format($stats['open']); ?></div><div class="label">Open</div></div>
            <div class="stat-item"><div class="number yellow"><?php echo number_format($stats['in_progress']); ?></div><div class="label">In Progress</div></div>
            <div class="stat-item"><div class="number green"><?php echo number_format($stats['resolved']); ?></div><div class="label">Resolved</div></div>
            <div class="stat-item"><div class="number red"><?php echo number_format($stats['escalated']); ?></div><div class="label">Escalated</div></div>
            <div class="stat-item"><div class="number purple"><?php echo number_format($stats['closed']); ?></div><div class="label">Closed</div></div>
        </div>

        <!-- Priority Summary -->
        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;padding:12px 16px;background:white;border-radius:10px;border:1px solid var(--gray-200);">
            <span style="font-weight:600;font-size:0.82rem;color:var(--gray-600);margin-right:8px;">Priority:</span>
            <span class="priority-badge urgent"><i class="fas fa-circle" style="font-size:6px;margin-right:4px;"></i> Urgent (<?php echo $stats['urgent']; ?>)</span>
            <span class="priority-badge high"><i class="fas fa-circle" style="font-size:6px;margin-right:4px;"></i> High (<?php echo $stats['high']; ?>)</span>
            <span class="priority-badge medium"><i class="fas fa-circle" style="font-size:6px;margin-right:4px;"></i> Medium (<?php echo $stats['medium']; ?>)</span>
            <span class="priority-badge low"><i class="fas fa-circle" style="font-size:6px;margin-right:4px;"></i> Low (<?php echo $stats['low']; ?>)</span>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar-pro">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:10px;flex:1;align-items:center;width:100%;">
                <div class="search-wrap" style="flex:1;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search tickets..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>>Open</option>
                    <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="waiting" <?php echo $status_filter === 'waiting' ? 'selected' : ''; ?>>Waiting</option>
                    <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                    <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                    <option value="escalated" <?php echo $status_filter === 'escalated' ? 'selected' : ''; ?>>Escalated</option>
                </select>
                <select name="priority">
                    <option value="">All Priority</option>
                    <option value="urgent" <?php echo $priority_filter === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                    <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                    <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                    <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                </select>
                <select name="category">
                    <option value="">All Categories</option>
                    <option value="technical" <?php echo $category_filter === 'technical' ? 'selected' : ''; ?>>Technical</option>
                    <option value="billing" <?php echo $category_filter === 'billing' ? 'selected' : ''; ?>>Billing</option>
                    <option value="feature_request" <?php echo $category_filter === 'feature_request' ? 'selected' : ''; ?>>Feature Request</option>
                    <option value="bug_report" <?php echo $category_filter === 'bug_report' ? 'selected' : ''; ?>>Bug Report</option>
                    <option value="account" <?php echo $category_filter === 'account' ? 'selected' : ''; ?>>Account</option>
                    <option value="security" <?php echo $category_filter === 'security' ? 'selected' : ''; ?>>Security</option>
                    <option value="other" <?php echo $category_filter === 'other' ? 'selected' : ''; ?>>Other</option>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
                <?php if (!empty($search) || !empty($status_filter) || !empty($priority_filter) || !empty($category_filter)): ?>
                    <a href="tickets.php" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Tickets Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-list" style="color:var(--primary);"></i> All Tickets
                    <span class="count"><?php echo number_format($total_tickets); ?></span>
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:60px;">Ticket</th>
                        <th>Subject</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>User</th>
                        <th>Assigned To</th>
                        <th>Replies</th>
                        <th>Created</th>
                        <th style="width:80px;text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($tickets) > 0): ?>
                        <?php foreach ($tickets as $ticket): ?>
                            <tr>
                                <td>
                                    <span style="font-weight:600;font-size:0.8rem;color:var(--primary);">
                                        <?php echo htmlspecialchars($ticket['ticket_number']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight:500;font-size:0.82rem;max-width:150px;word-wrap:break-word;">
                                        <?php echo htmlspecialchars($ticket['subject']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="category-badge <?php echo $ticket['category']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $ticket['category'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="priority-badge <?php echo $ticket['priority']; ?>">
                                        <?php echo ucfirst($ticket['priority']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $ticket['status']; ?>">
                                        <span class="dot"></span>
                                        <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size:0.78rem;">
                                        <?php echo htmlspecialchars($ticket['user_name'] ?? 'N/A'); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size:0.78rem;">
                                        <?php echo htmlspecialchars($ticket['assigned_to_name'] ?? 'Unassigned'); ?>
                                    </div>
                                </td>
                                <td style="text-align:center;font-weight:600;">
                                    <?php echo $ticket['reply_count'] ?? 0; ?>
                                </td>
                                <td style="font-size:0.7rem;color:var(--gray-500);">
                                    <?php echo date('M j, Y', strtotime($ticket['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="action-dropdown">
                                        <button class="dropdown-btn" onclick="toggleDropdown(this)"><i class="fas fa-ellipsis-v"></i></button>
                                        <div class="dropdown-menu">
                                            <button onclick="viewTicket(<?php echo $ticket['id']; ?>)"><i class="fas fa-eye"></i> View</button>
                                            <button onclick="openReplyModal(<?php echo $ticket['id']; ?>)"><i class="fas fa-reply"></i> Reply</button>
                                            <button onclick="openAssignModal(<?php echo $ticket['id']; ?>)"><i class="fas fa-user-check"></i> Assign</button>
                                            <div class="divider"></div>
                                            <?php if ($ticket['status'] !== 'closed' && $ticket['status'] !== 'resolved'): ?>
                                                <button onclick="closeTicket(<?php echo $ticket['id']; ?>)"><i class="fas fa-check-circle"></i> Close</button>
                                                <button onclick="escalateTicket(<?php echo $ticket['id']; ?>)"><i class="fas fa-exclamation-triangle"></i> Escalate</button>
                                            <?php else: ?>
                                                <button onclick="reopenTicket(<?php echo $ticket['id']; ?>)"><i class="fas fa-undo"></i> Reopen</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10">
                                <div class="empty-state-pro">
                                    <i class="fas fa-ticket-alt"></i>
                                    <h4>No tickets found</h4>
                                    <p>Create a new ticket to get started.</p>
                                    <button onclick="openModal('createTicketModal')" class="btn-primary" style="margin-top:12px;">
                                        <i class="fas fa-plus-circle"></i> Create Ticket
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-pro">
            <div class="info">
                Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $limit, $total_tickets); ?></strong> of <strong><?php echo number_format($total_tickets); ?></strong> tickets
            </div>
            <div class="pages">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&priority=<?php echo urlencode($priority_filter); ?>&category=<?php echo urlencode($category_filter); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1) {
                    echo '<a href="?page=1&search=' . urlencode($search) . '&status=' . urlencode($status_filter) . '&priority=' . urlencode($priority_filter) . '&category=' . urlencode($category_filter) . '">1</a>';
                    if ($start_page > 2) echo '<span>…</span>';
                }
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&priority=<?php echo urlencode($priority_filter); ?>&category=<?php echo urlencode($category_filter); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor;
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<span>…</span>';
                    echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search) . '&status=' . urlencode($status_filter) . '&priority=' . urlencode($priority_filter) . '&category=' . urlencode($category_filter) . '">' . $total_pages . '</a>';
                }
                ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&priority=<?php echo urlencode($priority_filter); ?>&category=<?php echo urlencode($category_filter); ?>">
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

<!-- Create Ticket Modal -->
<div class="modal-overlay" id="createTicketModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle" style="color:var(--primary);"></i> Create Ticket</h3>
            <button class="close-btn" onclick="closeModal('createTicketModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="create_ticket">
            <div class="form-group">
                <label>Tenant <span class="required">*</span></label>
                <select name="tenant_id" required>
                    <option value="">Select Tenant</option>
                    <?php foreach ($tenants as $t): ?>
                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Category <span class="required">*</span></label>
                <select name="category" required>
                    <option value="technical">Technical</option>
                    <option value="billing">Billing</option>
                    <option value="feature_request">Feature Request</option>
                    <option value="bug_report">Bug Report</option>
                    <option value="account">Account</option>
                    <option value="security">Security</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label>Priority <span class="required">*</span></label>
                <select name="priority" required>
                    <option value="low">Low</option>
                    <option value="medium" selected>Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>
            <div class="form-group">
                <label>Subject <span class="required">*</span></label>
                <input type="text" name="subject" placeholder="Brief summary of the issue" required>
            </div>
            <div class="form-group">
                <label>Description <span class="required">*</span></label>
                <textarea name="description" placeholder="Detailed description of the issue..." required></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createTicketModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Ticket</button>
            </div>
        </form>
    </div>
</div>

<!-- Reply Modal -->
<div class="modal-overlay" id="replyModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-reply" style="color:var(--primary);"></i> Reply to Ticket</h3>
            <button class="close-btn" onclick="closeModal('replyModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="reply_ticket">
            <input type="hidden" name="id" id="replyTicketId">
            <div class="form-group">
                <label>Message <span class="required">*</span></label>
                <textarea name="message" placeholder="Type your reply here..." required style="min-height:100px;"></textarea>
            </div>
            <div class="form-group">
                <div style="display:flex;align-items:center;gap:10px;">
                    <input type="checkbox" name="is_internal" id="isInternal" value="1">
                    <label for="isInternal" style="font-weight:400;cursor:pointer;">Internal Note (only visible to staff)</label>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('replyModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Send Reply</button>
            </div>
        </form>
    </div>
</div>

<!-- Assign Modal -->
<div class="modal-overlay" id="assignModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-user-check" style="color:var(--primary);"></i> Assign Ticket</h3>
            <button class="close-btn" onclick="closeModal('assignModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="assign_ticket">
            <input type="hidden" name="id" id="assignTicketId">
            <div class="form-group">
                <label>Assign To <span class="required">*</span></label>
                <select name="assigned_to" required>
                    <option value="">Select User</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['full_name'] ?? $u['email']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('assignModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Assign Ticket</button>
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
// DROPDOWN FUNCTIONS
// ============================================================
function toggleDropdown(btn) {
    var menu = btn.nextElementSibling;
    var isOpen = menu.classList.contains('open');
    document.querySelectorAll('.action-dropdown .dropdown-menu').forEach(function(m) {
        m.classList.remove('open');
    });
    if (!isOpen) {
        menu.classList.toggle('open');
    }
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.action-dropdown')) {
        document.querySelectorAll('.action-dropdown .dropdown-menu').forEach(function(m) {
            m.classList.remove('open');
        });
    }
});

// ============================================================
// TICKET ACTION FUNCTIONS
// ============================================================
function viewTicket(id) {
    alert('View ticket ID: ' + id + '\nImplement full ticket view with replies.');
}

function openReplyModal(id) {
    document.getElementById('replyTicketId').value = id;
    openModal('replyModal');
}

function openAssignModal(id) {
    document.getElementById('assignTicketId').value = id;
    openModal('assignModal');
}

function closeTicket(id) {
    if (confirm('Close this ticket?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="close_ticket"><input type="hidden" name="id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

function reopenTicket(id) {
    if (confirm('Reopen this ticket?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="reopen_ticket"><input type="hidden" name="id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

function escalateTicket(id) {
    if (confirm('Escalate this ticket? This will mark it as urgent.')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="escalate_ticket"><input type="hidden" name="id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
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