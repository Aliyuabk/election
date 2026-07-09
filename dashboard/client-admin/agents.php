<?php
// ============================================================
// AGENTS MANAGEMENT - CLIENT ADMIN (PROFESSIONAL UI)
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
// HANDLE AJAX REQUESTS
// ============================================================
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    $action = $_GET['action'] ?? '';
    $response = ['success' => false, 'message' => ''];
    
    try {
        switch ($action) {
            case 'get_agent':
                $id = (int)($_GET['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid agent ID.');
                
                $stmt = $db->prepare("
                    SELECT u.*, r.name as role_name, r.slug as role_slug
                    FROM users u
                    LEFT JOIN roles r ON u.role_id = r.id
                    WHERE u.id = ? AND u.tenant_id = ? AND u.deleted_at IS NULL
                ");
                $stmt->execute([$id, $tenant_id]);
                $agent = $stmt->fetch();
                
                if ($agent) {
                    $response = ['success' => true, 'data' => $agent];
                } else {
                    throw new Exception('Agent not found.');
                }
                break;
                
            case 'toggle_agent_status':
                $id = (int)($_POST['id'] ?? 0);
                $status = $_POST['status'] ?? 'active';
                
                if ($id <= 0) throw new Exception('Invalid agent ID.');
                if (!in_array($status, ['active', 'inactive', 'pending', 'suspended'])) {
                    throw new Exception('Invalid status value.');
                }
                
                $stmt = $db->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$status, $id, $tenant_id]);
                
                logActivity($user_id, 'agent_status_toggled', "Toggled agent ID: $id to $status");
                $response = ['success' => true, 'message' => 'Agent status updated successfully.'];
                break;
                
            default:
                throw new Exception('Invalid action.');
        }
    } catch (PDOException $e) {
        $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        error_log("Agent AJAX PDO Error: " . $e->getMessage());
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
        error_log("Agent AJAX Error: " . $e->getMessage());
    }
    
    echo json_encode($response);
    exit();
}

// ============================================================
// HANDLE ACTIONS
// ============================================================
$action_result = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add_agent':
                $first_name = trim($_POST['first_name'] ?? '');
                $last_name = trim($_POST['last_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $role = trim($_POST['role'] ?? '');
                $gender = trim($_POST['gender'] ?? '');
                $date_of_birth = trim($_POST['date_of_birth'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $nin = trim($_POST['nin'] ?? '');
                $bank_name = trim($_POST['bank_name'] ?? '');
                $account_number = trim($_POST['account_number'] ?? '');
                $account_name = trim($_POST['account_name'] ?? '');
                
                if (empty($first_name) || empty($last_name) || empty($email) || empty($phone)) {
                    throw new Exception('First name, last name, email, and phone are required.');
                }
                
                // Check if email exists
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE email = ? AND tenant_id = ?");
                $stmt->execute([$email, $tenant_id]);
                if ($stmt->fetch()['count'] > 0) {
                    throw new Exception('Email already exists.');
                }
                
                // Generate user code
                $user_code = 'AGT' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
                
                // Get role ID
                $stmt = $db->prepare("SELECT id FROM roles WHERE slug = ? AND (tenant_id = ? OR tenant_id IS NULL)");
                $stmt->execute([$role, $tenant_id]);
                $role_id = $stmt->fetch()['id'] ?? 7;
                
                // Create user
                $password_hash = password_hash('password123', PASSWORD_DEFAULT);
                $stmt = $db->prepare("
                    INSERT INTO users (
                        tenant_id, user_code, role_id, first_name, last_name,
                        email, phone, password_hash, gender, date_of_birth,
                        residential_address, nin, bank_name, account_number, account_name,
                        status, created_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())
                ");
                $stmt->execute([
                    $tenant_id, $user_code, $role_id, $first_name, $last_name,
                    $email, $phone, $password_hash, $gender, $date_of_birth,
                    $address, $nin, $bank_name, $account_number, $account_name,
                    $user_id
                ]);
                
                logActivity($user_id, 'agent_added', "Added agent: $first_name $last_name");
                $action_result = ['success' => true, 'message' => "Agent '$first_name $last_name' added successfully."];
                break;
                
            case 'edit_agent':
                $id = (int)($_POST['id'] ?? 0);
                $first_name = trim($_POST['first_name'] ?? '');
                $last_name = trim($_POST['last_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $role = trim($_POST['role'] ?? '');
                $gender = trim($_POST['gender'] ?? '');
                $date_of_birth = trim($_POST['date_of_birth'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $nin = trim($_POST['nin'] ?? '');
                $bank_name = trim($_POST['bank_name'] ?? '');
                $account_number = trim($_POST['account_number'] ?? '');
                $account_name = trim($_POST['account_name'] ?? '');
                $status = isset($_POST['status']) ? trim($_POST['status']) : 'active';
                
                if ($id <= 0 || empty($first_name) || empty($last_name)) {
                    throw new Exception('Invalid data provided.');
                }
                
                // Check if email exists for other users
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE email = ? AND id != ? AND tenant_id = ?");
                $stmt->execute([$email, $id, $tenant_id]);
                if ($stmt->fetch()['count'] > 0) {
                    throw new Exception('Email already exists for another user.');
                }
                
                // Get role ID
                $stmt = $db->prepare("SELECT id FROM roles WHERE slug = ? AND (tenant_id = ? OR tenant_id IS NULL)");
                $stmt->execute([$role, $tenant_id]);
                $role_id = $stmt->fetch()['id'] ?? 7;
                
                $stmt = $db->prepare("
                    UPDATE users SET 
                        first_name = ?, last_name = ?, email = ?, phone = ?,
                        role_id = ?, gender = ?, date_of_birth = ?,
                        residential_address = ?, nin = ?, bank_name = ?,
                        account_number = ?, account_name = ?, status = ?,
                        updated_at = NOW()
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmt->execute([
                    $first_name, $last_name, $email, $phone,
                    $role_id, $gender, $date_of_birth,
                    $address, $nin, $bank_name,
                    $account_number, $account_name, $status,
                    $id, $tenant_id
                ]);
                
                logActivity($user_id, 'agent_updated', "Updated agent ID: $id");
                $action_result = ['success' => true, 'message' => 'Agent updated successfully.'];
                break;
                
            case 'delete_agent':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid agent ID.');
                
                $stmt = $db->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$id, $tenant_id]);
                
                logActivity($user_id, 'agent_deleted', "Deleted agent ID: $id");
                $action_result = ['success' => true, 'message' => 'Agent deleted successfully.'];
                break;
        }
    } catch (PDOException $e) {
        $action_result = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        error_log("Agent action PDO Error: " . $e->getMessage());
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        error_log("Agent action Error: " . $e->getMessage());
    }
}

// ============================================================
// FETCH AGENTS
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? trim($_GET['role']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

$where_conditions = ["u.tenant_id = ?", "u.deleted_at IS NULL"];
$params = [$tenant_id];

if (!empty($search)) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($role_filter)) {
    $where_conditions[] = "r.slug = ?";
    $params[] = $role_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "u.status = ?";
    $params[] = $status_filter;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Count total
$count_sql = "SELECT COUNT(*) as total FROM users u LEFT JOIN roles r ON u.role_id = r.id $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_agents = $stmt->fetch()['total'] ?? 0;
$total_pages = ceil($total_agents / $limit);

// Fetch agents
$sql = "
    SELECT u.*, r.name as role_name, r.slug as role_slug,
           (SELECT COUNT(*) FROM agent_assignments WHERE user_id = u.id AND status IN ('pending', 'active')) as active_assignments
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    $where_clause
    ORDER BY u.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$agents = $stmt->fetchAll();

// ============================================================
// FETCH ROLES FOR DROPDOWN
// ============================================================
$roles = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, slug FROM roles 
        WHERE level IN ('pu_agent', 'party_agent', 'volunteer', 'observer') 
        AND (tenant_id = ? OR tenant_id IS NULL)
        ORDER BY name
    ");
    $stmt->execute([$tenant_id]);
    $roles = $stmt->fetchAll();
} catch (Exception $e) {}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<!-- ALL CSS REMAINS THE SAME AS YOUR ORIGINAL FILE -->
<style>
    /* ============================================================
       AGENTS MANAGEMENT - PROFESSIONAL UI STYLES
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
    .btn-sm.success { background: #ECFDF5; color: #065F46; }
    .btn-sm.success:hover { background: #D1FAE5; }
    .btn-sm.danger { background: #FEF2F2; color: #991B1B; }
    .btn-sm.danger:hover { background: #FEE2E2; }
    .btn-sm.info { background: #EFF6FF; color: #1E40AF; }
    .btn-sm.info:hover { background: #DBEAFE; }
    .btn-sm.warning { background: #FFFBEB; color: #92400E; }
    .btn-sm.warning:hover { background: #FEF3C7; }
    .btn-sm.purple { background: #F5F3FF; color: #5B21B6; }
    .btn-sm.purple:hover { background: #EDE9FE; }
    
    .structure-nav {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        margin-bottom: 20px;
        background: white;
        padding: 8px 12px;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        box-shadow: var(--shadow);
    }
    .structure-nav a {
        padding: 8px 18px;
        border-radius: 10px;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 500;
        transition: var(--transition);
        background: transparent;
        border: 1px solid transparent;
        color: var(--gray-600);
        display: inline-flex;
        align-items: center;
        gap: 6px;
        position: relative;
    }
    .structure-nav a:hover {
        background: var(--gray-50);
        border-color: var(--gray-200);
        color: var(--gray-700);
    }
    .structure-nav a.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.25);
    }
    .structure-nav a .count {
        background: var(--gray-200);
        color: var(--gray-600);
        padding: 0 8px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 600;
        margin-left: 4px;
        transition: var(--transition);
    }
    .structure-nav a.active .count {
        background: rgba(255,255,255,0.25);
        color: white;
    }
    .structure-nav a:hover .count {
        background: var(--gray-300);
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
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--primary);
        line-height: 1.2;
    }
    .stat-item .number.green { color: var(--secondary); }
    .stat-item .number.yellow { color: var(--warning); }
    .stat-item .number.purple { color: #8B5CF6; }
    .stat-item .number.blue { color: #3B82F6; }
    .stat-item .number.red { color: var(--danger); }
    .stat-item .number.orange { color: #F59E0B; }
    .stat-item .label {
        font-size: 0.75rem;
        color: var(--gray-500);
        margin-top: 4px;
        font-weight: 500;
    }
    .stat-item .sub-label {
        font-size: 0.65rem;
        color: var(--gray-400);
        margin-top: 2px;
    }
    
    .filter-bar {
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
        transition: var(--transition);
    }
    .filter-bar:hover {
        box-shadow: var(--shadow-hover);
    }
    .filter-bar .search-wrap {
        flex: 1;
        min-width: 200px;
        display: flex;
        align-items: center;
        background: var(--gray-50);
        border: 1.5px solid var(--gray-200);
        border-radius: 10px;
        padding: 6px 14px;
        transition: var(--transition);
    }
    .filter-bar .search-wrap:focus-within {
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 4px rgba(var(--primary-rgb), 0.08);
    }
    .filter-bar .search-wrap i {
        color: var(--gray-400);
        font-size: 0.85rem;
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
    .filter-bar .search-wrap input::placeholder {
        color: var(--gray-400);
    }
    .filter-bar select {
        padding: 8px 14px;
        border: 1.5px solid var(--gray-200);
        border-radius: 10px;
        font-family: 'Inter', sans-serif;
        font-size: 0.82rem;
        background: var(--gray-50);
        color: var(--gray-700);
        cursor: pointer;
        transition: var(--transition);
        min-width: 120px;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        padding-right: 36px;
    }
    .filter-bar select:focus {
        outline: none;
        border-color: var(--primary);
        background-color: white;
        box-shadow: 0 0 0 4px rgba(var(--primary-rgb), 0.08);
    }
    .filter-bar .btn-filter {
        padding: 8px 20px;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.82rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .filter-bar .btn-filter:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.25);
    }
    .filter-bar .btn-clear {
        padding: 8px 16px;
        background: transparent;
        color: var(--gray-500);
        border: 1.5px solid var(--gray-200);
        border-radius: 10px;
        font-weight: 500;
        font-size: 0.82rem;
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
        padding: 16px 24px;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        background: linear-gradient(135deg, var(--gray-50), white);
    }
    .table-container .table-header .table-title {
        font-weight: 600;
        font-size: 0.95rem;
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
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .table-container .table-header .table-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .table-container .table-header .table-actions span {
        font-size: 0.75rem;
        color: var(--gray-400);
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
        padding: 12px 16px;
        text-align: left;
        font-weight: 600;
        font-size: 0.7rem;
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
        padding: 10px 16px;
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
    .data-table tbody tr:hover td {
        border-color: var(--gray-200);
    }
    
    .agent-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.85rem;
        color: white;
        flex-shrink: 0;
    }
    .agent-avatar.primary { background: var(--primary); }
    .agent-avatar.green { background: var(--secondary); }
    .agent-avatar.purple { background: #8B5CF6; }
    .agent-avatar.orange { background: #F59E0B; }
    .agent-avatar.red { background: var(--danger); }
    
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        transition: var(--transition);
    }
    .badge-status .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
    }
    .badge-status.active { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
    .badge-status.active .dot { background: #10B981; }
    .badge-status.inactive { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
    .badge-status.inactive .dot { background: #EF4444; }
    .badge-status.pending { background: #FFFBEB; color: #92400E; border: 1px solid #FDE68A; }
    .badge-status.pending .dot { background: #F59E0B; }
    .badge-status.suspended { background: #F5F3FF; color: #5B21B6; border: 1px solid #C4B5FD; }
    .badge-status.suspended .dot { background: #8B5CF6; }
    
    .role-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
        background: #EFF6FF;
        color: #1E40AF;
    }
    .role-badge.party_agent { background: #F5F3FF; color: #5B21B6; }
    .role-badge.volunteer { background: #ECFDF5; color: #065F46; }
    .role-badge.observer { background: #FFFBEB; color: #92400E; }
    
    .action-dropdown {
        position: relative;
        display: inline-block;
    }
    .action-dropdown .dropdown-btn {
        background: none;
        border: none;
        padding: 6px 10px;
        cursor: pointer;
        color: var(--gray-400);
        font-size: 1.1rem;
        transition: var(--transition);
        border-radius: 8px;
    }
    .action-dropdown .dropdown-btn:hover {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .action-dropdown .dropdown-menu {
        position: absolute;
        right: 0;
        top: calc(100% + 4px);
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
        from { opacity: 0; transform: translateY(-8px) scale(0.98); }
        to { opacity: 1; transform: translateY(0) scale(1); }
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
        padding: 14px 24px;
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
        box-shadow: 0 2px 8px rgba(var(--primary-rgb), 0.2);
    }
    .pagination .pages .disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--gray-500);
    }
    .empty-state i {
        font-size: 4rem;
        color: var(--gray-300);
        display: block;
        margin-bottom: 16px;
    }
    .empty-state h4 {
        color: var(--gray-700);
        margin-bottom: 8px;
        font-size: 1.1rem;
    }
    .empty-state p {
        font-size: 0.9rem;
        color: var(--gray-400);
        max-width: 400px;
        margin: 0 auto;
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
    .modal .form-group label .required {
        color: var(--danger);
        margin-left: 2px;
    }
    .modal .form-group .help-text {
        font-size: 0.75rem;
        color: var(--gray-400);
        margin-top: 2px;
    }
    .modal .form-group input,
    .modal .form-group select,
    .modal .form-group textarea {
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
    .modal .form-group input:focus,
    .modal .form-group select:focus,
    .modal .form-group textarea:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 4px rgba(var(--primary-rgb), 0.08);
    }
    .modal .form-group .radio-group {
        display: flex;
        gap: 16px;
        padding-top: 4px;
        flex-wrap: wrap;
    }
    .modal .form-group .radio-group label {
        display: flex;
        align-items: center;
        gap: 6px;
        font-weight: 400;
        font-size: 0.85rem;
        color: var(--gray-700);
        cursor: pointer;
    }
    .modal .form-group .radio-group input[type="radio"] {
        accent-color: var(--primary);
        width: 16px;
        height: 16px;
        cursor: pointer;
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
    }
    .toast.success { background: var(--secondary); }
    .toast.error { background: var(--danger); }
    
    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar .search-wrap { min-width: auto; }
        .filter-bar select { width: 100%; }
        .table-container { overflow-x: auto; }
        .data-table { font-size: 0.78rem; }
        .data-table th, .data-table td { padding: 8px 12px; }
        .pagination { flex-direction: column; align-items: center; }
        .modal { padding: 20px; margin: 10px; }
        .modal .form-actions { flex-direction: column; }
        .modal .form-actions .btn { width: 100%; justify-content: center; }
        .structure-nav { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; padding: 6px 8px; }
        .structure-nav a { white-space: nowrap; font-size: 0.78rem; padding: 6px 12px; }
    }
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
        .stat-item { padding: 12px 14px; }
        .stat-item .number { font-size: 1.3rem; }
        .data-table th, .data-table td { padding: 6px 8px; font-size: 0.7rem; }
        .agent-avatar { width: 28px; height: 28px; font-size: 0.7rem; }
        .badge-status { font-size: 0.55rem; padding: 2px 8px; }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Toast Messages -->
        <?php if (!empty($action_result['message'])): ?>
        <div class="toast <?php echo $action_result['success'] ? 'success' : 'error'; ?>" style="position:static;animation:none;max-width:100%;margin-bottom:16px;">
            <i class="fas <?php echo $action_result['success'] ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($action_result['message']); ?>
        </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-user-tie" style="color:var(--primary);margin-right:8px;"></i> Agents Management
                    <small>Manage field agents, assignments, and payments</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button onclick="openModal('addAgentModal')" class="btn-primary">
                    <i class="fas fa-plus-circle"></i> Add Agent
                </button>
            </div>
        </div>

        <!-- Navigation -->
        <div class="structure-nav">
            <a href="agents.php" class="active">
                <i class="fas fa-users"></i> Agents
                <span class="count"><?php echo number_format($total_agents); ?></span>
            </a>
            <a href="agents-assign.php">
                <i class="fas fa-map-marker-alt"></i> Assign
            </a>
            <a href="agents-payments.php">
                <i class="fas fa-money-bill-wave"></i> Payments
            </a>
        </div>

        <!-- Stats -->
        <?php
        // Get stats
        $stats = ['total' => 0, 'active' => 0, 'inactive' => 0, 'pending' => 0, 'suspended' => 0];
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE tenant_id = ? AND role_id IN (SELECT id FROM roles WHERE level IN ('pu_agent', 'party_agent', 'volunteer', 'observer')) AND deleted_at IS NULL");
            $stmt->execute([$tenant_id]);
            $stats['total'] = $stmt->fetch()['total'] ?? 0;
            
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE tenant_id = ? AND status = 'active' AND role_id IN (SELECT id FROM roles WHERE level IN ('pu_agent', 'party_agent', 'volunteer', 'observer')) AND deleted_at IS NULL");
            $stmt->execute([$tenant_id]);
            $stats['active'] = $stmt->fetch()['total'] ?? 0;
            
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE tenant_id = ? AND status = 'inactive' AND role_id IN (SELECT id FROM roles WHERE level IN ('pu_agent', 'party_agent', 'volunteer', 'observer')) AND deleted_at IS NULL");
            $stmt->execute([$tenant_id]);
            $stats['inactive'] = $stmt->fetch()['total'] ?? 0;
            
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE tenant_id = ? AND status = 'pending' AND role_id IN (SELECT id FROM roles WHERE level IN ('pu_agent', 'party_agent', 'volunteer', 'observer')) AND deleted_at IS NULL");
            $stmt->execute([$tenant_id]);
            $stats['pending'] = $stmt->fetch()['total'] ?? 0;
            
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE tenant_id = ? AND status = 'suspended' AND role_id IN (SELECT id FROM roles WHERE level IN ('pu_agent', 'party_agent', 'volunteer', 'observer')) AND deleted_at IS NULL");
            $stmt->execute([$tenant_id]);
            $stats['suspended'] = $stmt->fetch()['total'] ?? 0;
        } catch (Exception $e) {}
        ?>
        <div class="stats-grid">
            <div class="stat-item">
                <div class="number"><?php echo number_format($stats['total']); ?></div>
                <div class="label">Total Agents</div>
                <div class="sub-label">All registered agents</div>
            </div>
            <div class="stat-item">
                <div class="number green"><?php echo number_format($stats['active']); ?></div>
                <div class="label">Active</div>
                <div class="sub-label">Currently active</div>
            </div>
            <div class="stat-item">
                <div class="number yellow"><?php echo number_format($stats['pending']); ?></div>
                <div class="label">Pending</div>
                <div class="sub-label">Awaiting activation</div>
            </div>
            <div class="stat-item">
                <div class="number red"><?php echo number_format($stats['inactive'] + $stats['suspended']); ?></div>
                <div class="label">Inactive/Suspended</div>
                <div class="sub-label">Not currently active</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:10px;flex:1;align-items:center;width:100%;">
                <div class="search-wrap" style="flex:1;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search agents by name, email, or phone..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <select name="role">
                    <option value="">All Roles</option>
                    <option value="pu_agent" <?php echo $role_filter == 'pu_agent' ? 'selected' : ''; ?>>PU Agent</option>
                    <option value="party_agent" <?php echo $role_filter == 'party_agent' ? 'selected' : ''; ?>>Party Agent</option>
                    <option value="volunteer" <?php echo $role_filter == 'volunteer' ? 'selected' : ''; ?>>Volunteer</option>
                    <option value="observer" <?php echo $role_filter == 'observer' ? 'selected' : ''; ?>>Observer</option>
                </select>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="suspended" <?php echo $status_filter == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
                <?php if (!empty($search) || !empty($role_filter) || !empty($status_filter)): ?>
                    <a href="agents.php" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Agents Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-list" style="color:var(--primary);"></i> Agents List
                    <span class="count"><?php echo number_format($total_agents); ?></span>
                </div>
                <div class="table-actions">
                    <span>Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $limit, $total_agents); ?></span>
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Agent</th>
                        <th>Contact</th>
                        <th>Role</th>
                        <th>Assignments</th>
                        <th>Status</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($agents) > 0): ?>
                        <?php 
                        $colors = ['primary', 'green', 'purple', 'orange', 'red'];
                        foreach ($agents as $index => $agent): 
                            $color = $colors[$index % count($colors)];
                            $initials = strtoupper(substr($agent['first_name'], 0, 1) . substr($agent['last_name'], 0, 1));
                            $role_class = $agent['role_slug'] ?? '';
                        ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <div class="agent-avatar <?php echo $color; ?>">
                                            <?php echo $initials; ?>
                                        </div>
                                        <div>
                                            <div style="font-weight:500;font-size:0.85rem;">
                                                <?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']); ?>
                                            </div>
                                            <div style="font-size:0.7rem;color:var(--gray-400);">
                                                <i class="fas fa-id-card"></i> <?php echo htmlspecialchars($agent['user_code']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size:0.8rem;">
                                        <div><i class="fas fa-envelope" style="color:var(--gray-400);width:16px;"></i> <?php echo htmlspecialchars($agent['email']); ?></div>
                                        <div><i class="fas fa-phone" style="color:var(--gray-400);width:16px;"></i> <?php echo htmlspecialchars($agent['phone']); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge <?php echo $role_class; ?>">
                                        <?php echo htmlspecialchars($agent['role_name'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-weight:600;font-size:0.85rem;">
                                        <?php echo number_format($agent['active_assignments'] ?? 0); ?>
                                    </span>
                                    <span style="font-size:0.6rem;color:var(--gray-400);display:block;">active</span>
                                </td>
                                <td>
                                    <span class="badge-status <?php echo $agent['status'] ?? 'pending'; ?>">
                                        <span class="dot"></span>
                                        <?php echo ucfirst($agent['status'] ?? 'Pending'); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-dropdown">
                                        <button class="dropdown-btn" onclick="toggleDropdown(this)"><i class="fas fa-ellipsis-v"></i></button>
                                        <div class="dropdown-menu">
                                            <button onclick="editAgent(<?php echo $agent['id']; ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <a href="agents-assign.php?agent=<?php echo $agent['id']; ?>">
                                                <i class="fas fa-map-marker-alt"></i> Assign
                                            </a>
                                            <a href="agents-payments.php?agent=<?php echo $agent['id']; ?>">
                                                <i class="fas fa-money-bill-wave"></i> Payments
                                            </a>
                                            <button onclick="viewAgentDetails(<?php echo $agent['id']; ?>)">
                                                <i class="fas fa-info-circle"></i> Details
                                            </button>
                                            <?php if ($agent['status'] == 'active'): ?>
                                                <button onclick="toggleAgentStatus(<?php echo $agent['id']; ?>, 'inactive')">
                                                    <i class="fas fa-pause-circle"></i> Deactivate
                                                </button>
                                            <?php else: ?>
                                                <button onclick="toggleAgentStatus(<?php echo $agent['id']; ?>, 'active')">
                                                    <i class="fas fa-play-circle"></i> Activate
                                                </button>
                                            <?php endif; ?>
                                            <div class="divider"></div>
                                            <button class="danger" onclick="deleteAgent(<?php echo $agent['id']; ?>)">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-user-tie"></i>
                                    <h4>No agents found</h4>
                                    <p>Add agents to start building your field team.</p>
                                    <button onclick="openModal('addAgentModal')" class="btn-primary" style="margin-top:12px;">
                                        <i class="fas fa-plus-circle"></i> Add Agent
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
                Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $limit, $total_agents); ?></strong> of <strong><?php echo number_format($total_agents); ?></strong> agents
            </div>
            <div class="pages">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1) {
                    echo '<a href="?page=1&search=' . urlencode($search) . '&role=' . urlencode($role_filter) . '&status=' . urlencode($status_filter) . '">1</a>';
                    if ($start_page > 2) echo '<span>…</span>';
                }
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor;
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<span>…</span>';
                    echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search) . '&role=' . urlencode($role_filter) . '&status=' . urlencode($status_filter) . '">' . $total_pages . '</a>';
                }
                ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
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

<!-- Add Agent Modal -->
<div class="modal-overlay" id="addAgentModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle" style="color:var(--primary);"></i> Add New Agent</h3>
            <button class="close-btn" onclick="closeModal('addAgentModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_agent">
            <div class="form-group">
                <label>First Name <span class="required">*</span></label>
                <input type="text" name="first_name" placeholder="Enter first name" required>
            </div>
            <div class="form-group">
                <label>Last Name <span class="required">*</span></label>
                <input type="text" name="last_name" placeholder="Enter last name" required>
            </div>
            <div class="form-group">
                <label>Email <span class="required">*</span></label>
                <input type="email" name="email" placeholder="agent@example.com" required>
            </div>
            <div class="form-group">
                <label>Phone <span class="required">*</span></label>
                <input type="tel" name="phone" placeholder="+234 800 000 0000" required>
            </div>
            <div class="form-group">
                <label>Role <span class="required">*</span></label>
                <select name="role" required>
                    <option value="">Select Role</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?php echo htmlspecialchars($role['slug']); ?>">
                            <?php echo htmlspecialchars($role['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Gender</label>
                <div class="radio-group">
                    <label><input type="radio" name="gender" value="male"> Male</label>
                    <label><input type="radio" name="gender" value="female"> Female</label>
                    <label><input type="radio" name="gender" value="other"> Other</label>
                </div>
            </div>
            <div class="form-group">
                <label>Date of Birth</label>
                <input type="date" name="date_of_birth">
            </div>
            <div class="form-group">
                <label>Residential Address</label>
                <textarea name="address" placeholder="Enter full address" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label>NIN (National Identification Number)</label>
                <input type="text" name="nin" placeholder="Enter NIN">
            </div>
            <div class="form-group">
                <label>Bank Name</label>
                <input type="text" name="bank_name" placeholder="e.g., GTBank">
            </div>
            <div class="form-group">
                <label>Account Number</label>
                <input type="text" name="account_number" placeholder="Enter account number">
            </div>
            <div class="form-group">
                <label>Account Name</label>
                <input type="text" name="account_name" placeholder="Enter account name">
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addAgentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Agent</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Agent Modal -->
<div class="modal-overlay" id="editAgentModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-edit" style="color:var(--primary);"></i> Edit Agent</h3>
            <button class="close-btn" onclick="closeModal('editAgentModal')">&times;</button>
        </div>
        <form method="POST" action="" id="editAgentForm">
            <input type="hidden" name="action" value="edit_agent">
            <input type="hidden" name="id" id="editAgentId">
            <div class="form-group">
                <label>First Name <span class="required">*</span></label>
                <input type="text" name="first_name" id="editFirstName" required>
            </div>
            <div class="form-group">
                <label>Last Name <span class="required">*</span></label>
                <input type="text" name="last_name" id="editLastName" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" id="editEmail">
            </div>
            <div class="form-group">
                <label>Phone <span class="required">*</span></label>
                <input type="tel" name="phone" id="editPhone" required>
            </div>
            <div class="form-group">
                <label>Role <span class="required">*</span></label>
                <select name="role" id="editRole" required>
                    <option value="">Select Role</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?php echo htmlspecialchars($role['slug']); ?>">
                            <?php echo htmlspecialchars($role['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Gender</label>
                <div class="radio-group">
                    <label><input type="radio" name="gender" value="male" id="editGenderMale"> Male</label>
                    <label><input type="radio" name="gender" value="female" id="editGenderFemale"> Female</label>
                    <label><input type="radio" name="gender" value="other" id="editGenderOther"> Other</label>
                </div>
            </div>
            <div class="form-group">
                <label>Date of Birth</label>
                <input type="date" name="date_of_birth" id="editDob">
            </div>
            <div class="form-group">
                <label>Residential Address</label>
                <textarea name="address" id="editAddress" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label>NIN</label>
                <input type="text" name="nin" id="editNin">
            </div>
            <div class="form-group">
                <label>Bank Name</label>
                <input type="text" name="bank_name" id="editBankName">
            </div>
            <div class="form-group">
                <label>Account Number</label>
                <input type="text" name="account_number" id="editAccountNumber">
            </div>
            <div class="form-group">
                <label>Account Name</label>
                <input type="text" name="account_name" id="editAccountName">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="editStatus">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="pending">Pending</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editAgentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Agent</button>
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
// AGENT FUNCTIONS
// ============================================================
function editAgent(id) {
    // Show loading state
    var modal = document.getElementById('editAgentModal');
    var submitBtn = modal.querySelector('button[type="submit"]');
    var originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    submitBtn.disabled = true;
    
    // Fetch agent data via AJAX
    fetch('agents.php?action=get_agent&id=' + id, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(function(response) {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(function(data) {
        if (data && data.success) {
            var agent = data.data;
            document.getElementById('editAgentId').value = agent.id;
            document.getElementById('editFirstName').value = agent.first_name || '';
            document.getElementById('editLastName').value = agent.last_name || '';
            document.getElementById('editEmail').value = agent.email || '';
            document.getElementById('editPhone').value = agent.phone || '';
            document.getElementById('editRole').value = agent.role_slug || '';
            document.getElementById('editDob').value = agent.date_of_birth || '';
            document.getElementById('editAddress').value = agent.residential_address || '';
            document.getElementById('editNin').value = agent.nin || '';
            document.getElementById('editBankName').value = agent.bank_name || '';
            document.getElementById('editAccountNumber').value = agent.account_number || '';
            document.getElementById('editAccountName').value = agent.account_name || '';
            document.getElementById('editStatus').value = agent.status || 'active';
            
            // Gender
            document.getElementById('editGenderMale').checked = (agent.gender === 'male');
            document.getElementById('editGenderFemale').checked = (agent.gender === 'female');
            document.getElementById('editGenderOther').checked = (agent.gender === 'other');
            
            openModal('editAgentModal');
        } else {
            alert(data.message || 'Failed to load agent data.');
        }
    })
    .catch(function(error) {
        console.error('Error:', error);
        alert('Error loading agent data. Please try again.');
    })
    .finally(function() {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

function viewAgentDetails(id) {
    alert('View details for Agent ID: ' + id + '\nThis feature will be implemented soon.');
}

function toggleAgentStatus(id, status) {
    var action = status === 'active' ? 'activate' : 'deactivate';
    if (!confirm('Are you sure you want to ' + action + ' this agent?')) {
        return;
    }
    
    var formData = new FormData();
    formData.append('action', 'toggle_agent_status');
    formData.append('id', id);
    formData.append('status', status);
    
    fetch('agents.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data && data.success) {
            location.reload();
        } else {
            alert(data.message || 'Failed to update agent status.');
        }
    })
    .catch(function(error) {
        console.error('Error:', error);
        alert('Error updating agent status. Please try again.');
    });
}

function deleteAgent(id) {
    if (!confirm('Delete this agent? This action cannot be undone.')) {
        return;
    }
    
    var form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = '<input type="hidden" name="action" value="delete_agent"><input type="hidden" name="id" value="' + id + '">';
    document.body.appendChild(form);
    form.submit();
}

// ============================================================
// SEARCH FUNCTIONALITY
// ============================================================
var searchInput = document.querySelector('.search-wrap input[name="search"]');
if (searchInput) {
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            this.closest('form').submit();
        }
    });
}

// ============================================================
// CLOSE MODAL ON ESCAPE KEY
// ============================================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(function(modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        });
    }
});
</script>
</body>
</html>