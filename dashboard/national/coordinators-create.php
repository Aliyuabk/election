<?php
// ============================================================
// NATIONAL COORDINATOR - CREATE COORDINATOR
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

// Get parameters - check both GET and POST
$state_id = isset($_GET['state']) ? intval($_GET['state']) : 0;
if ($state_id <= 0 && isset($_POST['state_id'])) {
    $state_id = intval($_POST['state_id']);
}
$level = isset($_GET['level']) ? $_GET['level'] : 'state';

// Debug log
error_log("=== coordinators-create.php ===");
error_log("GET: " . print_r($_GET, true));
error_log("State ID: " . $state_id);
error_log("Level: " . $level);

// If no state ID, redirect
if ($state_id <= 0) {
    error_log("Redirecting due to invalid state ID");
    header('Location: monitor-states.php?error=invalid_state');
    exit();
}

$db = getDB();

// ============================================================
// FETCH STATE NAME
// ============================================================
$state_name = '';
try {
    $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
    $stmt->execute([$state_id]);
    $state_name = $stmt->fetchColumn();
    error_log("State name found: " . $state_name);
    
    if (!$state_name) {
        error_log("State not found with ID: " . $state_id);
        header('Location: monitor-states.php?error=state_not_found');
        exit();
    }
} catch (Exception $e) {
    error_log("Create Coordinator Error: " . $e->getMessage());
    header('Location: monitor-states.php?error=database_error');
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
        WHERE level IN ('state', 'lga') 
        AND is_active = 1 
        ORDER BY level, name
    ");
    $stmt->execute();
    $roles = $stmt->fetchAll();
} catch (Exception $e) {
    $roles = [];
}

// ============================================================
// FETCH LGAS FOR SELECTION
// ============================================================
$lgas = [];
if ($state_id > 0) {
    try {
        $stmt = $db->prepare("SELECT id, name FROM lgas WHERE state_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$state_id]);
        $lgas = $stmt->fetchAll();
    } catch (Exception $e) {
        $lgas = [];
    }
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
    $role_id = intval($_POST['role_id'] ?? 0);
    $jurisdiction_id = intval($_POST['jurisdiction_id'] ?? 0);
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    
    // Debug
    error_log("Form submission: " . print_r($_POST, true));
    
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
    } elseif ($jurisdiction_id <= 0) {
        $error = 'Please select a jurisdiction';
    } elseif (empty($password)) {
        $error = 'Password is required';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        try {
            // Check if email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email already registered';
            } else {
                // Get role level
                $stmt = $db->prepare("SELECT level FROM roles WHERE id = ?");
                $stmt->execute([$role_id]);
                $role_level = $stmt->fetchColumn();
                
                // Generate user code
                $user_code = 'USR' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
                
                // Hash password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Determine jurisdiction type
                $jurisdiction_type = $role_level === 'state' ? 'state' : 'lga';
                
                // Insert user
                $stmt = $db->prepare("
                    INSERT INTO users (
                        tenant_id, user_code, role_id, first_name, last_name,
                        email, phone, password_hash, jurisdiction_type, jurisdiction_id,
                        status, created_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())
                ");
                
                $stmt->execute([
                    $tenant_id,
                    $user_code,
                    $role_id,
                    $first_name,
                    $last_name,
                    $email,
                    $phone,
                    $password_hash,
                    $jurisdiction_type,
                    $jurisdiction_id,
                    $user_id
                ]);
                
                $new_user_id = $db->lastInsertId();
                error_log("User created with ID: " . $new_user_id);
                
                // Log activity
                $log_stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, tenant_id, activity_type, description, entity_type, entity_id, created_at)
                    VALUES (?, ?, 'user_created', ?, 'user', ?, NOW())
                ");
                $log_stmt->execute([
                    $user_id,
                    $tenant_id,
                    "Created coordinator: $first_name $last_name for " . ucfirst($role_level),
                    $new_user_id
                ]);
                
                $success = true;
                $message = "Coordinator created successfully! User Code: $user_code";
                
                // Redirect after success
                header("Location: state-coordinators.php?id=$state_id&success=1");
                exit();
            }
        } catch (Exception $e) {
            $error = 'Failed to create coordinator: ' . $e->getMessage();
            error_log("Create Coordinator Error: " . $e->getMessage());
        }
    }
}

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Create Coordinator';
$page_subtitle = $level === 'state' ? 'State Coordinator' : 'LGA Coordinator';
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
                <a href="monitor-states.php" style="text-decoration:none;color:var(--gray-500);">Monitor States</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <a href="state-coordinators.php?id=<?php echo $state_id; ?>" style="text-decoration:none;color:var(--gray-500);"><?php echo htmlspecialchars($state_name); ?></a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);">Create Coordinator</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        Create <?php echo $level === 'state' ? 'State' : 'LGA'; ?> Coordinator
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <?php echo htmlspecialchars($state_name); ?> • 
                        <?php echo $level === 'state' ? 'State Level' : 'LGA Level'; ?>
                    </p>
                </div>
                <a href="state-coordinators.php?id=<?php echo $state_id; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
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

        <!-- Create Coordinator Form -->
        <form method="POST" action="" style="background:white;border-radius:var(--radius);padding:24px;border:1px solid var(--gray-200);">
            <input type="hidden" name="state_id" value="<?php echo $state_id; ?>">
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                <!-- Left Column -->
                <div>
                    <!-- First Name -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            First Name <span style="color:#EF4444;">*</span>
                        </label>
                        <input type="text" name="first_name" class="form-control" required
                               value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                               placeholder="Enter first name"
                               style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    
                    <!-- Last Name -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Last Name <span style="color:#EF4444;">*</span>
                        </label>
                        <input type="text" name="last_name" class="form-control" required
                               value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                               placeholder="Enter last name"
                               style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    
                    <!-- Email -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Email Address <span style="color:#EF4444;">*</span>
                        </label>
                        <input type="email" name="email" class="form-control" required
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                               placeholder="Enter email address"
                               style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    
                    <!-- Phone -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Phone Number <span style="color:#EF4444;">*</span>
                        </label>
                        <input type="tel" name="phone" class="form-control" required
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
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
                                    <?php echo ($_POST['role_id'] ?? '') == $role['id'] ? 'selected' : ''; ?>
                                    <?php echo $role['level'] === $level ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($role['name']); ?> (<?php echo ucfirst($role['level']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Jurisdiction -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Jurisdiction <span style="color:#EF4444;">*</span>
                        </label>
                        <?php if ($level === 'state'): ?>
                            <select name="jurisdiction_id" class="form-control" required
                                    style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;background:white;transition:var(--transition);">
                                <option value="">Select State...</option>
                                <?php
                                $stmt = $db->prepare("SELECT id, name FROM states WHERE is_active = 1 ORDER BY name");
                                $stmt->execute();
                                $all_states = $stmt->fetchAll();
                                foreach ($all_states as $state):
                                ?>
                                    <option value="<?php echo $state['id']; ?>"
                                        <?php echo ($_POST['jurisdiction_id'] ?? '') == $state['id'] ? 'selected' : ''; ?>
                                        <?php echo $state['id'] == $state_id ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($state['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <select name="jurisdiction_id" class="form-control" required
                                    style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;background:white;transition:var(--transition);">
                                <option value="">Select LGA...</option>
                                <?php foreach ($lgas as $lga): ?>
                                    <option value="<?php echo $lga['id']; ?>"
                                        <?php echo ($_POST['jurisdiction_id'] ?? '') == $lga['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($lga['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Password -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Password <span style="color:#EF4444;">*</span>
                        </label>
                        <input type="password" name="password" class="form-control" required
                               placeholder="Min 8 characters"
                               style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    
                    <!-- Confirm Password -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Confirm Password <span style="color:#EF4444;">*</span>
                        </label>
                        <input type="password" name="confirm_password" class="form-control" required
                               placeholder="Confirm password"
                               style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div style="display:flex;gap:12px;margin-top:24px;padding-top:20px;border-top:1px solid var(--gray-200);flex-wrap:wrap;">
                <button type="submit" class="btn-primary" style="padding:10px 32px;background:var(--primary);color:white;border:none;border-radius:10px;font-family:'Inter',sans-serif;font-weight:600;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-user-plus"></i> Create Coordinator
                </button>
                <a href="state-coordinators.php?id=<?php echo $state_id; ?>" class="btn-secondary" style="padding:10px 32px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-family:'Inter',sans-serif;font-weight:500;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
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