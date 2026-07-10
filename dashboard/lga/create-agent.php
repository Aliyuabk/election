<?php
// ============================================================
// LGA COORDINATOR - CREATE AGENT
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'lga') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'LGA Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$lga_id = SessionManager::get('lga_id');
$state_id = SessionManager::get('state_id');

if (empty($lga_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT lga_id, state_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $lga_id = $user['lga_id'];
            $state_id = $user['state_id'];
            SessionManager::set('lga_id', $lga_id);
            SessionManager::set('state_id', $state_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching lga_id/state_id: " . $e->getMessage());
    }
}

$db = getDB();

// Get LGA and State names
$lga_name = 'LGA';
$state_name = 'State';
try {
    if ($lga_id && $state_id) {
        $stmt = $db->prepare("
            SELECT l.name as lga_name, s.name as state_name 
            FROM lgas l 
            JOIN states s ON l.state_id = s.id 
            WHERE l.id = ?
        ");
        $stmt->execute([$lga_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $lga_name = $result['lga_name'];
            $state_name = $result['state_name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching LGA/State: " . $e->getMessage());
}

// Get wards for this LGA
$wards = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM wards WHERE lga_id = ? AND is_active = 1 ORDER BY name ASC");
    $stmt->execute([$lga_id]);
    $wards = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching wards: " . $e->getMessage());
}

// Get PU Agent role ID
$role_id = null;
try {
    $stmt = $db->prepare("SELECT id FROM roles WHERE level = 'pu_agent' LIMIT 1");
    $stmt->execute();
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($role) {
        $role_id = $role['id'];
    }
} catch (Exception $e) {
    error_log("Error fetching role: " . $e->getMessage());
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $ward_id = isset($_POST['ward_id']) ? (int)$_POST['ward_id'] : 0;
    $pu_id = isset($_POST['pu_id']) ? (int)$_POST['pu_id'] : 0;
    $gender = $_POST['gender'] ?? '';
    $nin = trim($_POST['nin'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif ($ward_id <= 0) {
        $error = 'Please select a ward.';
    } else {
        try {
            // Check if email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email already registered.';
            } else {
                // Generate user code
                $user_code = 'AGT' . rand(10000, 99999);
                $password_hash = hashPassword($password);
                
                // Insert user
                $stmt = $db->prepare("
                    INSERT INTO users (
                        tenant_id, user_code, role_id, first_name, last_name, 
                        email, phone, password_hash, state_id, lga_id, ward_id,
                        pu_id, gender, nin, residential_address,
                        status, created_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())
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
                    $state_id,
                    $lga_id,
                    $ward_id,
                    $pu_id ?: null,
                    $gender ?: null,
                    $nin ?: null,
                    $address ?: null,
                    $user_id
                ]);
                
                $new_user_id = $db->lastInsertId();
                
                logActivity($user_id, 'agent_created', 
                    "Created PU Agent: $first_name $last_name (ID: $new_user_id)",
                    'users', $new_user_id
                );
                
                // Send welcome email
                try {
                    $subject = "Welcome as PU Agent - " . APP_NAME;
                    $body = "
                        <h2>Welcome, $first_name $last_name!</h2>
                        <p>You have been registered as a Polling Unit Agent.</p>
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
                
                $message = "Agent created successfully! They will receive login details via email.";
                
                // Redirect to assign page
                if ($pu_id > 0) {
                    header('Location: assign-agent.php?pu_id=' . $pu_id . '&created=1');
                    exit();
                } else {
                    header('Location: pu-agents.php?pu_id=' . $pu_id . '&created=1');
                    exit();
                }
            }
        } catch (Exception $e) {
            $error = 'Failed to create agent: ' . $e->getMessage();
            error_log("Agent creation error: " . $e->getMessage());
        }
    }
}

$page_title = 'Create Agent';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.create-container {
    max-width: 700px;
    margin: 0 auto;
}

.form-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 28px;
    margin-bottom: 16px;
}

.form-card .card-title {
    font-size: 0.85rem;
    font-weight: 600;
    margin: 0 0 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-700);
}

.form-card .card-title i {
    color: var(--primary);
    margin-right: 6px;
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

.form-group label .required {
    color: #EF4444;
    margin-left: 2px;
}

.form-group input[type="text"],
.form-group input[type="email"],
.form-group input[type="tel"],
.form-group input[type="password"],
.form-group select,
.form-group textarea {
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
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.form-group textarea {
    resize: vertical;
    min-height: 60px;
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
    flex-wrap: wrap;
}

.btn-group button {
    padding: 10px 28px;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.btn-group .btn-submit {
    background: var(--primary);
    color: white;
}

.btn-group .btn-submit:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}

.btn-group .btn-cancel {
    background: var(--gray-100);
    color: var(--gray-700);
    text-decoration: none;
    padding: 10px 28px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-group .btn-cancel:hover {
    background: var(--gray-200);
}

.info-box {
    background: #F0F9FF;
    border: 1px solid #BAE6FD;
    border-radius: 10px;
    padding: 12px 16px;
    margin-bottom: 16px;
    font-size: 0.75rem;
    color: #0369A1;
}

.info-box i {
    margin-right: 6px;
}

@media (max-width: 768px) {
    .form-card {
        padding: 16px 18px;
    }
    .form-row {
        grid-template-columns: 1fr;
    }
    .btn-group {
        flex-direction: column;
    }
    .btn-group button,
    .btn-group .btn-cancel {
        width: 100%;
        justify-content: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="create-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-user-plus"></i> Create Agent</h1>
                    <p class="subtitle">
                        <i class="fas fa-map-marker-alt"></i> 
                        <?php echo htmlspecialchars($lga_name); ?> LGA - Create a new PU Agent
                    </p>
                </div>
                <div class="actions">
                    <a href="polling-units.php" class="btn-secondary-sm">
                        <i class="fas fa-arrow-left"></i> Back to PUs
                    </a>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                This agent will be created as a Polling Unit Agent. They will receive login credentials via email.
            </div>

            <div class="form-card">
                <div class="card-title"><i class="fas fa-user"></i> Agent Information</div>

                <form method="POST" action="" id="createForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name <span class="required">*</span></label>
                            <input type="text" name="first_name" required placeholder="First name" 
                                   value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" />
                        </div>
                        <div class="form-group">
                            <label>Last Name <span class="required">*</span></label>
                            <input type="text" name="last_name" required placeholder="Last name" 
                                   value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" />
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Email Address <span class="required">*</span></label>
                            <input type="email" name="email" required placeholder="agent@example.com" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" />
                            <div class="help-text">This will be used for login</div>
                        </div>
                        <div class="form-group">
                            <label>Phone Number <span class="required">*</span></label>
                            <input type="tel" name="phone" required placeholder="+234 800 000 0000" 
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" />
                        </div>
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

                    <div class="form-row">
                        <div class="form-group">
                            <label>Ward <span class="required">*</span></label>
                            <select name="ward_id" required>
                                <option value="">Select Ward...</option>
                                <?php foreach ($wards as $ward): ?>
                                    <option value="<?php echo $ward['id']; ?>" 
                                        <?php echo (isset($_POST['ward_id']) && $_POST['ward_id'] == $ward['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ward['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Gender</label>
                            <select name="gender">
                                <option value="">Select...</option>
                                <option value="male" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'male') ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'female') ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>NIN (Optional)</label>
                        <input type="text" name="nin" placeholder="National Identification Number" 
                               value="<?php echo htmlspecialchars($_POST['nin'] ?? ''); ?>" />
                    </div>

                    <div class="form-group">
                        <label>Residential Address</label>
                        <textarea name="address" rows="2" placeholder="Enter residential address..."><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                    </div>

                    <div class="btn-group">
                        <a href="polling-units.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-user-plus"></i> Create Agent
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
// Password validation
document.getElementById('createForm')?.addEventListener('submit', function(e) {
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