<?php
// ============================================================
// CLIENT ADMIN PROFILE - Organization Profile Management
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
// FETCH ORGANIZATION DETAILS
// ============================================================
$organization = null;
try {
    $stmt = $db->prepare("
        SELECT t.*, s.name as state_name, l.name as lga_name,
               (SELECT COUNT(*) FROM users WHERE tenant_id = t.id AND deleted_at IS NULL) as total_users
        FROM tenants t
        LEFT JOIN states s ON t.state_id = s.id
        LEFT JOIN lgas l ON t.lga_id = l.id
        WHERE t.id = ? AND t.deleted_at IS NULL
    ");
    $stmt->execute([$tenant_id]);
    $organization = $stmt->fetch();
} catch (Exception $e) {
    // Continue
}

// ============================================================
// FETCH ADMIN USER
// ============================================================
$admin_user = null;
try {
    $stmt = $db->prepare("
        SELECT u.*, r.name as role_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.tenant_id = ? AND u.role_id IN (SELECT id FROM roles WHERE level = 'client_admin')
        LIMIT 1
    ");
    $stmt->execute([$tenant_id]);
    $admin_user = $stmt->fetch();
} catch (Exception $e) {
    // Continue
}

// ============================================================
// HANDLE PROFILE UPDATE
// ============================================================
$error = '';
$success = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_organization':
                $name = trim($_POST['name'] ?? '');
                $type = $_POST['type'] ?? 'political_party';
                $contact_email = trim($_POST['contact_email'] ?? '');
                $contact_phone = trim($_POST['contact_phone'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $state_id = !empty($_POST['state_id']) ? (int)$_POST['state_id'] : null;
                $lga_id = !empty($_POST['lga_id']) ? (int)$_POST['lga_id'] : null;
                $primary_color = $_POST['primary_color'] ?? '#0F4C81';
                $secondary_color = $_POST['secondary_color'] ?? '#10B981';
                
                if (empty($name)) {
                    throw new Exception('Organization name is required.');
                }
                
                // Generate slug
                $slug = strtolower(preg_replace('/[^a-zA-Z0-9-]/', '-', $name));
                $slug = preg_replace('/-+/', '-', $slug);
                $slug = trim($slug, '-');
                
                $stmt = $db->prepare("
                    UPDATE tenants SET
                        name = ?,
                        slug = ?,
                        type = ?,
                        contact_email = ?,
                        contact_phone = ?,
                        address = ?,
                        state_id = ?,
                        lga_id = ?,
                        primary_color = ?,
                        secondary_color = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $name,
                    $slug,
                    $type,
                    $contact_email,
                    $contact_phone,
                    $address,
                    $state_id,
                    $lga_id,
                    $primary_color,
                    $secondary_color,
                    $tenant_id
                ]);
                
                logActivity($user_id, 'organization_updated', "Updated organization: $name");
                
                $success = "Organization profile updated successfully!";
                $message_type = 'success';
                
                // Refresh organization data
                $stmt = $db->prepare("
                    SELECT t.*, s.name as state_name, l.name as lga_name
                    FROM tenants t
                    LEFT JOIN states s ON t.state_id = s.id
                    LEFT JOIN lgas l ON t.lga_id = l.id
                    WHERE t.id = ?
                ");
                $stmt->execute([$tenant_id]);
                $organization = $stmt->fetch();
                break;
                
            case 'update_admin':
                $first_name = trim($_POST['first_name'] ?? '');
                $last_name = trim($_POST['last_name'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $gender = $_POST['gender'] ?? '';
                $date_of_birth = $_POST['date_of_birth'] ?? null;
                $password = $_POST['password'] ?? '';
                
                if (empty($first_name) || empty($last_name)) {
                    throw new Exception('First name and last name are required.');
                }
                
                $valid_genders = ['male', 'female', 'other', 'prefer_not_say'];
                if (!empty($gender) && !in_array($gender, $valid_genders)) {
                    $gender = '';
                }
                $gender_value = !empty($gender) ? $gender : null;
                $dob_value = !empty($date_of_birth) ? $date_of_birth : null;
                
                $update_fields = [];
                $params = [];
                
                $update_fields[] = "first_name = ?";
                $params[] = $first_name;
                
                $update_fields[] = "last_name = ?";
                $params[] = $last_name;
                
                $update_fields[] = "phone = ?";
                $params[] = $phone;
                
                $update_fields[] = "gender = ?";
                $params[] = $gender_value;
                
                $update_fields[] = "date_of_birth = ?";
                $params[] = $dob_value;
                
                if (!empty($password) && strlen($password) >= 8) {
                    $update_fields[] = "password_hash = ?";
                    $params[] = password_hash($password, PASSWORD_DEFAULT);
                }
                
                $update_fields[] = "updated_at = NOW()";
                $params[] = $user_id;
                
                $sql = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = ? AND tenant_id = ?";
                $params[] = $tenant_id;
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                
                // Update session name
                SessionManager::set('user_name', $first_name . ' ' . $last_name);
                
                logActivity($user_id, 'admin_profile_updated', "Updated admin profile");
                
                $success = "Admin profile updated successfully!";
                $message_type = 'success';
                
                // Refresh admin data
                $stmt = $db->prepare("
                    SELECT u.*, r.name as role_name
                    FROM users u
                    LEFT JOIN roles r ON u.role_id = r.id
                    WHERE u.id = ? AND u.deleted_at IS NULL
                ");
                $stmt->execute([$user_id]);
                $admin_user = $stmt->fetch();
                break;
                
            case 'upload_logo':
                if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Please select an image to upload.');
                }
                
                $file = $_FILES['logo'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
                
                if (!in_array($ext, $allowed)) {
                    throw new Exception('Invalid file type. Allowed: JPG, PNG, GIF, SVG, WEBP');
                }
                
                if ($file['size'] > 2 * 1024 * 1024) {
                    throw new Exception('File size exceeds 2MB limit.');
                }
                
                $upload_dir = '../../uploads/tenants/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $filename = 'tenant_' . $tenant_id . '_' . time() . '.' . $ext;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $logo_url = '/uploads/tenants/' . $filename;
                    
                    // Delete old logo if exists
                    if (!empty($organization['logo_url'])) {
                        $old_file = '../../' . $organization['logo_url'];
                        if (file_exists($old_file)) {
                            unlink($old_file);
                        }
                    }
                    
                    $stmt = $db->prepare("UPDATE tenants SET logo_url = ? WHERE id = ?");
                    $stmt->execute([$logo_url, $tenant_id]);
                    
                    logActivity($user_id, 'organization_logo_updated', "Updated organization logo");
                    
                    $success = "Logo uploaded successfully!";
                    $message_type = 'success';
                    
                    // Refresh organization data
                    $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
                    $stmt->execute([$tenant_id]);
                    $organization = $stmt->fetch();
                } else {
                    throw new Exception('Failed to upload logo.');
                }
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        $message_type = 'error';
    }
}

// ============================================================
// FETCH STATES FOR DROPDOWN
// ============================================================
$states = [];
try {
    $stmt = $db->query("SELECT id, name FROM states WHERE is_active = 1 ORDER BY name");
    $states = $stmt->fetchAll();
} catch (Exception $e) {}

// Get LGAs for current state
$lgas = [];
if ($organization && $organization['state_id']) {
    try {
        $stmt = $db->prepare("SELECT id, name FROM lgas WHERE state_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$organization['state_id']]);
        $lgas = $stmt->fetchAll();
    } catch (Exception $e) {}
}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       PROFILE - CLIENT ADMIN STYLES
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
    
    .profile-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
    }
    .profile-card {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 24px 28px;
        box-shadow: var(--shadow);
        margin-bottom: 20px;
    }
    .profile-card .card-title {
        font-weight: 600;
        font-size: 1rem;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--gray-100);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .profile-card .card-title i {
        color: var(--primary);
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
    .form-group select:focus,
    .form-group textarea:focus {
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
    }
    
    .form-actions {
        display: flex;
        gap: 12px;
        margin-top: 20px;
        padding-top: 16px;
        border-top: 1px solid var(--gray-200);
        flex-wrap: wrap;
    }
    .form-actions .btn {
        padding: 10px 24px;
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
    
    /* Logo Section */
    .logo-section {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 16px;
        padding: 20px;
        background: var(--gray-50);
        border-radius: 12px;
        border: 2px dashed var(--gray-200);
        transition: var(--transition);
    }
    .logo-section:hover {
        border-color: var(--primary);
        background: #EFF6FF;
    }
    .logo-section .logo-preview {
        width: 120px;
        height: 120px;
        border-radius: 16px;
        background: white;
        border: 2px solid var(--gray-200);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--primary);
        overflow: hidden;
    }
    .logo-section .logo-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .logo-section .logo-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .logo-section .logo-actions .btn-sm {
        padding: 6px 16px;
        border-radius: 8px;
        border: none;
        font-weight: 500;
        font-size: 0.78rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .logo-section .logo-actions .btn-sm.primary {
        background: var(--primary);
        color: white;
    }
    .logo-section .logo-actions .btn-sm.primary:hover {
        background: var(--primary-dark);
    }
    .logo-section .logo-actions .btn-sm.danger {
        background: #FEF2F2;
        color: var(--danger);
    }
    .logo-section .logo-actions .btn-sm.danger:hover {
        background: #FEE2E2;
    }
    
    /* Organization Stats */
    .org-stats {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-top: 12px;
    }
    .org-stat {
        background: var(--gray-50);
        border-radius: 10px;
        padding: 12px 16px;
        text-align: center;
    }
    .org-stat .number {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--primary);
    }
    .org-stat .label {
        font-size: 0.65rem;
        color: var(--gray-500);
    }
    
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 4px;
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
    .badge-status.trial { background: #FFFBEB; color: #92400E; }
    .badge-status.trial .dot { background: #F59E0B; }
    .badge-status.expired { background: #FEF2F2; color: #991B1B; }
    .badge-status.expired .dot { background: #EF4444; }
    
    .badge-plan {
        display: inline-block;
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .badge-plan.free { background: var(--gray-100); color: var(--gray-500); }
    .badge-plan.basic { background: #FFFBEB; color: #92400E; }
    .badge-plan.standard { background: #ECFDF5; color: #065F46; }
    .badge-plan.premium { background: #EFF6FF; color: #1E40AF; }
    .badge-plan.enterprise { background: #F5F3FF; color: #5B21B6; }
    
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
    
    @media (max-width: 992px) {
        .profile-grid {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        .profile-card {
            padding: 16px;
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
        .org-stats {
            grid-template-columns: 1fr 1fr;
        }
        .logo-section .logo-preview {
            width: 80px;
            height: 80px;
            font-size: 1.8rem;
        }
    }
    @media (max-width: 480px) {
        .profile-card {
            padding: 12px;
        }
        .form-group input,
        .form-group select {
            padding: 8px 12px;
            font-size: 0.8rem;
        }
        .org-stats {
            grid-template-columns: 1fr 1fr;
            gap: 6px;
        }
        .org-stat {
            padding: 8px 10px;
        }
        .org-stat .number {
            font-size: 1rem;
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
                    <i class="fas fa-building" style="color:var(--primary);margin-right:8px;"></i> Organization Profile
                    <small>Manage your organization settings and profile</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="settings.php" class="btn-outline">
                    <i class="fas fa-cog"></i> Settings
                </a>
                <a href="index.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo htmlspecialchars($error); ?></div>
            </div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div><?php echo htmlspecialchars($success); ?></div>
            </div>
        <?php endif; ?>

        <!-- Profile Grid -->
        <div class="profile-grid">
            <!-- Left Column -->
            <div>
                <!-- Organization Information -->
                <div class="profile-card">
                    <div class="card-title">
                        <i class="fas fa-building"></i> Organization Information
                    </div>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_organization">
                        
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label>Organization Name <span class="required">*</span></label>
                                <input type="text" name="name" value="<?php echo htmlspecialchars($organization['name'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Organization Type</label>
                                <select name="type">
                                    <option value="political_party" <?php echo ($organization['type'] ?? '') === 'political_party' ? 'selected' : ''; ?>>Political Party</option>
                                    <option value="candidate" <?php echo ($organization['type'] ?? '') === 'candidate' ? 'selected' : ''; ?>>Candidate</option>
                                    <option value="ngo" <?php echo ($organization['type'] ?? '') === 'ngo' ? 'selected' : ''; ?>>NGO</option>
                                    <option value="observer_group" <?php echo ($organization['type'] ?? '') === 'observer_group' ? 'selected' : ''; ?>>Observer Group</option>
                                    <option value="cso" <?php echo ($organization['type'] ?? '') === 'cso' ? 'selected' : ''; ?>>CSO</option>
                                    <option value="research_institution" <?php echo ($organization['type'] ?? '') === 'research_institution' ? 'selected' : ''; ?>>Research Institution</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Contact Email</label>
                                <input type="email" name="contact_email" value="<?php echo htmlspecialchars($organization['contact_email'] ?? ''); ?>" placeholder="contact@organization.ng">
                            </div>
                            
                            <div class="form-group">
                                <label>Contact Phone</label>
                                <input type="tel" name="contact_phone" value="<?php echo htmlspecialchars($organization['contact_phone'] ?? ''); ?>" placeholder="+234 800 555 5555">
                            </div>
                            
                            <div class="form-group">
                                <label>State</label>
                                <select name="state_id" id="stateSelect">
                                    <option value="">Select State</option>
                                    <?php foreach ($states as $state): ?>
                                        <option value="<?php echo $state['id']; ?>" <?php echo ($organization['state_id'] ?? '') == $state['id'] ? 'selected' : ''; ?>>
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
                                        <option value="<?php echo $lga['id']; ?>" <?php echo ($organization['lga_id'] ?? '') == $lga['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($lga['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group full-width">
                                <label>Address</label>
                                <textarea name="address" placeholder="Organization address"><?php echo htmlspecialchars($organization['address'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Primary Color</label>
                                <div class="color-input">
                                    <input type="color" name="primary_color" id="primaryColor" value="<?php echo htmlspecialchars($organization['primary_color'] ?? '#0F4C81'); ?>">
                                    <input type="text" name="primary_color_text" placeholder="#0F4C81" value="<?php echo htmlspecialchars($organization['primary_color'] ?? '#0F4C81'); ?>" oninput="document.getElementById('primaryColor').value=this.value">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Secondary Color</label>
                                <div class="color-input">
                                    <input type="color" name="secondary_color" id="secondaryColor" value="<?php echo htmlspecialchars($organization['secondary_color'] ?? '#10B981'); ?>">
                                    <input type="text" name="secondary_color_text" placeholder="#10B981" value="<?php echo htmlspecialchars($organization['secondary_color'] ?? '#10B981'); ?>" oninput="document.getElementById('secondaryColor').value=this.value">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Organization
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Admin Profile -->
                <div class="profile-card">
                    <div class="card-title">
                        <i class="fas fa-user-cog"></i> Administrator Profile
                    </div>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_admin">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>First Name <span class="required">*</span></label>
                                <input type="text" name="first_name" value="<?php echo htmlspecialchars($admin_user['first_name'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Last Name <span class="required">*</span></label>
                                <input type="text" name="last_name" value="<?php echo htmlspecialchars($admin_user['last_name'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" value="<?php echo htmlspecialchars($admin_user['email'] ?? ''); ?>" disabled>
                                <div class="help-text">Email cannot be changed. Contact super admin.</div>
                            </div>
                            
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="tel" name="phone" value="<?php echo htmlspecialchars($admin_user['phone'] ?? ''); ?>" placeholder="+234 800 555 5555">
                            </div>
                            
                            <div class="form-group">
                                <label>Gender</label>
                                <select name="gender">
                                    <option value="">Select Gender</option>
                                    <option value="male" <?php echo ($admin_user['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo ($admin_user['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo ($admin_user['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                    <option value="prefer_not_say" <?php echo ($admin_user['gender'] ?? '') === 'prefer_not_say' ? 'selected' : ''; ?>>Prefer not to say</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Date of Birth</label>
                                <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($admin_user['date_of_birth'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group full-width">
                                <label>New Password</label>
                                <input type="password" name="password" placeholder="Leave blank to keep current password" minlength="8">
                                <div class="help-text">Enter a new password only if you want to change it. Min 8 characters.</div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Organization Logo -->
                <div class="profile-card">
                    <div class="card-title">
                        <i class="fas fa-image"></i> Organization Logo
                    </div>
                    
                    <div class="logo-section">
                        <div class="logo-preview">
                            <?php if (!empty($organization['logo_url'])): ?>
                                <img src="<?php echo htmlspecialchars($organization['logo_url']); ?>" alt="<?php echo htmlspecialchars($organization['name'] ?? 'Logo'); ?>">
                            <?php else: ?>
                                <?php echo strtoupper(substr($organization['name'] ?? 'O', 0, 2)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="logo-actions">
                            <form method="POST" action="" enctype="multipart/form-data" style="display:inline;">
                                <input type="hidden" name="action" value="upload_logo">
                                <label class="btn-sm primary" style="cursor:pointer;">
                                    <i class="fas fa-upload"></i> Upload Logo
                                    <input type="file" name="logo" accept="image/*" style="display:none;" onchange="this.form.submit()">
                                </label>
                            </form>
                            <?php if (!empty($organization['logo_url'])): ?>
                                <form method="POST" action="" style="display:inline;">
                                    <input type="hidden" name="action" value="remove_logo">
                                    <button type="submit" class="btn-sm danger" onclick="return confirm('Remove logo?')">
                                        <i class="fas fa-trash"></i> Remove
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:0.7rem;color:var(--gray-400);">
                            Recommended: Square image, max 2MB
                        </div>
                    </div>
                </div>

                <!-- Organization Stats -->
                <div class="profile-card">
                    <div class="card-title">
                        <i class="fas fa-chart-simple"></i> Organization Stats
                    </div>
                    
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--gray-100);font-size:0.85rem;">
                            <span style="color:var(--gray-500);">Subscription Plan</span>
                            <span class="badge-plan <?php echo $organization['subscription_plan'] ?? 'free'; ?>">
                                <?php echo ucfirst($organization['subscription_plan'] ?? 'Free'); ?>
                            </span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--gray-100);font-size:0.85rem;">
                            <span style="color:var(--gray-500);">Subscription Status</span>
                            <span class="badge-status <?php echo $organization['subscription_status'] ?? 'trial'; ?>">
                                <span class="dot"></span>
                                <?php echo ucfirst($organization['subscription_status'] ?? 'Trial'); ?>
                            </span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--gray-100);font-size:0.85rem;">
                            <span style="color:var(--gray-500);">Expiry Date</span>
                            <span style="font-weight:500;">
                                <?php echo !empty($organization['subscription_end']) ? date('M j, Y', strtotime($organization['subscription_end'])) : 'N/A'; ?>
                            </span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--gray-100);font-size:0.85rem;">
                            <span style="color:var(--gray-500);">Total Users</span>
                            <span style="font-weight:500;"><?php echo number_format($organization['total_users'] ?? 0); ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:0.85rem;">
                            <span style="color:var(--gray-500);">Organization Type</span>
                            <span style="font-weight:500;"><?php echo ucfirst(str_replace('_', ' ', $organization['type'] ?? 'N/A')); ?></span>
                        </div>
                    </div>
                    
                    <div class="org-stats">
                        <div class="org-stat">
                            <div class="number"><?php echo number_format($organization['total_users'] ?? 0); ?></div>
                            <div class="label">Total Users</div>
                        </div>
                        <div class="org-stat">
                            <div class="number"><?php echo date('M j, Y', strtotime($organization['created_at'] ?? 'now')); ?></div>
                            <div class="label">Joined</div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="profile-card">
                    <div class="card-title">
                        <i class="fas fa-bolt"></i> Quick Actions
                    </div>
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        <a href="users-create.php" class="btn-primary" style="justify-content:center;width:100%;">
                            <i class="fas fa-user-plus"></i> Add User
                        </a>
                        <a href="settings.php" class="btn-outline" style="justify-content:center;width:100%;">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                        <a href="audit-logs.php" class="btn-outline" style="justify-content:center;width:100%;">
                            <i class="fas fa-clipboard-list"></i> Audit Logs
                        </a>
                    </div>
                </div>
            </div>
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
// STATE -> LGA DYNAMIC LOADING
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    var stateSelect = document.getElementById('stateSelect');
    var lgaSelect = document.getElementById('lgaSelect');
    
    if (stateSelect && lgaSelect) {
        stateSelect.addEventListener('change', function() {
            var stateId = this.value;
            lgaSelect.innerHTML = '<option value="">Loading...</option>';
            
            if (stateId) {
                fetch('../ajax/get-lgas.php?state_id=' + stateId)
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        lgaSelect.innerHTML = '<option value="">Select LGA</option>';
                        if (data.length > 0) {
                            data.forEach(function(lga) {
                                var option = document.createElement('option');
                                option.value = lga.id;
                                option.textContent = lga.name;
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
    
    // Color input sync
    document.querySelectorAll('.color-input input[type="text"]').forEach(function(textInput) {
        textInput.addEventListener('input', function() {
            var colorInput = this.closest('.color-input').querySelector('input[type="color"]');
            if (colorInput && this.value.match(/^#?[0-9a-f]{6}$/i)) {
                colorInput.value = this.value.startsWith('#') ? this.value : '#' + this.value;
            }
        });
    });
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