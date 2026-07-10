<?php
// ============================================================
// LGA COORDINATOR - CLOSE INCIDENT
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'lga') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'LGA Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$lga_id = SessionManager::get('lga_id');

if (empty($lga_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT lga_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['lga_id'])) {
            $lga_id = $user['lga_id'];
            SessionManager::set('lga_id', $lga_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching lga_id: " . $e->getMessage());
    }
}

$db = getDB();

// Get LGA name
$lga_name = 'LGA';
try {
    if ($lga_id) {
        $stmt = $db->prepare("SELECT name FROM lgas WHERE id = ?");
        $stmt->execute([$lga_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $lga_name = $result['name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching LGA: " . $e->getMessage());
}

$incident_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($incident_id <= 0) {
    header('Location: incidents.php');
    exit();
}

// Get incident details
$incident = null;
try {
    $stmt = $db->prepare("
        SELECT i.*, 
               u.first_name as reporter_first_name, 
               u.last_name as reporter_last_name,
               pu.name as pu_name,
               w.name as ward_name,
               resolved.first_name as resolved_first_name,
               resolved.last_name as resolved_last_name
        FROM incidents i
        LEFT JOIN users u ON i.reporter_id = u.id
        LEFT JOIN polling_units pu ON i.pu_id = pu.id
        LEFT JOIN wards w ON pu.ward_id = w.id
        LEFT JOIN users resolved ON i.resolved_by = resolved.id
        WHERE i.id = ? AND i.tenant_id = ? AND i.lga_id = ?
    ");
    $stmt->execute([$incident_id, $tenant_id, $lga_id]);
    $incident = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching incident: " . $e->getMessage());
}

if (!$incident) {
    header('Location: incidents.php');
    exit();
}

// Only allow closing if status is 'resolved'
if ($incident['status'] !== 'resolved') {
    header('Location: incidents.php?error=not_resolved');
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $close_notes = trim($_POST['close_notes'] ?? '');
    
    try {
        $stmt = $db->prepare("
            UPDATE incidents 
            SET status = 'closed', 
                updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$incident_id, $tenant_id]);
        
        // Add notes
        $notes = $incident['resolution_notes'] ?? '';
        $new_notes = $notes . "\n[" . date('Y-m-d H:i:s') . "] CLOSED: " . $close_notes;
        $stmt = $db->prepare("UPDATE incidents SET resolution_notes = ? WHERE id = ?");
        $stmt->execute([$new_notes, $incident_id]);
        
        logActivity($user_id, 'incident_closed', 
            "Closed incident #$incident_id",
            'incidents', $incident_id
        );
        
        $message = "Incident closed successfully!";
        
        // Refresh incident data
        $stmt = $db->prepare("SELECT * FROM incidents WHERE id = ?");
        $stmt->execute([$incident_id]);
        $incident = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = 'Failed to close incident: ' . $e->getMessage();
    }
}

$incident_types = [
    'violence' => 'Violence',
    'intimidation' => 'Intimidation',
    'ballot_stuffing' => 'Ballot Stuffing',
    'vote_buying' => 'Vote Buying',
    'voter_suppression' => 'Voter Suppression',
    'material_shortage' => 'Material Shortage',
    'delay' => 'Delay',
    'technical_issue' => 'Technical Issue',
    'other' => 'Other',
    'panic_button' => 'Panic Button'
];

$page_title = 'Close Incident';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.close-container {
    max-width: 600px;
    margin: 0 auto;
}

.close-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 28px;
}

.close-card .card-title {
    font-size: 0.85rem;
    font-weight: 600;
    margin: 0 0 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-700);
}

.close-card .card-title i {
    color: #6B7280;
    margin-right: 6px;
}

.incident-info {
    background: var(--gray-50);
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 16px;
}

.incident-info .label {
    font-size: 0.6rem;
    color: var(--gray-500);
    display: block;
}

.incident-info .value {
    font-weight: 500;
    color: var(--gray-800);
    font-size: 0.85rem;
}

.detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px;
}

.detail-item {
    font-size: 0.8rem;
}

.detail-item .label {
    color: var(--gray-500);
    font-size: 0.6rem;
    display: block;
}

.detail-item .value {
    font-weight: 500;
    color: var(--gray-800);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.55rem;
    padding: 2px 10px;
    border-radius: 10px;
    font-weight: 600;
}

.status-badge .dot {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    display: inline-block;
}

.status-badge.resolved { background: #ECFDF5; color: #065F46; }
.status-badge.resolved .dot { background: #10B981; }

.form-group {
    margin-bottom: 14px;
}

.form-group label {
    display: block;
    font-weight: 600;
    font-size: 0.75rem;
    color: var(--gray-700);
    margin-bottom: 4px;
}

.form-group textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    resize: vertical;
    min-height: 60px;
    transition: var(--transition);
}

.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.info-box {
    background: #F0F9FF;
    border: 1px solid #BAE6FD;
    border-radius: 8px;
    padding: 10px 14px;
    margin-bottom: 16px;
    font-size: 0.75rem;
    color: #0369A1;
}

.info-box i {
    margin-right: 6px;
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
    gap: 8px;
    flex-wrap: wrap;
}

.btn-group button {
    padding: 8px 24px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.82rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.btn-group .btn-close {
    background: #6B7280;
    color: white;
}

.btn-group .btn-close:hover {
    background: #4B5563;
}

.btn-group .btn-cancel {
    background: var(--gray-100);
    color: var(--gray-700);
    text-decoration: none;
    padding: 8px 24px;
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

@media (max-width: 768px) {
    .close-card {
        padding: 16px 18px;
    }
    .detail-grid {
        grid-template-columns: 1fr;
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
        <div class="close-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-times-circle" style="color:#6B7280;"></i> Close Incident</h1>
                    <p class="subtitle">
                        <i class="fas fa-exclamation-triangle"></i> 
                        #<?php echo $incident_id; ?> - <?php echo htmlspecialchars($incident['title']); ?>
                    </p>
                </div>
                <div>
                    <span class="status-badge <?php echo $incident['status']; ?>">
                        <span class="dot"></span>
                        <?php echo ucfirst(str_replace('_', ' ', $incident['status'])); ?>
                    </span>
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

            <div class="close-card">
                <div class="card-title"><i class="fas fa-info-circle"></i> Incident Details</div>
                
                <div class="incident-info">
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="label">Type</span>
                            <span class="value"><?php echo $incident_types[$incident['incident_type']] ?? ucfirst($incident['incident_type']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Severity</span>
                            <span class="value"><?php echo ucfirst($incident['severity']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Location</span>
                            <span class="value">
                                <?php echo htmlspecialchars($incident['pu_name'] ?? $incident['ward_name'] ?? 'N/A'); ?>
                            </span>
                        </div>
                        <div class="detail-item" style="grid-column: span 2;">
                            <span class="label">Resolved By</span>
                            <span class="value">
                                <?php echo htmlspecialchars($incident['resolved_first_name'] ?? '') . ' ' . htmlspecialchars($incident['resolved_last_name'] ?? ''); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    Closing this incident will mark it as closed. This action can be reversed by reopening.
                </div>

                <?php if (!empty($incident['resolution_notes'])): ?>
                    <div style="background:#F3F4F6;border-radius:8px;padding:8px 12px;margin-bottom:16px;font-size:0.75rem;">
                        <strong style="color:var(--gray-700);">Resolution Notes:</strong>
                        <p style="margin:4px 0 0;color:var(--gray-600);white-space:pre-wrap;"><?php echo htmlspecialchars($incident['resolution_notes']); ?></p>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label>Closing Notes</label>
                        <textarea name="close_notes" placeholder="Add any final notes about closing this incident..."></textarea>
                    </div>

                    <div class="btn-group">
                        <a href="incidents.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn-close">
                            <i class="fas fa-check"></i> Close Incident
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