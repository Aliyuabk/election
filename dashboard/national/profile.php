<?php
// ============================================================
// NATIONAL COORDINATOR - USER PROFILE
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
$user_email = SessionManager::get('user_email', '');
$user_role = SessionManager::get('role_level', 'national');

$db = getDB();

// ============================================================
// FETCH USER DATA
// ============================================================
$user_data = null;

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
                ELSE 'National'
            END as jurisdiction_name
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_data) {
        header('Location: ../national/index.php?error=user_not_found');
        exit();
    }
    
} catch (Exception $e) {
    error_log("Profile Error: " . $e->getMessage());
    header('Location: ../national/index.php?error=database_error');
    exit();
}

// ============================================================
// PROCESS FORM SUBMISSION
// ============================================================
$message = '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'profile') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if (empty($first_name)) {
            $error = 'First name is required';
        } elseif (empty($last_name)) {
            $error = 'Last name is required';
        } elseif (empty($email)) {
            $error = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address';
        } else {
            try {
                // Check if email exists for other users
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $user_id]);
                if ($stmt->fetch()) {
                    $error = 'Email already in use by another account';
                } else {
                    $stmt = $db->prepare("
                        UPDATE users 
                        SET first_name = ?, last_name = ?, phone = ?, email = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$first_name, $last_name, $phone, $email, $user_id]);
                    
                    // Update session
                    SessionManager::set('user_name', $first_name . ' ' . $last_name);
                    SessionManager::set('user_email', $email);
                    
                    $success = true;
                    $message = 'Profile updated successfully!';
                    
                    // Refresh user data
                    $stmt = $db->prepare("
                        SELECT 
                            u.*,
                            r.name as role_name,
                            r.level as role_level
                        FROM users u
                        JOIN roles r ON u.role_id = r.id
                        WHERE u.id = ?
                    ");
                    $stmt->execute([$user_id]);
                    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            } catch (Exception $e) {
                $error = 'Failed to update profile: ' . $e->getMessage();
                error_log("Profile Update Error: " . $e->getMessage());
            }
        }
    } elseif ($action === 'password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password)) {
            $error = 'Current password is required';
        } elseif (empty($new_password)) {
            $error = 'New password is required';
        } elseif (strlen($new_password) < 8) {
            $error = 'New password must be at least 8 characters';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Passwords do not match';
        } else {
            try {
                // Verify current password
                $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!password_verify($current_password, $user['password_hash'])) {
                    $error = 'Current password is incorrect';
                } else {
                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $stmt->execute([$new_hash, $user_id]);
                    
                    // Log activity
                    $log_stmt = $db->prepare("
                        INSERT INTO activity_logs (user_id, tenant_id, activity_type, description, entity_type, entity_id, created_at)
                        VALUES (?, ?, 'password_change', ?, 'user', ?, NOW())
                    ");
                    $log_stmt->execute([
                        $user_id,
                        $tenant_id,
                        "Changed password",
                        $user_id
                    ]);
                    
                    $success = true;
                    $message = 'Password changed successfully!';
                }
            } catch (Exception $e) {
                $error = 'Failed to change password: ' . $e->getMessage();
                error_log("Password Change Error: " . $e->getMessage());
            }
        }
    }
}

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'My Profile';
$page_subtitle = 'Manage your account settings';
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
                <span style="font-weight:600;color:var(--gray-800);">Profile</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <i class="fas fa-user" style="color:var(--primary);"></i>
                        My Profile
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-user-tag"></i> 
                        <?php echo htmlspecialchars($user_data['role_name'] ?? 'Coordinator'); ?>
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="settings.php" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                    <a href="security.php" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-shield-alt"></i> Security
                    </a>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($message && $success): ?>
            <div style="background:#D1FAE5;color:#065F46;padding:12px 20px;border-radius:10px;margin-bottom:20px;border:1px solid #A7F3D0;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-check-circle" style="font-size:1.2rem;"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background:#FEE2E2;color:#991B1B;padding:12px 20px;border-radius:10px;margin-bottom:20px;border:1px solid #FECACA;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-exclamation-circle" style="font-size:1.2rem;"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Profile Content -->
        <div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;">
            <!-- Left Column - Avatar & Info -->
            <div style="background:white;border-radius:var(--radius);padding:20px;border:1px solid var(--gray-200);text-align:center;">
                <div style="width:100px;height:100px;border-radius:50%;background:var(--primary);color:white;display:flex;align-items:center;justify-content:center;font-size:2.5rem;font-weight:700;margin:0 auto 16px;">
                    <?php echo strtoupper(substr($user_data['first_name'] ?? 'U', 0, 1) . substr($user_data['last_name'] ?? 'N', 0, 1)); ?>
                </div>
                <h3 style="font-size:1.1rem;font-weight:600;margin:0;"><?php echo htmlspecialchars($user_data['full_name'] ?? 'User'); ?></h3>
                <p style="color:var(--gray-500);font-size:0.8rem;margin:4px 0;">
                    <?php echo htmlspecialchars($user_data['role_name'] ?? 'Coordinator'); ?>
                </p>
                <p style="color:var(--gray-400);font-size:0.7rem;margin:4px 0;">
                    <i class="fas fa-map-marker-alt"></i> 
                    <?php echo htmlspecialchars($user_data['jurisdiction_name'] ?? 'National'); ?>
                </p>
                <p style="color:var(--gray-400);font-size:0.7rem;margin:4px 0;">
                    <i class="fas fa-id-card"></i> 
                    <?php echo htmlspecialchars($user_data['user_code'] ?? 'N/A'); ?>
                </p>
                <hr style="border-color:var(--gray-200);margin:12px 0;">
                <div style="font-size:0.75rem;color:var(--gray-500);">
                    <p style="margin:4px 0;">
                        <i class="fas fa-calendar-alt"></i> 
                        Joined: <?php echo date('M j, Y', strtotime($user_data['created_at'] ?? 'now')); ?>
                    </p>
                    <?php if (!empty($user_data['last_login_at'])): ?>
                        <p style="margin:4px 0;">
                            <i class="fas fa-clock"></i> 
                            Last login: <?php echo date('M j, Y g:i A', strtotime($user_data['last_login_at'])); ?>
                        </p>
                    <?php endif; ?>
                    <p style="margin:4px 0;">
                        <i class="fas fa-circle" style="color:#10B981;font-size:0.5rem;"></i> 
                        Status: <?php echo ucfirst($user_data['status'] ?? 'Active'); ?>
                    </p>
                </div>
            </div>

            <!-- Right Column - Forms -->
            <div style="display:flex;flex-direction:column;gap:20px;">
                <!-- Profile Form -->
                <div style="background:white;border-radius:var(--radius);padding:20px;border:1px solid var(--gray-200);">
                    <h4 style="font-size:0.9rem;font-weight:600;margin:0 0 16px;padding-bottom:12px;border-bottom:1px solid var(--gray-200);">
                        <i class="fas fa-user-edit" style="color:var(--primary);margin-right:6px;"></i>
                        Personal Information
                    </h4>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="profile">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div>
                                <label style="display:block;font-weight:500;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                                    First Name <span style="color:#EF4444;">*</span>
                                </label>
                                <input type="text" name="first_name" class="form-control" required
                                       value="<?php echo htmlspecialchars($user_data['first_name'] ?? ''); ?>"
                                       style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                            </div>
                            <div>
                                <label style="display:block;font-weight:500;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                                    Last Name <span style="color:#EF4444;">*</span>
                                </label>
                                <input type="text" name="last_name" class="form-control" required
                                       value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>"
                                       style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                            </div>
                            <div>
                                <label style="display:block;font-weight:500;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                                    Email Address <span style="color:#EF4444;">*</span>
                                </label>
                                <input type="email" name="email" class="form-control" required
                                       value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>"
                                       style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                            </div>
                            <div>
                                <label style="display:block;font-weight:500;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                                    Phone Number
                                </label>
                                <input type="tel" name="phone" class="form-control"
                                       value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>"
                                       placeholder="+234XXXXXXXXXX"
                                       style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                            </div>
                        </div>
                        <div style="margin-top:16px;">
                            <button type="submit" class="btn-primary" style="padding:8px 24px;background:var(--primary);color:white;border:none;border-radius:8px;font-weight:600;font-size:0.8rem;cursor:pointer;transition:var(--transition);">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Change Password -->
                <div style="background:white;border-radius:var(--radius);padding:20px;border:1px solid var(--gray-200);">
                    <h4 style="font-size:0.9rem;font-weight:600;margin:0 0 16px;padding-bottom:12px;border-bottom:1px solid var(--gray-200);">
                        <i class="fas fa-key" style="color:var(--warning);margin-right:6px;"></i>
                        Change Password
                    </h4>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="password">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div>
                                <label style="display:block;font-weight:500;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                                    Current Password <span style="color:#EF4444;">*</span>
                                </label>
                                <input type="password" name="current_password" class="form-control" required
                                       placeholder="Enter current password"
                                       style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                            </div>
                            <div></div>
                            <div>
                                <label style="display:block;font-weight:500;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                                    New Password <span style="color:#EF4444;">*</span>
                                </label>
                                <input type="password" name="new_password" class="form-control" required
                                       placeholder="Min 8 characters"
                                       style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                            </div>
                            <div>
                                <label style="display:block;font-weight:500;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                                    Confirm New Password <span style="color:#EF4444;">*</span>
                                </label>
                                <input type="password" name="confirm_password" class="form-control" required
                                       placeholder="Confirm new password"
                                       style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                            </div>
                        </div>
                        <div style="margin-top:16px;">
                            <button type="submit" class="btn-warning" style="padding:8px 24px;background:#F59E0B;color:white;border:none;border-radius:8px;font-weight:600;font-size:0.8rem;cursor:pointer;transition:var(--transition);">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
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
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}

.btn-warning:hover {
    background: #D97706;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.btn-secondary:hover {
    background: var(--gray-200);
    transform: translateY(-1px);
}

@media (max-width: 768px) {
    div[style*="grid-template-columns:1fr 2fr;gap:20px;"] {
        grid-template-columns: 1fr !important;
    }
    div[style*="grid-template-columns:1fr 1fr;gap:12px;"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<script>
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