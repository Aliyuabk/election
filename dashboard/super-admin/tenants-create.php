<?php
// ============================================================
// TENANT CREATE - SUPER ADMINISTRATOR
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Start session and check login
SessionManager::start();

// Redirect if not logged in
if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

// Check role - only super_admin can access this page
if (SessionManager::get('role_level') !== 'super_admin') {
    header('Location: ../client-admin/');
    exit();
}

// Get database connection
$db = getDB();

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
$error = '';
$success = '';
$form_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $form_data = [
        'name' => trim($_POST['name'] ?? ''),
        'type' => $_POST['type'] ?? 'political_party',
        'subscription_plan' => $_POST['subscription_plan'] ?? 'basic',
        'subscription_status' => $_POST['subscription_status'] ?? 'trial',
        'contact_email' => trim($_POST['contact_email'] ?? ''),
        'contact_phone' => trim($_POST['contact_phone'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'state_id' => !empty($_POST['state_id']) ? (int)$_POST['state_id'] : null,
        'lga_id' => !empty($_POST['lga_id']) ? (int)$_POST['lga_id'] : null,
        'primary_color' => $_POST['primary_color'] ?? '#3b82f6',
        'secondary_color' => $_POST['secondary_color'] ?? '#10b981',
        'max_users' => (int)($_POST['max_users'] ?? 100),
        'max_agents' => (int)($_POST['max_agents'] ?? 500),
        'admin_email' => trim($_POST['admin_email'] ?? ''),
        'admin_first_name' => trim($_POST['admin_first_name'] ?? ''),
        'admin_last_name' => trim($_POST['admin_last_name'] ?? ''),
        'admin_phone' => trim($_POST['admin_phone'] ?? ''),
        'admin_password' => $_POST['admin_password'] ?? '',
        'subscription_start' => $_POST['subscription_start'] ?? null,
        'subscription_end' => $_POST['subscription_end'] ?? null,
    ];

    // Validate required fields
    $errors = [];
    
    if (empty($form_data['name'])) {
        $errors[] = 'Organization name is required.';
    }
    
    if (empty($form_data['admin_email'])) {
        $errors[] = 'Admin email is required.';
    } elseif (!filter_var($form_data['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid admin email address.';
    }
    
    if (empty($form_data['admin_first_name'])) {
        $errors[] = 'Admin first name is required.';
    }
    
    if (empty($form_data['admin_last_name'])) {
        $errors[] = 'Admin last name is required.';
    }
    
    if (empty($form_data['admin_password'])) {
        $errors[] = 'Admin password is required.';
    } elseif (strlen($form_data['admin_password']) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    
    if (empty($form_data['contact_email'])) {
        $form_data['contact_email'] = $form_data['admin_email'];
    }
    
    // Check if tenant name already exists
    try {
        $stmt = $db->prepare("SELECT id FROM tenants WHERE name = ? AND deleted_at IS NULL");
        $stmt->execute([$form_data['name']]);
        if ($stmt->fetch()) {
            $errors[] = 'Organization name already exists.';
        }
    } catch (Exception $e) {
        // Continue
    }
    
    // Check if admin email already exists
    try {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND deleted_at IS NULL");
        $stmt->execute([$form_data['admin_email']]);
        if ($stmt->fetch()) {
            $errors[] = 'Admin email already registered.';
        }
    } catch (Exception $e) {
        // Continue
    }

    // If no errors, create tenant
    if (empty($errors)) {
        try {
            // Generate slug
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9-]/', '-', $form_data['name']));
            $slug = preg_replace('/-+/', '-', $slug);
            $slug = trim($slug, '-');
            
            // Generate UUID
            $uuid = uniqid() . '-' . bin2hex(random_bytes(8));
            
            // Insert tenant
            $stmt = $db->prepare("
                INSERT INTO tenants (
                    uuid, name, slug, type, subscription_plan, subscription_status,
                    subscription_start, subscription_end, max_users, max_agents,
                    contact_email, contact_phone, address, state_id, lga_id,
                    primary_color, secondary_color, is_active, created_by
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, 1, ?
                )
            ");
            
            $stmt->execute([
                $uuid,
                $form_data['name'],
                $slug,
                $form_data['type'],
                $form_data['subscription_plan'],
                $form_data['subscription_status'],
                !empty($form_data['subscription_start']) ? $form_data['subscription_start'] : null,
                !empty($form_data['subscription_end']) ? $form_data['subscription_end'] : null,
                $form_data['max_users'],
                $form_data['max_agents'],
                $form_data['contact_email'],
                $form_data['contact_phone'],
                $form_data['address'],
                $form_data['state_id'],
                $form_data['lga_id'],
                $form_data['primary_color'],
                $form_data['secondary_color'],
                SessionManager::get('user_id')
            ]);
            
            $tenant_id = $db->lastInsertId();
            
            // Get admin role ID
            $stmt = $db->prepare("SELECT id FROM roles WHERE level = 'client_admin' LIMIT 1");
            $stmt->execute();
            $role = $stmt->fetch();
            $role_id = $role['id'] ?? 2;
            
            // Generate user code
            $user_code = 'USR' . str_pad($tenant_id, 6, '0', STR_PAD_LEFT);
            
            // Hash password
            $password_hash = password_hash($form_data['admin_password'], PASSWORD_DEFAULT);
            
            // Insert admin user
            $stmt = $db->prepare("
                INSERT INTO users (
                    tenant_id, user_code, role_id, first_name, last_name,
                    email, phone, password_hash, status, email_verified_at,
                    created_by, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, 'active', NOW(),
                    ?, NOW()
                )
            ");
            
            $stmt->execute([
                $tenant_id,
                $user_code,
                $role_id,
                $form_data['admin_first_name'],
                $form_data['admin_last_name'],
                $form_data['admin_email'],
                $form_data['admin_phone'],
                $password_hash,
                SessionManager::get('user_id')
            ]);
            
            $user_id = $db->lastInsertId();
            
            // Log activity
            logActivity(
                SessionManager::get('user_id'),
                'tenant_created',
                "Created new tenant: {$form_data['name']} with admin: {$form_data['admin_email']}"
            );
            
            // Send welcome email to admin (optional - wrap in try catch so it doesn't break the process)
            try {
                $subject = "Welcome to " . APP_NAME . " - Your Tenant Account";
                $message = "Dear {$form_data['admin_first_name']},\n\n";
                $message .= "Your organization '{$form_data['name']}' has been successfully registered on " . APP_NAME . ".\n\n";
                $message .= "Login Credentials:\n";
                $message .= "Email: {$form_data['admin_email']}\n";
                $message .= "Password: {$form_data['admin_password']}\n\n";
                $message .= "Please login at: " . APP_URL . "/auth/login.php\n\n";
                $message .= "We recommend changing your password after first login.\n\n";
                $message .= "Best regards,\n" . APP_NAME . " Team";
                
                sendEmail($form_data['admin_email'], $subject, $message);
            } catch (Exception $e) {
                // Email failed but tenant was created successfully
                error_log("Welcome email failed: " . $e->getMessage());
            }
            
            $success = "Tenant created successfully!" . (isset($e) ? " (Welcome email could not be sent)" : " Welcome email sent to admin.");
            $form_data = []; // Clear form
            
        } catch (PDOException $e) {
            $error = 'Database error creating tenant: ' . $e->getMessage();
            error_log("Tenant creation PDO Error: " . $e->getMessage());
        } catch (Exception $e) {
            $error = 'Error creating tenant: ' . $e->getMessage();
            error_log("Tenant creation Error: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// ============================================================
// FETCH STATES AND LGAS FOR DROPDOWN
// ============================================================
$states = [];
try {
    $stmt = $db->query("SELECT id, name FROM states WHERE is_active = 1 ORDER BY name");
    $states = $stmt->fetchAll();
} catch (Exception $e) {
    // Continue
}

// Get user info
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');

include 'includes/base.php';
include 'includes/sidebar.php';
?> 

<main class="main-content">
    <!-- Fixed Header -->
    <?php include 'includes/header.php'; ?>
    
    <!-- Main Content Inner -->
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-plus-circle" style="color:var(--primary);  margin-right:8px;"></i> Create Tenant 
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="tenants.php" class="btn-outline" style="text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> Back to Tenants
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

        <!-- Create Tenant Form -->
        <div class="form-container">
            <div class="form-title">Organization Details</div>
            <div class="form-subtitle">Fill in the information below to create a new tenant organization.</div>
            
            <form method="POST" action="" id="tenantForm">
                <div class="form-grid">
                    <!-- Organization Information -->
                    <div class="form-section-title">Organization Information</div>
                    
                    <div class="form-group">
                        <label>Organization Name <span class="required">*</span></label>
                        <input type="text" name="name" placeholder="e.g., All Progressives Congress" 
                               value="<?php echo htmlspecialchars($form_data['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Organization Type</label>
                        <select name="type">
                            <option value="political_party" <?php echo ($form_data['type'] ?? '') === 'political_party' ? 'selected' : ''; ?>>Political Party</option>
                            <option value="candidate" <?php echo ($form_data['type'] ?? '') === 'candidate' ? 'selected' : ''; ?>>Candidate</option>
                            <option value="ngo" <?php echo ($form_data['type'] ?? '') === 'ngo' ? 'selected' : ''; ?>>NGO</option>
                            <option value="observer_group" <?php echo ($form_data['type'] ?? '') === 'observer_group' ? 'selected' : ''; ?>>Observer Group</option>
                            <option value="cso" <?php echo ($form_data['type'] ?? '') === 'cso' ? 'selected' : ''; ?>>CSO</option>
                            <option value="research_institution" <?php echo ($form_data['type'] ?? '') === 'research_institution' ? 'selected' : ''; ?>>Research Institution</option>
                        </select>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Address</label>
                        <textarea name="address" placeholder="Organization address"><?php echo htmlspecialchars($form_data['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Contact Email</label>
                        <input type="email" name="contact_email" placeholder="contact@organization.ng" 
                               value="<?php echo htmlspecialchars($form_data['contact_email'] ?? ''); ?>">
                        <div class="help-text">If not provided, admin email will be used.</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Contact Phone</label>
                        <input type="tel" name="contact_phone" placeholder="+234 800 555 5555" 
                               value="<?php echo htmlspecialchars($form_data['contact_phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>State</label>
                        <select name="state_id" id="stateSelect">
                            <option value="">Select State</option>
                            <?php foreach ($states as $state): ?>
                                <option value="<?php echo $state['id']; ?>" <?php echo ($form_data['state_id'] ?? '') == $state['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($state['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>LGA</label>
                        <select name="lga_id" id="lgaSelect">
                            <option value="">Select LGA</option>
                        </select>
                    </div>
                    
                    <!-- Branding -->
                    <div class="form-section-title">Branding</div>
                    
                    <div class="form-group">
                        <label>Primary Color</label>
                        <div class="color-input">
                            <input type="color" name="primary_color" value="<?php echo htmlspecialchars($form_data['primary_color'] ?? '#3b82f6'); ?>">
                            <input type="text" name="primary_color_text" placeholder="#3b82f6" 
                                   value="<?php echo htmlspecialchars($form_data['primary_color'] ?? '#3b82f6'); ?>"
                                   onchange="document.querySelector('input[name=\'primary_color\']').value=this.value">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Secondary Color</label>
                        <div class="color-input">
                            <input type="color" name="secondary_color" value="<?php echo htmlspecialchars($form_data['secondary_color'] ?? '#10b981'); ?>">
                            <input type="text" name="secondary_color_text" placeholder="#10b981" 
                                   value="<?php echo htmlspecialchars($form_data['secondary_color'] ?? '#10b981'); ?>"
                                   onchange="document.querySelector('input[name=\'secondary_color\']').value=this.value">
                        </div>
                    </div>
                    
                    <!-- Subscription -->
                    <div class="form-section-title">Subscription Settings</div>
                    
                    <div class="form-group">
                        <label>Subscription Plan</label>
                        <select name="subscription_plan">
                            <option value="free" <?php echo ($form_data['subscription_plan'] ?? '') === 'free' ? 'selected' : ''; ?>>Free</option>
                            <option value="basic" <?php echo ($form_data['subscription_plan'] ?? '') === 'basic' ? 'selected' : ''; ?>>Basic</option>
                            <option value="standard" <?php echo ($form_data['subscription_plan'] ?? '') === 'standard' ? 'selected' : ''; ?>>Standard</option>
                            <option value="premium" <?php echo ($form_data['subscription_plan'] ?? '') === 'premium' ? 'selected' : ''; ?>>Premium</option>
                            <option value="enterprise" <?php echo ($form_data['subscription_plan'] ?? '') === 'enterprise' ? 'selected' : ''; ?>>Enterprise</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Subscription Status</label>
                        <select name="subscription_status">
                            <option value="trial" <?php echo ($form_data['subscription_status'] ?? '') === 'trial' ? 'selected' : ''; ?>>Trial</option>
                            <option value="active" <?php echo ($form_data['subscription_status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="suspended" <?php echo ($form_data['subscription_status'] ?? '') === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            <option value="expired" <?php echo ($form_data['subscription_status'] ?? '') === 'expired' ? 'selected' : ''; ?>>Expired</option>
                            <option value="cancelled" <?php echo ($form_data['subscription_status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Subscription Start Date</label>
                        <input type="date" name="subscription_start" 
                               value="<?php echo htmlspecialchars($form_data['subscription_start'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Subscription End Date</label>
                        <input type="date" name="subscription_end" 
                               value="<?php echo htmlspecialchars($form_data['subscription_end'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Max Users</label>
                        <input type="number" name="max_users" value="<?php echo htmlspecialchars($form_data['max_users'] ?? 100); ?>" min="1">
                        <div class="help-text">Maximum number of users allowed for this tenant.</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Max Agents</label>
                        <input type="number" name="max_agents" value="<?php echo htmlspecialchars($form_data['max_agents'] ?? 500); ?>" min="1">
                        <div class="help-text">Maximum number of agents allowed for this tenant.</div>
                    </div>
                    
                    <!-- Admin User -->
                    <div class="form-section-title">Administrator Account</div>
                    
                    <div class="form-group">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" name="admin_first_name" placeholder="John" 
                               value="<?php echo htmlspecialchars($form_data['admin_first_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" name="admin_last_name" placeholder="Doe" 
                               value="<?php echo htmlspecialchars($form_data['admin_last_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Admin Email <span class="required">*</span></label>
                        <input type="email" name="admin_email" placeholder="admin@organization.ng" 
                               value="<?php echo htmlspecialchars($form_data['admin_email'] ?? ''); ?>" required>
                        <div class="help-text">This will be the primary admin login email.</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Admin Phone</label>
                        <input type="tel" name="admin_phone" placeholder="+234 800 555 5555" 
                               value="<?php echo htmlspecialchars($form_data['admin_phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Admin Password <span class="required">*</span></label>
                        <input type="password" name="admin_password" placeholder="Min 8 characters" required>
                        <div class="help-text">The admin will receive this password via email. They should change it after first login.</div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Create Tenant
                    </button>
                    <a href="tenants.php" class="btn btn-secondary">
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
    const preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(() => {
            preloader.style.display = 'none';
        }, 600);
    }
});

// ============================================================
// SIDEBAR TOGGLE (mobile)
// ============================================================
const sidebar = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const dashboardHeader = document.getElementById('dashboardHeader');

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

window.addEventListener('resize', () => {
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
document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
    toggle.addEventListener('click', function(e) {
        e.preventDefault();
        const dropdownId = this.dataset.dropdown;
        const dropdown = document.getElementById(dropdownId);
        const chevron = this.querySelector('.chevron');
        
        if (dropdown) {
            dropdown.classList.toggle('open');
            if (chevron) chevron.classList.toggle('open');
        }
    });
});

// ============================================================
// PROFILE DROPDOWN
// ============================================================
const profileBtn = document.getElementById('profileBtn');
const profileMenu = document.getElementById('profileMenu');

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
// STATE -> LGA DYNAMIC LOADING
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    const stateSelect = document.getElementById('stateSelect');
    const lgaSelect = document.getElementById('lgaSelect');
    const selectedLga = '<?php echo $form_data['lga_id'] ?? ''; ?>';
    
    if (stateSelect && lgaSelect) {
        stateSelect.addEventListener('change', function() {
            const stateId = this.value;
            lgaSelect.innerHTML = '<option value="">Loading...</option>';
            
            if (stateId) {
                fetch(`ajax/get-lgas.php?state_id=${stateId}`)
                    .then(response => response.json())
                    .then(data => {
                        lgaSelect.innerHTML = '<option value="">Select LGA</option>';
                        if (data.length > 0) {
                            data.forEach(lga => {
                                const option = document.createElement('option');
                                option.value = lga.id;
                                option.textContent = lga.name;
                                if (lga.id == selectedLga) {
                                    option.selected = true;
                                }
                                lgaSelect.appendChild(option);
                            });
                        }
                    })
                    .catch(() => {
                        lgaSelect.innerHTML = '<option value="">Error loading LGAs</option>';
                    });
            } else {
                lgaSelect.innerHTML = '<option value="">Select LGA</option>';
            }
        });
        
        // Trigger change if state is pre-selected
        if (stateSelect.value) {
            stateSelect.dispatchEvent(new Event('change'));
        }
    }
    
    // Color input sync
    document.querySelectorAll('.color-input input[type="text"]').forEach(textInput => {
        textInput.addEventListener('input', function() {
            const colorInput = this.closest('.color-input').querySelector('input[type="color"]');
            if (colorInput) {
                colorInput.value = this.value;
            }
        });
    });
});

// ============================================================
// SEARCH (header)
// ============================================================
const searchInput = document.getElementById('searchInput');
const searchResults = document.getElementById('searchResults');
let searchTimeout;

if (searchInput) {
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        clearTimeout(searchTimeout);
        
        if (query.length < 2) {
            if (searchResults) searchResults.classList.remove('active');
            return;
        }
        
        searchTimeout = setTimeout(() => {
            fetch(`search.php?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    if (searchResults) {
                        searchResults.innerHTML = '';
                        if (data && data.length > 0) {
                            data.forEach(item => {
                                const div = document.createElement('a');
                                div.className = 'result-item';
                                div.href = item.url || '#';
                                div.innerHTML = `
                                    <i class="fas ${item.icon || 'fa-file'}"></i>
                                    <span class="text-truncate">${item.label || item.name || ''}</span>
                                    <span class="result-type">${(item.type || '').charAt(0).toUpperCase() + (item.type || '').slice(1)}</span>
                                `;
                                searchResults.appendChild(div);
                            });
                            searchResults.classList.add('active');
                        } else {
                            searchResults.innerHTML = `
                                <div style="padding:12px;text-align:center;color:var(--gray-500);font-size:0.8rem;">
                                    <i class="fas fa-search" style="display:block;font-size:1.2rem;margin-bottom:4px;"></i>
                                    No results found
                                </div>
                            `;
                            searchResults.classList.add('active');
                        }
                    }
                })
                .catch(() => {});
        }, 300);
    });

    document.addEventListener('click', function(e) {
        const wrapper = document.querySelector('.search-wrapper');
        if (wrapper && !wrapper.contains(e.target) && searchResults) {
            searchResults.classList.remove('active');
        }
    });
}
</script>
</body>
</html>