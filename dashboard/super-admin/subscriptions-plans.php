<?php
// ============================================================
// SUBSCRIPTION PLANS - SUPER ADMINISTRATOR
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

// ============================================================
// HANDLE ACTIONS
// ============================================================
$action_result = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $plan_id = isset($_POST['plan_id']) ? (int)$_POST['plan_id'] : 0;
    
    try {
        switch ($action) {
            case 'delete':
                $stmt = $db->prepare("DELETE FROM subscription_plans WHERE id = ?");
                $stmt->execute([$plan_id]);
                if ($stmt->rowCount() > 0) {
                    $action_result = ['success' => true, 'message' => 'Plan deleted successfully.'];
                    logActivity(SessionManager::get('user_id'), 'plan_deleted', "Deleted plan ID: $plan_id");
                }
                break;
        }
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// ============================================================
// FETCH PLANS
// ============================================================
$plans = [];
try {
    $stmt = $db->query("SELECT * FROM subscription_plans ORDER BY price ASC");
    $plans = $stmt->fetchAll();
} catch (Exception $e) {
    // If table doesn't exist, define default plans
    $default_plans = [
        ['name' => 'Free', 'slug' => 'free', 'price' => 0, 'duration' => 'lifetime', 'user_limit' => 5, 'storage_limit' => 100, 'features' => 'Basic features'],
        ['name' => 'Basic', 'slug' => 'basic', 'price' => 29.99, 'duration' => 'monthly', 'user_limit' => 20, 'storage_limit' => 500, 'features' => 'All basic features'],
        ['name' => 'Standard', 'slug' => 'standard', 'price' => 49.99, 'duration' => 'monthly', 'user_limit' => 50, 'storage_limit' => 2000, 'features' => 'Advanced features'],
        ['name' => 'Premium', 'slug' => 'premium', 'price' => 99.99, 'duration' => 'monthly', 'user_limit' => 100, 'storage_limit' => 5000, 'features' => 'All premium features'],
        ['name' => 'Enterprise', 'slug' => 'enterprise', 'price' => 299.99, 'duration' => 'monthly', 'user_limit' => 500, 'storage_limit' => 20000, 'features' => 'Custom enterprise features']
    ];
    $plans = $default_plans;
}

// Get user info
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    .page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
    .page-header h2 { font-size: 1.3rem; font-weight: 700; }
    .page-header h2 small { font-size: 0.8rem; font-weight: 400; color: var(--gray-500); display: block; }
    .btn-primary { padding: 8px 18px; background: var(--primary); color: white; border: none; border-radius: 10px; font-weight: 600; font-size: 0.85rem; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: var(--transition); font-family: 'Inter', sans-serif; }
    .btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.25); }
    .btn-outline { padding: 8px 16px; background: transparent; color: var(--gray-600); border: 1px solid var(--gray-200); border-radius: 10px; font-weight: 500; font-size: 0.82rem; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: var(--transition); font-family: 'Inter', sans-serif; }
    .btn-outline:hover { background: var(--gray-50); border-color: var(--gray-300); }
    
    .plans-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-top: 20px; }
    .plan-card { background: white; border-radius: var(--radius); border: 1px solid var(--gray-200); padding: 24px; box-shadow: var(--shadow); transition: var(--transition); position: relative; overflow: hidden; }
    .plan-card:hover { box-shadow: var(--shadow-hover); transform: translateY(-4px); }
    .plan-card.popular { border-color: var(--primary); }
    .plan-card.popular::before { content: 'Popular'; position: absolute; top: 12px; right: 12px; background: var(--primary); color: white; padding: 2px 12px; border-radius: 20px; font-size: 0.65rem; font-weight: 600; }
    .plan-card .plan-name { font-size: 1.2rem; font-weight: 700; }
    .plan-card .plan-price { font-size: 2rem; font-weight: 700; color: var(--primary); margin: 8px 0; }
    .plan-card .plan-price small { font-size: 0.8rem; font-weight: 400; color: var(--gray-500); }
    .plan-card .plan-duration { font-size: 0.8rem; color: var(--gray-500); }
    .plan-card .plan-features { list-style: none; margin: 16px 0; padding: 0; }
    .plan-card .plan-features li { padding: 6px 0; border-bottom: 1px solid var(--gray-100); font-size: 0.85rem; display: flex; align-items: center; gap: 8px; }
    .plan-card .plan-features li i { color: var(--secondary); font-size: 0.8rem; }
    .plan-card .plan-features li:last-child { border-bottom: none; }
    .plan-card .card-actions { display: flex; gap: 8px; margin-top: 16px; flex-wrap: wrap; }
    .plan-card .card-actions .btn-sm { padding: 6px 14px; border-radius: 8px; border: none; font-weight: 500; font-size: 0.78rem; cursor: pointer; transition: var(--transition); font-family: 'Inter', sans-serif; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
    .plan-card .card-actions .btn-sm.primary { background: #EFF6FF; color: var(--primary); }
    .plan-card .card-actions .btn-sm.primary:hover { background: #DBEAFE; }
    .plan-card .card-actions .btn-sm.danger { background: #FEF2F2; color: var(--danger); }
    .plan-card .card-actions .btn-sm.danger:hover { background: #FEE2E2; }
    .plan-card .card-actions .btn-sm.success { background: #ECFDF5; color: var(--secondary); }
    .plan-card .card-actions .btn-sm.success:hover { background: #D1FAE5; }
    
    .empty-state-pro { text-align: center; padding: 48px 20px; color: var(--gray-500); }
    .empty-state-pro i { font-size: 3rem; color: var(--gray-300); display: block; margin-bottom: 12px; }
    
    @media (max-width: 768px) {
        .plans-grid { grid-template-columns: 1fr; }
        .page-header { flex-direction: column; align-items: flex-start; }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-layer-group" style="color:var(--primary);margin-right:8px;"></i> Subscription Plans
                    <small>Manage available subscription plans and pricing</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="subscriptions.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <a href="plans-create.php" class="btn-primary">
                    <i class="fas fa-plus-circle"></i> Create Plan
                </a>
            </div>
        </div>

        <div class="plans-grid">
            <?php foreach ($plans as $plan): ?>
                <div class="plan-card <?php echo ($plan['slug'] ?? '') === 'premium' ? 'popular' : ''; ?>">
                    <div class="plan-name"><?php echo htmlspecialchars($plan['name'] ?? ''); ?></div>
                    <div class="plan-price">
                        <?php echo isset($plan['price']) && $plan['price'] > 0 ? '₦' . number_format($plan['price'], 2) : 'Free'; ?>
                        <?php if (isset($plan['duration']) && $plan['duration'] !== 'lifetime'): ?>
                            <small>/<?php echo $plan['duration']; ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="plan-duration">
                        <i class="fas fa-clock"></i> 
                        <?php echo isset($plan['duration']) && $plan['duration'] !== 'lifetime' ? ucfirst($plan['duration']) : 'Lifetime'; ?>
                    </div>
                    <ul class="plan-features">
                        <li><i class="fas fa-check-circle"></i> <?php echo isset($plan['user_limit']) ? $plan['user_limit'] : 'Unlimited'; ?> Users</li>
                        <li><i class="fas fa-check-circle"></i> <?php echo isset($plan['storage_limit']) ? $plan['storage_limit'] . ' MB' : 'Unlimited'; ?> Storage</li>
                        <li><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($plan['features'] ?? 'Standard features'); ?></li>
                    </ul>
                    <div class="card-actions">
                        <a href="plans-edit.php?id=<?php echo $plan['id'] ?? 0; ?>" class="btn-sm primary">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <button onclick="if(confirm('Delete this plan?')){document.getElementById('deleteForm_<?php echo $plan['id'] ?? 0; ?>').submit();}" class="btn-sm danger">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                        <form id="deleteForm_<?php echo $plan['id'] ?? 0; ?>" method="POST" style="display:none;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="plan_id" value="<?php echo $plan['id'] ?? 0; ?>">
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
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
// Sidebar toggle, dropdowns, profile functions (same as other pages)
</script>
</body>
</html>