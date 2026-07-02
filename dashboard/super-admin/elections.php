<?php
$page_title = "Election Templates";
require_once 'includes/db.php';
$db = Database::getInstance()->getConnection();

// ============================================================
// HELPER: GET TENANT ID
// ============================================================
function getTenantId() {
    // First check if tenant_id is in session
    if (isset($_SESSION['tenant_id']) && !empty($_SESSION['tenant_id'])) {
        return (int)$_SESSION['tenant_id'];
    }
    
    // If not in session, get the first active tenant
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id FROM tenants WHERE is_active = 1 AND deleted_at IS NULL LIMIT 1");
        $stmt->execute();
        $tenant = $stmt->fetch();
        if ($tenant) {
            $_SESSION['tenant_id'] = (int)$tenant['id'];
            return (int)$tenant['id'];
        }
    } catch (Exception $e) {
        // Fall through
    }
    
    // If still no tenant, return null
    return null;
}

// ============================================================
// GET TENANT NAME
// ============================================================
function getTenantName($tenantId) {
    if (!$tenantId) return 'No Tenant';
    
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT name FROM tenants WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch();
        return $tenant ? $tenant['name'] : 'Unknown Tenant';
    } catch (Exception $e) {
        return 'Unknown Tenant';
    }
}

// ============================================================
// GET ACTIVE TENANT
// ============================================================
$tenant_id = getTenantId();

// If no tenant exists, show a message
if (!$tenant_id) {
    $message = "No tenant available. Please create a tenant first.";
    $message_type = 'error';
}

// ============================================================
// HANDLE ACTIONS
// ============================================================
$message = '';
$error = '';
$message_type = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $tenant_id = getTenantId();
    
    try {
        if (!$tenant_id) {
            throw new Exception("No tenant available. Please create a tenant first.");
        }
        
        switch ($action) {
            case 'create_template':
                $name = trim($_POST['name'] ?? '');
                $type = trim($_POST['type'] ?? '');
                $cycle = trim($_POST['cycle'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $settings = $_POST['settings'] ?? [];
                $status = $_POST['status'] ?? 'draft';
                $election_date = !empty($_POST['election_date']) ? $_POST['election_date'] : date('Y-m-d');
                $start_time = $_POST['start_time'] ?? null;
                $end_time = $_POST['end_time'] ?? null;
                
                if (empty($name) || empty($type) || empty($cycle)) {
                    throw new Exception("Name, type, and cycle are required.");
                }
                
                $settings_json = json_encode($settings);
                
                $stmt = $db->prepare("INSERT INTO elections 
                    (tenant_id, name, type, cycle, election_date, start_time, end_time, description, settings_json, status, created_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([
                    $tenant_id,
                    $name,
                    $type,
                    $cycle,
                    $election_date,
                    $start_time,
                    $end_time,
                    $description,
                    $settings_json,
                    $status,
                    $_SESSION['user_id'] ?? 1
                ]);
                
                $template_id = $db->lastInsertId();
                
                $message = "Template '{$name}' created successfully.";
                $message_type = 'success';
                break;
                
            case 'edit_template':
                $template_id = (int)($_POST['template_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $type = trim($_POST['type'] ?? '');
                $cycle = trim($_POST['cycle'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $settings = $_POST['settings'] ?? [];
                $status = $_POST['status'] ?? 'draft';
                $election_date = !empty($_POST['election_date']) ? $_POST['election_date'] : date('Y-m-d');
                $start_time = $_POST['start_time'] ?? null;
                $end_time = $_POST['end_time'] ?? null;
                
                if (empty($name) || empty($type) || empty($cycle)) {
                    throw new Exception("Name, type, and cycle are required.");
                }
                
                $settings_json = json_encode($settings);
                
                $stmt = $db->prepare("UPDATE elections 
                    SET name = ?, type = ?, cycle = ?, election_date = ?, start_time = ?, end_time = ?,
                        description = ?, settings_json = ?, status = ?, updated_at = NOW() 
                    WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL");
                $stmt->execute([$name, $type, $cycle, $election_date, $start_time, $end_time, 
                    $description, $settings_json, $status, $template_id, $tenant_id]);
                
                $message = "Template '{$name}' updated successfully.";
                $message_type = 'success';
                break;
                
            case 'delete_template':
                $template_id = (int)($_POST['template_id'] ?? 0);
                
                $stmt = $db->prepare("UPDATE elections SET deleted_at = NOW(), updated_at = NOW() WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$template_id, $tenant_id]);
                
                $message = "Template deleted successfully.";
                $message_type = 'success';
                break;
                
            case 'duplicate_template':
                $template_id = (int)($_POST['template_id'] ?? 0);
                
                $source = $db->prepare("SELECT * FROM elections WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL");
                $source->execute([$template_id, $tenant_id]);
                $source_data = $source->fetch();
                
                if (!$source_data) {
                    throw new Exception("Source template not found.");
                }
                
                $new_name = $source_data['name'] . ' (Copy)';
                
                $stmt = $db->prepare("INSERT INTO elections 
                    (tenant_id, name, type, cycle, election_date, start_time, end_time, description, settings_json, status, created_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([
                    $tenant_id,
                    $new_name,
                    $source_data['type'],
                    $source_data['cycle'],
                    $source_data['election_date'],
                    $source_data['start_time'],
                    $source_data['end_time'],
                    $source_data['description'],
                    $source_data['settings_json'],
                    'draft',
                    $_SESSION['user_id'] ?? 1
                ]);
                
                $new_id = $db->lastInsertId();
                
                $message = "Template duplicated successfully. <a href='elections.php?action=edit&id={$new_id}' style='color:#4f9cf7;'>Edit new template</a>";
                $message_type = 'success';
                break;
                
            case 'archive_template':
                $template_id = (int)($_POST['template_id'] ?? 0);
                
                $stmt = $db->prepare("UPDATE elections SET status = 'archived', updated_at = NOW() WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL");
                $stmt->execute([$template_id, $tenant_id]);
                
                $message = "Template archived successfully.";
                $message_type = 'success';
                break;
                
            case 'assign_template':
                $template_id = (int)($_POST['template_id'] ?? 0);
                $assign_to = $_POST['assign_to'] ?? [];
                
                if (empty($assign_to)) {
                    throw new Exception("Please select at least one entity to assign.");
                }
                
                $assign_json = json_encode([
                    'assigned_to' => $assign_to,
                    'assigned_at' => date('Y-m-d H:i:s'),
                    'assigned_by' => $_SESSION['user_id'] ?? 1
                ]);
                
                $stmt = $db->prepare("UPDATE elections SET settings_json = JSON_SET(settings_json, '$.assignment', CAST(? AS JSON)) WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$assign_json, $template_id, $tenant_id]);
                
                $message = "Template assigned to " . count($assign_to) . " entity(s) successfully.";
                $message_type = 'success';
                break;
                
            case 'bulk_action':
                $bulk_action = $_POST['bulk_action'] ?? '';
                $template_ids = $_POST['template_ids'] ?? [];
                
                if (!empty($template_ids) && is_array($template_ids)) {
                    $placeholders = implode(',', array_fill(0, count($template_ids), '?'));
                    $params = array_merge($template_ids, [$tenant_id]);
                    
                    switch ($bulk_action) {
                        case 'archive':
                            $stmt = $db->prepare("UPDATE elections SET status = 'archived', updated_at = NOW() WHERE id IN ($placeholders) AND tenant_id = ? AND deleted_at IS NULL");
                            $stmt->execute($params);
                            $message = count($template_ids) . " templates archived successfully.";
                            $message_type = 'success';
                            break;
                        case 'delete':
                            $stmt = $db->prepare("UPDATE elections SET deleted_at = NOW(), updated_at = NOW() WHERE id IN ($placeholders) AND tenant_id = ?");
                            $stmt->execute($params);
                            $message = count($template_ids) . " templates deleted successfully.";
                            $message_type = 'success';
                            break;
                        case 'activate':
                            $stmt = $db->prepare("UPDATE elections SET status = 'active', updated_at = NOW() WHERE id IN ($placeholders) AND tenant_id = ? AND deleted_at IS NULL");
                            $stmt->execute($params);
                            $message = count($template_ids) . " templates activated successfully.";
                            $message_type = 'success';
                            break;
                    }
                }
                break;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
        $message_type = 'error';
    }
}

// ============================================================
// GET TEMPLATE DATA
// ============================================================
$tenant_id = getTenantId();
$tenant_name = $tenant_id ? getTenantName($tenant_id) : 'No Tenant';

$search = $_GET['search'] ?? '';
$filter_type = $_GET['type'] ?? '';
$filter_status = $_GET['status'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_order = $_GET['order'] ?? 'DESC';
$per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Build base query
if ($tenant_id) {
    $base_query = "FROM elections e
                   LEFT JOIN tenants t ON e.tenant_id = t.id
                   WHERE e.deleted_at IS NULL AND e.tenant_id = ?";
    $params = [$tenant_id];
} else {
    $base_query = "FROM elections e
                   LEFT JOIN tenants t ON e.tenant_id = t.id
                   WHERE e.deleted_at IS NULL";
    $params = [];
}

if ($search) {
    $base_query .= " AND (e.name LIKE ? OR e.type LIKE ? OR e.cycle LIKE ? OR e.description LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($filter_type) {
    $base_query .= " AND e.type = ?";
    $params[] = $filter_type;
}

if ($filter_status) {
    $base_query .= " AND e.status = ?";
    $params[] = $filter_status;
}

// Get total count
$count_query = "SELECT COUNT(DISTINCT e.id) as total " . $base_query;
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_count = $count_stmt->fetch()['total'];
$total_pages = ceil($total_count / $per_page);

// Get paginated results
$query = "SELECT 
            e.*,
            t.name as tenant_name,
            t.slug as tenant_slug
          " . $base_query . "
          GROUP BY e.id 
          ORDER BY e.$sort_by $sort_order 
          LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;

$templates = $db->prepare($query);
$templates->execute($params);
$templates = $templates->fetchAll();

// Get filter options
$types = ['presidential', 'governorship', 'senatorial', 'house_of_reps', 'house_of_assembly', 'lga_chairman', 'councillorship', 'party_primary', 'internal_party'];
$statuses = ['draft', 'upcoming', 'active', 'closed', 'cancelled', 'archived'];

// Get template stats
if ($tenant_id) {
    $stats_query = "SELECT 
                       COUNT(*) as total,
                       SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
                       SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                       SUM(CASE WHEN status = 'upcoming' THEN 1 ELSE 0 END) as upcoming,
                       SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
                       SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived
                     FROM elections WHERE deleted_at IS NULL AND tenant_id = ?";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute([$tenant_id]);
    $stats = $stats_stmt->fetch();
} else {
    $stats = [
        'total' => 0,
        'draft' => 0,
        'active' => 0,
        'upcoming' => 0,
        'closed' => 0,
        'archived' => 0
    ];
}

// Get template for edit
$edit_template = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    if ($tenant_id) {
        $stmt = $db->prepare("SELECT * FROM elections WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL");
        $stmt->execute([$_GET['id'], $tenant_id]);
        $edit_template = $stmt->fetch();
    }
}

include 'includes/base.php';
?>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/header.php'; ?>

<style>
/* ============================================================
   ELECTION TEMPLATES STYLES
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

.tenant-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #e8f0fe;
    color: #1e40af;
    padding: 6px 16px;
    border-radius: 30px;
    font-size: 0.85rem;
    font-weight: 500;
}

.tenant-badge i {
    color: #4f9cf7;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
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
.stat-card .stat-icon.draft { background: #f3f4f6; color: #6b7280; }
.stat-card .stat-icon.active { background: #d1fae5; color: #10b981; }
.stat-card .stat-icon.upcoming { background: #dbeafe; color: #3b82f6; }
.stat-card .stat-icon.closed { background: #fef3c7; color: #f59e0b; }
.stat-card .stat-icon.archived { background: #fee2e2; color: #ef4444; }

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

.template-cell .template-name {
    font-weight: 500;
    color: #0b1a33;
}

.template-cell .template-meta {
    font-size: 0.7rem;
    color: #8b9bb5;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.template-cell .template-meta .tenant-tag {
    background: #f0f4fa;
    padding: 0 8px;
    border-radius: 30px;
}

.type-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.7rem;
    padding: 4px 12px;
    border-radius: 30px;
    font-weight: 500;
    text-transform: capitalize;
}

.type-badge.presidential { background: #ede9fe; color: #5b21b6; }
.type-badge.governorship { background: #dbeafe; color: #1e40af; }
.type-badge.senatorial { background: #d1fae5; color: #065f46; }
.type-badge.house_of_reps { background: #fef3c7; color: #92400e; }
.type-badge.house_of_assembly { background: #fce4ec; color: #9a1f3c; }
.type-badge.lga_chairman { background: #e0f7fa; color: #00695c; }
.type-badge.councillorship { background: #f3e5f5; color: #6a1b9a; }
.type-badge.party_primary { background: #fff3e0; color: #e65100; }
.type-badge.internal_party { background: #f1f8e9; color: #33691e; }

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

.status-badge.draft { background: #f3f4f6; color: #4b5563; }
.status-badge.upcoming { background: #dbeafe; color: #1e40af; }
.status-badge.active { background: #d1fae5; color: #065f46; }
.status-badge.closed { background: #fef3c7; color: #92400e; }
.status-badge.cancelled { background: #fee2e2; color: #991b1b; }
.status-badge.archived { background: #f3f4f6; color: #6b7280; }

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
.action-buttons .btn-icon.duplicate { color: #f59e0b; }
.action-buttons .btn-icon.duplicate:hover { background: #fef3c7; }
.action-buttons .btn-icon.archive { color: #6b7280; }
.action-buttons .btn-icon.archive:hover { background: #f3f4f6; }
.action-buttons .btn-icon.assign { color: #3b82f6; }
.action-buttons .btn-icon.assign:hover { background: #dbeafe; }
.action-buttons .btn-icon.delete { color: #ef4444; }
.action-buttons .btn-icon.delete:hover { background: #fee2e2; }

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
    max-width: 700px;
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
        min-width: 800px;
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
                <i class="fas fa-vote-yea" style="color:#4f9cf7;"></i>
                Election Templates
                <span class="page-badge">Templates</span>
            </h1>
            <p class="subtitle">Create and manage election templates with predefined settings, forms, and workflows</p>
            <?php if ($tenant_id): ?>
            <div class="tenant-badge">
                <i class="fas fa-building"></i>
                <?php echo htmlspecialchars($tenant_name); ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="header-actions">
            <?php if ($tenant_id): ?>
            <button class="btn-primary" onclick="openCreateModal()">
                <i class="fas fa-plus"></i> Create Template
            </button>
            <?php endif; ?>
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

    <?php if (!$tenant_id): ?>
    <!-- ============================================================
    NO TENANT MESSAGE
    ============================================================ -->
    <div class="alert alert-error" style="text-align:center; padding:40px;">
        <i class="fas fa-exclamation-triangle" style="font-size:2rem; display:block; margin-bottom:12px;"></i>
        <h3 style="color:#991b1b; margin-bottom:8px;">No Tenant Available</h3>
        <p style="color:#6d83a5;">Please create a tenant first before managing election templates.</p>
        <a href="tenant-edit.php" class="btn-primary" style="display:inline-flex; margin-top:12px;">
            <i class="fas fa-plus"></i> Create Tenant
        </a>
    </div>
    <?php else: ?>

    <!-- ============================================================
    STATISTICS
    ============================================================ -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon total"><i class="fas fa-file-alt"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo number_format($stats['total'] ?? 0); ?></div>
                <div class="stat-label">Total Templates</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon draft"><i class="fas fa-pencil-alt"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo number_format($stats['draft'] ?? 0); ?></div>
                <div class="stat-label">Draft</div>
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
            <div class="stat-icon upcoming"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo number_format($stats['upcoming'] ?? 0); ?></div>
                <div class="stat-label">Upcoming</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon closed"><i class="fas fa-check-double"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo number_format($stats['closed'] ?? 0); ?></div>
                <div class="stat-label">Closed</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon archived"><i class="fas fa-archive"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo number_format($stats['archived'] ?? 0); ?></div>
                <div class="stat-label">Archived</div>
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
                <input type="text" name="search" placeholder="Search templates..." 
                       value="<?php echo htmlspecialchars($search ?? ''); ?>">
            </div>
            
            <div class="filter-group">
                <i class="fas fa-tag"></i>
                <select name="type" id="filterType">
                    <option value="">All Types</option>
                    <?php foreach ($types as $type): ?>
                    <option value="<?php echo $type; ?>" <?php echo $filter_type === $type ? 'selected' : ''; ?>>
                        <?php echo ucwords(str_replace('_', ' ', $type)); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <i class="fas fa-circle"></i>
                <select name="status" id="filterStatus">
                    <option value="">All Status</option>
                    <?php foreach ($statuses as $status): ?>
                    <option value="<?php echo $status; ?>" <?php echo $filter_status === $status ? 'selected' : ''; ?>>
                        <?php echo ucfirst($status); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn-primary"><i class="fas fa-filter"></i> Filter</button>
                <a href="elections.php" class="btn-secondary"><i class="fas fa-times"></i> Clear</a>
            </div>
        </form>
    </div>

    <!-- ============================================================
    TEMPLATES TABLE
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
                            <option value="archive">Archive</option>
                            <option value="delete">Delete</option>
                        </select>
                        <button type="submit" class="btn-secondary" style="padding:4px 16px; font-size:0.8rem;">
                            Apply
                        </button>
                    </div>
                </form>
            </div>
            <div class="toolbar-right">
                <span><i class="fas fa-database"></i> <?php echo number_format($total_count); ?> templates</span>
                <span><i class="fas fa-arrow-up"></i> <?php echo ucfirst($sort_by ?? 'created_at'); ?> (<?php echo $sort_order ?? 'DESC'; ?>)</span>
            </div>
        </div>

        <table class="data-table" id="templatesTable">
            <thead>
                <tr>
                    <th style="width:36px;">
                        <input type="checkbox" id="selectAllHeader" onchange="toggleAllCheckboxes()">
                    </th>
                    <th style="width:50px;">
                        <a href="?sort=id&order=<?php echo ($sort_order ?? 'DESC') === 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search ?? ''); ?>&type=<?php echo urlencode($filter_type ?? ''); ?>&status=<?php echo urlencode($filter_status ?? ''); ?>&page=<?php echo $page; ?>">
                            ID
                            <?php if ($sort_by === 'id'): ?>
                            <i class="fas fa-sort-<?php echo strtolower($sort_order ?? 'DESC'); ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>Template</th>
                    <th style="width:120px;">Type</th>
                    <th style="width:100px;">Status</th>
                    <th style="width:100px;">
                        <a href="?sort=created_at&order=<?php echo ($sort_order ?? 'DESC') === 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search ?? ''); ?>&type=<?php echo urlencode($filter_type ?? ''); ?>&status=<?php echo urlencode($filter_status ?? ''); ?>&page=<?php echo $page; ?>">
                            Created
                            <?php if ($sort_by === 'created_at'): ?>
                            <i class="fas fa-sort-<?php echo strtolower($sort_order ?? 'DESC'); ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th style="width:240px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($templates)): ?>
                <tr>
                    <td colspan="7" class="empty-table">
                        <i class="fas fa-file-alt"></i>
                        <h3>No templates found</h3>
                        <p>Create your first election template to get started.</p>
                        <button class="btn-primary" onclick="openCreateModal()" style="display:inline-flex;">
                            <i class="fas fa-plus"></i> Create Template
                        </button>
                    </td>
                </tr>
                <?php endif; ?>
                
                <?php foreach ($templates as $template): ?>
                <tr>
                    <td>
                        <input type="checkbox" name="template_ids[]" value="<?php echo $template['id']; ?>" class="template-checkbox">
                    </td>
                    <td><span style="font-weight:600; color:#4f9cf7;">#<?php echo $template['id']; ?></span></td>
                    <td>
                        <div class="template-cell">
                            <div class="template-name">
                                <?php echo htmlspecialchars($template['name'] ?? ''); ?>
                            </div>
                            <div class="template-meta">
                                <span>Cycle: <?php echo htmlspecialchars($template['cycle'] ?? ''); ?></span>
                                <span>Date: <?php echo !empty($template['election_date']) ? date('M d, Y', strtotime($template['election_date'])) : 'N/A'; ?></span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="type-badge <?php echo $template['type'] ?? ''; ?>">
                            <?php echo ucwords(str_replace('_', ' ', $template['type'] ?? '')); ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge <?php echo $template['status'] ?? 'draft'; ?>">
                            <?php if ($template['status'] === 'active'): ?>
                            <i class="fas fa-check-circle"></i>
                            <?php elseif ($template['status'] === 'draft'): ?>
                            <i class="fas fa-pencil-alt"></i>
                            <?php elseif ($template['status'] === 'upcoming'): ?>
                            <i class="fas fa-clock"></i>
                            <?php elseif ($template['status'] === 'closed'): ?>
                            <i class="fas fa-check-double"></i>
                            <?php elseif ($template['status'] === 'archived'): ?>
                            <i class="fas fa-archive"></i>
                            <?php else: ?>
                            <i class="fas fa-times-circle"></i>
                            <?php endif; ?>
                            <?php echo ucfirst($template['status'] ?? 'draft'); ?>
                        </span>
                    </td>
                    <td><?php echo !empty($template['created_at']) ? date('M d, Y', strtotime($template['created_at'])) : 'N/A'; ?></td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-icon view" title="View Template" onclick="viewTemplate(<?php echo $template['id']; ?>)">
                                <i class="fas fa-eye"></i>
                                <span class="tooltip">View</span>
                            </button>
                            <a href="elections.php?action=edit&id=<?php echo $template['id']; ?>" class="btn-icon edit" title="Edit">
                                <i class="fas fa-edit"></i>
                                <span class="tooltip">Edit</span>
                            </a>
                            <button class="btn-icon duplicate" title="Duplicate" onclick="duplicateTemplate(<?php echo $template['id']; ?>, '<?php echo htmlspecialchars($template['name'] ?? ''); ?>')">
                                <i class="fas fa-copy"></i>
                                <span class="tooltip">Duplicate</span>
                            </button>
                            <button class="btn-icon assign" title="Assign" onclick="openAssignModal(<?php echo $template['id']; ?>, '<?php echo htmlspecialchars($template['name'] ?? ''); ?>')">
                                <i class="fas fa-users"></i>
                                <span class="tooltip">Assign</span>
                            </button>
                            <?php if ($template['status'] !== 'archived'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
                                <input type="hidden" name="action" value="archive_template">
                                <button type="submit" class="btn-icon archive" title="Archive" onclick="return confirm('Archive template \'<?php echo htmlspecialchars($template['name'] ?? ''); ?>\'?');">
                                    <i class="fas fa-archive"></i>
                                    <span class="tooltip">Archive</span>
                                </button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirmDelete('<?php echo htmlspecialchars($template['name'] ?? ''); ?>');">
                                <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
                                <input type="hidden" name="action" value="delete_template">
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
                of <strong><?php echo number_format($total_count); ?></strong> templates
            </div>
            <div class="pagination-links">
                <?php if ($page > 1): ?>
                <a href="?page=1&sort=<?php echo urlencode($sort_by ?? 'created_at'); ?>&order=<?php echo urlencode($sort_order ?? 'DESC'); ?>&search=<?php echo urlencode($search ?? ''); ?>&type=<?php echo urlencode($filter_type ?? ''); ?>&status=<?php echo urlencode($filter_status ?? ''); ?>">
                    <i class="fas fa-angle-double-left"></i>
                </a>
                <a href="?page=<?php echo $page - 1; ?>&sort=<?php echo urlencode($sort_by ?? 'created_at'); ?>&order=<?php echo urlencode($sort_order ?? 'DESC'); ?>&search=<?php echo urlencode($search ?? ''); ?>&type=<?php echo urlencode($filter_type ?? ''); ?>&status=<?php echo urlencode($filter_status ?? ''); ?>">
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
                    echo '<a href="?page=' . $i . '&sort=' . urlencode($sort_by ?? 'created_at') . '&order=' . urlencode($sort_order ?? 'DESC') . '&search=' . urlencode($search ?? '') . '&type=' . urlencode($filter_type ?? '') . '&status=' . urlencode($filter_status ?? '') . '" class="' . $active . '">' . $i . '</a>';
                }
                
                if ($end_page < $total_pages) {
                    echo '<span class="ellipsis">…</span>';
                }
                ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&sort=<?php echo urlencode($sort_by ?? 'created_at'); ?>&order=<?php echo urlencode($sort_order ?? 'DESC'); ?>&search=<?php echo urlencode($search ?? ''); ?>&type=<?php echo urlencode($filter_type ?? ''); ?>&status=<?php echo urlencode($filter_status ?? ''); ?>">
                    <i class="fas fa-angle-right"></i>
                </a>
                <a href="?page=<?php echo $total_pages; ?>&sort=<?php echo urlencode($sort_by ?? 'created_at'); ?>&order=<?php echo urlencode($sort_order ?? 'DESC'); ?>&search=<?php echo urlencode($search ?? ''); ?>&type=<?php echo urlencode($filter_type ?? ''); ?>&status=<?php echo urlencode($filter_status ?? ''); ?>">
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
    <div class="modal <?php echo $edit_template ? 'active' : ''; ?>" id="templateModal">
        <div class="modal-overlay" onclick="closeTemplateModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle"><i class="fas fa-<?php echo $edit_template ? 'edit' : 'plus-circle'; ?>"></i> <?php echo $edit_template ? 'Edit Template' : 'Create New Template'; ?></h2>
                <button class="modal-close" onclick="closeTemplateModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="templateForm">
                    <input type="hidden" name="action" value="<?php echo $edit_template ? 'edit_template' : 'create_template'; ?>">
                    <?php if ($edit_template): ?>
                    <input type="hidden" name="template_id" value="<?php echo $edit_template['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Template Name <span class="required">*</span></label>
                        <input type="text" name="name" class="form-control" 
                               placeholder="e.g., 2023 General Election Template" 
                               value="<?php echo htmlspecialchars($edit_template['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Election Type <span class="required">*</span></label>
                            <select name="type" class="form-control" id="electionType" required>
                                <option value="">Select Type</option>
                                <?php foreach ($types as $type): ?>
                                <option value="<?php echo $type; ?>" <?php echo ($edit_template && ($edit_template['type'] ?? '') === $type) ? 'selected' : ''; ?>>
                                    <?php echo ucwords(str_replace('_', ' ', $type)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Cycle <span class="required">*</span></label>
                            <input type="text" name="cycle" class="form-control" 
                                   placeholder="e.g., 2023" 
                                   value="<?php echo htmlspecialchars($edit_template['cycle'] ?? ''); ?>" required>
                            <div class="form-hint">Election cycle year or identifier</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Election Date <span class="required">*</span></label>
                            <input type="date" name="election_date" class="form-control" 
                                   value="<?php echo htmlspecialchars($edit_template['election_date'] ?? date('Y-m-d')); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="draft" <?php echo ($edit_template && ($edit_template['status'] ?? '') === 'draft') ? 'selected' : ''; ?>>Draft</option>
                                <option value="upcoming" <?php echo ($edit_template && ($edit_template['status'] ?? '') === 'upcoming') ? 'selected' : ''; ?>>Upcoming</option>
                                <option value="active" <?php echo ($edit_template && ($edit_template['status'] ?? '') === 'active') ? 'selected' : ''; ?>>Active</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Start Time</label>
                            <input type="time" name="start_time" class="form-control" 
                                   value="<?php echo htmlspecialchars($edit_template['start_time'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>End Time</label>
                            <input type="time" name="end_time" class="form-control" 
                                   value="<?php echo htmlspecialchars($edit_template['end_time'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="3" 
                                  placeholder="Brief description of this template..."><?php echo htmlspecialchars($edit_template['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <hr style="border-color:#eef3f8; margin:20px 0;">
                    
                    <h4 style="font-weight:600; color:#0b1a33; margin-bottom:12px;">
                        <i class="fas fa-cog" style="color:#4f9cf7;"></i> Template Settings
                    </h4>
                    <p style="font-size:0.85rem; color:#6d83a5; margin-bottom:16px;">
                        Configure the settings for this election template.
                    </p>
                    
                    <?php 
                    $settings = [];
                    if ($edit_template && !empty($edit_template['settings_json'])) {
                        $settings = json_decode($edit_template['settings_json'], true);
                    }
                    ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label style="font-weight:400; cursor:pointer;">
                                <input type="checkbox" name="settings[requires_approval]" value="1" 
                                       <?php echo (isset($settings['requires_approval']) && $settings['requires_approval']) ? 'checked' : ''; ?>>
                                Requires Approval
                            </label>
                            <div class="form-hint">Enable approval workflow for this election</div>
                        </div>
                        <div class="form-group">
                            <label style="font-weight:400; cursor:pointer;">
                                <input type="checkbox" name="settings[allow_public_view]" value="1" 
                                       <?php echo (isset($settings['allow_public_view']) && $settings['allow_public_view']) ? 'checked' : ''; ?>>
                                Allow Public View
                            </label>
                            <div class="form-hint">Make results publicly viewable</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label style="font-weight:400; cursor:pointer;">
                                <input type="checkbox" name="settings[enable_checkin]" value="1" 
                                       <?php echo (isset($settings['enable_checkin']) && $settings['enable_checkin']) ? 'checked' : ''; ?>>
                                Enable Agent Check-in
                            </label>
                            <div class="form-hint">Require agents to check in at polling units</div>
                        </div>
                        <div class="form-group">
                            <label style="font-weight:400; cursor:pointer;">
                                <input type="checkbox" name="settings[enable_incident_report]" value="1" 
                                       <?php echo (isset($settings['enable_incident_report']) && $settings['enable_incident_report']) ? 'checked' : ''; ?>>
                                Enable Incident Reporting
                            </label>
                            <div class="form-hint">Allow incident reporting during the election</div>
                        </div>
                    </div>
                    
                    <div style="display:flex; gap:12px; margin-top:20px; justify-content:flex-end;">
                        <button type="button" class="btn-secondary" onclick="closeTemplateModal()">Cancel</button>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> 
                            <?php echo $edit_template ? 'Update Template' : 'Create Template'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ============================================================
    ASSIGN MODAL
    ============================================================ -->
    <div class="modal" id="assignModal">
        <div class="modal-overlay" onclick="closeAssignModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-users"></i> Assign Template</h2>
                <button class="modal-close" onclick="closeAssignModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="assignForm">
                    <input type="hidden" name="action" value="assign_template">
                    <input type="hidden" name="template_id" id="assignTemplateId">
                    
                    <div class="form-group">
                        <label>Template</label>
                        <input type="text" class="form-control" id="assignTemplateName" disabled style="background:#f8faff;">
                    </div>
                    
                    <div class="form-group">
                        <label>Assign To <span class="required">*</span></label>
                        <div style="display:flex; flex-direction:column; gap:8px; margin-top:4px;">
                            <label style="font-weight:400; font-size:0.9rem; cursor:pointer;">
                                <input type="checkbox" name="assign_to[]" value="national"> National Level
                            </label>
                            <label style="font-weight:400; font-size:0.9rem; cursor:pointer;">
                                <input type="checkbox" name="assign_to[]" value="state"> State Level
                            </label>
                            <label style="font-weight:400; font-size:0.9rem; cursor:pointer;">
                                <input type="checkbox" name="assign_to[]" value="lga"> LGA Level
                            </label>
                            <label style="font-weight:400; font-size:0.9rem; cursor:pointer;">
                                <input type="checkbox" name="assign_to[]" value="ward"> Ward Level
                            </label>
                            <label style="font-weight:400; font-size:0.9rem; cursor:pointer;">
                                <input type="checkbox" name="assign_to[]" value="pu"> Polling Unit Level
                            </label>
                        </div>
                        <div class="form-hint">Select which levels this template should be available for</div>
                    </div>
                    
                    <div style="display:flex; gap:12px; margin-top:20px; justify-content:flex-end;">
                        <button type="button" class="btn-secondary" onclick="closeAssignModal()">Cancel</button>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-check"></i> Assign Template
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</main>

<!-- ============================================================
JAVASCRIPT
============================================================ -->
<script>
// ============================================================
// MODAL CONTROLS
// ============================================================
function openCreateModal() {
    const modal = document.getElementById('templateModal');
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Create New Template';
    document.getElementById('templateForm').reset();
    document.querySelector('input[name="action"]').value = 'create_template';
    const idInput = document.querySelector('input[name="template_id"]');
    if (idInput) idInput.remove();
    // Set default date
    const dateInput = document.querySelector('input[name="election_date"]');
    if (dateInput) {
        const today = new Date();
        dateInput.value = today.toISOString().split('T')[0];
    }
    modal.classList.add('active');
}

function closeTemplateModal() {
    document.getElementById('templateModal').classList.remove('active');
}

function openAssignModal(templateId, templateName) {
    const modal = document.getElementById('assignModal');
    document.getElementById('assignTemplateId').value = templateId;
    document.getElementById('assignTemplateName').value = templateName;
    modal.classList.add('active');
}

function closeAssignModal() {
    document.getElementById('assignModal').classList.remove('active');
}

// Close modals on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeTemplateModal();
        closeAssignModal();
    }
});

// Close modals on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function() {
        closeTemplateModal();
        closeAssignModal();
    });
});

// ============================================================
// VIEW TEMPLATE
// ============================================================
function viewTemplate(templateId) {
    alert('View template details - ID: ' + templateId + '\n\nFull details would be shown in a modal.');
}

// ============================================================
// DUPLICATE TEMPLATE
// ============================================================
function duplicateTemplate(templateId, templateName) {
    if (confirm(`Duplicate template "${templateName}"?\n\nA new template will be created with the same settings.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="template_id" value="${templateId}">
            <input type="hidden" name="action" value="duplicate_template">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// ============================================================
// CONFIRM DELETE
// ============================================================
function confirmDelete(templateName) {
    return confirm(`⚠️ Are you sure you want to delete template "${templateName}"?\n\nThis action cannot be undone.`);
}

// ============================================================
// BULK ACTIONS
// ============================================================
function toggleAllCheckboxes() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.template-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
}

function confirmBulkAction() {
    const bulkAction = document.getElementById('bulkAction');
    const selected = document.querySelectorAll('.template-checkbox:checked');
    
    if (!bulkAction.value) {
        alert('Please select an action to perform.');
        return false;
    }
    
    if (selected.length === 0) {
        alert('Please select at least one template.');
        return false;
    }
    
    const actionNames = {
        'activate': 'activate',
        'archive': 'archive',
        'delete': 'permanently delete'
    };
    
    return confirm(`Are you sure you want to ${actionNames[bulkAction.value] || bulkAction.value} ${selected.length} selected template(s)?`);
}

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

// ============================================================
// ENSURE EDIT MODAL OPENS ON PAGE LOAD
// ============================================================
<?php if ($edit_template): ?>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('templateModal');
    if (modal) {
        modal.classList.add('active');
    }
});
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>