<?php
// ============================================================
// STATE COORDINATOR - UPDATE INCIDENT STATUS
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
               u.first_name as reporter_first_name, u.last_name as reporter_last_name,
               pu.name as pu_name, pu.code as pu_code,
               w.name as ward_name, l.name as lga_name,
               e.name as election_name
        FROM incidents i
        LEFT JOIN users u ON i.reporter_id = u.id
        LEFT JOIN polling_units pu ON i.pu_id = pu.id
        LEFT JOIN wards w ON pu.ward_id = w.id
        LEFT JOIN lgas l ON w.lga_id = l.id
        LEFT JOIN elections e ON i.election_id = e.id
        WHERE i.id = ? AND i.tenant_id = ? AND i.state_id = ?
    ");
    $stmt->execute([$incident_id, $tenant_id, $state_id]);
    $incident = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching incident: " . $e->getMessage());
}

if (!$incident) {
    header('Location: incidents.php');
    exit();
}

$message = '';
$error = '';

// Get available statuses
$status_options = [
    'reported' => 'Reported',
    'acknowledged' => 'Acknowledged',
    'investigating' => 'Investigating',
    'resolved' => 'Resolved',
    'escalated' => 'Escalated',
    'closed' => 'Closed',
    'false_alarm' => 'False Alarm'
];

// Get users for assignment
$users = [];
try {
    $stmt = $db->prepare("
        SELECT id, first_name, last_name, email 
        FROM users 
        WHERE tenant_id = ? AND status = 'active' AND deleted_at IS NULL
        ORDER BY first_name ASC
    ");
    $stmt->execute([$tenant_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching users: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'] ?? $incident['status'];
    $assigned_to = isset($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : $incident['assigned_to'];
    $severity = $_POST['severity'] ?? $incident['severity'];
    $update_notes = trim($_POST['update_notes'] ?? '');
    
    try {
        $stmt = $db->prepare("
            UPDATE incidents 
            SET status = ?, assigned_to = ?, severity = ?, updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$status, $assigned_to, $severity, $incident_id, $tenant_id]);
        
        // Add notes if provided
        if (!empty($update_notes)) {
            // Append to resolution_notes or create a notes field
            $notes = $incident['resolution_notes'] ?? '';
            $new_notes = $notes . "\n[" . date('Y-m-d H:i:s') . "] " . $update_notes;
            $stmt = $db->prepare("UPDATE incidents SET resolution_notes = ? WHERE id = ?");
            $stmt->execute([$new_notes, $incident_id]);
        }
        
        logActivity($user_id, 'incident_updated', 
            "Updated incident #$incident_id: Status changed to $status",
            'incidents', $incident_id
        );
        
        $message = "Incident updated successfully!";
        
        // Refresh incident data
        $stmt = $db->prepare("SELECT * FROM incidents WHERE id = ?");
        $stmt->execute([$incident_id]);
        $incident = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = 'Failed to update incident: ' . $e->getMessage();
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

$severity_options = [
    'low' => 'Low',
    'medium' => 'Medium',
    'high' => 'High',
    'critical' => 'Critical'
];

$page_title = 'Update Incident';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.update-container {
    max-width: 700px;
    margin: 0 auto;
}

.update-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 28px;
    margin-bottom: 16px;
}

.update-card .card-title {
    font-size: 0.85rem;
    font-weight: 600;
    margin: 0 0 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-700);
}

.update-card .card-title i {
    color: var(--primary);
    margin-right: 6px;
}

.incident-info {
    background: var(--gray-50);
    border-radius: 10px;
    padding: 14px 18px;
    margin-bottom: 16px;
}

.incident-info .label {
    font-size: 0.65rem;
    color: var(--gray-500);
    display: block;
}

.incident-info .value {
    font-weight: 500;
    color: var(--gray-800);
}

.incident-info .value .status-badge {
    font-size: 0.6rem;
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

.status-badge.reported { background: #FFFBEB; color: #92400E; }
.status-badge.reported .dot { background: #F59E0B; }
.status-badge.acknowledged { background: #EFF6FF; color: #1E40AF; }
.status-badge.acknowledged .dot { background: #3B82F6; }
.status-badge.investigating { background: #F5F3FF; color: #5B21B6; }
.status-badge.investigating .dot { background: #8B5CF6; }
.status-badge.resolved { background: #ECFDF5; color: #065F46; }
.status-badge.resolved .dot { background: #10B981; }
.status-badge.escalated { background: #FEF2F2; color: #991B1B; }
.status-badge.escalated .dot { background: #EF4444; }
.status-badge.closed { background: #F3F4F6; color: #6B7280; }
.status-badge.closed .dot { background: #9CA3AF; }
.status-badge.false_alarm { background: #F3F4F6; color: #6B7280; }
.status-badge.false_alarm .dot { background: #9CA3AF; }

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
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
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
    flex-wrap: wrap;
}

.btn-group button {
    padding: 10px 28px;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.btn-group .btn-update {
    background: var(--primary);
    color: white;
}

.btn-group .btn-update:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}

.btn-group .btn-cancel {
    background: var(--gray-100);
    color: var(--gray-700);
    text-decoration: none;
    padding: 10px 28px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-group .btn-cancel:hover {
    background: var(--gray-200);
}

.detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}

.detail-item {
    font-size: 0.8rem;
}

.detail-item .label {
    color: var(--gray-500);
    font-size: 0.65rem;
    display: block;
}

.detail-item .value {
    font-weight: 500;
    color: var(--gray-800);
}

@media (max-width: 768px) {
    .update-card {
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
        <div class="update-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-edit"></i> Update Incident</h1>
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

            <div class="update-card">
                <div class="card-title"><i class="fas fa-info-circle"></i> Incident Information</div>
                
                <div class="incident-info">
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="label">Type</span>
                            <span class="value"><?php echo $incident_types[$incident['incident_type']] ?? ucfirst($incident['incident_type']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Severity</span>
                            <span class="value">
                                <span class="severity-badge <?php echo $incident['severity']; ?>">
                                    <?php echo ucfirst($incident['severity']); ?>
                                </span>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Location</span>
                            <span class="value">
                                <?php echo htmlspecialchars($incident['pu_name'] ?? $incident['ward_name'] ?? $incident['lga_name'] ?? 'N/A'); ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Election</span>
                            <span class="value"><?php echo htmlspecialchars($incident['election_name'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="detail-item" style="grid-column: span 2;">
                            <span class="label">Reported By</span>
                            <span class="value">
                                <?php echo htmlspecialchars($incident['reporter_first_name'] ?? '') . ' ' . htmlspecialchars($incident['reporter_last_name'] ?? 'Unknown'); ?>
                            </span>
                        </div>
                        <div class="detail-item" style="grid-column: span 2;">
                            <span class="label">Description</span>
                            <span class="value" style="font-weight:400;"><?php echo htmlspecialchars($incident['description']); ?></span>
                        </div>
                    </div>
                </div>

                <form method="POST" action="">
                    <div class="form-group">
                        <label>Status <span class="required">*</span></label>
                        <select name="status" required>
                            <?php foreach ($status_options as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo ($incident['status'] == $key) ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Severity <span class="required">*</span></label>
                        <select name="severity" required>
                            <?php foreach ($severity_options as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo ($incident['severity'] == $key) ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Assign To</label>
                        <select name="assigned_to">
                            <option value="">Unassigned</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo ($incident['assigned_to'] == $user['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                    (<?php echo htmlspecialchars($user['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Update Notes</label>
                        <textarea name="update_notes" placeholder="Describe what action was taken or any new information..."></textarea>
                    </div>

                    <div class="btn-group">
                        <a href="incidents.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn-update">
                            <i class="fas fa-save"></i> Update Incident
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