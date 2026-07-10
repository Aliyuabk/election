<?php
// ============================================================
// CREATE USER - CLIENT ADMIN WITH DYNAMIC JURISDICTION
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'client_admin') {
    header('Location: ../client-admin/');
    exit();
}

$db = getDB();
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');

// ============================================================
// FETCH ROLES
// ============================================================
$roles = [];
try {
    $stmt = $db->prepare("
        SELECT r.id, r.name, r.level 
        FROM roles r 
        WHERE (r.tenant_id = ? OR r.tenant_id IS NULL) 
        AND r.is_active = 1 
        ORDER BY FIELD(r.level, 'client_admin', 'national', 'state', 'senatorial', 'federal_constituency', 'lga', 'ward', 'pu_agent'), r.name
    ");
    $stmt->execute([$tenant_id]);
    $roles = $stmt->fetchAll();
} catch (Exception $e) {
    // Continue
}

// ============================================================
// FETCH STATES
// ============================================================
$states = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM states WHERE is_active = 1 ORDER BY name ASC");
    $stmt->execute();
    $states = $stmt->fetchAll();
} catch (Exception $e) {
    // Continue
}

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role_id = (int)($_POST['role_id'] ?? 0);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $status = $_POST['status'] ?? 'active';
    $gender = $_POST['gender'] ?? '';
    $date_of_birth = $_POST['date_of_birth'] ?? null;
    $nin = trim($_POST['nin'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // Jurisdiction fields
    $state_id = isset($_POST['state_id']) ? (int)$_POST['state_id'] : 0;
    $lga_id = isset($_POST['lga_id']) ? (int)$_POST['lga_id'] : 0;
    $ward_id = isset($_POST['ward_id']) ? (int)$_POST['ward_id'] : 0;
    $pu_id = isset($_POST['pu_id']) ? (int)$_POST['pu_id'] : 0;
    $senatorial_id = isset($_POST['senatorial_id']) ? (int)$_POST['senatorial_id'] : 0;
    $constituency_id = isset($_POST['constituency_id']) ? (int)$_POST['constituency_id'] : 0;
    
    $errors = [];
    
    if (empty($first_name)) $errors[] = 'First name is required.';
    if (empty($last_name)) $errors[] = 'Last name is required.';
    if (empty($email)) $errors[] = 'Email is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    if (empty($role_id)) $errors[] = 'Role is required.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm_password) $errors[] = 'Passwords do not match.';
    
    // Get role level for validation
    $role_level = '';
    foreach ($roles as $role) {
        if ($role['id'] == $role_id) {
            $role_level = $role['level'];
            break;
        }
    }
    
    // Jurisdiction validation based on role
    if ($role_level === 'state' || $role_level === 'senatorial' || $role_level === 'federal_constituency' || $role_level === 'lga' || $role_level === 'ward' || $role_level === 'pu_agent') {
        if ($state_id <= 0) $errors[] = 'State is required for this role.';
    }
    
    if ($role_level === 'lga' || $role_level === 'ward' || $role_level === 'pu_agent') {
        if ($lga_id <= 0) $errors[] = 'LGA is required for this role.';
    }
    
    if ($role_level === 'ward' || $role_level === 'pu_agent') {
        if ($ward_id <= 0) $errors[] = 'Ward is required for this role.';
    }
    
    if ($role_level === 'pu_agent') {
        if ($pu_id <= 0) $errors[] = 'Polling Unit is required for this role.';
    }
    
    // Check if email exists
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND tenant_id = ? AND deleted_at IS NULL");
            $stmt->execute([$email, $tenant_id]);
            if ($stmt->fetch()) {
                $errors[] = 'Email already registered.';
            }
        } catch (Exception $e) {
            // Continue
        }
    }
    
    if (empty($errors)) {
        try {
            $user_code = 'USR' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("
                INSERT INTO users (
                    tenant_id, user_code, role_id, first_name, last_name, 
                    email, phone, password_hash, status, gender, date_of_birth,
                    nin, residential_address, state_id, lga_id, ward_id, pu_id,
                    senatorial_id, federal_constituency_id,
                    created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
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
                $status,
                $gender ?: null,
                $date_of_birth ?: null,
                $nin ?: null,
                $address ?: null,
                $state_id ?: null,
                $lga_id ?: null,
                $ward_id ?: null,
                $pu_id ?: null,
                $senatorial_id ?: null,
                $constituency_id ?: null,
                $user_id
            ]);
            
            $new_user_id = $db->lastInsertId();
            
            logActivity($user_id, 'user_created', "Created user: $first_name $last_name (ID: $new_user_id)");
            
            // Send welcome email
            try {
                $subject = "Welcome to " . APP_NAME;
                $message = "Dear $first_name,\n\n";
                $message .= "Your account has been created.\n\n";
                $message .= "Login Details:\n";
                $message .= "Email: $email\n";
                $message .= "Password: $password\n\n";
                $message .= "Please change your password after logging in.\n\n";
                $message .= "Login: " . APP_URL . "/auth/login.php\n\n";
                $message .= "Best regards,\n" . APP_NAME . " Team";
                sendEmail($email, $subject, $message);
            } catch (Exception $e) {
                error_log("Welcome email failed: " . $e->getMessage());
            }
            
            $success = "User created successfully! They will receive login details via email.";
            
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
            error_log("User creation PDO Error: " . $e->getMessage());
        } catch (Exception $e) {
            $error = 'Error creating user: ' . $e->getMessage();
            error_log("User creation Error: " . $e->getMessage());
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

$page_title = 'Create User';
include 'includes/base.php';
include 'includes/sidebar.php';
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
.form-group select {
    padding: 10px 14px;
    border: 1.5px solid var(--gray-200);
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
.form-group select option[disabled] {
    color: var(--gray-400);
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
.form-section-title .required {
    color: var(--danger);
    font-weight: 700;
    font-size: 0.7rem;
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

.alert {
    padding: 14px 18px;
    border-radius: 10px;
    font-size: 0.85rem;
    margin-bottom: 16px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    border: 1px solid transparent;
}
.alert i {
    margin-top: 2px;
    font-size: 1.1rem;
}
.alert-success {
    background: #ECFDF5;
    color: #065F46;
    border-color: #A7F3D0;
}
.alert-error {
    background: #FEF2F2;
    color: #DC2626;
    border-color: #FECACA;
}

.jurisdiction-hint {
    background: #F0F9FF;
    border: 1px solid #BAE6FD;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 0.75rem;
    color: #0369A1;
    grid-column: 1 / -1;
}
.jurisdiction-hint i {
    margin-right: 6px;
}

.hidden {
    display: none !important;
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
@media (max-width: 480px) {
    .form-container {
        padding: 16px;
    }
    .form-group input,
    .form-group select {
        padding: 8px 12px;
        font-size: 0.8rem;
    }
}
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-user-plus" style="color:var(--primary);margin-right:8px;"></i> Create User
                    <small>Add a new user to your organization</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="users.php" class="btn btn-secondary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--gray-100);color:var(--gray-600);">
                    <i class="fas fa-arrow-left"></i> Back to Users
                </a>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div><?php echo $success; ?></div>
            </div>
        <?php endif; ?>

        <!-- Create Form -->
        <div class="form-container">
            <div class="form-title">
                <i class="fas fa-user-circle"></i> New User
            </div>
            <div class="form-subtitle">
                Fill in the details below to create a new user account.
            </div>
            
            <form method="POST" action="" id="createUserForm">
                <div class="form-grid">
                    <!-- Account Details -->
                    <div class="form-section-title">
                        <i class="fas fa-cog"></i> Account Details
                    </div>
                    
                    <div class="form-group">
                        <label>Role <span class="required">*</span></label>
                        <select name="role_id" id="roleSelect" required onchange="updateJurisdictionFields()">
                            <option value="">Select Role</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>" data-level="<?php echo $role['level']; ?>">
                                    <?php echo htmlspecialchars($role['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="active">Active</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>

                    <!-- Personal Information -->
                    <div class="form-section-title">
                        <i class="fas fa-user"></i> Personal Information
                    </div>
                    
                    <div class="form-group">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" placeholder="John" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" placeholder="Doe" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Address <span class="required">*</span></label>
                        <input type="email" name="email" placeholder="user@organization.ng" required>
                        <div class="help-text">This will be used for login.</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" placeholder="+234 800 555 5555">
                    </div>
                    
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender">
                            <option value="">Select Gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                            <option value="prefer_not_say">Prefer not to say</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="date_of_birth">
                    </div>
                    
                    <div class="form-group full-width">
                        <label>NIN (Optional)</label>
                        <input type="text" name="nin" placeholder="National Identification Number">
                    </div>

                    <div class="form-group full-width">
                        <label>Residential Address</label>
                        <input type="text" name="address" placeholder="Enter full address">
                    </div>

                    <!-- Jurisdiction -->
                    <div class="form-section-title" id="jurisdictionTitle">
                        <i class="fas fa-map-marker-alt"></i> Jurisdiction <span class="required">*</span>
                    </div>
                    
                    <div class="jurisdiction-hint" id="jurisdictionHint">
                        <i class="fas fa-info-circle"></i>
                        Please select the jurisdiction for this user based on their role.
                    </div>
                    
                    <!-- State -->
                    <div class="form-group hidden" id="stateField">
                        <label>State <span class="required">*</span></label>
                        <select name="state_id" id="stateSelect" onchange="loadLGAs()">
                            <option value="">Select State</option>
                            <?php foreach ($states as $state): ?>
                                <option value="<?php echo $state['id']; ?>"><?php echo htmlspecialchars($state['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- LGA -->
                    <div class="form-group hidden" id="lgaField">
                        <label>LGA <span class="required">*</span></label>
                        <select name="lga_id" id="lgaSelect" onchange="loadWards()">
                            <option value="">Select LGA</option>
                        </select>
                    </div>
                    
                    <!-- Ward -->
                    <div class="form-group hidden" id="wardField">
                        <label>Ward <span class="required">*</span></label>
                        <select name="ward_id" id="wardSelect" onchange="loadPollingUnits()">
                            <option value="">Select Ward</option>
                        </select>
                    </div>
                    
                    <!-- Polling Unit -->
                    <div class="form-group hidden" id="puField">
                        <label>Polling Unit <span class="required">*</span></label>
                        <select name="pu_id" id="puSelect">
                            <option value="">Select Polling Unit</option>
                        </select>
                    </div>

                    <!-- Senatorial District -->
                    <div class="form-group hidden" id="senatorialField">
                        <label>Senatorial District <span class="required">*</span></label>
                        <select name="senatorial_id" id="senatorialSelect">
                            <option value="">Select Senatorial District</option>
                        </select>
                    </div>

                    <!-- Federal Constituency -->
                    <div class="form-group hidden" id="constituencyField">
                        <label>Federal Constituency <span class="required">*</span></label>
                        <select name="constituency_id" id="constituencySelect">
                            <option value="">Select Federal Constituency</option>
                        </select>
                    </div>

                    <!-- Security -->
                    <div class="form-section-title">
                        <i class="fas fa-lock"></i> Security
                    </div>
                    
                    <div class="form-group">
                        <label>Password <span class="required">*</span></label>
                        <input type="password" name="password" placeholder="Min 8 characters" required minlength="8">
                        <div class="help-text">User will receive this password via email.</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm Password <span class="required">*</span></label>
                        <input type="password" name="confirm_password" placeholder="Confirm password" required minlength="8">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Create User
                    </button>
                    <a href="users.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
// ============================================================
// ROLE TO JURISDICTION MAPPING
// ============================================================
const roleJurisdictionMap = {
    'state': ['stateField'],
    'senatorial': ['stateField', 'senatorialField'],
    'federal_constituency': ['stateField', 'constituencyField'],
    'lga': ['stateField', 'lgaField'],
    'ward': ['stateField', 'lgaField', 'wardField'],
    'pu_agent': ['stateField', 'lgaField', 'wardField', 'puField'],
    'national': [],
    'client_admin': []
};

// ============================================================
// UPDATE JURISDICTION FIELDS BASED ON ROLE
// ============================================================
function updateJurisdictionFields() {
    var roleSelect = document.getElementById('roleSelect');
    var selectedOption = roleSelect.options[roleSelect.selectedIndex];
    var roleLevel = selectedOption ? selectedOption.dataset.level : '';
    
    // Hide all jurisdiction fields first
    var allFields = ['stateField', 'lgaField', 'wardField', 'puField', 'senatorialField', 'constituencyField'];
    allFields.forEach(function(fieldId) {
        var field = document.getElementById(fieldId);
        if (field) field.classList.add('hidden');
    });
    
    // Show fields based on role
    var fieldsToShow = roleJurisdictionMap[roleLevel] || [];
    fieldsToShow.forEach(function(fieldId) {
        var field = document.getElementById(fieldId);
        if (field) field.classList.remove('hidden');
    });
    
    // Show/hide jurisdiction section title
    var title = document.getElementById('jurisdictionTitle');
    var hint = document.getElementById('jurisdictionHint');
    if (fieldsToShow.length > 0) {
        title.classList.remove('hidden');
        hint.classList.remove('hidden');
    } else {
        title.classList.add('hidden');
        hint.classList.add('hidden');
    }
    
    // Update required attribute on fields
    allFields.forEach(function(fieldId) {
        var field = document.getElementById(fieldId);
        if (field) {
            var select = field.querySelector('select');
            if (select) {
                if (fieldsToShow.includes(fieldId)) {
                    select.setAttribute('required', 'required');
                } else {
                    select.removeAttribute('required');
                }
            }
        }
    });
    
    // Reset dependent dropdowns
    resetDropdowns();
}

// ============================================================
// RESET DROPDOWNS
// ============================================================
function resetDropdowns() {
    document.getElementById('lgaSelect').innerHTML = '<option value="">Select LGA</option>';
    document.getElementById('wardSelect').innerHTML = '<option value="">Select Ward</option>';
    document.getElementById('puSelect').innerHTML = '<option value="">Select Polling Unit</option>';
    document.getElementById('senatorialSelect').innerHTML = '<option value="">Select Senatorial District</option>';
    document.getElementById('constituencySelect').innerHTML = '<option value="">Select Federal Constituency</option>';
}

// ============================================================
// LOAD LGAS
// ============================================================
function loadLGAs() {
    var stateId = document.getElementById('stateSelect').value;
    var lgaSelect = document.getElementById('lgaSelect');
    lgaSelect.innerHTML = '<option value="">Loading...</option>';
    
    if (!stateId) {
        lgaSelect.innerHTML = '<option value="">Select LGA</option>';
        return;
    }
    
    fetch('ajax/get_lgas.php?state_id=' + stateId)
        .then(function(response) { return response.json(); })
        .then(function(data) {
            lgaSelect.innerHTML = '<option value="">Select LGA</option>';
            if (data && data.length > 0) {
                data.forEach(function(lga) {
                    var option = document.createElement('option');
                    option.value = lga.id;
                    option.textContent = lga.name;
                    lgaSelect.appendChild(option);
                });
            } else {
                lgaSelect.innerHTML = '<option value="">No LGAs found</option>';
            }
        })
        .catch(function() {
            lgaSelect.innerHTML = '<option value="">Error loading LGAs</option>';
        });
}

// ============================================================
// LOAD WARDS
// ============================================================
function loadWards() {
    var lgaId = document.getElementById('lgaSelect').value;
    var wardSelect = document.getElementById('wardSelect');
    wardSelect.innerHTML = '<option value="">Loading...</option>';
    
    if (!lgaId) {
        wardSelect.innerHTML = '<option value="">Select Ward</option>';
        return;
    }
    
    fetch('ajax/get_wards.php?lga_id=' + lgaId)
        .then(function(response) { return response.json(); })
        .then(function(data) {
            wardSelect.innerHTML = '<option value="">Select Ward</option>';
            if (data && data.length > 0) {
                data.forEach(function(ward) {
                    var option = document.createElement('option');
                    option.value = ward.id;
                    option.textContent = ward.name;
                    wardSelect.appendChild(option);
                });
            } else {
                wardSelect.innerHTML = '<option value="">No Wards found</option>';
            }
        })
        .catch(function() {
            wardSelect.innerHTML = '<option value="">Error loading Wards</option>';
        });
}

// ============================================================
// LOAD POLLING UNITS
// ============================================================
function loadPollingUnits() {
    var wardId = document.getElementById('wardSelect').value;
    var puSelect = document.getElementById('puSelect');
    puSelect.innerHTML = '<option value="">Loading...</option>';
    
    if (!wardId) {
        puSelect.innerHTML = '<option value="">Select Polling Unit</option>';
        return;
    }
    
    fetch('ajax/get_polling_units.php?ward_id=' + wardId)
        .then(function(response) { return response.json(); })
        .then(function(data) {
            puSelect.innerHTML = '<option value="">Select Polling Unit</option>';
            if (data && data.length > 0) {
                data.forEach(function(pu) {
                    var option = document.createElement('option');
                    option.value = pu.id;
                    option.textContent = pu.name + ' (' + pu.code + ')';
                    puSelect.appendChild(option);
                });
            } else {
                puSelect.innerHTML = '<option value="">No Polling Units found</option>';
            }
        })
        .catch(function() {
            puSelect.innerHTML = '<option value="">Error loading Polling Units</option>';
        });
}

// ============================================================
// LOAD SENATORIAL DISTRICTS
// ============================================================
function loadSenatorialDistricts() {
    var stateId = document.getElementById('stateSelect').value;
    var senatorialSelect = document.getElementById('senatorialSelect');
    senatorialSelect.innerHTML = '<option value="">Loading...</option>';
    
    if (!stateId) {
        senatorialSelect.innerHTML = '<option value="">Select Senatorial District</option>';
        return;
    }
    
    fetch('ajax/get_senatorial_districts.php?state_id=' + stateId)
        .then(function(response) { return response.json(); })
        .then(function(data) {
            senatorialSelect.innerHTML = '<option value="">Select Senatorial District</option>';
            if (data && data.length > 0) {
                data.forEach(function(sd) {
                    var option = document.createElement('option');
                    option.value = sd.id;
                    option.textContent = sd.name;
                    senatorialSelect.appendChild(option);
                });
            } else {
                senatorialSelect.innerHTML = '<option value="">No Senatorial Districts found</option>';
            }
        })
        .catch(function() {
            senatorialSelect.innerHTML = '<option value="">Error loading Senatorial Districts</option>';
        });
}

// ============================================================
// LOAD FEDERAL CONSTITUENCIES
// ============================================================
function loadFederalConstituencies() {
    var stateId = document.getElementById('stateSelect').value;
    var constituencySelect = document.getElementById('constituencySelect');
    constituencySelect.innerHTML = '<option value="">Loading...</option>';
    
    if (!stateId) {
        constituencySelect.innerHTML = '<option value="">Select Federal Constituency</option>';
        return;
    }
    
    fetch('ajax/get_federal_constituencies.php?state_id=' + stateId)
        .then(function(response) { return response.json(); })
        .then(function(data) {
            constituencySelect.innerHTML = '<option value="">Select Federal Constituency</option>';
            if (data && data.length > 0) {
                data.forEach(function(fc) {
                    var option = document.createElement('option');
                    option.value = fc.id;
                    option.textContent = fc.name;
                    constituencySelect.appendChild(option);
                });
            } else {
                constituencySelect.innerHTML = '<option value="">No Federal Constituencies found</option>';
            }
        })
        .catch(function() {
            constituencySelect.innerHTML = '<option value="">Error loading Federal Constituencies</option>';
        });
}

// ============================================================
// PASSWORD VALIDATION
// ============================================================
document.getElementById('createUserForm').addEventListener('submit', function(e) {
    var password = this.querySelector('input[name="password"]');
    var confirm = this.querySelector('input[name="confirm_password"]');
    
    if (password.value !== confirm.value) {
        e.preventDefault();
        alert('Passwords do not match!');
        confirm.focus();
        return false;
    }
});

// ============================================================
// INITIALIZE ON LOAD
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    updateJurisdictionFields();
});

// ============================================================
// PRELOADER, SIDEBAR, ETC...
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