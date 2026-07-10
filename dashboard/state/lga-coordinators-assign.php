<?php
// ============================================================
// STATE COORDINATOR - ASSIGN LGA COORDINATOR
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

$user_name = SessionManager::get('user_name', 'State Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
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
$message = '';
$error = '';

// Get state name
$state_name = 'Unknown State';
if (!empty($state_id)) {
    $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
    $stmt->execute([$state_id]);
    $state = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_name = $state['name'] ?? 'Unknown State';
}

// Get LGAs without coordinators
$available_lgas = [];
try {
    $stmt = $db->prepare("
        SELECT l.id, l.name 
        FROM lgas l
        WHERE l.state_id = ? 
        AND l.is_active = 1
        AND NOT EXISTS (
            SELECT 1 FROM users u 
            WHERE u.lga_id = l.id 
            AND u.deleted_at IS NULL
            AND u.status = 'active'
            AND u.role_id IN (SELECT id FROM roles WHERE level = 'lga')
        )
        ORDER BY l.name ASC
    ");
    $stmt->execute([$state_id]);
    $available_lgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching available LGAs: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $lga_id = (int)($_POST['lga_id'] ?? 0);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || $lga_id <= 0) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        try {
            // Check if email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email already registered.';
            } else {
                // Get LGA Coordinator role ID
                $stmt = $db->prepare("SELECT id FROM roles WHERE level = 'lga' LIMIT 1");
                $stmt->execute();
                $role = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$role) {
                    $error = 'LGA Coordinator role not found.';
                } else {
                    $user_code = 'LGA' . time() . rand(100, 999);
                    $password_hash = hashPassword($password);
                    
                    $stmt = $db->prepare("
                        INSERT INTO users (
                            tenant_id, user_code, role_id, first_name, last_name, 
                            email, phone, password_hash, state_id, lga_id, 
                            status, created_by, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())
                    ");
                    
                    $stmt->execute([
                        $tenant_id,
                        $user_code,
                        $role['id'],
                        $first_name,
                        $last_name,
                        $email,
                        $phone,
                        $password_hash,
                        $state_id,
                        $lga_id,
                        $user_id
                    ]);
                    
                    $new_user_id = $db->lastInsertId();
                    
                    // Log activity
                    logActivity($user_id, 'lga_coordinator_assigned', 
                        "Assigned LGA Coordinator: $first_name $last_name for LGA ID: $lga_id",
                        'user', $new_user_id
                    );
                    
                    // Send welcome email
                    try {
                        $subject = "Welcome as LGA Coordinator - " . APP_NAME;
                        $body = "
                            <h2>Welcome, $first_name $last_name!</h2>
                            <p>You have been assigned as the LGA Coordinator for your Local Government Area.</p>
                            <p><strong>Login Details:</strong></p>
                            <ul>
                                <li>Email: $email</li>
                                <li>Password: $password</li>
                            </ul>
                            <p>Please login and change your password immediately.</p>
                            <a href='" . APP_URL . "/auth/login.php'>Login Here</a>
                        ";
                        sendEmail($email, $subject, $body);
                    } catch (Exception $e) {
                        error_log("Welcome email failed: " . $e->getMessage());
                    }
                    
                    $message = "LGA Coordinator assigned successfully! They will receive login details via email.";
                    
                    // Clear form
                    $_POST = [];
                    $available_lgas = array_filter($available_lgas, function($lga) use ($lga_id) {
                        return $lga['id'] != $lga_id;
                    });
                }
            }
        } catch (Exception $e) {
            $error = 'Failed to assign coordinator: ' . $e->getMessage();
            error_log("Assign coordinator error: " . $e->getMessage());
        }
    }
}

$page_title = 'Assign LGA Coordinator';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.form-container {
    max-width: 600px;
    background: white;
    border-radius: var(--radius);
    padding: 28px 32px;
    border: 1px solid var(--gray-200);
    margin-top: 16px;
}

.form-group {
    margin-bottom: 18px;
}

.form-group label {
    display: block;
    font-weight: 600;
    font-size: 0.8rem;
    color: var(--gray-700);
    margin-bottom: 4px;
}

.form-group label .required {
    color: #EF4444;
    margin-left: 2px;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    transition: var(--transition);
    background: white;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.form-group .help-text {
    font-size: 0.65rem;
    color: var(--gray-400);
    margin-top: 4px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
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

.btn-submit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
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

.info-box {
    background: #F0F9FF;
    border: 1px solid #BAE6FD;
    border-radius: 10px;
    padding: 14px 18px;
    margin-bottom: 20px;
}

.info-box h4 {
    font-size: 0.8rem;
    color: #0369A1;
    margin: 0 0 4px;
}

.info-box p {
    font-size: 0.75rem;
    color: #0C4A6E;
    margin: 0;
}

@media (max-width: 768px) {
    .form-container {
        padding: 20px;
    }
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="welcome-section">
            <div>
                <h1><i class="fas fa-user-plus"></i> Assign LGA Coordinator</h1>
                <p class="subtitle">
                    <i class="fas fa-flag"></i> 
                    <?php echo htmlspecialchars($state_name); ?> State - Assign a new LGA Coordinator
                </p>
            </div>
            <div class="actions">
                <a href="lga-coordinators.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
        </div>

        <!-- Form -->
        <div class="form-container">
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

            <?php if (count($available_lgas) === 0 && !$message): ?>
                <div class="info-box">
                    <h4><i class="fas fa-info-circle"></i> No Available LGAs</h4>
                    <p>All LGAs in <?php echo htmlspecialchars($state_name); ?> already have assigned coordinators.</p>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="assignForm">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" required value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" />
                    </div>
                    <div class="form-group">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" required value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" />
                    </div>
                </div>

                <div class="form-group">
                    <label>Email Address <span class="required">*</span></label>
                    <input type="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" placeholder="coordinator@example.com" />
                </div>

                <div class="form-group">
                    <label>Phone Number <span class="required">*</span></label>
                    <input type="tel" name="phone" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" placeholder="+234 800 000 0000" />
                </div>

                <div class="form-group">
                    <label>Assign to LGA <span class="required">*</span></label>
                    <select name="lga_id" required <?php echo count($available_lgas) === 0 ? 'disabled' : ''; ?>>
                        <option value="">Select LGA...</option>
                        <?php foreach ($available_lgas as $lga): ?>
                            <option value="<?php echo $lga['id']; ?>" <?php echo ($_POST['lga_id'] ?? '') == $lga['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lga['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (count($available_lgas) === 0): ?>
                        <div class="help-text">No available LGAs to assign.</div>
                    <?php endif; ?>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Password <span class="required">*</span></label>
                        <input type="password" name="password" required minlength="8" />
                        <div class="help-text">Minimum 8 characters</div>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password <span class="required">*</span></label>
                        <input type="password" name="confirm_password" required minlength="8" />
                    </div>
                </div>

                <button type="submit" class="btn-submit" <?php echo count($available_lgas) === 0 ? 'disabled' : ''; ?>>
                    <i class="fas fa-user-plus"></i> Assign Coordinator
                </button>
            </form>
        </div>
    </div>
</main>

<script>
// Password validation
document.getElementById('assignForm')?.addEventListener('submit', function(e) {
    var password = this.querySelector('input[name="password"]');
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