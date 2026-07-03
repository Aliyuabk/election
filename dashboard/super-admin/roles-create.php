<?php
// ============================================================
// ROLE CREATE - SUPER ADMINISTRATOR
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'super_admin') {
    header('Location: ../client-admin/');
    exit();
}

$db = getDB();

$error = '';
$success = '';
$form_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = [
        'name' => trim($_POST['name'] ?? ''),
        'slug' => trim($_POST['slug'] ?? ''),
        'level' => $_POST['level'] ?? '',
        'description' => trim($_POST['description'] ?? ''),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'permissions' => $_POST['permissions'] ?? [],
    ];
    
    $errors = [];
    
    if (empty($form_data['name'])) {
        $errors[] = 'Role name is required.';
    }
    
    if (empty($form_data['slug'])) {
        $errors[] = 'Role slug is required.';
    }
    
    if (empty($form_data['level'])) {
        $errors[] = 'Role level is required.';
    }
    
    if (empty($errors)) {
        try {
            $permissions_json = json_encode($form_data['permissions']);
            
            $stmt = $db->prepare("
                INSERT INTO roles (
                    name, slug, level, description, is_active,
                    permissions_json, is_system, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, 0, NOW(), NOW()
                )
            ");
            
            $stmt->execute([
                $form_data['name'],
                $form_data['slug'],
                $form_data['level'],
                $form_data['description'],
                $form_data['is_active'],
                $permissions_json
            ]);
            
            $role_id = $db->lastInsertId();
            
            logActivity(
                SessionManager::get('user_id'),
                'role_created',
                "Created role: {$form_data['name']} (ID: $role_id)"
            );
            
            $success = "Role created successfully!";
            $form_data = [];
            
        } catch (Exception $e) {
            $error = 'Error creating role: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    .form-container { background: white; border-radius: var(--radius); border: 1px solid var(--gray-200); padding: 28px 32px; box-shadow: var(--shadow); }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 24px; }
    .form-group { display: flex; flex-direction: column; gap: 4px; }
    .form-group.full-width { grid-column: 1 / -1; }
    .form-group label { font-weight: 600; font-size: 0.82rem; color: var(--gray-700); }
    .form-group label .required { color: var(--danger); margin-left: 2px; }
    .form-group .help-text { font-size: 0.7rem; color: var(--gray-400); margin-top: 2px; }
    .form-group input, .form-group select, .form-group textarea { padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 10px; font-family: 'Inter', sans-serif; font-size: 0.85rem; transition: var(--transition); background: var(--gray-50); color: var(--gray-700); width: 100%; }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #8B5CF6; background: white; box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1); }
    .form-group .checkbox-group { display: flex; align-items: center; gap: 10px; padding-top: 6px; }
    .form-group .checkbox-group input[type="checkbox"] { width: 20px; height: 20px; accent-color: #8B5CF6; cursor: pointer; }
    .form-group .checkbox-group label { font-weight: 400; cursor: pointer; font-size: 0.85rem; }
    .form-section-title { font-weight: 600; font-size: 0.9rem; color: var(--gray-700); grid-column: 1 / -1; padding-top: 8px; border-bottom: 1px solid var(--gray-100); padding-bottom: 8px; margin-bottom: 4px; display: flex; align-items: center; gap: 8px; }
    .form-section-title i { color: #8B5CF6; }
    .form-actions { display: flex; gap: 12px; margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--gray-200); flex-wrap: wrap; }
    .form-actions .btn { padding: 10px 28px; border-radius: 10px; border: none; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: var(--transition); font-family: 'Inter', sans-serif; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
    .form-actions .btn-primary { background: #8B5CF6; color: white; }
    .form-actions .btn-primary:hover { background: #7C3AED; transform: translateY(-1px); box-shadow: 0 4px 16px rgba(139, 92, 246, 0.3); }
    .form-actions .btn-secondary { background: var(--gray-100); color: var(--gray-600); }
    .error-message { background: #FEF2F2; color: #DC2626; padding: 14px 18px; border-radius: 10px; font-size: 0.85rem; margin-bottom: 16px; border: 1px solid #FECACA; display: flex; align-items: flex-start; gap: 12px; }
    .success-message { background: #ECFDF5; color: #065F46; padding: 14px 18px; border-radius: 10px; font-size: 0.85rem; margin-bottom: 16px; border: 1px solid #A7F3D0; display: flex; align-items: flex-start; gap: 12px; }
    .permissions-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px; padding: 8px 0; }
    .permission-item { display: flex; align-items: center; gap: 8px; padding: 6px 10px; background: var(--gray-50); border-radius: 6px; border: 1px solid var(--gray-200); }
    .permission-item input[type="checkbox"] { width: 16px; height: 16px; accent-color: #8B5CF6; cursor: pointer; }
    .permission-item label { font-size: 0.8rem; font-weight: 400; cursor: pointer; }
    @media (max-width: 768px) {
        .form-grid { grid-template-columns: 1fr; gap: 12px; }
        .form-container { padding: 20px; }
        .form-actions { flex-direction: column; }
        .form-actions .btn { justify-content: center; width: 100%; }
        .permissions-grid { grid-template-columns: 1fr 1fr; }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-user-plus" style="color:#8B5CF6;margin-right:8px;"></i> Create Role
                    <small>Create a new role with specific permissions</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="roles.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-message"><i class="fas fa-exclamation-circle"></i><div><?php echo $error; ?></div></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="success-message"><i class="fas fa-check-circle"></i><div><?php echo $success; ?></div></div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-section-title"><i class="fas fa-info-circle"></i> Role Information</div>
                    
                    <div class="form-group">
                        <label>Role Name <span class="required">*</span></label>
                        <input type="text" name="name" placeholder="e.g., Regional Coordinator" value="<?php echo htmlspecialchars($form_data['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Slug <span class="required">*</span></label>
                        <input type="text" name="slug" placeholder="e.g., regional_coordinator" value="<?php echo htmlspecialchars($form_data['slug'] ?? ''); ?>" required>
                        <div class="help-text">Unique identifier (lowercase, underscores instead of spaces)</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Level <span class="required">*</span></label>
                        <select name="level" required>
                            <option value="">Select Level</option>
                            <option value="super_admin" <?php echo ($form_data['level'] ?? '') === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                            <option value="client_admin" <?php echo ($form_data['level'] ?? '') === 'client_admin' ? 'selected' : ''; ?>>Client Admin</option>
                            <option value="national" <?php echo ($form_data['level'] ?? '') === 'national' ? 'selected' : ''; ?>>National</option>
                            <option value="state" <?php echo ($form_data['level'] ?? '') === 'state' ? 'selected' : ''; ?>>State</option>
                            <option value="senatorial" <?php echo ($form_data['level'] ?? '') === 'senatorial' ? 'selected' : ''; ?>>Senatorial</option>
                            <option value="federal_constituency" <?php echo ($form_data['level'] ?? '') === 'federal_constituency' ? 'selected' : ''; ?>>Federal Constituency</option>
                            <option value="lga" <?php echo ($form_data['level'] ?? '') === 'lga' ? 'selected' : ''; ?>>LGA</option>
                            <option value="ward" <?php echo ($form_data['level'] ?? '') === 'ward' ? 'selected' : ''; ?>>Ward</option>
                            <option value="pu_agent" <?php echo ($form_data['level'] ?? '') === 'pu_agent' ? 'selected' : ''; ?>>PU Agent</option>
                            <option value="party_agent" <?php echo ($form_data['level'] ?? '') === 'party_agent' ? 'selected' : ''; ?>>Party Agent</option>
                            <option value="volunteer" <?php echo ($form_data['level'] ?? '') === 'volunteer' ? 'selected' : ''; ?>>Volunteer</option>
                            <option value="observer" <?php echo ($form_data['level'] ?? '') === 'observer' ? 'selected' : ''; ?>>Observer</option>
                        </select>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Description</label>
                        <textarea name="description" rows="3" placeholder="Role description..."><?php echo htmlspecialchars($form_data['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_active" id="isActive" value="1" <?php echo isset($form_data['is_active']) && $form_data['is_active'] ? 'checked' : 'checked'; ?>>
                            <label for="isActive">Role Active</label>
                        </div>
                        <div class="help-text">Inactive roles cannot be assigned to users.</div>
                    </div>

                    <div class="form-section-title"><i class="fas fa-key"></i> Permissions</div>
                    
                    <div class="form-group full-width">
                        <div class="permissions-grid">
                            <?php
                            $permissions_list = [
                                'manage_tenants' => 'Manage Tenants',
                                'manage_users' => 'Manage Users',
                                'manage_elections' => 'Manage Elections',
                                'view_results' => 'View Results',
                                'manage_agents' => 'Manage Agents',
                                'manage_roles' => 'Manage Roles',
                                'view_audit_logs' => 'View Audit Logs',
                                'manage_subscriptions' => 'Manage Subscriptions',
                                'manage_billing' => 'Manage Billing',
                                'manage_inec_data' => 'Manage INEC Data',
                                'view_reports' => 'View Reports',
                                'manage_settings' => 'Manage Settings'
                            ];
                            foreach ($permissions_list as $key => $label):
                            ?>
                                <div class="permission-item">
                                    <input type="checkbox" name="permissions[<?php echo $key; ?>]" id="perm_<?php echo $key; ?>" value="1">
                                    <label for="perm_<?php echo $key; ?>"><?php echo $label; ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="help-text">Select the permissions this role should have.</div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Create Role</button>
                    <a href="roles.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});
</script>
</body>
</html>