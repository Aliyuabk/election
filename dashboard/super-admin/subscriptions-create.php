<?php
// ============================================================
// SUBSCRIPTION CREATE - SUPER ADMINISTRATOR
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Start session and check login
SessionManager::start();

// Redirect if not logged in
if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

// Check role - only super_admin can access this page
if (SessionManager::get('role_level') !== 'super_admin') {
    header('Location: ../client-admin/');
    exit();
}

// Get database connection
$db = getDB();

// ============================================================
// FETCH TENANTS FOR DROPDOWN
// ============================================================
$tenants = [];
try {
    $stmt = $db->query("SELECT id, name FROM tenants WHERE deleted_at IS NULL ORDER BY name");
    $tenants = $stmt->fetchAll();
} catch (Exception $e) {
    // Continue
}

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
$error = '';
$success = '';
$form_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = [
        'tenant_id' => (int)($_POST['tenant_id'] ?? 0),
        'plan' => $_POST['plan'] ?? 'basic',
        'billing_cycle' => $_POST['billing_cycle'] ?? 'monthly',
        'amount' => (float)($_POST['amount'] ?? 0),
        'currency' => $_POST['currency'] ?? 'NGN',
        'start_date' => $_POST['start_date'] ?? null,
        'end_date' => $_POST['end_date'] ?? null,
        'auto_renew' => isset($_POST['auto_renew']) ? 1 : 0,
        'payment_status' => $_POST['payment_status'] ?? 'pending',
        'payment_method' => $_POST['payment_method'] ?? '',
        'transaction_reference' => trim($_POST['transaction_reference'] ?? ''),
        'invoice_url' => trim($_POST['invoice_url'] ?? ''),
    ];

    $errors = [];
    
    if (empty($form_data['tenant_id'])) {
        $errors[] = 'Please select a tenant.';
    }
    
    if (empty($form_data['plan'])) {
        $errors[] = 'Please select a plan.';
    }
    
    if ($form_data['amount'] <= 0) {
        $errors[] = 'Amount must be greater than 0.';
    }
    
    if (empty($form_data['start_date'])) {
        $errors[] = 'Start date is required.';
    }
    
    if (empty($form_data['end_date'])) {
        $errors[] = 'End date is required.';
    } elseif (strtotime($form_data['end_date']) <= strtotime($form_data['start_date'])) {
        $errors[] = 'End date must be after start date.';
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Insert subscription
            $stmt = $db->prepare("
                INSERT INTO subscriptions (
                    tenant_id, plan, billing_cycle, amount, currency,
                    start_date, end_date, auto_renew, payment_status,
                    payment_method, transaction_reference, invoice_url,
                    created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?,
                    NOW(), NOW()
                )
            ");
            
            $stmt->execute([
                $form_data['tenant_id'],
                $form_data['plan'],
                $form_data['billing_cycle'],
                $form_data['amount'],
                $form_data['currency'],
                $form_data['start_date'],
                $form_data['end_date'],
                $form_data['auto_renew'],
                $form_data['payment_status'],
                $form_data['payment_method'],
                $form_data['transaction_reference'],
                $form_data['invoice_url']
            ]);
            
            $subscription_id = $db->lastInsertId();
            
            // Update tenant subscription info
            $tenant_status = $form_data['payment_status'] === 'paid' ? 'active' : 'trial';
            $stmt = $db->prepare("
                UPDATE tenants SET 
                    subscription_plan = ?,
                    subscription_status = ?,
                    subscription_start = ?,
                    subscription_end = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $form_data['plan'],
                $tenant_status,
                $form_data['start_date'],
                $form_data['end_date'],
                $form_data['tenant_id']
            ]);
            
            // Log activity
            logActivity(
                SessionManager::get('user_id'),
                'subscription_created',
                "Created subscription for tenant ID: {$form_data['tenant_id']} with plan: {$form_data['plan']}"
            );
            
            $db->commit();
            
            $success = "Subscription created successfully!";
            $form_data = [];
            
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = 'Error creating subscription: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Get user info
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    .form-container {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 28px 32px;
        box-shadow: var(--shadow);
    }
    .form-container .form-title {
        font-size: 1.1rem;
        font-weight: 700;
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .form-container .form-title i {
        color: var(--primary);
    }
    .form-container .form-subtitle {
        color: var(--gray-500);
        font-size: 0.85rem;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--gray-100);
    }
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px 24px;
    }
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .form-group.full-width {
        grid-column: 1 / -1;
    }
    .form-group label {
        font-weight: 600;
        font-size: 0.82rem;
        color: var(--gray-700);
    }
    .form-group label .required {
        color: var(--danger);
        margin-left: 2px;
    }
    .form-group .help-text {
        font-size: 0.7rem;
        color: var(--gray-400);
        margin-top: 2px;
    }
    .form-group input,
    .form-group select {
        padding: 10px 14px;
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        font-family: 'Inter', sans-serif;
        font-size: 0.85rem;
        transition: var(--transition);
        background: var(--gray-50);
        color: var(--gray-700);
        width: 100%;
    }
    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
    }
    .form-group .checkbox-group {
        display: flex;
        align-items: center;
        gap: 10px;
        padding-top: 6px;
    }
    .form-group .checkbox-group input[type="checkbox"] {
        width: 20px;
        height: 20px;
        accent-color: var(--primary);
        cursor: pointer;
    }
    .form-group .checkbox-group label {
        font-weight: 400;
        cursor: pointer;
        font-size: 0.85rem;
    }
    .form-section-title {
        font-weight: 600;
        font-size: 0.9rem;
        color: var(--gray-700);
        grid-column: 1 / -1;
        padding-top: 8px;
        border-bottom: 1px solid var(--gray-100);
        padding-bottom: 8px;
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .form-section-title i {
        color: var(--primary);
        font-size: 0.85rem;
    }
    .form-actions {
        display: flex;
        gap: 12px;
        margin-top: 24px;
        padding-top: 20px;
        border-top: 1px solid var(--gray-200);
        flex-wrap: wrap;
    }
    .form-actions .btn {
        padding: 10px 28px;
        border-radius: 10px;
        border: none;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .form-actions .btn-primary {
        background: var(--primary);
        color: white;
    }
    .form-actions .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.25);
    }
    .form-actions .btn-secondary {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .form-actions .btn-secondary:hover {
        background: var(--gray-200);
    }
    .error-message {
        background: #FEF2F2;
        color: #DC2626;
        padding: 14px 18px;
        border-radius: 10px;
        font-size: 0.85rem;
        margin-bottom: 16px;
        border: 1px solid #FECACA;
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }
    .success-message {
        background: #ECFDF5;
        color: #065F46;
        padding: 14px 18px;
        border-radius: 10px;
        font-size: 0.85rem;
        margin-bottom: 16px;
        border: 1px solid #A7F3D0;
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }
    @media (max-width: 768px) {
        .form-grid { grid-template-columns: 1fr; gap: 12px; }
        .form-container { padding: 20px; }
        .form-actions { flex-direction: column; }
        .form-actions .btn { justify-content: center; width: 100%; }
    }
    @media (max-width: 480px) {
        .form-container { padding: 16px; }
        .form-group input, .form-group select { padding: 8px 12px; font-size: 0.8rem; }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-plus-circle" style="color:var(--primary);margin-right:8px;"></i> Create Subscription
                    <small>Create a new subscription for a tenant</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="subscriptions.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-message"><i class="fas fa-exclamation-circle"></i><div><?php echo $error; ?></div></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="success-message"><i class="fas fa-check-circle"></i><div><?php echo $success; ?></div></div>
        <?php endif; ?>

        <div class="form-container">
            <div class="form-title"><i class="fas fa-credit-card"></i> Subscription Details</div>
            <div class="form-subtitle">Fill in the details below to create a new subscription.</div>
            
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-section-title"><i class="fas fa-building"></i> Tenant & Plan</div>
                    
                    <div class="form-group">
                        <label>Tenant <span class="required">*</span></label>
                        <select name="tenant_id" required>
                            <option value="">Select Tenant</option>
                            <?php foreach ($tenants as $t): ?>
                                <option value="<?php echo $t['id']; ?>" <?php echo ($form_data['tenant_id'] ?? 0) == $t['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($t['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Plan <span class="required">*</span></label>
                        <select name="plan" required>
                            <option value="free" <?php echo ($form_data['plan'] ?? '') === 'free' ? 'selected' : ''; ?>>Free</option>
                            <option value="basic" <?php echo ($form_data['plan'] ?? '') === 'basic' ? 'selected' : ''; ?>>Basic</option>
                            <option value="standard" <?php echo ($form_data['plan'] ?? '') === 'standard' ? 'selected' : ''; ?>>Standard</option>
                            <option value="premium" <?php echo ($form_data['plan'] ?? '') === 'premium' ? 'selected' : ''; ?>>Premium</option>
                            <option value="enterprise" <?php echo ($form_data['plan'] ?? '') === 'enterprise' ? 'selected' : ''; ?>>Enterprise</option>
                        </select>
                    </div>

                    <div class="form-section-title"><i class="fas fa-calendar-alt"></i> Billing Details</div>
                    
                    <div class="form-group">
                        <label>Billing Cycle</label>
                        <select name="billing_cycle">
                            <option value="monthly" <?php echo ($form_data['billing_cycle'] ?? '') === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                            <option value="quarterly" <?php echo ($form_data['billing_cycle'] ?? '') === 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                            <option value="yearly" <?php echo ($form_data['billing_cycle'] ?? '') === 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Amount <span class="required">*</span></label>
                        <input type="number" name="amount" step="0.01" placeholder="0.00" value="<?php echo htmlspecialchars($form_data['amount'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Currency</label>
                        <select name="currency">
                            <option value="NGN" <?php echo ($form_data['currency'] ?? '') === 'NGN' ? 'selected' : ''; ?>>NGN - Nigerian Naira</option>
                            <option value="USD" <?php echo ($form_data['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>USD - US Dollar</option>
                            <option value="EUR" <?php echo ($form_data['currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>>EUR - Euro</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Start Date <span class="required">*</span></label>
                        <input type="date" name="start_date" value="<?php echo htmlspecialchars($form_data['start_date'] ?? date('Y-m-d')); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>End Date <span class="required">*</span></label>
                        <input type="date" name="end_date" value="<?php echo htmlspecialchars($form_data['end_date'] ?? date('Y-m-d', strtotime('+1 year'))); ?>" required>
                    </div>

                    <div class="form-section-title"><i class="fas fa-cog"></i> Payment Settings</div>
                    
                    <div class="form-group">
                        <label>Payment Status</label>
                        <select name="payment_status">
                            <option value="pending" <?php echo ($form_data['payment_status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="paid" <?php echo ($form_data['payment_status'] ?? '') === 'paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="overdue" <?php echo ($form_data['payment_status'] ?? '') === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                            <option value="cancelled" <?php echo ($form_data['payment_status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="refunded" <?php echo ($form_data['payment_status'] ?? '') === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Payment Method</label>
                        <select name="payment_method">
                            <option value="">Select Method</option>
                            <option value="bank_transfer" <?php echo ($form_data['payment_method'] ?? '') === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                            <option value="card" <?php echo ($form_data['payment_method'] ?? '') === 'card' ? 'selected' : ''; ?>>Credit/Debit Card</option>
                            <option value="mobile_money" <?php echo ($form_data['payment_method'] ?? '') === 'mobile_money' ? 'selected' : ''; ?>>Mobile Money</option>
                            <option value="paypal" <?php echo ($form_data['payment_method'] ?? '') === 'paypal' ? 'selected' : ''; ?>>PayPal</option>
                            <option value="cash" <?php echo ($form_data['payment_method'] ?? '') === 'cash' ? 'selected' : ''; ?>>Cash</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Transaction Reference</label>
                        <input type="text" name="transaction_reference" placeholder="TX-2024-001" value="<?php echo htmlspecialchars($form_data['transaction_reference'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Invoice URL</label>
                        <input type="url" name="invoice_url" placeholder="https://example.com/invoice/123" value="<?php echo htmlspecialchars($form_data['invoice_url'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group full-width">
                        <div class="checkbox-group">
                            <input type="checkbox" name="auto_renew" id="autoRenew" value="1" <?php echo isset($form_data['auto_renew']) && $form_data['auto_renew'] ? 'checked' : 'checked'; ?>>
                            <label for="autoRenew">Auto-Renew Subscription</label>
                        </div>
                        <div class="help-text">If enabled, the subscription will automatically renew at the end of the billing cycle.</div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Create Subscription</button>
                    <a href="subscriptions.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
// Same JS as other pages
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});

// Sidebar toggle, dropdowns, profile functions (same as other pages)
// ... (JavaScript omitted for brevity - same as other pages)
</script>
</body>
</html>