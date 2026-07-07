<?php
// ============================================================
// STATE COORDINATOR - EDIT COORDINATOR
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

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$user_email = SessionManager::get('user_email');
$state_id = SessionManager::get('state_id');
$tenant_id = SessionManager::get('tenant_id');

// Get coordinator ID
$coordinator_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($coordinator_id <= 0) {
    header('Location: monitor-lgas.php?error=invalid_coordinator');
    exit();
}

$db = getDB();

// ============================================================
// FETCH COORDINATOR DATA
// ============================================================
$coordinator = null;
$back_url = 'state-coordinators.php';

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
        header('Location: monitor-lgas.php?error=coordinator_not_found');
        exit();
    }
    
    // Determine back URL based on role level
    if ($coordinator['role_level'] === 'state') {
        $back_url = "state-coordinators.php";
    } elseif ($coordinator['role_level'] === 'lga') {
        $back_url = "lga-coordinators.php?id=" . $coordinator['jurisdiction_id'];
    } elseif ($coordinator['role_level'] === 'ward') {
        $back_url = "ward-dashboard.php?id=" . $coordinator['jurisdiction_id'];
    } elseif ($coordinator['role_level'] === 'pu_agent') {
        $back_url = "pu-agents.php?pu=" . $coordinator['jurisdiction_id'];
    } else {
        $back_url = "state-coordinators.php";
    }
    
} catch (Exception $e) {
    error_log("Coordinator Edit Error: " . $e->getMessage());
    header('Location: monitor-lgas.php?error=database_error');
    exit();
}

// ============================================================
// FETCH ROLES
// ============================================================
$roles = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, level 
        FROM roles 
        WHERE level IN ('state', 'lga', 'ward', 'pu_agent') 
        AND is_active = 1 
        ORDER BY FIELD(level, 'state', 'lga', 'ward', 'pu_agent'), name
    ");
    $stmt->execute();
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $roles = [];
}

// ============================================================
// PROCESS FORM SUBMISSION
// ============================================================
$message = '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $role_id = intval($_POST['role_id'] ?? 0);
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $reset_password = isset($_POST['reset_password']) ? true : false;
    
    // Validation
    if (empty($first_name)) {
        $error = 'First name is required';
    } elseif (empty($last_name)) {
        $error = 'Last name is required';
    } elseif (empty($email)) {
        $error = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } elseif (empty($phone)) {
        $error = 'Phone number is required';
    } elseif ($role_id <= 0) {
        $error = 'Please select a role';
    } elseif ($reset_password) {
        if (empty($password)) {
            $error = 'Password is required';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match';
        }
    } else {
        try {
            // Check if email already exists (excluding current user)
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $coordinator_id]);
            if ($stmt->fetch()) {
                $error = 'Email already registered to another user';
            } else {
                // Build update query
                $update_fields = [
                    "first_name = ?",
                    "last_name = ?",
                    "email = ?",
                    "phone = ?",
                    "status = ?",
                    "role_id = ?"
                ];
                $update_params = [$first_name, $last_name, $email, $phone, $status, $role_id];
                
                // Add password if resetting
                if ($reset_password && !empty($password)) {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $update_fields[] = "password_hash = ?";
                    $update_params[] = $password_hash;
                }
                
                $update_params[] = $coordinator_id;
                
                $stmt = $db->prepare("
                    UPDATE users 
                    SET " . implode(', ', $update_fields) . "
                    WHERE id = ?
                ");
                $stmt->execute($update_params);
                
                // Log activity
                $log_stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, tenant_id, activity_type, description, entity_type, entity_id, created_at)
                    VALUES (?, ?, 'user_updated', ?, 'user', ?, NOW())
                ");
                $log_stmt->execute([
                    $user_id,
                    $tenant_id,
                    "Updated coordinator: $first_name $last_name",
                    $coordinator_id
                ]);
                
                $success = true;
                $message = "Coordinator updated successfully!";
                
                // Redirect after success
                header("Location: " . $back_url . "&updated=1");
                exit();
            }
        } catch (Exception $e) {
            $error = 'Failed to update coordinator: ' . $e->getMessage();
            error_log("Update Coordinator Error: " . $e->getMessage());
        }
    }
}

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Edit Coordinator';
$page_subtitle = $coordinator['full_name'] ?? 'Coordinator';
?>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Breadcrumb -->
        <div class="welcome-section">
            <div class="breadcrumb">
                <i class="fas fa-home"></i>
                <a href="index.php" style="text-decoration:none;color:var(--gray-500);">Dashboard</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <a href="<?php echo $back_url; ?>" style="text-decoration:none;color:var(--gray-500);">Coordinators</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);">Edit Coordinator</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <i class="fas fa-edit" style="color:var(--primary);"></i>
                        Edit Coordinator
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

        <!-- Edit Form -->
        <form method="POST" action="" style="background:white;border-radius:var(--radius);padding:24px;border:1px solid var(--gray-200);">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                <!-- Left Column -->
                <div>
                    <!-- First Name -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            First Name <span style="color:#EF4444;">*</span>
                        </label>
                        <input type="text" name="first_name" class="form-control" required
                               value="<?php echo htmlspecialchars($coordinator['first_name'] ?? ''); ?>"
                               placeholder="Enter first name"
                               style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    
                    <!-- Last Name -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Last Name <span style="color:#EF4444;">*</span>
                        </label>
                        <input type="text" name="last_name" class="form-control" required
                               value="<?php echo htmlspecialchars($coordinator['last_name'] ?? ''); ?>"
                               placeholder="Enter last name"
                               style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    
                    <!-- Email -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Email Address <span style="color:#EF4444;">*</span>
                        </label>
                        <input type="email" name="email" class="form-control" required
                               value="<?php echo htmlspecialchars($coordinator['email'] ?? ''); ?>"
                               placeholder="Enter email address"
                               style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    
                    <!-- Phone -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Phone Number <span style="color:#EF4444;">*</span>
                        </label>
                        <input type="tel" name="phone" class="form-control" required
                               value="<?php echo htmlspecialchars($coordinator['phone'] ?? ''); ?>"
                               placeholder="Enter phone number (e.g., +234XXXXXXXXXX)"
                               style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                </div>
                
                <!-- Right Column -->
                <div>
                    <!-- Role -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Role <span style="color:#EF4444;">*</span>
                        </label>
                        <select name="role_id" class="form-control" required
                                style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;background:white;transition:var(--transition);">
                            <option value="">Select Role...</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>"
                                    <?php echo $coordinator['role_id'] == $role['id'] ? 'selected' : ''; ?>
                                    data-level="<?php echo $role['level']; ?>">
                                    <?php echo htmlspecialchars($role['name']); ?> (<?php echo ucfirst($role['level']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Status -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Status
                        </label>
                        <select name="status" class="form-control"
                                style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;background:white;transition:var(--transition);">
                            <option value="active" <?php echo ($coordinator['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="suspended" <?php echo ($coordinator['status'] ?? '') === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            <option value="pending" <?php echo ($coordinator['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="archived" <?php echo ($coordinator['status'] ?? '') === 'archived' ? 'selected' : ''; ?>>Archived</option>
                        </select>
                    </div>
                    
                    <!-- Jurisdiction Info -->
                    <div style="margin-bottom:16px;padding:12px 14px;background:var(--gray-50);border-radius:10px;border:1px solid var(--gray-200);">
                        <label style="display:block;font-weight:600;font-size:0.8rem;color:var(--gray-500);margin-bottom:4px;">
                            <i class="fas fa-map-marker-alt"></i> Jurisdiction (Read-only)
                        </label>
                        <div style="font-weight:500;font-size:0.9rem;color:var(--gray-700);">
                            <?php echo htmlspecialchars($coordinator['jurisdiction_name'] ?? 'Unknown'); ?>
                        </div>
                        <div style="font-size:0.7rem;color:var(--gray-400);">
                            Type: <?php echo ucfirst($coordinator['jurisdiction_type'] ?? 'Unknown'); ?>
                        </div>
                    </div>
                    
                    <!-- User Code -->
                    <div style="margin-bottom:16px;padding:12px 14px;background:var(--gray-50);border-radius:10px;border:1px solid var(--gray-200);">
                        <label style="display:block;font-weight:600;font-size:0.8rem;color:var(--gray-500);margin-bottom:4px;">
                            <i class="fas fa-id-card"></i> User Code
                        </label>
                        <div style="font-weight:500;font-size:0.9rem;color:var(--gray-700);">
                            <?php echo htmlspecialchars($coordinator['user_code'] ?? 'N/A'); ?>
                        </div>
                    </div>
                    
                    <!-- Reset Password -->
                    <div style="margin-top:8px;border-top:1px solid var(--gray-200);padding-top:16px;">
                        <label style="display:flex;align-items:center;gap:8px;font-size:0.85rem;color:var(--gray-600);cursor:pointer;margin-bottom:12px;">
                            <input type="checkbox" name="reset_password" value="1" id="resetPasswordCheck">
                            <i class="fas fa-key" style="color:#F59E0B;"></i>
                            Reset Password
                        </label>
                        
                        <div id="passwordFields" style="display:none;">
                            <div style="margin-bottom:12px;">
                                <label style="display:block;font-weight:600;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                                    New Password <span style="color:#EF4444;">*</span>
                                </label>
                                <input type="password" name="password" class="form-control"
                                       placeholder="Min 8 characters"
                                       style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                            </div>
                            <div>
                                <label style="display:block;font-weight:600;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">
                                    Confirm Password <span style="color:#EF4444;">*</span>
                                </label>
                                <input type="password" name="confirm_password" class="form-control"
                                       placeholder="Confirm new password"
                                       style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div style="display:flex;gap:12px;margin-top:24px;padding-top:20px;border-top:1px solid var(--gray-200);flex-wrap:wrap;">
                <button type="submit" class="btn-primary" style="padding:10px 32px;background:var(--primary);color:white;border:none;border-radius:10px;font-family:'Inter',sans-serif;font-weight:600;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-save"></i> Update Coordinator
                </button>
                <a href="<?php echo $back_url; ?>" class="btn-secondary" style="padding:10px 32px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-family:'Inter',sans-serif;font-weight:500;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
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

.btn-secondary:hover {
    background: var(--gray-200);
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    div[style*="grid-template-columns:1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<script>
// ============================================================
// TOGGLE PASSWORD FIELDS
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    var resetPasswordCheck = document.getElementById('resetPasswordCheck');
    var passwordFields = document.getElementById('passwordFields');
    
    if (resetPasswordCheck && passwordFields) {
        resetPasswordCheck.addEventListener('change', function() {
            passwordFields.style.display = this.checked ? 'block' : 'none';
        });
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