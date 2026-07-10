<?php
// ============================================================
// STATE COORDINATOR - SETTINGS
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
// FETCH STATE NAME
// ============================================================
$state_name = 'Unknown State';
try {
    if (!empty($state_id)) {
        $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
        $stmt->execute([$state_id]);
        $state = $stmt->fetch(PDO::FETCH_ASSOC);
        $state_name = $state['name'] ?? 'Unknown State';
    }
} catch (Exception $e) {
    error_log("Error fetching state: " . $e->getMessage());
}

// ============================================================
// FETCH CURRENT USER SETTINGS
// ============================================================
$user_settings = [];
try {
    $stmt = $db->prepare("
        SELECT first_name, last_name, email, phone, gender, date_of_birth
        FROM users WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $user_settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching user settings: " . $e->getMessage());
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
                    
                    // Refresh data
                    $user_settings['first_name'] = $first_name;
                    $user_settings['last_name'] = $last_name;
                    $user_settings['email'] = $email;
                    $user_settings['phone'] = $phone;
                    $user_settings['gender'] = $gender;
                    $user_settings['date_of_birth'] = $date_of_birth;
                    
                    logActivity($user_id, 'profile_updated', 'Updated profile information');
                    
                } catch (Exception $e) {
                    $error = 'Error updating profile: ' . $e->getMessage();
                }
            } else {
                $error = implode('<br>', $errors);
            }
        } elseif ($action === 'password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            $errors = [];
            
            if (empty($current_password)) {
                $errors[] = 'Current password is required.';
            }
            if (empty($new_password)) {
                $errors[] = 'New password is required.';
            } elseif (strlen($new_password) < 8) {
                $errors[] = 'New password must be at least 8 characters.';
            }
            if ($new_password !== $confirm_password) {
                $errors[] = 'Passwords do not match.';
            }
            
            if (empty($errors)) {
                try {
                    // Verify current password
                    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!password_verify($current_password, $user['password_hash'])) {
                        $errors[] = 'Current password is incorrect.';
                    }
                } catch (Exception $e) {
                    $error = 'Error verifying password: ' . $e->getMessage();
                }
            }
            
            if (empty($errors) && empty($error)) {
                try {
                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$new_hash, $user_id]);
                    
                    $success = 'Password updated successfully!';
                    logActivity($user_id, 'password_change', 'Changed password');
                    
                } catch (Exception $e) {
                    $error = 'Error updating password: ' . $e->getMessage();
                }
            } else {
                $error = implode('<br>', $errors);
            }
        } elseif ($action === 'security') {
            $two_factor = isset($_POST['two_factor_enabled']) ? 1 : 0;
            
            try {
                $stmt = $db->prepare("UPDATE users SET two_factor_enabled = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$two_factor, $user_id]);
                
                $success = 'Security settings updated successfully!';
                logActivity($user_id, $two_factor ? '2fa_enabled' : '2fa_disabled', 'Two-factor authentication ' . ($two_factor ? 'enabled' : 'disabled'));
                
            } catch (Exception $e) {
                $error = 'Error updating security settings: ' . $e->getMessage();
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

.settings-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
.settings-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px;
    box-shadow: var(--shadow-sm);
}
.settings-card .card-title {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.settings-card .card-title i {
    color: var(--primary);
}
.settings-card .card-subtitle {
    color: var(--gray-500);
    font-size: 0.8rem;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--gray-100);
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-bottom: 14px;
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
    flex-shrink: 0;
}
.form-group .checkbox-group label {
    font-weight: 400;
    cursor: pointer;
    font-size: 0.85rem;
}

.btn-save {
    padding: 10px 28px;
    border-radius: 10px;
    border: none;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
    background: var(--primary);
    color: white;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-save:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.25);
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

.full-width {
    grid-column: 1 / -1;
}

@media (max-width: 992px) {
    .settings-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .settings-card {
        padding: 16px;
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
                    <i class="fas fa-cog" style="color:var(--primary);margin-right:8px;"></i>
                    Settings
                    <small><?php echo htmlspecialchars($state_name); ?> - Manage your account settings</small>
                </h2>
            </div>
            <div>
                <a href="index.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
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

        <!-- Settings Grid -->
        <div class="settings-grid">
            <!-- Profile Settings -->
            <div class="settings-card">
                <div class="card-title">
                    <i class="fas fa-user"></i> Profile Information
                </div>
                <div class="card-subtitle">Update your personal information</div>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="profile">
                    
                    <div class="form-group">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($user_settings['first_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($user_settings['last_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user_settings['email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($user_settings['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender">
                            <option value="">Select Gender</option>
                            <option value="male" <?php echo ($user_settings['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo ($user_settings['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo ($user_settings['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                            <option value="prefer_not_say" <?php echo ($user_settings['gender'] ?? '') === 'prefer_not_say' ? 'selected' : ''; ?>>Prefer not to say</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($user_settings['date_of_birth'] ?? ''); ?>">
                    </div>
                    
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </form>
            </div>

            <!-- Security Settings -->
            <div class="settings-card">
                <div class="card-title">
                    <i class="fas fa-shield-alt"></i> Security
                </div>
                <div class="card-subtitle">Manage your security settings</div>
                
                <!-- Change Password -->
                <form method="POST" action="" style="margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid var(--gray-100);">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="password">
                    
                    <h4 style="font-size:0.85rem;font-weight:600;margin-bottom:12px;">Change Password</h4>
                    
                    <div class="form-group">
                        <label>Current Password <span class="required">*</span></label>
                        <input type="password" name="current_password" placeholder="Enter current password" required>
                    </div>
                    
                    <div class="form-group">
                        <label>New Password <span class="required">*</span></label>
                        <input type="password" name="new_password" placeholder="Min 8 characters" required>
                        <div class="help-text">Password must be at least 8 characters long.</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm Password <span class="required">*</span></label>
                        <input type="password" name="confirm_password" placeholder="Confirm new password" required>
                    </div>
                    
                    <button type="submit" class="btn-save">
                        <i class="fas fa-lock"></i> Change Password
                    </button>
                </form>

                <!-- Two-Factor Authentication -->
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="security">
                    
                    <h4 style="font-size:0.85rem;font-weight:600;margin-bottom:12px;">Two-Factor Authentication</h4>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="two_factor_enabled" id="twoFactor" value="1" <?php echo (SessionManager::get('two_factor_enabled') ?? 0) ? 'checked' : ''; ?>>
                            <label for="twoFactor">
                                <i class="fas fa-shield-alt"></i> Enable Two-Factor Authentication (2FA)
                            </label>
                        </div>
                        <div class="help-text">When enabled, you'll need to enter a verification code sent to your email when logging in.</div>
                    </div>
                    
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i> Update Security Settings
                    </button>
                </form>
            </div>

            <!-- State Information -->
            <div class="settings-card full-width">
                <div class="card-title">
                    <i class="fas fa-info-circle"></i> Account Information
                </div>
                <div class="card-subtitle">Your account details and role information</div>
                
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
                    <div>
                        <div style="font-size:0.7rem;color:var(--gray-400);">Role</div>
                        <div style="font-weight:600;">State Coordinator</div>
                    </div>
                    <div>
                        <div style="font-size:0.7rem;color:var(--gray-400);">State</div>
                        <div style="font-weight:600;"><?php echo htmlspecialchars($state_name); ?></div>
                    </div>
                    <div>
                        <div style="font-size:0.7rem;color:var(--gray-400);">User ID</div>
                        <div style="font-weight:600;">#<?php echo $user_id; ?></div>
                    </div>
                    <div>
                        <div style="font-size:0.7rem;color:var(--gray-400);">Tenant</div>
                        <div style="font-weight:600;"><?php echo htmlspecialchars(SessionManager::get('tenant_name') ?? 'N/A'); ?></div>
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
</script>
</body>
</html>