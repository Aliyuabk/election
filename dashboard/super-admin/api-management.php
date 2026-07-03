<?php
// ============================================================
// API MANAGEMENT - SUPER ADMINISTRATOR (COMPLETE REWRITE)
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
// ENSURE API TABLES EXIST
// ============================================================
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS api_keys (
            id INT PRIMARY KEY AUTO_INCREMENT,
            tenant_id INT DEFAULT NULL,
            user_id INT DEFAULT NULL,
            name VARCHAR(255) NOT NULL,
            key_hash VARCHAR(255) NOT NULL,
            key_prefix VARCHAR(20) NOT NULL,
            permissions_json JSON NOT NULL,
            rate_limit INT DEFAULT 1000,
            rate_limit_window INT DEFAULT 3600,
            last_used_at TIMESTAMP NULL,
            expires_at TIMESTAMP NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS api_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            api_key_id INT DEFAULT NULL,
            user_id INT DEFAULT NULL,
            tenant_id INT DEFAULT NULL,
            method VARCHAR(10) NOT NULL,
            endpoint VARCHAR(500) NOT NULL,
            request_body JSON DEFAULT NULL,
            response_status INT DEFAULT NULL,
            response_body JSON DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            duration_ms INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch (Exception $e) {
    // Tables already exist
}

// ============================================================
// HANDLE API ACTIONS
// ============================================================
$action_result = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_key':
                $name = trim($_POST['name'] ?? '');
                $tenant_id = !empty($_POST['tenant_id']) ? (int)$_POST['tenant_id'] : null;
                $rate_limit = (int)($_POST['rate_limit'] ?? 1000);
                $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
                $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];
                
                if (empty($name)) {
                    throw new Exception('API key name is required.');
                }
                
                // Generate API key
                $key_prefix = 'ak_' . bin2hex(random_bytes(4));
                $key_suffix = bin2hex(random_bytes(16));
                $full_key = $key_prefix . '_' . $key_suffix;
                $key_hash = password_hash($full_key, PASSWORD_DEFAULT);
                
                $permissions_json = json_encode($permissions);
                
                $stmt = $db->prepare("
                    INSERT INTO api_keys (
                        tenant_id, user_id, name, key_hash, key_prefix,
                        permissions_json, rate_limit, rate_limit_window,
                        expires_at, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $tenant_id,
                    SessionManager::get('user_id'),
                    $name,
                    $key_hash,
                    $key_prefix,
                    $permissions_json,
                    $rate_limit,
                    3600,
                    $expires_at,
                    SessionManager::get('user_id')
                ]);
                
                $api_key_id = $db->lastInsertId();
                
                logActivity(
                    SessionManager::get('user_id'),
                    'api_key_created',
                    "Created API key: $name (ID: $api_key_id)"
                );
                
                $action_result = [
                    'success' => true,
                    'message' => 'API key created successfully!',
                    'api_key' => $full_key,
                    'api_key_id' => $api_key_id
                ];
                break;
                
            case 'revoke_key':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception('Invalid API key ID.');
                }
                
                $stmt = $db->prepare("UPDATE api_keys SET is_active = 0 WHERE id = ?");
                $stmt->execute([$id]);
                
                logActivity(
                    SessionManager::get('user_id'),
                    'api_key_revoked',
                    "Revoked API key ID: $id"
                );
                
                $action_result = ['success' => true, 'message' => 'API key revoked successfully.'];
                break;
                
            case 'activate_key':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception('Invalid API key ID.');
                }
                
                $stmt = $db->prepare("UPDATE api_keys SET is_active = 1 WHERE id = ?");
                $stmt->execute([$id]);
                
                logActivity(
                    SessionManager::get('user_id'),
                    'api_key_activated',
                    "Activated API key ID: $id"
                );
                
                $action_result = ['success' => true, 'message' => 'API key activated successfully.'];
                break;
                
            case 'delete_key':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception('Invalid API key ID.');
                }
                
                $stmt = $db->prepare("DELETE FROM api_keys WHERE id = ?");
                $stmt->execute([$id]);
                
                logActivity(
                    SessionManager::get('user_id'),
                    'api_key_deleted',
                    "Deleted API key ID: $id"
                );
                
                $action_result = ['success' => true, 'message' => 'API key deleted successfully.'];
                break;
                
            default:
                throw new Exception('Invalid action.');
        }
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => $e->getMessage()];
    }
}

// ============================================================
// FETCH API KEYS
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build WHERE clause
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR key_prefix LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
}

if ($status_filter === 'active') {
    $where_conditions[] = "is_active = 1";
} elseif ($status_filter === 'revoked') {
    $where_conditions[] = "is_active = 0";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Count total
$count_sql = "SELECT COUNT(*) as total FROM api_keys $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_keys = $stmt->fetch()['total'] ?? 0;
$total_pages = ceil($total_keys / $limit);

// Fetch API keys
$sql = "
    SELECT 
        k.*,
        u.full_name as created_by_name,
        t.name as tenant_name
    FROM api_keys k
    LEFT JOIN users u ON k.created_by = u.id
    LEFT JOIN tenants t ON k.tenant_id = t.id
    $where_clause
    ORDER BY k.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$api_keys = $stmt->fetchAll();

// ============================================================
// FETCH API LOGS
// ============================================================
$api_logs = [];
try {
    $stmt = $db->query("
        SELECT 
            l.*,
            u.full_name as user_name,
            t.name as tenant_name
        FROM api_logs l
        LEFT JOIN users u ON l.user_id = u.id
        LEFT JOIN tenants t ON l.tenant_id = t.id
        ORDER BY l.created_at DESC
        LIMIT 20
    ");
    $api_logs = $stmt->fetchAll();
} catch (Exception $e) {
    // Continue
}

// ============================================================
// FETCH TENANTS FOR FILTER
// ============================================================
$tenants = [];
try {
    $stmt = $db->query("SELECT id, name FROM tenants WHERE deleted_at IS NULL ORDER BY name");
    $tenants = $stmt->fetchAll();
} catch (Exception $e) {
    // Continue
}

// ============================================================
// FETCH STATISTICS
// ============================================================
$stats = [
    'total_keys' => 0,
    'active_keys' => 0,
    'revoked_keys' => 0,
    'total_requests' => 0,
    'avg_response_time' => 0,
    'success_rate' => 0
];

try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM api_keys");
    $stats['total_keys'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM api_keys WHERE is_active = 1");
    $stats['active_keys'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM api_keys WHERE is_active = 0");
    $stats['revoked_keys'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM api_logs");
    $stats['total_requests'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT AVG(duration_ms) as avg FROM api_logs");
    $stats['avg_response_time'] = round($stmt->fetch()['avg'] ?? 0, 2);
    
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN response_status >= 200 AND response_status < 300 THEN 1 ELSE 0 END) as success
        FROM api_logs
    ");
    $result = $stmt->fetch();
    $stats['success_rate'] = $result['total'] > 0 ? round(($result['success'] / $result['total']) * 100, 1) : 0;
} catch (Exception $e) {
    // Continue
}

// Get user info
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       API MANAGEMENT - PRO STYLES
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
    .btn-sm.success {
        background: #ECFDF5;
        color: #065F46;
    }
    .btn-sm.success:hover {
        background: #D1FAE5;
    }
    .btn-sm.danger {
        background: #FEF2F2;
        color: #991B1B;
    }
    .btn-sm.danger:hover {
        background: #FEE2E2;
    }
    .btn-sm.warning {
        background: #FFFBEB;
        color: #92400E;
    }
    .btn-sm.warning:hover {
        background: #FEF3C7;
    }
    .btn-sm.info {
        background: #EFF6FF;
        color: #1E40AF;
    }
    .btn-sm.info:hover {
        background: #DBEAFE;
    }
    .btn-sm.purple {
        background: #F5F3FF;
        color: #5B21B6;
    }
    .btn-sm.purple:hover {
        background: #EDE9FE;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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
    .stat-item .number.green {
        color: var(--secondary);
    }
    .stat-item .number.red {
        color: var(--danger);
    }
    .stat-item .number.yellow {
        color: var(--warning);
    }
    .stat-item .number.purple {
        color: #8B5CF6;
    }
    .stat-item .number.blue {
        color: #3B82F6;
    }
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
    .table-container .table-header .table-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
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
    .status-badge.active {
        background: #ECFDF5;
        color: #065F46;
    }
    .status-badge.active .dot {
        background: #10B981;
    }
    .status-badge.revoked {
        background: #FEF2F2;
        color: #991B1B;
    }
    .status-badge.revoked .dot {
        background: #EF4444;
    }
    .status-badge.expired {
        background: #FFFBEB;
        color: #92400E;
    }
    .status-badge.expired .dot {
        background: #F59E0B;
    }
    
    .method-tag {
        display: inline-block;
        padding: 1px 8px;
        border-radius: 4px;
        font-size: 0.6rem;
        font-weight: 700;
        font-family: monospace;
    }
    .method-tag.GET {
        background: #EFF6FF;
        color: #1E40AF;
    }
    .method-tag.POST {
        background: #ECFDF5;
        color: #065F46;
    }
    .method-tag.PUT {
        background: #FFFBEB;
        color: #92400E;
    }
    .method-tag.DELETE {
        background: #FEF2F2;
        color: #991B1B;
    }
    .method-tag.PATCH {
        background: #F5F3FF;
        color: #5B21B6;
    }
    
    .status-code {
        display: inline-block;
        padding: 1px 8px;
        border-radius: 4px;
        font-size: 0.6rem;
        font-weight: 700;
        font-family: monospace;
    }
    .status-code.success {
        background: #ECFDF5;
        color: #065F46;
    }
    .status-code.error {
        background: #FEF2F2;
        color: #991B1B;
    }
    .status-code.warning {
        background: #FFFBEB;
        color: #92400E;
    }
    
    .api-key-display {
        font-family: 'Courier New', monospace;
        font-size: 0.75rem;
        background: var(--gray-50);
        padding: 2px 8px;
        border-radius: 4px;
        border: 1px solid var(--gray-200);
        word-break: break-all;
        display: inline-block;
        max-width: 200px;
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
    .empty-state-pro p {
        font-size: 0.85rem;
        color: var(--gray-400);
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
        max-width: 540px;
        width: 100%;
        padding: 28px 32px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        animation: modalIn 0.25s ease;
        max-height: 90vh;
        overflow-y: auto;
    }
    @keyframes modalIn {
        from {
            transform: scale(0.95) translateY(10px);
            opacity: 0;
        }
        to {
            transform: scale(1) translateY(0);
            opacity: 1;
        }
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
        min-height: 60px;
    }
    .modal .permissions-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }
    .modal .permissions-grid .perm-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 10px;
        background: var(--gray-50);
        border-radius: 6px;
        font-size: 0.8rem;
    }
    .modal .permissions-grid .perm-item input[type="checkbox"] {
        width: 16px;
        height: 16px;
        accent-color: var(--primary);
        cursor: pointer;
    }
    .modal .permissions-grid .perm-item label {
        cursor: pointer;
        font-weight: 400;
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
    
    .api-key-copy {
        background: var(--gray-50);
        border: 1px solid var(--gray-200);
        border-radius: 8px;
        padding: 12px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin: 16px 0;
    }
    .api-key-copy .key {
        font-family: 'Courier New', monospace;
        font-size: 0.85rem;
        color: var(--primary);
        font-weight: 600;
        word-break: break-all;
    }
    .api-key-copy .copy-btn {
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 6px;
        padding: 4px 12px;
        font-size: 0.7rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        font-weight: 500;
        white-space: nowrap;
    }
    .api-key-copy .copy-btn:hover {
        background: var(--primary-dark);
    }
    .api-key-copy .copy-btn.copied {
        background: var(--secondary);
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
    .toast.success {
        background: var(--secondary);
    }
    .toast.error {
        background: var(--danger);
    }
    .toast.info {
        background: var(--info);
    }
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(100px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .filter-bar-pro {
            flex-direction: column;
            align-items: stretch;
        }
        .filter-bar-pro .search-wrap {
            min-width: auto;
        }
        .filter-bar-pro select {
            width: 100%;
        }
        .table-container {
            overflow-x: auto;
        }
        .data-table {
            font-size: 0.78rem;
        }
        .data-table th,
        .data-table td {
            padding: 6px 10px;
        }
        .pagination-pro {
            flex-direction: column;
            align-items: center;
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .modal {
            padding: 20px;
            margin: 10px;
        }
        .permissions-grid {
            grid-template-columns: 1fr;
        }
        .api-key-copy {
            flex-direction: column;
            align-items: stretch;
        }
    }
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .stat-item {
            padding: 10px 12px;
        }
        .stat-item .number {
            font-size: 1.2rem;
        }
        .data-table th,
        .data-table td {
            padding: 4px 8px;
            font-size: 0.7rem;
        }
        .modal .form-actions {
            flex-direction: column;
        }
        .modal .form-actions .btn {
            width: 100%;
            justify-content: center;
        }
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
                    <?php if (!empty($action_result['api_key'])): ?>
                        <div style="margin-top:8px;background:rgba(255,255,255,0.15);padding:8px 12px;border-radius:6px;font-size:0.8rem;font-family:monospace;word-break:break-all;">
                            <?php echo htmlspecialchars($action_result['api_key']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-code" style="color:var(--primary);margin-right:8px;"></i> API Management
                    <small>Manage API keys and monitor API usage</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button onclick="openModal('createApiKeyModal')" class="btn-primary">
                    <i class="fas fa-plus-circle"></i> Create API Key
                </button>
                <button onclick="refreshPage()" class="btn-outline">
                    <i class="fas fa-sync"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-item"><div class="number"><?php echo number_format($stats['total_keys']); ?></div><div class="label">Total Keys</div></div>
            <div class="stat-item"><div class="number green"><?php echo number_format($stats['active_keys']); ?></div><div class="label">Active</div></div>
            <div class="stat-item"><div class="number red"><?php echo number_format($stats['revoked_keys']); ?></div><div class="label">Revoked</div></div>
            <div class="stat-item"><div class="number purple"><?php echo number_format($stats['total_requests']); ?></div><div class="label">Total Requests</div></div>
            <div class="stat-item"><div class="number blue"><?php echo number_format($stats['avg_response_time'], 2); ?> ms</div><div class="label">Avg Response Time</div></div>
            <div class="stat-item"><div class="number green"><?php echo number_format($stats['success_rate'], 1); ?>%</div><div class="label">Success Rate</div></div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar-pro">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:10px;flex:1;align-items:center;width:100%;">
                <div class="search-wrap" style="flex:1;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search API keys..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="revoked" <?php echo $status_filter === 'revoked' ? 'selected' : ''; ?>>Revoked</option>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
                <?php if (!empty($search) || !empty($status_filter)): ?>
                    <a href="api-management.php" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- API Keys Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-key" style="color:var(--primary);"></i> API Keys
                    <span class="count"><?php echo number_format($total_keys); ?></span>
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Name</th>
                        <th>Prefix</th>
                        <th>Tenant</th>
                        <th>Rate Limit</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th style="width:100px;text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($api_keys) > 0): ?>
                        <?php foreach ($api_keys as $index => $key): ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td>
                                    <div style="font-weight:500;font-size:0.85rem;"><?php echo htmlspecialchars($key['name']); ?></div>
                                    <div style="font-size:0.65rem;color:var(--gray-400);">
                                        Created by: <?php echo htmlspecialchars($key['created_by_name'] ?? 'System'); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="api-key-display"><?php echo htmlspecialchars($key['key_prefix']); ?>_****</span>
                                </td>
                                <td>
                                    <?php if (!empty($key['tenant_name'])): ?>
                                        <span style="font-size:0.78rem;background:var(--gray-100);padding:2px 10px;border-radius:12px;color:var(--gray-600);">
                                            <?php echo htmlspecialchars($key['tenant_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="font-size:0.75rem;color:var(--gray-400);">All Tenants</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.8rem;">
                                    <?php echo number_format($key['rate_limit']); ?> / hour
                                </td>
                                <td>
                                    <?php
                                    $status_class = 'active';
                                    $status_label = 'Active';
                                    if (!$key['is_active']) {
                                        $status_class = 'revoked';
                                        $status_label = 'Revoked';
                                    } elseif ($key['expires_at'] && strtotime($key['expires_at']) < time()) {
                                        $status_class = 'expired';
                                        $status_label = 'Expired';
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <span class="dot"></span>
                                        <?php echo $status_label; ?>
                                    </span>
                                </td>
                                <td style="font-size:0.75rem;color:var(--gray-500);">
                                    <?php echo date('M j, Y', strtotime($key['created_at'])); ?>
                                    <?php if ($key['expires_at']): ?>
                                        <div style="font-size:0.6rem;color:var(--gray-400);">
                                            Expires: <?php echo date('M j, Y', strtotime($key['expires_at'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display:flex;gap:4px;flex-wrap:wrap;justify-content:center;">
                                        <?php if ($key['is_active']): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="revoke_key">
                                                <input type="hidden" name="id" value="<?php echo $key['id']; ?>">
                                                <button type="submit" class="btn-sm warning" onclick="return confirm('Revoke this API key?')" title="Revoke">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="activate_key">
                                                <input type="hidden" name="id" value="<?php echo $key['id']; ?>">
                                                <button type="submit" class="btn-sm success" onclick="return confirm('Activate this API key?')" title="Activate">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="delete_key">
                                            <input type="hidden" name="id" value="<?php echo $key['id']; ?>">
                                            <button type="submit" class="btn-sm danger" onclick="return confirm('Delete this API key? This cannot be undone.')" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state-pro">
                                    <i class="fas fa-key"></i>
                                    <h4>No API keys found</h4>
                                    <p>Create your first API key to enable external integrations.</p>
                                    <button onclick="openModal('createApiKeyModal')" class="btn-primary" style="margin-top:12px;">
                                        <i class="fas fa-plus-circle"></i> Create API Key
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
                    Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $limit, $total_keys); ?></strong> of <strong><?php echo number_format($total_keys); ?></strong> API keys
                </div>
                <div class="pages">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    if ($start_page > 1) {
                        echo '<a href="?page=1&search=' . urlencode($search) . '&status=' . urlencode($status_filter) . '">1</a>';
                        if ($start_page > 2)
                            echo '<span>…</span>';
                    }
                    for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor;
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1)
                            echo '<span>…</span>';
                        echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search) . '&status=' . urlencode($status_filter) . '">' . $total_pages . '</a>';
                    }
                    ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- API Logs Section -->
        <div style="margin-top:24px;">
            <h3 style="font-size:1rem;font-weight:600;margin-bottom:12px;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-history" style="color:var(--primary);"></i> Recent API Requests
                <span style="font-size:0.75rem;font-weight:400;color:var(--gray-400);">(Last 20 requests)</span>
            </h3>

            <div class="table-container">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-list" style="color:var(--primary);"></i> API Logs
                        <span class="count"><?php echo count($api_logs); ?></span>
                    </div>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th>Endpoint</th>
                            <th>User</th>
                            <th>Tenant</th>
                            <th>Status</th>
                            <th>Duration</th>
                            <th>IP</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($api_logs) > 0): ?>
                            <?php foreach ($api_logs as $log): ?>
                                <tr>
                                    <td>
                                        <span class="method-tag <?php echo $log['method']; ?>">
                                            <?php echo htmlspecialchars($log['method']); ?>
                                        </span>
                                    </td>
                                    <td style="font-size:0.78rem;max-width:200px;word-wrap:break-word;">
                                        <?php echo htmlspecialchars($log['endpoint']); ?>
                                    </td>
                                    <td style="font-size:0.78rem;">
                                        <?php echo htmlspecialchars($log['user_name'] ?? 'Anonymous'); ?>
                                    </td>
                                    <td style="font-size:0.78rem;">
                                        <?php echo htmlspecialchars($log['tenant_name'] ?? 'Global'); ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = ($log['response_status'] >= 200 && $log['response_status'] < 300) ? 'success' : 'error';
                                        ?>
                                        <span class="status-code <?php echo $status_class; ?>">
                                            <?php echo $log['response_status'] ?? '—'; ?>
                                        </span>
                                    </td>
                                    <td style="font-size:0.75rem;color:var(--gray-500);">
                                        <?php echo $log['duration_ms'] ?? 0; ?> ms
                                    </td>
                                    <td style="font-size:0.7rem;color:var(--gray-400);font-family:monospace;">
                                        <?php echo htmlspecialchars($log['ip_address'] ?? '—'); ?>
                                    </td>
                                    <td style="font-size:0.7rem;color:var(--gray-400);white-space:nowrap;">
                                        <?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center;padding:30px;color:var(--gray-500);">
                                    <i class="fas fa-code" style="font-size:1.5rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                                    No API requests logged yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Create API Key Modal -->
<div class="modal-overlay" id="createApiKeyModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle" style="color:var(--primary);"></i> Create API Key</h3>
            <button class="close-btn" onclick="closeModal('createApiKeyModal')">&times;</button>
        </div>
        <form method="POST" action="" id="createApiKeyForm">
            <input type="hidden" name="action" value="create_key">
            
            <div class="form-group">
                <label>Key Name <span class="required">*</span></label>
                <input type="text" name="name" placeholder="e.g., Mobile App - Production" required>
                <div class="help-text">A descriptive name to identify this API key.</div>
            </div>
            
            <div class="form-group">
                <label>Tenant (Optional)</label>
                <select name="tenant_id">
                    <option value="">All Tenants</option>
                    <?php foreach ($tenants as $t): ?>
                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="help-text">Restrict this API key to a specific tenant.</div>
            </div>
            
            <div class="form-group">
                <label>Rate Limit (requests per hour)</label>
                <input type="number" name="rate_limit" value="1000" min="1">
                <div class="help-text">Maximum number of requests allowed per hour.</div>
            </div>
            
            <div class="form-group">
                <label>Expiration Date (Optional)</label>
                <input type="date" name="expires_at">
                <div class="help-text">Leave empty for no expiration.</div>
            </div>
            
            <div class="form-group">
                <label>Permissions</label>
                <div class="permissions-grid">
                    <div class="perm-item">
                        <input type="checkbox" name="permissions[read]" id="perm_read" value="1" checked>
                        <label for="perm_read">Read</label>
                    </div>
                    <div class="perm-item">
                        <input type="checkbox" name="permissions[write]" id="perm_write" value="1" checked>
                        <label for="perm_write">Write</label>
                    </div>
                    <div class="perm-item">
                        <input type="checkbox" name="permissions[delete]" id="perm_delete" value="1">
                        <label for="perm_delete">Delete</label>
                    </div>
                    <div class="perm-item">
                        <input type="checkbox" name="permissions[admin]" id="perm_admin" value="1">
                        <label for="perm_admin">Admin</label>
                    </div>
                </div>
                <div class="help-text">Select the permissions for this API key.</div>
            </div>
            
            <div style="background:#F5F3FF;padding:12px 16px;border-radius:8px;color:#5B21B6;font-size:0.85rem;border:1px solid #EDE9FE;margin-bottom:16px;">
                <i class="fas fa-info-circle"></i>
                <strong>Note:</strong> The API key will be shown only once after creation. Please copy and store it securely.
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createApiKeyModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Key</button>
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
    // REFRESH PAGE
    // ============================================================
    function refreshPage() {
        location.reload();
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