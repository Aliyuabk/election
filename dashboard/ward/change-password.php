<?php
// ============================================================
// WARD COORDINATOR - CHANGE PASSWORD
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

// Only Ward coordinator can access
if (SessionManager::get('role_level') !== 'ward') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$ward_id = SessionManager::get('ward_id');
$tenant_id = SessionManager::get('tenant_id');

// If ward_id is not set in session, try to get it from user record
if (empty($ward_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT ward_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['ward_id'])) {
            $ward_id = $user['ward_id'];
            SessionManager::set('ward_id', $ward_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching ward_id: " . $e->getMessage());
    }
}

$db = getDB();

// ============================================================
// FETCH WARD NAME
// ============================================================
$ward_name = 'Ward';
try {
    if ($ward_id) {
        $stmt = $db->prepare("SELECT name FROM wards WHERE id = ?");
        $stmt->execute([$ward_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $ward_name = $result['name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching ward name: " . $e->getMessage());
}

// ============================================================
// HANDLE PASSWORD CHANGE
// ============================================================
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "Please fill in all password fields.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error_message = "Password must be at least 8 characters.";
    } elseif ($new_password === $current_password) {
        $error_message = "New password must be different from current password.";
    } else {
        // Verify current password
        try {
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && verifyPassword($current_password, $user['password_hash'])) {
                $new_hash = hashPassword($new_password);
                $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$new_hash, $user_id]);
                
                logActivity($user_id, 'password_change', "Password changed successfully", 'user', $user_id);
                $success_message = "Password changed successfully! You will be redirected to login.";
                
                // Clear session and redirect to login after 3 seconds
                SessionManager::destroy();
                header('Refresh: 3; URL=../../auth/login.php');
                
            } else {
                $error_message = "Current password is incorrect.";
                logSecurityEvent($user_id, 'password_change_failed', "Failed password change attempt", 10);
            }
        } catch (Exception $e) {
            $error_message = "Error changing password: " . $e->getMessage();
            error_log("Password change error: " . $e->getMessage());
        }
    }
}

$page_title = 'Change Password';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.password-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.password-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.password-header h2 i {
    color: var(--primary);
}

.password-form {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px;
    max-width: 500px;
}
.password-form .form-group {
    margin-bottom: 16px;
}
.password-form .form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 4px;
}
.password-form .form-group label .required {
    color: #EF4444;
}
.password-form .form-group input[type="password"] {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    background: white;
}
.password-form .form-group .helper {
    font-size: 0.7rem;
    color: var(--gray-400);
    margin-top: 4px;
}
.password-form .form-group .password-strength {
    height: 4px;
    background: var(--gray-200);
    border-radius: 2px;
    margin-top: 6px;
    overflow: hidden;
}
.password-form .form-group .password-strength .bar {
    height: 100%;
    width: 0%;
    border-radius: 2px;
    transition: width 0.3s ease;
}
.password-form .form-group .password-strength .bar.weak { background: #EF4444; width: 25%; }
.password-form .form-group .password-strength .bar.fair { background: #F59E0B; width: 50%; }
.password-form .form-group .password-strength .bar.good { background: #3B82F6; width: 75%; }
.password-form .form-group .password-strength .bar.strong { background: #10B981; width: 100%; }

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid var(--gray-200);
}

.alert {
    padding: 12px 16px;
    border-radius: var(--radius);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-success {
    background: #ECFDF5;
    border: 1px solid #D1FAE5;
    color: #065F46;
}
.alert-danger {
    background: #FEF2F2;
    border: 1px solid #FEE2E2;
    color: #991B1B;
}
.alert i {
    font-size: 1.1rem;
}

.success-redirect {
    text-align: center;
    padding: 20px;
    background: #ECFDF5;
    border-radius: var(--radius);
    border: 1px solid #D1FAE5;
}
.success-redirect .icon {
    font-size: 3rem;
    color: #10B981;
    margin-bottom: 12px;
}
.success-redirect h3 {
    color: #065F46;
    margin: 0 0 8px;
}
.success-redirect p {
    color: #065F46;
    margin: 0;
}

@media (max-width: 768px) {
    .password-form {
        max-width: 100%;
    }
    .form-actions {
        flex-direction: column;
    }
    .form-actions button,
    .form-actions a {
        width: 100%;
        text-align: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="password-header">
            <div>
                <h2><i class="fas fa-key"></i> Change Password</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward • <?php echo htmlspecialchars($user_name); ?>
                </p>
            </div>
            <div>
                <a href="profile.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Profile
                </a>
            </div>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="success-redirect">
                <div class="icon"><i class="fas fa-check-circle"></i></div>
                <h3>Password Changed Successfully!</h3>
                <p><?php echo htmlspecialchars($success_message); ?></p>
                <p style="font-size:0.8rem;color:var(--gray-500);margin-top:8px;">
                    <i class="fas fa-spinner fa-spin"></i> Redirecting to login...
                </p>
            </div>
        <?php else: ?>
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Password Form -->
            <div class="password-form">
                <form method="POST" action="" id="passwordForm">
                    <div class="form-group">
                        <label>Current Password <span class="required">*</span></label>
                        <input type="password" name="current_password" id="current_password" required autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label>New Password <span class="required">*</span></label>
                        <input type="password" name="new_password" id="new_password" required minlength="8" onkeyup="checkPasswordStrength(this.value)">
                        <div class="password-strength">
                            <div class="bar" id="strengthBar"></div>
                        </div>
                        <div class="helper">Password must be at least 8 characters</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm New Password <span class="required">*</span></label>
                        <input type="password" name="confirm_password" id="confirm_password" required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                        <a href="profile.php" class="btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
// Password strength checker
function checkPasswordStrength(password) {
    const bar = document.getElementById('strengthBar');
    let strength = 0;
    
    // Length check
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    
    // Character type checks
    if (/[a-z]/.test(password)) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^a-zA-Z0-9]/.test(password)) strength++;
    
    // Determine strength level
    let level = 'weak';
    let percentage = 0;
    
    if (strength <= 2) {
        level = 'weak';
        percentage = 25;
    } else if (strength <= 4) {
        level = 'fair';
        percentage = 50;
    } else if (strength <= 6) {
        level = 'good';
        percentage = 75;
    } else {
        level = 'strong';
        percentage = 100;
    }
    
    bar.className = 'bar ' + level;
    bar.style.width = percentage + '%';
}

// Validate form
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('New passwords do not match.');
        return false;
    }
    
    if (newPassword.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters.');
        return false;
    }
    
    return true;
});

// Preloader
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});

// Sidebar toggle
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

// Sidebar dropdowns
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

// Profile dropdown
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