<?php
// ============================================================
// ROLE PERMISSIONS - SUPER ADMINISTRATOR
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

$role_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($role_id <= 0) {
    header('Location: roles.php');
    exit();
}

$role = null;
try {
    $stmt = $db->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([$role_id]);
    $role = $stmt->fetch();
} catch (Exception $e) {
    // Continue
}

if (!$role) {
    header('Location: roles.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $permissions = $_POST['permissions'] ?? [];
    
    try {
        $permissions_json = json_encode($permissions);
        
        $stmt = $db->prepare("UPDATE roles SET permissions_json = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$permissions_json, $role_id]);
        
        logActivity(
            SessionManager::get('user_id'),
            'role_permissions_updated',
            "Updated permissions for role: {$role['name']} (ID: $role_id)"
        );
        
        $success = "Permissions updated successfully!";
        
        // Refresh role data
        $stmt = $db->prepare("SELECT * FROM roles WHERE id = ?");
        $stmt->execute([$role_id]);
        $role = $stmt->fetch();
        
    } catch (Exception $e) {
        $error = 'Error updating permissions: ' . $e->getMessage();
    }
}

$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    .permissions-container { background: white; border-radius: var(--radius); border: 1px solid var(--gray-200); padding: 28px 32px; box-shadow: var(--shadow); }
    .permissions-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; padding: 16px 0; }
    .permission-card { display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: var(--gray-50); border-radius: 8px; border: 1px solid var(--gray-200); transition: var(--transition); }
    .permission-card:hover { background: #F5F3FF; border-color: #8B5CF6; }
    .permission-card input[type="checkbox"] { width: 18px; height: 18px; accent-color: #8B5CF6; cursor: pointer; flex-shrink: 0; }
    .permission-card label { font-size: 0.85rem; font-weight: 500; cursor: pointer; flex: 1; }
    .permission-card .perm-desc { font-size: 0.7rem; color: var(--gray-400); display: block; font-weight: 400; }
    .select-all { display: flex; align-items: center; gap: 10px; padding: 12px 16px; background: #F5F3FF; border-radius: 8px; border: 1px solid #EDE9FE; margin-bottom: 16px; }
    .select-all input[type="checkbox"] { width: 18px; height: 18px; accent-color: #8B5CF6; cursor: pointer; }
    .select-all label { font-weight: 600; font-size: 0.9rem; cursor: pointer; }
    .form-actions { display: flex; gap: 12px; margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--gray-200); flex-wrap: wrap; }
    .form-actions .btn { padding: 10px 28px; border-radius: 10px; border: none; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: var(--transition); font-family: 'Inter', sans-serif; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
    .form-actions .btn-primary { background: #8B5CF6; color: white; }
    .form-actions .btn-primary:hover { background: #7C3AED; transform: translateY(-1px); box-shadow: 0 4px 16px rgba(139, 92, 246, 0.3); }
    .form-actions .btn-secondary { background: var(--gray-100); color: var(--gray-600); }
    .error-message { background: #FEF2F2; color: #DC2626; padding: 14px 18px; border-radius: 10px; font-size: 0.85rem; margin-bottom: 16px; border: 1px solid #FECACA; display: flex; align-items: flex-start; gap: 12px; }
    .success-message { background: #ECFDF5; color: #065F46; padding: 14px 18px; border-radius: 10px; font-size: 0.85rem; margin-bottom: 16px; border: 1px solid #A7F3D0; display: flex; align-items: flex-start; gap: 12px; }
    @media (max-width: 768px) {
        .permissions-grid { grid-template-columns: 1fr 1fr; }
        .permissions-container { padding: 16px; }
        .form-actions { flex-direction: column; }
        .form-actions .btn { justify-content: center; width: 100%; }
    }
    @media (max-width: 480px) {
        .permissions-grid { grid-template-columns: 1fr; }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-lock" style="color:#8B5CF6;margin-right:8px;"></i> Role Permissions
                    <small>Manage permissions for <?php echo htmlspecialchars($role['name']); ?></small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="roles-view.php?id=<?php echo $role['id']; ?>" class="btn-outline">
                    <i class="fas fa-eye"></i> View Role
                </a>
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

        <div class="permissions-container">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
                <div>
                    <h3 style="font-size:1rem;font-weight:700;"><?php echo htmlspecialchars($role['name']); ?></h3>
                    <p style="color:var(--gray-500);font-size:0.85rem;">Select the permissions this role should have.</p>
                </div>
                <span class="badge-status <?php echo $role['is_active'] ? 'active' : 'inactive'; ?>">
                    <span class="dot"></span> <?php echo $role['is_active'] ? 'Active' : 'Inactive'; ?>
                </span>
            </div>
            
            <form method="POST" action="">
                <div class="select-all">
                    <input type="checkbox" id="selectAll" onchange="toggleAllPermissions(this.checked)">
                    <label for="selectAll">Select All Permissions</label>
                </div>

                <div class="permissions-grid">
                    <?php
                    $current_perms = json_decode($role['permissions_json'] ?? '{}', true);
                    $permissions_list = [
                        'manage_tenants' => ['label' => 'Manage Tenants', 'desc' => 'Create, edit, delete tenants'],
                        'manage_users' => ['label' => 'Manage Users', 'desc' => 'Create, edit, delete users'],
                        'manage_elections' => ['label' => 'Manage Elections', 'desc' => 'Create, edit, delete elections'],
                        'view_results' => ['label' => 'View Results', 'desc' => 'View election results'],
                        'manage_agents' => ['label' => 'Manage Agents', 'desc' => 'Assign and manage agents'],
                        'manage_roles' => ['label' => 'Manage Roles', 'desc' => 'Create, edit, delete roles'],
                        'view_audit_logs' => ['label' => 'View Audit Logs', 'desc' => 'View system audit logs'],
                        'manage_subscriptions' => ['label' => 'Manage Subscriptions', 'desc' => 'Manage tenant subscriptions'],
                        'manage_billing' => ['label' => 'Manage Billing', 'desc' => 'Manage invoices and payments'],
                        'manage_inec_data' => ['label' => 'Manage INEC Data', 'desc' => 'Upload and manage INEC data'],
                        'view_reports' => ['label' => 'View Reports', 'desc' => 'View and generate reports'],
                        'manage_settings' => ['label' => 'Manage Settings', 'desc' => 'Manage system settings'],
                        'manage_backups' => ['label' => 'Manage Backups', 'desc' => 'Create and restore backups'],
                        'view_security' => ['label' => 'View Security', 'desc' => 'View security events'],
                        'manage_api' => ['label' => 'Manage API', 'desc' => 'Manage API keys and access'],
                        'manage_support' => ['label' => 'Manage Support', 'desc' => 'Manage support tickets']
                    ];
                    foreach ($permissions_list as $key => $perm):
                    ?>
                        <div class="permission-card">
                            <input type="checkbox" name="permissions[<?php echo $key; ?>]" id="perm_<?php echo $key; ?>" value="1" <?php echo isset($current_perms[$key]) && $current_perms[$key] ? 'checked' : ''; ?>>
                            <label for="perm_<?php echo $key; ?>">
                                <?php echo $perm['label']; ?>
                                <span class="perm-desc"><?php echo $perm['desc']; ?></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Permissions</button>
                    <a href="roles-view.php?id=<?php echo $role['id']; ?>" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
function toggleAllPermissions(checked) {
    document.querySelectorAll('.permission-card input[type="checkbox"]').forEach(function(cb) {
        cb.checked = checked;
    });
}

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