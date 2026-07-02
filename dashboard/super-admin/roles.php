<?php
$page_title = "System-Wide Roles (RBAC)";
require_once 'includes/db.php';
$db = Database::getInstance()->getConnection();

// ============================================================
// HANDLE ACTIONS
// ============================================================
$action = $_GET['action'] ?? '';
$role_id = $_GET['id'] ?? 0;
$message = '';
$error = '';
$message_type = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_action = $_POST['action'] ?? '';
    $role_id = (int)($_POST['role_id'] ?? 0);
    
    try {
        switch ($post_action) {
            case 'create':
                $name = trim($_POST['name'] ?? '');
                $slug = trim($_POST['slug'] ?? '');
                $level = trim($_POST['level'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $permissions = $_POST['permissions'] ?? [];
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if (empty($name) || empty($slug) || empty($level)) {
                    throw new Exception("Name, slug, and level are required.");
                }
                
                $check = $db->prepare("SELECT id FROM roles WHERE slug = ? AND tenant_id IS NULL");
                $check->execute([$slug]);
                if ($check->fetch()) {
                    throw new Exception("Role slug '{$slug}' already exists.");
                }
                
                $permissions_json = json_encode($permissions);
                
                $stmt = $db->prepare("INSERT INTO roles (name, slug, level, description, permissions_json, is_system, is_active, created_at) 
                                     VALUES (?, ?, ?, ?, ?, 0, ?, NOW())");
                $stmt->execute([$name, $slug, $level, $description, $permissions_json, $is_active]);
                
                $message = "Role '{$name}' created successfully.";
                $message_type = 'success';
                break;
                
            case 'edit':
                $name = trim($_POST['name'] ?? '');
                $slug = trim($_POST['slug'] ?? '');
                $level = trim($_POST['level'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $permissions = $_POST['permissions'] ?? [];
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if (empty($name) || empty($slug) || empty($level)) {
                    throw new Exception("Name, slug, and level are required.");
                }
                
                $check = $db->prepare("SELECT id FROM roles WHERE slug = ? AND tenant_id IS NULL AND id != ?");
                $check->execute([$slug, $role_id]);
                if ($check->fetch()) {
                    throw new Exception("Role slug '{$slug}' already exists.");
                }
                
                $system_check = $db->prepare("SELECT is_system FROM roles WHERE id = ?");
                $system_check->execute([$role_id]);
                $is_system = $system_check->fetch()['is_system'] ?? 0;
                
                if ($is_system) {
                    $slug = null;
                    $is_active = 1;
                }
                
                $permissions_json = json_encode($permissions);
                
                $sql = "UPDATE roles SET name = ?, level = ?, description = ?, permissions_json = ?, is_active = ?, updated_at = NOW()";
                $params = [$name, $level, $description, $permissions_json, $is_active];
                
                if (!$is_system) {
                    $sql .= ", slug = ?";
                    $params[] = $slug;
                }
                
                $sql .= " WHERE id = ?";
                $params[] = $role_id;
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                
                $message = "Role '{$name}' updated successfully.";
                $message_type = 'success';
                break;
                
            case 'delete':
                $check = $db->prepare("SELECT is_system FROM roles WHERE id = ?");
                $check->execute([$role_id]);
                $is_system = $check->fetch()['is_system'] ?? 0;
                
                if ($is_system) {
                    throw new Exception("Cannot delete system roles.");
                }
                
                $check = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role_id = ?");
                $check->execute([$role_id]);
                $user_count = $check->fetch()['count'];
                
                if ($user_count > 0) {
                    throw new Exception("Cannot delete role that is assigned to {$user_count} user(s).");
                }
                
                $stmt = $db->prepare("DELETE FROM roles WHERE id = ?");
                $stmt->execute([$role_id]);
                
                $message = "Role deleted successfully.";
                $message_type = 'success';
                break;
                
            case 'clone':
                $source = $db->prepare("SELECT name, slug, level, description, permissions_json FROM roles WHERE id = ?");
                $source->execute([$role_id]);
                $source_data = $source->fetch();
                
                if (!$source_data) {
                    throw new Exception("Source role not found.");
                }
                
                $new_name = $source_data['name'] . ' (Copy)';
                $new_slug = $source_data['slug'] . '_copy_' . time();
                
                $stmt = $db->prepare("INSERT INTO roles (name, slug, level, description, permissions_json, is_system, is_active, created_at) 
                                     VALUES (?, ?, ?, ?, ?, 0, 1, NOW())");
                $stmt->execute([$new_name, $new_slug, $source_data['level'], $source_data['description'], $source_data['permissions_json']]);
                
                $new_id = $db->lastInsertId();
                $message = "Role cloned successfully. <a href='roles.php?action=edit&id={$new_id}' style='color:#4f9cf7;'>Edit new role</a>";
                $message_type = 'success';
                break;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
        $message_type = 'error';
    }
}

// ============================================================
// GET ROLE DATA
// ============================================================
$search = $_GET['search'] ?? '';
$filter_level = $_GET['level'] ?? '';
$filter_status = $_GET['status'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_order = $_GET['order'] ?? 'DESC';
$per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Build base query
$base_query = "FROM roles r WHERE r.tenant_id IS NULL";
$params = [];

if ($search) {
    $base_query .= " AND (r.name LIKE ? OR r.slug LIKE ? OR r.description LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if ($filter_level) {
    $base_query .= " AND r.level = ?";
    $params[] = $filter_level;
}

if ($filter_status !== '') {
    $base_query .= " AND r.is_active = ?";
    $params[] = $filter_status === 'active' ? 1 : 0;
}

// Get total count
$count_query = "SELECT COUNT(DISTINCT r.id) as total " . $base_query;
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_count = $count_stmt->fetch()['total'];
$total_pages = ceil($total_count / $per_page);

// Get paginated results
$query = "SELECT 
            r.*,
            (SELECT COUNT(*) FROM users WHERE role_id = r.id) as user_count
          " . $base_query . "
          GROUP BY r.id 
          ORDER BY r.$sort_by $sort_order 
          LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;

$roles = $db->prepare($query);
$roles->execute($params);
$roles = $roles->fetchAll();

// Get all unique levels for filter
$levels = $db->query("SELECT DISTINCT level FROM roles WHERE tenant_id IS NULL ORDER BY level")->fetchAll(PDO::FETCH_COLUMN);

// Get role stats
$stats_query = "SELECT 
                   COUNT(*) as total,
                   SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                   SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive,
                   SUM(CASE WHEN is_system = 1 THEN 1 ELSE 0 END) as system,
                   SUM(CASE WHEN is_system = 0 THEN 1 ELSE 0 END) as custom
                 FROM roles WHERE tenant_id IS NULL";
$stats = $db->query($stats_query)->fetch();

// Get all available permissions
$permissions = [
    'users' => [
        'label' => 'Users',
        'permissions' => [
            'create_users' => 'Create Users',
            'edit_users' => 'Edit Users',
            'delete_users' => 'Delete Users',
            'view_users' => 'View Users',
            'manage_roles' => 'Manage Roles'
        ]
    ],
    'elections' => [
        'label' => 'Elections',
        'permissions' => [
            'create_elections' => 'Create Elections',
            'edit_elections' => 'Edit Elections',
            'delete_elections' => 'Delete Elections',
            'view_elections' => 'View Elections',
            'manage_elections' => 'Manage Elections',
            'manage_candidates' => 'Manage Candidates'
        ]
    ],
    'results' => [
        'label' => 'Results',
        'permissions' => [
            'submit_results' => 'Submit Results',
            'verify_results' => 'Verify Results',
            'view_results' => 'View Results',
            'manage_results' => 'Manage Results',
            'publish_results' => 'Publish Results',
            'manage_reports' => 'Manage Reports'
        ]
    ],
    'finance' => [
        'label' => 'Finance',
        'permissions' => [
            'view_finance' => 'View Finance',
            'manage_finance' => 'Manage Finance',
            'create_budgets' => 'Create Budgets',
            'manage_expenses' => 'Manage Expenses',
            'view_reports' => 'View Financial Reports'
        ]
    ],
    'incidents' => [
        'label' => 'Incidents',
        'permissions' => [
            'report_incidents' => 'Report Incidents',
            'view_incidents' => 'View Incidents',
            'manage_incidents' => 'Manage Incidents',
            'resolve_incidents' => 'Resolve Incidents'
        ]
    ],
    'agents' => [
        'label' => 'Agents',
        'permissions' => [
            'manage_agents' => 'Manage Agents',
            'assign_agents' => 'Assign Agents',
            'view_agents' => 'View Agents',
            'manage_agent_payments' => 'Manage Agent Payments'
        ]
    ],
    'audit' => [
        'label' => 'Audit & Security',
        'permissions' => [
            'view_audit_logs' => 'View Audit Logs',
            'manage_security' => 'Manage Security',
            'view_activity_logs' => 'View Activity Logs'
        ]
    ],
    'tenants' => [
        'label' => 'Tenants',
        'permissions' => [
            'manage_tenants' => 'Manage Tenants',
            'view_tenants' => 'View Tenants'
        ]
    ],
    'broadcasts' => [
        'label' => 'Broadcasts',
        'permissions' => [
            'send_broadcasts' => 'Send Broadcasts',
            'manage_broadcasts' => 'Manage Broadcasts',
            'view_broadcasts' => 'View Broadcasts'
        ]
    ]
];

// Get role for edit
$edit_role = null;
if ($action === 'edit' && $role_id) {
    $stmt = $db->prepare("SELECT * FROM roles WHERE id = ? AND tenant_id IS NULL");
    $stmt->execute([$role_id]);
    $edit_role = $stmt->fetch();
}

include 'includes/base.php';
?>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/header.php'; ?>

<style>
/* ============================================================
   PROFESSIONAL ROLES MANAGEMENT STYLES
   ============================================================ */

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 28px;
    flex-wrap: wrap;
    gap: 16px;
}

.page-header .header-left h1 {
    font-size: 1.8rem;
    font-weight: 600;
    color: #0b1a33;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.page-header .header-left h1 .page-badge {
    font-size: 0.6rem;
    background: #4f9cf7;
    color: white;
    padding: 2px 12px;
    border-radius: 30px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.page-header .header-left .subtitle {
    color: #6d83a5;
    font-size: 0.95rem;
    margin-top: 4px;
}

.page-header .header-actions {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    background: white;
    border-radius: 14px;
    padding: 18px 20px;
    border: 1px solid #eef3f8;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
}

.stat-card .stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.stat-card .stat-icon.total { background: #e8f0fe; color: #4f9cf7; }
.stat-card .stat-icon.active { background: #d1fae5; color: #10b981; }
.stat-card .stat-icon.inactive { background: #fef3c7; color: #f59e0b; }
.stat-card .stat-icon.system { background: #ede9fe; color: #8b5cf6; }
.stat-card .stat-icon.custom { background: #dbeafe; color: #3b82d6; }

.stat-card .stat-info {
    flex: 1;
}

.stat-card .stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: #0b1a33;
    line-height: 1.2;
}

.stat-card .stat-label {
    font-size: 0.75rem;
    color: #6d83a5;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.filter-bar {
    background: white;
    border-radius: 14px;
    padding: 16px 20px;
    margin-bottom: 24px;
    border: 1px solid #eef3f8;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
}

.filter-bar .filter-form {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
}

.filter-bar .filter-group {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #f8faff;
    border: 1px solid #e8edf4;
    border-radius: 10px;
    padding: 0 14px;
    transition: all 0.2s ease;
    flex: 1;
    min-width: 160px;
}

.filter-bar .filter-group:focus-within {
    border-color: #4f9cf7;
    box-shadow: 0 0 0 3px rgba(79, 156, 247, 0.1);
    background: white;
}

.filter-bar .filter-group i {
    color: #8b9bb5;
    font-size: 0.85rem;
}

.filter-bar .filter-group input,
.filter-bar .filter-group select {
    border: none;
    padding: 10px 0;
    background: transparent;
    font-size: 0.85rem;
    color: #1f3149;
    width: 100%;
    outline: none;
}

.filter-bar .filter-group select {
    cursor: pointer;
    appearance: none;
    padding-right: 20px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%238b9bb5' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right center;
}

.filter-bar .filter-actions {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-bar .filter-actions .btn-primary,
.filter-bar .filter-actions .btn-secondary {
    padding: 9px 20px;
    font-size: 0.85rem;
    white-space: nowrap;
}

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
    padding: 16px 20px;
    border-bottom: 1px solid #f0f4fa;
    flex-wrap: wrap;
    gap: 12px;
}

.table-toolbar .toolbar-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.table-toolbar .toolbar-right {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.8rem;
    color: #6d83a5;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.data-table thead {
    background: #f8faff;
    border-bottom: 1px solid #eef3f8;
}

.data-table thead th {
    padding: 12px 16px;
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
}

.data-table thead th a:hover {
    color: #4f9cf7;
}

.data-table thead th a i {
    font-size: 0.6rem;
    opacity: 0.6;
}

.data-table tbody tr {
    transition: background 0.15s;
    border-bottom: 1px solid #f5f8fc;
}

.data-table tbody tr:last-child {
    border-bottom: none;
}

.data-table tbody tr:hover {
    background: #f8faff;
}

.data-table tbody td {
    padding: 12px 16px;
    vertical-align: middle;
    color: #1f3149;
}

.role-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.role-cell .role-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.role-cell .role-icon.system { background: #ede9fe; color: #8b5cf6; }
.role-cell .role-icon.custom { background: #dbeafe; color: #3b82d6; }

.role-cell .role-info {
    min-width: 0;
}

.role-cell .role-name {
    font-weight: 500;
    color: #0b1a33;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.role-cell .role-name .system-badge {
    font-size: 0.55rem;
    background: #ede9fe;
    color: #8b5cf6;
    padding: 1px 8px;
    border-radius: 30px;
    font-weight: 500;
}

.role-cell .role-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.7rem;
    color: #8b9bb5;
    flex-wrap: wrap;
}

.role-cell .role-meta .role-slug {
    background: #f0f4fa;
    padding: 0 8px;
    border-radius: 30px;
}

.level-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.7rem;
    padding: 4px 12px;
    border-radius: 30px;
    font-weight: 500;
    text-transform: capitalize;
}

.level-badge.super_admin { background: #ede9fe; color: #5b21b6; }
.level-badge.client_admin { background: #dbeafe; color: #1e40af; }
.level-badge.national { background: #d1fae5; color: #065f46; }
.level-badge.state { background: #fef3c7; color: #92400e; }
.level-badge.lga { background: #fce4ec; color: #9a1f3c; }
.level-badge.ward { background: #e0f7fa; color: #00695c; }
.level-badge.pu_agent { background: #f3e5f5; color: #6a1b9a; }
.level-badge.senatorial { background: #e8f5e9; color: #2e7d32; }
.level-badge.federal_constituency { background: #fff3e0; color: #e65100; }
.level-badge.party_agent { background: #fce4ec; color: #880e4f; }
.level-badge.volunteer { background: #f1f8e9; color: #33691e; }
.level-badge.observer { background: #e0f2f1; color: #00695c; }
.level-badge.situation_room { background: #f3e5f5; color: #4a148c; }
.level-badge.finance_officer { background: #e8eaf6; color: #283593; }

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.7rem;
    padding: 4px 12px;
    border-radius: 30px;
    font-weight: 500;
    text-transform: capitalize;
}

.status-badge.active { background: #d1fae5; color: #065f46; }
.status-badge.inactive { background: #fee2e2; color: #991b1b; }

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
    font-size: 0.65rem;
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
.action-buttons .btn-icon.clone { color: #f59e0b; }
.action-buttons .btn-icon.clone:hover { background: #fef3c7; }
.action-buttons .btn-icon.delete { color: #ef4444; }
.action-buttons .btn-icon.delete:hover { background: #fee2e2; }
.action-buttons .btn-icon.disabled { opacity: 0.4; cursor: not-allowed; }

.permissions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 12px;
    margin-top: 12px;
}

.permissions-group {
    background: #f8faff;
    border-radius: 10px;
    padding: 12px 16px;
    border: 1px solid #eef3f8;
}

.permissions-group .group-title {
    font-size: 0.8rem;
    font-weight: 600;
    color: #0b1a33;
    margin-bottom: 8px;
    padding-bottom: 6px;
    border-bottom: 1px solid #eef3f8;
}

.permissions-group .permission-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 0;
    font-size: 0.8rem;
    color: #1f3149;
}

.permissions-group .permission-item input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: #4f9cf7;
    cursor: pointer;
}

.permissions-group .permission-item input[type="checkbox"]:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 1000;
}

.modal.active {
    display: block;
}

.modal .modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(11, 26, 51, 0.6);
    backdrop-filter: blur(4px);
    animation: fadeIn 0.2s ease;
}

.modal .modal-content {
    position: relative;
    max-width: 800px;
    margin: 60px auto;
    background: white;
    border-radius: 20px;
    box-shadow: 0 32px 64px rgba(0,0,0,0.2);
    max-height: calc(100vh - 120px);
    overflow: hidden;
    animation: slideUp 0.3s ease;
}

.modal .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid #eef3f8;
    background: #f8faff;
}

.modal .modal-header h2 {
    font-size: 1.2rem;
    font-weight: 600;
    color: #0b1a33;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal .modal-header h2 i {
    color: #4f9cf7;
}

.modal .modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #8b9bb5;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 8px;
    transition: 0.15s;
}

.modal .modal-close:hover {
    background: #f0f4fa;
    color: #1f3149;
}

.modal .modal-body {
    padding: 24px;
    overflow-y: auto;
    max-height: calc(100vh - 200px);
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 500;
    color: #1f3149;
    margin-bottom: 4px;
}

.form-group label .required {
    color: #ef4444;
}

.form-group .form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #dce6f0;
    border-radius: 10px;
    font-size: 0.9rem;
    color: #1f3149;
    background: #f8faff;
    transition: all 0.2s ease;
}

.form-group .form-control:focus {
    outline: none;
    border-color: #4f9cf7;
    box-shadow: 0 0 0 3px rgba(79, 156, 247, 0.1);
    background: white;
}

.form-group .form-control:disabled {
    background: #f0f4fa;
    cursor: not-allowed;
}

.form-group .form-hint {
    font-size: 0.75rem;
    color: #8b9bb5;
    margin-top: 4px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.alert {
    padding: 14px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideDown 0.3s ease;
}

.alert i {
    font-size: 1.2rem;
    flex-shrink: 0;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.alert-success i { color: #10b981; }

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.alert-error i { color: #ef4444; }

.alert-info {
    background: #dbeafe;
    color: #1e40af;
    border: 1px solid #bfdbfe;
}

.alert-info i { color: #4f9cf7; }

.alert .alert-close {
    margin-left: auto;
    background: none;
    border: none;
    color: inherit;
    opacity: 0.6;
    cursor: pointer;
    font-size: 1.1rem;
    padding: 4px;
}

.alert .alert-close:hover {
    opacity: 1;
}

.empty-table {
    text-align: center;
    padding: 60px 20px !important;
    color: #8b9bb5;
}

.empty-table i {
    font-size: 3rem;
    color: #dce6f0;
    display: block;
    margin-bottom: 16px;
}

.empty-table h3 {
    font-size: 1.1rem;
    color: #1f3149;
    margin-bottom: 8px;
}

.empty-table p {
    font-size: 0.9rem;
    margin-bottom: 16px;
}

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

.pagination .pagination-links a.disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.pagination .pagination-links .ellipsis {
    color: #8b9bb5;
}

.info-box {
    padding: 12px 16px;
    background: #fef3c7;
    border-radius: 10px;
    font-size: 0.85rem;
    color: #92400e;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 12px;
}

.info-box i {
    color: #f59e0b;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 1024px) {
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .page-header .header-actions {
        width: 100%;
    }
    
    .page-header .header-actions .btn-primary,
    .page-header .header-actions .btn-secondary {
        flex: 1;
        justify-content: center;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .filter-bar .filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-bar .filter-group {
        width: 100%;
        min-width: unset;
    }
    
    .filter-bar .filter-actions {
        flex-direction: column;
    }
    
    .filter-bar .filter-actions .btn-primary,
    .filter-bar .filter-actions .btn-secondary {
        width: 100%;
        justify-content: center;
    }
    
    .table-toolbar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .table-toolbar .toolbar-left {
        flex-wrap: wrap;
    }
    
    .table-container {
        overflow-x: auto;
    }
    
    .data-table {
        font-size: 0.8rem;
        min-width: 700px;
    }
    
    .data-table thead th,
    .data-table tbody td {
        padding: 10px 12px;
    }
    
    .pagination {
        flex-direction: column;
        align-items: center;
    }
    
    .modal .modal-content {
        margin: 20px;
        max-height: calc(100vh - 40px);
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .permissions-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }
    
    .stat-card {
        padding: 12px 16px;
    }
    
    .stat-card .stat-number {
        font-size: 1.2rem;
    }
    
    .stat-card .stat-icon {
        width: 36px;
        height: 36px;
        font-size: 1rem;
    }
    
    .action-buttons .btn-icon {
        width: 28px;
        height: 28px;
        font-size: 0.75rem;
    }
}
</style>

<main class="main-content">
    <!-- ============================================================
    PAGE HEADER
    ============================================================ -->
    <div class="page-header">
        <div class="header-left">
            <h1>
                <i class="fas fa-shield-alt" style="color:#4f9cf7;"></i>
                System-Wide Roles (RBAC)
                <span class="page-badge">Super Admin</span>
            </h1>
            <p class="subtitle">Manage roles and permissions across the entire platform</p>
        </div>
        <div class="header-actions">
            <button class="btn-primary" onclick="openCreateModal()">
                <i class="fas fa-plus"></i> Create Role
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
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon total"><i class="fas fa-users-cog"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo number_format($stats['total'] ?? 0); ?></div>
                <div class="stat-label">Total Roles</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon active"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo number_format($stats['active'] ?? 0); ?></div>
                <div class="stat-label">Active</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon inactive"><i class="fas fa-times-circle"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo number_format($stats['inactive'] ?? 0); ?></div>
                <div class="stat-label">Inactive</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon system"><i class="fas fa-crown"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo number_format($stats['system'] ?? 0); ?></div>
                <div class="stat-label">System Roles</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon custom"><i class="fas fa-user-plus"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo number_format($stats['custom'] ?? 0); ?></div>
                <div class="stat-label">Custom Roles</div>
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
                <input type="text" name="search" placeholder="Search roles by name, slug, description..." 
                       value="<?php echo htmlspecialchars($search ?? ''); ?>">
            </div>
            
            <div class="filter-group">
                <i class="fas fa-layer-group"></i>
                <select name="level">
                    <option value="">All Levels</option>
                    <?php foreach ($levels as $level): ?>
                    <option value="<?php echo htmlspecialchars($level ?? ''); ?>" <?php echo $filter_level === $level ? 'selected' : ''; ?>>
                        <?php echo ucwords(str_replace('_', ' ', $level ?? '')); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <i class="fas fa-circle"></i>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn-primary"><i class="fas fa-filter"></i> Filter</button>
                <a href="roles.php" class="btn-secondary"><i class="fas fa-times"></i> Clear</a>
            </div>
        </form>
    </div>

    <!-- ============================================================
    ROLES TABLE
    ============================================================ -->
    <div class="table-container">
        <div class="table-toolbar">
            <div class="toolbar-left">
                <span><i class="fas fa-database"></i> <?php echo number_format($total_count); ?> roles</span>
            </div>
            <div class="toolbar-right">
                <span><i class="fas fa-arrow-up"></i> <?php echo ucfirst($sort_by ?? 'created_at'); ?> (<?php echo $sort_order ?? 'DESC'; ?>)</span>
            </div>
        </div>

        <table class="data-table" id="rolesTable">
            <thead>
                <tr>
                    <th style="width:50px;">
                        <a href="?sort=id&order=<?php echo ($sort_order ?? 'DESC') === 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search ?? ''); ?>&level=<?php echo urlencode($filter_level ?? ''); ?>&status=<?php echo urlencode($filter_status ?? ''); ?>&page=<?php echo $page; ?>">
                            ID
                            <?php if ($sort_by === 'id'): ?>
                            <i class="fas fa-sort-<?php echo strtolower($sort_order ?? 'DESC'); ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>Role</th>
                    <th style="width:120px;">Level</th>
                    <th style="width:100px;">Status</th>
                    <th style="width:70px;">Users</th>
                    <th style="width:100px;">
                        <a href="?sort=created_at&order=<?php echo ($sort_order ?? 'DESC') === 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search ?? ''); ?>&level=<?php echo urlencode($filter_level ?? ''); ?>&status=<?php echo urlencode($filter_status ?? ''); ?>&page=<?php echo $page; ?>">
                            Created
                            <?php if ($sort_by === 'created_at'): ?>
                            <i class="fas fa-sort-<?php echo strtolower($sort_order ?? 'DESC'); ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th style="width:220px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($roles)): ?>
                <tr>
                    <td colspan="7" class="empty-table">
                        <i class="fas fa-users-cog"></i>
                        <h3>No roles found</h3>
                        <p>Create your first role to start managing permissions.</p>
                        <button class="btn-primary" onclick="openCreateModal()" style="display:inline-flex;">
                            <i class="fas fa-plus"></i> Create Role
                        </button>
                    </td>
                </tr>
                <?php endif; ?>
                
                <?php foreach ($roles as $role): 
                    $isSystem = ($role['is_system'] ?? 0) == 1;
                    $iconClass = $isSystem ? 'system' : 'custom';
                    $icon = $isSystem ? 'fa-crown' : 'fa-user-tag';
                ?>
                <tr>
                    <td><span style="font-weight:600; color:#4f9cf7;">#<?php echo $role['id']; ?></span></td>
                    <td>
                        <div class="role-cell">
                            <div class="role-icon <?php echo $iconClass; ?>">
                                <i class="fas <?php echo $icon; ?>"></i>
                            </div>
                            <div class="role-info">
                                <div class="role-name">
                                    <?php echo htmlspecialchars($role['name'] ?? ''); ?>
                                    <?php if ($isSystem): ?>
                                    <span class="system-badge">System</span>
                                    <?php endif; ?>
                                </div>
                                <div class="role-meta">
                                    <span class="role-slug"><?php echo htmlspecialchars($role['slug'] ?? ''); ?></span>
                                    <?php if (!empty($role['description'])): ?>
                                    <span>· <?php echo htmlspecialchars(substr($role['description'], 0, 50)) . (strlen($role['description'] ?? '') > 50 ? '...' : ''); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="level-badge <?php echo htmlspecialchars($role['level'] ?? ''); ?>">
                            <?php echo ucwords(str_replace('_', ' ', $role['level'] ?? '')); ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge <?php echo ($role['is_active'] ?? 0) ? 'active' : 'inactive'; ?>">
                            <?php if ($role['is_active'] ?? 0): ?>
                            <i class="fas fa-check-circle"></i> Active
                            <?php else: ?>
                            <i class="fas fa-times-circle"></i> Inactive
                            <?php endif; ?>
                        </span>
                    </td>
                    <td>
                        <span style="font-weight:500; color:#0b1a33;"><?php echo $role['user_count'] ?? 0; ?></span>
                    </td>
                    <td><?php echo !empty($role['created_at']) ? date('M d, Y', strtotime($role['created_at'])) : 'N/A'; ?></td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-icon view" title="View Details" onclick="viewRole(<?php echo $role['id']; ?>)">
                                <i class="fas fa-eye"></i>
                                <span class="tooltip">View Details</span>
                            </button>
                            <a href="roles.php?action=edit&id=<?php echo $role['id']; ?>" class="btn-icon edit" title="Edit">
                                <i class="fas fa-edit"></i>
                                <span class="tooltip">Edit Role</span>
                            </a>
                            
                            <?php if (!$isSystem): ?>
                            <button class="btn-icon clone" title="Clone Role" onclick="cloneRole(<?php echo $role['id']; ?>, '<?php echo htmlspecialchars($role['name'] ?? ''); ?>')">
                                <i class="fas fa-copy"></i>
                                <span class="tooltip">Clone Role</span>
                            </button>
                            <?php endif; ?>
                            
                            <?php if (!$isSystem && ($role['user_count'] ?? 0) == 0): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirmDelete('<?php echo htmlspecialchars($role['name'] ?? ''); ?>');">
                                <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn-icon delete" title="Delete">
                                    <i class="fas fa-trash"></i>
                                    <span class="tooltip">Delete</span>
                                </button>
                            </form>
                            <?php elseif (!$isSystem && ($role['user_count'] ?? 0) > 0): ?>
                            <span class="btn-icon disabled" title="Cannot delete - in use by <?php echo $role['user_count']; ?> user(s)" style="opacity:0.4; cursor:not-allowed;">
                                <i class="fas fa-trash"></i>
                                <span class="tooltip">In use by <?php echo $role['user_count']; ?> users</span>
                            </span>
                            <?php endif; ?>
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
                of <strong><?php echo number_format($total_count); ?></strong> roles
            </div>
            <div class="pagination-links">
                <?php if ($page > 1): ?>
                <a href="?page=1&sort=<?php echo urlencode($sort_by ?? 'created_at'); ?>&order=<?php echo urlencode($sort_order ?? 'DESC'); ?>&search=<?php echo urlencode($search ?? ''); ?>&level=<?php echo urlencode($filter_level ?? ''); ?>&status=<?php echo urlencode($filter_status ?? ''); ?>">
                    <i class="fas fa-angle-double-left"></i>
                </a>
                <a href="?page=<?php echo $page - 1; ?>&sort=<?php echo urlencode($sort_by ?? 'created_at'); ?>&order=<?php echo urlencode($sort_order ?? 'DESC'); ?>&search=<?php echo urlencode($search ?? ''); ?>&level=<?php echo urlencode($filter_level ?? ''); ?>&status=<?php echo urlencode($filter_status ?? ''); ?>">
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
                    echo '<a href="?page=' . $i . '&sort=' . urlencode($sort_by ?? 'created_at') . '&order=' . urlencode($sort_order ?? 'DESC') . '&search=' . urlencode($search ?? '') . '&level=' . urlencode($filter_level ?? '') . '&status=' . urlencode($filter_status ?? '') . '" class="' . $active . '">' . $i . '</a>';
                }
                
                if ($end_page < $total_pages) {
                    echo '<span class="ellipsis">…</span>';
                }
                ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&sort=<?php echo urlencode($sort_by ?? 'created_at'); ?>&order=<?php echo urlencode($sort_order ?? 'DESC'); ?>&search=<?php echo urlencode($search ?? ''); ?>&level=<?php echo urlencode($filter_level ?? ''); ?>&status=<?php echo urlencode($filter_status ?? ''); ?>">
                    <i class="fas fa-angle-right"></i>
                </a>
                <a href="?page=<?php echo $total_pages; ?>&sort=<?php echo urlencode($sort_by ?? 'created_at'); ?>&order=<?php echo urlencode($sort_order ?? 'DESC'); ?>&search=<?php echo urlencode($search ?? ''); ?>&level=<?php echo urlencode($filter_level ?? ''); ?>&status=<?php echo urlencode($filter_status ?? ''); ?>">
                    <i class="fas fa-angle-double-right"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ============================================================
    CREATE/EDIT MODAL
    ============================================================ -->
    <div class="modal <?php echo $edit_role ? 'active' : ''; ?>" id="roleModal">
        <div class="modal-overlay" onclick="closeRoleModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle"><i class="fas fa-<?php echo $edit_role ? 'edit' : 'plus-circle'; ?>"></i> <?php echo $edit_role ? 'Edit Role' : 'Create New Role'; ?></h2>
                <button class="modal-close" onclick="closeRoleModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="roleForm">
                    <input type="hidden" name="action" value="<?php echo $edit_role ? 'edit' : 'create'; ?>">
                    <?php if ($edit_role): ?>
                    <input type="hidden" name="role_id" value="<?php echo $edit_role['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Role Name <span class="required">*</span></label>
                            <input type="text" name="name" class="form-control" 
                                   placeholder="e.g., Election Manager" 
                                   value="<?php echo htmlspecialchars($edit_role['name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Slug <span class="required">*</span></label>
                            <input type="text" name="slug" class="form-control" 
                                   placeholder="e.g., election_manager" 
                                   value="<?php echo htmlspecialchars($edit_role['slug'] ?? ''); ?>" 
                                   <?php echo ($edit_role && ($edit_role['is_system'] ?? 0)) ? 'readonly style="background:#f0f4fa;"' : ''; ?> required>
                            <div class="form-hint">Unique identifier, use lowercase and underscores</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Level <span class="required">*</span></label>
                            <select name="level" class="form-control" required>
                                <option value="">Select Level</option>
                                <option value="super_admin" <?php echo ($edit_role && ($edit_role['level'] ?? '') === 'super_admin') ? 'selected' : ''; ?>>Super Admin</option>
                                <option value="client_admin" <?php echo ($edit_role && ($edit_role['level'] ?? '') === 'client_admin') ? 'selected' : ''; ?>>Client Admin</option>
                                <option value="national" <?php echo ($edit_role && ($edit_role['level'] ?? '') === 'national') ? 'selected' : ''; ?>>National</option>
                                <option value="state" <?php echo ($edit_role && ($edit_role['level'] ?? '') === 'state') ? 'selected' : ''; ?>>State</option>
                                <option value="senatorial" <?php echo ($edit_role && ($edit_role['level'] ?? '') === 'senatorial') ? 'selected' : ''; ?>>Senatorial</option>
                                <option value="federal_constituency" <?php echo ($edit_role && ($edit_role['level'] ?? '') === 'federal_constituency') ? 'selected' : ''; ?>>Federal Constituency</option>
                                <option value="lga" <?php echo ($edit_role && ($edit_role['level'] ?? '') === 'lga') ? 'selected' : ''; ?>>LGA</option>
                                <option value="ward" <?php echo ($edit_role && ($edit_role['level'] ?? '') === 'ward') ? 'selected' : ''; ?>>Ward</option>
                                <option value="pu_agent" <?php echo ($edit_role && ($edit_role['level'] ?? '') === 'pu_agent') ? 'selected' : ''; ?>>PU Agent</option>
                                <option value="party_agent" <?php echo ($edit_role && ($edit_role['level'] ?? '') === 'party_agent') ? 'selected' : ''; ?>>Party Agent</option>
                                <option value="volunteer" <?php echo ($edit_role && ($edit_role['level'] ?? '') === 'volunteer') ? 'selected' : ''; ?>>Volunteer</option>
                                <option value="observer" <?php echo ($edit_role && ($edit_role['level'] ?? '') === 'observer') ? 'selected' : ''; ?>>Observer</option>
                                <option value="situation_room" <?php echo ($edit_role && ($edit_role['level'] ?? '') === 'situation_room') ? 'selected' : ''; ?>>Situation Room</option>
                                <option value="finance_officer" <?php echo ($edit_role && ($edit_role['level'] ?? '') === 'finance_officer') ? 'selected' : ''; ?>>Finance Officer</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="is_active" class="form-control" <?php echo ($edit_role && ($edit_role['is_system'] ?? 0)) ? 'disabled' : ''; ?>>
                                <option value="1" <?php echo ($edit_role && ($edit_role['is_active'] ?? 0)) ? 'selected' : ''; ?>>Active</option>
                                <option value="0" <?php echo ($edit_role && !($edit_role['is_active'] ?? 0)) ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                            <?php if ($edit_role && ($edit_role['is_system'] ?? 0)): ?>
                            <div class="form-hint">System roles cannot be deactivated</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" name="description" class="form-control" 
                               placeholder="Brief description of this role..." 
                               value="<?php echo htmlspecialchars($edit_role['description'] ?? ''); ?>">
                    </div>
                    
                    <hr style="border-color:#eef3f8; margin:20px 0;">
                    
                    <h4 style="font-weight:600; color:#0b1a33; margin-bottom:8px;">
                        <i class="fas fa-key" style="color:#4f9cf7;"></i> Permissions
                    </h4>
                    <p style="font-size:0.85rem; color:#6d83a5; margin-bottom:16px;">
                        Select the permissions this role should have. System roles have predefined permissions.
                    </p>
                    
                    <div class="permissions-grid">
                        <?php 
                        $rolePermissions = $edit_role ? json_decode($edit_role['permissions_json'] ?? '{}', true) : [];
                        foreach ($permissions as $key => $group): 
                        ?>
                        <div class="permissions-group">
                            <div class="group-title"><?php echo htmlspecialchars($group['label']); ?></div>
                            <?php foreach ($group['permissions'] as $permKey => $permLabel): ?>
                            <div class="permission-item">
                                <input type="checkbox" name="permissions[<?php echo $permKey; ?>]" 
                                       value="1" 
                                       id="perm_<?php echo $permKey; ?>"
                                       <?php echo (isset($rolePermissions[$permKey]) && $rolePermissions[$permKey]) ? 'checked' : ''; ?>
                                       <?php echo ($edit_role && ($edit_role['is_system'] ?? 0)) ? 'disabled' : ''; ?>>
                                <label for="perm_<?php echo $permKey; ?>" style="cursor:pointer;">
                                    <?php echo htmlspecialchars($permLabel); ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($edit_role && ($edit_role['is_system'] ?? 0)): ?>
                    <div class="info-box">
                        <i class="fas fa-info-circle"></i> 
                        System roles have predefined permissions and cannot be modified.
                    </div>
                    <?php endif; ?>
                    
                    <div style="display:flex; gap:12px; margin-top:20px; justify-content:flex-end;">
                        <button type="button" class="btn-secondary" onclick="closeRoleModal()">Cancel</button>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> 
                            <?php echo $edit_role ? 'Update Role' : 'Create Role'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ============================================================
    ROLE DETAIL MODAL
    ============================================================ -->
    <div class="modal" id="roleDetailModal">
        <div class="modal-overlay" onclick="closeRoleDetailModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-info-circle"></i> Role Details</h2>
                <button class="modal-close" onclick="closeRoleDetailModal()">&times;</button>
            </div>
            <div class="modal-body" id="roleDetailBody">
                <!-- Loaded via AJAX -->
            </div>
        </div>
    </div>
</main>

<!-- ============================================================
JAVASCRIPT
============================================================ -->
<script>
// ============================================================
// MODAL CONTROLS
// ============================================================
function openCreateModal() {
    const modal = document.getElementById('roleModal');
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Create New Role';
    document.getElementById('roleForm').reset();
    document.querySelector('input[name="action"]').value = 'create';
    const roleIdInput = document.querySelector('input[name="role_id"]');
    if (roleIdInput) roleIdInput.remove();
    document.querySelectorAll('.permission-item input[type="checkbox"]').forEach(cb => cb.checked = false);
    // Remove disabled from all checkboxes
    document.querySelectorAll('.permission-item input[type="checkbox"]').forEach(cb => cb.disabled = false);
    // Reset status select
    document.querySelector('select[name="is_active"]').value = '1';
    document.querySelector('select[name="is_active"]').disabled = false;
    modal.classList.add('active');
}

function closeRoleModal() {
    document.getElementById('roleModal').classList.remove('active');
    // Remove any editing state
    const form = document.getElementById('roleForm');
    form.reset();
}

function closeRoleDetailModal() {
    document.getElementById('roleDetailModal').classList.remove('active');
}

// Close modals on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeRoleModal();
        closeRoleDetailModal();
    }
});

// Close modals on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function() {
        closeRoleModal();
        closeRoleDetailModal();
    });
});

// ============================================================
// VIEW ROLE DETAILS
// ============================================================
function viewRole(roleId) {
    const modal = document.getElementById('roleDetailModal');
    const body = document.getElementById('roleDetailBody');
    
    modal.classList.add('active');
    body.innerHTML = '<div style="text-align:center; padding:40px; color:#6d83a5;"><i class="fas fa-spinner fa-spin" style="font-size:2rem; display:block; margin-bottom:12px;"></i> Loading role details...</div>';
    
    fetch(`role-details.php?id=${roleId}`)
        .then(response => response.text())
        .then(html => {
            body.innerHTML = html;
        })
        .catch(error => {
            body.innerHTML = '<div style="text-align:center; padding:40px; color:#ef4444;"><i class="fas fa-exclamation-circle" style="font-size:2rem; display:block; margin-bottom:12px;"></i> Failed to load role details</div>';
        });
}

// ============================================================
// CLONE ROLE
// ============================================================
function cloneRole(roleId, roleName) {
    if (confirm(`Clone role "${roleName || 'Untitled'}"?\n\nA new role will be created with the same permissions.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="role_id" value="${roleId}">
            <input type="hidden" name="action" value="clone">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// ============================================================
// CONFIRM DELETE
// ============================================================
function confirmDelete(roleName) {
    return confirm(`⚠️ Are you sure you want to delete role "${roleName || 'Untitled'}"?\n\nThis action cannot be undone.`);
}

// ============================================================
// AUTO-SUBMIT ON FILTER CHANGE
// ============================================================
document.querySelectorAll('select[name="level"], select[name="status"]').forEach(select => {
    select.addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
});

// ============================================================
// KEYBOARD SHORTCUTS
// ============================================================
document.addEventListener('keydown', function(e) {
    // Ctrl+F for focus search
    if (e.ctrlKey && e.key === 'f') {
        e.preventDefault();
        document.querySelector('input[name="search"]')?.focus();
    }
    // Ctrl+N for new role
    if (e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        openCreateModal();
    }
});

// ============================================================
// SLUG AUTO-GENERATION
// ============================================================
document.querySelector('input[name="name"]')?.addEventListener('input', function() {
    const slugInput = document.querySelector('input[name="slug"]');
    if (slugInput && !slugInput.readOnly) {
        const slug = this.value
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');
        slugInput.value = slug;
    }
});

// ============================================================
// ENSURE EDIT MODAL OPENS ON PAGE LOAD
// ============================================================
<?php if ($edit_role): ?>
document.addEventListener('DOMContentLoaded', function() {
    // The modal is already active via the 'active' class
    // Make sure the form is properly populated
    const modal = document.getElementById('roleModal');
    if (modal) {
        modal.classList.add('active');
    }
});
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>