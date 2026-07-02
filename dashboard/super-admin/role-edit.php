<?php
$page_title = "Edit Role";
require_once 'includes/db.php';
$db = Database::getInstance()->getConnection();

// ============================================================
// GET ROLE DATA
// ============================================================
$role_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$error = '';
$message_type = '';

if (!$role_id) {
    header('Location: roles.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update':
                $name = trim($_POST['name'] ?? '');
                $slug = trim($_POST['slug'] ?? '');
                $level = trim($_POST['level'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $permissions = $_POST['permissions'] ?? [];
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if (empty($name) || empty($slug) || empty($level)) {
                    throw new Exception("Name, slug, and level are required.");
                }
                
                // Check if slug exists (excluding current role)
                $check = $db->prepare("SELECT id FROM roles WHERE slug = ? AND tenant_id IS NULL AND id != ?");
                $check->execute([$slug, $role_id]);
                if ($check->fetch()) {
                    throw new Exception("Role slug '{$slug}' already exists.");
                }
                
                // Check if system role
                $system_check = $db->prepare("SELECT is_system FROM roles WHERE id = ?");
                $system_check->execute([$role_id]);
                $is_system = $system_check->fetch()['is_system'] ?? 0;
                
                $permissions_json = json_encode($permissions);
                
                $sql = "UPDATE roles SET name = ?, level = ?, description = ?, permissions_json = ?, is_active = ?, updated_at = NOW()";
                $params = [$name, $level, $description, $permissions_json, $is_active];
                
                if (!$is_system) {
                    $sql .= ", slug = ?";
                    $params[] = $slug;
                }
                
                $sql .= " WHERE id = ?";
                $params[] = $role_id;
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                
                $message = "Role '{$name}' updated successfully.";
                $message_type = 'success';
                break;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
        $message_type = 'error';
    }
}

// Get role data
$stmt = $db->prepare("SELECT * FROM roles WHERE id = ? AND tenant_id IS NULL");
$stmt->execute([$role_id]);
$role = $stmt->fetch();

if (!$role) {
    header('Location: roles.php');
    exit;
}

$isSystem = ($role['is_system'] ?? 0) == 1;
$rolePermissions = json_decode($role['permissions_json'] ?? '{}', true);

// Get all available permissions
$permissions = [
    'users' => [
        'label' => 'Users',
        'permissions' => [
            'create_users' => 'Create Users',
            'edit_users' => 'Edit Users',
            'delete_users' => 'Delete Users',
            'view_users' => 'View Users',
            'manage_roles' => 'Manage Roles'
        ]
    ],
    'elections' => [
        'label' => 'Elections',
        'permissions' => [
            'create_elections' => 'Create Elections',
            'edit_elections' => 'Edit Elections',
            'delete_elections' => 'Delete Elections',
            'view_elections' => 'View Elections',
            'manage_elections' => 'Manage Elections',
            'manage_candidates' => 'Manage Candidates'
        ]
    ],
    'results' => [
        'label' => 'Results',
        'permissions' => [
            'submit_results' => 'Submit Results',
            'verify_results' => 'Verify Results',
            'view_results' => 'View Results',
            'manage_results' => 'Manage Results',
            'publish_results' => 'Publish Results',
            'manage_reports' => 'Manage Reports'
        ]
    ],
    'finance' => [
        'label' => 'Finance',
        'permissions' => [
            'view_finance' => 'View Finance',
            'manage_finance' => 'Manage Finance',
            'create_budgets' => 'Create Budgets',
            'manage_expenses' => 'Manage Expenses',
            'view_reports' => 'View Financial Reports'
        ]
    ],
    'incidents' => [
        'label' => 'Incidents',
        'permissions' => [
            'report_incidents' => 'Report Incidents',
            'view_incidents' => 'View Incidents',
            'manage_incidents' => 'Manage Incidents',
            'resolve_incidents' => 'Resolve Incidents'
        ]
    ],
    'agents' => [
        'label' => 'Agents',
        'permissions' => [
            'manage_agents' => 'Manage Agents',
            'assign_agents' => 'Assign Agents',
            'view_agents' => 'View Agents',
            'manage_agent_payments' => 'Manage Agent Payments'
        ]
    ],
    'audit' => [
        'label' => 'Audit & Security',
        'permissions' => [
            'view_audit_logs' => 'View Audit Logs',
            'manage_security' => 'Manage Security',
            'view_activity_logs' => 'View Activity Logs'
        ]
    ],
    'tenants' => [
        'label' => 'Tenants',
        'permissions' => [
            'manage_tenants' => 'Manage Tenants',
            'view_tenants' => 'View Tenants'
        ]
    ],
    'broadcasts' => [
        'label' => 'Broadcasts',
        'permissions' => [
            'send_broadcasts' => 'Send Broadcasts',
            'manage_broadcasts' => 'Manage Broadcasts',
            'view_broadcasts' => 'View Broadcasts'
        ]
    ]
];

include 'includes/base.php';
?>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/header.php'; ?>

<style>
/* ============================================================
   ROLE EDIT STYLES
   ============================================================ */

.edit-container {
    background: white;
    border-radius: 14px;
    padding: 32px;
    border: 1px solid #eef3f8;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    max-width: 900px;
    margin: 0 auto;
}

.edit-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid #eef3f8;
}

.edit-header .header-left h1 {
    font-size: 1.5rem;
    font-weight: 600;
    color: #0b1a33;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.edit-header .header-left .subtitle {
    color: #6d83a5;
    font-size: 0.9rem;
    margin-top: 4px;
}

.edit-header .header-actions {
    display: flex;
    gap: 12px;
    align-items: center;
}

.system-badge {
    font-size: 0.6rem;
    background: #ede9fe;
    color: #8b5cf6;
    padding: 2px 12px;
    border-radius: 30px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 500;
    color: #1f3149;
    margin-bottom: 6px;
}

.form-group label .required {
    color: #ef4444;
}

.form-group .form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #dce6f0;
    border-radius: 10px;
    font-size: 0.9rem;
    color: #1f3149;
    background: #f8faff;
    transition: all 0.2s ease;
}

.form-group .form-control:focus {
    outline: none;
    border-color: #4f9cf7;
    box-shadow: 0 0 0 3px rgba(79, 156, 247, 0.1);
    background: white;
}

.form-group .form-control:disabled {
    background: #f0f4fa;
    cursor: not-allowed;
}

.form-group .form-hint {
    font-size: 0.75rem;
    color: #8b9bb5;
    margin-top: 4px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.permissions-section {
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid #eef3f8;
}

.permissions-section .section-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #0b1a33;
    margin-bottom: 4px;
}

.permissions-section .section-subtitle {
    font-size: 0.85rem;
    color: #6d83a5;
    margin-bottom: 16px;
}

.permissions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 12px;
}

.permissions-group {
    background: #f8faff;
    border-radius: 10px;
    padding: 12px 16px;
    border: 1px solid #eef3f8;
}

.permissions-group .group-title {
    font-size: 0.8rem;
    font-weight: 600;
    color: #0b1a33;
    margin-bottom: 8px;
    padding-bottom: 6px;
    border-bottom: 1px solid #eef3f8;
}

.permissions-group .permission-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 0;
    font-size: 0.8rem;
    color: #1f3149;
}

.permissions-group .permission-item input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: #4f9cf7;
    cursor: pointer;
}

.permissions-group .permission-item input[type="checkbox"]:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #eef3f8;
    justify-content: flex-end;
}

.alert {
    padding: 14px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideDown 0.3s ease;
}

.alert i {
    font-size: 1.2rem;
    flex-shrink: 0;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.alert-success i { color: #10b981; }

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.alert-error i { color: #ef4444; }

.alert .alert-close {
    margin-left: auto;
    background: none;
    border: none;
    color: inherit;
    opacity: 0.6;
    cursor: pointer;
    font-size: 1.1rem;
    padding: 4px;
}

.alert .alert-close:hover {
    opacity: 1;
}

.info-box {
    padding: 12px 16px;
    background: #fef3c7;
    border-radius: 10px;
    font-size: 0.85rem;
    color: #92400e;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 12px;
}

.info-box i {
    color: #f59e0b;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 768px) {
    .edit-container {
        padding: 20px;
        margin: 0 10px;
    }
    
    .edit-header {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }
    
    .edit-header .header-actions {
        width: 100%;
    }
    
    .edit-header .header-actions .btn-secondary {
        width: 100%;
        justify-content: center;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .permissions-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn-primary,
    .form-actions .btn-secondary {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .edit-container {
        padding: 16px;
    }
    
    .edit-header .header-left h1 {
        font-size: 1.2rem;
    }
}
</style>

<main class="main-content">
    <div class="edit-container">
        <!-- ============================================================
        HEADER
        ============================================================ -->
        <div class="edit-header">
            <div class="header-left">
                <h1>
                    <i class="fas fa-edit" style="color:#4f9cf7;"></i>
                    Edit Role
                    <?php if ($isSystem): ?>
                    <span class="system-badge">System Role</span>
                    <?php endif; ?>
                </h1>
                <div class="subtitle">
                    <i class="fas fa-shield-alt" style="font-size:0.7rem;"></i>
                    <?php echo htmlspecialchars($role['slug'] ?? ''); ?>
                </div>
            </div>
            <div class="header-actions">
                <a href="roles.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Roles
                </a>
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
        EDIT FORM
        ============================================================ -->
        <form method="POST" id="roleEditForm">
            <input type="hidden" name="action" value="update">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Role Name <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" 
                           placeholder="e.g., Election Manager" 
                           value="<?php echo htmlspecialchars($role['name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Slug <span class="required">*</span></label>
                    <input type="text" name="slug" class="form-control" 
                           placeholder="e.g., election_manager" 
                           value="<?php echo htmlspecialchars($role['slug'] ?? ''); ?>" 
                           <?php echo $isSystem ? 'readonly disabled style="background:#f0f4fa;"' : ''; ?> required>
                    <div class="form-hint">Unique identifier, use lowercase and underscores</div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Level <span class="required">*</span></label>
                    <select name="level" class="form-control" required>
                        <option value="">Select Level</option>
                        <option value="super_admin" <?php echo ($role['level'] ?? '') === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                        <option value="client_admin" <?php echo ($role['level'] ?? '') === 'client_admin' ? 'selected' : ''; ?>>Client Admin</option>
                        <option value="national" <?php echo ($role['level'] ?? '') === 'national' ? 'selected' : ''; ?>>National</option>
                        <option value="state" <?php echo ($role['level'] ?? '') === 'state' ? 'selected' : ''; ?>>State</option>
                        <option value="senatorial" <?php echo ($role['level'] ?? '') === 'senatorial' ? 'selected' : ''; ?>>Senatorial</option>
                        <option value="federal_constituency" <?php echo ($role['level'] ?? '') === 'federal_constituency' ? 'selected' : ''; ?>>Federal Constituency</option>
                        <option value="lga" <?php echo ($role['level'] ?? '') === 'lga' ? 'selected' : ''; ?>>LGA</option>
                        <option value="ward" <?php echo ($role['level'] ?? '') === 'ward' ? 'selected' : ''; ?>>Ward</option>
                        <option value="pu_agent" <?php echo ($role['level'] ?? '') === 'pu_agent' ? 'selected' : ''; ?>>PU Agent</option>
                        <option value="party_agent" <?php echo ($role['level'] ?? '') === 'party_agent' ? 'selected' : ''; ?>>Party Agent</option>
                        <option value="volunteer" <?php echo ($role['level'] ?? '') === 'volunteer' ? 'selected' : ''; ?>>Volunteer</option>
                        <option value="observer" <?php echo ($role['level'] ?? '') === 'observer' ? 'selected' : ''; ?>>Observer</option>
                        <option value="situation_room" <?php echo ($role['level'] ?? '') === 'situation_room' ? 'selected' : ''; ?>>Situation Room</option>
                        <option value="finance_officer" <?php echo ($role['level'] ?? '') === 'finance_officer' ? 'selected' : ''; ?>>Finance Officer</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="is_active" class="form-control" <?php echo $isSystem ? 'disabled' : ''; ?>>
                        <option value="1" <?php echo ($role['is_active'] ?? 0) ? 'selected' : ''; ?>>Active</option>
                        <option value="0" <?php echo !($role['is_active'] ?? 0) ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                    <?php if ($isSystem): ?>
                    <div class="form-hint">System roles cannot be deactivated</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <input type="text" name="description" class="form-control" 
                       placeholder="Brief description of this role..." 
                       value="<?php echo htmlspecialchars($role['description'] ?? ''); ?>">
            </div>
            
            <!-- ============================================================
            PERMISSIONS
            ============================================================ -->
            <div class="permissions-section">
                <div class="section-title">
                    <i class="fas fa-key" style="color:#4f9cf7;"></i> Permissions
                </div>
                <div class="section-subtitle">
                    Select the permissions this role should have.
                    <?php if ($isSystem): ?>
                    System roles have predefined permissions and cannot be modified.
                    <?php endif; ?>
                </div>
                
                <div class="permissions-grid">
                    <?php foreach ($permissions as $key => $group): ?>
                    <div class="permissions-group">
                        <div class="group-title"><?php echo htmlspecialchars($group['label']); ?></div>
                        <?php foreach ($group['permissions'] as $permKey => $permLabel): ?>
                        <div class="permission-item">
                            <input type="checkbox" name="permissions[<?php echo $permKey; ?>]" 
                                   value="1" 
                                   id="perm_<?php echo $permKey; ?>"
                                   <?php echo (isset($rolePermissions[$permKey]) && $rolePermissions[$permKey]) ? 'checked' : ''; ?>
                                   <?php echo $isSystem ? 'disabled' : ''; ?>>
                            <label for="perm_<?php echo $permKey; ?>" style="cursor:pointer;">
                                <?php echo htmlspecialchars($permLabel); ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($isSystem): ?>
                <div class="info-box">
                    <i class="fas fa-info-circle"></i> 
                    System roles have predefined permissions and cannot be modified.
                </div>
                <?php endif; ?>
            </div>
            
            <!-- ============================================================
            FORM ACTIONS
            ============================================================ -->
            <div class="form-actions">
                <a href="roles.php" class="btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" class="btn-primary" <?php echo $isSystem ? 'disabled style="opacity:0.6; cursor:not-allowed;"' : ''; ?>>
                    <i class="fas fa-save"></i> Update Role
                </button>
            </div>
        </form>
    </div>
</main>

<!-- ============================================================
JAVASCRIPT
============================================================ -->
<script>
// ============================================================
// SLUG AUTO-GENERATION
// ============================================================
document.querySelector('input[name="name"]')?.addEventListener('input', function() {
    const slugInput = document.querySelector('input[name="slug"]');
    if (slugInput && !slugInput.readOnly && !slugInput.disabled) {
        const slug = this.value
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');
        slugInput.value = slug;
    }
});

// ============================================================
// CONFIRM NAVIGATION
// ============================================================
let formChanged = false;
document.querySelector('#roleEditForm')?.addEventListener('change', function() {
    formChanged = true;
});

window.addEventListener('beforeunload', function(e) {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
    }
});

// ============================================================
// SELECT ALL PERMISSIONS IN A GROUP
// ============================================================
// Add click handlers for group titles to select all permissions in that group
document.querySelectorAll('.permissions-group .group-title').forEach(title => {
    title.style.cursor = 'pointer';
    title.addEventListener('click', function() {
        const group = this.closest('.permissions-group');
        const checkboxes = group.querySelectorAll('input[type="checkbox"]:not(:disabled)');
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        checkboxes.forEach(cb => cb.checked = !allChecked);
    });
});
</script>

<?php include 'includes/footer.php'; ?>