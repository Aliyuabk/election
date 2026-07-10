<?php
// ============================================================
// STATE COORDINATOR - PROFILE
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Start session
SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

// Only state coordinator can access
if (SessionManager::get('role_level') !== 'state') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'State Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$state_id = SessionManager::get('state_id');

// If state_id is not set in session, try to get it from user record
if (empty($state_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT state_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['state_id'])) {
            $state_id = $user['state_id'];
            SessionManager::set('state_id', $state_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching state_id: " . $e->getMessage());
    }
}

$db = getDB();

// ============================================================
// GENERATE CSRF TOKEN
// ============================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ============================================================
// FETCH USER PROFILE
// ============================================================
$profile = null;
$state_name = 'Unknown State';
$lga_name = 'N/A';

try {
    $stmt = $db->prepare("
        SELECT 
            u.*,
            r.name as role_name,
            r.level as role_level,
            s.name as state_name,
            l.name as lga_name
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN states s ON u.state_id = s.id
        LEFT JOIN lgas l ON u.lga_id = l.id
        WHERE u.id = ? AND u.deleted_at IS NULL
    ");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($profile) {
        $state_name = $profile['state_name'] ?? 'Unknown State';
        $lga_name = $profile['lga_name'] ?? 'N/A';
    }
} catch (Exception $e) {
    error_log("Error fetching profile: " . $e->getMessage());
}

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Security validation failed. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'profile') {
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $gender = $_POST['gender'] ?? '';
            $date_of_birth = $_POST['date_of_birth'] ?? null;
            
            $errors = [];
            
            if (empty($first_name)) {
                $errors[] = 'First name is required.';
            }
            if (empty($last_name)) {
                $errors[] = 'Last name is required.';
            }
            if (empty($email)) {
                $errors[] = 'Email is required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email address.';
            }
            
            if (empty($errors)) {
                try {
                    $stmt = $db->prepare("
                        UPDATE users 
                        SET first_name = ?, last_name = ?, email = ?, phone = ?,
                            gender = ?, date_of_birth = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$first_name, $last_name, $email, $phone, $gender, $date_of_birth, $user_id]);
                    
                    // Update session
                    SessionManager::set('user_name', $first_name . ' ' . $last_name);
                    SessionManager::set('user_email', $email);
                    
                    $success = 'Profile updated successfully!';
                    
                    // Refresh profile data
                    $stmt = $db->prepare("
                        SELECT 
                            u.*,
                            r.name as role_name,
                            r.level as role_level,
                            s.name as state_name,
                            l.name as lga_name
                        FROM users u
                        JOIN roles r ON u.role_id = r.id
                        LEFT JOIN states s ON u.state_id = s.id
                        LEFT JOIN lgas l ON u.lga_id = l.id
                        WHERE u.id = ? AND u.deleted_at IS NULL
                    ");
                    $stmt->execute([$user_id]);
                    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    logActivity($user_id, 'profile_updated', 'Updated profile information');
                    
                } catch (Exception $e) {
                    $error = 'Error updating profile: ' . $e->getMessage();
                }
            } else {
                $error = implode('<br>', $errors);
            }
        }
    }
}

include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
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
    margin: 0;
}
.page-header h2 small {
    font-size: 0.8rem;
    font-weight: 400;
    color: var(--gray-500);
    display: block;
    margin-top: 2px;
}

.btn-secondary-sm {
    padding: 8px 20px;
    background: var(--gray-100);
    color: var(--gray-700);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}
.btn-secondary-sm:hover {
    background: var(--gray-200);
}

.profile-header {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
    box-shadow: var(--shadow);
    margin-bottom: 20px;
}
.profile-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: 700;
    color: white;
    flex-shrink: 0;
}
.profile-avatar.blue { background: #3B82F6; }
.profile-avatar.green { background: #10B981; }
.profile-avatar.purple { background: #8B5CF6; }
.profile-avatar.orange { background: #F59E0B; }
.profile-avatar.red { background: #EF4444; }
.profile-avatar.teal { background: #0D9488; }

.profile-info h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.profile-info .subtitle {
    color: var(--gray-500);
    font-size: 0.85rem;
    margin-top: 2px;
}
.profile-info .meta {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-top: 6px;
    font-size: 0.8rem;
    color: var(--gray-500);
}
.profile-info .meta span {
    display: flex;
    align-items: center;
    gap: 4px;
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
.form-group input.error,
.form-group select.error {
    border-color: var(--danger);
    background: #FEF2F2;
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

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .profile-header {
        flex-direction: column;
        text-align: center;
    }
    .profile-info .meta {
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
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-user-circle" style="color:var(--primary);margin-right:8px;"></i>
                    My Profile
                    <small>View and manage your profile information</small>
                </h2>
            </div>
            <div>
                <a href="index.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <?php if ($profile): ?>
            <!-- Profile Header -->
            <?php 
            $avatar_colors = ['blue', 'green', 'purple', 'orange', 'red', 'teal'];
            $color_idx = ($profile['id'] ?? 0) % count($avatar_colors);
            $avatar_color = $avatar_colors[$color_idx];
            $initials = strtoupper(substr($profile['first_name'] ?? '', 0, 1) . substr($profile['last_name'] ?? '', 0, 1));
            ?>
            
            <div class="profile-header">
                <div class="profile-avatar <?php echo $avatar_color; ?>">
                    <?php echo $initials ?: '?'; ?>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']); ?></h2>
                    <div class="subtitle">
                        <i class="fas fa-user-tie"></i> 
                        <?php echo htmlspecialchars($profile['role_name'] ?? 'State Coordinator'); ?>
                    </div>
                    <div class="meta">
                        <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($profile['email'] ?? 'N/A'); ?></span>
                        <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($profile['phone'] ?? 'N/A'); ?></span>
                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($state_name); ?></span>
                        <span><i class="fas fa-code"></i> <?php echo htmlspecialchars($profile['user_code'] ?? 'N/A'); ?></span>
                        <span><i class="fas fa-calendar"></i> Joined: <?php echo date('M j, Y', strtotime($profile['created_at'])); ?></span>
                    </div>
                </div>
            </div>

            <!-- Messages -->
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

            <!-- Edit Form -->
            <div class="form-container">
                <div class="form-title">
                    <i class="fas fa-edit"></i> Edit Profile
                </div>
                <div class="form-subtitle">
                    Update your personal information.
                </div>
                
                <form method="POST" action="" id="profileForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="profile">
                    
                    <div class="form-grid">
                        <div class="form-section-title">
                            <i class="fas fa-user"></i> Personal Information
                        </div>
                        
                        <div class="form-group">
                            <label for="first_name">First Name <span class="required">*</span></label>
                            <input type="text" name="first_name" id="first_name" value="<?php echo htmlspecialchars($profile['first_name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Last Name <span class="required">*</span></label>
                            <input type="text" name="last_name" id="last_name" value="<?php echo htmlspecialchars($profile['last_name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address <span class="required">*</span></label>
                            <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>" required>
                            <div class="help-text">Changing your email will update your login credentials.</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" name="phone" id="phone" value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select name="gender" id="gender">
                                <option value="">Select Gender</option>
                                <option value="male" <?php echo ($profile['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo ($profile['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo ($profile['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                <option value="prefer_not_say" <?php echo ($profile['gender'] ?? '') === 'prefer_not_say' ? 'selected' : ''; ?>>Prefer not to say</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth</label>
                            <input type="date" name="date_of_birth" id="date_of_birth" value="<?php echo htmlspecialchars($profile['date_of_birth'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                        <a href="security.php" class="btn btn-secondary">
                            <i class="fas fa-shield-alt"></i> Security Settings
                        </a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
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
document.getElementById('profileForm').addEventListener('submit', function(e) {
    var firstName = document.getElementById('first_name');
    var lastName = document.getElementById('last_name');
    var email = document.getElementById('email');
    var isValid = true;
    
    document.querySelectorAll('.error').forEach(function(el) {
        el.classList.remove('error');
    });
    
    if (!firstName.value.trim()) {
        firstName.classList.add('error');
        isValid = false;
    }
    if (!lastName.value.trim()) {
        lastName.classList.add('error');
        isValid = false;
    }
    if (!email.value.trim() || !email.value.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
        email.classList.add('error');
        isValid = false;
    }
    
    if (!isValid) {
        e.preventDefault();
        var firstError = document.querySelector('.error');
        if (firstError) {
            firstError.focus();
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
});
</script>
</body>
</html>