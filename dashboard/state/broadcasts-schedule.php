<?php
// ============================================================
// STATE COORDINATOR - SCHEDULE BROADCAST
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'state') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'State Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$state_id = SessionManager::get('state_id');

if (empty($state_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT state_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['state_id'])) {
            $state_id = $user['state_id'];
            SessionManager::set('state_id', $state_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching state_id: " . $e->getMessage());
    }
}

$db = getDB();
$broadcast_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($broadcast_id <= 0) {
    header('Location: broadcasts.php');
    exit();
}

// Get broadcast details
$broadcast = null;
try {
    $stmt = $db->prepare("
        SELECT * FROM broadcasts 
        WHERE id = ? AND tenant_id = ? AND status IN ('draft', 'scheduled')
    ");
    $stmt->execute([$broadcast_id, $tenant_id]);
    $broadcast = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching broadcast: " . $e->getMessage());
}

if (!$broadcast) {
    header('Location: broadcasts.php');
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $scheduled_at = $_POST['scheduled_at'] ?? '';
    
    if (empty($scheduled_at)) {
        $error = 'Please select a date and time to schedule.';
    } else {
        try {
            $stmt = $db->prepare("
                UPDATE broadcasts 
                SET status = 'scheduled', scheduled_at = ?
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$scheduled_at, $broadcast_id, $tenant_id]);
            
            logActivity($user_id, 'broadcast_scheduled', 
                "Scheduled broadcast: {$broadcast['title']} for " . date('M j, Y g:i A', strtotime($scheduled_at)),
                'broadcasts', $broadcast_id
            );
            
            $message = "Broadcast scheduled successfully for " . date('M j, Y g:i A', strtotime($scheduled_at));
        } catch (Exception $e) {
            $error = 'Failed to schedule broadcast: ' . $e->getMessage();
        }
    }
}

$page_title = 'Schedule Broadcast';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.schedule-container {
    max-width: 500px;
    margin: 0 auto;
}

.schedule-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 28px;
}

.broadcast-info {
    background: var(--gray-50);
    border-radius: 10px;
    padding: 14px 18px;
    margin-bottom: 16px;
}

.broadcast-info .title {
    font-weight: 600;
    color: var(--gray-800);
}

.broadcast-info .preview {
    font-size: 0.8rem;
    color: var(--gray-500);
    margin-top: 4px;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    font-weight: 600;
    font-size: 0.8rem;
    color: var(--gray-700);
    margin-bottom: 4px;
}

.form-group input[type="datetime-local"] {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    transition: var(--transition);
}

.form-group input[type="datetime-local"]:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.alert {
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 0.85rem;
    margin-bottom: 16px;
}

.alert-success {
    background: #ECFDF5;
    color: #065F46;
    border: 1px solid #A7F3D0;
}

.alert-error {
    background: #FEF2F2;
    color: #991B1B;
    border: 1px solid #FECACA;
}

.alert i {
    margin-right: 6px;
}

.btn-group {
    display: flex;
    gap: 10px;
    margin-top: 8px;
}

.btn-schedule {
    padding: 10px 32px;
    background: #F59E0B;
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.btn-schedule:hover {
    background: #D97706;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.btn-cancel {
    padding: 10px 32px;
    background: var(--gray-100);
    color: var(--gray-700);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    text-decoration: none;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-cancel:hover {
    background: var(--gray-200);
}

@media (max-width: 768px) {
    .schedule-card {
        padding: 16px 18px;
    }
    .btn-group {
        flex-direction: column;
    }
    .btn-group button,
    .btn-group a {
        width: 100%;
        justify-content: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="schedule-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-calendar-plus"></i> Schedule Broadcast</h1>
                    <p class="subtitle">
                        <i class="fas fa-bullhorn"></i> 
                        <?php echo htmlspecialchars($broadcast['title']); ?>
                    </p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
                <div style="margin-top:12px;">
                    <a href="broadcasts.php" class="btn-cancel">
                        <i class="fas fa-arrow-left"></i> Back to Broadcasts
                    </a>
                </div>
            <?php else: ?>
                <div class="schedule-card">
                    <div class="broadcast-info">
                        <div class="title"><i class="fas fa-bullhorn"></i> <?php echo htmlspecialchars($broadcast['title']); ?></div>
                        <div class="preview"><?php echo htmlspecialchars(substr($broadcast['message'], 0, 100)) . (strlen($broadcast['message']) > 100 ? '...' : ''); ?></div>
                        <div style="font-size:0.7rem;color:var(--gray-400);margin-top:4px;">
                            Target: <?php echo ucfirst($broadcast['target_audience']); ?>
                        </div>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Schedule Date & Time <span class="required">*</span></label>
                            <input type="datetime-local" name="scheduled_at" required 
                                   min="<?php echo date('Y-m-d\TH:i', strtotime('+1 hour')); ?>" />
                            <div class="help-text" style="font-size:0.65rem;color:var(--gray-400);margin-top:4px;">
                                Minimum 1 hour from now
                            </div>
                        </div>

                        <div class="btn-group">
                            <a href="broadcasts.php" class="btn-cancel">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn-schedule">
                                <i class="fas fa-calendar-plus"></i> Schedule
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
// Set minimum datetime to 1 hour from now
document.addEventListener('DOMContentLoaded', function() {
    var input = document.querySelector('input[name="scheduled_at"]');
    if (input) {
        var now = new Date();
        now.setHours(now.getHours() + 1);
        var min = now.toISOString().slice(0, 16);
        input.min = min;
    }
});

// Same sidebar scripts as index.php
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});

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
</script>
</body>
</html>