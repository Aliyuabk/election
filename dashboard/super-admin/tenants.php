<?php

$page_title = "Manage Tenants";
require_once 'includes/db.php';
$db = Database::getInstance()->getConnection();

// ============================================================
// HANDLE ACTIONS
// ============================================================
$action = $_GET['action'] ?? '';
$tenant_id = $_GET['id'] ?? 0;
$message = '';
$error = '';
$message_type = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_action = $_POST['action'] ?? '';
    $tenant_id = $_POST['tenant_id'] ?? 0;
    
    try {
        switch ($post_action) {
            case 'suspend':
                $db->prepare("UPDATE tenants SET subscription_status = 'suspended', updated_at = NOW() WHERE id = ? AND deleted_at IS NULL")
                   ->execute([$tenant_id]);
                $message = "Tenant suspended successfully.";
                $message_type = 'success';
                break;
                
            case 'activate':
                $db->prepare("UPDATE tenants SET subscription_status = 'active', updated_at = NOW() WHERE id = ? AND deleted_at IS NULL")
                   ->execute([$tenant_id]);
                $message = "Tenant activated successfully.";
                $message_type = 'success';
                break;
                
            case 'delete':
                $db->prepare("UPDATE tenants SET deleted_at = NOW(), updated_at = NOW() WHERE id = ?")
                   ->execute([$tenant_id]);
                $message = "Tenant deleted successfully.";
                $message_type = 'success';
                break;
                
            case 'reset_password':
                $new_password = bin2hex(random_bytes(8));
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update user password
                $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE tenant_id = ? AND role_id = (SELECT id FROM roles WHERE slug = 'client_admin' LIMIT 1)");
                $stmt->execute([$password_hash, $tenant_id]);
                
                // Log the reset
                $stmt = $db->prepare("INSERT INTO activity_logs (user_id, tenant_id, activity_type, description, ip_address, created_at) 
                             VALUES (?, ?, 'password_reset', 'Admin password reset for tenant', ?, NOW())");
                $stmt->execute([$_SESSION['user_id'] ?? 1, $tenant_id, $_SERVER['REMOTE_ADDR']]);
                
                $message = "Password reset successfully. New password: <strong>{$new_password}</strong>";
                $message_type = 'info';
                break;
                
            case 'bulk_action':
                $bulk_action = $_POST['bulk_action'] ?? '';
                $tenant_ids = $_POST['tenant_ids'] ?? [];
                
                if (!empty($tenant_ids) && is_array($tenant_ids)) {
                    $placeholders = implode(',', array_fill(0, count($tenant_ids), '?'));
                    
                    switch ($bulk_action) {
                        case 'activate':
                            $db->prepare("UPDATE tenants SET subscription_status = 'active', updated_at = NOW() WHERE id IN ($placeholders) AND deleted_at IS NULL")
                               ->execute($tenant_ids);
                            $message = count($tenant_ids) . " tenants activated successfully.";
                            $message_type = 'success';
                            break;
                        case 'suspend':
                            $db->prepare("UPDATE tenants SET subscription_status = 'suspended', updated_at = NOW() WHERE id IN ($placeholders) AND deleted_at IS NULL")
                               ->execute($tenant_ids);
                            $message = count($tenant_ids) . " tenants suspended successfully.";
                            $message_type = 'success';
                            break;
                        case 'delete':
                            $db->prepare("UPDATE tenants SET deleted_at = NOW(), updated_at = NOW() WHERE id IN ($placeholders)")
                               ->execute($tenant_ids);
                            $message = count($tenant_ids) . " tenants deleted successfully.";
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
// GET TENANT DATA
// ============================================================
$search = $_GET['search'] ?? '';
$filter_plan = $_GET['plan'] ?? '';
$filter_status = $_GET['status'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_order = $_GET['order'] ?? 'DESC';
$per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Build base query
$base_query = "FROM tenants t
               LEFT JOIN users u ON u.tenant_id = t.id AND u.deleted_at IS NULL
               LEFT JOIN elections e ON e.tenant_id = t.id AND e.deleted_at IS NULL
               LEFT JOIN subscriptions s ON s.tenant_id = t.id
               WHERE t.deleted_at IS NULL";

$params = [];

if ($search) {
    $base_query .= " AND (t.name LIKE ? OR t.slug LIKE ? OR t.contact_email LIKE ? OR t.contact_phone LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($filter_plan) {
    $base_query .= " AND t.subscription_plan = ?";
    $params[] = $filter_plan;
}

if ($filter_status) {
    $base_query .= " AND t.subscription_status = ?";
    $params[] = $filter_status;
}

// Get total count
$count_query = "SELECT COUNT(DISTINCT t.id) as total " . $base_query;
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_count = $count_stmt->fetch()['total'];
$total_pages = ceil($total_count / $per_page);

// Get paginated results
$query = "SELECT 
            t.*,
            COUNT(DISTINCT u.id) as user_count,
            COUNT(DISTINCT e.id) as election_count,
            COUNT(DISTINCT s.id) as subscription_count,
            (SELECT COUNT(*) FROM invoices WHERE tenant_id = t.id AND status = 'paid') as invoice_count
          " . $base_query . "
          GROUP BY t.id 
          ORDER BY t.$sort_by $sort_order 
          LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;

$tenants = $db->prepare($query);
$tenants->execute($params);
$tenants = $tenants->fetchAll();

// Get filter options
$plans = ['free', 'basic', 'standard', 'premium', 'enterprise'];
$statuses = ['trial', 'active', 'suspended', 'expired', 'cancelled'];

// Get tenant stats
$stats_query = "SELECT 
                   COUNT(*) as total,
                   SUM(CASE WHEN subscription_status = 'active' THEN 1 ELSE 0 END) as active,
                   SUM(CASE WHEN subscription_status = 'suspended' THEN 1 ELSE 0 END) as suspended,
                   SUM(CASE WHEN subscription_status = 'trial' THEN 1 ELSE 0 END) as trial,
                   SUM(CASE WHEN subscription_status = 'expired' THEN 1 ELSE 0 END) as expired
                 FROM tenants WHERE deleted_at IS NULL";
$stats = $db->query($stats_query)->fetch();

include 'includes/base.php';
?>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/header.php'; ?>

<style>
/* ============================================================
   PROFESSIONAL TENANT MANAGEMENT STYLES
   ============================================================ */

/* Page Header */
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

/* Stats Cards */
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
.stat-card .stat-icon.suspended { background: #fef3c7; color: #f59e0b; }
.stat-card .stat-icon.trial { background: #ede9fe; color: #8b5cf6; }
.stat-card .stat-icon.expired { background: #fee2e2; color: #ef4444; }

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

.stat-card .stat-trend {
    font-size: 0.65rem;
    padding: 2px 10px;
    border-radius: 30px;
    font-weight: 500;
    margin-left: 8px;
}

.stat-card .stat-trend.up { background: #d1fae5; color: #065f46; }
.stat-card .stat-trend.down { background: #fee2e2; color: #991b1b; }

/* Filter Bar */
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

/* Data Table */
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

.data-table thead th .th-content {
    display: flex;
    align-items: center;
    gap: 6px;
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

.data-table tbody td .checkbox-wrapper {
    display: flex;
    align-items: center;
}

.data-table tbody td .checkbox-wrapper input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: #4f9cf7;
    cursor: pointer;
}

/* Tenant Cell */
.tenant-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.tenant-cell .tenant-avatar {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    object-fit: cover;
    border: 2px solid #eef3f8;
    flex-shrink: 0;
}

.tenant-cell .tenant-avatar-placeholder {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, #4f9cf7, #3b82d6);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.tenant-cell .tenant-info {
    min-width: 0;
}

.tenant-cell .tenant-name {
    font-weight: 500;
    color: #0b1a33;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.tenant-cell .tenant-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.7rem;
    color: #8b9bb5;
}

.tenant-cell .tenant-meta .tenant-slug {
    background: #f0f4fa;
    padding: 0 8px;
    border-radius: 30px;
}

/* Badges */
.plan-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.7rem;
    padding: 4px 12px;
    border-radius: 30px;
    font-weight: 500;
    text-transform: capitalize;
}

.plan-badge.free { background: #f3f4f6; color: #4b5563; }
.plan-badge.basic { background: #dbeafe; color: #1e40af; }
.plan-badge.standard { background: #d1fae5; color: #065f46; }
.plan-badge.premium { background: #fef3c7; color: #92400e; }
.plan-badge.enterprise { background: #ede9fe; color: #5b21b6; }

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
.status-badge.suspended { background: #fef3c7; color: #92400e; }
.status-badge.trial { background: #dbeafe; color: #1e40af; }
.status-badge.expired { background: #fee2e2; color: #991b1b; }
.status-badge.cancelled { background: #f3f4f6; color: #4b5563; }

/* Action Buttons */
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
.action-buttons .btn-icon.activate { color: #10b981; }
.action-buttons .btn-icon.activate:hover { background: #d1fae5; }
.action-buttons .btn-icon.suspend { color: #f59e0b; }
.action-buttons .btn-icon.suspend:hover { background: #fef3c7; }
.action-buttons .btn-icon.reset { color: #8b5cf6; }
.action-buttons .btn-icon.reset:hover { background: #ede9fe; }
.action-buttons .btn-icon.impersonate { color: #4f9cf7; }
.action-buttons .btn-icon.impersonate:hover { background: #dbeafe; }
.action-buttons .btn-icon.delete { color: #ef4444; }
.action-buttons .btn-icon.delete:hover { background: #fee2e2; }

/* Pagination */
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

/* Modal */
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
    max-width: 720px;
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

/* Alerts */
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

/* Empty State */
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

/* Animations */
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

/* Responsive */
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
                <i class="fas fa-building" style="color:#4f9cf7;"></i>
                Manage Tenants
                <span class="page-badge">Super Admin</span>
            </h1>
            <p class="subtitle">Manage all client organizations using the platform</p>
        </div>
        <!-- In the header actions section -->
        <div class="header-actions">
            <div class="btn-group">
                <button class="btn-secondary dropdown-toggle" onclick="toggleExportDropdown()">
                    <i class="fas fa-file-export"></i> Export <i class="fas fa-chevron-down"></i>
                </button>
                <div class="dropdown-menu" id="exportDropdown">
                    <a href="#" onclick="exportData('csv')"><i class="fas fa-file-csv"></i> CSV</a>
                    <a href="#" onclick="exportData('excel')"><i class="fas fa-file-excel"></i> Excel (XLSX)</a>
                    <a href="#" onclick="exportData('pdf')"><i class="fas fa-file-pdf"></i> PDF</a>
                </div>
            </div>
            <a href="tenant-edit.php" class="btn-primary">
                <i class="fas fa-plus"></i> Create Tenant
            </a>
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
            <div class="stat-icon total"><i class="fas fa-building"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo number_format($stats['total'] ?? 0); ?></div>
                <div class="stat-label">Total Tenants</div>
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
            <div class="stat-icon suspended"><i class="fas fa-pause-circle"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo number_format($stats['suspended'] ?? 0); ?></div>
                <div class="stat-label">Suspended</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon trial"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo number_format($stats['trial'] ?? 0); ?></div>
                <div class="stat-label">Trial</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon expired"><i class="fas fa-times-circle"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo number_format($stats['expired'] ?? 0); ?></div>
                <div class="stat-label">Expired</div>
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
                <input type="text" name="search" placeholder="Search tenants by name, slug, email..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="filter-group">
                <i class="fas fa-crown"></i>
                <select name="plan">
                    <option value="">All Plans</option>
                    <?php foreach ($plans as $plan): ?>
                    <option value="<?php echo $plan; ?>" <?php echo $filter_plan === $plan ? 'selected' : ''; ?>>
                        <?php echo ucfirst($plan); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <i class="fas fa-circle"></i>
                <select name="status">
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
                <a href="tenants.php" class="btn-secondary"><i class="fas fa-times"></i> Clear</a>
            </div>
        </form>
    </div>

    <!-- ============================================================
    TENANT TABLE
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

        <table class="data-table" id="tenantTable">
            <thead>
                <tr>
                    <th style="width:36px;">
                        <input type="checkbox" id="selectAllHeader" onchange="toggleAllCheckboxes()">
                    </th>
                    <th style="width:50px;">
                        <a href="?sort=id&order=<?php echo $sort_order === 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>&plan=<?php echo urlencode($filter_plan); ?>&status=<?php echo urlencode($filter_status); ?>&page=<?php echo $page; ?>">
                            ID
                            <?php if ($sort_by === 'id'): ?>
                            <i class="fas fa-sort-<?php echo strtolower($sort_order); ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>Organization</th>
                    <th style="width:100px;">Plan</th>
                    <th style="width:110px;">Status</th>
                    <th style="width:70px;">Users</th>
                    <th style="width:100px;">
                        <a href="?sort=created_at&order=<?php echo $sort_order === 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>&plan=<?php echo urlencode($filter_plan); ?>&status=<?php echo urlencode($filter_status); ?>&page=<?php echo $page; ?>">
                            Registered
                            <?php if ($sort_by === 'created_at'): ?>
                            <i class="fas fa-sort-<?php echo strtolower($sort_order); ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th style="width:100px;">
                        <a href="?sort=subscription_end&order=<?php echo $sort_order === 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>&plan=<?php echo urlencode($filter_plan); ?>&status=<?php echo urlencode($filter_status); ?>&page=<?php echo $page; ?>">
                            Expiry
                            <?php if ($sort_by === 'subscription_end'): ?>
                            <i class="fas fa-sort-<?php echo strtolower($sort_order); ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th style="width:200px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tenants)): ?>
                <tr>
                    <td colspan="9" class="empty-table">
                        <i class="fas fa-building"></i>
                        <h3>No tenants found</h3>
                        <p>Start by creating your first tenant organization.</p>
                        <a href="tenant-edit.php" class="btn-primary" style="display:inline-flex;">
                            <i class="fas fa-plus"></i> Create Tenant
                        </a>
                    </td>
                </tr>
                <?php endif; ?>
                
                <?php foreach ($tenants as $tenant): ?>
                <tr>
                    <td>
                        <div class="checkbox-wrapper">
                            <input type="checkbox" name="tenant_ids[]" value="<?php echo $tenant['id']; ?>" class="tenant-checkbox">
                        </div>
                    </td>
                    <td><span class="tenant-id" style="font-weight:600; color:#4f9cf7;">#<?php echo $tenant['id']; ?></span></td>
                    <td>
                        <div class="tenant-cell">
                            <?php if ($tenant['logo_url']): ?>
                            <img src="<?php echo htmlspecialchars($tenant['logo_url']); ?>" alt="" class="tenant-avatar">
                            <?php else: ?>
                            <div class="tenant-avatar-placeholder">
                                <?php echo strtoupper(substr($tenant['name'], 0, 2)); ?>
                            </div>
                            <?php endif; ?>
                            <div class="tenant-info">
                                <div class="tenant-name"><?php echo htmlspecialchars($tenant['name']); ?></div>
                                <div class="tenant-meta">
                                    <span class="tenant-slug"><?php echo htmlspecialchars($tenant['slug']); ?></span>
                                    <?php if ($tenant['contact_email']): ?>
                                    <span>· <?php echo htmlspecialchars($tenant['contact_email']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="plan-badge <?php echo $tenant['subscription_plan']; ?>">
                            <i class="fas fa-crown"></i>
                            <?php echo ucfirst($tenant['subscription_plan']); ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge <?php echo $tenant['subscription_status']; ?>">
                            <?php if ($tenant['subscription_status'] === 'active'): ?>
                            <i class="fas fa-check-circle"></i>
                            <?php elseif ($tenant['subscription_status'] === 'suspended'): ?>
                            <i class="fas fa-pause-circle"></i>
                            <?php elseif ($tenant['subscription_status'] === 'trial'): ?>
                            <i class="fas fa-clock"></i>
                            <?php elseif ($tenant['subscription_status'] === 'expired'): ?>
                            <i class="fas fa-times-circle"></i>
                            <?php else: ?>
                            <i class="fas fa-minus-circle"></i>
                            <?php endif; ?>
                            <?php echo ucfirst($tenant['subscription_status']); ?>
                        </span>
                    </td>
                    <td>
                        <span style="font-weight:500; color:#0b1a33;"><?php echo $tenant['user_count']; ?></span>
                        <span style="font-size:0.65rem; color:#8b9bb5;">users</span>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($tenant['created_at'])); ?></td>
                    <td>
                        <?php if ($tenant['subscription_end']): ?>
                        <span class="expiry-date <?php echo strtotime($tenant['subscription_end']) < time() ? 'expired' : ''; ?>" style="font-size:0.85rem;">
                            <?php echo date('M d, Y', strtotime($tenant['subscription_end'])); ?>
                            <?php if (strtotime($tenant['subscription_end']) < time()): ?>
                            <span class="expiry-warning" style="display:block; font-size:0.6rem; color:#ef4444;">(Expired)</span>
                            <?php endif; ?>
                        </span>
                        <?php else: ?>
                        <span style="color:#8b9bb5; font-size:0.8rem;">N/A</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-icon view" title="View Details" onclick="viewTenant(<?php echo $tenant['id']; ?>)">
                                <i class="fas fa-eye"></i>
                                <span class="tooltip">View Details</span>
                            </button>
                            <a href="tenant-edit.php?id=<?php echo $tenant['id']; ?>" class="btn-icon edit" title="Edit">
                                <i class="fas fa-edit"></i>
                                <span class="tooltip">Edit</span>
                            </a>
                            
                            <?php if ($tenant['subscription_status'] === 'suspended'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="tenant_id" value="<?php echo $tenant['id']; ?>">
                                <input type="hidden" name="action" value="activate">
                                <button type="submit" class="btn-icon activate" title="Activate">
                                    <i class="fas fa-check-circle"></i>
                                    <span class="tooltip">Activate</span>
                                </button>
                            </form>
                            <?php else: ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="tenant_id" value="<?php echo $tenant['id']; ?>">
                                <input type="hidden" name="action" value="suspend">
                                <button type="submit" class="btn-icon suspend" title="Suspend">
                                    <i class="fas fa-pause-circle"></i>
                                    <span class="tooltip">Suspend</span>
                                </button>
                            </form>
                            <?php endif; ?>
                            
                            <button class="btn-icon reset" title="Reset Password" onclick="resetPassword(<?php echo $tenant['id']; ?>, '<?php echo htmlspecialchars($tenant['name']); ?>')">
                                <i class="fas fa-key"></i>
                                <span class="tooltip">Reset Password</span>
                            </button>
                            
                            <button class="btn-icon impersonate" title="Login as Tenant" onclick="impersonateTenant(<?php echo $tenant['id']; ?>)">
                                <i class="fas fa-user-secret"></i>
                                <span class="tooltip">Impersonate</span>
                            </button>
                            
                            <form method="POST" style="display:inline;" onsubmit="return confirmDelete('<?php echo htmlspecialchars($tenant['name']); ?>');">
                                <input type="hidden" name="tenant_id" value="<?php echo $tenant['id']; ?>">
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
                of <strong><?php echo number_format($total_count); ?></strong> tenants
            </div>
            <div class="pagination-links">
                <?php if ($page > 1): ?>
                <a href="?page=1&sort=<?php echo urlencode($sort_by); ?>&order=<?php echo urlencode($sort_order); ?>&search=<?php echo urlencode($search); ?>&plan=<?php echo urlencode($filter_plan); ?>&status=<?php echo urlencode($filter_status); ?>">
                    <i class="fas fa-angle-double-left"></i>
                </a>
                <a href="?page=<?php echo $page - 1; ?>&sort=<?php echo urlencode($sort_by); ?>&order=<?php echo urlencode($sort_order); ?>&search=<?php echo urlencode($search); ?>&plan=<?php echo urlencode($filter_plan); ?>&status=<?php echo urlencode($filter_status); ?>">
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
                    echo '<a href="?page=' . $i . '&sort=' . urlencode($sort_by) . '&order=' . urlencode($sort_order) . '&search=' . urlencode($search) . '&plan=' . urlencode($filter_plan) . '&status=' . urlencode($filter_status) . '" class="' . $active . '">' . $i . '</a>';
                }
                
                if ($end_page < $total_pages) {
                    echo '<span class="ellipsis">…</span>';
                }
                ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&sort=<?php echo urlencode($sort_by); ?>&order=<?php echo urlencode($sort_order); ?>&search=<?php echo urlencode($search); ?>&plan=<?php echo urlencode($filter_plan); ?>&status=<?php echo urlencode($filter_status); ?>">
                    <i class="fas fa-angle-right"></i>
                </a>
                <a href="?page=<?php echo $total_pages; ?>&sort=<?php echo urlencode($sort_by); ?>&order=<?php echo urlencode($sort_order); ?>&search=<?php echo urlencode($search); ?>&plan=<?php echo urlencode($filter_plan); ?>&status=<?php echo urlencode($filter_status); ?>">
                    <i class="fas fa-angle-double-right"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ============================================================
    TENANT DETAIL MODAL
    ============================================================ -->
    <div class="modal" id="tenantModal">
        <div class="modal-overlay" onclick="closeModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-building"></i> Tenant Details</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="tenantModalBody">
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
// VIEW TENANT DETAILS
// ============================================================
function viewTenant(tenantId) {
    const modal = document.getElementById('tenantModal');
    const body = document.getElementById('tenantModalBody');
    
    modal.classList.add('active');
    body.innerHTML = '<div class="loading-spinner" style="text-align:center; padding:40px; color:#6d83a5;"><i class="fas fa-spinner fa-spin" style="font-size:2rem; display:block; margin-bottom:12px;"></i> Loading tenant details...</div>';
    
    fetch(`tenant-details.php?id=${tenantId}`)
        .then(response => response.text())
        .then(html => {
            body.innerHTML = html;
        })
        .catch(error => {
            body.innerHTML = '<div style="text-align:center; padding:40px; color:#ef4444;"><i class="fas fa-exclamation-circle" style="font-size:2rem; display:block; margin-bottom:12px;"></i> Failed to load tenant details</div>';
        });
}

function closeModal() {
    document.getElementById('tenantModal').classList.remove('active');
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});

// Close modal on overlay click
document.querySelector('.modal-overlay')?.addEventListener('click', closeModal);

// ============================================================
// RESET PASSWORD
// ============================================================
function resetPassword(tenantId, tenantName) {
    if (confirm(`Are you sure you want to reset the admin password for "${tenantName}"?\n\nA new random password will be generated and displayed.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="tenant_id" value="${tenantId}">
            <input type="hidden" name="action" value="reset_password">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// ============================================================
// IMPERSONATE TENANT
// ============================================================
function impersonateTenant(tenantId) {
    if (confirm('You are about to login as this tenant admin. This action will be logged for audit purposes.')) {
        window.location.href = `impersonate.php?tenant_id=${tenantId}`;
    }
}

// ============================================================
// CONFIRM DELETE
// ============================================================
function confirmDelete(tenantName) {
    return confirm(`⚠️ Are you sure you want to permanently delete "${tenantName}"?\n\nThis action cannot be undone and will remove all associated data.`);
}

// ============================================================
// BULK ACTIONS
// ============================================================
function toggleAllCheckboxes() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.tenant-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
}

function confirmBulkAction() {
    const bulkAction = document.getElementById('bulkAction');
    const selected = document.querySelectorAll('.tenant-checkbox:checked');
    
    if (!bulkAction.value) {
        alert('Please select an action to perform.');
        return false;
    }
    
    if (selected.length === 0) {
        alert('Please select at least one tenant.');
        return false;
    }
    
    const actionNames = {
        'activate': 'activate',
        'suspend': 'suspend',
        'delete': 'permanently delete'
    };
    
    return confirm(`Are you sure you want to ${actionNames[bulkAction.value] || bulkAction.value} ${selected.length} selected tenant(s)?`);
}

// ============================================================
// EXPORT DATA
// ============================================================
function exportData() {
    const search = document.querySelector('input[name="search"]')?.value || '';
    const plan = document.querySelector('select[name="plan"]')?.value || '';
    const status = document.querySelector('select[name="status"]')?.value || '';
    
    window.location.href = `tenant-export.php?search=${encodeURIComponent(search)}&plan=${encodeURIComponent(plan)}&status=${encodeURIComponent(status)}`;
}

// ============================================================
// AUTO-SUBMIT ON FILTER CHANGE
// ============================================================
document.querySelectorAll('select[name="plan"], select[name="status"]').forEach(select => {
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
});

// ============================================================
// EXPORT DATA WITH FORMAT OPTIONS
// ============================================================
function exportData(format = 'csv') {
    const search = document.querySelector('input[name="search"]')?.value || '';
    const plan = document.querySelector('select[name="plan"]')?.value || '';
    const status = document.querySelector('select[name="status"]')?.value || '';
    
    window.location.href = `tenant-export.php?search=${encodeURIComponent(search)}&plan=${encodeURIComponent(plan)}&status=${encodeURIComponent(status)}&format=${format}`;
}

// ============================================================
// EXPORT DROPDOWN TOGGLE
// ============================================================
function toggleExportDropdown() {
    const dropdown = document.getElementById('exportDropdown');
    dropdown.classList.toggle('show');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('exportDropdown');
    const btn = document.querySelector('.dropdown-toggle');
    if (dropdown && !dropdown.contains(e.target) && !btn.contains(e.target)) {
        dropdown.classList.remove('show');
    }
});
</script>

<?php include 'includes/footer.php'; ?>