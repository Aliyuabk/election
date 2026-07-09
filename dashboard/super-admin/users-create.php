<?php
// ============================================================
// USER CREATE - SUPER ADMINISTRATOR (PRO STYLE)
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
// GET TENANT ID (optional pre-selection)
// ============================================================
$pre_selected_tenant = isset($_GET['tenant']) ? (int)$_GET['tenant'] : 0;

// ============================================================
// FETCH TENANT DETAILS (if pre-selected)
// ============================================================
$pre_selected_tenant_name = '';
if ($pre_selected_tenant > 0) {
    try {
        $stmt = $db->prepare("SELECT name FROM tenants WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$pre_selected_tenant]);
        $tenant = $stmt->fetch();
        if ($tenant) {
            $pre_selected_tenant_name = $tenant['name'];
        }
    } catch (Exception $e) {
        // Continue
    }
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
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'role_id' => (int)($_POST['role_id'] ?? 0),
        'password' => $_POST['password'] ?? '',
        'status' => $_POST['status'] ?? 'active',
        'two_factor_enabled' => isset($_POST['two_factor_enabled']) ? 1 : 0,
        'gender' => $_POST['gender'] ?? '',
        'date_of_birth' => $_POST['date_of_birth'] ?? null,
    ];

    $errors = [];
    
    // Validate required fields
    if (empty($form_data['tenant_id'])) {
        $errors[] = 'Please select a tenant.';
    }
    
    if (empty($form_data['first_name'])) {
        $errors[] = 'First name is required.';
    }
    
    if (empty($form_data['last_name'])) {
        $errors[] = 'Last name is required.';
    }
    
    if (empty($form_data['email'])) {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    
    if (empty($form_data['role_id'])) {
        $errors[] = 'Please select a role.';
    }
    
    if (empty($form_data['password'])) {
        $errors[] = 'Password is required.';
    } elseif (strlen($form_data['password']) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    
    // Check if email already exists
    if (!empty($form_data['email'])) {
        try {
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND deleted_at IS NULL");
            $stmt->execute([$form_data['email']]);
            if ($stmt->fetch()) {
                $errors[] = 'This email is already registered.';
            }
        } catch (Exception $e) {
            // Continue
        }
    }

    // If no errors, create user
    if (empty($errors)) {
        try {
            // Generate user code
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE tenant_id = ?");
            $stmt->execute([$form_data['tenant_id']]);
            $count = $stmt->fetch()['count'] ?? 0;
            $user_code = 'USR' . str_pad($count + 1, 6, '0', STR_PAD_LEFT);
            
            // Hash password
            $password_hash = password_hash($form_data['password'], PASSWORD_DEFAULT);
            
            // Insert user
            $stmt = $db->prepare("
                INSERT INTO users (
                    tenant_id, user_code, role_id, first_name, last_name,
                    email, phone, password_hash, status, two_factor_enabled,
                    gender, date_of_birth, created_by, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, NOW()
                )
            ");
            
            $stmt->execute([
                $form_data['tenant_id'],
                $user_code,
                $form_data['role_id'],
                $form_data['first_name'],
                $form_data['last_name'],
                $form_data['email'],
                $form_data['phone'],
                $password_hash,
                $form_data['status'],
                $form_data['two_factor_enabled'],
                $form_data['gender'] ?: null,
                $form_data['date_of_birth'] ?: null,
                SessionManager::get('user_id')
            ]);
            
            $user_id = $db->lastInsertId();
            
            // Log activity
            logActivity(
                SessionManager::get('user_id'),
                'user_created',
                "Created user: {$form_data['first_name']} {$form_data['last_name']} (ID: $user_id) for tenant ID: {$form_data['tenant_id']}"
            );
            
            // Get tenant name for email
            $tenant_name = '';
            foreach ($tenants as $t) {
                if ($t['id'] == $form_data['tenant_id']) {
                    $tenant_name = $t['name'];
                    break;
                }
            }
            
            // Send welcome email (wrap in try-catch so it doesn't break the process)
            try {
                $subject = "Welcome to " . APP_NAME;
                $message = "Dear {$form_data['first_name']},\n\n";
                $message .= "You have been added as a user to \"{$tenant_name}\" on " . APP_NAME . ".\n\n";
                $message .= "Your account has been created with the following credentials:\n";
                $message .= "----------------------------------------\n";
                $message .= "Email: {$form_data['email']}\n";
                $message .= "Password: {$form_data['password']}\n";
                $message .= "----------------------------------------\n\n";
                $message .= "Please login at: " . APP_URL . "/auth/login.php\n\n";
                $message .= "For security reasons, we recommend changing your password after your first login.\n\n";
                $message .= "If you have any questions, please contact support.\n\n";
                $message .= "Best regards,\n" . APP_NAME . " Team";
                
                sendEmail($form_data['email'], $subject, $message);
                $success = "User created successfully! A welcome email has been sent to the user.";
            } catch (Exception $e) {
                $success = "User created successfully! (Welcome email could not be sent)";
                error_log("Welcome email failed: " . $e->getMessage());
            }
            
            $form_data = []; // Clear form
            
        } catch (PDOException $e) {
            $error = 'Database error creating user: ' . $e->getMessage();
            error_log("User creation PDO Error: " . $e->getMessage());
        } catch (Exception $e) {
            $error = 'Error creating user: ' . $e->getMessage();
            error_log("User creation Error: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
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
<!-- Rest of HTML remains the same -->
<style>
    /* ============================================================
       USER CREATE - PRO STYLES
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
    .form-group input.error,
    .form-group select.error {
        border-color: var(--danger);
        background: #FEF2F2;
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
    }
    .form-group .checkbox-group label {
        font-weight: 400;
        cursor: pointer;
        font-size: 0.85rem;
        color: var(--gray-700);
    }
    .form-group .checkbox-group label i {
        color: var(--gray-400);
        margin-right: 4px;
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
    .error-message i {
        margin-top: 2px;
        font-size: 1.1rem;
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
    .success-message i {
        margin-top: 2px;
        font-size: 1.1rem;
    }

    /* Tenant pre-select notice */
    .tenant-notice {
        background: #EFF6FF;
        border: 1px solid #BFDBFE;
        border-radius: 8px;
        padding: 10px 16px;
        color: #1E40AF;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .tenant-notice i {
        font-size: 1rem;
    }

    @media (max-width: 768px) {
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
        .form-section-title {
            font-size: 0.8rem;
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
                    <i class="fas fa-user-plus" style="color:var(--primary);margin-right:8px;"></i> Create User
                    <small>Add a new user to the platform</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="<?php echo $pre_selected_tenant > 0 ? 'tenants-users.php?id=' . $pre_selected_tenant : 'users.php'; ?>" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
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

        <!-- Form -->
        <div class="form-container">
            <div class="form-title">
                <i class="fas fa-user-circle"></i> User Information
            </div>
            <div class="form-subtitle">
                Fill in the details below to create a new user account.
                <?php if ($pre_selected_tenant > 0 && !empty($pre_selected_tenant_name)): ?>
                    <div class="tenant-notice" style="margin-top:10px;">
                        <i class="fas fa-building"></i>
                        <span>User will be created for: <strong><?php echo htmlspecialchars($pre_selected_tenant_name); ?></strong></span>
                    </div>
                <?php endif; ?>
            </div>
            
            <form method="POST" action="" id="userForm" novalidate>
                <div class="form-grid">
                    <!-- Account Details -->
                    <div class="form-section-title">
                        <i class="fas fa-cog"></i> Account Details
                    </div>
                    
                    <div class="form-group">
                        <label for="tenant_id">Tenant <span class="required">*</span></label>
                        <select name="tenant_id" id="tenant_id" required>
                            <option value="">Select Tenant</option>
                            <?php foreach ($tenants as $t): ?>
                                <option value="<?php echo $t['id']; ?>" <?php echo ($pre_selected_tenant == $t['id'] || ($form_data['tenant_id'] ?? 0) == $t['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($t['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="role_id">Role <span class="required">*</span></label>
                        <select name="role_id" id="role_id" required>
                            <option value="">Select Role</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>" <?php echo ($form_data['role_id'] ?? 0) == $role['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($role['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="status">
                            <option value="active" <?php echo ($form_data['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="pending" <?php echo ($form_data['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="suspended" <?php echo ($form_data['status'] ?? '') === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                        <div class="help-text">Active users can login immediately.</div>
                    </div>

                    <div class="form-group full-width">
                        <div class="checkbox-group">
                            <input type="checkbox" name="two_factor_enabled" id="twoFactor" value="1" <?php echo isset($form_data['two_factor_enabled']) && $form_data['two_factor_enabled'] ? 'checked' : ''; ?>>
                            <label for="twoFactor">
                                <i class="fas fa-shield-alt"></i> Enable Two-Factor Authentication (2FA)
                            </label>
                        </div>
                        <div class="help-text">Users with 2FA enabled will need to verify their identity using a code sent to their email.</div>
                    </div>

                    <!-- Personal Information -->
                    <div class="form-section-title">
                        <i class="fas fa-user"></i> Personal Information
                    </div>
                    
                    <div class="form-group">
                        <label for="first_name">First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" id="first_name" placeholder="John" value="<?php echo htmlspecialchars($form_data['first_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" id="last_name" placeholder="Doe" value="<?php echo htmlspecialchars($form_data['last_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" name="email" id="email" placeholder="user@organization.ng" value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" required>
                        <div class="help-text">This will be the user's login email.</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" name="phone" id="phone" placeholder="+234 800 555 5555" value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select name="gender" id="gender">
                            <option value="">Select Gender</option>
                            <option value="male" <?php echo ($form_data['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo ($form_data['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo ($form_data['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                            <option value="prefer_not_say" <?php echo ($form_data['gender'] ?? '') === 'prefer_not_say' ? 'selected' : ''; ?>>Prefer not to say</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth</label>
                        <input type="date" name="date_of_birth" id="date_of_birth" value="<?php echo htmlspecialchars($form_data['date_of_birth'] ?? ''); ?>">
                    </div>

                    <!-- Security -->
                    <div class="form-section-title">
                        <i class="fas fa-lock"></i> Security
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="password">Password <span class="required">*</span></label>
                        <input type="password" name="password" id="password" placeholder="Min 8 characters" required>
                        <div class="help-text">
                            <i class="fas fa-info-circle"></i> Password must be at least 8 characters long.
                            The user will receive this password via email.
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check-circle"></i> Create User
                    </button>
                    <a href="<?php echo $pre_selected_tenant > 0 ? 'tenants-users.php?id=' . $pre_selected_tenant : 'users.php'; ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
// ============================================================
// PRELOADER
// ============================================================
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() {
            preloader.style.display = 'none';
        }, 600);
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
// FORM VALIDATION
// ============================================================
document.getElementById('userForm').addEventListener('submit', function(e) {
    var password = document.getElementById('password');
    var email = document.getElementById('email');
    var firstName = document.getElementById('first_name');
    var lastName = document.getElementById('last_name');
    var tenant = document.getElementById('tenant_id');
    var role = document.getElementById('role_id');
    var isValid = true;
    
    // Remove previous error states
    document.querySelectorAll('.error').forEach(function(el) {
        el.classList.remove('error');
    });
    
    // Validate tenant
    if (!tenant.value) {
        tenant.classList.add('error');
        isValid = false;
    }
    
    // Validate first name
    if (!firstName.value.trim()) {
        firstName.classList.add('error');
        isValid = false;
    }
    
    // Validate last name
    if (!lastName.value.trim()) {
        lastName.classList.add('error');
        isValid = false;
    }
    
    // Validate email
    if (!email.value.trim() || !email.value.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
        email.classList.add('error');
        isValid = false;
    }
    
    // Validate role
    if (!role.value) {
        role.classList.add('error');
        isValid = false;
    }
    
    // Validate password
    if (!password.value || password.value.length < 8) {
        password.classList.add('error');
        isValid = false;
    }
    
    if (!isValid) {
        e.preventDefault();
        // Scroll to first error
        var firstError = document.querySelector('.error');
        if (firstError) {
            firstError.focus();
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
});

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