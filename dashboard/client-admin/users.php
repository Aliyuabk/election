<?php
// ============================================================
// USERS MANAGEMENT - CLIENT ADMIN (PROFESSIONAL)
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// ============================================================
// HELPER FUNCTION - Generate Random Password
// ============================================================
function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    $password = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }
    return $password;
}

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
// HANDLE ACTIONS
// ============================================================
$action_result = ['success' => false, 'message' => ''];
$show_modal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    try {
        switch ($action) {
            case 'suspend':
                $stmt = $db->prepare("UPDATE users SET status = 'suspended', updated_at = NOW() WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$id, $tenant_id]);
                $action_result = ['success' => true, 'message' => 'User suspended successfully.'];
                logActivity($user_id, 'user_suspended', "Suspended user ID: $id");
                break;
                
            case 'activate':
                $stmt = $db->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$id, $tenant_id]);
                $action_result = ['success' => true, 'message' => 'User activated successfully.'];
                logActivity($user_id, 'user_activated', "Activated user ID: $id");
                break;
                
            case 'archive':
                $stmt = $db->prepare("UPDATE users SET status = 'archived', updated_at = NOW() WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$id, $tenant_id]);
                $action_result = ['success' => true, 'message' => 'User archived successfully.'];
                logActivity($user_id, 'user_archived', "Archived user ID: $id");
                break;
                
            case 'delete':
                $stmt = $db->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$id, $tenant_id]);
                $action_result = ['success' => true, 'message' => 'User deleted successfully.'];
                logActivity($user_id, 'user_deleted', "Deleted user ID: $id");
                break;
                
            case 'reset_password':
                $new_password = generateRandomPassword(12);
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$password_hash, $id, $tenant_id]);
                
                if ($stmt->rowCount() > 0) {
                    $stmt = $db->prepare("SELECT email, first_name FROM users WHERE id = ?");
                    $stmt->execute([$id]);
                    $user = $stmt->fetch();
                    
                    if ($user && !empty($user['email'])) {
                        try {
                            $subject = "Password Reset - " . APP_NAME;
                            $message = "Dear {$user['first_name']},\n\n";
                            $message .= "Your password has been reset.\n\n";
                            $message .= "New Password: $new_password\n\n";
                            $message .= "Please change your password after logging in.\n\n";
                            $message .= "Best regards,\n" . APP_NAME . " Team";
                            sendEmail($user['email'], $subject, $message);
                            $action_result = ['success' => true, 'message' => 'Password reset successfully. New password sent via email.'];
                        } catch (Exception $e) {
                            $action_result = ['success' => true, 'message' => 'Password reset successfully but email could not be sent.'];
                            error_log("Password reset email failed: " . $e->getMessage());
                        }
                    } else {
                        $action_result = ['success' => true, 'message' => 'Password reset successfully.'];
                    }
                    
                    logActivity($user_id, 'user_password_reset', "Reset password for user ID: $id");
                }
                break;
                
            case 'bulk_import':
                if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Please select a valid file to import.');
                }
                
                $file = $_FILES['import_file'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                if (!in_array($ext, ['csv', 'xlsx'])) {
                    throw new Exception('Only CSV and Excel files are allowed.');
                }
                
                // Process import
                $imported = 0;
                $errors = [];
                
                if ($ext === 'csv') {
                    $handle = fopen($file['tmp_name'], 'r');
                    if ($handle) {
                        $headers = fgetcsv($handle);
                        while (($row = fgetcsv($handle)) !== false) {
                            try {
                                $data = array_combine($headers, $row);
                                // Validate and insert user
                                if (!empty($data['email']) && !empty($data['first_name']) && !empty($data['last_name'])) {
                                    // Check if email exists
                                    $check = $db->prepare("SELECT id FROM users WHERE email = ? AND tenant_id = ? AND deleted_at IS NULL");
                                    $check->execute([$data['email'], $tenant_id]);
                                    if (!$check->fetch()) {
                                        // Get role id
                                        $role_id = 2; // default
                                        if (!empty($data['role_name'])) {
                                            $role_stmt = $db->prepare("SELECT id FROM roles WHERE name = ? LIMIT 1");
                                            $role_stmt->execute([$data['role_name']]);
                                            $role = $role_stmt->fetch();
                                            if ($role) $role_id = $role['id'];
                                        }
                                        
                                        $user_code = 'USR' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
                                        $password_hash = password_hash('password123', PASSWORD_DEFAULT);
                                        
                                        $insert = $db->prepare("
                                            INSERT INTO users (tenant_id, user_code, role_id, first_name, last_name, email, phone, password_hash, status, created_by, created_at)
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())
                                        ");
                                        $insert->execute([
                                            $tenant_id, $user_code, $role_id, 
                                            $data['first_name'], $data['last_name'], 
                                            $data['email'], $data['phone'] ?? '', 
                                            $password_hash, $user_id
                                        ]);
                                        $imported++;
                                    }
                                }
                            } catch (Exception $e) {
                                $errors[] = $e->getMessage();
                            }
                        }
                        fclose($handle);
                    }
                }
                
                $action_result = [
                    'success' => true, 
                    'message' => "Imported $imported users successfully." . (!empty($errors) ? " Errors: " . implode(', ', $errors) : "")
                ];
                logActivity($user_id, 'users_bulk_imported', "Bulk imported $imported users");
                break;
                
            case 'bulk_export':
                // Handle export
                $format = $_POST['export_format'] ?? 'csv';
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d') . '.csv"');
                // ... export logic
                break;
        }
    } catch (PDOException $e) {
        $action_result = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        error_log("User action PDO Error: " . $e->getMessage());
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        error_log("User action Error: " . $e->getMessage());
    }
}

// ============================================================
// FETCH USERS WITH PAGINATION & FILTERS
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$gender_filter = isset($_GET['gender']) ? $_GET['gender'] : '';

// Build WHERE clause
$where_conditions = ["u.tenant_id = ?", "u.deleted_at IS NULL"];
$params = [$tenant_id];

if (!empty($search)) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.user_code LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    $where_conditions[] = "u.status = ?";
    $params[] = $status_filter;
}

if (!empty($role_filter)) {
    $where_conditions[] = "u.role_id = ?";
    $params[] = $role_filter;
}

if (!empty($gender_filter)) {
    $where_conditions[] = "u.gender = ?";
    $params[] = $gender_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Count total
$count_sql = "SELECT COUNT(*) as total FROM users u WHERE $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_users = $stmt->fetch()['total'] ?? 0;
$total_pages = ceil($total_users / $limit);

// Fetch users
$sql = "
    SELECT 
        u.*,
        r.name as role_name,
        r.level as role_level
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    WHERE $where_clause
    ORDER BY u.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// ============================================================
// FETCH ROLES FOR FILTER
// ============================================================
$roles = [];
try {
    $stmt = $db->prepare("
        SELECT r.id, r.name, r.level 
        FROM roles r 
        WHERE (r.tenant_id = ? OR r.tenant_id IS NULL) 
        AND r.is_active = 1 
        ORDER BY r.name
    ");
    $stmt->execute([$tenant_id]);
    $roles = $stmt->fetchAll();
} catch (Exception $e) {
    // Continue
}

// ============================================================
// FETCH STATISTICS
// ============================================================
$stats = [
    'total' => 0,
    'active' => 0,
    'suspended' => 0,
    'pending' => 0,
    'archived' => 0,
    'online' => 0,
    'today' => 0
];

try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE tenant_id = ? AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $stats['total'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE tenant_id = ? AND status = 'active' AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $stats['active'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE tenant_id = ? AND status = 'suspended' AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $stats['suspended'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE tenant_id = ? AND status = 'pending' AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $stats['pending'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE tenant_id = ? AND status = 'archived' AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $stats['archived'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT u.id) as total 
        FROM users u
        JOIN user_sessions us ON u.id = us.user_id
        WHERE u.tenant_id = ? AND us.is_active = 1 
        AND us.last_activity_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $stmt->execute([$tenant_id]);
    $stats['online'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE tenant_id = ? AND DATE(created_at) = CURDATE() AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $stats['today'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    // Continue
}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<!-- The rest of the HTML, CSS, and JavaScript remains exactly the same as your original file -->
<style>
    /* ============================================================
       USERS - CLIENT ADMIN PROFESSIONAL STYLES
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
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
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
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--primary);
    }
    .stat-item .number.green { color: var(--secondary); }
    .stat-item .number.red { color: var(--danger); }
    .stat-item .number.yellow { color: var(--warning); }
    .stat-item .number.purple { color: #8B5CF6; }
    .stat-item .number.blue { color: #3B82F6; }
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
        min-width: 120px;
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
    .filter-bar .btn-bulk {
        padding: 6px 14px;
        background: var(--secondary);
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
    .filter-bar .btn-bulk:hover {
        background: #059669;
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
    
    .user-avatar-sm {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.85rem;
        flex-shrink: 0;
        color: white;
        overflow: hidden;
    }
    .user-avatar-sm img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .user-avatar-sm.blue { background: #3B82F6; }
    .user-avatar-sm.green { background: #10B981; }
    .user-avatar-sm.purple { background: #8B5CF6; }
    .user-avatar-sm.orange { background: #F59E0B; }
    .user-avatar-sm.red { background: #EF4444; }
    .user-avatar-sm.pink { background: #EC4899; }
    .user-avatar-sm.teal { background: #14B8A6; }
    
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
    .badge-status.pending { background: #FFFBEB; color: #92400E; }
    .badge-status.pending .dot { background: #F59E0B; }
    .badge-status.archived { background: var(--gray-100); color: var(--gray-500); }
    .badge-status.archived .dot { background: var(--gray-400); }
    
    .badge-role {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 500;
        background: #EFF6FF;
        color: #1E40AF;
    }
    
    .online-indicator {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.65rem;
        color: var(--secondary);
    }
    .online-indicator .pulse {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--secondary);
        animation: pulse 1.5s ease-in-out infinite;
    }
    @keyframes pulse {
        0% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.5; transform: scale(0.8); }
        100% { opacity: 1; transform: scale(1); }
    }
    
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
        min-width: 200px;
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
        font-size: 1rem;
    }
    .empty-state p {
        font-size: 0.85rem;
        color: var(--gray-400);
    }
    
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
        max-width: 560px;
        width: 100%;
        padding: 28px 32px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.15);
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
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .modal .modal-header h3 i {
        color: var(--primary);
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
    .modal .modal-body {
        margin-bottom: 16px;
    }
    .modal .modal-body .form-group {
        margin-bottom: 14px;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .modal .modal-body .form-group label {
        font-weight: 600;
        font-size: 0.82rem;
        color: var(--gray-700);
    }
    .modal .modal-body .form-group label .required {
        color: var(--danger);
        margin-left: 2px;
    }
    .modal .modal-body .form-group .help-text {
        font-size: 0.7rem;
        color: var(--gray-400);
        margin-top: 2px;
    }
    .modal .modal-body .form-group input,
    .modal .modal-body .form-group select,
    .modal .modal-body .form-group textarea {
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
    .modal .modal-body .form-group input:focus,
    .modal .modal-body .form-group select:focus,
    .modal .modal-body .form-group textarea:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
    }
    .modal .modal-body .form-group textarea {
        resize: vertical;
        min-height: 60px;
    }
    .modal .modal-body .file-upload-area {
        border: 2px dashed var(--gray-200);
        border-radius: 10px;
        padding: 24px;
        text-align: center;
        cursor: pointer;
        transition: var(--transition);
        background: var(--gray-50);
    }
    .modal .modal-body .file-upload-area:hover {
        border-color: var(--primary);
        background: #EFF6FF;
    }
    .modal .modal-body .file-upload-area i {
        font-size: 2rem;
        color: var(--gray-400);
        display: block;
        margin-bottom: 8px;
    }
    .modal .modal-body .file-upload-area p {
        font-size: 0.85rem;
        color: var(--gray-500);
    }
    .modal .modal-body .file-upload-area .file-types {
        font-size: 0.7rem;
        color: var(--gray-400);
        margin-top: 4px;
    }
    .modal .modal-body .file-upload-area input[type="file"] {
        display: none;
    }
    .modal .modal-body .file-preview {
        display: none;
        margin-top: 12px;
        padding: 10px;
        background: var(--gray-50);
        border-radius: 8px;
        text-align: center;
    }
    .modal .modal-body .file-preview.show {
        display: block;
    }
    .modal .modal-body .file-preview .file-name {
        font-weight: 500;
        font-size: 0.85rem;
    }
    .modal .modal-body .file-preview .file-size {
        font-size: 0.7rem;
        color: var(--gray-400);
    }
    .modal .modal-footer {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid var(--gray-200);
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
    .modal .modal-footer .btn-primary {
        background: var(--primary);
        color: white;
    }
    .modal .modal-footer .btn-primary:hover {
        background: var(--primary-dark);
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
    .modal .modal-footer .btn-success {
        background: var(--secondary);
        color: white;
    }
    .modal .modal-footer .btn-success:hover {
        background: #059669;
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
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar .search-wrap { min-width: auto; }
        .filter-bar select { width: 100%; }
        .table-container { overflow-x: auto; }
        .data-table { font-size: 0.78rem; }
        .data-table th, .data-table td { padding: 6px 10px; }
        .pagination { flex-direction: column; align-items: center; }
        .page-header { flex-direction: column; align-items: flex-start; }
        .modal { padding: 20px; margin: 10px; }
        .modal .modal-footer { flex-direction: column; }
        .modal .modal-footer .btn { width: 100%; justify-content: center; }
    }
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
        .stat-item { padding: 10px 12px; }
        .stat-item .number { font-size: 1.2rem; }
        .data-table th, .data-table td { padding: 4px 8px; font-size: 0.7rem; }
        .user-avatar-sm { width: 32px; height: 32px; font-size: 0.7rem; }
        .badge-status { font-size: 0.55rem; padding: 1px 6px; }
        .action-dropdown .dropdown-menu { right: -10px; min-width: 160px; }
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
                    <i class="fas fa-users" style="color:var(--primary);margin-right:8px;"></i> User Management
                    <small>Manage all users in your organization</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button onclick="openModal('importModal')" class="btn-outline">
                    <i class="fas fa-file-import"></i> Import
                </button>
                <button onclick="openModal('exportModal')" class="btn-outline">
                    <i class="fas fa-file-export"></i> Export
                </button>
                <a href="users-create.php" class="btn-primary">
                    <i class="fas fa-user-plus"></i> Add User
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-item" onclick="applyFilter('status', '')">
                <div class="number"><?php echo number_format($stats['total']); ?></div>
                <div class="label">Total Users</div>
            </div>
            <div class="stat-item" onclick="applyFilter('status', 'active')">
                <div class="number green"><?php echo number_format($stats['active']); ?></div>
                <div class="label">Active</div>
            </div>
            <div class="stat-item" onclick="applyFilter('status', 'suspended')">
                <div class="number red"><?php echo number_format($stats['suspended']); ?></div>
                <div class="label">Suspended</div>
            </div>
            <div class="stat-item" onclick="applyFilter('status', 'pending')">
                <div class="number yellow"><?php echo number_format($stats['pending']); ?></div>
                <div class="label">Pending</div>
            </div>
            <div class="stat-item" onclick="applyFilter('status', 'archived')">
                <div class="number purple"><?php echo number_format($stats['archived']); ?></div>
                <div class="label">Archived</div>
            </div>
            <div class="stat-item">
                <div class="number blue"><?php echo number_format($stats['online']); ?></div>
                <div class="label">Online Now</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:10px;flex:1;align-items:center;width:100%;">
                <div class="search-wrap" style="flex:1;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search by name, email, phone, or code..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="archived" <?php echo $status_filter === 'archived' ? 'selected' : ''; ?>>Archived</option>
                </select>
                <select name="role">
                    <option value="">All Roles</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?php echo $role['id']; ?>" <?php echo $role_filter == $role['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($role['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="gender">
                    <option value="">All Gender</option>
                    <option value="male" <?php echo $gender_filter === 'male' ? 'selected' : ''; ?>>Male</option>
                    <option value="female" <?php echo $gender_filter === 'female' ? 'selected' : ''; ?>>Female</option>
                    <option value="other" <?php echo $gender_filter === 'other' ? 'selected' : ''; ?>>Other</option>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
                <?php if (!empty($search) || !empty($status_filter) || !empty($role_filter) || !empty($gender_filter)): ?>
                    <a href="users.php" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Users Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-list" style="color:var(--primary);"></i> All Users
                    <span class="count"><?php echo number_format($total_users); ?></span>
                </div>
                <div class="table-actions">
                    <span style="font-size:0.75rem;color:var(--gray-400);">
                        Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $limit, $total_users); ?>
                    </span>
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>User</th>
                        <th>Contact</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th style="width:70px;text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($users) > 0): ?>
                        <?php 
                        $avatar_colors = ['blue', 'green', 'purple', 'orange', 'red', 'pink', 'teal'];
                        foreach ($users as $index => $user): 
                            $color_idx = $index % count($avatar_colors);
                            $avatar_color = $avatar_colors[$color_idx];
                        ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <div class="user-avatar-sm <?php echo $avatar_color; ?>">
                                            <?php if (!empty($user['photograph_url'])): ?>
                                                <img src="<?php echo htmlspecialchars($user['photograph_url']); ?>" alt="Avatar">
                                            <?php else: ?>
                                                <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div style="font-weight:500;font-size:0.85rem;">
                                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                            </div>
                                            <div style="font-size:0.65rem;color:var(--gray-400);">
                                                <?php echo htmlspecialchars($user['user_code']); ?>
                                                <?php if (isset($user['last_login_at']) && strtotime($user['last_login_at']) > strtotime('-5 minutes')): ?>
                                                    <span class="online-indicator">
                                                        <span class="pulse"></span> Online
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size:0.8rem;"><?php echo htmlspecialchars($user['email']); ?></div>
                                    <div style="font-size:0.7rem;color:var(--gray-400);"><?php echo htmlspecialchars($user['phone']); ?></div>
                                </td>
                                <td>
                                    <span class="badge-role">
                                        <?php echo htmlspecialchars($user['role_name'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-status <?php echo $user['status']; ?>">
                                        <span class="dot"></span>
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td style="font-size:0.75rem;color:var(--gray-500);">
                                    <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="action-dropdown">
                                        <button class="dropdown-btn" onclick="toggleDropdown(this)"><i class="fas fa-ellipsis-v"></i></button>
                                        <div class="dropdown-menu">
                                            <a href="users-view.php?id=<?php echo $user['id']; ?>"><i class="fas fa-eye"></i> View</a>
                                            <a href="users-edit.php?id=<?php echo $user['id']; ?>"><i class="fas fa-edit"></i> Edit</a>
                                            <div class="divider"></div>
                                            <?php if ($user['status'] === 'active'): ?>
                                                <form method="POST" style="display:inline;width:100%;">
                                                    <input type="hidden" name="action" value="suspend">
                                                    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="danger" onclick="return confirm('Suspend this user?')">
                                                        <i class="fas fa-pause"></i> Suspend
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="display:inline;width:100%;">
                                                    <input type="hidden" name="action" value="activate">
                                                    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" onclick="return confirm('Activate this user?')">
                                                        <i class="fas fa-play"></i> Activate
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($user['status'] !== 'archived'): ?>
                                                <form method="POST" style="display:inline;width:100%;">
                                                    <input type="hidden" name="action" value="archive">
                                                    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="warning" onclick="return confirm('Archive this user?')">
                                                        <i class="fas fa-archive"></i> Archive
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" style="display:inline;width:100%;">
                                                <input type="hidden" name="action" value="reset_password">
                                                <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" onclick="return confirm('Reset password for this user?')">
                                                    <i class="fas fa-key"></i> Reset Password
                                                </button>
                                            </form>
                                            <form method="POST" style="display:inline;width:100%;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="danger" onclick="return confirm('Delete this user? This can be reversed.')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-users"></i>
                                    <h4>No users found</h4>
                                    <p>Create a new user or adjust your filters.</p>
                                    <button onclick="openModal('createUserModal')" class="btn-primary" style="margin-top:12px;">
                                        <i class="fas fa-user-plus"></i> Add User
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
        <div class="pagination">
            <div class="info">
                Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $limit, $total_users); ?></strong> of <strong><?php echo number_format($total_users); ?></strong> users
            </div>
            <div class="pages">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&role=<?php echo urlencode($role_filter); ?>&gender=<?php echo urlencode($gender_filter); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1) {
                    echo '<a href="?page=1&search=' . urlencode($search) . '&status=' . urlencode($status_filter) . '&role=' . urlencode($role_filter) . '&gender=' . urlencode($gender_filter) . '">1</a>';
                    if ($start_page > 2) echo '<span>…</span>';
                }
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&role=<?php echo urlencode($role_filter); ?>&gender=<?php echo urlencode($gender_filter); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor;
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<span>…</span>';
                    echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search) . '&status=' . urlencode($status_filter) . '&role=' . urlencode($role_filter) . '&gender=' . urlencode($gender_filter) . '">' . $total_pages . '</a>';
                }
                ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&role=<?php echo urlencode($role_filter); ?>&gender=<?php echo urlencode($gender_filter); ?>">
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
CREATE USER MODAL
============================================================ -->
<div class="modal-overlay" id="createUserModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> Create User</h3>
            <button class="close-btn" onclick="closeModal('createUserModal')">&times;</button>
        </div>
        <form method="POST" action="users-create.php">
            <div class="modal-body">
                <div class="form-group">
                    <label>First Name <span class="required">*</span></label>
                    <input type="text" name="first_name" placeholder="John" required>
                </div>
                <div class="form-group">
                    <label>Last Name <span class="required">*</span></label>
                    <input type="text" name="last_name" placeholder="Doe" required>
                </div>
                <div class="form-group">
                    <label>Email <span class="required">*</span></label>
                    <input type="email" name="email" placeholder="user@organization.ng" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone" placeholder="+234 800 555 5555">
                </div>
                <div class="form-group">
                    <label>Role <span class="required">*</span></label>
                    <select name="role_id" required>
                        <option value="">Select Role</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Password <span class="required">*</span></label>
                    <input type="password" name="password" placeholder="Min 8 characters" required>
                    <div class="help-text">User will receive this password via email.</div>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="active">Active</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================
IMPORT MODAL
============================================================ -->
<div class="modal-overlay" id="importModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-file-import"></i> Import Users</h3>
            <button class="close-btn" onclick="closeModal('importModal')">&times;</button>
        </div>
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="action" value="bulk_import">
            <div class="modal-body">
                <div class="form-group">
                    <label>Upload File <span class="required">*</span></label>
                    <div class="file-upload-area" onclick="document.getElementById('importFile').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Click to upload or drag &amp; drop</p>
                        <div class="file-types">Supported: CSV, Excel (.xlsx)</div>
                        <input type="file" name="import_file" id="importFile" accept=".csv,.xlsx" required>
                    </div>
                    <div class="file-preview" id="importPreview">
                        <div class="file-name" id="importFileName">file.csv</div>
                        <div class="file-size" id="importFileSize">0 KB</div>
                    </div>
                    <div class="help-text">
                        <i class="fas fa-info-circle"></i> 
                        Required columns: first_name, last_name, email, role_name
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('importModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Import Users</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================
EXPORT MODAL
============================================================ -->
<div class="modal-overlay" id="exportModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-file-export"></i> Export Users</h3>
            <button class="close-btn" onclick="closeModal('exportModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="bulk_export">
            <div class="modal-body">
                <div class="form-group">
                    <label>Export Format <span class="required">*</span></label>
                    <select name="export_format" required>
                        <option value="csv">CSV</option>
                        <option value="excel">Excel (.xlsx)</option>
                        <option value="pdf">PDF</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Fields to Export</label>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
                        <label style="font-weight:400;font-size:0.8rem;display:flex;align-items:center;gap:6px;">
                            <input type="checkbox" name="fields[]" value="name" checked> Name
                        </label>
                        <label style="font-weight:400;font-size:0.8rem;display:flex;align-items:center;gap:6px;">
                            <input type="checkbox" name="fields[]" value="email" checked> Email
                        </label>
                        <label style="font-weight:400;font-size:0.8rem;display:flex;align-items:center;gap:6px;">
                            <input type="checkbox" name="fields[]" value="phone" checked> Phone
                        </label>
                        <label style="font-weight:400;font-size:0.8rem;display:flex;align-items:center;gap:6px;">
                            <input type="checkbox" name="fields[]" value="role" checked> Role
                        </label>
                        <label style="font-weight:400;font-size:0.8rem;display:flex;align-items:center;gap:6px;">
                            <input type="checkbox" name="fields[]" value="status" checked> Status
                        </label>
                        <label style="font-weight:400;font-size:0.8rem;display:flex;align-items:center;gap:6px;">
                            <input type="checkbox" name="fields[]" value="gender"> Gender
                        </label>
                        <label style="font-weight:400;font-size:0.8rem;display:flex;align-items:center;gap:6px;">
                            <input type="checkbox" name="fields[]" value="dob"> Date of Birth
                        </label>
                        <label style="font-weight:400;font-size:0.8rem;display:flex;align-items:center;gap:6px;">
                            <input type="checkbox" name="fields[]" value="joined"> Joined Date
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('exportModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Export Users</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================
BULK ACTION MODAL
============================================================ -->
<div class="modal-overlay" id="bulkActionModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-tasks"></i> Bulk Action</h3>
            <button class="close-btn" onclick="closeModal('bulkActionModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <p style="color:var(--gray-600);margin-bottom:12px;">Select users and choose an action:</p>
                <div class="form-group">
                    <label>Action <span class="required">*</span></label>
                    <select name="bulk_action" required>
                        <option value="">Select Action</option>
                        <option value="activate">Activate Selected</option>
                        <option value="suspend">Suspend Selected</option>
                        <option value="archive">Archive Selected</option>
                        <option value="delete">Delete Selected</option>
                    </select>
                </div>
                <div style="max-height:200px;overflow-y:auto;border:1px solid var(--gray-200);border-radius:8px;padding:8px;">
                    <div style="display:flex;align-items:center;gap:8px;padding:4px 0;border-bottom:1px solid var(--gray-100);">
                        <input type="checkbox" id="selectAllBulk" onchange="toggleAllBulk(this.checked)">
                        <label for="selectAllBulk" style="font-weight:600;font-size:0.82rem;cursor:pointer;">Select All</label>
                    </div>
                    <div id="bulkUserList">
                        <?php foreach ($users as $user): ?>
                            <div style="display:flex;align-items:center;gap:8px;padding:4px 0;border-bottom:1px solid var(--gray-50);">
                                <input type="checkbox" name="bulk_ids[]" value="<?php echo $user['id']; ?>">
                                <span style="font-size:0.82rem;"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                                <span style="font-size:0.65rem;color:var(--gray-400);margin-left:auto;"><?php echo htmlspecialchars($user['email']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('bulkActionModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Apply Action</button>
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
// ACTION DROPDOWN
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
// FILTER FUNCTIONS
// ============================================================
function applyFilter(type, value) {
    var url = new URL(window.location.href);
    if (value) {
        url.searchParams.set(type, value);
    } else {
        url.searchParams.delete(type);
    }
    window.location.href = url.toString();
}

// ============================================================
// FILE IMPORT PREVIEW
// ============================================================
document.getElementById('importFile').addEventListener('change', function() {
    var preview = document.getElementById('importPreview');
    var fileName = document.getElementById('importFileName');
    var fileSize = document.getElementById('importFileSize');
    
    if (this.files && this.files[0]) {
        var file = this.files[0];
        fileName.textContent = file.name;
        fileSize.textContent = (file.size / 1024).toFixed(1) + ' KB';
        preview.classList.add('show');
    } else {
        preview.classList.remove('show');
    }
});

// ============================================================
// BULK SELECT
// ============================================================
function toggleAllBulk(checked) {
    document.querySelectorAll('#bulkUserList input[type="checkbox"]').forEach(function(cb) {
        cb.checked = checked;
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