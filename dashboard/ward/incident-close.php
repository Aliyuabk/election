<?php
// ============================================================
// WARD COORDINATOR - CLOSE INCIDENT
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Start session
SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

// Only Ward coordinator can access
if (SessionManager::get('role_level') !== 'ward') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$ward_id = SessionManager::get('ward_id');
$tenant_id = SessionManager::get('tenant_id');

// If ward_id is not set in session, try to get it from user record
if (empty($ward_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT ward_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['ward_id'])) {
            $ward_id = $user['ward_id'];
            SessionManager::set('ward_id', $ward_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching ward_id: " . $e->getMessage());
    }
}

$db = getDB();

// ============================================================
// GET INCIDENT ID
// ============================================================
$incident_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($incident_id <= 0) {
    header('Location: incidents.php');
    exit();
}

// ============================================================
// FETCH INCIDENT DETAILS
// ============================================================
$incident = null;
$error_message = '';

try {
    $stmt = $db->prepare("
        SELECT 
            i.*,
            u.full_name as reporter_name,
            pu.name as pu_name,
            pu.code as pu_code
        FROM incidents i
        LEFT JOIN users u ON i.reporter_id = u.id
        LEFT JOIN polling_units pu ON i.pu_id = pu.id
        WHERE i.id = ? AND i.tenant_id = ? AND i.ward_id = ?
    ");
    $stmt->execute([$incident_id, $tenant_id, $ward_id]);
    $incident = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$incident) {
        header('Location: incidents.php?error=notfound');
        exit();
    }
    
    if ($incident['status'] === 'closed') {
        header('Location: incidents.php?error=already_closed');
        exit();
    }
    
} catch (Exception $e) {
    error_log("Error fetching incident: " . $e->getMessage());
    header('Location: incidents.php?error=db');
    exit();
}

// ============================================================
// HANDLE CLOSING
// ============================================================
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $closing_notes = isset($_POST['closing_notes']) ? trim($_POST['closing_notes']) : '';
    
    if ($action === 'close') {
        if (empty($closing_notes)) {
            $error_message = "Please provide closing notes.";
        } else {
            try {
                $stmt = $db->prepare("
                    UPDATE incidents 
                    SET status = 'closed', resolved_by = ?, resolved_at = NOW(), 
                        resolution_notes = CONCAT(COALESCE(resolution_notes, ''), '\n', 'CLOSED: ', ?), updated_at = NOW()
                    WHERE id = ? AND tenant_id = ? AND ward_id = ?
                ");
                $stmt->execute([$user_id, $closing_notes, $incident_id, $tenant_id, $ward_id]);
                
                logActivity($user_id, 'incident_closed', "Closed incident ID: $incident_id", 'incidents', $incident_id);
                
                $success_message = "Incident closed successfully!";
                header('Location: incidents.php?success=' . urlencode($success_message));
                exit();
                
            } catch (Exception $e) {
                $error_message = "Error closing incident: " . $e->getMessage();
                error_log("Incident close error: " . $e->getMessage());
            }
        }
    }
}

$page_title = 'Close Incident';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.close-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.close-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.close-header h2 i {
    color: #6B7280;
}

.incident-info {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px;
    margin-bottom: 20px;
}
.incident-info .info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 8px 16px;
}
.incident-info .info-grid .item {
    font-size: 0.85rem;
    padding: 4px 0;
}
.incident-info .info-grid .item .label {
    color: var(--gray-500);
    font-weight: 500;
}
.incident-info .info-grid .item .value {
    color: var(--gray-800);
}

.confirm-box {
    background: #F3F4F6;
    border: 1px solid #D1D5DB;
    border-radius: var(--radius);
    padding: 20px;
    margin-bottom: 20px;
    text-align: center;
}
.confirm-box .icon {
    font-size: 3rem;
    color: #6B7280;
    margin-bottom: 12px;
}
.confirm-box h3 {
    color: #374151;
    margin: 0 0 8px;
}
.confirm-box p {
    color: #4B5563;
    font-size: 0.95rem;
    margin: 0;
}

.close-form {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
}
.close-form .form-group {
    margin-bottom: 16px;
}
.close-form .form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 4px;
}
.close-form .form-group label .required {
    color: #EF4444;
}
.close-form .form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    resize: vertical;
    min-height: 80px;
    font-family: inherit;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 16px;
}
.form-actions .btn-secondary {
    background: #6B7280;
    color: white;
    border-color: #6B7280;
}
.form-actions .btn-secondary:hover {
    background: #4B5563;
    border-color: #4B5563;
}

.alert {
    padding: 12px 16px;
    border-radius: var(--radius);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-danger {
    background: #FEF2F2;
    border: 1px solid #FEE2E2;
    color: #991B1B;
}
.alert i {
    font-size: 1.1rem;
}

.status-badge {
    font-size: 0.6rem;
    padding: 2px 10px;
    border-radius: 20px;
    font-weight: 500;
}
.status-badge.reported { background: #FEF3C7; color: #92400E; }
.status-badge.investigating { background: #DBEAFE; color: #1E40AF; }
.status-badge.resolved { background: #D1FAE5; color: #065F46; }

@media (max-width: 768px) {
    .incident-info .info-grid {
        grid-template-columns: 1fr;
    }
    .form-actions {
        flex-direction: column;
    }
    .form-actions button,
    .form-actions a {
        width: 100%;
        text-align: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="close-header">
            <div>
                <h2><i class="fas fa-times-circle"></i> Close Incident</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward • Incident #<?php echo $incident_id; ?>
                </p>
            </div>
            <div>
                <a href="incidents.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Incidents
                </a>
            </div>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($incident): ?>
            <!-- Incident Information -->
            <div class="incident-info">
                <h3 style="margin:0 0 12px;font-size:0.95rem;">
                    <i class="fas fa-info-circle"></i> Incident Details
                </h3>
                <div class="info-grid">
                    <div class="item">
                        <span class="label">Title</span><br>
                        <span class="value"><?php echo htmlspecialchars($incident['title']); ?></span>
                    </div>
                    <div class="item">
                        <span class="label">Type</span><br>
                        <span class="value"><?php echo ucfirst(str_replace('_', ' ', $incident['incident_type'] ?? 'Unknown')); ?></span>
                    </div>
                    <div class="item">
                        <span class="label">Status</span><br>
                        <span class="status-badge <?php echo $incident['status']; ?>"><?php echo ucfirst($incident['status']); ?></span>
                    </div>
                    <div class="item">
                        <span class="label">Severity</span><br>
                        <span class="value"><?php echo ucfirst($incident['severity'] ?? 'Medium'); ?></span>
                    </div>
                    <div class="item">
                        <span class="label">Reported By</span><br>
                        <span class="value"><?php echo htmlspecialchars($incident['reporter_name'] ?? 'Unknown'); ?></span>
                    </div>
                    <div class="item">
                        <span class="label">Polling Unit</span><br>
                        <span class="value"><?php echo htmlspecialchars($incident['pu_name'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="item">
                        <span class="label">Created</span><br>
                        <span class="value"><?php echo date('M d, Y H:i', strtotime($incident['created_at'])); ?></span>
                    </div>
                    <?php if (!empty($incident['resolution_notes'])): ?>
                        <div class="item" style="grid-column:1/-1;">
                            <span class="label">Resolution Notes</span><br>
                            <span class="value"><?php echo nl2br(htmlspecialchars(substr($incident['resolution_notes'], 0, 200))); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Confirmation -->
            <div class="confirm-box">
                <div class="icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3>Close Incident</h3>
                <p>Confirm that this incident is fully resolved and can be closed. This will archive the incident.</p>
            </div>

            <!-- Close Form -->
            <div class="close-form">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="close">
                    
                    <div class="form-group">
                        <label>Closing Notes <span class="required">*</span></label>
                        <textarea name="closing_notes" id="closing_notes" placeholder="Provide final notes for closing this incident..." required></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-secondary" onclick="return confirm('Are you sure you want to close this incident?')">
                            <i class="fas fa-check"></i> Close Incident
                        </button>
                        <a href="incidents.php" class="btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>

        <?php else: ?>
            <div style="text-align:center;padding:60px 20px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);">
                <i class="fas fa-exclamation-triangle" style="font-size:4rem;color:var(--gray-300);"></i>
                <h4 style="margin:16px 0 8px;">Incident Not Found</h4>
                <p style="color:var(--gray-500);">The incident you're trying to close does not exist.</p>
                <a href="incidents.php" class="btn-primary-sm" style="display:inline-block;margin-top:12px;">
                    <i class="fas fa-arrow-left"></i> Back to Incidents
                </a>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
// Preloader
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});

// Sidebar toggle
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

// Sidebar dropdowns
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

// Profile dropdown
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
</script>
</body>
</html>