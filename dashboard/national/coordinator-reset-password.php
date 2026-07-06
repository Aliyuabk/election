<?php
// ============================================================
// NATIONAL COORDINATOR - RESET COORDINATOR PASSWORD
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

// Only national coordinator can access
if (SessionManager::get('role_level') !== 'national') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');

// Get coordinator ID
$coordinator_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($coordinator_id <= 0) {
    header('Location: monitor-states.php?error=invalid_coordinator');
    exit();
}

$db = getDB();

// ============================================================
// FETCH COORDINATOR DATA
// ============================================================
$coordinator = null;
$back_url = 'monitor-states.php';

try {
    $stmt = $db->prepare("
        SELECT 
            u.*,
            r.name as role_name,
            r.level as role_level,
            CASE 
                WHEN u.jurisdiction_type = 'state' THEN (SELECT name FROM states WHERE id = u.jurisdiction_id)
                WHEN u.jurisdiction_type = 'lga' THEN (SELECT name FROM lgas WHERE id = u.jurisdiction_id)
                WHEN u.jurisdiction_type = 'ward' THEN (SELECT name FROM wards WHERE id = u.jurisdiction_id)
                WHEN u.jurisdiction_type = 'pu' THEN (SELECT name FROM polling_units WHERE id = u.jurisdiction_id)
                ELSE 'Unknown'
            END as jurisdiction_name
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.id = ? AND u.tenant_id = ?
    ");
    $stmt->execute([$coordinator_id, $tenant_id]);
    $coordinator = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$coordinator) {
        header('Location: monitor-states.php?error=coordinator_not_found');
        exit();
    }
    
    // Determine back URL based on role level
    if ($coordinator['role_level'] === 'state') {
        $back_url = "state-coordinators.php?id=" . $coordinator['jurisdiction_id'];
    } elseif ($coordinator['role_level'] === 'lga') {
        $back_url = "lga-coordinators.php?id=" . $coordinator['jurisdiction_id'];
    } elseif ($coordinator['role_level'] === 'ward') {
        $back_url = "ward-dashboard.php?id=" . $coordinator['jurisdiction_id'];
    } elseif ($coordinator['role_level'] === 'pu_agent') {
        $back_url = "pu-agents.php?pu=" . $coordinator['jurisdiction_id'];
    } else {
        $back_url = "monitor-states.php";
    }
    
} catch (Exception $e) {
    error_log("Coordinator Reset Password Error: " . $e->getMessage());
    header('Location: monitor-states.php?error=database_error');
    exit();
}

// ============================================================
// PROCESS PASSWORD RESET
// ============================================================
$message = '';
$error = '';
$success = false;
$new_password = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $send_email = isset($_POST['send_email']) ? true : false;
    
    if (empty($password)) {
        $error = 'Please enter a password';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        try {
            // Hash the new password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Update user password
            $stmt = $db->prepare("
                UPDATE users 
                SET password_hash = ?,
                    updated_at = NOW() 
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$password_hash, $coordinator_id, $tenant_id]);
            
            // Log activity
            $log_stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, tenant_id, activity_type, description, entity_type, entity_id, created_at)
                VALUES (?, ?, 'password_reset_admin', ?, 'user', ?, NOW())
            ");
            $log_stmt->execute([
                $user_id,
                $tenant_id,
                "Reset password for coordinator: " . $coordinator['full_name'],
                $coordinator_id
            ]);
            
            // Log security event
            $security_stmt = $db->prepare("
                INSERT INTO security_events (tenant_id, user_id, event_type, description, ip_address, created_at)
                VALUES (?, ?, 'password_reset', ?, ?, NOW())
            ");
            $security_stmt->execute([
                $tenant_id,
                $coordinator_id,
                "Password reset by administrator: " . $user_name,
                getClientIP()
            ]);
            
            // Send email notification if requested
            if ($send_email && !empty($coordinator['email'])) {
                $login_url = APP_URL . '/auth/login.php';
                $email_subject = 'Password Reset - ' . APP_NAME;
                $email_body = '
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; background: #f4f6fa; padding: 20px; }
                        .container { max-width: 500px; margin: 0 auto; background: white; border-radius: 12px; padding: 40px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
                        .header { text-align: center; margin-bottom: 30px; }
                        .header h1 { color: #0F4C81; margin: 0; }
                        .credentials { background: #F8FAFC; padding: 20px; border-radius: 12px; margin: 20px 0; border-left: 4px solid #F59E0B; }
                        .btn { display: inline-block; padding: 12px 32px; background: #0F4C81; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; }
                        .footer { text-align: center; color: #64748B; font-size: 12px; margin-top: 30px; border-top: 1px solid #E2E8F0; padding-top: 20px; }
                        .warning { background: #FEF3C7; padding: 12px; border-radius: 8px; color: #92400E; font-size: 14px; margin: 16px 0; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h1>🔐 ' . APP_NAME . '</h1>
                            <p style="color: #64748B;">Password Reset Confirmation</p>
                        </div>
                        <p>Dear ' . htmlspecialchars($coordinator['first_name']) . ',</p>
                        <p>Your password has been reset by an administrator. Below are your new credentials:</p>
                        <div class="credentials">
                            <p><strong>Email:</strong> ' . htmlspecialchars($coordinator['email']) . '</p>
                            <p><strong>New Password:</strong> <code style="background:#E2E8F0;padding:2px 8px;border-radius:4px;font-size:14px;">' . htmlspecialchars($password) . '</code></p>
                        </div>
                        <div class="warning">
                            <strong>⚠️ Important:</strong> Please change your password immediately after logging in for security reasons.
                        </div>
                        <div style="text-align: center; margin: 30px 0;">
                            <a href="' . $login_url . '" class="btn">Login Now</a>
                        </div>
                        <p style="color: #64748B; font-size: 14px;">
                            If you did not request this password reset, please contact your administrator immediately.
                        </p>
                        <div class="footer">
                            &copy; ' . date('Y') . ' ' . APP_NAME . '. All rights reserved.
                        </div>
                    </div>
                </body>
                </html>
                ';
                
                sendEmail($coordinator['email'], $email_subject, $email_body);
            }
            
            $success = true;
            $new_password = $password;
            $message = "Password reset successfully for " . $coordinator['full_name'] . "!";
            
        } catch (Exception $e) {
            $error = 'Failed to reset password: ' . $e->getMessage();
            error_log("Reset Password Error: " . $e->getMessage());
        }
    }
}

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Reset Password';
$page_subtitle = $coordinator['full_name'] ?? 'Coordinator';
?>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Breadcrumb -->
        <div class="welcome-section">
            <div class="breadcrumb">
                <i class="fas fa-home"></i>
                <a href="../national/index.php" style="text-decoration:none;color:var(--gray-500);">Dashboard</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <a href="<?php echo $back_url; ?>" style="text-decoration:none;color:var(--gray-500);">Coordinators</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);">Reset Password</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <i class="fas fa-key" style="color:var(--warning);"></i>
                        Reset Password
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-user"></i> 
                        <?php echo htmlspecialchars($coordinator['full_name']); ?> • 
                        <?php echo htmlspecialchars($coordinator['role_name']); ?>
                    </p>
                </div>
                <a href="<?php echo $back_url; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Success Message -->
        <?php if ($message && $success): ?>
            <div style="background:#D1FAE5;color:#065F46;padding:16px 20px;border-radius:10px;margin-bottom:20px;border:1px solid #A7F3D0;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                    <i class="fas fa-check-circle" style="font-size:1.2rem;"></i>
                    <span style="font-weight:600;"><?php echo htmlspecialchars($message); ?></span>
                </div>
                <div style="background:white;border-radius:8px;padding:12px 16px;margin-top:8px;border:1px solid #A7F3D0;">
                    <p style="font-size:0.8rem;color:#065F46;margin:0;">
                        <strong>New Password:</strong> 
                        <code style="background:#E2E8F0;padding:4px 12px;border-radius:4px;font-size:14px;font-weight:600;"><?php echo htmlspecialchars($new_password); ?></code>
                    </p>
                    <p style="font-size:0.75rem;color:#065F46;margin:4px 0 0;">
                        <i class="fas fa-info-circle"></i> Please provide this password to the coordinator.
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background:#FEE2E2;color:#991B1B;padding:12px 20px;border-radius:10px;margin-bottom:20px;border:1px solid #FECACA;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-exclamation-circle" style="font-size:1.2rem;"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Reset Password Form -->
        <form method="POST" action="" style="background:white;border-radius:var(--radius);padding:24px;border:1px solid var(--gray-200);">
            <!-- Coordinator Info -->
            <div style="background:var(--gray-50);border-radius:8px;padding:12px 16px;margin-bottom:20px;border:1px solid var(--gray-200);">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;">
                    <div>
                        <label style="display:block;font-size:0.6rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">Name</label>
                        <div style="font-weight:500;font-size:0.85rem;"><?php echo htmlspecialchars($coordinator['full_name']); ?></div>
                    </div>
                    <div>
                        <label style="display:block;font-size:0.6rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">Email</label>
                        <div style="font-weight:500;font-size:0.85rem;"><?php echo htmlspecialchars($coordinator['email']); ?></div>
                    </div>
                    <div>
                        <label style="display:block;font-size:0.6rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">Role</label>
                        <div style="font-weight:500;font-size:0.85rem;"><?php echo htmlspecialchars($coordinator['role_name']); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- New Password -->
            <div style="margin-bottom:16px;">
                <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                    New Password <span style="color:#EF4444;">*</span>
                </label>
                <input type="password" name="password" class="form-control" required
                       placeholder="Enter new password (min 8 characters)"
                       style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                <div style="font-size:0.65rem;color:var(--gray-400);margin-top:4px;">
                    <i class="fas fa-info-circle"></i> Password must be at least 8 characters
                </div>
            </div>
            
            <!-- Confirm Password -->
            <div style="margin-bottom:16px;">
                <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                    Confirm Password <span style="color:#EF4444;">*</span>
                </label>
                <input type="password" name="confirm_password" class="form-control" required
                       placeholder="Confirm new password"
                       style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
            </div>
            
            <!-- Send Email Notification -->
            <div style="margin-bottom:16px;">
                <label style="display:flex;align-items:center;gap:8px;font-size:0.85rem;color:var(--gray-600);cursor:pointer;padding:8px 12px;background:#F0FDF4;border-radius:8px;border:1px solid #A7F3D0;">
                    <input type="checkbox" name="send_email" value="1" checked>
                    <i class="fas fa-envelope" style="color:#10B981;"></i>
                    <span>Send new password to coordinator via email</span>
                </label>
                <div style="font-size:0.65rem;color:var(--gray-400);margin-top:4px;margin-left:12px;">
                    <i class="fas fa-info-circle"></i> The coordinator will receive an email with the new password
                </div>
            </div>

            <!-- Warning -->
            <div style="background:#FEF3C7;border-radius:8px;padding:12px 16px;margin-bottom:16px;border:1px solid #FDE68A;">
                <div style="display:flex;align-items:flex-start;gap:8px;">
                    <i class="fas fa-exclamation-triangle" style="color:#92400E;margin-top:2px;"></i>
                    <div>
                        <p style="font-size:0.8rem;color:#92400E;margin:0;">
                            <strong>⚠️ Important:</strong> Resetting the password will immediately revoke all existing sessions. 
                            The coordinator will need to log in with the new password.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div style="display:flex;gap:12px;margin-top:24px;padding-top:20px;border-top:1px solid var(--gray-200);flex-wrap:wrap;">
                <button type="submit" class="btn-primary" style="padding:10px 32px;background:var(--warning);color:white;border:none;border-radius:10px;font-family:'Inter',sans-serif;font-weight:600;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-key"></i> Reset Password
                </button>
                <a href="<?php echo $back_url; ?>" class="btn-secondary" style="padding:10px 32px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-family:'Inter',sans-serif;font-weight:500;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>

        <!-- Quick Actions -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-top:16px;">
            <a href="coordinator-view.php?id=<?php echo $coordinator_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-eye" style="color:var(--primary);"></i>
                <span>View Profile</span>
            </a>
            <a href="coordinator-edit.php?id=<?php echo $coordinator_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-edit" style="color:var(--secondary);"></i>
                <span>Edit Profile</span>
            </a>
            <a href="coordinator-activity.php?id=<?php echo $coordinator_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-clock" style="color:var(--warning);"></i>
                <span>View Activity</span>
            </a>
        </div>
    </div>
</main>

<style>
.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.btn-primary:hover {
    background: #D97706;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.btn-secondary:hover {
    background: var(--gray-200);
    transform: translateY(-2px);
}

.quick-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-hover);
    border-color: var(--primary);
}

@media (max-width: 768px) {
    div[style*="grid-template-columns:repeat(auto-fit,minmax(150px,1fr))"] {
        grid-template-columns: 1fr 1fr !important;
    }
    div[style*="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))"] {
        grid-template-columns: 1fr 1fr !important;
    }
}
</style>

<script>
// ============================================================
// PASSWORD STRENGTH INDICATOR (Optional)
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    var passwordInput = document.querySelector('input[name="password"]');
    var confirmInput = document.querySelector('input[name="confirm_password"]');
    
    // Optional: Add password strength indicator
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            var strength = checkPasswordStrength(this.value);
            // You can add a strength bar here if desired
        });
    }
    
    function checkPasswordStrength(password) {
        var strength = 0;
        if (password.length >= 8) strength++;
        if (password.match(/[a-z]/)) strength++;
        if (password.match(/[A-Z]/)) strength++;
        if (password.match(/[0-9]/)) strength++;
        if (password.match(/[^a-zA-Z0-9]/)) strength++;
        return strength;
    }
});

// ============================================================
// SIDEBAR TOGGLE, DROPDOWNS, PROFILE, SEARCH
// ============================================================
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