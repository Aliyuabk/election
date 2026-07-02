<?php
// tenant-details.php - Called via AJAX
require_once 'includes/db.php';
$db = Database::getInstance()->getConnection();

$tenant_id = $_GET['id'] ?? 0;

if (!$tenant_id) {
    echo '<div class="error-message">Invalid tenant ID</div>';
    exit;
}

$tenant = $db->prepare("
    SELECT t.*,
           (SELECT COUNT(*) FROM users WHERE tenant_id = t.id AND deleted_at IS NULL) as total_users,
           (SELECT COUNT(*) FROM users WHERE tenant_id = t.id AND status = 'active' AND deleted_at IS NULL) as active_users,
           (SELECT COUNT(*) FROM elections WHERE tenant_id = t.id AND deleted_at IS NULL) as total_elections,
           (SELECT COUNT(*) FROM elections WHERE tenant_id = t.id AND status = 'active' AND deleted_at IS NULL) as active_elections,
           (SELECT COUNT(*) FROM elections WHERE tenant_id = t.id AND status = 'closed' AND deleted_at IS NULL) as completed_elections,
           (SELECT COUNT(*) FROM incidents WHERE tenant_id = t.id) as total_incidents,
           (SELECT COUNT(*) FROM support_tickets WHERE tenant_id = t.id AND status != 'closed') as open_tickets,
           (SELECT COUNT(*) FROM subscriptions WHERE tenant_id = t.id) as total_subscriptions,
           (SELECT SUM(amount) FROM invoices WHERE tenant_id = t.id AND status = 'paid') as total_revenue
    FROM tenants t
    WHERE t.id = ? AND t.deleted_at IS NULL
");
$tenant->execute([$tenant_id]);
$tenant = $tenant->fetch();

if (!$tenant) {
    echo '<div class="error-message">Tenant not found</div>';
    exit;
}
?>
<div class="tenant-detail">
    <div class="detail-header">
        <div class="detail-logo">
            <?php if ($tenant['logo_url']): ?>
            <img src="<?php echo htmlspecialchars($tenant['logo_url']); ?>" alt="">
            <?php else: ?>
            <div class="detail-logo-placeholder">
                <?php echo strtoupper(substr($tenant['name'], 0, 2)); ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="detail-title">
            <h3><?php echo htmlspecialchars($tenant['name']); ?></h3>
            <div class="detail-slug"><?php echo htmlspecialchars($tenant['slug']); ?></div>
        </div>
        <span class="status-badge <?php echo $tenant['subscription_status']; ?>">
            <?php echo ucfirst($tenant['subscription_status']); ?>
        </span>
    </div>

    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">Plan</span>
            <span class="plan-badge <?php echo $tenant['subscription_plan']; ?>">
                <?php echo ucfirst($tenant['subscription_plan']); ?>
            </span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Type</span>
            <span><?php echo ucfirst(str_replace('_', ' ', $tenant['type'])); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Registered</span>
            <span><?php echo date('F d, Y', strtotime($tenant['created_at'])); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Expiry</span>
            <span><?php echo $tenant['subscription_end'] ? date('F d, Y', strtotime($tenant['subscription_end'])) : 'N/A'; ?></span>
        </div>
    </div>

    <div class="detail-contact">
        <div class="detail-item">
            <span class="detail-label"><i class="fas fa-envelope"></i> Email</span>
            <span><?php echo htmlspecialchars($tenant['contact_email'] ?? 'Not set'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label"><i class="fas fa-phone"></i> Phone</span>
            <span><?php echo htmlspecialchars($tenant['contact_phone'] ?? 'Not set'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label"><i class="fas fa-map-marker-alt"></i> Address</span>
            <span><?php echo htmlspecialchars($tenant['address'] ?? 'Not set'); ?></span>
        </div>
    </div>

    <div class="detail-stats">
        <div class="stat-item">
            <span class="stat-number"><?php echo $tenant['total_users']; ?></span>
            <span class="stat-label">Total Users</span>
        </div>
        <div class="stat-item">
            <span class="stat-number"><?php echo $tenant['active_users']; ?></span>
            <span class="stat-label">Active Users</span>
        </div>
        <div class="stat-item">
            <span class="stat-number"><?php echo $tenant['total_elections']; ?></span>
            <span class="stat-label">Elections</span>
        </div>
        <div class="stat-item">
            <span class="stat-number"><?php echo $tenant['active_elections']; ?></span>
            <span class="stat-label">Active Elections</span>
        </div>
        <div class="stat-item">
            <span class="stat-number"><?php echo $tenant['total_incidents']; ?></span>
            <span class="stat-label">Incidents</span>
        </div>
        <div class="stat-item">
            <span class="stat-number"><?php echo $tenant['open_tickets']; ?></span>
            <span class="stat-label">Open Tickets</span>
        </div>
        <div class="stat-item">
            <span class="stat-number"><?php echo number_format($tenant['total_revenue'] ?? 0, 2); ?></span>
            <span class="stat-label">Total Revenue</span>
        </div>
        <div class="stat-item">
            <span class="stat-number"><?php echo $tenant['total_subscriptions']; ?></span>
            <span class="stat-label">Subscriptions</span>
        </div>
    </div>
</div>

<style>
.tenant-detail {
    padding: 4px;
}

.detail-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.detail-logo img,
.detail-logo-placeholder {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    object-fit: cover;
}

.detail-logo-placeholder {
    background: #4f9cf7;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1.2rem;
}

.detail-title h3 {
    font-size: 1.1rem;
    font-weight: 600;
    color: #0b1a33;
}

.detail-slug {
    font-size: 0.8rem;
    color: #8b9bb5;
}

.detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 16px;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 8px 12px;
    background: #f8faff;
    border-radius: 8px;
}

.detail-label {
    font-size: 0.7rem;
    color: #8b9bb5;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.detail-contact {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-bottom: 20px;
}

.detail-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    padding-top: 16px;
    border-top: 1px solid #eef3f8;
}

.detail-stats .stat-item {
    text-align: center;
}

.detail-stats .stat-number {
    font-size: 1.2rem;
    font-weight: 600;
    color: #0b1a33;
}

.detail-stats .stat-label {
    font-size: 0.7rem;
    color: #8b9bb5;
    display: block;
}

@media (max-width: 600px) {
    .detail-grid,
    .detail-contact {
        grid-template-columns: 1fr;
    }
    .detail-stats {
        grid-template-columns: 1fr 1fr;
    }
}
</style>