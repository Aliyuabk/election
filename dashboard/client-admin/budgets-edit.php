<?php
// ============================================================
// BUDGET EDIT - CLIENT ADMIN (PROFESSIONAL UI)
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

// Check role - only client_admin can access this page
if (SessionManager::get('role_level') !== 'client_admin') {
    header('Location: ../client-admin/');
    exit();
}

// Get database connection
$db = getDB();

// Get user info
$user_id = SessionManager::get('user_id');
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');
$tenant_id = SessionManager::get('tenant_id');

// ============================================================
// GET BUDGET ID
// ============================================================
$budget_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($budget_id <= 0) {
    header('Location: budgets.php');
    exit();
}

// ============================================================
// FETCH BUDGET DETAILS
// ============================================================
$budget = null;
try {
    $stmt = $db->prepare("
        SELECT b.*, 
               e.name as election_name,
               (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE budget_id = b.id AND tenant_id = ? AND status != 'rejected') as spent_amount,
               (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE budget_id = b.id AND tenant_id = ? AND status = 'pending') as pending_amount
        FROM budgets b
        LEFT JOIN elections e ON b.election_id = e.id
        WHERE b.id = ? AND b.tenant_id = ?
    ");
    $stmt->execute([$tenant_id, $tenant_id, $budget_id, $tenant_id]);
    $budget = $stmt->fetch();
    
    if (!$budget) {
        header('Location: budgets.php');
        exit();
    }
} catch (Exception $e) {
    header('Location: budgets.php');
    exit();
}

// ============================================================
// FETCH ELECTIONS FOR DROPDOWN
// ============================================================
$elections = [];
try {
    $stmt = $db->prepare("SELECT id, name, type, status, election_date FROM elections WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY election_date DESC");
    $stmt->execute([$tenant_id]);
    $elections = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
$action_result = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_budget':
                $name = trim($_POST['name'] ?? '');
                $total_amount = (float)($_POST['total_amount'] ?? 0);
                $start_date = trim($_POST['start_date'] ?? '');
                $end_date = trim($_POST['end_date'] ?? '');
                $election_id = (int)($_POST['election_id'] ?? 0);
                $status = trim($_POST['status'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                if (empty($name) || $total_amount <= 0 || empty($start_date)) {
                    throw new Exception('Name, amount, and start date are required.');
                }
                
                // Check if budget can be updated
                if ($budget['status'] == 'closed' && $status != 'closed') {
                    throw new Exception('Cannot modify a closed budget.');
                }
                
                $stmt = $db->prepare("
                    UPDATE budgets SET 
                        name = ?, total_amount = ?, start_date = ?, 
                        end_date = ?, election_id = ?, status = ?
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmt->execute([
                    $name, $total_amount, $start_date, 
                    $end_date, $election_id, $status,
                    $budget_id, $tenant_id
                ]);
                
                logActivity($user_id, 'budget_updated', "Updated budget ID: $budget_id");
                $action_result = ['success' => true, 'message' => 'Budget updated successfully.'];
                break;
        }
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       BUDGET EDIT - PROFESSIONAL UI STYLES
       ============================================================ */
    
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
    
    .btn-primary {
        padding: 10px 20px;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.3);
    }
    .btn-outline {
        padding: 10px 18px;
        background: transparent;
        color: var(--gray-600);
        border: 1.5px solid var(--gray-200);
        border-radius: 10px;
        font-weight: 500;
        font-size: 0.85rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-outline:hover {
        background: var(--gray-50);
        border-color: var(--primary);
        color: var(--primary);
    }
    .btn-secondary {
        padding: 10px 20px;
        background: var(--gray-100);
        color: var(--gray-600);
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-secondary:hover {
        background: var(--gray-200);
    }
    
    .form-container {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 28px 32px;
        box-shadow: var(--shadow);
        max-width: 800px;
        margin: 0 auto;
    }
    .form-container:hover {
        box-shadow: var(--shadow-hover);
    }
    .form-container .form-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 2px solid var(--gray-100);
    }
    .form-container .form-header .icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: #EFF6FF;
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
    }
    .form-container .form-header h3 {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--gray-800);
    }
    .form-container .form-header p {
        font-size: 0.85rem;
        color: var(--gray-500);
        margin-top: 2px;
    }
    
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
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
    .form-group select,
    .form-group textarea {
        padding: 8px 12px;
        border: 1.5px solid var(--gray-200);
        border-radius: 8px;
        font-family: 'Inter', sans-serif;
        font-size: 0.82rem;
        transition: var(--transition);
        background: var(--gray-50);
        color: var(--gray-700);
        width: 100%;
    }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 4px rgba(var(--primary-rgb), 0.08);
    }
    .form-group textarea {
        resize: vertical;
        min-height: 60px;
    }
    
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
    }
    .badge-status .dot {
        width: 5px;
        height: 5px;
        border-radius: 50%;
        display: inline-block;
    }
    .badge-status.active { background: #ECFDF5; color: #065F46; }
    .badge-status.active .dot { background: #10B981; }
    .badge-status.closed { background: var(--gray-100); color: var(--gray-500); }
    .badge-status.closed .dot { background: var(--gray-400); }
    .badge-status.draft { background: #FFFBEB; color: #92400E; }
    .badge-status.draft .dot { background: #F59E0B; }
    .badge-status.cancelled { background: #FEF2F2; color: #991B1B; }
    .badge-status.cancelled .dot { background: #EF4444; }
    
    .form-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 20px;
        padding-top: 16px;
        border-top: 2px solid var(--gray-100);
    }
    .form-actions .btn {
        padding: 10px 24px;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .form-actions .btn-primary {
        background: var(--primary);
        color: white;
    }
    .form-actions .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.25);
    }
    .form-actions .btn-secondary {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .form-actions .btn-secondary:hover {
        background: var(--gray-200);
    }
    
    .toast {
        padding: 12px 18px;
        border-radius: 8px;
        color: white;
        font-size: 0.82rem;
        font-weight: 500;
        box-shadow: var(--shadow-hover);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        max-width: 100%;
    }
    .toast.success { background: var(--secondary); }
    .toast.error { background: var(--danger); }
    
    .budget-summary {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-bottom: 20px;
        padding: 16px 20px;
        background: var(--gray-50);
        border-radius: 8px;
        border: 1px solid var(--gray-200);
    }
    .budget-summary .item {
        text-align: center;
    }
    .budget-summary .item .label {
        font-size: 0.65rem;
        color: var(--gray-500);
    }
    .budget-summary .item .value {
        font-weight: 700;
        font-size: 1.1rem;
        color: var(--primary);
    }
    .budget-summary .item .value.green { color: var(--secondary); }
    .budget-summary .item .value.yellow { color: var(--warning); }
    .budget-summary .item .value.red { color: var(--danger); }
    
    @media (max-width: 768px) {
        .form-container {
            padding: 16px;
        }
        .form-grid {
            grid-template-columns: 1fr;
        }
        .form-actions {
            flex-direction: column;
        }
        .form-actions .btn {
            width: 100%;
            justify-content: center;
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .budget-summary {
            grid-template-columns: 1fr;
            gap: 8px;
        }
    }
    @media (max-width: 480px) {
        .form-container {
            padding: 12px;
        }
        .form-container .form-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Toast Messages -->
        <?php if (!empty($action_result['message'])): ?>
        <div class="toast <?php echo $action_result['success'] ? 'success' : 'error'; ?>" style="position:static;animation:none;">
            <i class="fas <?php echo $action_result['success'] ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($action_result['message']); ?>
        </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-edit" style="color:var(--primary);margin-right:8px;"></i> Edit Budget
                    <small>Update budget details and financial information</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="budgets-details.php?id=<?php echo $budget_id; ?>" class="btn-outline">
                    <i class="fas fa-eye"></i> View Details
                </a>
                <a href="budgets.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Budget Summary -->
        <div class="budget-summary">
            <div class="item">
                <div class="label">Total Budget</div>
                <div class="value">₦<?php echo number_format($budget['total_amount']); ?></div>
            </div>
            <div class="item">
                <div class="label">Spent</div>
                <div class="value green">₦<?php echo number_format($budget['spent_amount']); ?></div>
            </div>
            <div class="item">
                <div class="label">Remaining</div>
                <div class="value <?php echo ($budget['total_amount'] - $budget['spent_amount']) > 0 ? 'green' : 'red'; ?>">
                    ₦<?php echo number_format($budget['total_amount'] - $budget['spent_amount']); ?>
                </div>
            </div>
        </div>

        <!-- Form Container -->
        <div class="form-container">
            <div class="form-header">
                <div class="icon">
                    <i class="fas fa-wallet"></i>
                </div>
                <div>
                    <h3>Edit Budget: <?php echo htmlspecialchars($budget['name']); ?></h3>
                    <p>Update the budget details. Fields marked with <span style="color:var(--danger);">*</span> are required.</p>
                </div>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="action" value="update_budget">
                
                <div class="form-grid">
                    <!-- Budget Name -->
                    <div class="form-group full-width">
                        <label>Budget Name <span class="required">*</span></label>
                        <input type="text" name="name" placeholder="e.g., Election Campaign Budget" required
                               value="<?php echo htmlspecialchars($budget['name']); ?>">
                    </div>

                    <!-- Total Amount -->
                    <div class="form-group">
                        <label>Total Amount <span class="required">*</span></label>
                        <input type="number" name="total_amount" placeholder="0.00" step="0.01" min="0" required
                               value="<?php echo $budget['total_amount']; ?>">
                    </div>

                    <!-- Status -->
                    <div class="form-group">
                        <label>Status <span class="required">*</span></label>
                        <select name="status" required>
                            <option value="draft" <?php echo $budget['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="active" <?php echo $budget['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="closed" <?php echo $budget['status'] == 'closed' ? 'selected' : ''; ?>>Closed</option>
                            <option value="cancelled" <?php echo $budget['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>

                    <!-- Election -->
                    <div class="form-group">
                        <label>Election</label>
                        <select name="election_id">
                            <option value="0">General (No Election)</option>
                            <?php foreach ($elections as $election): ?>
                                <option value="<?php echo $election['id']; ?>" <?php echo $budget['election_id'] == $election['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($election['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Start Date -->
                    <div class="form-group">
                        <label>Start Date <span class="required">*</span></label>
                        <input type="date" name="start_date" required
                               value="<?php echo $budget['start_date']; ?>">
                    </div>

                    <!-- End Date -->
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date"
                               value="<?php echo $budget['end_date']; ?>">
                        <div class="help-text">Optional: Set an end date for the budget period</div>
                    </div>

                    <!-- Description -->
                    <div class="form-group full-width">
                        <label>Description</label>
                        <textarea name="description" placeholder="Budget description and notes..." rows="3"><?php echo htmlspecialchars($budget['description'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="budgets-details.php?id=<?php echo $budget_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Budget
                    </button>
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
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});

// ============================================================
// SIDEBAR TOGGLE
// ============================================================
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

// ============================================================
// SIDEBAR DROPDOWNS
// ============================================================
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

// ============================================================
// PROFILE DROPDOWN
// ============================================================
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

// ============================================================
// SEARCH FUNCTIONALITY
// ============================================================
var searchInput = document.querySelector('.search-wrap input');
if (searchInput) {
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            this.closest('form').submit();
        }
    });
}
</script>
</body>
</html>