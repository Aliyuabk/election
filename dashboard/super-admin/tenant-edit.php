<?php
$page_title = "Edit Tenant";
require_once 'includes/db.php';

// Get database instance
$db = Database::getInstance();
$conn = $db->getConnection();

$tenant_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $tenant_id > 0;
$message = '';
$error = '';

// Initialize $tenant as empty array for new tenant
$tenant = [
    'name' => '',
    'slug' => '',
    'type' => 'political_party',
    'subscription_plan' => 'basic',
    'subscription_status' => 'trial',
    'contact_email' => '',
    'contact_phone' => '',
    'address' => '',
    'state_id' => null,
    'lga_id' => null,
    'logo_url' => null,
    'primary_color' => '#3b82f6',
    'secondary_color' => '#10b981',
    'max_users' => 100,
    'max_agents' => 500,
    'created_at' => null
];

// ============================================================
// GET CURRENT USER ID (for logging)
// ============================================================
function getCurrentUserId() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if user is logged in
    if (isset($_SESSION['user_id'])) {
        return (int)$_SESSION['user_id'];
    }
    
    // Try to get admin user
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // Find first admin user
        $stmt = $conn->prepare("SELECT id FROM users WHERE role_id = 1 OR email = 'admin@5gguru.ng' OR email LIKE '%admin%' LIMIT 1");
        $stmt->execute();
        $user = $stmt->fetch();
        
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            return (int)$user['id'];
        }
        
        // If no admin user exists, check if there's any user at all
        $stmt = $conn->prepare("SELECT id FROM users LIMIT 1");
        $stmt->execute();
        $user = $stmt->fetch();
        
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            return (int)$user['id'];
        }
        
        return null;
    } catch (Exception $e) {
        return null;
    }
}

// ============================================================
// LOG ACTIVITY FUNCTION
// ============================================================
function logActivity($userId, $tenantId, $type, $description) {
    if (!$userId) return false;
    
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $sql = "INSERT INTO activity_logs (user_id, tenant_id, activity_type, description, ip_address, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        return $stmt->execute([
            $userId,
            $tenantId,
            $type,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);
    } catch (Exception $e) {
        // Log error but don't fail the main operation
        error_log("Failed to log activity: " . $e->getMessage());
        return false;
    }
}

// Get current user ID
$currentUserId = getCurrentUserId();

// ============================================================
// GET TENANT DATA FOR EDITING
// ============================================================
if ($is_edit) {
    $stmt = $conn->prepare("SELECT * FROM tenants WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $fetchedTenant = $stmt->fetch();
    
    if ($fetchedTenant) {
        $tenant = $fetchedTenant;
    } else {
        header("Location: tenants.php?error=" . urlencode("Tenant not found"));
        exit;
    }
}

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $type = $_POST['type'] ?? 'political_party';
    $subscription_plan = $_POST['subscription_plan'] ?? 'basic';
    $subscription_status = $_POST['subscription_status'] ?? 'trial';
    $contact_email = trim($_POST['contact_email'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $state_id = !empty($_POST['state_id']) ? (int)$_POST['state_id'] : null;
    $lga_id = !empty($_POST['lga_id']) ? (int)$_POST['lga_id'] : null;
    $primary_color = $_POST['primary_color'] ?? '#3b82f6';
    $secondary_color = $_POST['secondary_color'] ?? '#10b981';
    $max_users = (int)($_POST['max_users'] ?? 100);
    $max_agents = (int)($_POST['max_agents'] ?? 500);
    
    // Validate
    if (empty($name)) {
        $error = "Organization name is required.";
    } elseif (empty($slug)) {
        $error = "Slug is required.";
    } elseif (!preg_match('/^[a-z0-9-]+$/', $slug)) {
        $error = "Slug can only contain lowercase letters, numbers, and hyphens.";
    } else {
        try {
            // Check if slug is unique
            $checkStmt = $conn->prepare("SELECT id FROM tenants WHERE slug = ? AND id != ? AND deleted_at IS NULL");
            $checkStmt->execute([$slug, $tenant_id]);
            if ($checkStmt->fetch()) {
                $error = "Slug already exists. Please choose a different one.";
            } else {
                // Prepare data
                $data = [
                    'name' => $name,
                    'slug' => $slug,
                    'type' => $type,
                    'subscription_plan' => $subscription_plan,
                    'subscription_status' => $subscription_status,
                    'contact_email' => $contact_email,
                    'contact_phone' => $contact_phone,
                    'address' => $address,
                    'state_id' => $state_id,
                    'lga_id' => $lga_id,
                    'primary_color' => $primary_color,
                    'secondary_color' => $secondary_color,
                    'max_users' => $max_users,
                    'max_agents' => $max_agents,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                // Handle logo upload
                $logo_uploaded = false;
                if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = __DIR__ . '/../uploads/tenants/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
                    
                    if (!in_array($ext, $allowed_exts)) {
                        throw new Exception("Invalid file type. Allowed: " . implode(', ', $allowed_exts));
                    }
                    
                    // Check file size (max 2MB)
                    if ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
                        throw new Exception("File too large. Maximum size is 2MB.");
                    }
                    
                    $filename = 'tenant_' . ($tenant_id ?: time()) . '_' . uniqid() . '.' . $ext;
                    $filepath = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $filepath)) {
                        $data['logo_url'] = '/uploads/tenants/' . $filename;
                        $logo_uploaded = true;
                    } else {
                        throw new Exception("Failed to upload logo.");
                    }
                }
                
                // Start transaction
                $conn->beginTransaction();
                
                if ($is_edit) {
                    // Update existing tenant
                    $updateFields = [];
                    $updateParams = [];
                    
                    foreach ($data as $key => $value) {
                        $updateFields[] = "$key = ?";
                        $updateParams[] = $value;
                    }
                    $updateParams[] = $tenant_id;
                    
                    $sql = "UPDATE tenants SET " . implode(', ', $updateFields) . " WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute($updateParams);
                    
                    // Log activity
                    if ($currentUserId) {
                        logActivity($currentUserId, $tenant_id, 'tenant_updated', "Tenant updated: $name");
                    }
                    
                    $conn->commit();
                    $message = "Tenant updated successfully.";
                    
                    // Refresh tenant data
                    $stmt = $conn->prepare("SELECT * FROM tenants WHERE id = ? AND deleted_at IS NULL");
                    $stmt->execute([$tenant_id]);
                    $tenant = $stmt->fetch();
                    
                } else {
                    // Create new tenant
                    $data['uuid'] = uniqid() . '-' . bin2hex(random_bytes(8));
                    $data['created_by'] = $currentUserId ?? null;
                    $data['created_at'] = date('Y-m-d H:i:s');
                    
                    // Build insert query
                    $fields = array_keys($data);
                    $placeholders = rtrim(str_repeat('?, ', count($fields)), ', ');
                    $sql = "INSERT INTO tenants (" . implode(', ', $fields) . ") VALUES ($placeholders)";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute(array_values($data));
                    
                    $newId = (int)$conn->lastInsertId();
                    
                    // Log activity
                    if ($currentUserId && $newId) {
                        logActivity($currentUserId, $newId, 'tenant_created', "New tenant created: $name");
                    }
                    
                    $conn->commit();
                    $message = "Tenant created successfully.";
                    
                    // Redirect to edit page with success message
                    header("Location: tenant-edit.php?id=" . $newId . "&msg=" . urlencode($message));
                    exit;
                }
            }
        } catch (Exception $e) {
            // Rollback on error
            if (isset($conn) && $conn->inTransaction()) {
                $conn->rollBack();
            }
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Get success message from URL
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
}

// Re-fetch tenant data if editing and we have an ID
if ($is_edit && $tenant_id && !isset($tenant)) {
    $stmt = $conn->prepare("SELECT * FROM tenants WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $tenant = $stmt->fetch();
}

include 'includes/base.php';
?>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/header.php'; ?>

<main class="main-content">
    <div class="page-header">
        <div>
            <h1><?php echo $is_edit ? 'Edit Tenant' : 'Create Tenant'; ?></h1>
            <p class="subtitle"><?php echo $is_edit ? 'Update tenant information' : 'Add a new client organization'; ?></p>
        </div>
        <a href="tenants.php" class="btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Tenants
        </a>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="tenant-form">
        <div class="form-grid">
            <!-- ============================================================
            BASIC INFORMATION
            ============================================================ -->
            <div class="form-section">
                <h3><i class="fas fa-building"></i> Basic Information</h3>
                
                <div class="form-group">
                    <label for="name">Organization Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" required 
                           value="<?php echo htmlspecialchars($tenant['name'] ?? ''); ?>"
                           placeholder="e.g., APC Nigeria">
                </div>
                
                <div class="form-group">
                    <label for="slug">Slug <span class="required">*</span></label>
                    <input type="text" id="slug" name="slug" required 
                           value="<?php echo htmlspecialchars($tenant['slug'] ?? ''); ?>"
                           placeholder="e.g., apc-nigeria"
                           pattern="[a-z0-9-]+"
                           title="Only lowercase letters, numbers, and hyphens">
                    <small>Unique identifier used in URLs (lowercase, hyphens only)</small>
                </div>
                
                <div class="form-group">
                    <label for="type">Organization Type</label>
                    <select id="type" name="type">
                        <option value="political_party" <?php echo ($tenant['type'] ?? '') === 'political_party' ? 'selected' : ''; ?>>Political Party</option>
                        <option value="candidate" <?php echo ($tenant['type'] ?? '') === 'candidate' ? 'selected' : ''; ?>>Candidate</option>
                        <option value="ngo" <?php echo ($tenant['type'] ?? '') === 'ngo' ? 'selected' : ''; ?>>NGO</option>
                        <option value="observer_group" <?php echo ($tenant['type'] ?? '') === 'observer_group' ? 'selected' : ''; ?>>Observer Group</option>
                        <option value="cso" <?php echo ($tenant['type'] ?? '') === 'cso' ? 'selected' : ''; ?>>CSO</option>
                        <option value="research_institution" <?php echo ($tenant['type'] ?? '') === 'research_institution' ? 'selected' : ''; ?>>Research Institution</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="logo">Organization Logo</label>
                    <div class="file-upload">
                        <input type="file" id="logo" name="logo" accept="image/*">
                        <label for="logo" class="file-upload-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Choose logo file</span>
                        </label>
                        <?php if (!empty($tenant['logo_url'])): ?>
                        <div class="current-logo">
                            <img src="<?php echo htmlspecialchars($tenant['logo_url']); ?>" alt="Current Logo">
                            <span>Current logo</span>
                        </div>
                        <?php endif; ?>
                        <small>Supported: JPG, PNG, GIF, SVG, WEBP (Max 2MB)</small>
                    </div>
                </div>
            </div>

            <!-- ============================================================
            CONTACT INFORMATION
            ============================================================ -->
            <div class="form-section">
                <h3><i class="fas fa-address-card"></i> Contact Information</h3>
                
                <div class="form-group">
                    <label for="contact_email">Contact Email</label>
                    <input type="email" id="contact_email" name="contact_email" 
                           value="<?php echo htmlspecialchars($tenant['contact_email'] ?? ''); ?>"
                           placeholder="admin@organization.com">
                </div>
                
                <div class="form-group">
                    <label for="contact_phone">Contact Phone</label>
                    <input type="tel" id="contact_phone" name="contact_phone" 
                           value="<?php echo htmlspecialchars($tenant['contact_phone'] ?? ''); ?>"
                           placeholder="+2348005555555">
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" rows="3" 
                              placeholder="Street address, city, state"><?php echo htmlspecialchars($tenant['address'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="state_id">State</label>
                        <select id="state_id" name="state_id">
                            <option value="">Select State</option>
                            <?php
                            try {
                                $states = $conn->query("SELECT id, name FROM states WHERE is_active = 1 ORDER BY name");
                                while ($state = $states->fetch()) {
                                    $selected = ($tenant['state_id'] ?? '') == $state['id'] ? 'selected' : '';
                                    echo '<option value="' . $state['id'] . '" ' . $selected . '>' . htmlspecialchars($state['name']) . '</option>';
                                }
                            } catch (Exception $e) {
                                // Table might not exist yet
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="lga_id">LGA</label>
                        <select id="lga_id" name="lga_id">
                            <option value="">Select LGA</option>
                            <?php
                            try {
                                $lgas = $conn->query("SELECT id, name FROM lgas WHERE is_active = 1 ORDER BY name");
                                while ($lga = $lgas->fetch()) {
                                    $selected = ($tenant['lga_id'] ?? '') == $lga['id'] ? 'selected' : '';
                                    echo '<option value="' . $lga['id'] . '" ' . $selected . '>' . htmlspecialchars($lga['name']) . '</option>';
                                }
                            } catch (Exception $e) {
                                // Table might not exist yet
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- ============================================================
            SUBSCRIPTION & LIMITS
            ============================================================ -->
            <div class="form-section">
                <h3><i class="fas fa-crown"></i> Subscription & Limits</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="subscription_plan">Subscription Plan</label>
                        <select id="subscription_plan" name="subscription_plan">
                            <option value="free" <?php echo ($tenant['subscription_plan'] ?? '') === 'free' ? 'selected' : ''; ?>>Free</option>
                            <option value="basic" <?php echo ($tenant['subscription_plan'] ?? '') === 'basic' ? 'selected' : ''; ?>>Basic</option>
                            <option value="standard" <?php echo ($tenant['subscription_plan'] ?? '') === 'standard' ? 'selected' : ''; ?>>Standard</option>
                            <option value="premium" <?php echo ($tenant['subscription_plan'] ?? '') === 'premium' ? 'selected' : ''; ?>>Premium</option>
                            <option value="enterprise" <?php echo ($tenant['subscription_plan'] ?? '') === 'enterprise' ? 'selected' : ''; ?>>Enterprise</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="subscription_status">Status</label>
                        <select id="subscription_status" name="subscription_status">
                            <option value="trial" <?php echo ($tenant['subscription_status'] ?? '') === 'trial' ? 'selected' : ''; ?>>Trial</option>
                            <option value="active" <?php echo ($tenant['subscription_status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="suspended" <?php echo ($tenant['subscription_status'] ?? '') === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            <option value="expired" <?php echo ($tenant['subscription_status'] ?? '') === 'expired' ? 'selected' : ''; ?>>Expired</option>
                            <option value="cancelled" <?php echo ($tenant['subscription_status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="max_users">Max Users</label>
                        <input type="number" id="max_users" name="max_users" 
                               value="<?php echo htmlspecialchars($tenant['max_users'] ?? 100); ?>"
                               min="1" max="99999">
                    </div>
                    
                    <div class="form-group">
                        <label for="max_agents">Max Agents</label>
                        <input type="number" id="max_agents" name="max_agents" 
                               value="<?php echo htmlspecialchars($tenant['max_agents'] ?? 500); ?>"
                               min="1" max="99999">
                    </div>
                </div>
            </div>

            <!-- ============================================================
            BRANDING
            ============================================================ -->
            <div class="form-section">
                <h3><i class="fas fa-palette"></i> Branding</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="primary_color">Primary Color</label>
                        <div class="color-picker">
                            <input type="color" id="primary_color" name="primary_color" 
                                   value="<?php echo htmlspecialchars($tenant['primary_color'] ?? '#3b82f6'); ?>">
                            <input type="text" id="primary_color_hex" 
                                   value="<?php echo htmlspecialchars($tenant['primary_color'] ?? '#3b82f6'); ?>"
                                   pattern="^#[0-9a-fA-F]{6}$">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="secondary_color">Secondary Color</label>
                        <div class="color-picker">
                            <input type="color" id="secondary_color" name="secondary_color" 
                                   value="<?php echo htmlspecialchars($tenant['secondary_color'] ?? '#10b981'); ?>">
                            <input type="text" id="secondary_color_hex" 
                                   value="<?php echo htmlspecialchars($tenant['secondary_color'] ?? '#10b981'); ?>"
                                   pattern="^#[0-9a-fA-F]{6}$">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================
        FORM ACTIONS
        ============================================================ -->
        <div class="form-actions">
            <button type="submit" class="btn-primary">
                <i class="fas fa-save"></i> <?php echo $is_edit ? 'Update Tenant' : 'Create Tenant'; ?>
            </button>
            <a href="tenants.php" class="btn-secondary">Cancel</a>
        </div>
    </form>
</main>

<style>
/* Tenant Form Styles */
.tenant-form {
    background: white;
    border-radius: 16px;
    padding: 24px;
    border: 1px solid #eef3f8;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

@media (max-width: 1024px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
}

.form-section {
    padding: 20px;
    background: #f8faff;
    border-radius: 12px;
    border: 1px solid #eef3f8;
}

.form-section h3 {
    font-size: 0.95rem;
    font-weight: 600;
    color: #1b293e;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-section h3 i {
    color: #4f9cf7;
}

.form-group {
    margin-bottom: 16px;
}

.form-group:last-child {
    margin-bottom: 0;
}

.form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 500;
    color: #1f3149;
    margin-bottom: 6px;
}

.form-group .required {
    color: #ef4444;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #dce6f0;
    border-radius: 10px;
    font-size: 0.9rem;
    background: white;
    color: #1f3149;
    transition: 0.15s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #4f9cf7;
    box-shadow: 0 0 0 3px rgba(79, 156, 247, 0.1);
}

.form-group textarea {
    resize: vertical;
    min-height: 80px;
}

.form-group small {
    display: block;
    font-size: 0.7rem;
    color: #8b9bb5;
    margin-top: 4px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

@media (max-width: 480px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}

/* File Upload */
.file-upload {
    position: relative;
}

.file-upload input[type="file"] {
    position: absolute;
    opacity: 0;
    width: 0.1px;
    height: 0.1px;
}

.file-upload-label {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border: 2px dashed #dce6f0;
    border-radius: 10px;
    cursor: pointer;
    transition: 0.15s;
    color: #6d83a5;
}

.file-upload-label:hover {
    border-color: #4f9cf7;
    background: #f8faff;
}

.file-upload-label i {
    font-size: 1.2rem;
    color: #4f9cf7;
}

.current-logo {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 10px;
    padding: 8px 12px;
    background: #f0f6fe;
    border-radius: 8px;
}

.current-logo img {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    object-fit: cover;
}

.current-logo span {
    font-size: 0.8rem;
    color: #405473;
}

/* Color Picker */
.color-picker {
    display: flex;
    align-items: center;
    gap: 12px;
}

.color-picker input[type="color"] {
    width: 44px;
    height: 44px;
    padding: 2px;
    border: 2px solid #dce6f0;
    border-radius: 10px;
    cursor: pointer;
}

.color-picker input[type="text"] {
    flex: 1;
    padding: 10px 14px;
    border: 1px solid #dce6f0;
    border-radius: 10px;
    font-size: 0.9rem;
    font-family: monospace;
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #eef3f8;
}

/* Alerts */
.alert {
    padding: 14px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 12px;
}

.alert i {
    font-size: 1.2rem;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.alert-success i {
    color: #10b981;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.alert-error i {
    color: #ef4444;
}

@media (max-width: 768px) {
    .tenant-form {
        padding: 16px;
    }
    
    .form-section {
        padding: 16px;
    }
}
</style>

<script>
// ============================================================
// COLOR PICKER SYNC
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    // Primary color sync
    const primaryColor = document.getElementById('primary_color');
    const primaryHex = document.getElementById('primary_color_hex');
    if (primaryColor && primaryHex) {
        primaryColor.addEventListener('input', function() {
            primaryHex.value = this.value;
        });
        primaryHex.addEventListener('input', function() {
            if (this.value.match(/^#[0-9a-f]{6}$/i)) {
                primaryColor.value = this.value;
            }
        });
    }
    
    // Secondary color sync
    const secondaryColor = document.getElementById('secondary_color');
    const secondaryHex = document.getElementById('secondary_color_hex');
    if (secondaryColor && secondaryHex) {
        secondaryColor.addEventListener('input', function() {
            secondaryHex.value = this.value;
        });
        secondaryHex.addEventListener('input', function() {
            if (this.value.match(/^#[0-9a-f]{6}$/i)) {
                secondaryColor.value = this.value;
            }
        });
    }
    
    // Slug auto-generation from name
    const nameInput = document.getElementById('name');
    const slugInput = document.getElementById('slug');
    if (nameInput && slugInput) {
        nameInput.addEventListener('input', function() {
            if (!slugInput.dataset.manual) {
                slugInput.value = this.value
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-|-$/g, '');
            }
        });
        slugInput.addEventListener('input', function() {
            this.dataset.manual = 'true';
        });
    }
    
    // File upload label update
    const fileInput = document.getElementById('logo');
    const fileLabel = document.querySelector('.file-upload-label span');
    if (fileInput && fileLabel) {
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                fileLabel.textContent = this.files[0].name;
            } else {
                fileLabel.textContent = 'Choose logo file';
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>