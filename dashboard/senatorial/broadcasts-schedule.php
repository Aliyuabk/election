<?php
// ============================================================
// SENATORIAL COORDINATOR - SCHEDULE BROADCAST
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
// GET BROADCAST ID
// ============================================================
$broadcast_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$broadcast_id) {
    header('Location: broadcasts.php');
    exit();
}

// ============================================================
// GET BROADCAST DATA
// ============================================================
$broadcast = null;
try {
    $stmt = $db->prepare("SELECT * FROM broadcasts WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$broadcast_id, $tenant_id]);
    $broadcast = $stmt->fetch();
} catch (Exception $e) {
    error_log("Error fetching broadcast: " . $e->getMessage());
}

if (!$broadcast) {
    header('Location: broadcasts.php');
    exit();
}

// Only drafts can be scheduled
if ($broadcast['status'] !== 'draft') {
    $_SESSION['flash_error'] = 'Only drafts can be scheduled.';
    header('Location: broadcasts.php');
    exit();
}

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $scheduled_at = $_POST['scheduled_at'] ?? '';
    
    if (empty($scheduled_at)) {
        $error = 'Please select a date and time to schedule the broadcast.';
    } else {
        try {
            $stmt = $db->prepare("
                UPDATE broadcasts SET 
                    scheduled_at = ?,
                    status = 'scheduled',
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$scheduled_at, $broadcast_id, $tenant_id]);
            
            logActivity($user_id, 'broadcast_scheduled', "Scheduled broadcast: {$broadcast['title']} for $scheduled_at (ID: $broadcast_id)");
            
            $_SESSION['flash_success'] = 'Broadcast scheduled successfully!';
            header('Location: broadcasts.php');
            exit();
        } catch (Exception $e) {
            $error = 'Error scheduling broadcast: ' . $e->getMessage();
            error_log("Broadcast schedule error: " . $e->getMessage());
        }
    }
}

$page_title = 'Schedule Broadcast';
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

.schedule-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 32px;
    max-width: 500px;
    margin: 0 auto;
}
.schedule-card .icon {
    font-size: 3rem;
    color: var(--primary);
    margin-bottom: 16px;
    text-align: center;
}
.schedule-card h3 {
    font-size: 1.2rem;
    font-weight: 700;
    margin-bottom: 8px;
    text-align: center;
}
.schedule-card p {
    color: var(--gray-500);
    text-align: center;
    margin-bottom: 20px;
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
.form-group input[type="datetime-local"] {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid var(--gray-200);
    border-radius: 10px;
    font-family: 'Inter', sans-serif;
    font-size: 0.9rem;
    transition: var(--transition);
    background: var(--gray-50);
    color: var(--gray-700);
}
.form-group input[type="datetime-local"]:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.broadcast-preview {
    background: var(--gray-50);
    border-radius: 10px;
    padding: 12px 16px;
    margin-bottom: 16px;
}
.broadcast-preview .label {
    font-size: 0.7rem;
    color: var(--gray-400);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.broadcast-preview .title {
    font-weight: 600;
    font-size: 0.95rem;
}
.broadcast-preview .message {
    color: var(--gray-600);
    font-size: 0.85rem;
    margin-top: 4px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.btn-group {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
    margin-top: 16px;
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
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-calendar-plus" style="color:var(--primary);margin-right:8px;"></i> 
                    Schedule Broadcast
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="broadcasts.php" class="btn btn-secondary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--gray-100);color:var(--gray-600);border:none;">
                    <i class="fas fa-arrow-left"></i> Back to Broadcasts
                </a>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>

        <div class="schedule-card">
            <div class="icon">
                <i class="fas fa-clock"></i>
            </div>
            <h3>Schedule Broadcast</h3>
            <p>Choose a date and time to automatically send this broadcast.</p>

            <div class="broadcast-preview">
                <div class="label">Broadcast</div>
                <div class="title"><?php echo htmlspecialchars($broadcast['title']); ?></div>
                <div class="message"><?php echo htmlspecialchars(substr($broadcast['message'], 0, 100)); ?>...</div>
            </div>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="scheduled_at">Date & Time <span class="required" style="color:var(--danger);">*</span></label>
                    <input type="datetime-local" name="scheduled_at" id="scheduled_at" required min="<?php echo date('Y-m-d\TH:i', strtotime('+1 hour')); ?>">
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-calendar-check"></i> Schedule
                    </button>
                    <a href="broadcasts.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
// ============================================================
// SET MINIMUM DATETIME
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    var input = document.getElementById('scheduled_at');
    var now = new Date();
    var minDate = new Date(now.getTime() + 60 * 60 * 1000); // 1 hour from now
    input.min = minDate.toISOString().slice(0, 16);
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