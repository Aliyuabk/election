<?php
// ============================================================
// STATE COORDINATOR - REQUEST CORRECTION
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
$result_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($result_id <= 0) {
    header('Location: result-verification.php');
    exit();
}

// Get result details
$result = null;
try {
    $stmt = $db->prepare("
        SELECT r.*, pu.name as pu_name, pu.code as pu_code, e.name as election_name,
               u.first_name as agent_first_name, u.last_name as agent_last_name,
               u.email as agent_email, u.phone as agent_phone
        FROM results_ec8a r
        JOIN polling_units pu ON r.pu_id = pu.id
        JOIN elections e ON r.election_id = e.id
        LEFT JOIN users u ON r.agent_id = u.id
        WHERE r.id = ? AND r.tenant_id = ?
    ");
    $stmt->execute([$result_id, $tenant_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching result: " . $e->getMessage());
}

if (!$result) {
    header('Location: result-verification.php');
    exit();
}

$message = '';
$error = '';

// Handle correction request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correction_type = $_POST['correction_type'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $instructions = trim($_POST['instructions'] ?? '');
    
    if (empty($correction_type) || empty($description)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            // Update result status to flagged with correction note
            $stmt = $db->prepare("
                UPDATE results_ec8a 
                SET status = 'flagged',
                    remarks = CONCAT(COALESCE(remarks, ''), '\n', 
                        'CORRECTION REQUESTED: ', ?, '\n',
                        'Type: ', ?, '\n',
                        'Instructions: ', ?
                    ),
                    verified_by = ?,
                    verified_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([
                $description,
                $correction_type,
                $instructions,
                $user_id,
                $result_id,
                $tenant_id
            ]);
            
            logActivity($user_id, 'ec8a_correction_requested', 
                "Requested correction for EC8A result ID: $result_id for PU: {$result['pu_name']} - Type: $correction_type",
                'results_ec8a', $result_id
            );
            
            // Send notification to agent (if email available)
            if (!empty($result['agent_email'])) {
                try {
                    $agent_name = ($result['agent_first_name'] ?? '') . ' ' . ($result['agent_last_name'] ?? '');
                    $subject = "Correction Requested - EC8A Result - " . APP_NAME;
                    $body = "
                        <h2>Correction Requested</h2>
                        <p>Dear $agent_name,</p>
                        <p>A correction has been requested for the EC8A result you submitted for:</p>
                        <p><strong>Polling Unit:</strong> {$result['pu_name']} ({$result['pu_code']})</p>
                        <p><strong>Election:</strong> {$result['election_name']}</p>
                        <p><strong>Correction Type:</strong> $correction_type</p>
                        <p><strong>Description:</strong> $description</p>
                        <p><strong>Instructions:</strong> $instructions</p>
                        <p>Please login to review and resubmit the corrected result.</p>
                        <a href='" . APP_URL . "/auth/login.php'>Login Here</a>
                    ";
                    sendEmail($result['agent_email'], $subject, $body);
                } catch (Exception $e) {
                    error_log("Correction email failed: " . $e->getMessage());
                }
            }
            
            $message = 'Correction request sent successfully. The agent will be notified.';
        } catch (Exception $e) {
            $error = 'Failed to request correction: ' . $e->getMessage();
        }
    }
}

$page_title = 'Request Correction';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.request-container {
    max-width: 600px;
    margin: 0 auto;
}

.request-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 28px;
    margin-bottom: 16px;
}

.request-card .card-title {
    font-size: 0.85rem;
    font-weight: 600;
    margin: 0 0 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-700);
}

.request-card .card-title i {
    color: #F59E0B;
    margin-right: 6px;
}

.result-info {
    background: var(--gray-50);
    border-radius: 10px;
    padding: 14px 18px;
    margin-bottom: 16px;
}

.result-info .label {
    font-size: 0.65rem;
    color: var(--gray-500);
    display: block;
}

.result-info .value {
    font-weight: 500;
    color: var(--gray-800);
}

.info-box {
    background: #F0F9FF;
    border: 1px solid #BAE6FD;
    border-radius: 10px;
    padding: 12px 16px;
    margin-bottom: 16px;
    font-size: 0.75rem;
    color: #0369A1;
}

.info-box i {
    margin-right: 6px;
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

.form-group label .required {
    color: #EF4444;
    margin-left: 2px;
}

.form-group select {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    background: white;
    transition: var(--transition);
}

.form-group select:focus {
    outline: none;
    border-color: #F59E0B;
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.06);
}

.form-group textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    resize: vertical;
    min-height: 80px;
    transition: var(--transition);
}

.form-group textarea:focus {
    outline: none;
    border-color: #F59E0B;
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.06);
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

.btn-submit {
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

.btn-submit:hover {
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

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.65rem;
    padding: 4px 14px;
    border-radius: 12px;
    font-weight: 600;
}

.status-badge .dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    display: inline-block;
}

.status-badge.verified { background: #EFF6FF; color: #1E40AF; }
.status-badge.verified .dot { background: #3B82F6; }
.status-badge.pending { background: #FFFBEB; color: #92400E; }
.status-badge.pending .dot { background: #F59E0B; }

@media (max-width: 768px) {
    .request-card {
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
        <div class="request-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-edit" style="color:#F59E0B;"></i> Request Correction</h1>
                    <p class="subtitle">
                        <i class="fas fa-map-pin"></i> 
                        <?php echo htmlspecialchars($result['pu_name']); ?> - 
                        <?php echo htmlspecialchars($result['election_name'] ?? 'N/A'); ?>
                    </p>
                </div>
                <div>
                    <span class="status-badge <?php echo $result['status']; ?>">
                        <span class="dot"></span>
                        <?php echo ucfirst($result['status']); ?>
                    </span>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
                <div style="margin-top:12px;">
                    <a href="result-verification.php" class="btn-cancel">
                        <i class="fas fa-arrow-left"></i> Back to Verification
                    </a>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!$message): ?>
                <div class="request-card">
                    <div class="card-title"><i class="fas fa-edit"></i> Request Correction</div>

                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        Requesting a correction will flag this result and notify the agent to review and resubmit.
                    </div>

                    <div class="result-info">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                            <div>
                                <span class="label">Polling Unit</span>
                                <span class="value"><?php echo htmlspecialchars($result['pu_name']); ?></span>
                            </div>
                            <div>
                                <span class="label">PU Code</span>
                                <span class="value"><?php echo htmlspecialchars($result['pu_code']); ?></span>
                            </div>
                            <div>
                                <span class="label">Election</span>
                                <span class="value"><?php echo htmlspecialchars($result['election_name'] ?? 'N/A'); ?></span>
                            </div>
                            <div>
                                <span class="label">Agent</span>
                                <span class="value"><?php echo htmlspecialchars($result['agent_first_name'] ?? '') . ' ' . htmlspecialchars($result['agent_last_name'] ?? ''); ?></span>
                            </div>
                            <div>
                                <span class="label">Valid Votes</span>
                                <span class="value"><?php echo number_format($result['valid_votes']); ?></span>
                            </div>
                            <div>
                                <span class="label">Submitted At</span>
                                <span class="value"><?php echo date('M j, Y g:i A', strtotime($result['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Correction Type <span class="required">*</span></label>
                            <select name="correction_type" required>
                                <option value="">Select correction type...</option>
                                <option value="data_error">Data Entry Error</option>
                                <option value="missing_votes">Missing Votes</option>
                                <option value="incorrect_totals">Incorrect Totals</option>
                                <option value="photo_quality">Photo Quality Issue</option>
                                <option value="mismatch">Mismatch Detected</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Description of Issue <span class="required">*</span></label>
                            <textarea name="description" required placeholder="Please describe what needs to be corrected..."></textarea>
                        </div>

                        <div class="form-group">
                            <label>Instructions for Agent</label>
                            <textarea name="instructions" placeholder="Provide clear instructions on what needs to be fixed..."></textarea>
                        </div>

                        <div class="btn-group">
                            <a href="verify-ec8a.php?id=<?php echo $result_id; ?>" class="btn-cancel">
                                <i class="fas fa-arrow-left"></i> Back
                            </a>
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-paper-plane"></i> Request Correction
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
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