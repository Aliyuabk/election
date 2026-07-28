<?php
// ============================================================
// SENATORIAL COORDINATOR - ASSIGN INCIDENT PRIORITY
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'senatorial') {
    header('Location: ../client-admin/');
    exit();
}

$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');

$db = getDB();

// ============================================================
// GET INCIDENT ID
// ============================================================
$incident_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$incident_id) {
    header('Location: incidents.php');
    exit();
}

// ============================================================
// GET INCIDENT DATA
// ============================================================
$incident = null;
try {
    $stmt = $db->prepare("SELECT * FROM incidents WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$incident_id, $tenant_id]);
    $incident = $stmt->fetch();
} catch (Exception $e) {
    error_log("Error fetching incident: " . $e->getMessage());
}

if (!$incident) {
    header('Location: incidents.php');
    exit();
}

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $severity = $_POST['severity'] ?? '';
    
    if (empty($severity)) {
        $error = 'Please select a priority level.';
    } else {
        try {
            $stmt = $db->prepare("
                UPDATE incidents SET 
                    severity = ?,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$severity, $incident_id, $tenant_id]);
            
            logActivity($user_id, 'incident_priority', "Assigned priority $severity to incident: {$incident['title']} (ID: $incident_id)");
            
            $_SESSION['flash_success'] = 'Incident priority updated successfully!';
            header('Location: incident-details.php?id=' . $incident_id);
            exit();
        } catch (Exception $e) {
            $error = 'Error updating priority: ' . $e->getMessage();
            error_log("Incident priority error: " . $e->getMessage());
        }
    }
}

$page_title = 'Assign Priority';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
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

.form-container {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 28px 32px;
    max-width: 500px;
    margin: 0 auto;
}

.form-group {
    margin-bottom: 16px;
}
.form-group label {
    display: block;
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--gray-700);
    margin-bottom: 4px;
}
.form-group select {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid var(--gray-200);
    border-radius: 10px;
    font-family: 'Inter', sans-serif;
    font-size: 0.85rem;
    transition: var(--transition);
    background: var(--gray-50);
    color: var(--gray-700);
}
.form-group select:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.priority-options {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin: 12px 0;
}
.priority-option {
    padding: 16px;
    border: 2px solid var(--gray-200);
    border-radius: 10px;
    text-align: center;
    cursor: pointer;
    transition: var(--transition);
}
.priority-option:hover {
    border-color: var(--primary);
}
.priority-option input[type="radio"] {
    display: none;
}
.priority-option.selected {
    border-color: var(--primary);
    background: rgba(37, 99, 235, 0.04);
}
.priority-option .icon {
    font-size: 1.5rem;
    display: block;
    margin-bottom: 4px;
}
.priority-option .label {
    font-size: 0.85rem;
    font-weight: 600;
}
.priority-option .desc {
    font-size: 0.7rem;
    color: var(--gray-400);
}
.priority-option.critical .icon { color: #7F1D1D; }
.priority-option.high .icon { color: #DC2626; }
.priority-option.medium .icon { color: #F59E0B; }
.priority-option.low .icon { color: #10B981; }

.btn {
    padding: 10px 28px;
    border-radius: 10px;
    border: none;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-primary {
    background: var(--primary);
    color: white;
}
.btn-primary:hover {
    background: var(--primary-dark);
}
.btn-secondary {
    background: var(--gray-100);
    color: var(--gray-600);
}
.btn-secondary:hover {
    background: var(--gray-200);
}

.alert {
    padding: 14px 18px;
    border-radius: 10px;
    font-size: 0.85rem;
    margin-bottom: 16px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    border: 1px solid transparent;
}
.alert i {
    margin-top: 2px;
    font-size: 1.1rem;
}
.alert-error {
    background: #FEF2F2;
    color: #DC2626;
    border-color: #FECACA;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 20px;
    flex-wrap: wrap;
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-flag" style="color:var(--primary);margin-right:8px;"></i> 
                    Assign Priority
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="incident-details.php?id=<?php echo $incident_id; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <div class="form-title">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($incident['title']); ?>
            </div>
            <div style="color:var(--gray-500);font-size:0.85rem;margin-bottom:16px;">
                Current Priority: <strong><?php echo ucfirst($incident['severity']); ?></strong>
            </div>

            <form method="POST" action="">
                <div class="form-group">
                    <label>Select Priority Level <span class="required" style="color:var(--danger);">*</span></label>
                    <div class="priority-options">
                        <label class="priority-option critical <?php echo $incident['severity'] === 'critical' ? 'selected' : ''; ?>" onclick="selectPriority(this)">
                            <input type="radio" name="severity" value="critical" <?php echo $incident['severity'] === 'critical' ? 'checked' : ''; ?>>
                            <span class="icon"><i class="fas fa-exclamation-circle"></i></span>
                            <span class="label">Critical</span>
                            <span class="desc">Immediate action</span>
                        </label>
                        <label class="priority-option high <?php echo $incident['severity'] === 'high' ? 'selected' : ''; ?>" onclick="selectPriority(this)">
                            <input type="radio" name="severity" value="high" <?php echo $incident['severity'] === 'high' ? 'checked' : ''; ?>>
                            <span class="icon"><i class="fas fa-exclamation-triangle"></i></span>
                            <span class="label">High</span>
                            <span class="desc">Urgent attention</span>
                        </label>
                        <label class="priority-option medium <?php echo $incident['severity'] === 'medium' ? 'selected' : ''; ?>" onclick="selectPriority(this)">
                            <input type="radio" name="severity" value="medium" <?php echo $incident['severity'] === 'medium' ? 'checked' : ''; ?>>
                            <span class="icon"><i class="fas fa-minus-circle"></i></span>
                            <span class="label">Medium</span>
                            <span class="desc">Normal priority</span>
                        </label>
                        <label class="priority-option low <?php echo $incident['severity'] === 'low' ? 'selected' : ''; ?>" onclick="selectPriority(this)">
                            <input type="radio" name="severity" value="low" <?php echo $incident['severity'] === 'low' ? 'checked' : ''; ?>>
                            <span class="icon"><i class="fas fa-check-circle"></i></span>
                            <span class="label">Low</span>
                            <span class="desc">Minimal priority</span>
                        </label>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Assign Priority
                    </button>
                    <a href="incident-details.php?id=<?php echo $incident_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
// ============================================================
// SELECT PRIORITY
// ============================================================
function selectPriority(element) {
    document.querySelectorAll('.priority-option').forEach(function(el) {
        el.classList.remove('selected');
    });
    element.classList.add('selected');
    element.querySelector('input[type="radio"]').checked = true;
}

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