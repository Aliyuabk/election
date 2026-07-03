<?php
// ============================================================
// USER EDIT - SUPER ADMINISTRATOR (FIXED)
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
// GET USER ID
// ============================================================
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id <= 0) {
    header('Location: users.php');
    exit();
}

// ============================================================
// FETCH USER DETAILS
// ============================================================
$user = null;
try {
    $stmt = $db->prepare("
        SELECT 
            u.*,
            r.name as role_name,
            r.level as role_level,
            t.name as tenant_name,
            t.slug as tenant_slug
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        LEFT JOIN tenants t ON u.tenant_id = t.id
        WHERE u.id = ? AND u.deleted_at IS NULL
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    // Continue
}

if (!$user) {
    header('Location: users.php');
    exit();
}

// ============================================================
// FETCH ROLES
// ============================================================
$roles = [];
try {
    $stmt = $db->query("SELECT id, name, level FROM roles WHERE is_active = 1 ORDER BY name");
    $roles = $stmt->fetchAll();
} catch (Exception $e) {
    // Continue
}

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
    // Check if this is a quick action (suspend/activate)
    $action = $_POST['action'] ?? '';
    
    if ($action === 'suspend' || $action === 'activate') {
        try {
            $status = ($action === 'activate') ? 'active' : 'suspended';
            $stmt = $db->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $user_id]);
            
            if ($stmt->rowCount() > 0) {
                $action_msg = $action === 'activate' ? 'activated' : 'suspended';
                $success = "User {$action_msg} successfully!";
                logActivity(SessionManager::get('user_id'), "user_{$action_msg}", "User ID: $user_id");
                
                // Refresh user data
                $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    } else {
        // Regular form update
        $form_data = [
            'tenant_id' => (int)($_POST['tenant_id'] ?? 0),
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'role_id' => (int)($_POST['role_id'] ?? 0),
            'status' => $_POST['status'] ?? 'active',
            'two_factor_enabled' => isset($_POST['two_factor_enabled']) ? 1 : 0,
            'gender' => $_POST['gender'] ?? '',
            'date_of_birth' => $_POST['date_of_birth'] ?? null,
            'password' => $_POST['password'] ?? '',
        ];

        $errors = [];
        
        if (empty($form_data['tenant_id'])) {
            $errors[] = 'Tenant is required.';
        }
        
        if (empty($form_data['first_name'])) {
            $errors[] = 'First name is required.';
        }
        
        if (empty($form_data['last_name'])) {
            $errors[] = 'Last name is required.';
        }
        
        if (empty($form_data['email'])) {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address.';
        }
        
        if (empty($form_data['role_id'])) {
            $errors[] = 'Role is required.';
        }
        
        // Check if email already exists (excluding current user)
        if (!empty($form_data['email'])) {
            try {
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ? AND deleted_at IS NULL");
                $stmt->execute([$form_data['email'], $user_id]);
                if ($stmt->fetch()) {
                    $errors[] = 'Email already registered by another user.';
                }
            } catch (Exception $e) {
                // Continue
            }
        }

        if (empty($errors)) {
            try {
                // Begin transaction
                $db->beginTransaction();
                
                // Build update query
                $update_fields = [];
                $params = [];
                
                $update_fields[] = "tenant_id = ?";
                $params[] = $form_data['tenant_id'];
                
                $update_fields[] = "first_name = ?";
                $params[] = $form_data['first_name'];
                
                $update_fields[] = "last_name = ?";
                $params[] = $form_data['last_name'];
                
                $update_fields[] = "email = ?";
                $params[] = $form_data['email'];
                
                $update_fields[] = "phone = ?";
                $params[] = $form_data['phone'];
                
                $update_fields[] = "role_id = ?";
                $params[] = $form_data['role_id'];
                
                $update_fields[] = "status = ?";
                $params[] = $form_data['status'];
                
                $update_fields[] = "two_factor_enabled = ?";
                $params[] = $form_data['two_factor_enabled'];
                
                $update_fields[] = "gender = ?";
                $params[] = $form_data['gender'] ?: null;
                
                $update_fields[] = "date_of_birth = ?";
                $params[] = $form_data['date_of_birth'] ?: null;
                
                // Update password if provided
                if (!empty($form_data['password']) && strlen($form_data['password']) >= 8) {
                    $update_fields[] = "password_hash = ?";
                    $params[] = password_hash($form_data['password'], PASSWORD_DEFAULT);
                }
                
                $update_fields[] = "updated_at = NOW()";
                $params[] = $user_id;
                
                $sql = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                
                // If password was changed, send email
                if (!empty($form_data['password']) && strlen($form_data['password']) >= 8) {
                    $subject = "Password Updated - " . APP_NAME;
                    $message = "Your password has been updated.\n\n";
                    $message .= "New Password: {$form_data['password']}\n\n";
                    $message .= "Please change your password after logging in.\n\n";
                    $message .= "Login: " . APP_URL . "/auth/login.php\n\n";
                    $message .= "Best regards,\n" . APP_NAME . " Team";
                    sendEmail($form_data['email'], $subject, $message);
                }
                
                logActivity(
                    SessionManager::get('user_id'),
                    'user_updated',
                    "Updated user: {$form_data['first_name']} {$form_data['last_name']} (ID: $user_id)"
                );
                
                // Commit transaction
                $db->commit();
                $success = "User updated successfully!";
                
                // Refresh user data
                $stmt = $db->prepare("
                    SELECT 
                        u.*,
                        r.name as role_name,
                        r.level as role_level,
                        t.name as tenant_name,
                        t.slug as tenant_slug
                    FROM users u
                    LEFT JOIN roles r ON u.role_id = r.id
                    LEFT JOIN tenants t ON u.tenant_id = t.id
                    WHERE u.id = ? AND u.deleted_at IS NULL
                ");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
            } catch (Exception $e) {
                // Rollback only if transaction is active
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = 'Error updating user: ' . $e->getMessage();
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
<!-- Rest of the HTML remains the same -->
<style>
    /* ============================================================
       USER EDIT - PRO STYLES
       ============================================================ */
    
    .profile-header {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 20px 24px;
        display: flex;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
        box-shadow: var(--shadow);
        margin-bottom: 24px;
        position: relative;
        overflow: hidden;
    }
    .profile-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
    }
    .profile-header .user-avatar-lg {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        font-weight: 700;
        color: white;
        flex-shrink: 0;
        border: 3px solid var(--gray-200);
    }
    .profile-header .user-avatar-lg.blue { background: #3B82F6; }
    .profile-header .user-avatar-lg.green { background: #10B981; }
    .profile-header .user-avatar-lg.purple { background: #8B5CF6; }
    .profile-header .user-avatar-lg.orange { background: #F59E0B; }
    .profile-header .user-avatar-lg.red { background: #EF4444; }
    .profile-header .user-avatar-lg.pink { background: #EC4899; }
    .profile-header .user-avatar-lg.teal { background: #14B8A6; }
    
    .profile-header .user-info h2 {
        font-size: 1.2rem;
        font-weight: 700;
        margin-bottom: 2px;
    }
    .profile-header .user-info .user-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        font-size: 0.82rem;
        color: var(--gray-500);
    }
    .profile-header .user-info .user-meta span {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .profile-header .user-actions {
        margin-left: auto;
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .badge-status .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
    }
    .badge-status.active { background: #ECFDF5; color: #065F46; }
    .badge-status.active .dot { background: #10B981; }
    .badge-status.suspended { background: #FEF2F2; color: #991B1B; }
    .badge-status.suspended .dot { background: #EF4444; }
    .badge-status.pending { background: #FFFBEB; color: #92400E; }
    .badge-status.pending .dot { background: #F59E0B; }

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
        flex-shrink: 0;
    }
    .form-group .checkbox-group label {
        font-weight: 400;
        cursor: pointer;
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

    /* Modal */
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

    @media (max-width: 992px) {
        .profile-header { flex-direction: column; align-items: center; text-align: center; }
        .profile-header .user-actions { margin-left: 0; width: 100%; justify-content: center; }
        .profile-header .user-actions .btn { flex: 1; justify-content: center; }
    }
    @media (max-width: 768px) {
        .form-grid { grid-template-columns: 1fr; gap: 12px; }
        .form-container { padding: 20px; }
        .form-actions { flex-direction: column; }
        .form-actions .btn { justify-content: center; width: 100%; }
        .profile-header { padding: 16px; }
        .profile-header .user-avatar-lg { width: 56px; height: 56px; font-size: 1.2rem; }
        .profile-header .user-info h2 { font-size: 1rem; }
    }
    @media (max-width: 480px) {
        .form-container { padding: 16px; }
        .form-group input, .form-group select { padding: 8px 12px; font-size: 0.8rem; }
        .profile-header .user-avatar-lg { width: 48px; height: 48px; font-size: 1rem; }
        .modal { padding: 20px; margin: 10px; }
        .modal .modal-footer { flex-direction: column; }
        .modal .modal-footer .btn { width: 100%; justify-content: center; }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Profile Header -->
        <?php 
        $avatar_colors = ['blue', 'green', 'purple', 'orange', 'red', 'pink', 'teal'];
        $color_idx = ($user['id'] ?? 0) % count($avatar_colors);
        $avatar_color = $avatar_colors[$color_idx];
        ?>
        <div class="profile-header">
            <div class="user-avatar-lg <?php echo $avatar_color; ?>">
                <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
            </div>
            <div class="user-info">
                <h2><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                <div class="user-meta">
                    <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></span>
                    <span><i class="fas fa-code"></i> <?php echo htmlspecialchars($user['user_code']); ?></span>
                    <span>
                        <span class="badge-status <?php echo $user['status']; ?>">
                            <span class="dot"></span>
                            <?php echo ucfirst($user['status']); ?>
                        </span>
                    </span>
                </div>
            </div>
            <div class="user-actions">
                <a href="users-view.php?id=<?php echo $user['id']; ?>" class="btn-outline" style="padding:8px 16px;font-size:0.8rem;">
                    <i class="fas fa-eye"></i> View
                </a>
                <a href="users.php" class="btn-outline" style="padding:8px 16px;font-size:0.8rem;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <?php if ($user['status'] === 'active'): ?>
                    <button type="button" class="btn btn-danger" onclick="confirmAction('suspend')" style="padding:8px 16px;font-size:0.8rem;">
                        <i class="fas fa-pause"></i> Suspend
                    </button>
                <?php else: ?>
                    <button type="button" class="btn btn-success" onclick="confirmAction('activate')" style="padding:8px 16px;font-size:0.8rem;">
                        <i class="fas fa-play"></i> Activate
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Error/Success Messages -->
        <?php if (!empty($error)): ?>
            <div class="error-message"><i class="fas fa-exclamation-circle"></i><div><?php echo $error; ?></div></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="success-message"><i class="fas fa-check-circle"></i><div><?php echo $success; ?></div></div>
        <?php endif; ?>

        <!-- Edit Form -->
        <div class="form-container">
            <div class="form-title">Edit User</div>
            <div class="form-subtitle">Update user information for <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>.</div>
            
            <form method="POST" action="">
                <div class="form-grid">
                    <!-- Account Details -->
                    <div class="form-section-title">Account Details</div>
                    
                    <div class="form-group">
                        <label>Tenant <span class="required">*</span></label>
                        <select name="tenant_id" required>
                            <option value="">Select Tenant</option>
                            <?php foreach ($tenants as $t): ?>
                                <option value="<?php echo $t['id']; ?>" <?php echo ($user['tenant_id'] == $t['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($t['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Role <span class="required">*</span></label>
                        <select name="role_id" required>
                            <option value="">Select Role</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>" <?php echo ($user['role_id'] == $role['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($role['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="pending" <?php echo $user['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="suspended" <?php echo $user['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            <option value="archived" <?php echo $user['status'] === 'archived' ? 'selected' : ''; ?>>Archived</option>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <div class="checkbox-group">
                            <input type="checkbox" name="two_factor_enabled" id="twoFactor" value="1" <?php echo $user['two_factor_enabled'] ? 'checked' : ''; ?>>
                            <label for="twoFactor">Enable Two-Factor Authentication (2FA)</label>
                        </div>
                        <div class="help-text">Users with 2FA enabled will need to verify their identity using a code sent to their email.</div>
                    </div>

                    <!-- Personal Information -->
                    <div class="form-section-title">Personal Information</div>
                    
                    <div class="form-group">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" placeholder="John" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" placeholder="Doe" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" name="email" placeholder="user@organization.ng" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        <div class="help-text">Changing the email will update the user's login credentials.</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" placeholder="+234 800 555 5555" value="<?php echo htmlspecialchars($user['phone']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender">
                            <option value="">Select Gender</option>
                            <option value="male" <?php echo $user['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo $user['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo $user['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                            <option value="prefer_not_say" <?php echo $user['gender'] === 'prefer_not_say' ? 'selected' : ''; ?>>Prefer not to say</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>">
                    </div>

                    <!-- Security -->
                    <div class="form-section-title">Security</div>
                    
                    <div class="form-group full-width">
                        <label>New Password</label>
                        <input type="password" name="password" placeholder="Leave blank to keep current password" minlength="8">
                        <div class="help-text">Enter a new password only if you want to change it. Min 8 characters. User will be notified via email.</div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update User</button>
                    <a href="users-view.php?id=<?php echo $user['id']; ?>" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                    <?php if ($user['status'] === 'active'): ?>
                        <button type="button" class="btn btn-danger" onclick="confirmAction('suspend')">
                            <i class="fas fa-pause"></i> Suspend
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-success" onclick="confirmAction('activate')">
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
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});

// ============================================================
// SIDEBAR TOGGLE
// ============================================================
var sidebar = document.getElementById('sidebar');
var sidebarToggle = document.getElementById('sidebarToggle');
var sidebarOverlay = document.getElementById('sidebarOverlay');
var dashboardHeader = document.getElementById('dashboardHeader');

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
// CONFIRMATION MODAL
// ============================================================
function confirmAction(action) {
    var modal = document.getElementById('confirmModal');
    var userFullName = '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>';
    
    if (action === 'suspend') {
        document.getElementById('confirmTitle').textContent = 'Suspend User';
        document.getElementById('confirmBody').innerHTML = 'Are you sure you want to suspend <strong>' + userFullName + '</strong>? The user will lose access to the platform.';
        document.getElementById('confirmAction').value = 'suspend';
        document.getElementById('confirmBtn').className = 'btn btn-danger';
        document.getElementById('confirmBtn').textContent = 'Suspend';
    } else if (action === 'activate') {
        document.getElementById('confirmTitle').textContent = 'Activate User';
        document.getElementById('confirmBody').innerHTML = 'Are you sure you want to activate <strong>' + userFullName + '</strong>? The user will regain full access.';
        document.getElementById('confirmAction').value = 'activate';
        document.getElementById('confirmBtn').className = 'btn btn-primary';
        document.getElementById('confirmBtn').textContent = 'Activate';
    }
    
    modal.classList.add('active');
}

function closeModal() {
    document.getElementById('confirmModal').classList.remove('active');
}

// ============================================================
// SEARCH
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