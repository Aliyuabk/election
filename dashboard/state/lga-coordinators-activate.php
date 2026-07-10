<?php
// ============================================================
// STATE COORDINATOR - ACTIVATE LGA COORDINATOR
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

$user_id = SessionManager::get('user_id');
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
$coordinator_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($coordinator_id <= 0) {
    header('Location: lga-coordinators.php');
    exit();
}

// Get coordinator info
$coordinator = null;
try {
    $stmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, u.status, l.name as lga_name
        FROM users u
        LEFT JOIN lgas l ON u.lga_id = l.id
        WHERE u.id = ? AND u.deleted_at IS NULL
    ");
    $stmt->execute([$coordinator_id]);
    $coordinator = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching coordinator: " . $e->getMessage());
}

if (!$coordinator) {
    header('Location: lga-coordinators.php');
    exit();
}

if ($coordinator['status'] === 'active') {
    header('Location: lga-coordinators-profiles.php?id=' . $coordinator_id . '&error=already_active');
    exit();
}

$message = '';
$error = '';

// Handle activation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $stmt->execute([$coordinator_id]);
        
        logActivity($user_id, 'coordinator_activated', 
            "Activated LGA Coordinator: {$coordinator['first_name']} {$coordinator['last_name']} (ID: $coordinator_id)",
            'user', $coordinator_id
        );
        
        $message = "Coordinator activated successfully!";
        
        // Redirect after successful activation
        header('Location: lga-coordinators-profiles.php?id=' . $coordinator_id . '&activated=1');
        exit();
    } catch (Exception $e) {
        $error = 'Failed to activate coordinator: ' . $e->getMessage();
    }
}

$page_title = 'Activate Coordinator';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.confirm-container {
    max-width: 500px;
    margin: 0 auto;
}

.confirm-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 28px 32px;
    text-align: center;
}

.confirm-card .success-icon {
    font-size: 3rem;
    color: #10B981;
    margin-bottom: 12px;
}

.confirm-card h3 {
    color: var(--gray-800);
    margin: 0 0 4px;
}

.confirm-card .coordinator-name {
    font-weight: 600;
    color: var(--primary);
}

.confirm-card .lga-name {
    color: var(--gray-500);
    font-size: 0.85rem;
}

.confirm-card .info-box {
    background: #F0F9FF;
    border: 1px solid #BAE6FD;
    border-radius: 10px;
    padding: 14px 18px;
    margin: 16px 0;
    font-size: 0.8rem;
    color: #0369A1;
}

.confirm-card .info-box i {
    margin-right: 6px;
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
    justify-content: center;
    margin-top: 16px;
}

.btn-success {
    padding: 10px 32px;
    background: #10B981;
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.btn-success:hover {
    background: #059669;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
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

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.6rem;
    padding: 3px 12px;
    border-radius: 12px;
    font-weight: 600;
}

.status-badge .dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    display: inline-block;
}

.status-badge.suspended { background: #FEF2F2; color: #991B1B; }
.status-badge.suspended .dot { background: #EF4444; }
.status-badge.pending { background: #FFFBEB; color: #92400E; }
.status-badge.pending .dot { background: #F59E0B; }

@media (max-width: 768px) {
    .confirm-card {
        padding: 20px;
    }
    .btn-group {
        flex-direction: column;
    }
    .btn-group a,
    .btn-group button {
        width: 100%;
        text-align: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="confirm-container">
            <div class="confirm-card">
                <div class="success-icon">
                    <i class="fas fa-play"></i>
                </div>
                
                <h3>Activate Coordinator</h3>
                <p class="coordinator-name">
                    <?php echo htmlspecialchars($coordinator['first_name'] . ' ' . $coordinator['last_name']); ?>
                </p>
                <p class="lga-name">
                    <i class="fas fa-map-marker-alt"></i> 
                    <?php echo htmlspecialchars($coordinator['lga_name'] ?? 'N/A'); ?> LGA
                </p>
                <p style="font-size:0.85rem;color:var(--gray-500);margin:4px 0;">
                    Status: <span class="status-badge <?php echo $coordinator['status']; ?>">
                        <span class="dot"></span>
                        <?php echo ucfirst($coordinator['status']); ?>
                    </span>
                </p>

                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    Activating this coordinator will restore their access to the system. 
                    They will be able to login and perform their duties.
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-error" style="text-align:left;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="btn-group">
                        <a href="lga-coordinators-profiles.php?id=<?php echo $coordinator_id; ?>" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn-success">
                            <i class="fas fa-check"></i> Activate Coordinator
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
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