<?php
// subscription-details.php
require_once 'includes/db.php';

$db = Database::getInstance();
$conn = $db->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    echo '<div style="text-align:center; padding:40px; color:#ef4444;">Invalid subscription ID</div>';
    exit;
}

$stmt = $conn->prepare("
    SELECT s.*, t.name as tenant_name, t.slug as tenant_slug
    FROM subscriptions s
    INNER JOIN tenants t ON s.tenant_id = t.id
    WHERE s.id = ?
");
$stmt->execute([$id]);
$sub = $stmt->fetch();

if (!$sub) {
    echo '<div style="text-align:center; padding:40px; color:#ef4444;">Subscription not found</div>';
    exit;
}
?>
<div class="subscription-detail">
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
        <div style="padding:12px 16px; background:#f8faff; border-radius:10px;">
            <div style="font-size:0.7rem; color:#8b9bb5; text-transform:uppercase;">Tenant</div>
            <div style="font-weight:500; color:#0b1a33;"><?php echo htmlspecialchars($sub['tenant_name']); ?></div>
            <div style="font-size:0.8rem; color:#6d83a5;"><?php echo htmlspecialchars($sub['tenant_slug']); ?></div>
        </div>
        <div style="padding:12px 16px; background:#f8faff; border-radius:10px;">
            <div style="font-size:0.7rem; color:#8b9bb5; text-transform:uppercase;">Plan</div>
            <div style="font-weight:500; color:#0b1a33;"><?php echo ucfirst($sub['plan']); ?></div>
            <div style="font-size:0.8rem; color:#6d83a5;"><?php echo ucfirst($sub['billing_cycle']); ?></div>
        </div>
        <div style="padding:12px 16px; background:#f8faff; border-radius:10px;">
            <div style="font-size:0.7rem; color:#8b9bb5; text-transform:uppercase;">Amount</div>
            <div style="font-weight:500; color:#0b1a33;">₦<?php echo number_format($sub['amount'], 2); ?></div>
            <div style="font-size:0.8rem; color:#6d83a5;"><?php echo $sub['currency']; ?></div>
        </div>
        <div style="padding:12px 16px; background:#f8faff; border-radius:10px;">
            <div style="font-size:0.7rem; color:#8b9bb5; text-transform:uppercase;">Status</div>
            <div>
                <span class="status-badge <?php echo $sub['payment_status']; ?>" style="font-size:0.75rem;">
                    <?php echo ucfirst($sub['payment_status']); ?>
                </span>
            </div>
        </div>
        <div style="padding:12px 16px; background:#f8faff; border-radius:10px;">
            <div style="font-size:0.7rem; color:#8b9bb5; text-transform:uppercase;">Start Date</div>
            <div style="font-weight:500; color:#0b1a33;"><?php echo date('F d, Y', strtotime($sub['start_date'])); ?></div>
        </div>
        <div style="padding:12px 16px; background:#f8faff; border-radius:10px;">
            <div style="font-size:0.7rem; color:#8b9bb5; text-transform:uppercase;">End Date</div>
            <div style="font-weight:500; color:#0b1a33;"><?php echo date('F d, Y', strtotime($sub['end_date'])); ?></div>
            <?php if (strtotime($sub['end_date']) < time()): ?>
            <div style="font-size:0.7rem; color:#ef4444;">(Expired)</div>
            <?php endif; ?>
        </div>
        <div style="padding:12px 16px; background:#f8faff; border-radius:10px;">
            <div style="font-size:0.7rem; color:#8b9bb5; text-transform:uppercase;">Payment Method</div>
            <div style="font-weight:500; color:#0b1a33;"><?php echo $sub['payment_method'] ? ucfirst(str_replace('_', ' ', $sub['payment_method'])) : 'N/A'; ?></div>
        </div>
        <div style="padding:12px 16px; background:#f8faff; border-radius:10px;">
            <div style="font-size:0.7rem; color:#8b9bb5; text-transform:uppercase;">Auto Renew</div>
            <div style="font-weight:500; color:#0b1a33;"><?php echo $sub['auto_renew'] ? '✅ Yes' : '❌ No'; ?></div>
        </div>
        <?php if ($sub['transaction_reference']): ?>
        <div style="padding:12px 16px; background:#f8faff; border-radius:10px; grid-column:1/3;">
            <div style="font-size:0.7rem; color:#8b9bb5; text-transform:uppercase;">Transaction Reference</div>
            <div style="font-weight:500; color:#0b1a33; font-family:monospace;"><?php echo htmlspecialchars($sub['transaction_reference']); ?></div>
        </div>
        <?php endif; ?>
        <div style="padding:12px 16px; background:#f8faff; border-radius:10px; grid-column:1/3;">
            <div style="font-size:0.7rem; color:#8b9bb5; text-transform:uppercase;">Created</div>
            <div style="font-weight:500; color:#0b1a33;"><?php echo date('F d, Y H:i:s', strtotime($sub['created_at'])); ?></div>
        </div>
    </div>
</div>

<style>
.subscription-detail .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.7rem;
    padding: 2px 12px;
    border-radius: 30px;
    font-weight: 500;
}

.subscription-detail .status-badge.paid { background: #d1fae5; color: #065f46; }
.subscription-detail .status-badge.pending { background: #fef3c7; color: #92400e; }
.subscription-detail .status-badge.overdue { background: #fee2e2; color: #991b1b; }
.subscription-detail .status-badge.cancelled { background: #f3f4f6; color: #4b5563; }
.subscription-detail .status-badge.refunded { background: #dbeafe; color: #1e40af; }
</style>