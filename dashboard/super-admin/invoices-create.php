<?php
// ============================================================
// INVOICE CREATE - SUPER ADMINISTRATOR
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'super_admin') {
    header('Location: ../client-admin/');
    exit();
}

$db = getDB();

// ============================================================
// FETCH TENANTS
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
        'subscription_id' => !empty($_POST['subscription_id']) ? (int)$_POST['subscription_id'] : null,
        'amount' => (float)($_POST['amount'] ?? 0),
        'tax_amount' => (float)($_POST['tax_amount'] ?? 0),
        'total_amount' => (float)($_POST['total_amount'] ?? 0),
        'due_date' => $_POST['due_date'] ?? null,
        'status' => $_POST['status'] ?? 'draft',
        'notes' => trim($_POST['notes'] ?? ''),
    ];

    $errors = [];
    
    if (empty($form_data['tenant_id'])) {
        $errors[] = 'Please select a tenant.';
    }
    
    if ($form_data['amount'] <= 0) {
        $errors[] = 'Amount must be greater than 0.';
    }
    
    if (empty($form_data['due_date'])) {
        $errors[] = 'Due date is required.';
    }

    if (empty($errors)) {
        try {
            // Generate invoice number
            $invoice_number = 'INV-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $stmt = $db->prepare("
                INSERT INTO invoices (
                    tenant_id, subscription_id, invoice_number, amount,
                    tax_amount, total_amount, due_date, status, notes,
                    created_at
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    NOW()
                )
            ");
            
            $stmt->execute([
                $form_data['tenant_id'],
                $form_data['subscription_id'],
                $invoice_number,
                $form_data['amount'],
                $form_data['tax_amount'],
                $form_data['total_amount'] > 0 ? $form_data['total_amount'] : $form_data['amount'] + $form_data['tax_amount'],
                $form_data['due_date'],
                $form_data['status'],
                $form_data['notes']
            ]);
            
            logActivity(
                SessionManager::get('user_id'),
                'invoice_created',
                "Created invoice: $invoice_number for tenant ID: {$form_data['tenant_id']}"
            );
            
            $success = "Invoice created successfully!";
            $form_data = [];
            
        } catch (Exception $e) {
            $error = 'Error creating invoice: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    .form-container { background: white; border-radius: var(--radius); border: 1px solid var(--gray-200); padding: 28px 32px; box-shadow: var(--shadow); }
    .form-container .form-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 4px; display: flex; align-items: center; gap: 8px; }
    .form-container .form-title i { color: var(--primary); }
    .form-container .form-subtitle { color: var(--gray-500); font-size: 0.85rem; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid var(--gray-100); }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 24px; }
    .form-group { display: flex; flex-direction: column; gap: 4px; }
    .form-group.full-width { grid-column: 1 / -1; }
    .form-group label { font-weight: 600; font-size: 0.82rem; color: var(--gray-700); }
    .form-group label .required { color: var(--danger); margin-left: 2px; }
    .form-group .help-text { font-size: 0.7rem; color: var(--gray-400); margin-top: 2px; }
    .form-group input, .form-group select, .form-group textarea { padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 10px; font-family: 'Inter', sans-serif; font-size: 0.85rem; transition: var(--transition); background: var(--gray-50); color: var(--gray-700); width: 100%; }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--primary); background: white; box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06); }
    .form-group textarea { resize: vertical; min-height: 80px; }
    .form-section-title { font-weight: 600; font-size: 0.9rem; color: var(--gray-700); grid-column: 1 / -1; padding-top: 8px; border-bottom: 1px solid var(--gray-100); padding-bottom: 8px; margin-bottom: 4px; display: flex; align-items: center; gap: 8px; }
    .form-section-title i { color: var(--primary); font-size: 0.85rem; }
    .form-actions { display: flex; gap: 12px; margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--gray-200); flex-wrap: wrap; }
    .form-actions .btn { padding: 10px 28px; border-radius: 10px; border: none; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: var(--transition); font-family: 'Inter', sans-serif; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
    .form-actions .btn-primary { background: var(--primary); color: white; }
    .form-actions .btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.25); }
    .form-actions .btn-secondary { background: var(--gray-100); color: var(--gray-600); }
    .form-actions .btn-secondary:hover { background: var(--gray-200); }
    .error-message { background: #FEF2F2; color: #DC2626; padding: 14px 18px; border-radius: 10px; font-size: 0.85rem; margin-bottom: 16px; border: 1px solid #FECACA; display: flex; align-items: flex-start; gap: 12px; }
    .success-message { background: #ECFDF5; color: #065F46; padding: 14px 18px; border-radius: 10px; font-size: 0.85rem; margin-bottom: 16px; border: 1px solid #A7F3D0; display: flex; align-items: flex-start; gap: 12px; }
    @media (max-width: 768px) {
        .form-grid { grid-template-columns: 1fr; gap: 12px; }
        .form-container { padding: 20px; }
        .form-actions { flex-direction: column; }
        .form-actions .btn { justify-content: center; width: 100%; }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-file-invoice" style="color:var(--primary);margin-right:8px;"></i> Create Invoice
                    <small>Generate a new invoice for a tenant</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="billing.php" class="btn-outline">
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
            <div class="form-title"><i class="fas fa-file-invoice"></i> Invoice Details</div>
            <div class="form-subtitle">Fill in the details below to create a new invoice.</div>
            
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-section-title"><i class="fas fa-building"></i> Billing Information</div>
                    
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
                        <label>Subscription (Optional)</label>
                        <select name="subscription_id">
                            <option value="">Select Subscription</option>
                            <?php
                            try {
                                $stmt = $db->query("SELECT s.id, s.plan, t.name FROM subscriptions s LEFT JOIN tenants t ON s.tenant_id = t.id ORDER BY s.created_at DESC");
                                $subs = $stmt->fetchAll();
                                foreach ($subs as $sub):
                            ?>
                                <option value="<?php echo $sub['id']; ?>">
                                    <?php echo htmlspecialchars($sub['name'] ?? 'Unknown') . ' - ' . ucfirst($sub['plan']); ?>
                                </option>
                            <?php endforeach; } catch (Exception $e) {} ?>
                        </select>
                    </div>

                    <div class="form-section-title"><i class="fas fa-money-bill-wave"></i> Amount Details</div>
                    
                    <div class="form-group">
                        <label>Subtotal Amount <span class="required">*</span></label>
                        <input type="number" name="amount" step="0.01" placeholder="0.00" value="<?php echo htmlspecialchars($form_data['amount'] ?? ''); ?>" required onchange="calculateTotal()">
                    </div>
                    
                    <div class="form-group">
                        <label>Tax Amount</label>
                        <input type="number" name="tax_amount" step="0.01" placeholder="0.00" value="<?php echo htmlspecialchars($form_data['tax_amount'] ?? ''); ?>" onchange="calculateTotal()">
                        <div class="help-text">VAT or other taxes (optional)</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Total Amount</label>
                        <input type="number" name="total_amount" step="0.01" placeholder="Auto-calculated" value="<?php echo htmlspecialchars($form_data['total_amount'] ?? ''); ?>" readonly style="background:var(--gray-100);font-weight:600;">
                        <div class="help-text">Auto-calculated: Subtotal + Tax</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Due Date <span class="required">*</span></label>
                        <input type="date" name="due_date" value="<?php echo htmlspecialchars($form_data['due_date'] ?? date('Y-m-d', strtotime('+30 days'))); ?>" required>
                    </div>

                    <div class="form-section-title"><i class="fas fa-cog"></i> Invoice Settings</div>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="draft" <?php echo ($form_data['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="sent" <?php echo ($form_data['status'] ?? '') === 'sent' ? 'selected' : ''; ?>>Sent</option>
                            <option value="paid" <?php echo ($form_data['status'] ?? '') === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        </select>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Notes</label>
                        <textarea name="notes" placeholder="Additional notes or payment instructions..."><?php echo htmlspecialchars($form_data['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Create Invoice</button>
                    <a href="billing.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
function calculateTotal() {
    var amount = parseFloat(document.querySelector('input[name="amount"]').value) || 0;
    var tax = parseFloat(document.querySelector('input[name="tax_amount"]').value) || 0;
    document.querySelector('input[name="total_amount"]').value = (amount + tax).toFixed(2);
}
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});
</script>
</body>
</html>