<?php
// ============================================================
// TENANT MANAGEMENT - SUPER ADMINISTRATOR
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
// HANDLE ACTIONS (POST Requests)
// ============================================================
$action_result = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $tenant_id = isset($_POST['tenant_id']) ? (int)$_POST['tenant_id'] : 0;
    
    try {
        switch ($action) {
            case 'suspend':
                $stmt = $db->prepare("UPDATE tenants SET is_active = 0, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL");
                $stmt->execute([$tenant_id]);
                if ($stmt->rowCount() > 0) {
                    $action_result = ['success' => true, 'message' => 'Tenant suspended successfully.'];
                    logActivity(SessionManager::get('user_id'), 'tenant_suspended', "Suspended tenant ID: $tenant_id");
                }
                break;
                
            case 'activate':
                $stmt = $db->prepare("UPDATE tenants SET is_active = 1, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL");
                $stmt->execute([$tenant_id]);
                if ($stmt->rowCount() > 0) {
                    $action_result = ['success' => true, 'message' => 'Tenant activated successfully.'];
                    logActivity(SessionManager::get('user_id'), 'tenant_activated', "Activated tenant ID: $tenant_id");
                }
                break;
                
            case 'delete':
                // Soft delete
                $stmt = $db->prepare("UPDATE tenants SET deleted_at = NOW() WHERE id = ?");
                $stmt->execute([$tenant_id]);
                if ($stmt->rowCount() > 0) {
                    $action_result = ['success' => true, 'message' => 'Tenant deleted successfully.'];
                    logActivity(SessionManager::get('user_id'), 'tenant_deleted', "Deleted tenant ID: $tenant_id");
                }
                break;
                
            case 'reset_password':
                $new_password = generateRandomPassword(12);
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE tenant_id = ? AND role_id IN (SELECT id FROM roles WHERE level = 'client_admin')");
                $stmt->execute([$password_hash, $tenant_id]);
                
                if ($stmt->rowCount() > 0) {
                    // Get admin email
                    $stmt = $db->prepare("SELECT email FROM users WHERE tenant_id = ? AND role_id IN (SELECT id FROM roles WHERE level = 'client_admin') LIMIT 1");
                    $stmt->execute([$tenant_id]);
                    $admin = $stmt->fetch();
                    
                    if ($admin && !empty($admin['email'])) {
                        // Send email with new password
                        $subject = "Admin Password Reset - " . APP_NAME;
                        $message = "Your admin password has been reset.\n\nNew Password: " . $new_password . "\n\nPlease change your password after logging in.";
                        sendEmail($admin['email'], $subject, $message);
                    }
                    
                    $action_result = ['success' => true, 'message' => 'Admin password reset successfully. New password sent via email.'];
                    logActivity(SessionManager::get('user_id'), 'tenant_password_reset', "Reset admin password for tenant ID: $tenant_id");
                } else {
                    $action_result = ['success' => false, 'message' => 'No admin user found for this tenant.'];
                }
                break;
                
            case 'impersonate':
                // Login as tenant admin
                $stmt = $db->prepare("SELECT u.id, u.full_name, u.email, u.role_id, r.level, r.slug FROM users u JOIN roles r ON u.role_id = r.id WHERE u.tenant_id = ? AND r.level = 'client_admin' LIMIT 1");
                $stmt->execute([$tenant_id]);
                $admin = $stmt->fetch();
                
                if ($admin) {
                    // Store original user ID for return
                    $_SESSION['impersonating'] = SessionManager::get('user_id');
                    $_SESSION['original_role'] = SessionManager::get('role_level');
                    
                    // Set session as tenant admin
                    SessionManager::set('user_id', $admin['id']);
                    SessionManager::set('user_name', $admin['full_name']);
                    SessionManager::set('user_email', $admin['email']);
                    SessionManager::set('user_role', $admin['role_id']);
                    SessionManager::set('role_level', $admin['level']);
                    SessionManager::set('role_slug', $admin['slug']);
                    SessionManager::set('role', $admin['level']);
                    SessionManager::set('tenant_id', $tenant_id);
                    SessionManager::set('impersonating_tenant', true);
                    
                    header('Location: ../client-admin/');
                    exit();
                } else {
                    $action_result = ['success' => false, 'message' => 'No admin user found to impersonate.'];
                }
                break;
                
            case 'upload_logo':
                if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../../uploads/tenants/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $extension = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                    $filename = 'tenant_' . time() . '_' . uniqid() . '.' . $extension;
                    $filepath = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $filepath)) {
                        $logo_url = '/uploads/tenants/' . $filename;
                        $stmt = $db->prepare("UPDATE tenants SET logo_url = ? WHERE id = ?");
                        $stmt->execute([$logo_url, $tenant_id]);
                        $action_result = ['success' => true, 'message' => 'Logo uploaded successfully.'];
                        logActivity(SessionManager::get('user_id'), 'tenant_logo_uploaded', "Uploaded logo for tenant ID: $tenant_id");
                    } else {
                        $action_result = ['success' => false, 'message' => 'Failed to upload logo.'];
                    }
                } else {
                    $action_result = ['success' => false, 'message' => 'No file uploaded or upload error.'];
                }
                break;
        }
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// ============================================================
// FETCH TENANTS WITH PAGINATION & FILTERS
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 15;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$plan_filter = isset($_GET['plan']) ? $_GET['plan'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

// Build WHERE clause
$where_conditions = ["t.deleted_at IS NULL"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(t.name LIKE ? OR t.slug LIKE ? OR t.contact_email LIKE ? OR t.contact_phone LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    if ($status_filter === 'active') {
        $where_conditions[] = "t.is_active = 1";
    } elseif ($status_filter === 'suspended') {
        $where_conditions[] = "t.is_active = 0";
    } elseif ($status_filter === 'trial') {
        $where_conditions[] = "t.subscription_status = 'trial'";
    } elseif ($status_filter === 'expired') {
        $where_conditions[] = "t.subscription_status = 'expired'";
    } elseif ($status_filter === 'active_subscription') {
        $where_conditions[] = "t.subscription_status = 'active'";
    }
}

if (!empty($plan_filter)) {
    $where_conditions[] = "t.subscription_plan = ?";
    $params[] = $plan_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Count total
$count_sql = "SELECT COUNT(*) as total FROM tenants t WHERE $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_tenants = $stmt->fetch()['total'] ?? 0;
$total_pages = ceil($total_tenants / $limit);

// Fetch tenants
$sql = "SELECT 
            t.*,
            u.full_name as admin_name,
            u.email as admin_email,
            (SELECT COUNT(*) FROM users WHERE tenant_id = t.id AND deleted_at IS NULL) as user_count
        FROM tenants t
        LEFT JOIN users u ON t.created_by = u.id
        WHERE $where_clause
        ORDER BY t.$sort_by $sort_order
        LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$tenants = $stmt->fetchAll();

// ============================================================
// FETCH STATISTICS FOR SUMMARY CARDS
// ============================================================
$stats = [
    'total' => 0,
    'active' => 0,
    'suspended' => 0,
    'trial' => 0,
    'expired' => 0,
    'total_users' => 0
];

try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM tenants WHERE deleted_at IS NULL");
    $stats['total'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->query("SELECT COUNT(*) as total FROM tenants WHERE is_active = 1 AND deleted_at IS NULL");
    $stats['active'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->query("SELECT COUNT(*) as total FROM tenants WHERE is_active = 0 AND deleted_at IS NULL");
    $stats['suspended'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->query("SELECT COUNT(*) as total FROM tenants WHERE subscription_status = 'trial' AND deleted_at IS NULL");
    $stats['trial'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->query("SELECT COUNT(*) as total FROM tenants WHERE subscription_status = 'expired' AND deleted_at IS NULL");
    $stats['expired'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE deleted_at IS NULL");
    $stats['total_users'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    // Log error but continue
}

// Get user info
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       TENANT MANAGEMENT SPECIFIC STYLES
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
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .btn-sm.info { background: #EFF6FF; color: var(--info); }
    .btn-sm.info:hover { background: #DBEAFE; }
    .btn-sm.success { background: #ECFDF5; color: var(--secondary); }
    .btn-sm.success:hover { background: #D1FAE5; }
    .btn-sm.warning { background: #FFFBEB; color: var(--warning); }
    .btn-sm.warning:hover { background: #FEF3C7; }
    .btn-sm.danger { background: #FEF2F2; color: var(--danger); }
    .btn-sm.danger:hover { background: #FEE2E2; }
    .btn-sm.primary { background: #EFF6FF; color: var(--primary); }
    .btn-sm.primary:hover { background: #DBEAFE; }
    .btn-sm.purple { background: #F5F3FF; color: #8B5CF6; }
    .btn-sm.purple:hover { background: #EDE9FE; }

    .filter-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        margin-bottom: 16px;
        padding: 12px 16px;
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
    }
    .filter-bar .search-input {
        flex: 1;
        min-width: 180px;
        padding: 8px 14px;
        border: 1px solid var(--gray-200);
        border-radius: 8px;
        font-family: 'Inter', sans-serif;
        font-size: 0.85rem;
        transition: var(--transition);
        background: var(--gray-50);
    }
    .filter-bar .search-input:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
    }
    .filter-bar select {
        padding: 8px 14px;
        border: 1px solid var(--gray-200);
        border-radius: 8px;
        font-family: 'Inter', sans-serif;
        font-size: 0.82rem;
        background: var(--gray-50);
        cursor: pointer;
        transition: var(--transition);
        min-width: 120px;
    }
    .filter-bar select:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
    }
    .filter-bar .filter-actions {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }

    .tenants-table-wrapper {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        overflow: hidden;
        box-shadow: var(--shadow);
    }
    .tenants-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    .tenants-table thead {
        background: var(--gray-50);
        border-bottom: 1px solid var(--gray-200);
    }
    .tenants-table th {
        padding: 12px 14px;
        text-align: left;
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--gray-500);
        white-space: nowrap;
        cursor: pointer;
        user-select: none;
        position: sticky;
        top: 0;
        background: var(--gray-50);
        z-index: 2;
    }
    .tenants-table th:hover {
        color: var(--gray-700);
    }
    .tenants-table td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--gray-100);
        vertical-align: middle;
    }
    .tenants-table tbody tr:hover {
        background: var(--gray-50);
    }
    .tenants-table tbody tr:last-child td {
        border-bottom: none;
    }

    .tenant-logo {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        object-fit: cover;
        background: var(--gray-100);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        color: var(--gray-500);
        font-size: 0.8rem;
        overflow: hidden;
    }
    .tenant-logo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .status-badge.active { background: #ECFDF5; color: #065F46; }
    .status-badge.suspended { background: #FEF2F2; color: #991B1B; }
    .status-badge.trial { background: #FFFBEB; color: #92400E; }
    .status-badge.expired { background: #FEF2F2; color: #991B1B; }
    .status-badge.premium { background: #EFF6FF; color: #1E40AF; }
    .status-badge.enterprise { background: #F5F3FF; color: #5B21B6; }
    .status-badge.standard { background: #ECFDF5; color: #065F46; }
    .status-badge.basic { background: #FFFBEB; color: #92400E; }
    .status-badge.free { background: var(--gray-100); color: var(--gray-500); }

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
        border-radius: 10px;
        box-shadow: var(--shadow-hover);
        border: 1px solid var(--gray-200);
        min-width: 180px;
        padding: 4px;
        display: none;
        z-index: 50;
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
        border-radius: 6px;
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
    }
    .action-dropdown .dropdown-menu .divider {
        height: 1px;
        background: var(--gray-100);
        margin: 4px 8px;
    }

    .pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        padding: 14px 16px;
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        margin-top: 16px;
    }
    .pagination .info {
        font-size: 0.82rem;
        color: var(--gray-500);
    }
    .pagination .pages {
        display: flex;
        gap: 4px;
    }
    .pagination .pages a,
    .pagination .pages span {
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 0.82rem;
        text-decoration: none;
        color: var(--gray-600);
        transition: var(--transition);
        min-width: 32px;
        text-align: center;
    }
    .pagination .pages a:hover {
        background: var(--gray-100);
    }
    .pagination .pages .active {
        background: var(--primary);
        color: white;
    }
    .pagination .pages .disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }

    /* Modal */
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.4);
        z-index: 300;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .modal-overlay.active { display: flex; }
    .modal {
        background: white;
        border-radius: var(--radius);
        max-width: 480px;
        width: 100%;
        padding: 28px 32px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        animation: modalIn 0.25s ease;
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
    }
    .modal .modal-header .close-btn {
        background: none;
        border: none;
        font-size: 1.4rem;
        color: var(--gray-400);
        cursor: pointer;
        transition: var(--transition);
    }
    .modal .modal-header .close-btn:hover {
        color: var(--gray-600);
    }
    .modal .modal-body { margin-bottom: 16px; }
    .modal .modal-footer {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }
    .modal .modal-footer .btn {
        padding: 8px 20px;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .modal .modal-footer .btn-secondary {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .modal .modal-footer .btn-secondary:hover {
        background: var(--gray-200);
    }
    .modal .modal-footer .btn-danger {
        background: var(--danger);
        color: white;
    }
    .modal .modal-footer .btn-danger:hover {
        background: #DC2626;
    }
    .modal .modal-footer .btn-primary {
        background: var(--primary);
        color: white;
    }
    .modal .modal-footer .btn-primary:hover {
        background: var(--primary-dark);
    }

    .file-upload-area {
        border: 2px dashed var(--gray-200);
        border-radius: 10px;
        padding: 24px;
        text-align: center;
        cursor: pointer;
        transition: var(--transition);
        background: var(--gray-50);
    }
    .file-upload-area:hover {
        border-color: var(--primary);
        background: #EFF6FF;
    }
    .file-upload-area i {
        font-size: 2rem;
        color: var(--gray-400);
        display: block;
        margin-bottom: 8px;
    }
    .file-upload-area p {
        font-size: 0.85rem;
        color: var(--gray-500);
    }
    .file-upload-area input[type="file"] {
        display: none;
    }

    /* Toast notifications */
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
    .toast.info { background: var(--info); }
    @keyframes slideIn {
        from { transform: translateX(100px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }

    .empty-state {
        text-align: center;
        padding: 48px 20px;
        color: var(--gray-500);
    }
    .empty-state i {
        font-size: 3rem;
        color: var(--gray-300);
        display: block;
        margin-bottom: 12px;
    }
    .empty-state h4 {
        color: var(--gray-700);
        margin-bottom: 4px;
    }

    @media (max-width: 768px) {
        .tenants-table-wrapper { overflow-x: auto; }
        .tenants-table { font-size: 0.78rem; }
        .tenants-table th, .tenants-table td { padding: 8px 10px; }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar .search-input { min-width: auto; }
        .filter-bar .filter-actions { justify-content: flex-end; }
        .page-header { flex-direction: column; align-items: flex-start; }
        .pagination { flex-direction: column; align-items: center; }
        .modal { padding: 20px; margin: 10px; }
        .toast { min-width: auto; max-width: 90%; }
    }
    @media (max-width: 480px) {
        .tenants-table th, .tenants-table td { padding: 6px 8px; font-size: 0.7rem; }
        .action-dropdown .dropdown-menu { right: -10px; min-width: 160px; }
        .btn-sm { font-size: 0.6rem; padding: 3px 6px; }
    }
</style>

<main class="main-content">
    <!-- Fixed Header -->
    <?php include 'includes/header.php'; ?>
    
    <!-- Main Content Inner -->
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-building" style="color:var(--primary);margin-right:8px;"></i> Tenant Management
                    <small>Manage all client organizations on the platform</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="tenants-export.php" class="btn-outline">
                    <i class="fas fa-file-export"></i> Export
                </a>
                <a href="tenants-create.php" class="btn-primary">
                    <i class="fas fa-plus-circle"></i> Create Tenant
                </a>
            </div>
        </div>

        <!-- Stats Summary Cards -->
        <div class="stats-grid" style="margin-bottom:16px;">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-building"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
                <div class="stat-label">Total Tenants</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['active']); ?></div>
                <div class="stat-label">Active</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-ban"></i></div>
                <div class="stat-number"><?php echo number_format($stats['suspended']); ?></div>
                <div class="stat-label">Suspended</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo number_format($stats['trial']); ?></div>
                <div class="stat-label">Trial</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_users']); ?></div>
                <div class="stat-label">Total Users</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:10px;flex:1;align-items:center;">
                <input type="text" name="search" class="search-input" placeholder="Search by name, email, phone..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    <option value="trial" <?php echo $status_filter === 'trial' ? 'selected' : ''; ?>>Trial</option>
                    <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                    <option value="active_subscription" <?php echo $status_filter === 'active_subscription' ? 'selected' : ''; ?>>Active Subscription</option>
                </select>
                <select name="plan">
                    <option value="">All Plans</option>
                    <option value="free" <?php echo $plan_filter === 'free' ? 'selected' : ''; ?>>Free</option>
                    <option value="basic" <?php echo $plan_filter === 'basic' ? 'selected' : ''; ?>>Basic</option>
                    <option value="standard" <?php echo $plan_filter === 'standard' ? 'selected' : ''; ?>>Standard</option>
                    <option value="premium" <?php echo $plan_filter === 'premium' ? 'selected' : ''; ?>>Premium</option>
                    <option value="enterprise" <?php echo $plan_filter === 'enterprise' ? 'selected' : ''; ?>>Enterprise</option>
                </select>
                <button type="submit" class="btn-primary" style="padding:8px 16px;font-size:0.82rem;">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <?php if (!empty($search) || !empty($status_filter) || !empty($plan_filter)): ?>
                    <a href="tenants.php" class="btn-outline" style="padding:8px 14px;font-size:0.82rem;">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Toast Notifications -->
        <?php if (!empty($action_result['message'])): ?>
        <div style="margin-bottom:12px;">
            <div class="toast <?php echo $action_result['success'] ? 'success' : 'error'; ?>" style="position:static;animation:none;max-width:100%;">
                <i class="fas <?php echo $action_result['success'] ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($action_result['message']); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tenants Table -->
        <div class="tenants-table-wrapper">
            <table class="tenants-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Organization</th>
                        <th>Admin</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th>Users</th>
                        <th>Expiry</th>
                        <th style="width:80px;text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($tenants) > 0): ?>
                        <?php foreach ($tenants as $index => $tenant): ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <div class="tenant-logo">
                                            <?php if (!empty($tenant['logo_url'])): ?>
                                                <img src="<?php echo htmlspecialchars($tenant['logo_url']); ?>" alt="Logo">
                                            <?php else: ?>
                                                <?php echo strtoupper(substr($tenant['name'], 0, 2)); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div style="font-weight:600;font-size:0.85rem;"><?php echo htmlspecialchars($tenant['name']); ?></div>
                                            <div style="font-size:0.7rem;color:var(--gray-400);"><?php echo htmlspecialchars($tenant['slug']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight:500;font-size:0.82rem;"><?php echo htmlspecialchars($tenant['admin_name'] ?? 'N/A'); ?></div>
                                    <div style="font-size:0.7rem;color:var(--gray-400);"><?php echo htmlspecialchars($tenant['admin_email'] ?? ''); ?></div>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $tenant['subscription_plan']; ?>">
                                        <?php echo ucfirst($tenant['subscription_plan']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $status_class = $tenant['is_active'] ? 'active' : 'suspended';
                                    $status_label = $tenant['is_active'] ? 'Active' : 'Suspended';
                                    
                                    // Check subscription status
                                    if ($tenant['is_active'] && $tenant['subscription_status'] === 'trial') {
                                        $status_class = 'trial';
                                        $status_label = 'Trial';
                                    } elseif ($tenant['is_active'] && $tenant['subscription_status'] === 'expired') {
                                        $status_class = 'expired';
                                        $status_label = 'Expired';
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <i class="fas fa-circle" style="font-size:6px;"></i>
                                        <?php echo $status_label; ?>
                                    </span>
                                </td>
                                <td style="text-align:center;font-weight:600;"><?php echo $tenant['user_count'] ?? 0; ?></td>
                                <td style="font-size:0.8rem;">
                                    <?php 
                                    if (!empty($tenant['subscription_end'])) {
                                        echo date('M j, Y', strtotime($tenant['subscription_end']));
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="action-dropdown">
                                        <button class="dropdown-btn" onclick="toggleDropdown(this)">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a href="tenants-view.php?id=<?php echo $tenant['id']; ?>">
                                                <i class="fas fa-eye"></i> View Details
                                            </a>
                                            <a href="tenants-edit.php?id=<?php echo $tenant['id']; ?>">
                                                <i class="fas fa-edit"></i> Edit Tenant
                                            </a>
                                            <div class="divider"></div>
                                            <?php if ($tenant['is_active']): ?>
                                                <button onclick="confirmAction('suspend', <?php echo $tenant['id']; ?>, '<?php echo htmlspecialchars($tenant['name']); ?>')">
                                                    <i class="fas fa-pause"></i> Suspend
                                                </button>
                                            <?php else: ?>
                                                <button onclick="confirmAction('activate', <?php echo $tenant['id']; ?>, '<?php echo htmlspecialchars($tenant['name']); ?>')">
                                                    <i class="fas fa-play"></i> Activate
                                                </button>
                                            <?php endif; ?>
                                            <button onclick="confirmAction('reset_password', <?php echo $tenant['id']; ?>, '<?php echo htmlspecialchars($tenant['name']); ?>')">
                                                <i class="fas fa-key"></i> Reset Admin Password
                                            </button>
                                            <button onclick="confirmAction('impersonate', <?php echo $tenant['id']; ?>, '<?php echo htmlspecialchars($tenant['name']); ?>')">
                                                <i class="fas fa-user-secret"></i> Login as Tenant
                                            </button>
                                            <div class="divider"></div>
                                            <button onclick="openUploadModal(<?php echo $tenant['id']; ?>)" class="purple">
                                                <i class="fas fa-image"></i> Upload Logo
                                            </button>
                                            <button onclick="confirmAction('delete', <?php echo $tenant['id']; ?>, '<?php echo htmlspecialchars($tenant['name']); ?>')" class="danger">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-building"></i>
                                    <h4>No tenants found</h4>
                                    <p>Try adjusting your search or filters, or create a new tenant.</p>
                                    <a href="tenants-create.php" class="btn-primary" style="margin-top:12px;">
                                        <i class="fas fa-plus-circle"></i> Create Tenant
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <div class="info">
                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_tenants); ?> of <?php echo number_format($total_tenants); ?> tenants
            </div>
            <div class="pages">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&plan=<?php echo urlencode($plan_filter); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>

                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1) {
                    echo '<a href="?page=1&search=' . urlencode($search) . '&status=' . urlencode($status_filter) . '&plan=' . urlencode($plan_filter) . '">1</a>';
                    if ($start_page > 2) echo '<span>…</span>';
                }
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&plan=<?php echo urlencode($plan_filter); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor;
                
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<span>…</span>';
                    echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search) . '&status=' . urlencode($status_filter) . '&plan=' . urlencode($plan_filter) . '">' . $total_pages . '</a>';
                }
                ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&plan=<?php echo urlencode($plan_filter); ?>">
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

<!-- ============================================================
CONFIRMATION MODAL
============================================================ -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="confirmTitle">Confirm Action</h3>
            <button class="close-btn" onclick="closeModal('confirmModal')">&times;</button>
        </div>
        <div class="modal-body" id="confirmBody">
            <p>Are you sure you want to perform this action?</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('confirmModal')">Cancel</button>
            <button class="btn btn-danger" id="confirmActionBtn" onclick="executeAction()">Confirm</button>
        </div>
    </div>
</div>

<!-- ============================================================
UPLOAD LOGO MODAL
============================================================ -->
<div class="modal-overlay" id="uploadModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Upload Organization Logo</h3>
            <button class="close-btn" onclick="closeModal('uploadModal')">&times;</button>
        </div>
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_logo">
            <input type="hidden" name="tenant_id" id="uploadTenantId">
            <div class="modal-body">
                <div class="file-upload-area" onclick="document.getElementById('logoFile').click()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p>Click to upload logo or drag & drop</p>
                    <p style="font-size:0.7rem;color:var(--gray-400);">PNG, JPG, SVG (Max 2MB)</p>
                    <input type="file" name="logo" id="logoFile" accept="image/*" required>
                </div>
                <div id="filePreview" style="display:none;margin-top:12px;text-align:center;">
                    <img id="previewImage" src="#" alt="Preview" style="max-height:120px;border-radius:8px;border:1px solid var(--gray-200);">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('uploadModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Upload Logo</button>
            </div>
        </form>
    </div>
</div>

<script>
// ============================================================
// PRELOADER
// ============================================================
window.addEventListener('load', function() {
    const preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(() => {
            preloader.style.display = 'none';
        }, 600);
    }
});

// ============================================================
// SIDEBAR TOGGLE (mobile)
// ============================================================
const sidebar = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const dashboardHeader = document.getElementById('dashboardHeader');

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

window.addEventListener('resize', () => {
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
document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
    toggle.addEventListener('click', function(e) {
        e.preventDefault();
        const dropdownId = this.dataset.dropdown;
        const dropdown = document.getElementById(dropdownId);
        const chevron = this.querySelector('.chevron');
        
        if (dropdown) {
            dropdown.classList.toggle('open');
            if (chevron) chevron.classList.toggle('open');
        }
    });
});

// ============================================================
// PROFILE DROPDOWN
// ============================================================
const profileBtn = document.getElementById('profileBtn');
const profileMenu = document.getElementById('profileMenu');

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
// ACTION DROPDOWN
// ============================================================
function toggleDropdown(btn) {
    const menu = btn.nextElementSibling;
    const isOpen = menu.classList.contains('open');
    
    // Close all dropdowns
    document.querySelectorAll('.action-dropdown .dropdown-menu').forEach(m => m.classList.remove('open'));
    
    if (!isOpen) {
        menu.classList.toggle('open');
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.action-dropdown')) {
        document.querySelectorAll('.action-dropdown .dropdown-menu').forEach(m => m.classList.remove('open'));
    }
});

// ============================================================
// CONFIRMATION MODAL
// ============================================================
let pendingAction = null;

function confirmAction(action, tenantId, tenantName) {
    const modal = document.getElementById('confirmModal');
    const title = document.getElementById('confirmTitle');
    const body = document.getElementById('confirmBody');
    const btn = document.getElementById('confirmActionBtn');
    
    const actionLabels = {
        'suspend': { title: 'Suspend Tenant', body: `Are you sure you want to suspend <strong>${tenantName}</strong>? The tenant will not be able to access the platform.`, color: 'btn-warning' },
        'activate': { title: 'Activate Tenant', body: `Are you sure you want to activate <strong>${tenantName}</strong>? The tenant will regain full access.`, color: 'btn-primary' },
        'delete': { title: 'Delete Tenant', body: `Are you sure you want to delete <strong>${tenantName}</strong>? This action can be reversed.`, color: 'btn-danger' },
        'reset_password': { title: 'Reset Admin Password', body: `Are you sure you want to reset the admin password for <strong>${tenantName}</strong>? A new password will be sent via email.`, color: 'btn-primary' },
        'impersonate': { title: 'Login as Tenant', body: `You are about to login as <strong>${tenantName}</strong>. You will be able to perform actions on behalf of this tenant.`, color: 'btn-primary' }
    };
    
    const label = actionLabels[action] || actionLabels['suspend'];
    title.textContent = label.title;
    body.innerHTML = label.body;
    btn.className = `btn ${label.color}`;
    
    pendingAction = { action, tenantId };
    modal.classList.add('active');
}

function executeAction() {
    if (!pendingAction) return;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = pendingAction.action;
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'tenant_id';
    idInput.value = pendingAction.tenantId;
    
    form.appendChild(actionInput);
    form.appendChild(idInput);
    document.body.appendChild(form);
    form.submit();
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

// ============================================================
// UPLOAD LOGO MODAL
// ============================================================
function openUploadModal(tenantId) {
    document.getElementById('uploadTenantId').value = tenantId;
    document.getElementById('uploadModal').classList.add('active');
    document.getElementById('filePreview').style.display = 'none';
    document.getElementById('logoFile').value = '';
}

// File preview
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('logoFile');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const preview = document.getElementById('filePreview');
            const previewImg = document.getElementById('previewImage');
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
});

// ============================================================
// SEARCH - Live Database Search (for header search)
// ============================================================
const searchInput = document.getElementById('searchInput');
const searchResults = document.getElementById('searchResults');
let searchTimeout;

if (searchInput) {
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        
        clearTimeout(searchTimeout);
        
        if (query.length < 2) {
            if (searchResults) searchResults.classList.remove('active');
            return;
        }
        
        searchTimeout = setTimeout(() => {
            performSearch(query);
        }, 300);
    });

    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (searchResults) searchResults.classList.remove('active');
            this.blur();
        }
    });
}

function performSearch(query) {
    fetch(`search.php?q=${encodeURIComponent(query)}`, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        renderSearchResults(data);
    })
    .catch(() => {
        // Silently fail
    });
}

function renderSearchResults(data) {
    if (!searchResults) return;
    searchResults.innerHTML = '';
    
    if (!data || data.length === 0) {
        searchResults.innerHTML = `
            <div style="padding:12px;text-align:center;color:var(--gray-500);font-size:0.8rem;">
                <i class="fas fa-search" style="display:block;font-size:1.2rem;margin-bottom:4px;"></i>
                No results found
            </div>
        `;
        searchResults.classList.add('active');
        return;
    }
    
    data.forEach(item => {
        const div = document.createElement('a');
        div.className = 'result-item';
        div.href = item.url || '#';
        
        const icon = item.icon || 'fa-file';
        const type = item.type || '';
        const typeLabel = type.charAt(0).toUpperCase() + type.slice(1);
        
        div.innerHTML = `
            <i class="fas ${icon}"></i>
            <span class="text-truncate">${item.label || item.name || ''}</span>
            <span class="result-type">${typeLabel}</span>
        `;
        searchResults.appendChild(div);
    });
    
    searchResults.classList.add('active');
}

// Close search results on click outside
document.addEventListener('click', function(e) {
    const searchWrapper = document.querySelector('.search-wrapper');
    if (searchWrapper && !searchWrapper.contains(e.target)) {
        if (searchResults) searchResults.classList.remove('active');
    }
});

// ============================================================
// CHARTS (keep for compatibility)
// ============================================================
// Charts are included but not used on this page
// This prevents errors if chart elements don't exist
</script>
</body>
</html>