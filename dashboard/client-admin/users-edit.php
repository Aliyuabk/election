<?php
// ============================================================
// USER EDIT - CLIENT ADMIN
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

// Check role - only client_admin can access this page
if (SessionManager::get('role_level') !== 'client_admin') {
    header('Location: ../client-admin/');
    exit();
}

// Get database connection
$db = getDB();

// Get user info
$user_id = SessionManager::get('user_id');
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');
$tenant_id = SessionManager::get('tenant_id');

// ============================================================
// GET USER ID
// ============================================================
$edit_user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($edit_user_id <= 0) {
    header('Location: users.php');
    exit();
}

// ============================================================
// FETCH USER DETAILS
// ============================================================
$user = null;
try {
    $stmt = $db->prepare("
        SELECT u.*, r.name as role_name, r.level as role_level
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.id = ? AND u.tenant_id = ? AND u.deleted_at IS NULL
    ");
    $stmt->execute([$edit_user_id, $tenant_id]);
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
    $stmt = $db->prepare("
        SELECT r.id, r.name, r.level 
        FROM roles r 
        WHERE (r.tenant_id = ? OR r.tenant_id IS NULL) 
        AND r.is_active = 1 
        ORDER BY r.name
    ");
    $stmt->execute([$tenant_id]);
    $roles = $stmt->fetchAll();
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
    
    try {
        // Check if this is a quick action (suspend/activate)
        if ($action === 'suspend' || $action === 'activate') {
            $status = ($action === 'activate') ? 'active' : 'suspended';
            $stmt = $db->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$status, $edit_user_id, $tenant_id]);
            
            if ($stmt->rowCount() > 0) {
                $action_msg = $action === 'activate' ? 'activated' : 'suspended';
                $success = "User {$action_msg} successfully!";
                logActivity($user_id, "user_{$action_msg}", "User ID: $edit_user_id");
                
                // Refresh user data
                $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$edit_user_id, $tenant_id]);
                $user = $stmt->fetch();
            }
        } else {
            // Regular form update
            $form_data = [
                'first_name' => trim($_POST['first_name'] ?? ''),
                'last_name' => trim($_POST['last_name'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'phone' => trim($_POST['phone'] ?? ''),
                'role_id' => (int)($_POST['role_id'] ?? 0),
                'status' => $_POST['status'] ?? 'active',
                'gender' => $_POST['gender'] ?? '',
                'date_of_birth' => $_POST['date_of_birth'] ?? null,
                'password' => $_POST['password'] ?? '',
            ];

            $errors = [];
            
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
                    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ? AND tenant_id = ? AND deleted_at IS NULL");
                    $stmt->execute([$form_data['email'], $edit_user_id, $tenant_id]);
                    if ($stmt->fetch()) {
                        $errors[] = 'Email already registered by another user.';
                    }
                } catch (Exception $e) {
                    // Continue
                }
            }

            if (empty($errors)) {
                try {
                    // Build update query
                    $update_fields = [];
                    $params = [];
                    
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
                    
                    $update_fields[] = "gender = ?";
                    $gender_value = !empty($form_data['gender']) ? $form_data['gender'] : null;
                    $params[] = $gender_value;
                    
                    $update_fields[] = "date_of_birth = ?";
                    $dob_value = !empty($form_data['date_of_birth']) ? $form_data['date_of_birth'] : null;
                    $params[] = $dob_value;
                    
                    // Update password if provided
                    if (!empty($form_data['password']) && strlen($form_data['password']) >= 8) {
                        $update_fields[] = "password_hash = ?";
                        $params[] = password_hash($form_data['password'], PASSWORD_DEFAULT);
                    }
                    
                    $update_fields[] = "updated_at = NOW()";
                    $params[] = $edit_user_id;
                    
                    $sql = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = ? AND tenant_id = ?";
                    $params[] = $tenant_id;
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    
                    // If password was changed, send email
                    if (!empty($form_data['password']) && strlen($form_data['password']) >= 8) {
                        try {
                            $subject = "Password Updated - " . APP_NAME;
                            $message = "Dear {$form_data['first_name']},\n\n";
                            $message .= "Your password has been updated.\n\n";
                            $message .= "New Password: {$form_data['password']}\n\n";
                            $message .= "Please change your password after logging in.\n\n";
                            $message .= "Login: " . APP_URL . "/auth/login.php\n\n";
                            $message .= "Best regards,\n" . APP_NAME . " Team";
                            sendEmail($form_data['email'], $subject, $message);
                        } catch (Exception $e) {
                            error_log("Password update email failed: " . $e->getMessage());
                        }
                    }
                    
                    logActivity($user_id, 'user_updated', "Updated user: {$form_data['first_name']} {$form_data['last_name']}");
                    
                    $success = "User updated successfully!";
                    
                    // Refresh user data
                    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND tenant_id = ?");
                    $stmt->execute([$edit_user_id, $tenant_id]);
                    $user = $stmt->fetch();
                    
                } catch (PDOException $e) {
                    $error = 'Database error updating user: ' . $e->getMessage();
                    error_log("User update PDO Error: " . $e->getMessage());
                } catch (Exception $e) {
                    $error = 'Error updating user: ' . $e->getMessage();
                    error_log("User update Error: " . $e->getMessage());
                }
            } else {
                $error = implode('<br>', $errors);
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get avatar color
$avatar_colors = ['blue', 'green', 'purple', 'orange', 'red', 'pink', 'teal'];
$color_idx = ($edit_user_id ?? 0) % count($avatar_colors);
$avatar_color = $avatar_colors[$color_idx];

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       USER EDIT - CLIENT ADMIN STYLES
       ============================================================ */
    
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 20px;
    }
    .page-header h2 {
        font-size: 1.3rem;
        font-weight: 700;
    }
    .page-header h2 small {
        font-size: 0.8rem;
        font-weight: 400;
        color: var(--gray-500);
        display: block;
        margin-top: 2px;
    }
    
    .btn-primary {
        padding: 8px 18px;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.25);
    }
    .btn-outline {
        padding: 8px 16px;
        background: transparent;
        color: var(--gray-600);
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        font-weight: 500;
        font-size: 0.82rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-outline:hover {
        background: var(--gray-50);
        border-color: var(--gray-300);
    }
    .btn-danger {
        padding: 8px 18px;
        background: var(--danger);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-danger:hover {
        background: #DC2626;
        transform: translateY(-1px);
    }
    .btn-success {
        padding: 8px 18px;
        background: var(--secondary);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-success:hover {
        background: #059669;
        transform: translateY(-1px);
        box-shadow: 0 4px 16px rgba(16, 185, 129, 0.25);
    }
    
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
    .profile-header .user-actions {
        margin-left: auto;
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.65rem;
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
        display: flex;
        align-items: center;
        gap: 8px;
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
        border: 1.5px solid var(--gray-200);
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
    .form-group input:disabled {
        background: var(--gray-100);
        cursor: not-allowed;
        opacity: 0.7;
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
    
    .alert {
        padding: 14px 18px;
        border-radius: 10px;
        font-size: 0.85rem;
        margin-bottom: 16px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        border: 1px solid transparent;
    }
    .alert i {
        margin-top: 2px;
        font-size: 1.1rem;
    }
    .alert-success {
        background: #ECFDF5;
        color: #065F46;
        border-color: #A7F3D0;
    }
    .alert-error {
        background: #FEF2F2;
        color: #DC2626;
        border-color: #FECACA;
    }
    
    @media (max-width: 768px) {
        .profile-header {
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .profile-header .user-actions {
            margin-left: 0;
            width: 100%;
            justify-content: center;
        }
        .form-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        .form-container {
            padding: 20px;
        }
        .form-actions {
            flex-direction: column;
        }
        .form-actions .btn {
            justify-content: center;
            width: 100%;
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .profile-header .user-avatar-lg {
            width: 56px;
            height: 56px;
            font-size: 1.2rem;
        }
    }
    @media (max-width: 480px) {
        .form-container {
            padding: 16px;
        }
        .form-group input,
        .form-group select {
            padding: 8px 12px;
            font-size: 0.8rem;
        }
        .profile-header {
            padding: 16px;
        }
        .profile-header .user-avatar-lg {
            width: 48px;
            height: 48px;
            font-size: 1rem;
        }
        .modal {
            padding: 20px;
            margin: 10px;
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
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-edit" style="color:var(--primary);margin-right:8px;"></i> Edit User
                    <small>Update user information</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="users-view.php?id=<?php echo $edit_user_id; ?>" class="btn-outline">
                    <i class="fas fa-eye"></i> View
                </a>
                <a href="users.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Users
                </a>
            </div>
        </div>

        <!-- Profile Header -->
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
                <?php if ($user['status'] === 'active'): ?>
                    <button class="btn-danger" style="padding:8px 16px;font-size:0.8rem;" onclick="confirmAction('suspend')">
                        <i class="fas fa-pause"></i> Suspend
                    </button>
                <?php else: ?>
                    <button class="btn-success" style="padding:8px 16px;font-size:0.8rem;" onclick="confirmAction('activate')">
                        <i class="fas fa-play"></i> Activate
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div><?php echo $success; ?></div>
            </div>
        <?php endif; ?>

        <!-- Edit Form -->
        <div class="form-container">
            <div class="form-title">
                <i class="fas fa-user-circle"></i> Edit User
            </div>
            <div class="form-subtitle">
                Update the information for <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>.
            </div>
            
            <form method="POST" action="">
                <div class="form-grid">
                    <!-- Account Details -->
                    <div class="form-section-title">
                        <i class="fas fa-cog"></i> Account Details
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

                    <!-- Personal Information -->
                    <div class="form-section-title">
                        <i class="fas fa-user"></i> Personal Information
                    </div>
                    
                    <div class="form-group">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Address <span class="required">*</span></label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        <div class="help-text">Changing the email will update the user's login credentials.</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" placeholder="+234 800 555 5555">
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
                    <div class="form-section-title">
                        <i class="fas fa-lock"></i> Security
                    </div>
                    
                    <div class="form-group full-width">
                        <label>New Password</label>
                        <input type="password" name="password" placeholder="Leave blank to keep current password" minlength="8">
                        <div class="help-text">Enter a new password only if you want to change it. Min 8 characters. User will be notified via email.</div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update User
                    </button>
                    <a href="users-view.php?id=<?php echo $edit_user_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
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