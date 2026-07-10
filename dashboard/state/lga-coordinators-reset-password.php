<?php
// ============================================================
// STATE COORDINATOR - RESET LGA COORDINATOR PASSWORD
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'state') {
    header('Location: ../client-admin/');
    exit();
}

$user_id = SessionManager::get('user_id');
$state_id = SessionManager::get('state_id');

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
$coordinator_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($coordinator_id <= 0) {
    header('Location: lga-coordinators.php');
    exit();
}

// Get coordinator info
$coordinator = null;
try {
    $stmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, u.status, l.name as lga_name
        FROM users u
        LEFT JOIN lgas l ON u.lga_id = l.id
        WHERE u.id = ? AND u.deleted_at IS NULL
    ");
    $stmt->execute([$coordinator_id]);
    $coordinator = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching coordinator: " . $e->getMessage());
}

if (!$coordinator) {
    header('Location: lga-coordinators.php');
    exit();
}

$message = '';
$error = '';

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $password_hash = hashPassword($new_password);
            
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$password_hash, $coordinator_id]);
            
            // Revoke all sessions
            $stmt = $db->prepare("UPDATE user_sessions SET is_active = 0 WHERE user_id = ?");
            $stmt->execute([$coordinator_id]);
            
            logActivity($user_id, 'coordinator_password_reset', 
                "Reset password for LGA Coordinator: {$coordinator['first_name']} {$coordinator['last_name']} (ID: $coordinator_id)",
                'user', $coordinator_id
            );
            
            // Send email with new password
            try {
                $full_name = $coordinator['first_name'] . ' ' . $coordinator['last_name'];
                $subject = "Password Reset - " . APP_NAME;
                $body = "
                    <h2>Password Reset</h2>
                    <p>Dear $full_name,</p>
                    <p>Your LGA Coordinator account password has been reset.</p>
                    <p><strong>New Password:</strong> $new_password</p>
                    <p>Please login and change your password immediately.</p>
                    <p><a href='" . APP_URL . "/auth/login.php'>Login Here</a></p>
                ";
                sendEmail($coordinator['email'], $subject, $body);
            } catch (Exception $e) {
                error_log("Password reset email failed: " . $e->getMessage());
            }
            
            $message = "Password reset successfully! The coordinator will receive the new password via email.";
        } catch (Exception $e) {
            $error = 'Failed to reset password: ' . $e->getMessage();
            error_log("Password reset error: " . $e->getMessage());
        }
    }
}

$page_title = 'Reset Password';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.reset-container {
    max-width: 500px;
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 28px 32px;
    margin-top: 16px;
}

.reset-container .coordinator-info {
    background: var(--gray-50);
    border-radius: 10px;
    padding: 14px 18px;
    margin-bottom: 20px;
}

.reset-container .coordinator-info .name {
    font-weight: 600;
    color: var(--gray-800);
}

.reset-container .coordinator-info .lga {
    font-size: 0.8rem;
    color: var(--gray-500);
}

.reset-container .coordinator-info .email {
    font-size: 0.8rem;
    color: var(--gray-500);
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    font-weight: 600;
    font-size: 0.8rem;
    color: var(--gray-700);
    margin-bottom: 4px;
}

.form-group input {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    transition: var(--transition);
}

.form-group input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.form-group .help-text {
    font-size: 0.65rem;
    color: var(--gray-400);
    margin-top: 4px;
}

.alert {
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 0.85rem;
    margin-bottom: 16px;
}

.alert-success {
    background: #ECFDF5;
    color: #065F46;
    border: 1px solid #A7F3D0;
}

.alert-error {
    background: #FEF2F2;
    color: #991B1B;
    border: 1px solid #FECACA;
}

.alert i {
    margin-right: 6px;
}

.btn-group {
    display: flex;
    gap: 10px;
    margin-top: 8px;
}

.btn-submit {
    padding: 10px 32px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.btn-submit:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}

.btn-cancel {
    padding: 10px 32px;
    background: var(--gray-100);
    color: var(--gray-700);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    text-decoration: none;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.btn-cancel:hover {
    background: var(--gray-200);
}

.password-requirements {
    background: #F0F9FF;
    border: 1px solid #BAE6FD;
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 0.75rem;
    color: #0369A1;
    margin-bottom: 16px;
}

.password-requirements ul {
    margin: 4px 0 0 16px;
}

@media (max-width: 768px) {
    .reset-container {
        padding: 20px;
    }
    .btn-group {
        flex-direction: column;
    }
    .btn-group a, .btn-group button {
        width: 100%;
        text-align: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="reset-container">
            <!-- Page Header -->
            <div style="margin-bottom:16px;">
                <h3 style="font-size:1.1rem;margin:0;"><i class="fas fa-key" style="color:var(--primary);margin-right:6px;"></i> Reset Password</h3>
                <p style="font-size:0.8rem;color:var(--gray-500);margin:2px 0 0;">Reset password for LGA Coordinator</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="coordinator-info">
                <div class="name">
                    <i class="fas fa-user" style="color:var(--primary);margin-right:6px;"></i>
                    <?php echo htmlspecialchars($coordinator['first_name'] . ' ' . $coordinator['last_name']); ?>
                </div>
                <div class="lga">
                    <i class="fas fa-map-marker-alt" style="margin-right:4px;"></i>
                    <?php echo htmlspecialchars($coordinator['lga_name'] ?? 'N/A'); ?> LGA
                </div>
                <div class="email">
                    <i class="fas fa-envelope" style="margin-right:4px;"></i>
                    <?php echo htmlspecialchars($coordinator['email'] ?? 'N/A'); ?>
                </div>
            </div>

            <div class="password-requirements">
                <strong><i class="fas fa-info-circle"></i> Password Requirements:</strong>
                <ul>
                    <li>Minimum 8 characters</li>
                    <li>Include at least one uppercase letter</li>
                    <li>Include at least one lowercase letter</li>
                    <li>Include at least one number</li>
                </ul>
            </div>

            <?php if (!$message): ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label>New Password <span class="required">*</span></label>
                    <input type="password" name="new_password" required minlength="8" />
                    <div class="help-text">Minimum 8 characters</div>
                </div>

                <div class="form-group">
                    <label>Confirm Password <span class="required">*</span></label>
                    <input type="password" name="confirm_password" required minlength="8" />
                </div>

                <div class="btn-group">
                    <a href="lga-coordinators-profiles.php?id=<?php echo $coordinator_id; ?>" class="btn-cancel">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-key"></i> Reset Password
                    </button>
                </div>
            </form>
            <?php else: ?>
                <div style="text-align:center;padding:10px 0;">
                    <a href="lga-coordinators-profiles.php?id=<?php echo $coordinator_id; ?>" class="btn-cancel">
                        <i class="fas fa-arrow-left"></i> Back to Profile
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
// Password validation
document.querySelector('form')?.addEventListener('submit', function(e) {
    var password = this.querySelector('input[name="new_password"]');
    var confirm = this.querySelector('input[name="confirm_password"]');
    
    if (password.value !== confirm.value) {
        e.preventDefault();
        alert('Passwords do not match!');
        confirm.focus();
        return false;
    }
});

// Same sidebar scripts as index.php
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});

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
</script>
</body>
</html>