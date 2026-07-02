<?php
// role-details.php - Modal content for role details
require_once 'includes/db.php';
$db = Database::getInstance()->getConnection();

$role_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$role_id) {
    echo '<div class="error-message">Invalid role ID.</div>';
    exit;
}

// Get role details - REMOVED deleted_at
$stmt = $db->prepare("
    SELECT r.*, 
           (SELECT COUNT(*) FROM users WHERE role_id = r.id) as user_count
    FROM roles r
    WHERE r.id = ? AND r.tenant_id IS NULL
");
$stmt->execute([$role_id]);
$role = $stmt->fetch();

if (!$role) {
    echo '<div class="error-message">Role not found.</div>';
    exit;
}

$permissions = json_decode($role['permissions_json'], true);
$isSystem = $role['is_system'] == 1;

// Define permission labels
$permissionLabels = [
    'create_users' => 'Create Users',
    'edit_users' => 'Edit Users',
    'delete_users' => 'Delete Users',
    'view_users' => 'View Users',
    'manage_roles' => 'Manage Roles',
    'create_elections' => 'Create Elections',
    'edit_elections' => 'Edit Elections',
    'delete_elections' => 'Delete Elections',
    'view_elections' => 'View Elections',
    'manage_elections' => 'Manage Elections',
    'manage_candidates' => 'Manage Candidates',
    'submit_results' => 'Submit Results',
    'verify_results' => 'Verify Results',
    'view_results' => 'View Results',
    'manage_results' => 'Manage Results',
    'publish_results' => 'Publish Results',
    'manage_reports' => 'Manage Reports',
    'view_finance' => 'View Finance',
    'manage_finance' => 'Manage Finance',
    'create_budgets' => 'Create Budgets',
    'manage_expenses' => 'Manage Expenses',
    'view_reports' => 'View Financial Reports',
    'report_incidents' => 'Report Incidents',
    'view_incidents' => 'View Incidents',
    'manage_incidents' => 'Manage Incidents',
    'resolve_incidents' => 'Resolve Incidents',
    'manage_agents' => 'Manage Agents',
    'assign_agents' => 'Assign Agents',
    'view_agents' => 'View Agents',
    'manage_agent_payments' => 'Manage Agent Payments',
    'view_audit_logs' => 'View Audit Logs',
    'manage_security' => 'Manage Security',
    'view_activity_logs' => 'View Activity Logs',
    'manage_tenants' => 'Manage Tenants',
    'view_tenants' => 'View Tenants',
    'send_broadcasts' => 'Send Broadcasts',
    'manage_broadcasts' => 'Manage Broadcasts',
    'view_broadcasts' => 'View Broadcasts'
];
?>

<style>
.role-detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 20px;
}

.role-detail-item {
    background: #f8faff;
    padding: 14px 18px;
    border-radius: 10px;
    border: 1px solid #eef3f8;
}

.role-detail-item .label {
    font-size: 0.7rem;
    text-transform: uppercase;
    color: #8b9bb5;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.role-detail-item .value {
    font-size: 0.95rem;
    color: #0b1a33;
    font-weight: 500;
    margin-top: 4px;
}

.permission-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
}

.permission-tag {
    background: #dbeafe;
    color: #1e40af;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 500;
}

.permission-tag.system {
    background: #ede9fe;
    color: #5b21b6;
}

.permission-tag.custom {
    background: #d1fae5;
    color: #065f46;
}

.no-permissions {
    color: #8b9bb5;
    font-style: italic;
    font-size: 0.9rem;
}

.error-message {
    padding: 20px;
    color: #ef4444;
    text-align: center;
    background: #fee2e2;
    border-radius: 10px;
}

@media (max-width: 600px) {
    .role-detail-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="role-detail-content">
    <div class="role-detail-grid">
        <div class="role-detail-item">
            <div class="label"><i class="fas fa-tag"></i> Role Name</div>
            <div class="value">
                <?php echo htmlspecialchars($role['name']); ?>
                <?php if ($isSystem): ?>
                <span style="font-size:0.65rem; background:#ede9fe; color:#8b5cf6; padding:2px 10px; border-radius:30px; margin-left:8px; font-weight:500;">System</span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="role-detail-item">
            <div class="label"><i class="fas fa-link"></i> Slug</div>
            <div class="value"><?php echo htmlspecialchars($role['slug']); ?></div>
        </div>
        
        <div class="role-detail-item">
            <div class="label"><i class="fas fa-layer-group"></i> Level</div>
            <div class="value">
                <span class="level-badge <?php echo $role['level']; ?>">
                    <?php echo ucwords(str_replace('_', ' ', $role['level'])); ?>
                </span>
            </div>
        </div>
        
        <div class="role-detail-item">
            <div class="label"><i class="fas fa-circle"></i> Status</div>
            <div class="value">
                <span class="status-badge <?php echo $role['is_active'] ? 'active' : 'inactive'; ?>">
                    <?php if ($role['is_active']): ?>
                    <i class="fas fa-check-circle"></i> Active
                    <?php else: ?>
                    <i class="fas fa-times-circle"></i> Inactive
                    <?php endif; ?>
                </span>
            </div>
        </div>
        
        <div class="role-detail-item" style="grid-column: 1 / -1;">
            <div class="label"><i class="fas fa-align-left"></i> Description</div>
            <div class="value">
                <?php echo htmlspecialchars($role['description'] ?? 'No description provided.'); ?>
            </div>
        </div>
        
        <div class="role-detail-item" style="grid-column: 1 / -1;">
            <div class="label"><i class="fas fa-users"></i> Users with this role</div>
            <div class="value"><?php echo number_format($role['user_count']); ?> user(s)</div>
        </div>
        
        <div class="role-detail-item" style="grid-column: 1 / -1;">
            <div class="label"><i class="fas fa-key"></i> Permissions</div>
            <div class="value">
                <?php if (empty($permissions) || count(array_filter($permissions)) === 0): ?>
                <span class="no-permissions">No permissions assigned to this role.</span>
                <?php else: ?>
                <div class="permission-list">
                    <?php foreach ($permissions as $key => $value): 
                        if (!$value) continue;
                        $label = $permissionLabels[$key] ?? ucwords(str_replace('_', ' ', $key));
                    ?>
                    <span class="permission-tag <?php echo $isSystem ? 'system' : 'custom'; ?>">
                        <i class="fas fa-check-circle" style="font-size:0.6rem;"></i>
                        <?php echo htmlspecialchars($label); ?>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($role['created_at']): ?>
        <div class="role-detail-item">
            <div class="label"><i class="fas fa-calendar-plus"></i> Created</div>
            <div class="value"><?php echo date('F d, Y h:i A', strtotime($role['created_at'])); ?></div>
        </div>
        <?php endif; ?>
        
        <?php if (isset($role['updated_at']) && $role['updated_at'] && $role['updated_at'] !== $role['created_at']): ?>
        <div class="role-detail-item">
            <div class="label"><i class="fas fa-calendar-edit"></i> Last Updated</div>
            <div class="value"><?php echo date('F d, Y h:i A', strtotime($role['updated_at'])); ?></div>
        </div>
        <?php endif; ?>
    </div>
</div>