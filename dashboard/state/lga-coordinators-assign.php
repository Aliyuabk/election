<?php
// ============================================================
// STATE COORDINATOR - ASSIGN LGA COORDINATOR
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
// FETCH STATE NAME AND LGAS
// ============================================================
$state_name = 'Unknown State';
$lgas = [];

try {
    if (!empty($state_id)) {
        $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
        $stmt->execute([$state_id]);
        $state = $stmt->fetch(PDO::FETCH_ASSOC);
        $state_name = $state['name'] ?? 'Unknown State';
        
        $stmt = $db->prepare("SELECT id, name FROM lgas WHERE state_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$state_id]);
        $lgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error fetching data: " . $e->getMessage());
}

// ============================================================
// FETCH LGA COORDINATOR ROLE ID
// ============================================================
$lga_role_id = null;
try {
    $stmt = $db->prepare("SELECT id FROM roles WHERE level = 'lga' AND tenant_id IS NULL LIMIT 1");
    $stmt->execute();
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    $lga_role_id = $role['id'] ?? null;
} catch (Exception $e) {
    error_log("Error fetching role: " . $e->getMessage());
}

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
$error = '';
$success = '';
$form_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Security validation failed. Please try again.';
    } else {
        $form_data = [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'lga_id' => (int)($_POST['lga_id'] ?? 0),
            'password' => $_POST['password'] ?? '',
            'gender' => $_POST['gender'] ?? '',
            'date_of_birth' => $_POST['date_of_birth'] ?? null,
        ];

        $errors = [];

        // Validate required fields
        if (empty($form_data['first_name'])) {
            $errors[] = 'First name is required.';
        }
        
        if (empty($form_data['last_name'])) {
            $errors[] = 'Last name is required.';
        }
        
        if (empty($form_data['email'])) {
            $errors[] = 'Email address is required.';
        } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        
        if (empty($form_data['lga_id'])) {
            $errors[] = 'Please select an LGA.';
        }
        
        if (empty($form_data['password'])) {
            $errors[] = 'Password is required.';
        } elseif (strlen($form_data['password']) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }
        
        if (empty($lga_role_id)) {
            $errors[] = 'LGA Coordinator role not found. Please contact support.';
        }

        // Check if email already exists
        if (!empty($form_data['email'])) {
            try {
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND deleted_at IS NULL");
                $stmt->execute([$form_data['email']]);
                if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                    $errors[] = 'This email is already registered.';
                }
            } catch (Exception $e) {
                // Continue
            }
        }

        // Check if LGA already has a coordinator
        if (!empty($form_data['lga_id'])) {
            try {
                $stmt = $db->prepare("
                    SELECT u.id FROM users u
                    JOIN roles r ON u.role_id = r.id
                    WHERE u.lga_id = ? AND r.level = 'lga' AND u.deleted_at IS NULL AND u.status = 'active'
                ");
                $stmt->execute([$form_data['lga_id']]);
                if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                    $errors[] = 'This LGA already has an active coordinator. Please deactivate the current coordinator first.';
                }
            } catch (Exception $e) {
                // Continue
            }
        }

        if (empty($errors)) {
            try {
                // Begin transaction
                $db->beginTransaction();
                
                // Generate user code
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE tenant_id = ?");
                $stmt->execute([$tenant_id]);
                $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                $user_code = 'LGA' . str_pad($count + 1, 6, '0', STR_PAD_LEFT);
                
                // Hash password
                $password_hash = password_hash($form_data['password'], PASSWORD_DEFAULT);
                
                // Insert user as LGA Coordinator
                $stmt = $db->prepare("
                    INSERT INTO users (
                        tenant_id, user_code, role_id, first_name, last_name,
                        email, phone, password_hash, status, gender, date_of_birth,
                        state_id, lga_id, jurisdiction_type, jurisdiction_id,
                        created_by, created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, 'active', ?, ?,
                        ?, ?, 'lga', ?,
                        ?, NOW()
                    )
                ");
                
                $stmt->execute([
                    $tenant_id,
                    $user_code,
                    $lga_role_id,
                    $form_data['first_name'],
                    $form_data['last_name'],
                    $form_data['email'],
                    $form_data['phone'],
                    $password_hash,
                    $form_data['gender'] ?: null,
                    $form_data['date_of_birth'] ?: null,
                    $state_id,
                    $form_data['lga_id'],
                    $form_data['lga_id'],
                    $user_id
                ]);
                
                $new_user_id = $db->lastInsertId();
                
                // Commit transaction
                $db->commit();
                
                // Log activity
                logActivity(
                    $user_id,
                    'lga_coordinator_assigned',
                    "Assigned LGA Coordinator: {$form_data['first_name']} {$form_data['last_name']} (ID: $new_user_id) for LGA ID: {$form_data['lga_id']}"
                );
                
                // Send welcome email with password reset link
                try {
                    $reset_token = bin2hex(random_bytes(32));
                    $token_hash = password_hash($reset_token, PASSWORD_DEFAULT);
                    
                    $stmt = $db->prepare("
                        INSERT INTO password_resets (user_id, token, expires_at) 
                        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
                    ");
                    $stmt->execute([$new_user_id, $token_hash]);
                    
                    $reset_link = APP_URL . "/auth/reset-password.php?token=" . urlencode($reset_token) . "&email=" . urlencode($form_data['email']);
                    
                    $lga_name = '';
                    foreach ($lgas as $lga) {
                        if ($lga['id'] == $form_data['lga_id']) {
                            $lga_name = $lga['name'];
                            break;
                        }
                    }
                    
                    $subject = "Welcome as LGA Coordinator - " . APP_NAME;
                    $message = "Dear {$form_data['first_name']},\n\n";
                    $message .= "You have been assigned as the LGA Coordinator for **{$lga_name}** Local Government Area in {$state_name} State.\n\n";
                    $message .= "Your account has been created with the following email:\n";
                    $message .= "----------------------------------------\n";
                    $message .= "Email: {$form_data['email']}\n";
                    $message .= "----------------------------------------\n\n";
                    $message .= "To set up your password, please click the link below:\n";
                    $message .= $reset_link . "\n\n";
                    $message .= "This link will expire in 24 hours.\n\n";
                    $message .= "As an LGA Coordinator, you will be responsible for:\n";
                    $message .= "• Managing polling unit agents in your LGA\n";
                    $message .= "• Monitoring election activities\n";
                    $message .= "• Verifying and approving results\n";
                    $message .= "• Managing incidents and issues\n\n";
                    $message .= "If you have any questions, please contact your State Coordinator.\n\n";
                    $message .= "Best regards,\n" . APP_NAME . " Team";
                    
                    sendEmail($form_data['email'], $subject, $message);
                    $success = "LGA Coordinator assigned successfully! A welcome email has been sent.";
                } catch (Exception $e) {
                    $success = "LGA Coordinator assigned successfully! (Welcome email could not be sent)";
                    error_log("Welcome email failed: " . $e->getMessage());
                }
                
                // Clear form and regenerate CSRF token
                $form_data = [];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $csrf_token = $_SESSION['csrf_token'];
                
            } catch (PDOException $e) {
                $db->rollBack();
                $error = 'Database error: ' . $e->getMessage();
                error_log("LGA Coordinator Assignment PDO Error: " . $e->getMessage());
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Error: ' . $e->getMessage();
                error_log("LGA Coordinator Assignment Error: " . $e->getMessage());
            }
        } else {
            $error = implode('<br>', $errors);
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
.form-group select,
.form-group textarea {
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
    .page-header {
        flex-direction: column;
        align-items: flex-start;
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
                    <i class="fas fa-user-plus" style="color:var(--primary);margin-right:8px;"></i>
                    Assign LGA Coordinator
                    <small><?php echo htmlspecialchars($state_name); ?> - Assign a coordinator to an LGA</small>
                </h2>
            </div>
            <div>
                <a href="lga-coordinators.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Coordinators
                </a>
            </div>
        </div>

        <!-- Error/Success Messages -->
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

        <!-- Form -->
        <div class="form-container">
            <div class="form-title">
                <i class="fas fa-user-circle"></i> Coordinator Information
            </div>
            <div class="form-subtitle">
                Fill in the details below to assign a new LGA Coordinator for <?php echo htmlspecialchars($state_name); ?> State.
            </div>
            
            <form method="POST" action="" id="assignForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                
                <div class="form-grid">
                    <!-- Personal Information -->
                    <div class="form-section-title">
                        <i class="fas fa-user"></i> Personal Information
                    </div>
                    
                    <div class="form-group">
                        <label for="first_name">First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" id="first_name" placeholder="John" value="<?php echo htmlspecialchars($form_data['first_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" id="last_name" placeholder="Doe" value="<?php echo htmlspecialchars($form_data['last_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" name="email" id="email" placeholder="coordinator@organization.ng" value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" required>
                        <div class="help-text">This will be the coordinator's login email.</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" name="phone" id="phone" placeholder="+234 800 555 5555" value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select name="gender" id="gender">
                            <option value="">Select Gender</option>
                            <option value="male" <?php echo ($form_data['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo ($form_data['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo ($form_data['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                            <option value="prefer_not_say" <?php echo ($form_data['gender'] ?? '') === 'prefer_not_say' ? 'selected' : ''; ?>>Prefer not to say</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth</label>
                        <input type="date" name="date_of_birth" id="date_of_birth" value="<?php echo htmlspecialchars($form_data['date_of_birth'] ?? ''); ?>">
                    </div>

                    <!-- Assignment Details -->
                    <div class="form-section-title">
                        <i class="fas fa-map-marker-alt"></i> Assignment Details
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="lga_id">Local Government Area <span class="required">*</span></label>
                        <select name="lga_id" id="lga_id" required>
                            <option value="">Select LGA</option>
                            <?php foreach ($lgas as $lga): ?>
                                <option value="<?php echo $lga['id']; ?>" <?php echo ($form_data['lga_id'] ?? 0) == $lga['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lga['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="help-text">
                            <i class="fas fa-info-circle"></i> 
                            Select the LGA this coordinator will be responsible for.
                            <?php if (count($lgas) === 0): ?>
                                <span style="color:var(--danger);">
                                    No active LGAs found in <?php echo htmlspecialchars($state_name); ?>.
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Security -->
                    <div class="form-section-title">
                        <i class="fas fa-lock"></i> Security
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="password">Password <span class="required">*</span></label>
                        <input type="password" name="password" id="password" placeholder="Min 8 characters" required>
                        <div class="help-text">
                            <i class="fas fa-info-circle"></i> Password must be at least 8 characters long.
                            The coordinator will receive instructions to set their password via email.
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check-circle"></i> Assign Coordinator
                    </button>
                    <a href="lga-coordinators.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
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
// FORM VALIDATION
// ============================================================
document.getElementById('assignForm').addEventListener('submit', function(e) {
    var password = document.getElementById('password');
    var email = document.getElementById('email');
    var firstName = document.getElementById('first_name');
    var lastName = document.getElementById('last_name');
    var lga = document.getElementById('lga_id');
    var isValid = true;
    
    // Remove previous error states
    document.querySelectorAll('.error').forEach(function(el) {
        el.classList.remove('error');
    });
    
    // Validate first name
    if (!firstName.value.trim()) {
        firstName.classList.add('error');
        isValid = false;
    }
    
    // Validate last name
    if (!lastName.value.trim()) {
        lastName.classList.add('error');
        isValid = false;
    }
    
    // Validate email
    if (!email.value.trim() || !email.value.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
        email.classList.add('error');
        isValid = false;
    }
    
    // Validate LGA
    if (!lga.value) {
        lga.classList.add('error');
        isValid = false;
    }
    
    // Validate password
    if (!password.value || password.value.length < 8) {
        password.classList.add('error');
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