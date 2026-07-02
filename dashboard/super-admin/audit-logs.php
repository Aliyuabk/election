<?php
$page_title = "Audit Logs";
require_once 'includes/db.php';
$db = Database::getInstance()->getConnection();

// Get filters
$action = $_GET['action'] ?? '';
$user = $_GET['user'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$query = "SELECT al.*, u.full_name, u.email, t.name as tenant_name 
          FROM audit_logs al
          LEFT JOIN users u ON al.user_id = u.id
          LEFT JOIN tenants t ON al.tenant_id = t.id
          WHERE 1=1";
$params = [];

if ($action) {
    $query .= " AND al.action LIKE ?";
    $params[] = "%$action%";
}
if ($user) {
    $query .= " AND u.full_name LIKE ?";
    $params[] = "%$user%";
}
if ($date_from) {
    $query .= " AND DATE(al.created_at) >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $query .= " AND DATE(al.created_at) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY al.created_at DESC LIMIT 100";
$logs = $db->prepare($query);
$logs->execute($params);
$logs = $logs->fetchAll();

include 'includes/base.php';
?>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/header.php'; ?>

<main class="main-content">
    <div class="page-header">
        <div>
            <h1>Audit Logs</h1>
            <p class="subtitle">Track all system activities and changes</p>
        </div>
        <button class="btn-secondary" onclick="window.print()">
            <i class="fas fa-print"></i> Export
        </button>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
        <form method="GET" class="filter-form">
            <input type="text" name="action" placeholder="Action..." value="<?php echo htmlspecialchars($action); ?>">
            <input type="text" name="user" placeholder="User..." value="<?php echo htmlspecialchars($user); ?>">
            <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
            <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
            <button type="submit" class="btn-primary"><i class="fas fa-filter"></i> Filter</button>
            <a href="audit-logs.php" class="btn-secondary">Clear</a>
        </form>
    </div>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>User</th>
                    <th>Tenant</th>
                    <th>Action</th>
                    <th>Entity</th>
                    <th>Severity</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                    <td>
                        <div class="user-cell">
                            <div><?php echo htmlspecialchars($log['full_name'] ?? 'System'); ?></div>
                            <div class="user-email"><?php echo htmlspecialchars($log['email'] ?? ''); ?></div>
                        </div>
                    </td>
                    <td><?php echo htmlspecialchars($log['tenant_name'] ?? 'System'); ?></td>
                    <td>
                        <span class="action-tag"><?php echo htmlspecialchars($log['action']); ?></span>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($log['entity_type']); ?>
                        <?php if ($log['entity_id']): ?>
                        <span class="entity-id">#<?php echo $log['entity_id']; ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="severity-badge <?php echo $log['severity']; ?>">
                            <?php echo ucfirst($log['severity']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<?php include 'includes/footer.php'; ?>