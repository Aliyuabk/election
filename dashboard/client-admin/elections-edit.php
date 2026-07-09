<?php
// ============================================================
// ELECTION EDIT - CLIENT ADMIN (PROFESSIONAL UI)
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
// GET ELECTION ID
// ============================================================
$election_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($election_id <= 0) {
    header('Location: elections.php');
    exit();
}

// ============================================================
// FETCH ELECTION DETAILS
// ============================================================
$election = null;
try {
    $stmt = $db->prepare("
        SELECT e.*, u.full_name as created_by_name
        FROM elections e
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.id = ? AND e.tenant_id = ? AND e.deleted_at IS NULL
    ");
    $stmt->execute([$election_id, $tenant_id]);
    $election = $stmt->fetch();
} catch (Exception $e) {
    // Continue
}

if (!$election) {
    header('Location: elections.php');
    exit();
}

// ============================================================
// FETCH STATES FOR DROPDOWN
// ============================================================
$states = [];
try {
    $stmt = $db->query("SELECT id, name, code FROM states WHERE is_active = 1 ORDER BY name");
    $states = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
$action_result = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_election':
                $name = trim($_POST['name'] ?? '');
                $type = trim($_POST['type'] ?? '');
                $cycle = trim($_POST['cycle'] ?? '');
                $election_date = trim($_POST['election_date'] ?? '');
                $start_time = trim($_POST['start_time'] ?? '');
                $end_time = trim($_POST['end_time'] ?? '');
                $status = trim($_POST['status'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $states_json = isset($_POST['states']) ? json_encode($_POST['states']) : '[]';
                
                if (empty($name) || empty($type) || empty($election_date) || empty($status)) {
                    throw new Exception('Name, type, date, and status are required.');
                }
                
                // Convert empty time values to NULL
                $start_time = !empty($start_time) ? $start_time : null;
                $end_time = !empty($end_time) ? $end_time : null;
                
                $stmt = $db->prepare("
                    UPDATE elections SET 
                        name = ?, 
                        type = ?, 
                        cycle = ?,
                        election_date = ?, 
                        start_time = ?, 
                        end_time = ?,
                        status = ?, 
                        description = ?, 
                        states_json = ?,
                        updated_at = NOW()
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmt->execute([
                    $name, $type, $cycle,
                    $election_date, $start_time, $end_time,
                    $status, $description, $states_json,
                    $election_id, $tenant_id
                ]);
                
                logActivity($user_id, 'election_updated', "Updated election ID: $election_id");
                $action_result = ['success' => true, 'message' => 'Election updated successfully.'];
                
                // Refresh election data
                $stmt = $db->prepare("
                    SELECT e.*, u.full_name as created_by_name
                    FROM elections e
                    LEFT JOIN users u ON e.created_by = u.id
                    WHERE e.id = ? AND e.tenant_id = ? AND e.deleted_at IS NULL
                ");
                $stmt->execute([$election_id, $tenant_id]);
                $election = $stmt->fetch();
                break;
        }
    } catch (PDOException $e) {
        $action_result = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        error_log("Election update PDO Error: " . $e->getMessage());
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        error_log("Election update Error: " . $e->getMessage());
    }
}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       ELECTION EDIT - PROFESSIONAL UI STYLES
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
    
    .edit-form-container {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 32px 36px;
        box-shadow: var(--shadow);
        max-width: 900px;
        margin: 0 auto;
    }
    .edit-form-container:hover {
        box-shadow: var(--shadow-hover);
    }
    .edit-form-container .form-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 2px solid var(--gray-100);
    }
    .edit-form-container .form-header .icon {
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
    .edit-form-container .form-header h3 {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--gray-800);
    }
    .edit-form-container .form-header p {
        font-size: 0.85rem;
        color: var(--gray-500);
        margin-top: 2px;
    }
    
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
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
        font-size: 0.85rem;
        color: var(--gray-700);
    }
    .form-group label .required {
        color: var(--danger);
        margin-left: 2px;
    }
    .form-group .help-text {
        font-size: 0.75rem;
        color: var(--gray-400);
        margin-top: 2px;
    }
    .form-group input,
    .form-group select,
    .form-group textarea {
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
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 4px rgba(var(--primary-rgb), 0.08);
    }
    .form-group textarea {
        resize: vertical;
        min-height: 80px;
    }
    .form-group .checkbox-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 8px;
        padding: 8px 0;
    }
    .form-group .checkbox-grid label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 400;
        font-size: 0.85rem;
        color: var(--gray-600);
        cursor: pointer;
        padding: 6px 10px;
        border-radius: 8px;
        transition: var(--transition);
    }
    .form-group .checkbox-grid label:hover {
        background: var(--gray-50);
    }
    .form-group .checkbox-grid input[type="checkbox"] {
        width: 16px;
        height: 16px;
        accent-color: var(--primary);
        cursor: pointer;
    }
    .form-group .checkbox-grid .state-code {
        font-family: monospace;
        font-size: 0.65rem;
        color: var(--gray-400);
        background: var(--gray-100);
        padding: 1px 6px;
        border-radius: 4px;
    }
    
    .form-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 24px;
        padding-top: 20px;
        border-top: 2px solid var(--gray-100);
    }
    .form-actions .btn {
        padding: 10px 24px;
        border-radius: 10px;
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
    
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .badge-status .dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        display: inline-block;
    }
    .badge-status.draft { background: rgba(148, 163, 184, 0.2); color: var(--gray-500); }
    .badge-status.draft .dot { background: var(--gray-400); }
    .badge-status.upcoming { background: rgba(245, 158, 11, 0.2); color: #92400E; }
    .badge-status.upcoming .dot { background: #F59E0B; }
    .badge-status.active { background: rgba(16, 185, 129, 0.2); color: #065F46; }
    .badge-status.active .dot { background: #10B981; }
    .badge-status.closed { background: rgba(59, 130, 246, 0.2); color: #1E40AF; }
    .badge-status.closed .dot { background: #3B82F6; }
    .badge-status.cancelled { background: rgba(239, 68, 68, 0.2); color: #991B1B; }
    .badge-status.cancelled .dot { background: #EF4444; }
    .badge-status.archived { background: rgba(148, 163, 184, 0.2); color: var(--gray-500); }
    .badge-status.archived .dot { background: var(--gray-400); }
    
    .toast {
        padding: 14px 20px;
        border-radius: 10px;
        color: white;
        font-size: 0.85rem;
        font-weight: 500;
        box-shadow: var(--shadow-hover);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .toast.success { background: var(--secondary); }
    .toast.error { background: var(--danger); }
    
    @media (max-width: 768px) {
        .edit-form-container {
            padding: 20px;
        }
        .form-grid {
            grid-template-columns: 1fr;
        }
        .form-group .checkbox-grid {
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .form-actions {
            flex-direction: column;
        }
        .form-actions .btn {
            width: 100%;
            justify-content: center;
        }
    }
    @media (max-width: 480px) {
        .edit-form-container {
            padding: 14px;
        }
        .edit-form-container .form-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .form-group .checkbox-grid {
            grid-template-columns: 1fr 1fr;
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
                    <i class="fas fa-edit" style="color:var(--primary);margin-right:8px;"></i> Edit Election
                    <small>Update election details and configuration</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="elections-view.php?id=<?php echo $election_id; ?>" class="btn-outline">
                    <i class="fas fa-eye"></i> View
                </a>
                <a href="elections.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Toast Messages -->
        <?php if (!empty($action_result['message'])): ?>
        <div class="toast <?php echo $action_result['success'] ? 'success' : 'error'; ?>">
            <i class="fas <?php echo $action_result['success'] ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($action_result['message']); ?>
        </div>
        <?php endif; ?>

        <!-- Edit Form -->
        <div class="edit-form-container">
            <div class="form-header">
                <div class="icon">
                    <i class="fas fa-vote-yea"></i>
                </div>
                <div>
                    <h3>Edit Election: <?php echo htmlspecialchars($election['name']); ?></h3>
                    <p>Update the election details below. Fields marked with <span style="color:var(--danger);">*</span> are required.</p>
                </div>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="action" value="update_election">
                
                <div class="form-grid">
                    <!-- Election Name -->
                    <div class="form-group">
                        <label>Election Name <span class="required">*</span></label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($election['name']); ?>" required>
                    </div>

                    <!-- Type -->
                    <div class="form-group">
                        <label>Election Type <span class="required">*</span></label>
                        <select name="type" required>
                            <option value="presidential" <?php echo $election['type'] == 'presidential' ? 'selected' : ''; ?>>Presidential</option>
                            <option value="governorship" <?php echo $election['type'] == 'governorship' ? 'selected' : ''; ?>>Governorship</option>
                            <option value="senatorial" <?php echo $election['type'] == 'senatorial' ? 'selected' : ''; ?>>Senatorial</option>
                            <option value="house_of_reps" <?php echo $election['type'] == 'house_of_reps' ? 'selected' : ''; ?>>House of Representatives</option>
                            <option value="house_of_assembly" <?php echo $election['type'] == 'house_of_assembly' ? 'selected' : ''; ?>>House of Assembly</option>
                            <option value="lga_chairman" <?php echo $election['type'] == 'lga_chairman' ? 'selected' : ''; ?>>LGA Chairman</option>
                            <option value="councillorship" <?php echo $election['type'] == 'councillorship' ? 'selected' : ''; ?>>Councillorship</option>
                            <option value="party_primary" <?php echo $election['type'] == 'party_primary' ? 'selected' : ''; ?>>Party Primary</option>
                            <option value="internal_party" <?php echo $election['type'] == 'internal_party' ? 'selected' : ''; ?>>Internal Party</option>
                        </select>
                    </div>

                    <!-- Cycle -->
                    <div class="form-group">
                        <label>Election Cycle <span class="required">*</span></label>
                        <input type="text" name="cycle" value="<?php echo htmlspecialchars($election['cycle']); ?>" placeholder="e.g., 2027" required>
                        <div class="help-text">E.g., 2023, 2027, 2031</div>
                    </div>

                    <!-- Election Date -->
                    <div class="form-group">
                        <label>Election Date <span class="required">*</span></label>
                        <input type="date" name="election_date" value="<?php echo htmlspecialchars($election['election_date']); ?>" required>
                    </div>

                    <!-- Start Time -->
                    <div class="form-group">
                        <label>Start Time</label>
                        <input type="time" name="start_time" value="<?php echo htmlspecialchars($election['start_time'] ?? ''); ?>">
                        <div class="help-text">When voting begins</div>
                    </div>

                    <!-- End Time -->
                    <div class="form-group">
                        <label>End Time</label>
                        <input type="time" name="end_time" value="<?php echo htmlspecialchars($election['end_time'] ?? ''); ?>">
                        <div class="help-text">When voting ends</div>
                    </div>

                    <!-- Status -->
                    <div class="form-group">
                        <label>Status <span class="required">*</span></label>
                        <select name="status" required>
                            <option value="draft" <?php echo $election['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="upcoming" <?php echo $election['status'] == 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                            <option value="active" <?php echo $election['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="closed" <?php echo $election['status'] == 'closed' ? 'selected' : ''; ?>>Closed</option>
                            <option value="cancelled" <?php echo $election['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="archived" <?php echo $election['status'] == 'archived' ? 'selected' : ''; ?>>Archived</option>
                        </select>
                    </div>

                    <!-- Description -->
                    <div class="form-group full-width">
                        <label>Description</label>
                        <textarea name="description" placeholder="Describe the election details, key dates, and other important information..." rows="4"><?php echo htmlspecialchars($election['description'] ?? ''); ?></textarea>
                    </div>

                    <!-- States -->
                    <div class="form-group full-width">
                        <label>Jurisdiction (States)</label>
                        <div class="help-text">Select the states included in this election. Leave empty for national elections.</div>
                        <?php 
                        $selected_states = json_decode($election['states_json'] ?? '[]', true);
                        ?>
                        <div class="checkbox-grid">
                            <?php foreach ($states as $state): ?>
                                <label>
                                    <input type="checkbox" name="states[]" value="<?php echo $state['id']; ?>" 
                                        <?php echo in_array($state['id'], $selected_states) ? 'checked' : ''; ?>>
                                    <?php echo htmlspecialchars($state['name']); ?>
                                    <span class="state-code"><?php echo htmlspecialchars($state['code']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <?php if (empty($states)): ?>
                            <div style="padding:12px;background:#FEF2F2;border-radius:8px;color:#991B1B;font-size:0.85rem;">
                                <i class="fas fa-exclamation-triangle"></i> No states available. Please add states first.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="elections-view.php?id=<?php echo $election_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Election
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