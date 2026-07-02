<?php
$page_title = "System Permissions";
require_once 'includes/db.php';
$db = Database::getInstance()->getConnection();

// ============================================================
// HANDLE ACTIONS
// ============================================================
$message = '';
$error = '';
$message_type = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_permission':
                $module = trim($_POST['module'] ?? '');
                $action_name = trim($_POST['action_name'] ?? '');
                $slug = trim($_POST['slug'] ?? '');
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                if (empty($module) || empty($action_name) || empty($slug) || empty($name)) {
                    throw new Exception("Module, action, slug, and name are required.");
                }
                
                // Check if slug exists
                $check = $db->prepare("SELECT id FROM permissions WHERE slug = ?");
                $check->execute([$slug]);
                if ($check->fetch()) {
                    throw new Exception("Permission slug '{$slug}' already exists.");
                }
                
                // Check if module+action combo exists
                $check = $db->prepare("SELECT id FROM permissions WHERE module = ? AND action = ?");
                $check->execute([$module, $action_name]);
                if ($check->fetch()) {
                    throw new Exception("Permission '{$module}.{$action_name}' already exists.");
                }
                
                $stmt = $db->prepare("INSERT INTO permissions (module, action, slug, name, description, created_at) 
                                     VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$module, $action_name, $slug, $name, $description]);
                
                $message = "Permission '{$name}' created successfully.";
                $message_type = 'success';
                break;
                
            case 'delete_permission':
                $permission_id = (int)($_POST['permission_id'] ?? 0);
                
                // Check if permission is in use by any role
                $check = $db->prepare("SELECT COUNT(*) as count FROM roles WHERE JSON_CONTAINS(permissions_json, JSON_OBJECT(?, 1), '$')");
                $check->execute([$permission_id]);
                $role_count = $check->fetch()['count'];
                
                if ($role_count > 0) {
                    throw new Exception("Cannot delete permission that is assigned to {$role_count} role(s).");
                }
                
                $stmt = $db->prepare("DELETE FROM permissions WHERE id = ?");
                $stmt->execute([$permission_id]);
                
                $message = "Permission deleted successfully.";
                $message_type = 'success';
                break;
                
            case 'bulk_delete':
                $permission_ids = $_POST['permission_ids'] ?? [];
                
                if (!empty($permission_ids) && is_array($permission_ids)) {
                    // Check which permissions are in use
                    $in_use = [];
                    foreach ($permission_ids as $pid) {
                        $check = $db->prepare("SELECT COUNT(*) as count FROM roles WHERE JSON_CONTAINS(permissions_json, JSON_OBJECT(?, 1), '$')");
                        $check->execute([$pid]);
                        if ($check->fetch()['count'] > 0) {
                            $in_use[] = $pid;
                        }
                    }
                    
                    if (!empty($in_use)) {
                        throw new Exception("Cannot delete " . count($in_use) . " permission(s) that are in use by roles.");
                    }
                    
                    $placeholders = implode(',', array_fill(0, count($permission_ids), '?'));
                    $stmt = $db->prepare("DELETE FROM permissions WHERE id IN ($placeholders)");
                    $stmt->execute($permission_ids);
                    $message = count($permission_ids) . " permissions deleted successfully.";
                    $message_type = 'success';
                }
                break;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
        $message_type = 'error';
    }
}

// ============================================================
// GET PERMISSION DATA
// ============================================================
$search = $_GET['search'] ?? '';
$filter_module = $_GET['module'] ?? '';
$sort_by = $_GET['sort'] ?? 'module';
$sort_order = $_GET['order'] ?? 'ASC';
$per_page = 25;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Build base query
$base_query = "FROM permissions p WHERE 1=1";
$params = [];

if ($search) {
    $base_query .= " AND (p.name LIKE ? OR p.slug LIKE ? OR p.module LIKE ? OR p.action LIKE ? OR p.description LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
}

if ($filter_module) {
    $base_query .= " AND p.module = ?";
    $params[] = $filter_module;
}

// Get total count
$count_query = "SELECT COUNT(DISTINCT p.id) as total " . $base_query;
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_count = $count_stmt->fetch()['total'];
$total_pages = ceil($total_count / $per_page);

// Get paginated results
$query = "SELECT 
            p.*,
            (SELECT COUNT(*) FROM roles WHERE JSON_CONTAINS(permissions_json, JSON_OBJECT(p.id, 1), '$')) as role_count
          " . $base_query . "
          GROUP BY p.id 
          ORDER BY p.$sort_by $sort_order 
          LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;

$permissions = $db->prepare($query);
$permissions->execute($params);
$permissions = $permissions->fetchAll();

// Get all unique modules for filter
$modules = $db->query("SELECT DISTINCT module FROM permissions ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);

// Get permission stats
$stats_query = "SELECT COUNT(*) as total FROM permissions";
$stats = $db->query($stats_query)->fetch();

include 'includes/base.php';
?>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/header.php'; ?>

<style>
/* ============================================================
   PERMISSIONS MANAGEMENT STYLES
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

.table-toolbar .toolbar-left .bulk-actions {
    display: flex;
    gap: 6px;
    align-items: center;
}

.table-toolbar .toolbar-left .bulk-actions select {
    padding: 6px 12px;
    border: 1px solid #dce6f0;
    border-radius: 8px;
    font-size: 0.8rem;
    background: white;
    color: #1f3149;
    outline: none;
}

.table-toolbar .toolbar-left .bulk-actions select:focus {
    border-color: #4f9cf7;
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

.permission-cell .permission-name {
    font-weight: 500;
    color: #0b1a33;
}

.permission-cell .permission-slug {
    font-size: 0.7rem;
    color: #8b9bb5;
    display: block;
}

.permission-cell .permission-description {
    font-size: 0.8rem;
    color: #6d83a5;
}

.module-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.7rem;
    padding: 4px 12px;
    border-radius: 30px;
    font-weight: 500;
    background: #dbeafe;
    color: #1e40af;
}

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

.action-buttons .btn-icon.delete { color: #ef4444; }
.action-buttons .btn-icon.delete:hover { background: #fee2e2; }
.action-buttons .btn-icon.disabled { opacity: 0.4; cursor: not-allowed; }

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
    max-width: 600px;
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

.pagination .pagination-links .ellipsis {
    color: #8b9bb5;
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
                <i class="fas fa-lock" style="color:#4f9cf7;"></i>
                System Permissions
                <span class="page-badge">RBAC</span>
            </h1>
            <p class="subtitle">Manage all system permissions and access controls</p>
        </div>
        <div class="header-actions">
            <button class="btn-primary" onclick="openCreateModal()">
                <i class="fas fa-plus"></i> Create Permission
            </button>
        </div>
    </div>

    <!-- ============================================================
    ALERTS
    ============================================================ -->
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type ?: 'success'; ?>">
        <i class="fas fa-<?php echo $message_type === 'error' ? 'exclamation-circle' : 'check-circle'; ?>"></i>
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
            <div class="stat-icon total"><i class="fas fa-key"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo number_format($stats['total'] ?? 0); ?></div>
                <div class="stat-label">Total Permissions</div>
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
                <input type="text" name="search" placeholder="Search permissions..." 
                       value="<?php echo htmlspecialchars($search ?? ''); ?>">
            </div>
            
            <div class="filter-group">
                <i class="fas fa-folder"></i>
                <select name="module">
                    <option value="">All Modules</option>
                    <?php foreach ($modules as $module): ?>
                    <option value="<?php echo htmlspecialchars($module ?? ''); ?>" <?php echo $filter_module === $module ? 'selected' : ''; ?>>
                        <?php echo ucwords(str_replace('_', ' ', $module ?? '')); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn-primary"><i class="fas fa-filter"></i> Filter</button>
                <a href="permissions.php" class="btn-secondary"><i class="fas fa-times"></i> Clear</a>
            </div>
        </form>
    </div>

    <!-- ============================================================
    PERMISSIONS TABLE
    ============================================================ -->
    <div class="table-container">
        <div class="table-toolbar">
            <div class="toolbar-left">
                <form method="POST" id="bulkActionForm" onsubmit="return confirmBulkAction();">
                    <input type="hidden" name="action" value="bulk_delete">
                    <div class="bulk-actions">
                        <input type="checkbox" id="selectAll" onchange="toggleAllCheckboxes()">
                        <label for="selectAll" style="font-size:0.8rem; color:#6d83a5; cursor:pointer;">Select All</label>
                        <select name="bulk_action" id="bulkAction">
                            <option value="">Bulk Actions</option>
                            <option value="delete">Delete</option>
                        </select>
                        <button type="submit" class="btn-secondary" style="padding:4px 16px; font-size:0.8rem;">
                            Apply
                        </button>
                    </div>
                </form>
            </div>
            <div class="toolbar-right">
                <span><i class="fas fa-database"></i> <?php echo number_format($total_count); ?> permissions</span>
                <span><i class="fas fa-arrow-up"></i> <?php echo ucfirst($sort_by ?? 'module'); ?> (<?php echo $sort_order ?? 'ASC'; ?>)</span>
            </div>
        </div>

        <table class="data-table" id="permissionsTable">
            <thead>
                <tr>
                    <th style="width:36px;">
                        <input type="checkbox" id="selectAllHeader" onchange="toggleAllCheckboxes()">
                    </th>
                    <th style="width:50px;">
                        <a href="?sort=id&order=<?php echo ($sort_order ?? 'ASC') === 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search ?? ''); ?>&module=<?php echo urlencode($filter_module ?? ''); ?>&page=<?php echo $page; ?>">
                            ID
                            <?php if ($sort_by === 'id'): ?>
                            <i class="fas fa-sort-<?php echo strtolower($sort_order ?? 'ASC'); ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>Permission</th>
                    <th style="width:120px;">Module</th>
                    <th style="width:70px;">Roles</th>
                    <th style="width:120px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($permissions)): ?>
                <tr>
                    <td colspan="6" class="empty-table">
                        <i class="fas fa-key"></i>
                        <h3>No permissions found</h3>
                        <p>Create your first permission to start building access controls.</p>
                        <button class="btn-primary" onclick="openCreateModal()" style="display:inline-flex;">
                            <i class="fas fa-plus"></i> Create Permission
                        </button>
                    </td>
                </tr>
                <?php endif; ?>
                
                <?php foreach ($permissions as $perm): ?>
                <tr>
                    <td>
                        <input type="checkbox" name="permission_ids[]" value="<?php echo $perm['id']; ?>" class="permission-checkbox">
                    </td>
                    <td><span style="font-weight:600; color:#4f9cf7;">#<?php echo $perm['id']; ?></span></td>
                    <td>
                        <div class="permission-cell">
                            <div class="permission-name">
                                <?php echo htmlspecialchars($perm['name'] ?? ''); ?>
                            </div>
                            <div class="permission-slug">
                                <?php echo htmlspecialchars($perm['slug'] ?? ''); ?>
                            </div>
                            <div class="permission-description">
                                <?php echo htmlspecialchars($perm['description'] ?? 'No description'); ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="module-badge">
                            <i class="fas fa-folder"></i>
                            <?php echo ucwords(str_replace('_', ' ', $perm['module'] ?? '')); ?>
                        </span>
                    </td>
                    <td>
                        <span style="font-weight:500; color:#0b1a33;"><?php echo $perm['role_count'] ?? 0; ?></span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <?php if (($perm['role_count'] ?? 0) == 0): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete permission \'<?php echo htmlspecialchars($perm['name'] ?? ''); ?>\'?');">
                                <input type="hidden" name="permission_id" value="<?php echo $perm['id']; ?>">
                                <input type="hidden" name="action" value="delete_permission">
                                <button type="submit" class="btn-icon delete" title="Delete">
                                    <i class="fas fa-trash"></i>
                                    <span class="tooltip">Delete</span>
                                </button>
                            </form>
                            <?php else: ?>
                            <span class="btn-icon disabled" title="In use by <?php echo $perm['role_count']; ?> role(s)" style="opacity:0.4; cursor:not-allowed;">
                                <i class="fas fa-trash"></i>
                                <span class="tooltip">In use by <?php echo $perm['role_count']; ?> roles</span>
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
                of <strong><?php echo number_format($total_count); ?></strong> permissions
            </div>
            <div class="pagination-links">
                <?php if ($page > 1): ?>
                <a href="?page=1&sort=<?php echo urlencode($sort_by ?? 'module'); ?>&order=<?php echo urlencode($sort_order ?? 'ASC'); ?>&search=<?php echo urlencode($search ?? ''); ?>&module=<?php echo urlencode($filter_module ?? ''); ?>">
                    <i class="fas fa-angle-double-left"></i>
                </a>
                <a href="?page=<?php echo $page - 1; ?>&sort=<?php echo urlencode($sort_by ?? 'module'); ?>&order=<?php echo urlencode($sort_order ?? 'ASC'); ?>&search=<?php echo urlencode($search ?? ''); ?>&module=<?php echo urlencode($filter_module ?? ''); ?>">
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
                    echo '<a href="?page=' . $i . '&sort=' . urlencode($sort_by ?? 'module') . '&order=' . urlencode($sort_order ?? 'ASC') . '&search=' . urlencode($search ?? '') . '&module=' . urlencode($filter_module ?? '') . '" class="' . $active . '">' . $i . '</a>';
                }
                
                if ($end_page < $total_pages) {
                    echo '<span class="ellipsis">…</span>';
                }
                ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&sort=<?php echo urlencode($sort_by ?? 'module'); ?>&order=<?php echo urlencode($sort_order ?? 'ASC'); ?>&search=<?php echo urlencode($search ?? ''); ?>&module=<?php echo urlencode($filter_module ?? ''); ?>">
                    <i class="fas fa-angle-right"></i>
                </a>
                <a href="?page=<?php echo $total_pages; ?>&sort=<?php echo urlencode($sort_by ?? 'module'); ?>&order=<?php echo urlencode($sort_order ?? 'ASC'); ?>&search=<?php echo urlencode($search ?? ''); ?>&module=<?php echo urlencode($filter_module ?? ''); ?>">
                    <i class="fas fa-angle-double-right"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ============================================================
    CREATE MODAL
    ============================================================ -->
    <div class="modal" id="permissionModal">
        <div class="modal-overlay" onclick="closePermissionModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle"><i class="fas fa-plus-circle"></i> Create Permission</h2>
                <button class="modal-close" onclick="closePermissionModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="permissionForm">
                    <input type="hidden" name="action" value="create_permission">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Module <span class="required">*</span></label>
                            <input type="text" name="module" class="form-control" 
                                   placeholder="e.g., users, elections, results" 
                                   id="moduleInput" required>
                            <div class="form-hint">Group/category for this permission</div>
                        </div>
                        <div class="form-group">
                            <label>Action <span class="required">*</span></label>
                            <input type="text" name="action_name" class="form-control" 
                                   placeholder="e.g., create, edit, delete" 
                                   id="actionInput" required>
                            <div class="form-hint">The specific action</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Slug <span class="required">*</span></label>
                            <input type="text" name="slug" class="form-control" 
                                   placeholder="e.g., users_create" 
                                   id="slugInput" required>
                            <div class="form-hint">Unique identifier, use lowercase and underscores</div>
                        </div>
                        <div class="form-group">
                            <label>Name <span class="required">*</span></label>
                            <input type="text" name="name" class="form-control" 
                                   placeholder="e.g., Create Users" 
                                   id="nameInput" required>
                            <div class="form-hint">Display name for the permission</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" name="description" class="form-control" 
                               placeholder="Brief description of this permission..." 
                               id="descriptionInput">
                    </div>
                    
                    <div style="display:flex; gap:12px; margin-top:20px; justify-content:flex-end;">
                        <button type="button" class="btn-secondary" onclick="closePermissionModal()">Cancel</button>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Create Permission
                        </button>
                    </div>
                </form>
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
    const modal = document.getElementById('permissionModal');
    document.getElementById('permissionForm').reset();
    modal.classList.add('active');
}

function closePermissionModal() {
    document.getElementById('permissionModal').classList.remove('active');
}

// Close modals on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePermissionModal();
    }
});

// Close modals on overlay click
document.querySelector('.modal-overlay')?.addEventListener('click', function() {
    closePermissionModal();
});

// ============================================================
// AUTO-SLUG GENERATION
// ============================================================
document.getElementById('moduleInput')?.addEventListener('input', generateSlug);
document.getElementById('actionInput')?.addEventListener('input', generateSlug);

function generateSlug() {
    const module = document.getElementById('moduleInput').value || '';
    const action = document.getElementById('actionInput').value || '';
    const slugInput = document.getElementById('slugInput');
    
    if (module && action && slugInput) {
        const slug = `${module}_${action}`.toLowerCase().replace(/[^a-z0-9_]+/g, '_');
        slugInput.value = slug;
    }
}

// ============================================================
// BULK ACTIONS
// ============================================================
function toggleAllCheckboxes() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.permission-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
}

function confirmBulkAction() {
    const bulkAction = document.getElementById('bulkAction');
    const selected = document.querySelectorAll('.permission-checkbox:checked');
    
    if (!bulkAction.value) {
        alert('Please select an action to perform.');
        return false;
    }
    
    if (selected.length === 0) {
        alert('Please select at least one permission.');
        return false;
    }
    
    return confirm(`Are you sure you want to delete ${selected.length} selected permission(s)?`);
}

// ============================================================
// AUTO-SUBMIT ON FILTER CHANGE
// ============================================================
document.querySelectorAll('select[name="module"]').forEach(select => {
    select.addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
});

// ============================================================
// KEYBOARD SHORTCUTS
// ============================================================
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'f') {
        e.preventDefault();
        document.querySelector('input[name="search"]')?.focus();
    }
    if (e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        openCreateModal();
    }
});
</script>

<?php include 'includes/footer.php'; ?>