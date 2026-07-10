<?php
// ============================================================
// WARD COORDINATOR - ADD EC8B REMARKS
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'ward') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Ward Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$ward_id = SessionManager::get('ward_id');

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
$ec8b_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($ec8b_id <= 0) {
    header('Location: ec8b-history.php');
    exit();
}

// Get EC8B details
$ec8b = null;
try {
    $stmt = $db->prepare("
        SELECT r.*, e.name as election_name, w.name as ward_name
        FROM results_ec8b r
        JOIN elections e ON r.election_id = e.id
        JOIN wards w ON r.ward_id = w.id
        WHERE r.id = ? AND r.tenant_id = ? AND r.ward_id = ?
    ");
    $stmt->execute([$ec8b_id, $tenant_id, $ward_id]);
    $ec8b = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching EC8B: " . $e->getMessage());
}

if (!$ec8b) {
    header('Location: ec8b-history.php');
    exit();
}

$message = '';
$error = '';

// Handle remarks submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $remarks = trim($_POST['remarks'] ?? '');
    
    if (empty($remarks)) {
        $error = 'Please enter some remarks.';
    } else {
        try {
            $stmt = $db->prepare("
                UPDATE results_ec8b 
                SET remarks = CONCAT(COALESCE(remarks, ''), '\n', ?, '\n')
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$remarks, $ec8b_id, $tenant_id]);
            
            logActivity($user_id, 'ec8b_remarks_added', 
                "Added remarks to EC8B ID: $ec8b_id",
                'results_ec8b', $ec8b_id
            );
            
            $message = "Remarks added successfully!";
            
            // Refresh data
            $stmt = $db->prepare("SELECT * FROM results_ec8b WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$ec8b_id, $tenant_id]);
            $ec8b = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $error = 'Failed to add remarks: ' . $e->getMessage();
        }
    }
}

$page_title = 'EC8B Remarks';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.remarks-container {
    max-width: 700px;
    margin: 0 auto;
}

.remarks-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 28px;
    margin-bottom: 16px;
}

.remarks-card .card-title {
    font-size: 0.85rem;
    font-weight: 600;
    margin: 0 0 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-700);
}

.remarks-card .card-title i {
    color: #F59E0B;
    margin-right: 6px;
}

.ec8b-info {
    background: var(--gray-50);
    border-radius: 8px;
    padding: 10px 14px;
    margin-bottom: 16px;
}

.ec8b-info .label {
    font-size: 0.6rem;
    color: var(--gray-500);
    display: block;
}

.ec8b-info .value {
    font-weight: 500;
    color: var(--gray-800);
    font-size: 0.85rem;
}

.existing-remarks {
    background: var(--gray-50);
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 16px;
    max-height: 200px;
    overflow-y: auto;
}

.existing-remarks .remark-item {
    padding: 4px 0;
    border-bottom: 1px solid var(--gray-200);
    font-size: 0.8rem;
}

.existing-remarks .remark-item:last-child {
    border-bottom: none;
}

.existing-remarks .remark-item .timestamp {
    font-size: 0.6rem;
    color: var(--gray-400);
}

.existing-remarks .remark-item .content {
    color: var(--gray-700);
}

.form-group {
    margin-bottom: 14px;
}

.form-group label {
    display: block;
    font-weight: 600;
    font-size: 0.8rem;
    color: var(--gray-700);
    margin-bottom: 4px;
}

.form-group label .required {
    color: #EF4444;
    margin-left: 2px;
}

.form-group textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    resize: vertical;
    min-height: 100px;
    transition: var(--transition);
}

.form-group textarea:focus {
    outline: none;
    border-color: #F59E0B;
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.06);
}

.alert {
    padding: 10px 14px;
    border-radius: 8px;
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
    flex-wrap: wrap;
}

.btn-group button {
    padding: 8px 20px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.82rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.btn-group .btn-submit {
    background: #F59E0B;
    color: white;
}

.btn-group .btn-submit:hover {
    background: #D97706;
}

.btn-group .btn-cancel {
    background: var(--gray-100);
    color: var(--gray-700);
    text-decoration: none;
    padding: 8px 20px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.82rem;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-group .btn-cancel:hover {
    background: var(--gray-200);
}

.empty-remarks {
    text-align: center;
    padding: 16px;
    color: var(--gray-400);
    font-size: 0.85rem;
}

@media (max-width: 768px) {
    .remarks-card {
        padding: 16px 18px;
    }
    .btn-group {
        flex-direction: column;
    }
    .btn-group button,
    .btn-group .btn-cancel {
        width: 100%;
        justify-content: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="remarks-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-comment"></i> EC8B Remarks</h1>
                    <p class="subtitle">
                        <i class="fas fa-file-alt"></i> 
                        EC8B #<?php echo $ec8b_id; ?> - <?php echo htmlspecialchars($ec8b['election_name']); ?>
                    </p>
                </div>
                <div class="actions">
                    <a href="ec8b-history.php" class="btn-secondary-sm">
                        <i class="fas fa-history"></i> Back to History
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="remarks-card">
                <div class="card-title"><i class="fas fa-info-circle"></i> EC8B Information</div>
                
                <div class="ec8b-info">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
                        <div>
                            <span class="label">Election</span>
                            <span class="value"><?php echo htmlspecialchars($ec8b['election_name']); ?></span>
                        </div>
                        <div>
                            <span class="label">Ward</span>
                            <span class="value"><?php echo htmlspecialchars($ec8b['ward_name']); ?></span>
                        </div>
                        <div>
                            <span class="label">Valid Votes</span>
                            <span class="value"><?php echo number_format($ec8b['valid_votes']); ?></span>
                        </div>
                        <div>
                            <span class="label">Status</span>
                            <span class="value">
                                <span class="status-badge <?php echo $ec8b['status']; ?>">
                                    <span class="dot"></span>
                                    <?php echo ucfirst($ec8b['status']); ?>
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Existing Remarks -->
            <div class="remarks-card">
                <div class="card-title"><i class="fas fa-history"></i> Existing Remarks</div>
                
                <?php if (!empty($ec8b['remarks'])): ?>
                    <div class="existing-remarks">
                        <?php 
                            $remarks_lines = explode("\n", $ec8b['remarks']);
                            foreach ($remarks_lines as $line):
                                if (empty(trim($line))) continue;
                        ?>
                            <div class="remark-item">
                                <div class="content"><?php echo htmlspecialchars($line); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-remarks">
                        <i class="fas fa-comment" style="font-size:1.2rem;display:block;margin-bottom:4px;"></i>
                        <p>No remarks added yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Add Remarks -->
            <div class="remarks-card">
                <div class="card-title"><i class="fas fa-plus-circle" style="color:#F59E0B;"></i> Add Remarks</div>

                <form method="POST" action="">
                    <div class="form-group">
                        <label>Remarks <span class="required">*</span></label>
                        <textarea name="remarks" required placeholder="Enter your remarks about this EC8B form..."></textarea>
                    </div>

                    <div class="btn-group">
                        <a href="ec8b-history.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-plus"></i> Add Remarks
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