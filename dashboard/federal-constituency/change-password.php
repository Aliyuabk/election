<?php
// ============================================================
// FEDERAL CONSTITUENCY COORDINATOR - CHANGE PASSWORD
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'federal_constituency') {
    header('Location: ../client-admin/');
    exit();
}

$user_id = SessionManager::get('user_id');
$db = getDB();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif (strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New password and confirmation do not match.';
    } else {
        try {
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $error = 'User not found.';
            } elseif (!verifyPassword($current_password, $user['password_hash'])) {
                $error = 'Current password is incorrect.';
                logSecurityEvent($user_id, 'password_failed', 'Failed password change attempt');
            } else {
                $new_hash = hashPassword($new_password);
                $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$new_hash, $user_id]);
                
                $success = 'Password changed successfully!';
                logActivity($user_id, 'password_change', 'Password changed successfully');
                logSecurityEvent($user_id, 'password_changed', 'Password changed successfully');
            }
        } catch (Exception $e) {
            $error = 'Failed to change password. Please try again.';
            error_log("Password change error: " . $e->getMessage());
        }
    }
}

$page_title = 'Change Password';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.form-container {
    max-width: 600px;
    margin: 0 auto;
}
.form-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 30px;
}
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    font-weight: 600;
    font-size: 0.82rem;
    color: var(--gray-700);
    margin-bottom: 4px;
}
.form-group label .required {
    color: #DC2626;
}
.form-group input {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid var(--gray-200);
    border-radius: 10px;
    font-family: 'Inter', sans-serif;
    font-size: 0.85rem;
    transition: var(--transition);
    background: var(--gray-50);
    color: var(--gray-700);
}
.form-group input:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}
.form-group .password-strength {
    height: 4px;
    background: var(--gray-200);
    border-radius: 2px;
    margin-top: 8px;
    overflow: hidden;
}
.form-group .password-strength .bar {
    height: 100%;
    width: 0%;
    transition: width 0.3s ease;
    border-radius: 2px;
}
.form-group .password-strength .bar.weak { background: #EF4444; width: 25%; }
.form-group .password-strength .bar.medium { background: #F59E0B; width: 50%; }
.form-group .password-strength .bar.strong { background: #10B981; width: 75%; }
.form-group .password-strength .bar.very-strong { background: #059669; width: 100%; }
.form-group .password-hint {
    font-size: 0.7rem;
    color: var(--gray-400);
    margin-top: 4px;
}
.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid var(--gray-200);
}
.btn {
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
.btn-primary {
    background: var(--primary);
    color: white;
}
.btn-primary:hover {
    background: var(--primary-dark);
}
.btn-secondary {
    background: var(--gray-100);
    color: var(--gray-600);
}
.btn-secondary:hover {
    background: var(--gray-200);
}

.password-rules {
    background: var(--gray-50);
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 24px;
}
.password-rules ul {
    margin: 8px 0 0 0;
    padding-left: 20px;
    font-size: 0.8rem;
    color: var(--gray-600);
}
.password-rules ul li {
    margin-bottom: 4px;
}
.password-rules ul li .check {
    color: #10B981;
}

.alert {
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 0.85rem;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-success {
    background: #ECFDF5;
    color: #065F46;
    border: 1px solid #A7F3D0;
}
.alert-error {
    background: #FEF2F2;
    color: #DC2626;
    border: 1px solid #FECACA;
}

@media (max-width: 768px) {
    .form-actions {
        flex-direction: column;
    }
    .form-actions .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="form-container">
            <h2 style="font-size:1.3rem;font-weight:700;margin-bottom:4px;">
                <i class="fas fa-key" style="color:var(--primary);"></i> Change Password
            </h2>
            <p style="color:var(--gray-500);font-size:0.9rem;margin-bottom:20px;">
                Update your password to keep your account secure.
            </p>

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

            <div class="form-card">
                <div class="password-rules">
                    <strong style="font-size:0.85rem;">Password Requirements:</strong>
                    <ul>
                        <li><span class="check">✓</span> Minimum 8 characters</li>
                        <li><span class="check">✓</span> Include at least one uppercase letter</li>
                        <li><span class="check">✓</span> Include at least one lowercase letter</li>
                        <li><span class="check">✓</span> Include at least one number</li>
                        <li><span class="check">✓</span> Include at least one special character</li>
                    </ul>
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label>Current Password <span class="required">*</span></label>
                        <input type="password" name="current_password" required autofocus>
                    </div>

                    <div class="form-group">
                        <label>New Password <span class="required">*</span></label>
                        <input type="password" id="new_password" name="new_password" required>
                        <div class="password-strength">
                            <div class="bar" id="strength_bar"></div>
                        </div>
                        <div class="password-hint" id="password_hint">Enter a strong password</div>
                    </div>

                    <div class="form-group">
                        <label>Confirm New Password <span class="required">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                        <div class="password-hint" id="confirm_hint"></div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Password
                        </button>
                        <a href="profile.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
// Password strength checker
document.getElementById('new_password').addEventListener('input', function() {
    var password = this.value;
    var bar = document.getElementById('strength_bar');
    var hint = document.getElementById('password_hint');
    
    var strength = 0;
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    
    var level = 'weak';
    var message = 'Weak password';
    
    if (strength >= 6) {
        level = 'very-strong';
        message = 'Very strong password!';
    } else if (strength >= 5) {
        level = 'strong';
        message = 'Strong password!';
    } else if (strength >= 3) {
        level = 'medium';
        message = 'Medium strength password';
    }
    
    bar.className = 'bar ' + level;
    hint.textContent = message;
    hint.style.color = level === 'weak' ? '#EF4444' : level === 'medium' ? '#F59E0B' : '#059669';
});

// Confirm password check
document.getElementById('confirm_password').addEventListener('input', function() {
    var newPassword = document.getElementById('new_password').value;
    var confirmPassword = this.value;
    var hint = document.getElementById('confirm_hint');
    
    if (confirmPassword.length > 0) {
        if (newPassword === confirmPassword) {
            hint.textContent = '✓ Passwords match';
            hint.style.color = '#059669';
        } else {
            hint.textContent = '✗ Passwords do not match';
            hint.style.color = '#EF4444';
        }
    } else {
        hint.textContent = '';
    }
});

// Sidebar toggle (standard)
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