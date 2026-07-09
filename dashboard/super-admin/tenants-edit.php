<?php
// ============================================================
// TENANT EDIT - SUPER ADMINISTRATOR (FIXED)
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
// GET TENANT ID
// ============================================================
$tenant_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($tenant_id <= 0) {
    header('Location: tenants.php');
    exit();
}

// ============================================================
// FETCH TENANT DETAILS
// ============================================================
$tenant = null;
try {
    $stmt = $db->prepare("
        SELECT 
            t.*,
            u.full_name as created_by_name,
            u.email as created_by_email
        FROM tenants t
        LEFT JOIN users u ON t.created_by = u.id
        WHERE t.id = ? AND t.deleted_at IS NULL
    ");
    $stmt->execute([$tenant_id]);
    $tenant = $stmt->fetch();
} catch (Exception $e) {
    // Continue
}

if (!$tenant) {
    header('Location: tenants.php');
    exit();
}

// ============================================================
// FETCH STATES AND LGAS
// ============================================================
$states = [];
try {
    $stmt = $db->query("SELECT id, name FROM states WHERE is_active = 1 ORDER BY name");
    $states = $stmt->fetchAll();
} catch (Exception $e) {
    // Continue
}

$lgas = [];
if ($tenant['state_id']) {
    try {
        $stmt = $db->prepare("SELECT id, name FROM lgas WHERE state_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$tenant['state_id']]);
        $lgas = $stmt->fetchAll();
    } catch (Exception $e) {
        // Continue
    }
}

// ============================================================
// FETCH ADMIN USER
// ============================================================
$admin_user = null;
try {
    $stmt = $db->prepare("
        SELECT id, first_name, last_name, email, phone 
        FROM users 
        WHERE tenant_id = ? AND role_id IN (SELECT id FROM roles WHERE level = 'client_admin') 
        LIMIT 1
    ");
    $stmt->execute([$tenant_id]);
    $admin_user = $stmt->fetch();
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
    $action = $_POST['action'] ?? '';
    
    // Handle quick actions (suspend/activate)
    if ($action === 'suspend' || $action === 'activate') {
        try {
            $is_active = ($action === 'activate') ? 1 : 0;
            $stmt = $db->prepare("UPDATE tenants SET is_active = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$is_active, $tenant_id]);
            
            if ($stmt->rowCount() > 0) {
                $action_msg = $action === 'activate' ? 'activated' : 'suspended';
                $success = "Tenant {$action_msg} successfully!";
                logActivity(SessionManager::get('user_id'), "tenant_{$action_msg}", "Tenant ID: $tenant_id");
                
                // Refresh tenant data
                $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ? AND deleted_at IS NULL");
                $stmt->execute([$tenant_id]);
                $tenant = $stmt->fetch();
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    } else {
        // Regular form update
        $form_data = [
            'name' => trim($_POST['name'] ?? ''),
            'type' => $_POST['type'] ?? 'political_party',
            'subscription_plan' => $_POST['subscription_plan'] ?? 'basic',
            'subscription_status' => $_POST['subscription_status'] ?? 'trial',
            'contact_email' => trim($_POST['contact_email'] ?? ''),
            'contact_phone' => trim($_POST['contact_phone'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'state_id' => !empty($_POST['state_id']) ? (int)$_POST['state_id'] : null,
            'lga_id' => !empty($_POST['lga_id']) ? (int)$_POST['lga_id'] : null,
            'primary_color' => $_POST['primary_color'] ?? '#3b82f6',
            'secondary_color' => $_POST['secondary_color'] ?? '#10b981',
            'max_users' => (int)($_POST['max_users'] ?? 100),
            'max_agents' => (int)($_POST['max_agents'] ?? 500),
            'subscription_start' => $_POST['subscription_start'] ?? null,
            'subscription_end' => $_POST['subscription_end'] ?? null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'admin_email' => trim($_POST['admin_email'] ?? ''),
            'admin_password' => $_POST['admin_password'] ?? '',
            'admin_first_name' => trim($_POST['admin_first_name'] ?? ''),
            'admin_last_name' => trim($_POST['admin_last_name'] ?? ''),
            'admin_phone' => trim($_POST['admin_phone'] ?? ''),
        ];

        // Validate required fields
        $errors = [];
        
        if (empty($form_data['name'])) {
            $errors[] = 'Organization name is required.';
        }
        
        if (empty($form_data['contact_email'])) {
            $errors[] = 'Contact email is required.';
        } elseif (!filter_var($form_data['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid contact email address.';
        }
        
        // Check if tenant name already exists
        if (!empty($form_data['name'])) {
            try {
                $stmt = $db->prepare("SELECT id FROM tenants WHERE name = ? AND id != ? AND deleted_at IS NULL");
                $stmt->execute([$form_data['name'], $tenant_id]);
                if ($stmt->fetch()) {
                    $errors[] = 'Organization name already exists.';
                }
            } catch (Exception $e) {
                // Continue
            }
        }

        if (empty($errors)) {
            try {
                // Generate slug
                $slug = strtolower(preg_replace('/[^a-zA-Z0-9-]/', '-', $form_data['name']));
                $slug = preg_replace('/-+/', '-', $slug);
                $slug = trim($slug, '-');
                
                // Update tenant
                $stmt = $db->prepare("
                    UPDATE tenants SET
                        name = ?,
                        slug = ?,
                        type = ?,
                        subscription_plan = ?,
                        subscription_status = ?,
                        subscription_start = ?,
                        subscription_end = ?,
                        max_users = ?,
                        max_agents = ?,
                        contact_email = ?,
                        contact_phone = ?,
                        address = ?,
                        state_id = ?,
                        lga_id = ?,
                        primary_color = ?,
                        secondary_color = ?,
                        is_active = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $form_data['name'],
                    $slug,
                    $form_data['type'],
                    $form_data['subscription_plan'],
                    $form_data['subscription_status'],
                    !empty($form_data['subscription_start']) ? $form_data['subscription_start'] : null,
                    !empty($form_data['subscription_end']) ? $form_data['subscription_end'] : null,
                    $form_data['max_users'],
                    $form_data['max_agents'],
                    $form_data['contact_email'],
                    $form_data['contact_phone'],
                    $form_data['address'],
                    $form_data['state_id'],
                    $form_data['lga_id'],
                    $form_data['primary_color'],
                    $form_data['secondary_color'],
                    $form_data['is_active'],
                    $tenant_id
                ]);
                
                // Update admin user if email or other fields provided
                if (!empty($form_data['admin_email'])) {
                    $update_admin = [];
                    $params = [];
                    
                    if (!empty($form_data['admin_email'])) {
                        $update_admin[] = "email = ?";
                        $params[] = $form_data['admin_email'];
                    }
                    
                    if (!empty($form_data['admin_first_name'])) {
                        $update_admin[] = "first_name = ?";
                        $params[] = $form_data['admin_first_name'];
                    }
                    
                    if (!empty($form_data['admin_last_name'])) {
                        $update_admin[] = "last_name = ?";
                        $params[] = $form_data['admin_last_name'];
                    }
                    
                    if (!empty($form_data['admin_phone'])) {
                        $update_admin[] = "phone = ?";
                        $params[] = $form_data['admin_phone'];
                    }
                    
                    if (!empty($form_data['admin_password']) && strlen($form_data['admin_password']) >= 8) {
                        $update_admin[] = "password_hash = ?";
                        $params[] = password_hash($form_data['admin_password'], PASSWORD_DEFAULT);
                    }
                    
                    if (!empty($update_admin)) {
                        $params[] = $tenant_id;
                        $sql = "UPDATE users SET " . implode(", ", $update_admin) . " WHERE tenant_id = ? AND role_id IN (SELECT id FROM roles WHERE level = 'client_admin')";
                        $stmt = $db->prepare($sql);
                        $stmt->execute($params);
                        
                        // If password was changed, send email
                        if (!empty($form_data['admin_password']) && strlen($form_data['admin_password']) >= 8) {
                            try {
                                $subject = "Admin Password Updated - " . APP_NAME;
                                $message = "Your admin password for {$form_data['name']} has been updated.\n\n";
                                $message .= "New Password: {$form_data['admin_password']}\n\n";
                                $message .= "Please change your password after logging in.\n\n";
                                $message .= "Login: " . APP_URL . "/auth/login.php\n\n";
                                $message .= "Best regards,\n" . APP_NAME . " Team";
                                sendEmail($form_data['admin_email'], $subject, $message);
                            } catch (Exception $e) {
                                // Email failed but update was successful
                                error_log("Password update email failed: " . $e->getMessage());
                            }
                        }
                    }
                }
                
                logActivity(
                    SessionManager::get('user_id'),
                    'tenant_updated',
                    "Updated tenant: {$form_data['name']} (ID: $tenant_id)"
                );
                
                $success = "Tenant updated successfully!";
                
                // Refresh tenant data
                $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ? AND deleted_at IS NULL");
                $stmt->execute([$tenant_id]);
                $tenant = $stmt->fetch();
                
            } catch (PDOException $e) {
                $error = 'Database error updating tenant: ' . $e->getMessage();
                error_log("Tenant update PDO Error: " . $e->getMessage());
            } catch (Exception $e) {
                $error = 'Error updating tenant: ' . $e->getMessage();
                error_log("Tenant update Error: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
}

// Get user info
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       TENANT EDIT SPECIFIC STYLES
       ============================================================ */
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
    }
    .form-container .form-subtitle {
        color: var(--gray-500);
        font-size: 0.85rem;
        margin-bottom: 20px;
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
    .form-group select,
    .form-group textarea {
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
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
    }
    .form-group input.error,
    .form-group select.error {
        border-color: var(--danger);
    }
    .form-group textarea {
        resize: vertical;
        min-height: 60px;
    }
    .form-group .color-input {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .form-group .color-input input[type="color"] {
        width: 44px;
        height: 44px;
        padding: 2px;
        border-radius: 8px;
        border: 1px solid var(--gray-200);
        cursor: pointer;
        flex-shrink: 0;
        background: none;
    }
    .form-group .color-input input[type="color"]::-webkit-color-swatch-wrapper {
        padding: 2px;
    }
    .form-group .color-input input[type="color"]::-webkit-color-swatch {
        border-radius: 6px;
        border: none;
    }
    .form-group .color-input input[type="text"] {
        flex: 1;
        font-family: 'Inter', sans-serif;
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
        flex-shrink: 0;
        border-radius: 4px;
        border: 2px solid var(--gray-300);
    }
    .form-group .checkbox-group input[type="checkbox"]:checked {
        border-color: var(--primary);
    }
    .form-group .checkbox-group label {
        font-weight: 400;
        cursor: pointer;
        font-size: 0.85rem;
        color: var(--gray-700);
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
    .form-actions .btn-danger {
        background: var(--danger);
        color: white;
    }
    .form-actions .btn-danger:hover {
        background: #DC2626;
    }
    .form-actions .btn-success {
        background: var(--secondary);
        color: white;
    }
    .form-actions .btn-success:hover {
        background: #059669;
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
    }
    .error-message {
        background: #FEF2F2;
        color: #DC2626;
        padding: 12px 16px;
        border-radius: 10px;
        font-size: 0.85rem;
        margin-bottom: 16px;
        border: 1px solid #FECACA;
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }
    .error-message i {
        margin-top: 2px;
    }
    .success-message {
        background: #ECFDF5;
        color: #065F46;
        padding: 12px 16px;
        border-radius: 10px;
        font-size: 0.85rem;
        margin-bottom: 16px;
        border: 1px solid #A7F3D0;
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }
    .success-message i {
        margin-top: 2px;
    }

    /* Status Bar */
    .status-bar {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 12px 20px;
        margin-bottom: 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        box-shadow: var(--shadow);
    }
    .status-bar .status-info {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .status-bar .status-label {
        font-weight: 500;
        font-size: 0.85rem;
        color: var(--gray-600);
    }
    .tenant-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    .tenant-status-badge.active { background: #ECFDF5; color: #065F46; }
    .tenant-status-badge.suspended { background: #FEF2F2; color: #991B1B; }
    .tenant-status-badge.trial { background: #FFFBEB; color: #92400E; }
    .tenant-status-badge.expired { background: #FEF2F2; color: #991B1B; }
    .tenant-status-badge.free { background: var(--gray-100); color: var(--gray-500); }
    .tenant-status-badge.basic { background: #FFFBEB; color: #92400E; }
    .tenant-status-badge.standard { background: #ECFDF5; color: #065F46; }
    .tenant-status-badge.premium { background: #EFF6FF; color: #1E40AF; }
    .tenant-status-badge.enterprise { background: #F5F3FF; color: #5B21B6; }
    .tenant-status-badge .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
    }
    .tenant-status-badge.active .dot { background: #10B981; }
    .tenant-status-badge.suspended .dot { background: #EF4444; }
    .tenant-status-badge.trial .dot { background: #F59E0B; }
    .tenant-status-badge.expired .dot { background: #EF4444; }

    .status-bar .meta-info {
        display: flex;
        align-items: center;
        gap: 16px;
        font-size: 0.8rem;
        color: var(--gray-400);
        flex-wrap: wrap;
    }
    .status-bar .meta-info span {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .status-bar .meta-info i {
        font-size: 0.75rem;
    }

    /* No Admin Alert */
    .no-admin-alert {
        background: #FEF2F2;
        padding: 12px 16px;
        border-radius: 8px;
        color: var(--danger);
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 10px;
        border: 1px solid #FECACA;
    }
    .no-admin-alert i {
        font-size: 1.1rem;
    }

    /* Modal Styles */
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.4);
        z-index: 300;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .modal-overlay.active { display: flex; }
    .modal {
        background: white;
        border-radius: var(--radius);
        max-width: 440px;
        width: 100%;
        padding: 28px 32px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        animation: modalIn 0.25s ease;
    }
    @keyframes modalIn {
        from { transform: scale(0.95) translateY(10px); opacity: 0; }
        to { transform: scale(1) translateY(0); opacity: 1; }
    }
    .modal .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }
    .modal .modal-header h3 {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--gray-800);
    }
    .modal .modal-header .close-btn {
        background: none;
        border: none;
        font-size: 1.4rem;
        color: var(--gray-400);
        cursor: pointer;
        transition: var(--transition);
        padding: 0 4px;
    }
    .modal .modal-header .close-btn:hover {
        color: var(--gray-600);
    }
    .modal .modal-body {
        margin-bottom: 20px;
        color: var(--gray-600);
        font-size: 0.9rem;
        line-height: 1.6;
    }
    .modal .modal-body strong {
        color: var(--gray-800);
    }
    .modal .modal-footer {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }
    .modal .modal-footer .btn {
        padding: 8px 20px;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .modal .modal-footer .btn-secondary {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .modal .modal-footer .btn-secondary:hover {
        background: var(--gray-200);
    }
    .modal .modal-footer .btn-danger {
        background: var(--danger);
        color: white;
    }
    .modal .modal-footer .btn-danger:hover {
        background: #DC2626;
    }
    .modal .modal-footer .btn-primary {
        background: var(--primary);
        color: white;
    }
    .modal .modal-footer .btn-primary:hover {
        background: var(--primary-dark);
    }

    /* Responsive */
    @media (max-width: 992px) {
        .form-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        .form-container {
            padding: 20px;
        }
        .status-bar {
            flex-direction: column;
            align-items: flex-start;
        }
        .status-bar .meta-info {
            width: 100%;
            justify-content: flex-start;
        }
    }
    @media (max-width: 768px) {
        .form-container {
            padding: 16px;
        }
        .form-actions {
            flex-direction: column;
        }
        .form-actions .btn {
            justify-content: center;
            width: 100%;
        }
        .form-group input,
        .form-group select {
            padding: 8px 12px;
            font-size: 0.8rem;
        }
        .form-group .color-input input[type="color"] {
            width: 38px;
            height: 38px;
        }
        .modal {
            padding: 20px;
            margin: 10px;
        }
        .status-bar {
            padding: 12px 16px;
        }
        .status-bar .status-info {
            gap: 8px;
        }
        .tenant-status-badge {
            font-size: 0.7rem;
            padding: 3px 10px;
        }
    }
    @media (max-width: 480px) {
        .form-container {
            padding: 12px;
            border-radius: 12px;
        }
        .form-grid {
            gap: 10px;
        }
        .form-section-title {
            font-size: 0.8rem;
        }
        .status-bar .meta-info {
            font-size: 0.7rem;
            gap: 10px;
        }
        .modal .modal-footer {
            flex-direction: column;
        }
        .modal .modal-footer .btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<main class="main-content">
    <!-- Fixed Header -->
    <?php include 'includes/header.php'; ?>
    
    <!-- Main Content Inner -->
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-edit" style="color:var(--primary);margin-right:8px;"></i> Edit Tenant
                    <small>Update organization details for <?php echo htmlspecialchars($tenant['name']); ?></small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="tenants-view.php?id=<?php echo $tenant_id; ?>" class="btn-outline">
                    <i class="fas fa-eye"></i> View
                </a>
                <a href="tenants.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Tenants
                </a>
            </div>
        </div>

        <!-- Error/Success Messages -->
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <div><?php echo $success; ?></div>
            </div>
        <?php endif; ?>

        <!-- Status Bar -->
        <div class="status-bar">
            <div class="status-info">
                <span class="status-label">Current Status:</span>
                <span class="tenant-status-badge <?php echo $tenant['is_active'] ? 'active' : 'suspended'; ?>">
                    <span class="dot"></span>
                    <?php echo $tenant['is_active'] ? 'Active' : 'Suspended'; ?>
                </span>
                <span class="tenant-status-badge <?php echo $tenant['subscription_plan']; ?>">
                    <?php echo ucfirst($tenant['subscription_plan']); ?>
                </span>
                <span class="tenant-status-badge <?php echo $tenant['subscription_status']; ?>">
                    <?php echo ucfirst($tenant['subscription_status']); ?>
                </span>
            </div>
            <div class="meta-info">
                <span><i class="fas fa-calendar-alt"></i> Created: <?php echo date('M j, Y', strtotime($tenant['created_at'])); ?></span>
                <?php if ($tenant['created_by_name']): ?>
                    <span><i class="fas fa-user"></i> by <?php echo htmlspecialchars($tenant['created_by_name']); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Edit Form -->
        <div class="form-container">
            <div class="form-title">Edit Organization Details</div>
            <div class="form-subtitle">Update the information for <?php echo htmlspecialchars($tenant['name']); ?>.</div>
            
            <form method="POST" action="" id="tenantForm">
                <div class="form-grid">
                    <!-- Organization Information -->
                    <div class="form-section-title">Organization Information</div>
                    
                    <div class="form-group">
                        <label>Organization Name <span class="required">*</span></label>
                        <input type="text" name="name" placeholder="e.g., All Progressives Congress" 
                               value="<?php echo htmlspecialchars($tenant['name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Organization Type</label>
                        <select name="type">
                            <option value="political_party" <?php echo $tenant['type'] === 'political_party' ? 'selected' : ''; ?>>Political Party</option>
                            <option value="candidate" <?php echo $tenant['type'] === 'candidate' ? 'selected' : ''; ?>>Candidate</option>
                            <option value="ngo" <?php echo $tenant['type'] === 'ngo' ? 'selected' : ''; ?>>NGO</option>
                            <option value="observer_group" <?php echo $tenant['type'] === 'observer_group' ? 'selected' : ''; ?>>Observer Group</option>
                            <option value="cso" <?php echo $tenant['type'] === 'cso' ? 'selected' : ''; ?>>CSO</option>
                            <option value="research_institution" <?php echo $tenant['type'] === 'research_institution' ? 'selected' : ''; ?>>Research Institution</option>
                        </select>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Address</label>
                        <textarea name="address" placeholder="Organization address"><?php echo htmlspecialchars($tenant['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Contact Email <span class="required">*</span></label>
                        <input type="email" name="contact_email" placeholder="contact@organization.ng" 
                               value="<?php echo htmlspecialchars($tenant['contact_email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Contact Phone</label>
                        <input type="tel" name="contact_phone" placeholder="+234 800 555 5555" 
                               value="<?php echo htmlspecialchars($tenant['contact_phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>State</label>
                        <select name="state_id" id="stateSelect">
                            <option value="">Select State</option>
                            <?php foreach ($states as $state): ?>
                                <option value="<?php echo $state['id']; ?>" <?php echo $tenant['state_id'] == $state['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($state['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>LGA</label>
                        <select name="lga_id" id="lgaSelect">
                            <option value="">Select LGA</option>
                            <?php foreach ($lgas as $lga): ?>
                                <option value="<?php echo $lga['id']; ?>" <?php echo $tenant['lga_id'] == $lga['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lga['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Branding -->
                    <div class="form-section-title">Branding</div>
                    
                    <div class="form-group">
                        <label>Primary Color</label>
                        <div class="color-input">
                            <input type="color" name="primary_color" id="primaryColor" value="<?php echo htmlspecialchars($tenant['primary_color'] ?? '#3b82f6'); ?>">
                            <input type="text" name="primary_color_text" placeholder="#3b82f6" 
                                   value="<?php echo htmlspecialchars($tenant['primary_color'] ?? '#3b82f6'); ?>"
                                   oninput="document.getElementById('primaryColor').value=this.value">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Secondary Color</label>
                        <div class="color-input">
                            <input type="color" name="secondary_color" id="secondaryColor" value="<?php echo htmlspecialchars($tenant['secondary_color'] ?? '#10b981'); ?>">
                            <input type="text" name="secondary_color_text" placeholder="#10b981" 
                                   value="<?php echo htmlspecialchars($tenant['secondary_color'] ?? '#10b981'); ?>"
                                   oninput="document.getElementById('secondaryColor').value=this.value">
                        </div>
                    </div>
                    
                    <!-- Subscription -->
                    <div class="form-section-title">Subscription Settings</div>
                    
                    <div class="form-group">
                        <label>Subscription Plan</label>
                        <select name="subscription_plan">
                            <option value="free" <?php echo $tenant['subscription_plan'] === 'free' ? 'selected' : ''; ?>>Free</option>
                            <option value="basic" <?php echo $tenant['subscription_plan'] === 'basic' ? 'selected' : ''; ?>>Basic</option>
                            <option value="standard" <?php echo $tenant['subscription_plan'] === 'standard' ? 'selected' : ''; ?>>Standard</option>
                            <option value="premium" <?php echo $tenant['subscription_plan'] === 'premium' ? 'selected' : ''; ?>>Premium</option>
                            <option value="enterprise" <?php echo $tenant['subscription_plan'] === 'enterprise' ? 'selected' : ''; ?>>Enterprise</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Subscription Status</label>
                        <select name="subscription_status">
                            <option value="trial" <?php echo $tenant['subscription_status'] === 'trial' ? 'selected' : ''; ?>>Trial</option>
                            <option value="active" <?php echo $tenant['subscription_status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="suspended" <?php echo $tenant['subscription_status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            <option value="expired" <?php echo $tenant['subscription_status'] === 'expired' ? 'selected' : ''; ?>>Expired</option>
                            <option value="cancelled" <?php echo $tenant['subscription_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Subscription Start Date</label>
                        <input type="date" name="subscription_start" 
                               value="<?php echo htmlspecialchars($tenant['subscription_start'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Subscription End Date</label>
                        <input type="date" name="subscription_end" 
                               value="<?php echo htmlspecialchars($tenant['subscription_end'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Max Users</label>
                        <input type="number" name="max_users" value="<?php echo htmlspecialchars($tenant['max_users'] ?? 100); ?>" min="1">
                        <div class="help-text">Maximum number of users allowed for this tenant.</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Max Agents</label>
                        <input type="number" name="max_agents" value="<?php echo htmlspecialchars($tenant['max_agents'] ?? 500); ?>" min="1">
                        <div class="help-text">Maximum number of agents allowed for this tenant.</div>
                    </div>
                    
                    <!-- Status -->
                    <div class="form-section-title">Account Status</div>
                    
                    <div class="form-group full-width">
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_active" id="isActive" value="1" <?php echo $tenant['is_active'] ? 'checked' : ''; ?>>
                            <label for="isActive">Account Active</label>
                        </div>
                        <div class="help-text">Uncheck to suspend this tenant's account.</div>
                    </div>
                    
                    <!-- Admin User -->
                    <div class="form-section-title">Administrator Account</div>
                    
                    <?php if ($admin_user): ?>
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text" name="admin_first_name" placeholder="John" 
                                   value="<?php echo htmlspecialchars($admin_user['first_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text" name="admin_last_name" placeholder="Doe" 
                                   value="<?php echo htmlspecialchars($admin_user['last_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Admin Email</label>
                            <input type="email" name="admin_email" placeholder="admin@organization.ng" 
                                   value="<?php echo htmlspecialchars($admin_user['email'] ?? ''); ?>">
                            <div class="help-text">Update the admin's email address.</div>
                        </div>
                        
                        <div class="form-group">
                            <label>Admin Phone</label>
                            <input type="tel" name="admin_phone" placeholder="+234 800 555 5555" 
                                   value="<?php echo htmlspecialchars($admin_user['phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group full-width">
                            <label>New Admin Password</label>
                            <input type="password" name="admin_password" placeholder="Leave blank to keep current password" minlength="8">
                            <div class="help-text">Enter a new password only if you want to change it. Min 8 characters.</div>
                        </div>
                    <?php else: ?>
                        <div class="form-group full-width">
                            <div class="no-admin-alert">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>No admin user found for this tenant. Please create one.</span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Tenant
                    </button>
                    <a href="tenants-view.php?id=<?php echo $tenant_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <?php if ($tenant['is_active']): ?>
                        <button type="button" class="btn btn-danger" onclick="confirmSuspend()">
                            <i class="fas fa-pause"></i> Suspend
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-success" onclick="confirmActivate()">
                            <i class="fas fa-play"></i> Activate
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</main>

<!-- ============================================================
CONFIRMATION MODAL
============================================================ -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="confirmTitle">Confirm Action</h3>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="confirmBody">
            <p>Are you sure you want to perform this action?</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <form method="POST" action="" id="confirmForm">
                <input type="hidden" name="tenant_id" value="<?php echo $tenant_id; ?>">
                <input type="hidden" name="action" id="confirmAction" value="">
                <button type="submit" class="btn btn-danger" id="confirmBtn">Confirm</button>
            </form>
        </div>
    </div>
</div>

<script>
// ============================================================
// PRELOADER
// ============================================================
window.addEventListener('load', function() {
    const preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() {
            preloader.style.display = 'none';
        }, 600);
    }
});

// ============================================================
// SIDEBAR TOGGLE (mobile)
// ============================================================
const sidebar = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const dashboardHeader = document.getElementById('dashboardHeader');

function toggleSidebar() {
    sidebar.classList.toggle('open');
    sidebarOverlay.classList.toggle('active');
    updateHeaderPosition();
}

function updateHeaderPosition() {
    if (window.innerWidth > 768) {
        dashboardHeader.style.left = '260px';
    } else if (sidebar.classList.contains('open')) {
        dashboardHeader.style.left = '280px';
    } else {
        dashboardHeader.style.left = '0';
    }
}

if (sidebarToggle) {
    sidebarToggle.addEventListener('click', toggleSidebar);
}
if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', toggleSidebar);
}

window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        sidebar.classList.remove('open');
        sidebarOverlay.classList.remove('active');
        dashboardHeader.style.left = '260px';
    } else if (!sidebar.classList.contains('open')) {
        dashboardHeader.style.left = '0';
    }
});

// ============================================================
// SIDEBAR DROPDOWNS
// ============================================================
document.querySelectorAll('.dropdown-toggle').forEach(function(toggle) {
    toggle.addEventListener('click', function(e) {
        e.preventDefault();
        var dropdownId = this.dataset.dropdown;
        var dropdown = document.getElementById(dropdownId);
        var chevron = this.querySelector('.chevron');
        
        if (dropdown) {
            dropdown.classList.toggle('open');
            if (chevron) chevron.classList.toggle('open');
        }
    });
});

// ============================================================
// PROFILE DROPDOWN
// ============================================================
var profileBtn = document.getElementById('profileBtn');
var profileMenu = document.getElementById('profileMenu');

if (profileBtn && profileMenu) {
    profileBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        profileMenu.classList.toggle('active');
    });

    document.addEventListener('click', function(e) {
        if (!profileBtn.contains(e.target) && !profileMenu.contains(e.target)) {
            profileMenu.classList.remove('active');
        }
    });
}

// ============================================================
// STATE -> LGA DYNAMIC LOADING
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    var stateSelect = document.getElementById('stateSelect');
    var lgaSelect = document.getElementById('lgaSelect');
    var currentLga = <?php echo json_encode($tenant['lga_id'] ?? ''); ?>;
    
    if (stateSelect && lgaSelect) {
        stateSelect.addEventListener('change', function() {
            var stateId = this.value;
            lgaSelect.innerHTML = '<option value="">Loading...</option>';
            
            if (stateId) {
                fetch(`ajax/get-lgas.php?state_id=${stateId}`)
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        lgaSelect.innerHTML = '<option value="">Select LGA</option>';
                        if (data.length > 0) {
                            data.forEach(function(lga) {
                                var option = document.createElement('option');
                                option.value = lga.id;
                                option.textContent = lga.name;
                                if (lga.id == currentLga) {
                                    option.selected = true;
                                }
                                lgaSelect.appendChild(option);
                            });
                        }
                    })
                    .catch(function() {
                        lgaSelect.innerHTML = '<option value="">Error loading LGAs</option>';
                    });
            } else {
                lgaSelect.innerHTML = '<option value="">Select LGA</option>';
            }
        });
        
        // Trigger change if state is pre-selected
        if (stateSelect.value) {
            stateSelect.dispatchEvent(new Event('change'));
        }
    }
    
    // Color input sync - both directions
    document.querySelectorAll('.color-input input[type="text"]').forEach(function(textInput) {
        textInput.addEventListener('input', function() {
            var colorInput = this.closest('.color-input').querySelector('input[type="color"]');
            if (colorInput && this.value.match(/^#?[0-9a-f]{6}$/i)) {
                colorInput.value = this.value.startsWith('#') ? this.value : '#' + this.value;
            }
        });
    });
    
    document.querySelectorAll('.color-input input[type="color"]').forEach(function(colorInput) {
        colorInput.addEventListener('input', function() {
            var textInput = this.closest('.color-input').querySelector('input[type="text"]');
            if (textInput) {
                textInput.value = this.value;
            }
        });
    });
});

// ============================================================
// CONFIRMATION MODAL
// ============================================================
function confirmSuspend() {
    var modal = document.getElementById('confirmModal');
    document.getElementById('confirmTitle').textContent = 'Suspend Tenant';
    document.getElementById('confirmBody').innerHTML = 'Are you sure you want to suspend <strong><?php echo htmlspecialchars($tenant['name']); ?></strong>? The tenant will lose access to the platform.';
    document.getElementById('confirmAction').value = 'suspend';
    document.getElementById('confirmBtn').className = 'btn btn-danger';
    document.getElementById('confirmBtn').textContent = 'Suspend';
    modal.classList.add('active');
}

function confirmActivate() {
    var modal = document.getElementById('confirmModal');
    document.getElementById('confirmTitle').textContent = 'Activate Tenant';
    document.getElementById('confirmBody').innerHTML = 'Are you sure you want to activate <strong><?php echo htmlspecialchars($tenant['name']); ?></strong>? The tenant will regain full access.';
    document.getElementById('confirmAction').value = 'activate';
    document.getElementById('confirmBtn').className = 'btn btn-primary';
    document.getElementById('confirmBtn').textContent = 'Activate';
    modal.classList.add('active');
}

function closeModal() {
    document.getElementById('confirmModal').classList.remove('active');
}

// ============================================================
// SEARCH (header)
// ============================================================
var searchInput = document.getElementById('searchInput');
var searchResults = document.getElementById('searchResults');
var searchTimeout;

if (searchInput) {
    searchInput.addEventListener('input', function() {
        var query = this.value.trim();
        clearTimeout(searchTimeout);
        
        if (query.length < 2) {
            if (searchResults) searchResults.classList.remove('active');
            return;
        }
        
        searchTimeout = setTimeout(function() {
            fetch('search.php?q=' + encodeURIComponent(query))
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (searchResults) {
                        searchResults.innerHTML = '';
                        if (data && data.length > 0) {
                            data.forEach(function(item) {
                                var div = document.createElement('a');
                                div.className = 'result-item';
                                div.href = item.url || '#';
                                div.innerHTML = '<i class="fas ' + (item.icon || 'fa-file') + '"></i><span class="text-truncate">' + (item.label || item.name || '') + '</span><span class="result-type">' + ((item.type || '').charAt(0).toUpperCase() + (item.type || '').slice(1)) + '</span>';
                                searchResults.appendChild(div);
                            });
                            searchResults.classList.add('active');
                        } else {
                            searchResults.innerHTML = '<div style="padding:12px;text-align:center;color:var(--gray-500);font-size:0.8rem;"><i class="fas fa-search" style="display:block;font-size:1.2rem;margin-bottom:4px;"></i>No results found</div>';
                            searchResults.classList.add('active');
                        }
                    }
                })
                .catch(function() {});
        }, 300);
    });

    document.addEventListener('click', function(e) {
        var wrapper = document.querySelector('.search-wrapper');
        if (wrapper && !wrapper.contains(e.target) && searchResults) {
            searchResults.classList.remove('active');
        }
    });
}
</script>
</body>
</html>