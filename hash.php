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
$message_type = '';

// Initialize tenant data with defaults
$tenant = [
    'id' => 0,
    'name' => '',
    'slug' => '',
    'type' => 'political_party',
    'subscription_plan' => 'basic',
    'subscription_status' => 'trial',
    'subscription_end' => null,
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
    'max_storage_mb' => 10737418240,
    'created_at' => null,
    'settings_json' => null
];

// ============================================================
// GET CURRENT USER ID
// ============================================================
function getCurrentUserId() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['user_id'])) {
        return (int)$_SESSION['user_id'];
    }
    
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("SELECT id FROM users WHERE role_id = 1 OR email LIKE '%admin%' LIMIT 1");
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
// LOG ACTIVITY
// ============================================================
function logActivity($userId, $tenantId, $type, $description) {
    if (!$userId) return false;
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        $sql = "INSERT INTO activity_logs (user_id, tenant_id, activity_type, description, ip_address, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        return $stmt->execute([$userId, $tenantId, $type, $description, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
        return false;
    }
}

$currentUserId = getCurrentUserId();

// ============================================================
// GET TENANT DATA
// ============================================================
if ($is_edit) {
    $stmt = $conn->prepare("SELECT * FROM tenants WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $fetched = $stmt->fetch();
    if ($fetched) {
        $tenant = $fetched;
    } else {
        header("Location: tenants.php?error=" . urlencode("Tenant not found"));
        exit;
    }
}

// Get tenant subscriptions
$tenantSubscriptions = [];
$activeSub = null;
if ($is_edit && $tenant_id) {
    $subStmt = $conn->prepare("SELECT * FROM subscriptions WHERE tenant_id = ? ORDER BY created_at DESC");
    $subStmt->execute([$tenant_id]);
    $tenantSubscriptions = $subStmt->fetchAll();
    
    $activeStmt = $conn->prepare("SELECT * FROM subscriptions WHERE tenant_id = ? AND payment_status = 'paid' AND end_date >= CURDATE() ORDER BY end_date DESC LIMIT 1");
    $activeStmt->execute([$tenant_id]);
    $activeSub = $activeStmt->fetch();
}

// Get tenant stats
$tenantStats = null;
if ($is_edit && $tenant_id) {
    $statsStmt = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) FROM users WHERE tenant_id = ? AND deleted_at IS NULL) as total_users,
            (SELECT COUNT(*) FROM users WHERE tenant_id = ? AND status = 'active' AND deleted_at IS NULL) as active_users,
            (SELECT COUNT(*) FROM elections WHERE tenant_id = ? AND deleted_at IS NULL) as total_elections,
            (SELECT COUNT(*) FROM elections WHERE tenant_id = ? AND status = 'active' AND deleted_at IS NULL) as active_elections,
            (SELECT COUNT(*) FROM incidents WHERE tenant_id = ?) as total_incidents,
            (SELECT COUNT(*) FROM support_tickets WHERE tenant_id = ? AND status != 'closed') as open_tickets,
            (SELECT COUNT(*) FROM subscriptions WHERE tenant_id = ?) as total_subscriptions,
            (SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE tenant_id = ? AND status = 'paid') as total_revenue
    ");
    $statsStmt->execute([$tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id]);
    $tenantStats = $statsStmt->fetch();
}

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update';
    
    try {
        // Start transaction
        $conn->beginTransaction();
        
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
        $max_storage_mb = (int)($_POST['max_storage_mb'] ?? 10737418240);
        
        // Validate
        if (empty($name)) {
            throw new Exception("Organization name is required.");
        }
        if (empty($slug)) {
            throw new Exception("Slug is required.");
        }
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            throw new Exception("Slug can only contain lowercase letters, numbers, and hyphens.");
        }
        
        // Check slug uniqueness
        $checkStmt = $conn->prepare("SELECT id FROM tenants WHERE slug = ? AND id != ? AND deleted_at IS NULL");
        $checkStmt->execute([$slug, $tenant_id]);
        if ($checkStmt->fetch()) {
            throw new Exception("Slug already exists. Please choose a different one.");
        }
        
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
            'max_storage_mb' => $max_storage_mb,
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
        
        // Handle settings JSON
        if (isset($_POST['settings'])) {
            $data['settings_json'] = json_encode($_POST['settings']);
        }
        
        if ($is_edit) {
            // Update tenant
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
            
            logActivity($currentUserId, $tenant_id, 'tenant_updated', "Tenant updated: $name");
            $message = "Tenant updated successfully.";
            $message_type = 'success';
            
            // Commit transaction
            $conn->commit();
            
            // Refresh tenant data
            $stmt = $conn->prepare("SELECT * FROM tenants WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$tenant_id]);
            $tenant = $stmt->fetch();
            
        } else {
            // Create tenant
            $data['uuid'] = uniqid() . '-' . bin2hex(random_bytes(8));
            $data['created_by'] = $currentUserId ?? null;
            $data['created_at'] = date('Y-m-d H:i:s');
            
            $fields = array_keys($data);
            $placeholders = rtrim(str_repeat('?, ', count($fields)), ', ');
            $sql = "INSERT INTO tenants (" . implode(', ', $fields) . ") VALUES ($placeholders)";
            $stmt = $conn->prepare($sql);
            $stmt->execute(array_values($data));
            
            $newId = (int)$conn->lastInsertId();
            
            logActivity($currentUserId, $newId, 'tenant_created', "New tenant created: $name");
            
            // Commit transaction BEFORE redirect
            $conn->commit();
            
            $message = "Tenant created successfully.";
            $message_type = 'success';
            
            // Redirect after commit
            header("Location: tenant-edit.php?id=" . $newId . "&msg=" . urlencode($message));
            exit;
        }
        
    } catch (Exception $e) {
        // Rollback on error
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = $e->getMessage();
        $message_type = 'error';
    }
}

// Get success message from URL
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $message_type = 'success';
}

include 'includes/base.php';
?>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/header.php'; ?>

<main class="main-content">
    <!-- ============================================================
    PAGE HEADER
    ============================================================ -->
    <div class="page-header">
        <div class="header-left">
            <h1>
                <i class="fas fa-building" style="color:#4f9cf7;"></i>
                <?php echo $is_edit ? 'Edit Tenant' : 'Create Tenant'; ?>
                <?php if ($is_edit): ?>
                <span class="page-badge">#<?php echo $tenant['id']; ?></span>
                <?php endif; ?>
            </h1>
            <p class="subtitle">
                <?php echo $is_edit ? 'Update tenant information and manage settings' : 'Add a new client organization to the platform'; ?>
            </p>
        </div>
        <div class="header-actions">
            <?php if ($is_edit): ?>
            <a href="tenants.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Tenants
            </a>
            <?php else: ?>
            <a href="tenants.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Cancel
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================
    ALERTS
    ============================================================ -->
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type ?: 'success'; ?>">
        <i class="fas fa-<?php echo $message_type === 'error' ? 'exclamation-circle' : 'check-circle'; ?>"></i>
        <?php echo $message; ?>
        <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($error); ?>
        <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php endif; ?>

    <!-- ============================================================
    TENANT STATS BAR (Edit Mode Only)
    ============================================================ -->
    <?php if ($is_edit && $tenantStats): ?>
    <div class="tenant-stats-bar">
        <div class="stat-item">
            <span class="stat-value"><?php echo number_format($tenantStats['total_users'] ?? 0); ?></span>
            <span class="stat-label">Total Users</span>
        </div>
        <div class="stat-item">
            <span class="stat-value"><?php echo number_format($tenantStats['active_users'] ?? 0); ?></span>
            <span class="stat-label">Active Users</span>
        </div>
        <div class="stat-item">
            <span class="stat-value"><?php echo number_format($tenantStats['total_elections'] ?? 0); ?></span>
            <span class="stat-label">Elections</span>
        </div>
        <div class="stat-item">
            <span class="stat-value"><?php echo number_format($tenantStats['total_incidents'] ?? 0); ?></span>
            <span class="stat-label">Incidents</span>
        </div>
        <div class="stat-item">
            <span class="stat-value"><?php echo number_format($tenantStats['open_tickets'] ?? 0); ?></span>
            <span class="stat-label">Open Tickets</span>
        </div>
        <div class="stat-item">
            <span class="stat-value">₦<?php echo number_format($tenantStats['total_revenue'] ?? 0, 2); ?></span>
            <span class="stat-label">Total Revenue</span>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================================
    MAIN FORM
    ============================================================ -->
    <form method="POST" enctype="multipart/form-data" class="tenant-form" id="tenantForm">
        <input type="hidden" name="action" value="<?php echo $is_edit ? 'update' : 'create'; ?>">
        
        <!-- ============================================================
        FORM TABS
        ============================================================ -->
        <div class="form-tabs">
            <button type="button" class="tab-btn active" data-tab="basic">
                <i class="fas fa-building"></i> Basic Info
            </button>
            <button type="button" class="tab-btn" data-tab="contact">
                <i class="fas fa-address-card"></i> Contact
            </button>
            <button type="button" class="tab-btn" data-tab="subscription">
                <i class="fas fa-crown"></i> Subscription
            </button>
            <button type="button" class="tab-btn" data-tab="branding">
                <i class="fas fa-palette"></i> Branding
            </button>
            <button type="button" class="tab-btn" data-tab="limits">
                <i class="fas fa-sliders-h"></i> Limits
            </button>
            <?php if ($is_edit): ?>
            <button type="button" class="tab-btn" data-tab="history">
                <i class="fas fa-history"></i> History
            </button>
            <?php endif; ?>
        </div>

        <!-- ============================================================
        TAB 1: BASIC INFORMATION
        ============================================================ -->
        <div class="tab-content active" id="tab-basic">
            <div class="form-section">
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Organization Name <span class="required">*</span></label>
                        <input type="text" id="name" name="name" required 
                               value="<?php echo htmlspecialchars($tenant['name'] ?? ''); ?>"
                               placeholder="e.g., APC Nigeria"
                               class="form-control">
                        <small>Full legal name of the organization</small>
                    </div>
                    <div class="form-group">
                        <label for="slug">Slug <span class="required">*</span></label>
                        <input type="text" id="slug" name="slug" required 
                               value="<?php echo htmlspecialchars($tenant['slug'] ?? ''); ?>"
                               placeholder="e.g., apc-nigeria"
                               pattern="[a-z0-9-]+"
                               title="Only lowercase letters, numbers, and hyphens"
                               class="form-control">
                        <small>Unique URL identifier (lowercase, hyphens only)</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="type">Organization Type</label>
                        <select id="type" name="type" class="form-control">
                            <option value="political_party" <?php echo ($tenant['type'] ?? '') === 'political_party' ? 'selected' : ''; ?>>
                                Political Party
                            </option>
                            <option value="candidate" <?php echo ($tenant['type'] ?? '') === 'candidate' ? 'selected' : ''; ?>>
                                Candidate
                            </option>
                            <option value="ngo" <?php echo ($tenant['type'] ?? '') === 'ngo' ? 'selected' : ''; ?>>
                                NGO
                            </option>
                            <option value="observer_group" <?php echo ($tenant['type'] ?? '') === 'observer_group' ? 'selected' : ''; ?>>
                                Observer Group
                            </option>
                            <option value="cso" <?php echo ($tenant['type'] ?? '') === 'cso' ? 'selected' : ''; ?>>
                                CSO
                            </option>
                            <option value="research_institution" <?php echo ($tenant['type'] ?? '') === 'research_institution' ? 'selected' : ''; ?>>
                                Research Institution
                            </option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="logo">Organization Logo</label>
                        <div class="file-upload-wrapper">
                            <div class="file-upload-area" id="fileUploadArea">
                                <input type="file" id="logo" name="logo" accept="image/*" class="file-input">
                                <div class="file-upload-content">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Drop logo here or click to browse</span>
                                    <small>JPG, PNG, GIF, SVG, WEBP (Max 2MB)</small>
                                </div>
                            </div>
                            <?php if (!empty($tenant['logo_url'])): ?>
                            <div class="current-logo">
                                <img src="<?php echo htmlspecialchars($tenant['logo_url']); ?>" alt="Current Logo">
                                <div class="logo-info">
                                    <span class="logo-name">Current Logo</span>
                                    <button type="button" class="btn-remove-logo" onclick="removeLogo()">
                                        <i class="fas fa-times"></i> Remove
                                    </button>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================
        TAB 2: CONTACT INFORMATION
        ============================================================ -->
        <div class="tab-content" id="tab-contact">
            <div class="form-section">
                <div class="form-row">
                    <div class="form-group">
                        <label for="contact_email">Contact Email</label>
                        <input type="email" id="contact_email" name="contact_email" 
                               value="<?php echo htmlspecialchars($tenant['contact_email'] ?? ''); ?>"
                               placeholder="admin@organization.com"
                               class="form-control">
                        <small>Primary contact email for the organization</small>
                    </div>
                    <div class="form-group">
                        <label for="contact_phone">Contact Phone</label>
                        <input type="tel" id="contact_phone" name="contact_phone" 
                               value="<?php echo htmlspecialchars($tenant['contact_phone'] ?? ''); ?>"
                               placeholder="+2348005555555"
                               class="form-control">
                        <small>Primary contact phone number</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" rows="3" 
                              placeholder="Street address, city, state"
                              class="form-control"><?php echo htmlspecialchars($tenant['address'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="state_id">State</label>
                        <select id="state_id" name="state_id" class="form-control">
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
                        <select id="lga_id" name="lga_id" class="form-control">
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
        </div>

        <!-- ============================================================
        TAB 3: SUBSCRIPTION
        ============================================================ -->
        <div class="tab-content" id="tab-subscription">
            <div class="form-section">
                <div class="form-row">
                    <div class="form-group">
                        <label for="subscription_plan">Subscription Plan</label>
                        <select id="subscription_plan" name="subscription_plan" class="form-control" onchange="updatePlanDetails()">
                            <option value="free" <?php echo ($tenant['subscription_plan'] ?? '') === 'free' ? 'selected' : ''; ?>>Free</option>
                            <option value="basic" <?php echo ($tenant['subscription_plan'] ?? '') === 'basic' ? 'selected' : ''; ?>>Basic</option>
                            <option value="standard" <?php echo ($tenant['subscription_plan'] ?? '') === 'standard' ? 'selected' : ''; ?>>Standard</option>
                            <option value="premium" <?php echo ($tenant['subscription_plan'] ?? '') === 'premium' ? 'selected' : ''; ?>>Premium</option>
                            <option value="enterprise" <?php echo ($tenant['subscription_plan'] ?? '') === 'enterprise' ? 'selected' : ''; ?>>Enterprise</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="subscription_status">Status</label>
                        <select id="subscription_status" name="subscription_status" class="form-control">
                            <option value="trial" <?php echo ($tenant['subscription_status'] ?? '') === 'trial' ? 'selected' : ''; ?>>Trial</option>
                            <option value="active" <?php echo ($tenant['subscription_status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="suspended" <?php echo ($tenant['subscription_status'] ?? '') === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            <option value="expired" <?php echo ($tenant['subscription_status'] ?? '') === 'expired' ? 'selected' : ''; ?>>Expired</option>
                            <option value="cancelled" <?php echo ($tenant['subscription_status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                </div>
                
                <div id="planDetails" class="plan-details">
                    <!-- Dynamic plan details will be shown here -->
                </div>
                
                <?php if ($is_edit): ?>
                <!-- Subscription History -->
                <div class="subscription-management">
                    <h4>Subscription History</h4>
                    <?php if (!empty($tenantSubscriptions)): ?>
                    <div class="subscription-history">
                        <table class="subscription-table">
                            <thead>
                                <tr>
                                    <th>Plan</th>
                                    <th>Amount</th>
                                    <th>Period</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tenantSubscriptions as $sub): ?>
                                <tr>
                                    <td>
                                        <span class="plan-badge <?php echo $sub['plan']; ?>">
                                            <?php echo ucfirst($sub['plan']); ?>
                                        </span>
                                    </td>
                                    <td>₦<?php echo number_format($sub['amount'], 2); ?></td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($sub['start_date'])); ?> - 
                                        <?php echo date('M d, Y', strtotime($sub['end_date'])); ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $sub['payment_status']; ?>">
                                            <?php echo ucfirst($sub['payment_status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="no-data">No subscription history available</p>
                    <?php endif; ?>
                    
                    <div class="subscription-actions">
                        <a href="tenant-subscriptions.php?tenant_id=<?php echo $tenant_id; ?>" class="btn-secondary">
                            <i class="fas fa-crown"></i> Manage Subscriptions
                        </a>
                        <button type="button" class="btn-primary" onclick="addSubscription(<?php echo $tenant_id; ?>)">
                            <i class="fas fa-plus"></i> Add Subscription
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ============================================================
        TAB 4: BRANDING
        ============================================================ -->
        <div class="tab-content" id="tab-branding">
            <div class="form-section">
                <div class="form-row">
                    <div class="form-group">
                        <label for="primary_color">Primary Color</label>
                        <div class="color-picker-wrapper">
                            <input type="color" id="primary_color" name="primary_color" 
                                   value="<?php echo htmlspecialchars($tenant['primary_color'] ?? '#3b82f6'); ?>"
                                   class="color-picker">
                            <input type="text" id="primary_color_hex" 
                                   value="<?php echo htmlspecialchars($tenant['primary_color'] ?? '#3b82f6'); ?>"
                                   pattern="^#[0-9a-fA-F]{6}$"
                                   class="color-hex-input">
                            <div class="color-preview" id="primaryPreview" 
                                 style="background-color: <?php echo htmlspecialchars($tenant['primary_color'] ?? '#3b82f6'); ?>;">
                            </div>
                        </div>
                        <small>Main brand color for the tenant interface</small>
                    </div>
                    <div class="form-group">
                        <label for="secondary_color">Secondary Color</label>
                        <div class="color-picker-wrapper">
                            <input type="color" id="secondary_color" name="secondary_color" 
                                   value="<?php echo htmlspecialchars($tenant['secondary_color'] ?? '#10b981'); ?>"
                                   class="color-picker">
                            <input type="text" id="secondary_color_hex" 
                                   value="<?php echo htmlspecialchars($tenant['secondary_color'] ?? '#10b981'); ?>"
                                   pattern="^#[0-9a-fA-F]{6}$"
                                   class="color-hex-input">
                            <div class="color-preview" id="secondaryPreview" 
                                 style="background-color: <?php echo htmlspecialchars($tenant['secondary_color'] ?? '#10b981'); ?>;">
                            </div>
                        </div>
                        <small>Secondary brand color for accents</small>
                    </div>
                </div>
                
                <div class="brand-preview">
                    <h4>Brand Preview</h4>
                    <div class="preview-box" id="brandPreview">
                        <div class="preview-header" style="background-color: <?php echo htmlspecialchars($tenant['primary_color'] ?? '#3b82f6'); ?>;">
                            <span style="color:white;">Tenant Brand</span>
                            <span class="preview-badge" style="background-color: <?php echo htmlspecialchars($tenant['secondary_color'] ?? '#10b981'); ?>;">
                                Preview
                            </span>
                        </div>
                        <div class="preview-content">
                            <p style="color: <?php echo htmlspecialchars($tenant['primary_color'] ?? '#3b82f6'); ?>;">
                                <i class="fas fa-check-circle" style="color: <?php echo htmlspecialchars($tenant['secondary_color'] ?? '#10b981'); ?>;"></i>
                                Brand colors preview
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================
        TAB 5: LIMITS
        ============================================================ -->
        <div class="tab-content" id="tab-limits">
            <div class="form-section">
                <div class="form-row">
                    <div class="form-group">
                        <label for="max_users">Maximum Users</label>
                        <input type="number" id="max_users" name="max_users" 
                               value="<?php echo htmlspecialchars($tenant['max_users'] ?? 100); ?>"
                               min="1" max="999999"
                               class="form-control">
                        <small>Maximum number of users allowed for this tenant</small>
                    </div>
                    <div class="form-group">
                        <label for="max_agents">Maximum Agents</label>
                        <input type="number" id="max_agents" name="max_agents" 
                               value="<?php echo htmlspecialchars($tenant['max_agents'] ?? 500); ?>"
                               min="1" max="999999"
                               class="form-control">
                        <small>Maximum number of agents allowed</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="max_storage_mb">Storage Limit (MB)</label>
                    <input type="number" id="max_storage_mb" name="max_storage_mb" 
                           value="<?php echo htmlspecialchars(($tenant['max_storage_mb'] ?? 10737418240) / 1024 / 1024); ?>"
                           min="100" max="1048576"
                           class="form-control">
                    <small>Maximum storage space in MB (1 GB = 1024 MB)</small>
                </div>
                
                <div class="usage-indicator">
                    <h4>Usage Statistics</h4>
                    <div class="usage-item">
                        <span class="usage-label">User Usage</span>
                        <div class="usage-bar">
                            <div class="usage-fill" style="width: <?php echo isset($tenantStats) ? min(($tenantStats['total_users'] / $tenant['max_users']) * 100, 100) : 0; ?>%;">
                            </div>
                        </div>
                        <span class="usage-text">
                            <?php echo $tenantStats['total_users'] ?? 0; ?> / <?php echo $tenant['max_users']; ?>
                        </span>
                    </div>
                    <div class="usage-item">
                        <span class="usage-label">Agent Usage</span>
                        <div class="usage-bar">
                            <div class="usage-fill" style="width: 0%;"></div>
                        </div>
                        <span class="usage-text">0 / <?php echo $tenant['max_agents']; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================
        TAB 6: HISTORY (Edit Mode Only)
        ============================================================ -->
        <?php if ($is_edit): ?>
        <div class="tab-content" id="tab-history">
            <div class="form-section">
                <div class="history-info">
                    <div class="history-item">
                        <span class="history-label">Created</span>
                        <span class="history-value">
                            <?php echo date('F d, Y H:i:s', strtotime($tenant['created_at'])); ?>
                        </span>
                    </div>
                    <div class="history-item">
                        <span class="history-label">Last Updated</span>
                        <span class="history-value">
                            <?php echo date('F d, Y H:i:s', strtotime($tenant['updated_at'] ?? $tenant['created_at'])); ?>
                        </span>
                    </div>
                    <div class="history-item">
                        <span class="history-label">Created By</span>
                        <span class="history-value">
                            <?php 
                            if ($tenant['created_by']) {
                                $userStmt = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
                                $userStmt->execute([$tenant['created_by']]);
                                $user = $userStmt->fetch();
                                echo $user ? htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) : 'System';
                            } else {
                                echo 'System';
                            }
                            ?>
                        </span>
                    </div>
                    <?php if ($tenant['deleted_at']): ?>
                    <div class="history-item">
                        <span class="history-label">Deleted</span>
                        <span class="history-value" style="color:#ef4444;">
                            <?php echo date('F d, Y H:i:s', strtotime($tenant['deleted_at'])); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Activity Log -->
                <div class="recent-activity">
                    <h4>Recent Activity</h4>
                    <?php
                    $activityStmt = $conn->prepare("
                        SELECT * FROM activity_logs 
                        WHERE tenant_id = ? 
                        ORDER BY created_at DESC 
                        LIMIT 10
                    ");
                    $activityStmt->execute([$tenant_id]);
                    $activities = $activityStmt->fetchAll();
                    ?>
                    <?php if (!empty($activities)): ?>
                    <div class="activity-timeline">
                        <?php foreach ($activities as $activity): ?>
                        <div class="timeline-item">
                            <div class="timeline-icon">
                                <?php if ($activity['activity_type'] === 'login'): ?>
                                <i class="fas fa-sign-in-alt" style="color:#4f9cf7;"></i>
                                <?php elseif ($activity['activity_type'] === 'logout'): ?>
                                <i class="fas fa-sign-out-alt" style="color:#f59e0b;"></i>
                                <?php elseif ($activity['activity_type'] === 'tenant_created'): ?>
                                <i class="fas fa-plus-circle" style="color:#10b981;"></i>
                                <?php elseif ($activity['activity_type'] === 'tenant_updated'): ?>
                                <i class="fas fa-edit" style="color:#8b5cf6;"></i>
                                <?php else: ?>
                                <i class="fas fa-circle" style="color:#a0b8d4;"></i>
                                <?php endif; ?>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-text"><?php echo htmlspecialchars($activity['description']); ?></div>
                                <div class="timeline-time">
                                    <?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?>
                                    <span class="timeline-ip"><?php echo htmlspecialchars($activity['ip_address'] ?? ''); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="audit-logs.php?tenant=<?php echo $tenant_id; ?>" class="btn-secondary" style="margin-top:12px;">
                        <i class="fas fa-history"></i> View All Activity
                    </a>
                    <?php else: ?>
                    <p class="no-data">No recent activity</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============================================================
        FORM ACTIONS
        ============================================================ -->
        <div class="form-actions">
            <div class="form-actions-left">
                <?php if ($is_edit): ?>
                <button type="button" class="btn-danger" onclick="deleteTenant(<?php echo $tenant_id; ?>)">
                    <i class="fas fa-trash"></i> Delete Tenant
                </button>
                <?php endif; ?>
            </div>
            <div class="form-actions-right">
                <a href="tenants.php" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> 
                    <?php echo $is_edit ? 'Update Tenant' : 'Create Tenant'; ?>
                </button>
            </div>
        </div>
    </form>
</main>
v
<!-- ============================================================
STYLES
============================================================ -->
<style>
/* ============================================================
   TENANT FORM STYLES
   ============================================================ */

/* Form Container */
.tenant-form {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid #eef3f8;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

/* Form Tabs */
.form-tabs {
    display: flex;
    background: #f8faff;
    border-bottom: 1px solid #eef3f8;
    padding: 0 24px;
    overflow-x: auto;
    flex-wrap: nowrap;
}

.tab-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 16px 20px;
    background: none;
    border: none;
    color: #6d83a5;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    white-space: nowrap;
    border-bottom: 3px solid transparent;
}

.tab-btn:hover {
    color: #1f3149;
    background: rgba(79, 156, 247, 0.05);
}

.tab-btn.active {
    color: #4f9cf7;
    border-bottom-color: #4f9cf7;
}

.tab-btn i {
    font-size: 1rem;
}

/* Tab Content */
.tab-content {
    display: none;
    padding: 24px;
}

.tab-content.active {
    display: block;
    animation: fadeIn 0.3s ease;
}

/* Form Sections */
.form-section {
    max-width: 100%;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
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

.form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #dce6f0;
    border-radius: 10px;
    font-size: 0.9rem;
    background: white;
    color: #1f3149;
    transition: all 0.2s ease;
}

.form-control:focus {
    outline: none;
    border-color: #4f9cf7;
    box-shadow: 0 0 0 3px rgba(79, 156, 247, 0.1);
}

.form-control:disabled {
    background: #f8faff;
    cursor: not-allowed;
}

select.form-control {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%238b9bb5' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 36px;
}

textarea.form-control {
    resize: vertical;
    min-height: 80px;
}

.form-group small {
    display: block;
    font-size: 0.7rem;
    color: #8b9bb5;
    margin-top: 4px;
}

/* File Upload */
.file-upload-wrapper {
    position: relative;
}

.file-upload-area {
    border: 2px dashed #dce6f0;
    border-radius: 10px;
    padding: 30px 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.file-upload-area:hover {
    border-color: #4f9cf7;
    background: #f8faff;
}

.file-upload-area.dragover {
    border-color: #4f9cf7;
    background: #e8f0fe;
}

.file-input {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
}

.file-upload-content i {
    font-size: 2.5rem;
    color: #8b9bb5;
    display: block;
    margin-bottom: 8px;
}

.file-upload-content span {
    display: block;
    font-size: 0.9rem;
    color: #1f3149;
}

.file-upload-content small {
    display: block;
    font-size: 0.7rem;
    color: #8b9bb5;
    margin-top: 4px;
}

.current-logo {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-top: 12px;
    padding: 12px 16px;
    background: #f8faff;
    border-radius: 10px;
    border: 1px solid #eef3f8;
}

.current-logo img {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    object-fit: cover;
}

.logo-info {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.logo-name {
    font-weight: 500;
    color: #0b1a33;
}

.btn-remove-logo {
    background: none;
    border: none;
    color: #ef4444;
    cursor: pointer;
    font-size: 0.8rem;
    padding: 4px 12px;
    border-radius: 6px;
    transition: 0.15s;
}

.btn-remove-logo:hover {
    background: #fee2e2;
}

/* Color Picker */
.color-picker-wrapper {
    display: flex;
    align-items: center;
    gap: 12px;
}

.color-picker {
    width: 44px;
    height: 44px;
    padding: 2px;
    border: 2px solid #dce6f0;
    border-radius: 10px;
    cursor: pointer;
    flex-shrink: 0;
}

.color-hex-input {
    flex: 1;
    padding: 10px 14px;
    border: 1px solid #dce6f0;
    border-radius: 10px;
    font-size: 0.9rem;
    font-family: monospace;
    background: white;
    color: #1f3149;
    transition: all 0.2s ease;
}

.color-hex-input:focus {
    outline: none;
    border-color: #4f9cf7;
    box-shadow: 0 0 0 3px rgba(79, 156, 247, 0.1);
}

.color-preview {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    border: 2px solid #dce6f0;
    flex-shrink: 0;
    transition: background-color 0.3s ease;
}

/* Brand Preview */
.brand-preview {
    margin-top: 20px;
}

.brand-preview h4 {
    font-size: 0.9rem;
    font-weight: 600;
    color: #1f3149;
    margin-bottom: 12px;
}

.preview-box {
    border: 1px solid #eef3f8;
    border-radius: 10px;
    overflow: hidden;
    max-width: 400px;
}

.preview-header {
    padding: 16px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: white;
    transition: background-color 0.3s ease;
}

.preview-badge {
    padding: 2px 12px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 500;
    transition: background-color 0.3s ease;
}

.preview-content {
    padding: 20px;
    background: #f8faff;
}

.preview-content p {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    transition: color 0.3s ease;
}

/* Plan Details */
.plan-details {
    margin-top: 16px;
    padding: 16px;
    background: #f8faff;
    border-radius: 10px;
    border: 1px solid #eef3f8;
}

.plan-details h4 {
    font-size: 0.9rem;
    font-weight: 600;
    color: #1f3149;
    margin-bottom: 8px;
}

.plan-features {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-top: 8px;
}

.plan-feature {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.85rem;
    color: #1f3149;
}

.plan-feature i {
    color: #10b981;
    font-size: 0.8rem;
}

/* Subscription Management */
.subscription-management {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eef3f8;
}

.subscription-management h4 {
    font-size: 0.9rem;
    font-weight: 600;
    color: #1f3149;
    margin-bottom: 12px;
}

.subscription-history {
    background: #f8faff;
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid #eef3f8;
    margin-bottom: 12px;
}

.subscription-table {
    width: 100%;
    font-size: 0.85rem;
    border-collapse: collapse;
}

.subscription-table th {
    padding: 10px 14px;
    text-align: left;
    font-weight: 600;
    color: #405473;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: #f0f4fa;
    border-bottom: 1px solid #eef3f8;
}

.subscription-table td {
    padding: 10px 14px;
    border-bottom: 1px solid #f0f4fa;
}

.subscription-table tr:last-child td {
    border-bottom: none;
}

.subscription-actions {
    display: flex;
    gap: 8px;
    margin-top: 12px;
}

/* Tenant Stats Bar */
.tenant-stats-bar {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 12px;
    padding: 16px 20px;
    background: white;
    border-radius: 14px;
    margin-bottom: 24px;
    border: 1px solid #eef3f8;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
}

.tenant-stats-bar .stat-item {
    text-align: center;
}

.tenant-stats-bar .stat-value {
    font-size: 1.2rem;
    font-weight: 700;
    color: #0b1a33;
    display: block;
}

.tenant-stats-bar .stat-label {
    font-size: 0.65rem;
    color: #8b9bb5;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

/* Usage Indicator */
.usage-indicator {
    margin-top: 16px;
    padding: 16px;
    background: #f8faff;
    border-radius: 10px;
    border: 1px solid #eef3f8;
}

.usage-indicator h4 {
    font-size: 0.9rem;
    font-weight: 600;
    color: #1f3149;
    margin-bottom: 12px;
}

.usage-item {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
}

.usage-item:last-child {
    margin-bottom: 0;
}

.usage-label {
    font-size: 0.8rem;
    color: #6d83a5;
    min-width: 80px;
}

.usage-bar {
    flex: 1;
    height: 6px;
    background: #eef3f8;
    border-radius: 10px;
    overflow: hidden;
}

.usage-fill {
    height: 100%;
    background: linear-gradient(90deg, #4f9cf7, #3b82d6);
    border-radius: 10px;
    transition: width 0.6s ease;
}

.usage-text {
    font-size: 0.75rem;
    color: #6d83a5;
    min-width: 80px;
    text-align: right;
}

/* History */
.history-info {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 24px;
}

.history-item {
    padding: 12px 16px;
    background: #f8faff;
    border-radius: 8px;
    border: 1px solid #eef3f8;
}

.history-label {
    font-size: 0.7rem;
    color: #8b9bb5;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    display: block;
}

.history-value {
    font-size: 0.9rem;
    font-weight: 500;
    color: #0b1a33;
    display: block;
    margin-top: 2px;
}

/* Activity Timeline */
.activity-timeline {
    max-height: 300px;
    overflow-y: auto;
}

.timeline-item {
    display: flex;
    gap: 14px;
    padding: 10px 0;
    border-bottom: 1px solid #f0f4fa;
}

.timeline-item:last-child {
    border-bottom: none;
}

.timeline-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #f0f4fa;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 0.8rem;
}

.timeline-content {
    flex: 1;
}

.timeline-text {
    font-size: 0.85rem;
    color: #1f3149;
}

.timeline-time {
    font-size: 0.7rem;
    color: #8b9bb5;
    margin-top: 2px;
}

.timeline-ip {
    margin-left: 8px;
    padding: 1px 8px;
    background: #f0f4fa;
    border-radius: 30px;
    font-size: 0.6rem;
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    background: #f8faff;
    border-top: 1px solid #eef3f8;
    flex-wrap: wrap;
    gap: 12px;
}

.form-actions-left {
    display: flex;
    gap: 8px;
}

.form-actions-right {
    display: flex;
    gap: 8px;
}

.btn-danger {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
    padding: 10px 20px;
    border-radius: 10px;
    font-weight: 500;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: all 0.15s;
}

.btn-danger:hover {
    background: #fecaca;
}

.no-data {
    color: #8b9bb5;
    font-size: 0.85rem;
    padding: 12px 0;
}

/* ============================================================
   RESPONSIVE
   ============================================================ */
@media (max-width: 1024px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .form-tabs {
        padding: 0 16px;
    }
    
    .tab-btn {
        padding: 12px 16px;
        font-size: 0.8rem;
    }
    
    .tab-content {
        padding: 16px;
    }
    
    .history-info {
        grid-template-columns: 1fr;
    }
    
    .plan-features {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .tenant-stats-bar {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .form-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .form-actions-left,
    .form-actions-right {
        justify-content: center;
    }
    
    .form-actions .btn-primary,
    .form-actions .btn-secondary,
    .form-actions .btn-danger {
        flex: 1;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .tenant-stats-bar {
        grid-template-columns: 1fr 1fr;
    }
    
    .tab-btn span {
        display: none;
    }
    
    .tab-btn i {
        font-size: 1.2rem;
    }
    
    .color-picker-wrapper {
        flex-wrap: wrap;
    }
    
    .subscription-actions {
        flex-direction: column;
    }
    
    .subscription-actions .btn-primary,
    .subscription-actions .btn-secondary {
        justify-content: center;
    }
}

/* ============================================================
   ANIMATIONS
   ============================================================ */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
<!-- ============================================================
JAVASCRIPT (Same as before - kept for brevity)
============================================================ -->
<script>
/* ... (keep all the JavaScript from your original file) ... */
</script>

<?php include 'includes/footer.php'; ?>