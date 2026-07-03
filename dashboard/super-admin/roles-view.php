<?php
// ============================================================
// ROLE VIEW - SUPER ADMINISTRATOR
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

// Fetch role details
$role = null;
try {
    $stmt = $db->prepare("
        SELECT r.*,
            (SELECT COUNT(*) FROM users WHERE role_id = r.id AND deleted_at IS NULL) as user_count
        FROM roles r
        WHERE r.id = ?
    ");
    $stmt->execute([$role_id]);
    $role = $stmt->fetch();
} catch (Exception $e) {
    // Continue
}

if (!$role) {
    header('Location: roles.php');
    exit();
}

$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    .profile-header { background: white; border-radius: var(--radius); border: 1px solid var(--gray-200); padding: 24px 28px; display: flex; align-items: center; gap: 24px; flex-wrap: wrap; box-shadow: var(--shadow); margin-bottom: 24px; position: relative; overflow: hidden; }
    .profile-header::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #8B5CF6, #3B82F6); }
    .profile-header .role-icon { width: 72px; height: 72px; border-radius: 50%; background: #F5F3FF; display: flex; align-items: center; justify-content: center; font-size: 2rem; color: #8B5CF6; flex-shrink: 0; border: 3px solid var(--gray-200); }
    .profile-header .role-info h2 { font-size: 1.3rem; font-weight: 700; margin-bottom: 2px; }
    .profile-header .role-info .role-meta { display: flex; flex-wrap: wrap; gap: 16px; font-size: 0.82rem; color: var(--gray-500); }
    .profile-header .role-actions { margin-left: auto; display: flex; gap: 8px; flex-wrap: wrap; }
    .btn-primary { padding: 8px 18px; background: #8B5CF6; color: white; border: none; border-radius: 10px; font-weight: 600; font-size: 0.85rem; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: var(--transition); font-family: 'Inter', sans-serif; }
    .btn-primary:hover { background: #7C3AED; transform: translateY(-1px); box-shadow: 0 4px 16px rgba(139, 92, 246, 0.3); }
    .btn-outline { padding: 8px 16px; background: transparent; color: var(--gray-600); border: 1px solid var(--gray-200); border-radius: 10px; font-weight: 500; font-size: 0.82rem; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: var(--transition); font-family: 'Inter', sans-serif; }
    .btn-outline:hover { background: var(--gray-50); border-color: var(--gray-300); }
    
    .detail-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
    .detail-card { background: white; border-radius: var(--radius); border: 1px solid var(--gray-200); padding: 20px 24px; box-shadow: var(--shadow); }
    .detail-card .card-title { font-weight: 600; font-size: 0.95rem; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 1px solid var(--gray-100); display: flex; align-items: center; gap: 8px; }
    .detail-row { display: flex; padding: 8px 0; border-bottom: 1px solid var(--gray-50); font-size: 0.85rem; }
    .detail-row:last-child { border-bottom: none; }
    .detail-row .label { font-weight: 500; color: var(--gray-500); min-width: 140px; flex-shrink: 0; }
    .detail-row .value { color: var(--gray-700); }
    .badge-level { display: inline-block; padding: 2px 12px; border-radius: 12px; font-size: 0.7rem; font-weight: 500; background: #F5F3FF; color: #5B21B6; }
    .badge-status { display: inline-flex; align-items: center; gap: 5px; padding: 3px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
    .badge-status.active { background: #ECFDF5; color: #065F46; }
    .badge-status.inactive { background: #FEF2F2; color: #991B1B; }
    .badge-status .dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
    .badge-status.active .dot { background: #10B981; }
    .badge-status.inactive .dot { background: #EF4444; }
    .system-badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 0.65rem; font-weight: 500; background: #F5F3FF; color: #5B21B6; }
    .permission-item { display: inline-block; padding: 4px 12px; background: var(--gray-50); border-radius: 6px; font-size: 0.78rem; margin: 4px 4px 4px 0; border: 1px solid var(--gray-200); }
    @media (max-width: 768px) {
        .profile-header { flex-direction: column; align-items: center; text-align: center; }
        .profile-header .role-actions { margin-left: 0; width: 100%; justify-content: center; }
        .detail-grid { grid-template-columns: 1fr; }
        .detail-row { flex-direction: column; }
        .detail-row .label { min-width: auto; }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="profile-header">
            <div class="role-icon"><i class="fas fa-user-shield"></i></div>
            <div class="role-info">
                <h2><?php echo htmlspecialchars($role['name']); ?></h2>
                <div class="role-meta">
                    <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($role['slug']); ?></span>
                    <span><i class="fas fa-level-up-alt"></i> <?php echo ucfirst(str_replace('_', ' ', $role['level'])); ?></span>
                    <span><i class="fas fa-users"></i> <?php echo $role['user_count'] ?? 0; ?> users</span>
                    <span><span class="badge-status <?php echo $role['is_active'] ? 'active' : 'inactive'; ?>"><span class="dot"></span><?php echo $role['is_active'] ? 'Active' : 'Inactive'; ?></span></span>
                </div>
            </div>
            <div class="role-actions">
                <a href="roles-edit.php?id=<?php echo $role['id']; ?>" class="btn-primary"><i class="fas fa-edit"></i> Edit</a>
                <a href="roles-permissions.php?id=<?php echo $role['id']; ?>" class="btn-primary" style="background:#3B82F6;"><i class="fas fa-lock"></i> Permissions</a>
                <a href="roles.php" class="btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
            </div>
        </div>

        <div class="detail-grid">
            <div>
                <div class="detail-card">
                    <div class="card-title"><i class="fas fa-info-circle" style="color:#8B5CF6;"></i> Role Details</div>
                    <div class="detail-row"><span class="label">Role Name</span><span class="value"><?php echo htmlspecialchars($role['name']); ?></span></div>
                    <div class="detail-row"><span class="label">Slug</span><span class="value"><?php echo htmlspecialchars($role['slug']); ?></span></div>
                    <div class="detail-row"><span class="label">Level</span><span class="value"><span class="badge-level"><?php echo ucfirst(str_replace('_', ' ', $role['level'])); ?></span></span></div>
                    <div class="detail-row"><span class="label">Status</span><span class="value"><span class="badge-status <?php echo $role['is_active'] ? 'active' : 'inactive'; ?>"><span class="dot"></span><?php echo $role['is_active'] ? 'Active' : 'Inactive'; ?></span></span></div>
                    <div class="detail-row"><span class="label">Type</span><span class="value"><?php echo $role['is_system'] ? '<span class="system-badge"><i class="fas fa-star"></i> System Role</span>' : '<span class="system-badge" style="background:var(--gray-100);color:var(--gray-500);"><i class="fas fa-user-edit"></i> Custom Role</span>'; ?></span></div>
                    <div class="detail-row"><span class="label">Description</span><span class="value"><?php echo htmlspecialchars($role['description'] ?? 'No description'); ?></span></div>
                    <div class="detail-row"><span class="label">Users Assigned</span><span class="value"><strong><?php echo $role['user_count'] ?? 0; ?></strong> users</span></div>
                    <div class="detail-row"><span class="label">Created</span><span class="value"><?php echo date('M j, Y g:i A', strtotime($role['created_at'])); ?></span></div>
                </div>
            </div>
            <div>
                <div class="detail-card">
                    <div class="card-title"><i class="fas fa-key" style="color:#8B5CF6;"></i> Permissions</div>
                    <?php
                    $permissions = json_decode($role['permissions_json'] ?? '{}', true);
                    if (!empty($permissions)):
                    ?>
                        <div style="display:flex;flex-wrap:wrap;gap:4px;">
                            <?php foreach ($permissions as $key => $value): ?>
                                <span class="permission-item"><i class="fas fa-check-circle" style="color:var(--secondary);font-size:0.65rem;"></i> <?php echo ucfirst(str_replace('_', ' ', $key)); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color:var(--gray-500);font-size:0.85rem;">No specific permissions assigned.</p>
                    <?php endif; ?>
                </div>
                
                <div class="detail-card" style="margin-top:16px;">
                    <div class="card-title"><i class="fas fa-bolt" style="color:#8B5CF6;"></i> Quick Actions</div>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;">
                        <a href="roles-edit.php?id=<?php echo $role['id']; ?>" class="btn-primary" style="padding:6px 14px;font-size:0.8rem;"><i class="fas fa-edit"></i> Edit Role</a>
                        <a href="roles-permissions.php?id=<?php echo $role['id']; ?>" class="btn-primary" style="padding:6px 14px;font-size:0.8rem;background:#3B82F6;"><i class="fas fa-lock"></i> Permissions</a>
                        <?php if (!$role['is_system']): ?>
                            <button class="btn-primary" style="padding:6px 14px;font-size:0.8rem;background:var(--danger);" onclick="if(confirm('Delete this role?')){alert('Deleting...');}"><i class="fas fa-trash"></i> Delete</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
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