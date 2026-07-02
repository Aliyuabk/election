<?php
// users-export.php - Export users to CSV
require_once 'includes/db.php';

$db = Database::getInstance();
$conn = $db->getConnection();

// Get filter parameters
$search = $_GET['search'] ?? '';
$filter_role = $_GET['role'] ?? '';
$filter_tenant = $_GET['tenant'] ?? '';
$filter_status = $_GET['status'] ?? '';

// Build query
$query = "SELECT 
            u.id,
            u.user_code,
            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            u.status,
            DATE_FORMAT(u.created_at, '%Y-%m-%d %H:%i:%s') as created_at,
            DATE_FORMAT(u.last_login_at, '%Y-%m-%d %H:%i:%s') as last_login_at,
            t.name as tenant_name,
            r.name as role_name
          FROM users u
          LEFT JOIN tenants t ON u.tenant_id = t.id
          LEFT JOIN roles r ON u.role_id = r.id
          WHERE u.deleted_at IS NULL";

$params = [];

if ($search) {
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.user_code LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
}

if ($filter_role) {
    $query .= " AND u.role_id = ?";
    $params[] = $filter_role;
}

if ($filter_tenant) {
    $query .= " AND u.tenant_id = ?";
    $params[] = $filter_tenant;
}

if ($filter_status) {
    $query .= " AND u.status = ?";
    $params[] = $filter_status;
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d_His') . '.csv"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Headers
$headers = ['ID', 'User Code', 'First Name', 'Last Name', 'Email', 'Phone', 'Status', 'Created At', 'Last Login', 'Tenant', 'Role'];
fputcsv($output, $headers);

// Data
foreach ($users as $user) {
    fputcsv($output, [
        $user['id'],
        $user['user_code'],
        $user['first_name'],
        $user['last_name'],
        $user['email'],
        $user['phone'],
        $user['status'],
        $user['created_at'],
        $user['last_login_at'] ?? 'Never',
        $user['tenant_name'] ?? 'System',
        $user['role_name'] ?? 'Unknown'
    ]);
}

fclose($output);
exit;
?>