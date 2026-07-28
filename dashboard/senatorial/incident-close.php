<?php
// ============================================================
// SENATORIAL COORDINATOR - CLOSE/RESOLVE INCIDENT
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

// Only reported or investigating incidents can be closed
if (!in_array($incident['status'], ['reported', 'investigating'])) {
    $_SESSION['flash_error'] = 'This incident has already been resolved or escalated.';
    header('Location: incident-details.php?id=' . $incident_id);
    exit();
}

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resolution_notes = trim($_POST['resolution_notes'] ?? '');
    
    if (empty($resolution_notes)) {
        $error = 'Please provide resolution notes.';
    } else {
        try {
            $stmt = $db->prepare("
                UPDATE incidents SET 
                    status = 'resolved',
                    resolved_at = NOW(),
                    resolved_by = ?,
                    resolution_notes = ?,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$user_id, $resolution_notes, $incident_id, $tenant_id]);
            
            logActivity($user_id, 'incident_resolved', "Resolved incident: {$incident['title']} (ID: $incident_id)");
            
            $_SESSION['flash_success'] = 'Incident resolved successfully!';
            header('Location: incident-details.php?id=' . $incident_id);
            exit();
        } catch (Exception $e) {
            $error = 'Error resolving incident: ' . $e->getMessage();
            error_log("Incident resolve error: " . $e->getMessage());
        }
    }
}

$page_title = 'Resolve Incident';
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
    max-width: 600px;
    margin: 0 auto;
}
.form-container .form-title {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.form-container .form-subtitle {
    color: var(--gray-500);
    font-size: 0.85rem;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--gray-100);
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
.form-group textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid var(--gray-200);
    border-radius: 10px;
    font-family: 'Inter', sans-serif;
    font-size: 0.85rem;
    transition: var(--transition);
    background: var(--gray-50);
    color: var(--gray-700);
    min-height: 120px;
    resize: vertical;
}
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

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
.btn-success {
    background: #10B981;
    color: white;
}
.btn-success:hover {
    background: #059669;
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

.incident-info {
    background: var(--gray-50);
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 16px;
}
.incident-info .label {
    font-size: 0.7rem;
    color: var(--gray-400);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.incident-info .value {
    font-weight: 600;
    font-size: 0.95rem;
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-check-circle" style="color:var(--success);margin-right:8px;"></i> 
                    Resolve Incident
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
                <i class="fas fa-exclamation-triangle"></i> Resolve Incident
            </div>
            <div class="form-subtitle">
                Mark "<?php echo htmlspecialchars($incident['title']); ?>" as resolved.
            </div>

            <div class="incident-info">
                <div class="label">Incident</div>
                <div class="value"><?php echo htmlspecialchars($incident['title']); ?></div>
                <div style="font-size:0.8rem;color:var(--gray-500);margin-top:4px;">
                    Status: <span class="status-badge <?php echo $incident['status']; ?>" style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:0.65rem;font-weight:600;background:#FEE2E2;color:#DC2626;">
                        <?php echo ucfirst($incident['status']); ?>
                    </span>
                </div>
            </div>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="resolution_notes">Resolution Notes <span class="required" style="color:var(--danger);">*</span></label>
                    <textarea name="resolution_notes" id="resolution_notes" placeholder="Describe how this incident was resolved..." required></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check-circle"></i> Resolve Incident
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