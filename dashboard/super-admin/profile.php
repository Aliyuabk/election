<?php
// ============================================================
// USER PROFILE - SUPER ADMINISTRATOR (FIXED)
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

// Get user info from session
$user_id = SessionManager::get('user_id');
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');

// ============================================================
// FETCH USER DETAILS
// ============================================================
$user = null;
try {
    $stmt = $db->prepare("
        SELECT u.*, r.name as role_name, r.level as role_level, t.name as tenant_name
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

// ============================================================
// HANDLE PROFILE UPDATE
// ============================================================
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_profile':
                $first_name = trim($_POST['first_name'] ?? '');
                $last_name = trim($_POST['last_name'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $gender = $_POST['gender'] ?? '';
                $date_of_birth = $_POST['date_of_birth'] ?? '';
                $address = trim($_POST['address'] ?? '');
                
                // Validate
                if (empty($first_name) || empty($last_name)) {
                    throw new Exception('First name and last name are required.');
                }
                
                // Validate gender
                $valid_genders = ['male', 'female', 'other', 'prefer_not_say'];
                if (!empty($gender) && !in_array($gender, $valid_genders)) {
                    $gender = '';
                }
                $gender_value = !empty($gender) ? $gender : null;
                
                // FIX: Handle date_of_birth - convert empty string to NULL
                $dob_value = !empty($date_of_birth) ? $date_of_birth : null;
                
                // Update user
                $stmt = $db->prepare("
                    UPDATE users SET 
                        first_name = ?, 
                        last_name = ?, 
                        phone = ?, 
                        gender = ?, 
                        date_of_birth = ?, 
                        residential_address = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$first_name, $last_name, $phone, $gender_value, $dob_value, $address, $user_id]);
                
                // Update session name
                SessionManager::set('user_name', $first_name . ' ' . $last_name);
                
                // Log activity
                logActivity($user_id, 'profile_updated', 'Profile information updated');
                
                $success = "Profile updated successfully!";
                
                // Refresh user data
                $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                break;
                
            case 'update_avatar':
                if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Please select an image to upload.');
                }
                
                $file = $_FILES['avatar'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (!in_array($ext, $allowed)) {
                    throw new Exception('Invalid file type. Allowed: JPG, PNG, GIF, WEBP');
                }
                
                // Check file size (max 5MB)
                if ($file['size'] > 5 * 1024 * 1024) {
                    throw new Exception('File size exceeds 5MB limit.');
                }
                
                $upload_dir = '../../uploads/avatars/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $avatar_url = '/uploads/avatars/' . $filename;
                    
                    // Delete old avatar if exists
                    if (!empty($user['photograph_url'])) {
                        $old_file = '../../' . $user['photograph_url'];
                        if (file_exists($old_file)) {
                            unlink($old_file);
                        }
                    }
                    
                    $stmt = $db->prepare("UPDATE users SET photograph_url = ? WHERE id = ?");
                    $stmt->execute([$avatar_url, $user_id]);
                    
                    logActivity($user_id, 'avatar_updated', 'Profile avatar updated');
                    
                    $success = "Avatar updated successfully!";
                    
                    // Refresh user data
                    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                } else {
                    throw new Exception('Failed to upload avatar.');
                }
                break;
                
            default:
                throw new Exception('Invalid action.');
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get avatar color
$avatar_colors = ['blue', 'green', 'purple', 'orange', 'red', 'pink', 'teal'];
$color_idx = ($user_id ?? 0) % count($avatar_colors);
$avatar_color = $avatar_colors[$color_idx];

// Get user initials
$user_initials = strtoupper(
    substr($user['first_name'] ?? 'A', 0, 1) . 
    substr($user['last_name'] ?? 'D', 0, 1)
);

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<!-- Rest of the HTML remains the same -->
<style>
    /* ============================================================
       PROFILE - PROFESSIONAL STYLES
       ============================================================ */
    
    /* Page Header */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 24px;
    }
    .page-header h2 {
        font-size: 1.4rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .page-header h2 i {
        color: var(--primary);
    }
    .page-header h2 small {
        font-size: 0.8rem;
        font-weight: 400;
        color: var(--gray-500);
        display: block;
        margin-top: 2px;
    }

    /* Profile Card */
    .profile-card {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        box-shadow: var(--shadow);
        overflow: hidden;
        margin-top: 4px;
    }

    /* Profile Header */
    .profile-header {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        padding: 32px 36px;
        position: relative;
    }
    .profile-header .avatar-section {
        display: flex;
        align-items: center;
        gap: 28px;
        flex-wrap: wrap;
    }
    .profile-header .avatar-wrapper {
        position: relative;
        flex-shrink: 0;
    }
    .profile-header .avatar {
        width: 110px;
        height: 110px;
        border-radius: 50%;
        border: 4px solid rgba(255, 255, 255, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.8rem;
        font-weight: 700;
        color: white;
        overflow: hidden;
        transition: var(--transition);
        position: relative;
        cursor: pointer;
    }
    .profile-header .avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .profile-header .avatar.blue { background: #3B82F6; }
    .profile-header .avatar.green { background: #10B981; }
    .profile-header .avatar.purple { background: #8B5CF6; }
    .profile-header .avatar.orange { background: #F59E0B; }
    .profile-header .avatar.red { background: #EF4444; }
    .profile-header .avatar.pink { background: #EC4899; }
    .profile-header .avatar.teal { background: #14B8A6; }
    
    .profile-header .avatar .avatar-overlay {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: var(--transition);
        border-radius: 50%;
    }
    .profile-header .avatar:hover .avatar-overlay {
        opacity: 1;
    }
    .profile-header .avatar .avatar-overlay i {
        color: white;
        font-size: 1.6rem;
    }
    .profile-header .avatar .avatar-overlay input[type="file"] {
        display: none;
    }
    
    .profile-header .user-info {
        color: white;
        flex: 1;
    }
    .profile-header .user-info h2 {
        font-size: 1.6rem;
        font-weight: 700;
        margin-bottom: 4px;
    }
    .profile-header .user-info .user-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 18px;
        font-size: 0.85rem;
        opacity: 0.9;
        margin-bottom: 8px;
    }
    .profile-header .user-info .user-meta span {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .profile-header .user-info .user-meta i {
        font-size: 0.8rem;
        opacity: 0.7;
    }
    .profile-header .user-info .role-badge {
        display: inline-block;
        background: rgba(255, 255, 255, 0.2);
        padding: 4px 16px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        backdrop-filter: blur(4px);
    }

    /* Profile Body */
    .profile-body {
        padding: 32px 36px;
    }
    .profile-body .section-title {
        font-weight: 600;
        font-size: 1rem;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--gray-100);
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--gray-700);
    }
    .profile-body .section-title i {
        color: var(--primary);
        font-size: 1.1rem;
    }
    
    /* Form */
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px 28px;
    }
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
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
        min-height: 70px;
    }
    
    /* Form Actions */
    .form-actions {
        display: flex;
        gap: 12px;
        margin-top: 28px;
        padding-top: 20px;
        border-top: 1.5px solid var(--gray-200);
        flex-wrap: wrap;
    }
    .form-actions .btn {
        padding: 10px 30px;
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
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.25);
    }
    .form-actions .btn-secondary {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .form-actions .btn-secondary:hover {
        background: var(--gray-200);
    }
    
    /* Messages */
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
    
    /* Toast Container */
    .toast-container {
        position: fixed;
        top: 80px;
        right: 20px;
        z-index: 999;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .toast {
        padding: 14px 20px;
        border-radius: 10px;
        color: white;
        font-size: 0.85rem;
        font-weight: 500;
        box-shadow: var(--shadow-hover);
        animation: slideIn 0.3s ease;
        min-width: 280px;
        max-width: 400px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .toast-success { background: var(--secondary); }
    .toast-error { background: var(--danger); }
    @keyframes slideIn {
        from { opacity: 0; transform: translateX(100px); }
        to { opacity: 1; transform: translateX(0); }
    }
    
    /* Responsive */
    @media (max-width: 992px) {
        .profile-header { padding: 24px; }
        .profile-body { padding: 24px; }
        .profile-header .avatar { width: 90px; height: 90px; font-size: 2.2rem; }
    }
    @media (max-width: 768px) {
        .page-header { flex-direction: column; align-items: flex-start; }
        .profile-header .avatar-section { flex-direction: column; align-items: center; text-align: center; }
        .profile-header .user-info { text-align: center; }
        .profile-header .user-info .user-meta { justify-content: center; }
        .profile-body { padding: 20px; }
        .form-grid { grid-template-columns: 1fr; gap: 14px; }
        .form-actions { flex-direction: column; }
        .form-actions .btn { justify-content: center; width: 100%; }
        .profile-header .avatar { width: 80px; height: 80px; font-size: 2rem; }
        .profile-header .user-info h2 { font-size: 1.3rem; }
    }
    @media (max-width: 480px) {
        .profile-header { padding: 16px; }
        .profile-body { padding: 16px; }
        .profile-header .avatar { width: 70px; height: 70px; font-size: 1.6rem; }
        .profile-header .user-info h2 { font-size: 1.1rem; }
        .profile-header .user-info .user-meta { font-size: 0.75rem; gap: 10px; }
        .form-group input, .form-group select { padding: 8px 12px; font-size: 0.8rem; }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-user-circle"></i> My Profile
                    <small>View and manage your personal information</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="profile.php" class="btn-outline" style="padding:8px 16px;border-radius:10px;border:1px solid var(--gray-200);background:transparent;color:var(--gray-600);text-decoration:none;font-size:0.82rem;font-weight:500;display:inline-flex;align-items:center;gap:6px;transition:var(--transition);">
                    <i class="fas fa-sync"></i> Refresh
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

        <!-- Profile Card -->
        <div class="profile-card">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="avatar-section">
                    <div class="avatar-wrapper">
                        <div class="avatar <?php echo $avatar_color; ?>">
                            <?php if (!empty($user['photograph_url'])): ?>
                                <img src="<?php echo htmlspecialchars($user['photograph_url']); ?>" alt="Profile Avatar">
                            <?php else: ?>
                                <?php echo $user_initials; ?>
                            <?php endif; ?>
                            <div class="avatar-overlay" onclick="document.getElementById('avatarInput').click()">
                                <i class="fas fa-camera"></i>
                                <input type="file" id="avatarInput" accept="image/*" form="avatarForm">
                            </div>
                        </div>
                    </div>
                    <div class="user-info">
                        <h2><?php echo htmlspecialchars($user['first_name'] ?? '') . ' ' . htmlspecialchars($user['last_name'] ?? ''); ?></h2>
                        <div class="user-meta">
                            <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email'] ?? ''); ?></span>
                            <span><i class="fas fa-code"></i> <?php echo htmlspecialchars($user['user_code'] ?? ''); ?></span>
                            <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($user['tenant_name'] ?? 'Global'); ?></span>
                        </div>
                        <span class="role-badge">
                            <i class="fas fa-user-shield"></i>
                            <?php echo ucfirst(str_replace('_', ' ', $user['role_level'] ?? 'super_admin')); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Profile Body -->
            <div class="profile-body">
                <!-- Hidden Avatar Upload Form -->
                <form method="POST" action="" enctype="multipart/form-data" id="avatarForm" style="display:none;">
                    <input type="hidden" name="action" value="update_avatar">
                    <input type="file" name="avatar" id="avatarInput" accept="image/*" onchange="this.form.submit()">
                </form>

                <!-- Profile Edit Form -->
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="section-title">
                        <i class="fas fa-user-edit"></i> Personal Information
                        <span style="font-size:0.7rem;font-weight:400;color:var(--gray-400);margin-left:auto;">
                            <i class="fas fa-info-circle"></i> Fields marked with <span class="required">*</span> are required
                        </span>
                    </div>
                    
                    <div class="form-grid">
                        <!-- First Name -->
                        <div class="form-group">
                            <label>First Name <span class="required">*</span></label>
                            <input type="text" name="first_name" 
                                   value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" 
                                   placeholder="Enter your first name" required>
                        </div>
                        
                        <!-- Last Name -->
                        <div class="form-group">
                            <label>Last Name <span class="required">*</span></label>
                            <input type="text" name="last_name" 
                                   value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" 
                                   placeholder="Enter your last name" required>
                        </div>
                        
                        <!-- Email (Read Only) -->
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" 
                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" 
                                   disabled>
                            <div class="help-text">
                                <i class="fas fa-info-circle"></i> 
                                Email cannot be changed here. Contact your administrator.
                            </div>
                        </div>
                        
                        <!-- Phone -->
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                                   placeholder="+234 800 555 5555">
                        </div>
                        
                        <!-- Gender -->
                        <div class="form-group">
                            <label>Gender</label>
                            <select name="gender">
                                <option value="">Select Gender</option>
                                <option value="male" <?php echo ($user['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo ($user['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo ($user['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                <option value="prefer_not_say" <?php echo ($user['gender'] ?? '') === 'prefer_not_say' ? 'selected' : ''; ?>>Prefer not to say</option>
                            </select>
                        </div>
                        
                        <!-- Date of Birth -->
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="date" name="date_of_birth" 
                                   value="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>">
                        </div>
                        
                        <!-- Address -->
                        <div class="form-group full-width">
                            <label>Residential Address</label>
                            <textarea name="address" placeholder="Enter your residential address..."><?php echo htmlspecialchars($user['residential_address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                    </div>
                </form>
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
// AVATAR UPLOAD
// ============================================================
document.getElementById('avatarInput').addEventListener('change', function() {
    if (this.files && this.files[0]) {
        // Validate file size
        if (this.files[0].size > 5 * 1024 * 1024) {
            showToast('error', 'File size exceeds 5MB limit.');
            this.value = '';
            return;
        }
        // Submit form
        this.form.submit();
    }
});

// ============================================================
// TOAST NOTIFICATIONS
// ============================================================
function showToast(type, message) {
    var container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    
    var toast = document.createElement('div');
    toast.className = 'toast toast-' + type;
    toast.innerHTML = '<i class="fas ' + (type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle') + '"></i> ' + message;
    container.appendChild(toast);
    
    setTimeout(function() {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100px)';
        setTimeout(function() {
            toast.remove();
        }, 300);
    }, 4000);
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