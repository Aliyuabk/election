<?php
// subscriptions-export.php - Export subscriptions to CSV
require_once 'includes/db.php';

$db = Database::getInstance();
$conn = $db->getConnection();

// Get filter parameters
$search = $_GET['search'] ?? '';
$filter_plan = $_GET['plan'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_tenant = $_GET['tenant'] ?? '';
$filter_billing = $_GET['billing'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$query = "SELECT 
            s.id,
            s.plan,
            s.billing_cycle,
            s.amount,
            s.currency,
            DATE_FORMAT(s.start_date, '%Y-%m-%d') as start_date,
            DATE_FORMAT(s.end_date, '%Y-%m-%d') as end_date,
            s.payment_status,
            s.auto_renew,
            s.payment_method,
            s.transaction_reference,
            DATE_FORMAT(s.created_at, '%Y-%m-%d %H:%i:%s') as created_at,
            t.name as tenant_name,
            t.slug as tenant_slug
          FROM subscriptions s
          INNER JOIN tenants t ON s.tenant_id = t.id
          WHERE t.deleted_at IS NULL";

$params = [];

if ($search) {
    $query .= " AND (t.name LIKE ? OR t.slug LIKE ? OR s.transaction_reference LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if ($filter_plan) {
    $query .= " AND s.plan = ?";
    $params[] = $filter_plan;
}

if ($filter_status) {
    $query .= " AND s.payment_status = ?";
    $params[] = $filter_status;
}

if ($filter_tenant) {
    $query .= " AND s.tenant_id = ?";
    $params[] = $filter_tenant;
}

if ($filter_billing) {
    $query .= " AND s.billing_cycle = ?";
    $params[] = $filter_billing;
}

if ($date_from) {
    $query .= " AND DATE(s.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $query .= " AND DATE(s.created_at) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY s.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$subscriptions = $stmt->fetchAll();

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="subscriptions_export_' . date('Y-m-d_His') . '.csv"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Headers
$headers = ['ID', 'Tenant', 'Plan', 'Billing Cycle', 'Amount', 'Currency', 'Start Date', 'End Date', 'Status', 'Auto Renew', 'Payment Method', 'Transaction Reference', 'Created At'];
fputcsv($output, $headers);

// Data
foreach ($subscriptions as $sub) {
    fputcsv($output, [
        $sub['id'],
        $sub['tenant_name'],
        ucfirst($sub['plan']),
        ucfirst($sub['billing_cycle']),
        number_format($sub['amount'], 2),
        $sub['currency'],
        $sub['start_date'],
        $sub['end_date'],
        ucfirst($sub['payment_status']),
        $sub['auto_renew'] ? 'Yes' : 'No',
        $sub['payment_method'] ? ucfirst(str_replace('_', ' ', $sub['payment_method'])) : 'N/A',
        $sub['transaction_reference'] ?? '',
        $sub['created_at']
    ]);
}

fclose($output);
exit;
?>